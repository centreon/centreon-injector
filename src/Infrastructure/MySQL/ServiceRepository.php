<?php

namespace App\Infrastructure\MySQL;

use Doctrine\DBAL\Driver\Connection;
use App\Domain\Service;

class ServiceRepository
{
    /**
     * @var Connection
     */
    private $connection;

    private const PROPERTY_NAME = 'service';

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function inject(Service $service, array $properties, array $injectedIds): array
    {
        $ids = [];

        $count = $properties[self::PROPERTY_NAME]['count'];

        $result = $this->connection->query('SELECT MAX(service_id) AS max FROM service');
        $firstId = ((int) $result->fetch()['max']) + 1;
        $maxId = $firstId + $count;

        $baseQuery = 'INSERT INTO service ' .
            '(service_id, service_description, service_alias, service_register, command_command_id) ' .
            'VALUES ';
        $valuesQuery = '';

        $description = $service->getDescription() . '_';
        $alias = $service->getAlias() . '_';
        $insertCount = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            $ids[] = $i;
            $insertCount++;
            $valuesQuery .= '(' .
                $i . ',' .
                '"' . $description . $i . '",' .
                '"' . $alias . $i . '",' .
                '"1",' .
                $injectedIds['command'][array_rand($injectedIds['command'], 1)] .
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

        $baseQuery = 'INSERT INTO host_service_relation ' .
            '(host_host_id, service_service_id) ' .
            'VALUES ';
        $valuesQuery = '';

        $insertCount = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            $insertCount++;
            $valuesQuery .= '(' . $injectedIds['host'][array_rand($injectedIds['host'], 1)] . ',' . $i . '),';

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
        $this->connection->query('TRUNCATE service');
    }
}
