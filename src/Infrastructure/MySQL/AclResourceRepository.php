<?php

namespace App\Infrastructure\MySQL;

use App\Domain\Doctrine\DynamicConnection as Connection;
use App\Domain\AclResource;

class AclResourceRepository
{
    /**
     * @var Connection
     */
    private $connection;

    private const PROPERTY_NAME = 'acl_resource';

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function inject(AclResource $aclResource, array $properties, array $injectedIds): array
    {
        $ids = [];

        $count = $properties[self::PROPERTY_NAME]['count'];

        $result = $this->connection->executeQuery('SELECT MAX(acl_res_id) AS max FROM acl_resources');
        $firstId = ((int) $result->fetchAssociative()['max']) + 1;
        $maxId = $firstId + $count;

        $baseQuery = 'INSERT INTO acl_resources ' .
            '(acl_res_id, acl_res_name, acl_res_alias, acl_res_activate) ' .
            'VALUES ';
        $valuesQuery = '';

        $name = $aclResource->getName() . '_';
        $alias = $name;
        $insertCount = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            $ids[] = $i;
            $insertCount++;
            $valuesQuery .= '(' .
                $i . ',' .
                '"' . $name . $i . '",' .
                '"' . $alias . $i . '",' .
                '"1"' .
                '),';
            if ($insertCount === 50000) {
                $query = rtrim($baseQuery . $valuesQuery, ',');
                $this->connection->executeQuery($query);
                $insertCount = 0;
                $valuesQuery = '';
            }
        }

        if ($insertCount > 0) {
            $query = rtrim($baseQuery . $valuesQuery, ',');
            $this->connection->executeQuery($query);
        }

        // host relations

        $baseQuery = 'INSERT INTO acl_resources_host_relations ' .
            '(acl_res_id, host_host_id) ' .
            'VALUES ';
        $valuesQuery = '';

        $insertCount = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            foreach (array_slice($injectedIds['host'], 0, $properties[self::PROPERTY_NAME]['hosts']) as $hostId) {
                $insertCount++;
                $valuesQuery .= '(' . $i . ',' . $hostId . '),';

                if ($insertCount === 50000) {
                    $query = rtrim($baseQuery . $valuesQuery, ',');
                    $this->connection->executeQuery($query);
                    $insertCount = 0;
                    $valuesQuery = '';
                }
            }
        }

        if ($insertCount > 0) {
            $query = rtrim($baseQuery . $valuesQuery, ',');
            $this->connection->executeQuery($query);
        }

        // servicegroup relations

        $baseQuery = 'INSERT INTO acl_resources_sg_relations ' .
            '(acl_res_id, sg_id) ' .
            'VALUES ';
        $valuesQuery = '';

        $insertCount = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            foreach (array_slice($injectedIds['servicegroup'], 0, $properties[self::PROPERTY_NAME]['servicegroups']) as $servicegroupId) {
                $insertCount++;
                $valuesQuery .= '(' . $i . ',' . $servicegroupId . '),';

                if ($insertCount === 50000) {
                    $query = rtrim($baseQuery . $valuesQuery, ',');
                    $this->connection->executeQuery($query);
                    $insertCount = 0;
                    $valuesQuery = '';
                }
            }
        }

        if ($insertCount > 0) {
            $query = rtrim($baseQuery . $valuesQuery, ',');
            $this->connection->executeQuery($query);
        }

        return $ids;
    }

    public function purge()
    {
        $this->connection->executeQuery('TRUNCATE acl_resources');
    }
}
