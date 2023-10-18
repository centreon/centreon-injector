<?php

namespace App\Infrastructure\MySQL;

use App\Domain\Doctrine\DynamicConnection as Connection;
use App\Domain\HostDiscoJob;

class HostDiscoJobRepository
{
    private $connection;

    private const PROPERTY_NAME = 'host_disco_job';

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function inject(HostDiscoJob $hostDiscoJob, array $properties, array $injectedIds): array
    {
        $ids = [];

        $count = $properties[self::PROPERTY_NAME]['count'];

        if ($count === 0) {
            return $ids;
        }

        $pluginPackResult = $this->connection->executeQuery('SELECT MAX(pluginpack_id) AS max FROM mod_ppm_pluginpack');
        $firstPluginPackId = ((int) $pluginPackResult->fetchAssociative()['max']) + 1;

        $providerTypeResult = $this->connection->executeQuery('SELECT MAX(id) AS max FROM mod_host_disco_provider_type');
        $firstProviderTypeId = ((int) $providerTypeResult->fetchAssociative()['max']) + 1;

        $providerResult = $this->connection->executeQuery('SELECT MAX(id) AS max FROM mod_host_disco_provider');
        $firstProviderId = ((int) $providerResult->fetchAssociative()['max']) + 1;

        $result = $this->connection->executeQuery('SELECT MAX(id) AS max FROM mod_host_disco_job');
        $firstId = ((int) $result->fetchAssociative()['max']) + 1;
        $maxId = $firstId + $count;

        $iconsQuery = 'INSERT INTO mod_ppm_icons (icon_file) VALUES (NULL)';
        $this->connection->executeQuery($iconsQuery);

        $pluginPackQuery = 'INSERT INTO mod_ppm_pluginpack (name, slug, version, status, discovery_category_id, icon) VALUES ("my PP' . $firstPluginPackId . '", "my_pp' . $firstPluginPackId . '", "1.1.1", "0", 1, 1)';
        $this->connection->executeQuery($pluginPackQuery);

        $providerTypeQuery = 'INSERT INTO mod_host_disco_provider_type (name, encryption_salt) VALUES ("my type' . $firstProviderTypeId . '", "salt' . $firstProviderTypeId . '")';
        $this->connection->executeQuery($providerTypeQuery);

        $baseProviderQuery = 'INSERT INTO mod_host_disco_provider (pluginpack_id, name, slug, type_id, need_proxy, command_id, host_template_id) VALUES (' . $firstPluginPackId . ', "my provider' . $firstProviderId . '", "my_provider' . $firstProviderId . '", ' . $firstProviderTypeId . ', false, 1, 2),';
        $providerQuery = rtrim($baseProviderQuery, ',');
        $this->connection->executeQuery($providerQuery);

        $baseQuery = 'INSERT INTO mod_host_disco_job ' .
            '(alias, provider_id, execution_mode, analysis_mode, save_mode, status, ' .
            'duration, message, monitoring_server_id, discovered_items) ' .
            'VALUES ';
        $valuesQuery = '';

        for ($i = $firstId; $i < $maxId; $i++) {
            $ids[] = $i;
            $valuesQuery .= '(' .
                '"' . $hostDiscoJob->getAlias() . $i . '",' .
                $firstProviderId . ',' .
                $hostDiscoJob->getExecutionMode() . ',' .
                $hostDiscoJob->getAnalysisMode() . ',' .
                $hostDiscoJob->getSaveMode() . ',' .
                $hostDiscoJob->getStatus() . ',' .
                $hostDiscoJob->getDuration() . ',' .
                'NULL,' .
                $hostDiscoJob->getMonitoringServerId() . ',' .
                '34' .
                '),';
        }

        $query = rtrim($baseQuery . $valuesQuery, ',');
        $this->connection->executeQuery($query);

        return $ids;
    }

    public function purge()
    {
        $this->connection->executeQuery('TRUNCATE mod_host_disco_job');
        $this->connection->executeQuery('TRUNCATE mod_ppm_pluginpack');
        $this->connection->executeQuery('TRUNCATE mod_host_disco_provider');
        $this->connection->executeQuery('TRUNCATE mod_host_disco_provider_type');
        $this->connection->executeQuery('TRUNCATE mod_ppm_icons');
    }

    public function isHostDiscoInstalled(): bool
    {
        $result = $this->connection->executeQuery('SELECT id FROM modules_informations WHERE name = "centreon-autodiscovery-server"');

        if ($result->fetchAssociative()) {
            return true;
        }

        return false;
    }
}
