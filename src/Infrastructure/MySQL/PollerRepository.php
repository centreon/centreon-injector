<?php

namespace App\Infrastructure\MySQL;

use App\Domain\Doctrine\DynamicConnection as Connection;

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

    public function getPollerIds(array $properties): array
    {
        $ids = [];

        $query = 'SELECT id FROM nagios_server WHERE ns_activate = "1"';

        if (isset($properties['poller']['hostsOnCentral']) && $properties['poller']['hostsOnCentral'] === false) {
            $query .= ' AND localhost = "0"';
        }

        $result = $this->connection->query($query);
        while ($poller = $result->fetch()) {
            $ids[] = $poller['id'];
        }

        if (empty($ids)) {
            throw new \Exception('At least one poller must be enabled');
        }

        return $ids;
    }
}
