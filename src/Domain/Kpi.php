<?php

namespace App\Domain;

class Kpi
{
    private $id;

    public function getId()
    {
        return $this->id;
    }

    public function setId(?int $id)
    {
        $this->id = $id;
        return $this;
    }
}
