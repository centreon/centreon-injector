<?php

namespace App\Domain;

class Contact
{
    private $id;

    private $name;
    private $alias;
    private $password = '$2y$10$IWhlnT6vadelXk8ecbvkzeJ9Reka4VKnQTNa1POR2Tg7ji9uziJSu'; // hash of "centreon"

    public function __construct(string $name, string $alias)
    {
        $this->name = $name;
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

    public function getName()
    {
        return $this->name;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function getPassword()
    {
        return $this->password;
    }
}
