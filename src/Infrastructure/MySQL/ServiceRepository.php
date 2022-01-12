<?php

namespace App\Infrastructure\MySQL;

use App\Domain\Doctrine\DynamicConnection as Connection;
use App\Domain\Service;

class ServiceRepository
{
    /**
     * @var Connection
     */
    private $connection;

    private const PROPERTY_NAME = 'service';

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function inject(Service $service, array $properties, array $injectedIds): array
    {
        $ids = [];

        $count = $properties[self::PROPERTY_NAME]['count'];

        $serviceTemplateId = $this->injectServiceTemplate($service, $properties, $injectedIds);

        $result = $this->connection->query('SELECT MAX(service_id) AS max FROM service');
        $firstId = ((int) $result->fetch()['max']) + 1;
        $maxId = $firstId + $count;

        $baseQuery = 'INSERT INTO service ' .
            '(service_id, service_template_model_stm_id, service_description, service_alias, service_register, command_command_id) ' .
            'VALUES ';
        $valuesQuery = '';

        $description = $service->getDescription() . '_';
        $alias = $service->getAlias() . '_';
        $insertCount = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            $insertCount++;
            $valuesQuery .= '(' .
                $i . ',' .
                $serviceTemplateId . ',' .
                '"' . $description . $i . '",' .
                '"' . $alias . $i . '",' .
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


        // service macros

        $minMetricsCount = isset($properties['command']['metrics']['min'])
            ? $properties['command']['metrics']['min']
            : 0;
        $maxMetricsCount = isset($properties['command']['metrics']['max'])
            ? $properties['command']['metrics']['max']
            : 10;

        $baseQuery = 'INSERT INTO on_demand_macro_service ' .
            '(svc_macro_name, svc_macro_value, svc_svc_id) ' .
            'VALUES ';
        $valuesQuery = '';

        $insertCount = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            if (random_int(0, 2) === 2) { // 1/3 of services contain directly macro values
                $insertCount++;
                $valuesQuery .= '("$_SERVICEMETRICCOUNT$",' . random_int($minMetricsCount, $maxMetricsCount) . ',' . $i . '),'
                    . '("$_SERVICEMETRICMINRANGE$",' . random_int(-1000000000, 0) . ',' . $i . '),'
                    . '("$_SERVICEMETRICMAXRANGE$",' . random_int(0, 1000000000) . ',' . $i . '),';

                if ($insertCount === 10000) {
                    $query = rtrim($baseQuery . $valuesQuery, ',');
                    $this->connection->query($query);
                    $insertCount = 0;
                    $valuesQuery = '';
                }
            }
        }

        if ($insertCount > 0) {
            $query = rtrim($baseQuery . $valuesQuery, ',');
            $this->connection->query($query);
        }


        // host and service relations

        $baseQuery = 'INSERT INTO host_service_relation ' .
            '(host_host_id, service_service_id) ' .
            'VALUES ';
        $valuesQuery = '';

        $insertCount = 0;
        for ($i = $firstId; $i < $maxId; $i++) {
            $hostId = $injectedIds['host'][array_rand($injectedIds['host'], 1)];
            $ids[] = [
                'host_id' => $hostId,
                'service_id' => $i,
            ];
            $insertCount++;
            $valuesQuery .= '(' . $hostId . ',' . $i . '),';

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
        $this->connection->query('TRUNCATE service');
    }

    private function injectServiceTemplate(Service $service, array $properties, array $injectedIds): int
    {
        $result = $this->connection->query('SELECT MAX(service_id) AS max FROM service');
        $firstId = ((int) $result->fetch()['max']) + 1;

        $query = 'INSERT INTO service ' .
            '(service_id, service_description, service_alias, service_register, command_command_id) ' .
            'VALUES (' .
            $firstId . ',' .
            '"' . $service->getDescription() . '_template",' .
            '"' .  $service->getAlias() . '_template",' .
            '"0",' .
            $injectedIds['command'][array_rand($injectedIds['command'], 1)] .
            ')';
        $this->connection->query($query);


        // macros

        $minMetricsCount = isset($properties['command']['metrics']['min'])
            ? $properties['command']['metrics']['min']
            : 0;
        $maxMetricsCount = isset($properties['command']['metrics']['max'])
            ? $properties['command']['metrics']['max']
            : 10;

        $query = 'INSERT INTO on_demand_macro_service ' .
            '(svc_macro_name, svc_macro_value, svc_svc_id) ' .
            'VALUES ("$_SERVICEMETRICCOUNT$",' . random_int($minMetricsCount, $maxMetricsCount) . ',' . $firstId . '),' .
            '("$_SERVICEMETRICMINRANGE$",' . random_int(-1000000000, 0) . ',' . $firstId . '),' .
            '("$_SERVICEMETRICMAXRANGE$",' . random_int(0, 1000000000) . ',' . $firstId . ')';
        $this->connection->query($query);

        return $firstId;
    }
}
