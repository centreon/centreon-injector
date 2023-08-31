<?php

namespace App\Infrastructure\MySQL;

use App\Domain\Doctrine\DynamicConnection as Connection;
use App\Domain\AclGroup;

class AclGroupRepository
{
    /**
     * @var Connection
     */
    private $connection;

    private const PROPERTY_NAME = 'acl_group';

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function inject(AclGroup $aclGroup, array $properties, array $injectedIds): array
    {
        $ids = [];

        $count = $properties[self::PROPERTY_NAME]['count'];

        $result = $this->connection->executeQuery('SELECT MAX(acl_group_id) AS max FROM acl_groups');
        $firstId = ((int) $result->fetchAssociative()['max']) + 1;
        $maxId = $firstId + $count;

        $baseQuery = 'INSERT INTO acl_groups ' .
            '(acl_group_id, acl_group_name, acl_group_alias, acl_group_activate) ' .
            'VALUES ';
        $valuesQuery = '';

        $name = $aclGroup->getName() . '_';
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

        // acl resource relations

        $baseQuery = 'INSERT INTO acl_res_group_relations ' .
            '(acl_group_id, acl_res_id) ' .
            'VALUES ';
        $valuesQuery = '';

        $insertCount = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            foreach (array_slice($injectedIds['aclresource'], 0, $properties[self::PROPERTY_NAME]['resources']) as $resourceId) {
                $insertCount++;
                $valuesQuery .= '(' . $i . ',' . $resourceId . '),';

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

        // menu relations

        $this->connection->executeQuery(
            'INSERT INTO acl_group_topology_relations (acl_group_id, acl_topology_id) '
            . 'SELECT aclg.acl_group_id, aclt.acl_topo_id '
            . 'FROM acl_groups aclg, acl_topology aclt '
        );

        return $ids;
    }

    public function purge()
    {
        $this->connection->executeQuery('TRUNCATE acl_groups');
    }
}
