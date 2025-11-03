import argparse
import concurrent.futures
import logging
import os
import random
import threading
import time
import zoneinfo
from contextlib import contextmanager
from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from typing import Any, Dict, List, Optional, Set, Tuple

import mysql.connector
import pytz
from mysql.connector import pooling
from tqdm import tqdm

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(name)s - %(levelname)s - [%(processName)s] %(message)s",
    handlers=[
        logging.FileHandler("centreon_backfill.log", encoding="utf-8"),
        logging.StreamHandler(),
    ],
)
logger = logging.getLogger(__name__)

TZ_INFO = zoneinfo.ZoneInfo("UTC")


# Centralized configuration
@dataclass
class TableConfig:
    name: str
    batch_size: int = 15000
    fetch_limit: int = 500000
    num_raws_returned: int = 1
    priority_date_cols: List[str] = None
    unique_key_strategy: List[str] = None


TABLES_CONFIG = [
    TableConfig(
        "mod_bam_reporting_ba_availabilities",
        priority_date_cols=["time_id", "start_time"],
        unique_key_strategy=["ba_id", "time_id"],
    ),
    TableConfig(
        "mod_bam_reporting_ba_events_durations",
        priority_date_cols=["start_time", "end_time"],
        unique_key_strategy=["ba_id", "start_time", "end_time"],
    ),
    TableConfig(
        "mod_bam_reporting_ba_events",
        priority_date_cols=["start_time", "end_time"],
        unique_key_strategy=["ba_id", "start_time"],
    ),
    TableConfig(
        "mod_bam_reporting_kpi_events",
        priority_date_cols=["start_time", "end_time"],
        unique_key_strategy=["kpi_id", "start_time"],
    ),
    TableConfig(
        "mod_bam_reporting_status",
        priority_date_cols=["timestamp"],
        unique_key_strategy=["ba_id", "timestamp"],
    ),
    TableConfig(
        "mod_bi_hostavailability",
        priority_date_cols=["time_id"],
        unique_key_strategy=[
            "modbihost_id",
            "liveservice_id",
            "time_id",
        ],
    ),
    TableConfig(
        "mod_bi_serviceavailability",
        priority_date_cols=["time_id"],
        unique_key_strategy=[
            "modbiservice_id",
            "liveservice_id",
            "time_id",
        ],
    ),
    TableConfig(
        "mod_bi_hoststateevents",
        priority_date_cols=["start_time", "end_time"],
        unique_key_strategy=[
            "modbihost_id",
            "modbiliveservice_id",
            "start_time",
            "end_time",
        ],
    ),
    TableConfig(
        "mod_bi_servicestateevents",
        priority_date_cols=["start_time", "end_time"],
        unique_key_strategy=[
            "modbiservice_id",
            "modbiliveservice_id",
            "start_time",
            "end_time",
        ],
    ),
    TableConfig(
        "mod_bi_metricdailyvalue",
        priority_date_cols=["time_id"],
        unique_key_strategy=[
            "servicemetric_id",
            "liveservice_id",
            "time_id",
        ],
    ),
    TableConfig(
        "mod_bi_metrichourlyvalue",
        priority_date_cols=["time_id"],
        unique_key_strategy=[
            "servicemetric_id",
            "time_id",
        ],
    ),
    TableConfig(
        "mod_bi_hgmonthavailability",
        priority_date_cols=["time_id"],
        unique_key_strategy=[
            "modbihg_id",
            "modbihc_id",
            "time_id",
            "liveservice_id",
        ],
    ),
    TableConfig(
        "mod_bi_metricentiledailyvalue",
        priority_date_cols=["time_id"],
        unique_key_strategy=[
            "servicemetric_id",
            "time_id",
            "liveservice_id",
        ],
    ),
    TableConfig(
        "mod_bi_metricentileweeklyvalue",
        priority_date_cols=["time_id"],
        unique_key_strategy=[
            "servicemetric_id",
            "time_id",
            "liveservice_id",
        ],
    ),
    TableConfig(
        "mod_bi_metricentilemonthlyvalue",
        priority_date_cols=["time_id"],
        unique_key_strategy=[
            "servicemetric_id",
            "time_id",
            "liveservice_id",
        ],
    ),
]

TIME_COLUMNS_CANDIDATES = [
    "time_id",
    "start_time",
    "end_time",
    "utime",
    "dtime",
    "ctime",
    "timestamp",
]

SOURCE_START = datetime(2025, 5, 1)
today = datetime.today()
SOURCE_END = datetime(today.year, today.month, today.day)
# SOURCE_END = datetime(2025, 6, 1)
COVERAGE_MONTHS = 6
MAX_RETRIES = 3
POOL_SIZE = 8
CONNECTION_TIMEOUT = 60
MAX_WORKERS = 4

thread_local = threading.local()


class Config:
    def __init__(self, args: argparse.Namespace):
        self.host = args.host
        self.port = args.port
        self.user = args.user
        self.password = args.password
        self.database = args.database
        self.inject = args.inject
        self.dry_run = args.dry_run
        self.parallel = args.parallel
        self.use_fake_data = args.fake_data
        self.db_central = args.db_central
        self.metric_name = args.metric_name
        self.truncate = args.truncate

        self.pool_config = {
            "pool_name": "centreon_pool",
            "pool_size": POOL_SIZE,
            "pool_reset_session": True,
            "host": self.host,
            "port": self.port,
            "user": self.user,
            "password": self.password,
            "database": self.database,
            "autocommit": True,
            "connection_timeout": CONNECTION_TIMEOUT,
            "charset": "utf8mb4",
            "collation": "utf8mb4_general_ci",
            "use_unicode": True,
            "raise_on_warnings": False,
        }


class ConnectionManager:
    """Thread-safe connection pool manager."""

    def __init__(self, config: Config):
        self.config = config
        self._pool = None
        self._lock = threading.Lock()

    def get_pool(self):
        if self._pool is None:
            with self._lock:
                if self._pool is None:
                    try:
                        self._pool = pooling.MySQLConnectionPool(
                            **self.config.pool_config
                        )
                        logger.info(
                            f"Connection pool created with {POOL_SIZE} connections"
                        )
                    except mysql.connector.Error as e:
                        logger.error(f"Pool creation error: {e}")
                        raise
        return self._pool

    @contextmanager
    def get_connection(self):
        """Context manager for getting a connection from the pool."""
        pool = self.get_pool()
        conn = None
        try:
            for attempt in range(MAX_RETRIES):
                try:
                    conn = pool.get_connection()
                    yield conn
                    break
                except mysql.connector.Error as e:
                    if attempt == MAX_RETRIES - 1:
                        logger.error(
                            f"Unable to get connection after {MAX_RETRIES} attempts: {e}"
                        )
                        raise
                    time.sleep(2**attempt)
                    continue
        finally:
            if conn and conn.is_connected():
                conn.close()


def get_table_config(table_name: str) -> TableConfig:
    """Retrieve table configuration."""
    for config in TABLES_CONFIG:
        if config.name == table_name:
            return config
    return TableConfig(table_name)


