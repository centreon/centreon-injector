<?php

namespace App\Domain;

class Container
{
    private $id;
    private $httpPort;
    private $mysqlPort;

    public function __construct(string $id, int $httpPort, int $mysqlPort)
    {
        $this->id = $id;
        $this->httpPort = $httpPort;
        $this->mysqlPort = $mysqlPort;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getHttpPort()
    {
        return $this->httpPort;
    }

    public function getMysqlPort()
    {
        return $this->mysqlPort;
    }
}
