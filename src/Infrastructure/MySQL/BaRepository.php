<?php

namespace App\Infrastructure\MySQL;

use Doctrine\DBAL\Driver\Connection;
use App\Domain\Ba;

class BaRepository
{
    /**
     * @var Connection
     */
    private $connection;

    private const PROPERTY_NAME = 'ba';

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function inject(Ba $ba, array $properties, array $injectedIds): array
    {
        $ids = [];

        $count = $properties[self::PROPERTY_NAME]['count'];

        $result = $this->connection->query('SELECT MAX(ba_id) AS max FROM mod_bam');
        $firstId = ((int) $result->fetch()['max']) + 1;
        $maxId = $firstId + $count;

        $baseQuery = 'INSERT INTO mod_bam ' .
            '(ba_id, name, description, state_source, level_w, level_c, ' .
            'event_handler_enabled, notifications_enabled, id_notification_period, id_reporting_period, activate) ' .
            'VALUES ';
        $valuesQuery = '';

        $name = $ba->getName() . '_';
        $description = $ba->getDescription() . '_';
        $insertCount = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            $calculationType = random_int(0, 4);
            $ids[] = $i;
            $insertCount++;
            $valuesQuery .= '(' .
                $i . ',' .
                '"' . $name . $i . '",' .
                '"' . $description . $i . '",' .
                $calculationType . ',' .
                ($calculationType !== 4 ? 90 : 80) . ',' .
                ($calculationType !== 4 ? 80 : 90) . ',' .
                '"0",' .
                '"0",' .
                $injectedIds['timeperiod'][array_rand($injectedIds['timeperiod'], 1)] . ',' .
                $injectedIds['timeperiod'][array_rand($injectedIds['timeperiod'], 1)] . ',' .
                '"1"' .
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

        $result = $this->connection->query('SELECT id FROM nagios_server WHERE localhost = "1"');
        $pollerId = (int) $result->fetch()['id'];

        $baseQuery = 'INSERT INTO mod_bam_poller_relations ' .
            '(ba_id, poller_id) ' .
            'VALUES ';
        $valuesQuery = '';

        $insertCount = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            $insertCount++;
            $valuesQuery .= '(' . $i . ',' . $pollerId . '),';

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
        $this->connection->query('TRUNCATE mod_bam');
    }
}