def is_monthly_table(table_name: str) -> bool:
    """Determine if a table contains monthly data."""
    return "month" in table_name.lower()


def is_hourly_table(table_name: str) -> bool:
    """Determine if a table contains hourly data."""
    return "hourly" in table_name.lower()


def add_months(dt: datetime, months: int) -> datetime:
    """Add months to a date robustly."""
    month = dt.month - 1 + months
    year = dt.year + month // 12
    month = month % 12 + 1

    import calendar

    day = min(dt.day, calendar.monthrange(year, month)[1])

    return dt.replace(year=year, month=month, day=day)


def generate_expected_dates(granularity: str) -> List[datetime]:
    """Generate expected dates based on granularity."""
    today = datetime.now(tz=TZ_INFO).replace(hour=0, minute=0, second=0, microsecond=0)
    dates = []

    if granularity == "month":
        start = today.replace(day=1)
        for i in range(COVERAGE_MONTHS):
            start = add_months(start, -1)

        current = start
        while current <= today:
            dates.append(current)
            current = add_months(current, 1)
    elif granularity == "hour":
        hours_to_cover = 24 * 30 * 1
        start = today - timedelta(hours=hours_to_cover)
        start = start.replace(minute=0, second=0, microsecond=0)
        current = start
        while current <= today:
            dates.append(current)
            current += timedelta(hours=1)
    else:
        start = today - timedelta(days=30 * COVERAGE_MONTHS)
        current = start
        while current <= today:
            dates.append(current)
            current += timedelta(days=1)

    return dates


