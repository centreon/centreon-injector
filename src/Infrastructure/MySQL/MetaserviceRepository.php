<?php

namespace App\Infrastructure\MySQL;

use App\Domain\Doctrine\DynamicConnection as Connection;
use App\Domain\Metaservice;

class MetaserviceRepository
{
    /**
     * @var Connection
     */
    private $connection;

    private const PROPERTY_NAME = 'metaservice';

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function inject(Metaservice $metaservice, array $properties, array $injectedIds): array
    {
        $ids = [];

        $result = $this->connection->query('SELECT host_id FROM host WHERE host_name = "_Module_Meta"');

        if ($host = $result->fetch()) {
            $hostId = $host['host_id'];
        } else {
            $this->connection->query(
                'INSERT INTO host (host_name, host_register, host_activate)
                VALUES ("_Module_Meta","2","1")'
            );
            $result = $this->connection->query('SELECT host_id FROM host WHERE host_name = "_Module_Meta"');
            if ($host = $result->fetch()) {
                $hostId = $host['host_id'];
            } else {
                throw new \Exception('cannot create metaservice virtual host');
            }
        }

        $count = $properties[self::PROPERTY_NAME]['count'];

        $result = $this->connection->query('SELECT MAX(meta_id) AS max FROM meta_service');
        $firstId = ((int) $result->fetch()['max']) + 1;
        $maxId = $firstId + $count;

        $baseQuery = 'INSERT INTO meta_service ' .
            '(meta_id, meta_name, meta_display, metric, calcul_type, data_source_type, meta_select_mode, regexp_str,
            check_period, max_check_attempts, normal_check_interval, retry_check_interval,
            notifications_enabled, warning, critical, meta_activate) ' .
            'VALUES ';
        $valuesQuery = '';

        $name = $metaservice->getName() . '_';
        $criticalThreshold = random_int(1, 100000000000);
        $warningThreshold = 80 * $criticalThreshold / 100;
        $insertCount = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            $ids[] = $i;
            $insertCount++;
            $service = $injectedIds['service'][array_rand($injectedIds['service'], 1)];
            $valuesQuery .= '(' .
                $i . ',' .
                '"' . $name . $i . '",' .
                '"calculated value : %d",' .
                '"metric.1",' .
                '"AVE",' .
                '0,' .
                '"2",' .
                '"service_name_' . $service['service_id'] . '%",' .
                $injectedIds['timeperiod'][array_rand($injectedIds['timeperiod'], 1)] . ',' .
                '3,' .
                '5,' .
                '1,' .
                '"1",' .
                $warningThreshold . ',' .
                $criticalThreshold . ',' .
                '"1"' .
                '),';
            if ($insertCount === 50000) {
                $query = rtrim($baseQuery . $valuesQuery, ',');
                $this->connection->query($query);
                $insertCount = 0;
                $valuesQuery = '';
            }
        }

        if ($insertCount > 0) {
            $query = rtrim($baseQuery . $valuesQuery, ',');
            $this->connection->query($query);
        }

        $result = $this->connection->query('SELECT MAX(service_id) AS max FROM service');
        $firstServiceId = ((int) $result->fetch()['max']) + 1;
        $maxId = $firstServiceId + $count;

        $baseQuery = 'INSERT INTO service ' .
            '(service_id, service_description, display_name,
            service_register, service_activate) ' .
            'VALUES ';
        $valuesQuery = '';

        $displayName = $metaservice->getName() . '_';
        $description = 'meta_';
        $insertCount = 0;
        $metaId = $firstId;
        for ($i = $firstServiceId; $i < $maxId; $i++) {
            $insertCount++;
            $valuesQuery .= '(' .
                $i . ',' .
                '"' . $description . $metaId . '",' .
                '"' . $displayName . $metaId . '",' .
                '"2",' .
                '"1"' .
                '),';
            if ($insertCount === 50000) {
                $query = rtrim($baseQuery . $valuesQuery, ',');
                $this->connection->query($query);
                $insertCount = 0;
                $valuesQuery = '';
            }
            $metaId++;
        }

        if ($insertCount > 0) {
            $query = rtrim($baseQuery . $valuesQuery, ',');
            $this->connection->query($query);
        }

        // host and service relations

        $baseQuery = 'INSERT INTO host_service_relation ' .
            '(host_host_id, service_service_id) ' .
            'VALUES ';
        $valuesQuery = '';

        $insertCount = 0;
        for ($i = $firstServiceId; $i < $maxId; $i++) {
            $insertCount++;
            $valuesQuery .= '(' . $hostId . ',' . $i . '),';

            if ($insertCount === 50000) {
                $query = rtrim($baseQuery . $valuesQuery, ',');
                $this->connection->query($query);
                $insertCount = 0;
                $valuesQuery = '';
            }
        }

        if ($insertCount > 0) {
            $query = rtrim($baseQuery . $valuesQuery, ',');
            $this->connection->query($query);
        }

        return $ids;
    }

    public function purge()
    {
        $this->connection->query('TRUNCATE meta_service');
    }
}
