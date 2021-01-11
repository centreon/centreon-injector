<?php

namespace App\Domain;

class Host
{
    private $id;

    private $name;
    private $alias;
    private $address;

    public function __construct(string $name, string $alias, string $address)
    {
        $this->name = $name;
        $this->alias = $alias;
        $this->address = $address;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId(?int $id)
    {
        $this->id = $id;
        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function getAddress()
    {
        return $this->address;
    }
}
