<?php

namespace App\Domain;

class Contact
{
    private $id;

    public function __construct(private string $name, private string $alias)
    {
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }
}
