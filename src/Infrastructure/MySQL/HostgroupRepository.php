<?php

namespace App\Infrastructure\MySQL;

use App\Domain\Doctrine\DynamicConnection as Connection;
use App\Domain\Hostgroup;

class HostgroupRepository
{
    /**
     * @var Connection
     */
    private $connection;

    private const PROPERTY_NAME = 'hostgroup';

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function inject(Hostgroup $hostgroup, array $properties, array $injectedIds): array
    {
        $ids = [];

        $count = $properties[self::PROPERTY_NAME]['count'];

        $result = $this->connection->query('SELECT MAX(hg_id) AS max FROM hostgroup');
        $firstId = ((int) $result->fetch()['max']) + 1;
        $maxId = $firstId + $count;

        $baseQuery = 'INSERT INTO hostgroup ' .
            '(hg_id, hg_name, hg_alias) ' .
            'VALUES ';
        $valuesQuery = '';

        $name = $hostgroup->getName() . '_';
        $alias = $hostgroup->getAlias() . '_';
        $insertCount = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            $ids[] = $i;
            $insertCount++;
            $valuesQuery .= '(' .
                $i . ',' .
                '"' . $name . $i . '",' .
                '"' . $alias . $i . '"' .
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



        $minHostsCount = isset($properties[self::PROPERTY_NAME]['hosts']['min'])
            ? $properties[self::PROPERTY_NAME]['hosts']['min']
            : 0;
        $maxHostsCount = isset($properties[self::PROPERTY_NAME]['hosts']['max'])
            ? $properties[self::PROPERTY_NAME]['hosts']['max']
            : 5;

        $baseQuery = 'INSERT INTO hostgroup_relation ' .
            '(hostgroup_hg_id, host_host_id) ' .
            'VALUES ';
        $valuesQuery = '';

        $insertCount = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            $hostCount = random_int($minHostsCount, $maxHostsCount);
            for ($j = 0; $j < $hostCount; $j++) {
                $insertCount++;
                $valuesQuery .= '(' . $i . ',' . $injectedIds['host'][array_rand($injectedIds['host'], 1)] . '),';

                if ($insertCount === 50000) {
                    $query = rtrim($baseQuery . $valuesQuery, ',');
                    $this->connection->query($query);
                    $insertCount = 0;
                    $valuesQuery = '';
                }
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
        $this->connection->query('TRUNCATE hostgroup');
    }
}
