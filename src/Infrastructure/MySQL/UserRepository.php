<?php

namespace App\Infrastructure\MySQL;

use App\Domain\Doctrine\DynamicConnection as Connection;
use App\Domain\User;
use App\Domain\UserRole;

class UserRepository
{
    /**
     * @var Connection
     */
    private $connection;

    private const PROPERTY_NAME = 'user';

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function inject(User $user, array $properties, array $injectedIds): array
    {
        $ids = [];

        $yamlSubKey = match ($user->getRole()) {
            UserRole::Administrator => 'administrators',
            UserRole::Editor => 'editors',
            UserRole::User => 'users',
        };

        $count = $properties[self::PROPERTY_NAME][$yamlSubKey];

        $result = $this->connection->executeQuery('SELECT MAX(contact_id) AS max FROM contact');
        $firstId = ((int) $result->fetchAssociative()['max']) + 1;
        $maxId = $firstId + $count;

        $baseQuery = 'INSERT INTO contact ' .
            '(contact_id, contact_name, contact_alias, contact_email, contact_lang, contact_activate, contact_admin, contact_oreon) ' .
            'VALUES ';
        $valuesQuery = '';

        $name = $user->getName() . '_';
        $alias = $name;
        $isAdmin = $user->getRole() === UserRole::Administrator ? '1' : '0';
        $password = password_hash('centreon', PASSWORD_BCRYPT);

        $insertCount = 0;
        $userIndex = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            $ids[] = $i;
            $insertCount++;
            $userIndex++;
            $valuesQuery .= '(' .
                $i . ',' .
                '"' . $name . $userIndex . '",' .
                '"' . $alias . $userIndex . '",' .
                '"' . $name . $userIndex . '@localhost",' .
                '"en_US.UTF-8",' .
                '"1",' .
                '"' . $isAdmin . '",' .
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

        $this->connection->executeQuery(
            'INSERT INTO contact_password (password, contact_id, creation_date) '
            . 'SELECT "' . $password . '",contact_id,' . time() . ' '
            . 'FROM contact WHERE contact_name LIKE "' . $user->getName() . '\_%"'
        );

        if ($isAdmin) {
            return $ids;
        }

        // add relation with 1 random acl group

        $baseQuery = 'INSERT INTO acl_group_contacts_relations ' .
            '(contact_contact_id, acl_group_id) ' .
            'VALUES ';
        $valuesQuery = '';

        $insertCount = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            foreach (array_slice($injectedIds['acl_group'], random_int(0, count($injectedIds['acl_group'])), 1) as $aclGroupId) {
                $insertCount++;
                $valuesQuery .= '(' . $i . ',' . $aclGroupId . '),';

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

        return $ids;
    }

    public function purge()
    {
        $this->connection->executeQuery('TRUNCATE contact');
    }
}
