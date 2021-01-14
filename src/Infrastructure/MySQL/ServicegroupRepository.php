<?php

namespace App\Infrastructure\MySQL;

use Doctrine\DBAL\Driver\Connection;
use App\Domain\Servicegroup;

class ServicegroupRepository
{
    /**
     * @var Connection
     */
    private $connection;

    private const PROPERTY_NAME = 'servicegroup';

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function inject(Servicegroup $servicegroup, array $properties, array $injectedIds): array
    {
        $ids = [];

        $count = $properties[self::PROPERTY_NAME]['count'];

        $result = $this->connection->query('SELECT MAX(sg_id) AS max FROM servicegroup');
        $firstId = ((int) $result->fetch()['max']) + 1;
        $maxId = $firstId + $count;

        $baseQuery = 'INSERT INTO servicegroup ' .
            '(sg_id, sg_name, sg_alias) ' .
            'VALUES ';
        $valuesQuery = '';

        $name = $servicegroup->getName() . '_';
        $alias = $servicegroup->getAlias() . '_';
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

        $baseQuery = 'INSERT INTO servicegroup_relation ' .
            '(servicegroup_sg_id, service_service_id) ' .
            'VALUES ';
        $valuesQuery = '';

        $insertCount = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            $serviceCount = random_int($minServicesCount, $maxServicesCount);
            for ($j = 0; $j < $serviceCount; $j++) {
                $insertCount++;
                $valuesQuery .= '(' . $i . ',' . $injectedIds['service'][array_rand($injectedIds['service'], 1)] . '),';

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
        $this->connection->query('TRUNCATE servicegroup');
    }
}
