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

        $result = $this->connection->query('SELECT MAX(command_id) AS max FROM command');
        $i = ((int) $result->fetch()['max']) + 1;
        $firstId = $i;
        $maxId = $i + $count;

        $query = 'INSERT INTO command ' .
            '(command_id, command_name, command_line, command_type) ' .
            'VALUES ';

        $name = $command->getName() . '_';
        $line = $command->getLine();
        $type = $command->getType();
        for ($i; $i < $maxId; $i++) {
            if ($i === $firstId) {
                // first injected command is host command
                $lineWithMetrics = addslashes(
                    $line .
                    ' --host ' .
                    ' --status-sequence "up,up,up,up,up,up,down,up,up,up,up,up,up,up,up,up,up,down,down,down" '
                );
            } else {
                $lineWithMetrics = addslashes(
                    $line .
                    ' --status-sequence "ok,ok,ok,ok,ok,critical,warning,ok,ok,critical,critical,critical,ok,ok,ok,ok,ok,ok,ok,ok,ok,ok,ok,ok,ok,ok,ok,ok,ok,ok,unknown,unknown,unknown"' .
                    ' --metrics-count $_SERVICEMETRICCOUNT$ ' .
                    '--metrics-name "metric" ' .
                    '--metrics-values-range "$_SERVICEMETRICMINRANGE$:$_SERVICEMETRICMAXRANGE$"'
                );
            }
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