class TableAnalyzer:
    """Class for analyzing table structure."""

    def __init__(self, connection_manager: ConnectionManager):
        self.conn_mgr = connection_manager
        self._schema_cache = {}

    def get_table_schema(self, table: str) -> Dict:
        """Retrieve and cache table schema."""
        if table not in self._schema_cache:
            with self.conn_mgr.get_connection() as conn:
                cursor = conn.cursor(dictionary=True)
                schema = self._analyze_table_structure(cursor, table)
                self._schema_cache[table] = schema
        return self._schema_cache[table]

    def _analyze_table_structure(self, cursor, table: str) -> Dict:
        """Complete analysis of table structure."""
        schema = {
            "columns": [],
            "date_columns": [],
            "primary_key": [],
            "auto_increment": [],
            "indexes": {},
            "primary_date_column": None,
        }

        try:
            cursor.execute(f"SHOW COLUMNS FROM `{table}`")
            columns_info = cursor.fetchall()

            for col in columns_info:
                field = col["Field"]
                schema["columns"].append(field)

                if col["Key"] == "PRI":
                    schema["primary_key"].append(field)
                if "auto_increment" in col["Extra"].lower():
                    schema["auto_increment"].append(field)
                if field in TIME_COLUMNS_CANDIDATES:
                    schema["date_columns"].append(field)

            # Primary date column detection
            config = get_table_config(table)
            if config.priority_date_cols:
                for col in config.priority_date_cols:
                    if col in schema["date_columns"]:
                        schema["primary_date_column"] = col
                        break

            if not schema["primary_date_column"] and schema["date_columns"]:
                schema["primary_date_column"] = schema["date_columns"][0]

            # Indexes (for query optimization)
            cursor.execute(f"SHOW INDEX FROM `{table}`")
            indexes = cursor.fetchall()
            for idx in indexes:
                key_name = idx["Key_name"]
                if key_name not in schema["indexes"]:
                    schema["indexes"][key_name] = []
                schema["indexes"][key_name].append(idx["Column_name"])

            logger.debug(
                f"[{table}] Schema analyzed: {len(schema['columns'])} columns, "
                f"primary date: {schema['primary_date_column']}"
            )

        except mysql.connector.Error as e:
            logger.error(f"[{table}] Structure analysis error: {e}")

        return schema

    def get_existing_partitions(self, table: str) -> List[int]:
        """
        Retourne la liste des valeurs LESS THAN des partitions existantes.
        """
        partitions = []
        with self.conn_mgr.get_connection() as conn:
            cursor = conn.cursor(dictionary=True)
            cursor.execute(
                """
                SELECT PARTITION_NAME, PARTITION_DESCRIPTION
                FROM INFORMATION_SCHEMA.PARTITIONS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = %s
                AND PARTITION_DESCRIPTION IS NOT NULL
                ORDER BY PARTITION_DESCRIPTION
            """,
                (table,),
            )
            for row in cursor.fetchall():
                try:
                    val = int(row["PARTITION_DESCRIPTION"])
                    partitions.append(val)
                except (ValueError, TypeError):
                    continue
        return sorted(partitions)

    def create_partition_for_value(
        self, table: str, target_value: int, step: int = 86400
    ):
        """
        Crée une partition pour couvrir target_value si aucune ne le couvre.
        """
        existing = self.get_existing_partitions(table)
        if any(target_value < p for p in existing):
            logger.debug(
                f"[{table}] A partition already exists for value {target_value}."
            )
            return

        next_upper = ((target_value // step) + 1) * step
        partition_name = f"p{next_upper}"

        logger.info(
            f"[{table}] Creating partition {partition_name} for target_value {target_value}, next_upper: {next_upper}"
        )

        alter_sql = f"""
            ALTER TABLE `{table}`
            ADD PARTITION (
                PARTITION {partition_name}
                VALUES LESS THAN ({next_upper})
            )
        """
        with self.conn_mgr.get_connection() as conn:
            cursor = conn.cursor()
            try:
                cursor.execute(alter_sql)
                conn.commit()
                logger.info(
                    f"[{table}] Partition '{partition_name}' created for value < {next_upper}"
                )
            except mysql.connector.Error as e:
                logger.error(
                    f"[{table}] Failed to create partition for value {target_value}: {e}"
                )
                return

    def ensure_partitions_for_range(self, table: str, min_value, max_value):
        """
        Crée toutes les partitions journalières manquantes pour couvrir les valeurs
        entre min_value et max_value (timestamps ou datetime).
        """
        # Normaliser en datetime
        if isinstance(min_value, (int, float)):
            min_value = datetime.utcfromtimestamp(min_value)
        if isinstance(max_value, (int, float)):
            max_value = datetime.utcfromtimestamp(max_value)

        current = min_value.date()
        end = max_value.date()

        logger.info(f"[{table}] Checking partitions for range {current} -> {end}")

        while current <= end:
            # Prendre le timestamp de minuit pour ce jour
            ts = int(
                datetime.combine(current, datetime.min.time())
                .replace(tzinfo=timezone.utc)
                .timestamp()
            )
            self.create_partition_for_value(table, ts, step=86400)
            current += timedelta(days=1)

    def add_primary_key_to_hoststateevents(conn_mgr):
        """
        Ajoute la clé primaire composite sur mod_bi_hoststateevents.
        Priorité absolue pour accélérer les inserts et garantir l'unicité.
        """
        table = "mod_bi_hoststateevents"

        logger.info(
            f"[{table}] Adding primary key on (modbihost_id, modbiliveservice_id, start_time, end_time)"
        )

        alter_sql = """
            ALTER TABLE mod_bi_hoststateevents
            ADD PRIMARY KEY (modbihost_id, modbiliveservice_id, start_time, end_time)
        """

        with conn_mgr.get_connection() as conn:
            cursor = conn.cursor()
            try:
                cursor.execute(alter_sql)
                conn.commit()
                logger.info(f"[{table}] Primary key added successfully")
            except mysql.connector.Error as e:
                if e.errno == 1068:  # Multiple primary key defined
                    logger.warning(f"[{table}] Primary key already exists: {e}")
                elif e.errno == 1062:  # Duplicate entry
                    logger.error(
                        f"[{table}] Duplicate entries found, cannot add primary key: {e}"
                    )
                else:
                    logger.error(f"[{table}] Failed to add primary key: {e}")
                conn.rollback()

    def add_index_to_servicestateevents(conn_mgr, use_primary_key=True):
        """
        Ajoute un index ou une clé primaire composite sur mod_bi_servicestateevents.
        Priorité absolue pour accélérer les inserts et garantir l'unicité.

        Args:
            conn_mgr: Connection manager
            use_primary_key: Si True, crée une clé primaire. Si False, crée un index unique.
        """
        table = "mod_bi_servicestateevents"

        if use_primary_key:
            logger.info(
                f"[{table}] Adding primary key on (modbiservice_id, modbiliveservice_id, start_time, end_time)"
            )
            alter_sql = """
                ALTER TABLE mod_bi_servicestateevents
                ADD PRIMARY KEY (modbiservice_id, modbiliveservice_id, start_time, end_time)
            """
        else:
            logger.info(
                f"[{table}] Adding unique index on (modbiservice_id, modbiliveservice_id, start_time, end_time)"
            )
            alter_sql = """
                CREATE UNIQUE INDEX idx_servicestateevents_composite
                ON mod_bi_servicestateevents (modbiservice_id, modbiliveservice_id, start_time, end_time)
            """

        with conn_mgr.get_connection() as conn:
            cursor = conn.cursor()
            try:
                cursor.execute(alter_sql)
                conn.commit()
                index_type = "Primary key" if use_primary_key else "Index"
                logger.info(f"[{table}] {index_type} added successfully")
            except mysql.connector.Error as e:
                if e.errno == 1068:  # Multiple primary key defined
                    logger.warning(f"[{table}] Primary key already exists: {e}")
                elif e.errno == 1061:  # Duplicate key name
                    logger.warning(f"[{table}] Index already exists: {e}")
                elif e.errno == 1062:  # Duplicate entry
                    logger.error(
                        f"[{table}] Duplicate entries found, cannot add unique constraint: {e}"
                    )
                else:
                    logger.error(f"[{table}] Failed to add index/primary key: {e}")
                conn.rollback()


class DataProcessor:
    """Class for data processing."""

    def __init__(
        self,
        connection_manager: ConnectionManager,
        analyzer: TableAnalyzer,
        config: Config,
    ):
        self.conn_mgr = connection_manager
        self.analyzer = analyzer
        self.config = config
        self.metric_name = config.metric_name

        ##(Optionnal) To fill some tables
        self.fill_hostname_for_service()
        self.fill_hostname_for_host()

    def debug_dates_comparison(self, table: str):
        """Debug function to compare expected vs existing dates"""
        granularity = (
            "month"
            if is_monthly_table(table)
            else "hour"
            if is_hourly_table(table)
            else "day"
        )

        expected_dates = generate_expected_dates(granularity)
        existing_dates = self.fetch_existing_dates(table, granularity)
        missing_dates = sorted(set(expected_dates) - existing_dates)

        logger.info(f"[{table}] DEBUG - Granularity: {granularity}")
        logger.info(
            f"[{table}] DEBUG - Expected dates range: {min(expected_dates)} to {max(expected_dates)}"
        )
        logger.info(f"[{table}] DEBUG - Expected count: {len(expected_dates)}")
        logger.info(f"[{table}] DEBUG - Existing count: {len(existing_dates)}")
        logger.info(f"[{table}] DEBUG - Missing count: {len(missing_dates)}")

        if existing_dates:
            existing_list = sorted(list(existing_dates))
            logger.info(
                f"[{table}] DEBUG - Existing range: {existing_list[0]} to {existing_list[-1]}"
            )

        if missing_dates:
            logger.info(f"[{table}] DEBUG - First missing dates: {missing_dates[:5]}")
            logger.info(f"[{table}] DEBUG - Last missing dates: {missing_dates[-5:]}")

    def fetch_existing_dates(self, table: str, granularity: str) -> Set[datetime]:
        """Fetch existing dates in the table optimally."""
        schema = self.analyzer.get_table_schema(table)
        date_col = schema["primary_date_column"]

        if not date_col:
            return set()

        with self.conn_mgr.get_connection() as conn:
            cursor = conn.cursor(dictionary=True)

            try:
                today = datetime.now(tz=TZ_INFO)
                if granularity == "month":
                    cutoff_date = today - timedelta(days=30 * (COVERAGE_MONTHS + 2))
                elif granularity == "hour":
                    cutoff_date = today - timedelta(
                        hours=24 * 30 * COVERAGE_MONTHS + 24
                    )
                else:  # daily
                    cutoff_date = today - timedelta(days=30 * COVERAGE_MONTHS + 30)

                cutoff_timestamp = int(cutoff_date.timestamp())

                logger.info(
                    f"[{table}] Fetching existing dates since {cutoff_date} (timestamp: {cutoff_timestamp})"
                )

                if granularity == "month":
                    query = f"""
                        SELECT DISTINCT 
                            YEAR(FROM_UNIXTIME({date_col})) as year, 
                            MONTH(FROM_UNIXTIME({date_col})) as month 
                        FROM `{table}` 
                        WHERE {date_col} IS NOT NULL 
                        AND {date_col} > 0
                        AND {date_col} > %s
                        ORDER BY year, month
                    """
                    cursor.execute(query, (cutoff_timestamp,))
                    results = cursor.fetchall()
                    existing_dates = set()
                    for r in results:
                        if r["year"] and r["month"]:
                            # Créer en UTC mais à minuit le 1er du mois
                            dt = datetime(
                                r["year"], r["month"], 1, 0, 0, 0, tzinfo=TZ_INFO
                            )
                            existing_dates.add(dt)
                            logger.debug(f"[{table}] Found existing month: {dt}")
                    return existing_dates

                elif granularity == "hour":
                    query = f"""
                        SELECT DISTINCT 
                            YEAR(FROM_UNIXTIME({date_col})) as year,
                            MONTH(FROM_UNIXTIME({date_col})) as month,
                            DAY(FROM_UNIXTIME({date_col})) as day,
                            HOUR(FROM_UNIXTIME({date_col})) as hour
                        FROM `{table}` 
                        WHERE {date_col} IS NOT NULL 
                        AND {date_col} > 0
                        AND {date_col} > %s
                        ORDER BY year, month, day, hour
                    """
                    cursor.execute(query, (cutoff_timestamp,))
                    results = cursor.fetchall()
                    existing_dates = set()
                    for r in results:
                        if (
                            r["year"]
                            and r["month"]
                            and r["day"]
                            and r["hour"] is not None
                        ):
                            dt = datetime(
                                r["year"],
                                r["month"],
                                r["day"],
                                r["hour"],
                                0,
                                0,
                                tzinfo=TZ_INFO,
                            )
                            existing_dates.add(dt)
                            logger.debug(f"[{table}] Found existing hour: {dt}")
                    return existing_dates

                else:  # daily
                    query = f"""
                        SELECT DISTINCT 
                            YEAR(FROM_UNIXTIME({date_col})) as year,
                            MONTH(FROM_UNIXTIME({date_col})) as month,
                            DAY(FROM_UNIXTIME({date_col})) as day
                        FROM `{table}` 
                        WHERE {date_col} IS NOT NULL 
                        AND {date_col} > 0
                        AND {date_col} > %s
                        ORDER BY year, month, day
                    """
                    cursor.execute(query, (cutoff_timestamp,))
                    results = cursor.fetchall()
                    existing_dates = set()
                    for r in results:
                        if r["year"] and r["month"] and r["day"]:
                            dt = datetime(
                                r["year"], r["month"], r["day"], 0, 0, 0, tzinfo=TZ_INFO
                            )
                            existing_dates.add(dt)
                            logger.debug(f"[{table}] Found existing day: {dt}")

                    logger.info(
                        f"[{table}] Total existing {granularity} records found: {len(existing_dates)}"
                    )
                    if existing_dates:
                        existing_list = sorted(list(existing_dates))
                        logger.info(
                            f"[{table}] Existing date range: {existing_list[0]} to {existing_list[-1]}"
                        )

                    return existing_dates

            except mysql.connector.Error as e:
                logger.error(f"[{table}] Error fetching existing dates: {e}")
                return set()

    def change_date_into_row(
        self, table: str, target_date: datetime, source_date: datetime
    ) -> List[Dict]:
        """Fetch and transform source data."""
        config = get_table_config(table)
        schema = self.analyzer.get_table_schema(table)
        date_col = schema["primary_date_column"]

        if not date_col:
            return []

        with self.conn_mgr.get_connection() as conn:
            cursor = conn.cursor(dictionary=True)

            try:
                # Generate fake data
                if self.config.use_fake_data:
                    source_rows = self._generate_fake_rows_by_date(table, source_date)

                # Fetch real data
                else:
                    source_rows = self._fetch_rows_by_date(
                        cursor, table, date_col, source_date, config.fetch_limit
                    )

                if not source_rows:
                    return []

                # Transform data
                transformed_rows = []
                for row in source_rows:
                    new_row = self._transform_row(row, schema, target_date)

                    if new_row and not self._row_exists(cursor, table, new_row, schema):
                        transformed_rows.append(new_row)

                return transformed_rows

            except mysql.connector.Error as e:
                logger.error(
                    f"[{table}] Error fetching source data for {source_date}: {e}"
                )
                return []

    def _fetch_rows_by_date(
        self, cursor, table: str, date_col: str, target_date: datetime, limit: int
    ) -> List[Dict]:
        """Fetch rows for a given date."""

        try:
            utc = pytz.UTC
            local_tz = pytz.timezone("UTC")

            if is_monthly_table(table):
                local_start = local_tz.localize(
                    datetime(target_date.year, target_date.month, 1)
                )
                start_ts = int(local_start.astimezone(utc).timestamp())
                query = f"SELECT * FROM `{table}` WHERE {date_col} = %s LIMIT %s"
                cursor.execute(query, (start_ts, limit))

            else:
                local_start = local_tz.localize(
                    datetime(
                        target_date.year, target_date.month, target_date.day, 0, 0, 0
                    )
                )
                local_end = local_tz.localize(
                    datetime(
                        target_date.year, target_date.month, target_date.day, 23, 59, 59
                    )
                )
                start_ts = int(local_start.astimezone(utc).timestamp())
                end_ts = int(local_end.astimezone(utc).timestamp())
                query = f"SELECT * FROM `{table}` WHERE {date_col} BETWEEN %s AND %s LIMIT %s"
                cursor.execute(query, (start_ts, end_ts, limit))

            rows = cursor.fetchall()
            if len(rows) == limit:
                logger.warning(
                    f"[{table}] Limit of {limit} rows reached / {target_date}"
                )

            return rows

        except mysql.connector.Error as e:
            logger.error(f"[{table}] Error fetching rows for {target_date}: {e}")
            return []

    def _fetch_servicemetrics_ids(self, metric_name: str) -> List[Dict]:
        """
        Fetch servicemetric_id from mod_bi_servicemetrics
        filtered by a specific metric_name.
        """
        ids = []

        query = """
            SELECT id
            FROM mod_bi_servicemetrics
            WHERE metric_name = %s
            # AND host_name LIKE "host_name_1%"
            # AND service_description = "service_name_677"
            # LIMIT 1000
        """

        try:
            with self.conn_mgr.get_connection() as conn:
                cursor = conn.cursor(dictionary=True)
                cursor.execute(query, (metric_name,))
                rows = cursor.fetchall()
                ids = [row["id"] for row in rows]
        except Exception as e:
            logger.error(
                f"Failed to fetch servicemetric IDs for metric '{metric_name}': {e}"
            )

        return ids

    def _fetch_ba_ids(self) -> List[int]:
        """
        Fetch all ba_id from mod_bam_reporting_ba.
        """
        ids = []

        query = """
            SELECT ba_id
            FROM mod_bam_reporting_ba
            WHERE ba_id IS NOT NULL
        """

        try:
            with self.conn_mgr.get_connection() as conn:
                cursor = conn.cursor(dictionary=True)
                cursor.execute(query)
                rows = cursor.fetchall()
                ids = [row["ba_id"] for row in rows]
        except Exception as e:
            logger.error(f"Failed to fetch ba_ids from mod_bam_reporting_ba: {e}")

        return ids

    def _fetch_host_ids(self) -> List[int]:
        """
        Fetch all modbihost_id from mod_bi_host.
        """
        ids = []

        query = """
            SELECT id
            FROM mod_bi_hosts
            WHERE id IS NOT NULL
        """

        try:
            with self.conn_mgr.get_connection() as conn:
                cursor = conn.cursor(dictionary=True)
                cursor.execute(query)
                rows = cursor.fetchall()
                ids = [row["id"] for row in rows]
        except Exception as e:
            logger.error(f"Failed to fetch modbihost_ids from mod_bi_host: {e}")

        return ids

    def _fetch_services_ids(self) -> List[int]:
        """
        Fetch all modbiservice_id from mod_bi_service.
        """
        ids = []

        query = """
            SELECT id
            FROM mod_bi_services
            WHERE id IS NOT NULL
        """

        try:
            with self.conn_mgr.get_connection() as conn:
                cursor = conn.cursor(dictionary=True)
                cursor.execute(query)
                rows = cursor.fetchall()
                ids = [row["id"] for row in rows]
        except Exception as e:
            logger.error(f"Failed to fetch modbiservice_ids from mod_bi_services: {e}")

        return ids

    def _generate_metric_values(self) -> Dict[str, float]:
        """Generate consistent metric values."""
        min_val = round(random.uniform(0.01, 10), 6)
        max_val = round(random.uniform(50, 150), 6)
        first_val = round(random.uniform(min_val, max_val), 6)
        last_val = round(random.uniform(min_val, max_val), 6)
        avg_val = round((min_val + max_val + first_val + last_val) / 4, 7)

        return {
            "avg_value": avg_val,
            "min_value": min_val,
            "max_value": max_val,
            "first_value": first_val,
            "last_value": last_val,
        }

    def _create_monthly_row(self, table: str, time_id: int, ids: int) -> Dict:
        """Create a monthly row based on table type."""
        if "hgmonthavailability" in table.lower():
            return {
                "modbihost_id": ids,
                "time_id": time_id,
                "liveservice_id": 1,
                "available": 60000,
                "unavailable": 0,
                "unreachable": 0,
                "alert_unavailable_opened": 0,
                "alert_unavailable_closed": 0,
                "alert_unreachable_opened": 0,
                "alert_unreachable_closed": 0,
            }
        elif "hgservicemonthavailability" in table.lower():
            return {
                "modbiservice_id": ids,
                "time_id": time_id,
                "liveservice_id": 1,
                "available": random.randint(30000, 60000),
                "unavailable": random.randint(0, 100),
                "degraded": random.randint(0, 50),
                "alert_unavailable_opened": random.randint(0, 2),
                "alert_unavailable_closed": random.randint(0, 2),
                "alert_unreachable_opened": random.randint(0, 2),
                "alert_unreachable_closed": random.randint(0, 2),
            }
        elif "metric" in table.lower() and "centile" not in table.lower():
            metric_values = self._generate_metric_values()
            return {
                "servicemetric_id": ids,
                "time_id": time_id,
                "liveservice_id": 1,
                **metric_values,
                "total": round(random.uniform(90, 150), 6),
                "warning_treshold": 200.0,
                "critical_treshold": 400.0,
            }

        return None

    def _create_daily_row(
        self, table: str, time_id: int, ids: int, resource: str
    ) -> Dict:
        """Create a daily row based on table type."""
        if "hostavailability" in table.lower():
            return {
                f"modbi{resource}_id": ids,
                "time_id": time_id,
                "liveservice_id": 1,
                "available": random.randint(30000, 60000),
                "unavailable": random.randint(
                    10000, 50000
                ),  # "unavailable": 1000000000,
                "unreachable": random.randint(
                    5000, 20000
                ),  # "unreachable": 1000000000,
                "alert_unavailable_opened": random.randint(0, 200),
                "alert_unavailable_closed": random.randint(0, 200),
                "alert_unreachable_opened": random.randint(0, 200),
                "alert_unreachable_closed": random.randint(0, 200),
            }
        elif "serviceavailability" in table.lower():
            return {
                f"modbi{resource}_id": ids,
                "time_id": time_id,
                "liveservice_id": 1,
                "available": random.randint(30000, 60000),
                "unavailable": random.randint(10000, 50000),
                "degraded": random.randint(0, 50),
                "alert_unavailable_opened": random.randint(0, 2),
                "alert_unavailable_closed": random.randint(0, 2),
                "alert_unreachable_opened": random.randint(0, 2),
                "alert_unreachable_closed": random.randint(0, 2),
            }
        elif "stateevents" in table.lower():
            return {
                f"modbi{resource}_id": ids,
                "modbiliveservice_id": 1,
                "state": random.randint(0, 3),
                "start_time": time_id,
                "end_time": time_id + random.randint(60, 200),
                "duration": random.randint(60, 600),
                "sla_duration": random.randint(75, 100),
                "ack_time": random.randint(0, 100),
                "last_update": 1,
            }
        elif "ba_avail" in table.lower():
            return {
                "ba_id": ids,
                "time_id": time_id,
                "timeperiod_id": 1,
                "available": random.randint(60000, 86400),
                "unavailable": random.randint(0, 10000),
                "degraded": random.randint(0, 5000),
                "unknown": random.randint(0, 500),
                "downtime": random.randint(0, 3000),
                "alert_unavailable_opened": random.randint(0, 3),
                "alert_degraded_opened": random.randint(0, 3),
                "alert_unknown_opened": random.randint(0, 3),
                "nb_downtime": random.randint(0, 2),
                "timeperiod_is_default": 1,
            }
        elif "metric" in table.lower() and "centile" not in table.lower():
            metric_values = self._generate_metric_values()
            data = {
                "servicemetric_id": ids,
                "time_id": time_id,
                "liveservice_id": 1,
                **metric_values,
                "total": round(random.uniform(90, 150), 6),
                "warning_treshold": 200.0,
                "critical_treshold": 400.0,
            }
            return data

        return None

    def _create_hourly_row(self, table: str, time_id: int, ids: int) -> Dict:
        """Create an hourly row based on table type."""
        if "metric" in table.lower():
            metric_values = self._generate_metric_values()
            data = {
                "servicemetric_id": ids,
                "time_id": time_id,
                "liveservice_id": 1,
                **metric_values,
                "total": round(random.uniform(90, 150), 6),
                "warning_treshold": 50.0,
                "critical_treshold": 100.0,
            }
            return data

        return None

    def _generate_fake_rows_by_date(
        self, table: str, target_date: datetime
    ) -> List[Dict]:
        """Generate fake daily/monthly rows for availability or metric"""
        utc = pytz.UTC
        fake_rows = []
        l_ids_metrics = []
        l_ids_bas = []
        metric_name = self.config.metric_name
        l_ids_metrics = self._fetch_servicemetrics_ids(metric_name)
        l_ids_bas = self._fetch_ba_ids()
        l_ids_hosts = self._fetch_host_ids()
        l_ids_services = self._fetch_services_ids()

        ##### Monthly
        if is_monthly_table(table):
            base_time = utc.localize(datetime(target_date.year, target_date.month, 1))
            time_id = int(base_time.timestamp())

            if "metric" in table.lower():
                for ids in l_ids_metrics:
                    fake_row = self._create_daily_row(table, time_id, ids, "metric")
                    if fake_row:
                        fake_rows.append(fake_row)

        ##### Hourly
        elif is_hourly_table(table):
            if target_date.tzinfo is None:
                base_time = target_date.replace(tzinfo=pytz.UTC)
            else:
                base_time = target_date.astimezone(pytz.UTC)
            time_id = int(base_time.timestamp())

            if "metric" in table.lower() and "centile" not in table.lower():
                for ids in l_ids_metrics:
                    fake_row = self._create_hourly_row(table, time_id, ids)
                    if fake_row:
                        fake_rows.append(fake_row)

        ##### Daily
        else:
            base_time = utc.localize(
                datetime(target_date.year, target_date.month, target_date.day)
            )
            time_id = int((base_time).timestamp())

            if "hostavailability" in table.lower():
                for ids in l_ids_hosts:
                    fake_row = self._create_daily_row(table, time_id, ids, "host")
                    if fake_row:
                        fake_rows.append(fake_row)

            if "serviceavailability" in table.lower():
                for ids in l_ids_services:
                    fake_row = self._create_daily_row(table, time_id, ids, "service")
                    if fake_row:
                        fake_rows.append(fake_row)

            if "servicestateevents" in table.lower():
                for ids in l_ids_services:
                    fake_row = self._create_daily_row(table, time_id, ids, "service")
                    if fake_row:
                        fake_rows.append(fake_row)

            if "hoststateevents" in table.lower():
                for ids in l_ids_services:
                    fake_row = self._create_daily_row(table, time_id, ids, "host")
                    if fake_row:
                        fake_rows.append(fake_row)

            if "_ba_" in table.lower():
                for ids in l_ids_bas:
                    fake_row = self._create_daily_row(table, time_id, ids, "ba")
                    if fake_row:
                        fake_rows.append(fake_row)

            if "metric" in table.lower() and "centile" not in table.lower():
                for ids in l_ids_metrics:
                    fake_row = self._create_daily_row(table, time_id, ids, "metric")
                    if fake_row:
                        fake_rows.append(fake_row)

        return fake_rows

    def _transform_row(
        self, row: Dict, schema: Dict, target_date: datetime
    ) -> Optional[Dict]:
        """Transform a row by adjusting dates."""
        new_row = dict(row)

        # Remove auto-increment
        exclude_cols = set(schema.get("auto_increment", []))
        for col in exclude_cols:
            new_row.pop(col, None)

        # Transform dates
        for col in schema["date_columns"]:
            if col in new_row and new_row[col] is not None:
                try:
                    if isinstance(new_row[col], int):
                        new_row[col] = self.normalize_unix_to_day(
                            int(target_date.timestamp())
                        )
                    elif isinstance(new_row[col], datetime):
                        new_row[col] = self.normalize_unix_to_day(
                            int(target_date.timestamp())
                        )
                except (ValueError, TypeError) as e:
                    logger.debug(f"Cannot transform date for {col}: {e}")
                    continue

        return new_row

    def _row_exists(self, cursor, table: str, row: Dict, schema: Dict) -> bool:
        """Check if a row already exists."""
        config = get_table_config(table)

        if config.unique_key_strategy:
            compare_cols = [col for col in config.unique_key_strategy if col in row]
        else:
            compare_cols = [
                col for col in row.keys() if col not in TIME_COLUMNS_CANDIDATES
            ][:3]

        if not compare_cols:
            return False

        try:
            where_clause = " AND ".join([f"`{col}` = %s" for col in compare_cols])
            query = f"SELECT 1 FROM `{table}` WHERE {where_clause} LIMIT 1"
            values = tuple(row[col] for col in compare_cols)
            cursor.execute(query, values)
            return cursor.fetchone() is not None
        except mysql.connector.Error as e:
            logger.debug(f"[{table}] Error checking existence: {e}")
            return False

    def get_host_by_id(self, host_id: int) -> Optional[Dict[str, Any]]:
        """Fetch host_id and host_name from the 'host' table for a given host_id."""
        with self.conn_mgr.get_connection() as conn:
            cursor = conn.cursor(dictionary=True)

            try:
                query = """
                    SELECT host_id, name
                    FROM hosts
                    WHERE host_id = %s
                """
                cursor.execute(query, (host_id,))
                result = cursor.fetchone()
                return result if result else None

            except mysql.connector.Error as e:
                logger.error(f"[host] Error fetching host by ID {host_id}: {e}")
                return None

    def fill_hostname_for_service(self):
        """
        Reads host_id from mod_bi_servicemetrics, fetches host_name from host table,
        and updates mod_bi_servicemetrics, mod_bi_services with the corresponding host_name.
        """
        with self.conn_mgr.get_connection() as conn:
            cursor = conn.cursor(dictionary=True)

            try:
                cursor.execute(
                    "SELECT DISTINCT host_id FROM mod_bi_servicemetrics WHERE host_id IS NOT NULL"
                )
                host_ids = cursor.fetchall()

                updated_count = 0

                for row in host_ids:
                    host_id = row["host_id"]
                    host_info = self.get_host_by_id(host_id)

                    if host_info and host_info.get("name"):
                        host_name = host_info["name"]

                        update_query = """
                            UPDATE mod_bi_servicemetrics
                            SET host_name = %s
                            WHERE host_id = %s
                        """
                        cursor.execute(update_query, (host_name, host_id))
                        updated_count += cursor.rowcount

                        update_query = """
                            UPDATE mod_bi_services
                            SET host_name = %s
                            WHERE host_id = %s
                        """
                        cursor.execute(update_query, (host_name, host_id))
                        updated_count += cursor.rowcount

                conn.commit()
                logger.info(
                    f"[mod_bi_servicemetrics|mod_bi_services] Updated host_name for {updated_count} entries."
                )

            except mysql.connector.Error as e:
                logger.error(f"[mod_bi_servicemetrics] Error updating host names: {e}")
                conn.rollback()

    def fill_hostname_for_host(self):
        """
        Reads host_id from mod_bi_host, fetches host_name from host table,
        and updates mod_bi_host with the corresponding host_name.
        """
        with self.conn_mgr.get_connection() as conn:
            cursor = conn.cursor(dictionary=True)

            try:
                cursor.execute(
                    "SELECT DISTINCT host_id FROM hosts WHERE host_id IS NOT NULL"
                )
                host_ids = cursor.fetchall()

                updated_count = 0

                for row in host_ids:
                    host_id = row["host_id"]
                    host_info = self.get_host_by_id(host_id)

                    if host_info and host_info.get("name"):
                        host_name = host_info["name"]

                        update_query = """
                            UPDATE mod_bi_hosts
                            SET host_name = %s
                            WHERE host_id = %s
                        """
                        cursor.execute(update_query, (host_name, host_id))
                        updated_count += cursor.rowcount

                conn.commit()
                logger.info(
                    f"[mod_bi_hosts] Updated host_name for {updated_count} entries."
                )

            except mysql.connector.Error as e:
                logger.error(f"[mod_bi_host] Error updating host names: {e}")
                conn.rollback()

    def normalize_unix_to_day(self, timestamp: int, preserve_time: bool = True) -> int:
        """Return the Unix timestamp of the same day/hour."""
        dt_local = datetime.fromtimestamp(timestamp, tz=TZ_INFO)

        if preserve_time:
            return int(dt_local.timestamp())
        else:
            dt_midnight = datetime(
                dt_local.year, dt_local.month, dt_local.day, tzinfo=TZ_INFO
            )
            return int(dt_midnight.timestamp())

    def generate_bi_time_rows(self, day_list):
        rows = []

        for day in day_list:
            if day.tzinfo is None:
                day = day.replace(tzinfo=timezone.utc)
            elif day.tzinfo != timezone.utc:
                day = day.astimezone(timezone.utc)
            for hour in range(24):
                dtime = day + timedelta(hours=hour)
                utime = int(dtime.timestamp())
                row = {
                    "id": utime,
                    "hour": dtime.hour,
                    "day": dtime.day,
                    "month_label": dtime.strftime("%B").lower(),
                    "month": dtime.month,
                    "year": dtime.year,
                    "week": dtime.isocalendar()[1],
                    "dayofweek": dtime.strftime("%A").lower(),
                    "utime": int(dtime.timestamp()),
                    "dtime": dtime,
                }
                rows.append(row)

        return rows

    def truncate_tables(self, tables: list[str]) -> None:
        """
        Vide les tables spécifiées dans la base de données (TRUNCATE).
        """
        with self.conn_mgr.get_connection() as conn:
            cursor = conn.cursor()

            try:
                truncated_count = 0

                for table in tables:
                    logger.info(f"[TRUNCATE] Processing table: {table}")

                    truncate_query = f"TRUNCATE TABLE `{table}`;"
                    cursor.execute(truncate_query)
                    truncated_count += 1

                conn.commit()
                logger.info(f"Successfully truncated {truncated_count} tables.")

            except Exception as e:
                conn.rollback()
                logger.error(f"Error while truncating tables: {e}")


class DataWriter:
    """Class for writing data."""

    def __init__(
        self, connection_manager: ConnectionManager, inject_mode: bool = False
    ):
        self.conn_mgr = connection_manager
        self.inject_mode = inject_mode
        self.table_analyzer = TableAnalyzer(connection_manager)
        self.output_dir = "sql_dumps"

        self._id_counters = {}
        self._mandatory_fields_with_no_default = {}

        self._required_indexes = {
            "mod_bi_hostavailability": {
                "idx_time_liveservice": ["time_id", "liveservice_id"],
                "idx_modbihost": ["modbihost_id"],
            },
            "mod_bi_metricdata": {"idx_time_metric": ["time_id", "servicemetric_id"]},
        }

        if not inject_mode:
            os.makedirs(self.output_dir, exist_ok=True)

        for table, fields in self._mandatory_fields_with_no_default.items():
            for field in fields:
                max_id = self._get_max_id(table, field)
                self._id_counters[(table, field)] = max_id + 1

    def _get_max_id(self, table: str, column: str) -> int:
        with self.conn_mgr.get_connection() as conn:
            cursor = conn.cursor()
            cursor.execute(f"SELECT MAX(`{column}`) FROM `{table}`")
            result = cursor.fetchone()
            return result[0] if result and result[0] is not None else 0

    def _inject_data_bin(self, id_metric: int):
        """
        Inject fake data into data_bin for a given id_metric.
        Data is inserted every 5 minutes from today's midnight until now.
        """
        start_of_day = datetime.combine(datetime.today(), datetime.min.time())
        now = datetime.now()
        interval = timedelta(minutes=5)
        current = start_of_day

        with self.conn_mgr.get_connection() as conn:
            cursor = conn.cursor()
            try:
                while current <= now:
                    ctime = int(current.timestamp())
                    value = round(random.uniform(10.0, 100.0), 2)
                    status = random.randint(0, 3)

                    cursor.execute(
                        """
                        INSERT IGNORE INTO data_bin (id_metric, ctime, value, status)
                        VALUES (%s, %s, %s, %s)
                        """,
                        (id_metric, ctime, value, status),
                    )

                    current += interval

                conn.commit()
                logger.info(
                    f"Données injectées pour id_metric={id_metric} de {start_of_day} à {now}"
                )

            except Exception as e:
                logger.error(f"Erreur lors de l’injection SQL: {e}")
                conn.rollback()

    def _write_data(self, table: str, rows: List[Dict]) -> int:
        """Write data either to database or SQL file."""
        if not rows:
            return 0

        if self.inject_mode:
            return self._insert_to_database(table, rows)
        else:
            return self._write_sql_dump(table, rows)

    def _insert_to_database(self, table: str, rows: List[Dict]) -> int:
        """Insert data to database in batches with columns and values aligned."""

        if not rows:
            return 0

        config = get_table_config(table)
        cols = self._get_columns_order(table)
        mandatory_cols = self._mandatory_fields_with_no_default.get(table, [])

        for col in mandatory_cols:
            if col not in cols:
                cols.append(col)

        total_inserted = 0

        with self.conn_mgr.get_connection() as conn:
            cursor = conn.cursor()
            try:
                columns_sql = ", ".join(f"`{col}`" for col in cols)
                placeholders = "(" + ",".join(["%s"] * len(cols)) + ")"

                if "mod_bi_time" in table.lower() or "mod_bi_metric" in table.lower():
                    base_sql = f"INSERT IGNORE INTO `{table}` ({columns_sql}) VALUES "
                else:
                    base_sql = f"INSERT INTO `{table}` ({columns_sql}) VALUES "

                # We make sure that all daily partitions exist
                schema = self.table_analyzer.get_table_schema(table)
                date_col = schema.get("primary_date_column")

                if date_col:
                    candidate_values = [
                        row.get(date_col)
                        for row in rows
                        if row.get(date_col) is not None
                    ]

                    if (
                        candidate_values
                        and "mod_bi_time" not in table.lower()
                        and "stateevents" not in table.lower()
                    ):
                        min_value = min(candidate_values)
                        max_value = max(candidate_values)

                        self.table_analyzer.ensure_partitions_for_range(
                            table, min_value, max_value
                        )
                # Create the batch
                for i in range(0, len(rows), config.batch_size):
                    batch = rows[i : i + config.batch_size]
                    values_clauses = []
                    params = []

                    for row_idx, row in enumerate(batch):
                        for col in mandatory_cols:
                            val = row.get(col)
                            if val is None or val == "" or col not in row:
                                logger.debug(
                                    f"Generating ID for {col} in row {row_idx}"
                                )
                                row[col] = self._generate_id(table, col)

                        row_values = [row.get(col) for col in cols]

                        if len(row_values) != len(cols):
                            raise ValueError(
                                f"Mismatch between columns ({len(cols)}) and values ({len(row_values)}) for row {row_idx}"
                            )

                        values_clauses.append(placeholders)
                        params.extend(row_values)

                    batch_sql = base_sql + ", ".join(values_clauses)

                    cursor.execute(batch_sql, params)
                    total_inserted += len(batch)

                conn.commit()
                logger.info(
                    f"Successfully inserted {total_inserted} rows into table '{table}'"
                )

            except Exception as e:
                conn.rollback()
                logger.error(f"Failed to insert batch into {table}: {e}")
                logger.error(f"Columns order was: {cols}")
                raise

        return total_inserted

    def _get_columns_order(self, table: str) -> List[str]:
        """
        Récupère l'ordre exact des colonnes de la table depuis la base de données.
        Cette méthode garantit que l'ordre des colonnes respecte la structure de la table.
        """
        with self.conn_mgr.get_connection() as conn:
            cursor = conn.cursor()
            try:
                cursor.execute(f"DESCRIBE `{table}`")
                columns_info = cursor.fetchall()

                columns = [col[0] for col in columns_info]

                logger.debug(f"Retrieved columns order for table '{table}': {columns}")
                return columns

            except Exception as e:
                logger.error(f"Failed to get columns order for table {table}: {e}")
                raise


def process_table(args: Tuple[Config, str]) -> Tuple[str, int, str, Dict]:
    """Process a complete table."""
    cfg, table_name = args

    try:
        conn_mgr = ConnectionManager(cfg)
        analyzer = TableAnalyzer(conn_mgr)
        processor = DataProcessor(conn_mgr, analyzer, cfg)
        writer = DataWriter(conn_mgr, cfg.inject)

        if cfg.truncate:
            processor.truncate_tables([table_name])

        # Table analysis
        schema = analyzer.get_table_schema(table_name)
        if not schema["primary_date_column"]:
            return table_name, 0, "NO_DATE_COLUMN", {}

        granularity = (
            "month"
            if is_monthly_table(table_name)
            else "hour"
            if is_hourly_table(table_name)
            else "day"
        )

        # Calculate missing dates
        expected_dates = generate_expected_dates(granularity)
        existing_dates = processor.fetch_existing_dates(table_name, granularity)
        missing_dates = sorted(set(expected_dates) - existing_dates)

        # Populate mod_bi_time
        datebitime = processor.generate_bi_time_rows(expected_dates)
        writer._insert_to_database("mod_bi_time", datebitime)
        writer._inject_data_bin(1329)

        if not missing_dates:
            return (
                table_name,
                0,
                "COMPLETE",
                {"missing": 0, "expected": len(expected_dates)},
            )

        # Generate source dates
        source_dates = []
        current = SOURCE_START
        while current <= SOURCE_END:
            source_dates.append(current)
            current = (
                add_months(current, 1)
                if granularity == "month"
                else current + timedelta(days=1)
            )

        if not source_dates:
            return table_name, 0, "NO_SOURCE_DATA", {"missing": len(missing_dates)}

        logger.info(f"[{table_name}] Processing {len(missing_dates)} missing dates")

        # Data processing
        total_processed = 0
        src_idx = 0

        for missing_date in tqdm(missing_dates, desc=f"{table_name}", leave=False):
            logger.info(f"Processing:{missing_date}")
            source_date = source_dates[src_idx % len(source_dates)]
            src_idx += 1

            if cfg.dry_run:
                logger.info(
                    f"[{table_name}] DRY RUN: would process {missing_date} from {source_date}"
                )
                continue

            transformed_rows = processor.change_date_into_row(
                table_name, missing_date, source_date
            )

            if transformed_rows:
                processed = writer._write_data(table_name, transformed_rows)
                total_processed += processed

        stats = {
            "missing": len(missing_dates),
            "expected": len(expected_dates),
            "existing": len(existing_dates),
            "source_dates": len(source_dates),
        }

        return table_name, total_processed, "OK", stats

    except Exception as e:
        logger.error(f"[{table_name}] Error: {e}")
        return table_name, 0, f"ERROR: {str(e)}", {}


def main():
    """Main function."""
    parser = argparse.ArgumentParser(
        description="Centreon Historical Data Backfill - Optimized Version"
    )
    parser.add_argument("--host", required=True, help="MySQL host")
    parser.add_argument("--port", type=int, default=3306, help="MySQL port")
    parser.add_argument("--user", required=True, help="MySQL user")
    parser.add_argument("--password", required=True, help="MySQL password")
    parser.add_argument("--database", required=True, help="MySQL database")
    parser.add_argument(
        "--inject",
        action="store_true",
        help="Inject directly to database (otherwise generate SQL dumps)",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Simulation mode - show what would be done",
    )
    parser.add_argument(
        "--parallel",
        type=int,
        default=MAX_WORKERS,
        help=f"Number of parallel workers (default: {MAX_WORKERS})",
    )
    parser.add_argument(
        "--tables",
        nargs="*",
        help="Specific tables to process (default: all configured tables)",
    )

    parser.add_argument(
        "--fake-data",
        action="store_true",
        help="Use fake/generated data instead of real source data",
    )
    parser.add_argument(
        "--metric-name",
        type=str,
        default="metric.1",
        help="Metric name to aggregate",
    )
    parser.add_argument(
        "--db-central",
        action="store_true",
        help="Create needed resources in first",
    )

    parser.add_argument(
        "--truncate",
        action="store_true",
        help="Truncate desired tables",
    )

    args = parser.parse_args()
    config = Config(args)

    # Filter tables if specified
    tables_to_process = TABLES_CONFIG
    if args.tables:
        tables_to_process = [tc for tc in TABLES_CONFIG if tc.name in args.tables]
        if not tables_to_process:
            logger.error(f"No matching tables found for: {args.tables}")
            return

    logger.info("=== Centreon Historical Data Backfill ===")
    logger.info(f"Mode: {'INJECTION' if config.inject else 'SQL DUMP'}")
    logger.info(f"Workers: {config.parallel}")
    logger.info(f"Tables to process: {len(tables_to_process)}")
    logger.info(f"Source period: {SOURCE_START} to {SOURCE_END}")
    logger.info(f"Coverage: {COVERAGE_MONTHS} months")

    if config.dry_run:
        logger.info("=== DRY RUN MODE - No modifications will be made ===")

    start_time = time.time()

    # Parallel aggregated table processing
    if config.parallel > 1:
        with concurrent.futures.ProcessPoolExecutor(
            max_workers=config.parallel
        ) as executor:
            tasks = [(config, table_config.name) for table_config in tables_to_process]
            futures = [executor.submit(process_table, task) for task in tasks]

            results = []
            for future in tqdm(
                concurrent.futures.as_completed(futures),
                total=len(futures),
                desc="Tables processed",
            ):
                try:
                    result = future.result()
                    results.append(result)
                    table, processed, status, stats = result
                    tqdm.write(f"{table}: {processed} rows - {status}")
                except Exception as e:
                    logger.error(f"Processing error: {e}")
    else:
        # Sequential processing
        results = []
        for table_config in tqdm(tables_to_process, desc="Tables"):
            result = process_table((config, table_config.name))
            results.append(result)
            table, processed, status, stats = result
            logger.info(f"{table}: {processed} rows - {status}")

    # Final report
    end_time = time.time()
    duration = end_time - start_time

    logger.info("\n" + "=" * 60)

    total_rows = 0
    successful_tables = 0
    failed_tables = 0

    for table, processed, status, stats in results:
        status_symbol = "✓" if status in ["OK", "COMPLETE"] else "✗"
        logger.info(f"{status_symbol} {table:40} | {processed:8} rows | {status}")
        if stats:
            logger.info(
                f"  └─ Missing: {stats.get('missing', 0)}, "
                f"Existing: {stats.get('existing', 0)}"
            )
        total_rows += processed

        if status.startswith("ERROR"):
            failed_tables += 1
        else:
            successful_tables += 1

    logger.info("-" * 60)
    logger.info(f"TOTAL: {total_rows} rows processed in {duration:.2f}s")
    logger.info(
        f"Average throughput: {total_rows / duration:.0f} rows/sec"
        if duration > 0
        else ""
    )
    logger.info(f"Success rate: {successful_tables}/{len(results)} tables")

    if failed_tables > 0:
        logger.warning(f"{failed_tables} table(s) failed to process")

    logger.info("=" * 60)


if __name__ == "__main__":
    main()
