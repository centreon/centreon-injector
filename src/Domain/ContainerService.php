<?php

namespace App\Domain;

use Doctrine\DBAL\Driver\Connection;

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

    public function run(string $image, ?string $containerId): Container
    {
        if ($containerId === null) {
            shell_exec('docker pull ' . $image);
            $containerId = shell_exec('docker run -d -p 80 -p 3306 ' . $image);
        }

        $containerId = str_replace(array('.', ' ', "\n", "\t", "\r"), '', $containerId);

        $httpPort = shell_exec('docker port ' . $containerId . ' 80');
        $httpPort = (int) explode(':', $httpPort)[1];

        $mysqlPort = shell_exec('docker port ' . $containerId . ' 3306');
        $mysqlPort = (int) explode(':', $mysqlPort)[1];

        shell_exec('docker exec ' . $containerId . ' yum install git');
        shell_exec('docker exec ' . $containerId . ' git clone https://github.com/centreon/centreon-plugins.git');
        shell_exec('docker exec ' . $containerId . ' sh -c "cp -R centreon-plugins/* /usr/lib/centreon/plugins/"');
        shell_exec('docker exec ' . $containerId . ' sh -c "chmod +x /usr/lib/centreon/plugins/centreon_plugins.pl"');

        $this->connection->changeDatabase('127.0.0.1', $mysqlPort, 'root', 'centreon', 'centreon');

        $container = new Container($containerId, $httpPort, $mysqlPort);

        return $container;
    }
}
