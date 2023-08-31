<?php

namespace App\Domain;

use App\Domain\Doctrine\DynamicConnection as Connection;

class ContainerService
{
    /**
     * @var App\Domain\Doctrine\DynamicConnection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function run(string $image, ?string $containerId, int $httpPort, string $label): Container
    {
        if ($containerId === null) {
            shell_exec('docker pull ' . $image);
            $containerId = shell_exec('docker run -d -p ' . $httpPort . ':80 -p 3306:3306 --name ' . $label . ' ' . $image);
        }

        $containerId = str_replace(array('.', ' ', "\n", "\t", "\r"), '', $containerId);

        $mysqlPort = shell_exec('docker port ' . $containerId . ' 3306');
        $mysqlPort = (int) explode(':', $mysqlPort)[1];

        shell_exec('docker exec ' . $containerId . ' dnf install -y git');
        shell_exec('docker exec ' . $containerId . ' git clone --depth=1 https://github.com/centreon/centreon-plugins.git');
        shell_exec('docker exec ' . $containerId . ' sh -c "cp -R centreon-plugins/src/* /usr/lib/centreon/plugins/"');
        shell_exec('docker exec ' . $containerId . ' sh -c "chmod +x /usr/lib/centreon/plugins/centreon_plugins.pl"');

        $this->connection->changeDatabase('127.0.0.1', $mysqlPort, 'root', 'centreon', 'centreon');

        $container = new Container($containerId, $httpPort, $mysqlPort);

        return $container;
    }
}
