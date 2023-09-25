<?php

namespace App\Infrastructure\MySQL;

use App\Domain\Doctrine\DynamicConnection as Connection;

class AclMenuRepository
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function inject(array $properties, array $injectedIds): array
    {
        $ids = [];

        $count = 1;

        $result = $this->connection->executeQuery('SELECT MAX(acl_topo_id) AS max FROM acl_topology');
        $firstId = ((int) $result->fetchAssociative()['max']) + 1;
        $maxId = $firstId + $count;

        $baseQuery = 'INSERT INTO acl_topology ' .
            '(acl_topo_id, acl_topo_name, acl_topo_alias, acl_topo_activate) ' .
            'VALUES ';
        $valuesQuery = '';

        $name = 'acl_menu_';
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

        // menu relations

        $this->connection->executeQuery(
            'INSERT INTO acl_topology_relations (topology_topology_id, acl_topo_id, access_right) '
            . 'SELECT t.topology_id, aclt.acl_topo_id, 1 '
            . 'FROM topology t, acl_topology aclt '
        );

        return $ids;
    }

    public function purge()
    {
        $this->connection->executeQuery('TRUNCATE acl_groups');
    }
}
