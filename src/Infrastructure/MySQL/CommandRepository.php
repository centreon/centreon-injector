<?php

namespace App\Infrastructure\MySQL;

use Doctrine\DBAL\Driver\Connection;
use App\Domain\Command;

class CommandRepository
{
    /**
     * @var Connection
     */
    private $connection;

    private const PROPERTY_NAME = 'command';

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function inject(Command $command, array $properties, array $injectedIds): array
    {
        $ids = [];

        $count = $properties[self::PROPERTY_NAME]['count'];
        $minMetricsCount = isset($properties[self::PROPERTY_NAME]['metrics']['min'])
            ? $properties[self::PROPERTY_NAME]['metrics']['min']
            : 0;
        $maxMetricsCount = isset($properties[self::PROPERTY_NAME]['metrics']['max'])
            ? $properties[self::PROPERTY_NAME]['metrics']['max']
            : 10;

        $result = $this->connection->query('SELECT MAX(command_id) AS max FROM command');
        $i = ((int) $result->fetch()['max']) + 1;
        $maxId = $i + $count;

        $query = 'INSERT INTO command ' .
            '(command_id, command_name, command_line, command_type) ' .
            'VALUES ';

        $name = $command->getName() . '_';
        $line = $command->getLine();
        $type = $command->getType();
        for ($i; $i < $maxId; $i++) {
            $lineWithMetrics = addslashes(
                $line .
                ' --metrics-count ' . random_int($minMetricsCount, $maxMetricsCount) . ' ' .
                '--metrics-name "metric" ' .
                '--metrics-values-range "-10000:10000"'
            );
            $ids[] = $i;
            $query .= '(' .
                $i . ',' .
                '"' . $name . $i . '",' .
                '"' . $lineWithMetrics . '",' .
                $type .
                '),';
        }
        $query = rtrim($query, ',');

        $this->connection->query($query);

        return $ids;
    }

    public function purge()
    {
        $this->connection->query('TRUNCATE command');
    }
}
