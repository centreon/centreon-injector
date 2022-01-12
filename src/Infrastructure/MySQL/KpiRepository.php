<?php

namespace App\Infrastructure\MySQL;

use App\Domain\Doctrine\DynamicConnection as Connection;
use App\Domain\Kpi;

class KpiRepository
{
    /**
     * @var Connection
     */
    private $connection;

    private const PROPERTY_NAME = 'kpi';

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function inject(Kpi $kpi, array $properties, array $injectedIds): array
    {
        $ids = [];

        $count = $properties[self::PROPERTY_NAME]['count'];

        $result = $this->connection->query('SELECT MAX(kpi_id) AS max FROM mod_bam_kpi');
        $firstId = ((int) $result->fetch()['max']) + 1;
        $maxId = $firstId + $count;

        $baseQuery = 'INSERT INTO mod_bam_kpi ' .
            '(kpi_id, id_ba, state_type, kpi_type, config_type, host_id, service_id, ' .
            'drop_warning, drop_critical, drop_unknown, activate) ' .
            'VALUES ';
        $valuesQuery = '';

        $insertCount = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            $ids[] = $i;
            $service = $injectedIds['service'][array_rand($injectedIds['service'], 1)];
            $insertCount++;
            $valuesQuery .= '(' .
                $i . ',' .
                $injectedIds['ba'][array_rand($injectedIds['ba'], 1)] . ',' .
                '"1",' .
                '"0",' .
                '"0",' .
                $service['host_id'] . ',' .
                $service['service_id'] . ',' .
                '10,' .
                '20,' .
                '5,' .
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

        return $ids;
    }

    public function purge()
    {
        $this->connection->query('TRUNCATE mod_bam_kpi');
    }
}
