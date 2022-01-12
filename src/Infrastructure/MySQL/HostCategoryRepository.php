<?php

namespace App\Infrastructure\MySQL;

use App\Domain\Doctrine\DynamicConnection as Connection;
use App\Domain\HostCategory;

class HostCategoryRepository
{
    /**
     * @var Connection
     */
    private $connection;

    private const PROPERTY_NAME = 'host_category';

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function inject(HostCategory $hostCategory, array $properties, array $injectedIds): array
    {
        $ids = [];

        $count = $properties[self::PROPERTY_NAME]['count'];

        $result = $this->connection->query('SELECT MAX(hc_id) AS max FROM hostcategories');
        $firstId = ((int) $result->fetch()['max']) + 1;
        $maxId = $firstId + $count;

        $baseQuery = 'INSERT INTO hostcategories ' .
            '(hc_id, hc_name, hc_alias) ' .
            'VALUES ';
        $valuesQuery = '';

        $name = $hostCategory->getName() . '_';
        $alias = $hostCategory->getAlias() . '_';
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

        $baseQuery = 'INSERT INTO hostcategories_relation ' .
            '(hostcategories_hc_id, host_host_id) ' .
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
        $this->connection->query('TRUNCATE hostcategories');
    }
}
