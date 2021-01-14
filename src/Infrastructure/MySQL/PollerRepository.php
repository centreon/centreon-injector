<?php

namespace App\Infrastructure\MySQL;

use Doctrine\DBAL\Driver\Connection;

class PollerRepository
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getPollerIds(): array
    {
        $ids = [];

        $result = $this->connection->query('SELECT id FROM nagios_server WHERE ns_activate = "1"');
        while ($poller = $result->fetch()) {
            $ids[] = $poller['id'];
        }

        return $ids;
    }
}
