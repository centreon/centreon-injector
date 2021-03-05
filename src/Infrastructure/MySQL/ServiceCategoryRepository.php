<?php

namespace App\Infrastructure\MySQL;

use Doctrine\DBAL\Driver\Connection;
use App\Domain\ServiceCategory;

class ServiceCategoryRepository
{
    /**
     * @var Connection
     */
    private $connection;

    private const PROPERTY_NAME = 'service_category';

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function inject(ServiceCategory $serviceCategory, array $properties, array $injectedIds): array
    {
        $ids = [];

        $count = $properties[self::PROPERTY_NAME]['count'];

        $result = $this->connection->query('SELECT MAX(sc_id) AS max FROM service_categories');
        $firstId = ((int) $result->fetch()['max']) + 1;
        $maxId = $firstId + $count;

        $baseQuery = 'INSERT INTO service_categories ' .
            '(sc_id, sc_name, sc_description) ' .
            'VALUES ';
        $valuesQuery = '';

        $name = $serviceCategory->getName() . '_';
        $alias = $serviceCategory->getAlias() . '_';
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



        $minServicesCount = isset($properties[self::PROPERTY_NAME]['services']['min'])
            ? $properties[self::PROPERTY_NAME]['services']['min']
            : 0;
        $maxServicesCount = isset($properties[self::PROPERTY_NAME]['services']['max'])
            ? $properties[self::PROPERTY_NAME]['services']['max']
            : 5;

        $baseQuery = 'INSERT INTO service_categories_relation ' .
            '(sc_id, service_service_id) ' .
            'VALUES ';
        $valuesQuery = '';

        $insertCount = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            $hostCount = random_int($minServicesCount, $maxServicesCount);
            for ($j = 0; $j < $hostCount; $j++) {
                $insertCount++;
                $valuesQuery .= '(' . $i . ',' .
                    $injectedIds['service'][array_rand($injectedIds['service'], 1)]['service_id'] . '),';

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
        $this->connection->query('TRUNCATE service_categories');
    }
}
