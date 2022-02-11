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

        $pluginPackResult = $this->connection->query('SELECT MAX(name) AS max FROM mod_ppm_pluginpack');
        $firstPluginPackId = ((int) $pluginPackResult->fetch()['max']) + 1;

        $providerTypeResult = $this->connection->query('SELECT MAX(name) AS max FROM mod_host_disco_provider_type');
        $firstProviderTypeId = ((int) $providerTypeResult->fetch()['max']) + 1;

        $providerResult = $this->connection->query('SELECT MAX(name) AS max FROM mod_host_disco_provider');
        $firstProviderId = ((int) $providerResult->fetch()['max']) + 1;

        $result = $this->connection->query('SELECT MAX(alias) AS max FROM mod_host_disco_job');
        $firstId = ((int) $result->fetch()['max']) + 1;
        $maxId = $firstId + $count;

        $baseIconsQuery = 'INSERT INTO mod_ppm_icons (icon_file) VALUES (NULL),';
        $iconsQuery = rtrim($baseIconsQuery, ',');
        $this->connection->query($iconsQuery);

        $basePluginPackQuery = 'INSERT INTO mod_ppm_pluginpack (name, slug, version, status, discovery_category_id, icon) VALUES ("my PP' . $firstPluginPackId . '", "my_pp' . $firstPluginPackId . '", "1.1.1", "0", 1, 1),';
        $pluginPackQuery = rtrim($basePluginPackQuery, ',');
        $this->connection->query($pluginPackQuery);

        $baseProviderTypeQuery = 'INSERT INTO mod_host_disco_provider_type (name, encryption_salt) VALUES ("my type' . $firstProviderTypeId . '", "salt' . $firstProviderTypeId . '"),';
        $providerTypeQuery = rtrim($baseProviderTypeQuery, ',');
        $this->connection->query($providerTypeQuery);

        $baseProviderQuery = 'INSERT INTO mod_host_disco_provider (pluginpack_id, name, slug, type_id, need_proxy, command_id, host_template_id) VALUES (' . $firstPluginPackId . ', "my provider' . $firstProviderId . '", "my_provider' . $firstProviderId . '", ' . $firstProviderTypeId . ', false, 1, 2),';
        $providerQuery = rtrim($baseProviderQuery, ',');
        $this->connection->query($providerQuery);

        $baseQuery = 'INSERT INTO mod_host_disco_job ' .
            '(alias, provider_id, execution_mode, analysis_mode, save_mode, status, ' .
            'duration, message, monitoring_server_id, discovered_items) ' .
            'VALUES ';
        $valuesQuery = '';

        $insertCount = 0;
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
                $hostDiscoJob->getMonitoringServerId() .
                '34' .
                '),';
        }

        $query = rtrim($baseQuery . $valuesQuery, ',');
        $this->connection->query($query);

        return $ids;
    }

    public function purge()
    {
        $this->connection->query('TRUNCATE mod_host_disco_job');
        $this->connection->query('TRUNCATE mod_ppm_pluginpack');
        $this->connection->query('TRUNCATE mod_host_disco_provider');
        $this->connection->query('TRUNCATE mod_host_disco_provider_type');
        $this->connection->query('TRUNCATE mod_ppm_icons');
    }

    public function isHostDiscoInstalled(): bool
    {
        $result = $this->connection->query('SELECT id FROM modules_informations WHERE name = "centreon-autodiscovery-server"');

        if ($result->fetch()) {
            return true;
        }

        return false;
    }
}
