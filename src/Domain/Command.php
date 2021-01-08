<?php

namespace App\Domain;

class Command
{
    private $id;

    private $name;
    private $line;

    private $type = 2;

    public function __construct(string $name, string $line)
    {
        $this->name = $name;
        $this->line = $line;
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

    public function getLine()
    {
        return $this->line;
    }

    public function getType()
    {
        return $this->type;
    }
}
