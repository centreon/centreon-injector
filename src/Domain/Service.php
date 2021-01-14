<?php

namespace App\Domain;

class Service
{
    private $id;

    private $description;
    private $alias;

    public function __construct(string $description, string $alias)
    {
        $this->description = $description;
        $this->alias = $alias;
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

    public function getDescription()
    {
        return $this->description;
    }

    public function getAlias()
    {
        return $this->alias;
    }
}
