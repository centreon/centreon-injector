<?php

namespace App\Infrastructure\MySQL;

use Doctrine\DBAL\Driver\Connection;
use App\Domain\Host;

class HostRepository
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function inject(Host $host, int $count, array $injectedIds): array
    {
        $ids = [];

        $result = $this->connection->query('SELECT MAX(host_id) AS max FROM host');
        $firstId = ((int) $result->fetch()['max']) + 1;
        $maxId = $firstId + $count;

        $baseQuery = 'INSERT INTO host ' .
            '(host_id, host_name, host_alias, host_address, host_register, command_command_id) ' .
            'VALUES ';
        $valuesQuery = '';

        $name = $host->getName() . '_';
        $alias = $host->getAlias() . '_';
        $address = $host->getAddress();
        $insertCount = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            $ids[] = $i;
            $insertCount++;
            $valuesQuery .= '(' .
                $i . ',' .
                '"' . $name . $i . '",' .
                '"' . $alias . $i . '",' .
                '"' . $address . '",' .
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

        $baseQuery = 'INSERT INTO ns_host_relation ' .
            '(nagios_server_id, host_host_id) ' .
            'VALUES ';
        $valuesQuery = '';

        $insertCount = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            $insertCount++;
            $valuesQuery .= '(1,' . $i . '),';

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
        $this->connection->query('TRUNCATE host');
    }
}
