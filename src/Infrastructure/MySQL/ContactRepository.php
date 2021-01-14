<?php

namespace App\Infrastructure\MySQL;

use Doctrine\DBAL\Driver\Connection;
use App\Domain\Contact;

class ContactRepository
{
    /**
     * @var Connection
     */
    private $connection;

    private const PROPERTY_NAME = 'contact';

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function inject(Contact $contact, array $properties, array $injectedIds): array
    {
        $ids = [];

        $count = $properties[self::PROPERTY_NAME]['count'];

        $result = $this->connection->query('SELECT MAX(contact_id) AS max FROM contact');
        $i = ((int) $result->fetch()['max']) + 1;
        $maxId = $i + $count;

        $query = 'INSERT INTO contact ' .
            '(contact_id, contact_name, contact_alias, contact_email, contact_passwd, ' .
            'contact_oreon, reach_api, reach_api_rt, contact_admin, ' .
            'timeperiod_tp_id, timeperiod_tp_id2) ' .
            'VALUES ';

        $name = $contact->getName() . '_';
        $alias = $contact->getAlias() . '_';
        $password = $contact->getPassword();
        for ($i; $i < $maxId; $i++) {
            $ids[] = $i;
            $query .= '(' .
                $i . ',' .
                '"' . $name . $i . '",' .
                '"' . $alias . $i . '",' .
                '"' . $alias . $i . '@localhost",' .
                '"' . $password . '",' .
                '"1",' .
                '1,' .
                '1,' .
                '"1",' .
                $injectedIds['timeperiod'][array_rand($injectedIds['timeperiod'], 1)] . ',' .
                $injectedIds['timeperiod'][array_rand($injectedIds['timeperiod'], 1)] .
                '),';
        }
        $query = rtrim($query, ',');

        $this->connection->query($query);

        return $ids;
    }

    public function purge()
    {
        $this->connection->query('TRUNCATE contact');
    }
}
