<?php

namespace App\Infrastructure\MySQL;

use Doctrine\DBAL\Driver\Connection;
use App\Domain\Timeperiod;

class TimeperiodRepository
{
    /**
     * @var Connection
     */
    private $connection;

    private const PROPERTY_NAME = 'timeperiod';

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function inject(Timeperiod $timeperiod, array $properties, array $injectedIds): array
    {
        $ids = [];

        $count = $properties[self::PROPERTY_NAME]['count'];

        $result = $this->connection->query('SELECT MAX(tp_id) AS max FROM timeperiod');
        $i = ((int) $result->fetch()['max']) + 1;
        $maxId = $i + $count;

        $query = 'INSERT INTO timeperiod ' .
            '(tp_id, tp_name, tp_alias, tp_monday, tp_tuesday, ' .
            'tp_wednesday, tp_thursday, tp_friday, tp_saturday, tp_sunday) ' .
            'VALUES ';

        $name = $timeperiod->getName() . '_';
        $alias = $timeperiod->getAlias() . '_';
        $range = $timeperiod->getMondayRange();
        for ($i; $i < $maxId; $i++) {
            $ids[] = $i;
            $query .= '(' .
                $i . ',' .
                '"' . $name . $i . '",' .
                '"' . $alias . $i . '",' .
                '"' . $range . '",' .
                '"' . $range . '",' .
                '"' . $range . '",' .
                '"' . $range . '",' .
                '"' . $range . '",' .
                '"' . $range . '",' .
                '"' . $range . '"' .
                '),';
        }
        $query = rtrim($query, ',');

        $this->connection->query($query);

        return $ids;
    }

    public function purge()
    {
        $this->connection->query('TRUNCATE timeperiod');
    }
}
