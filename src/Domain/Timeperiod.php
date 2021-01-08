<?php

namespace App\Domain;

class Timeperiod
{
    private $id;

    private $name;
    private $alias;

    private $mondayRange = '00:00-24:00';
    private $tuesdayRange = '00:00-24:00';
    private $wednesdayRange = '00:00-24:00';
    private $thursdayRange = '00:00-24:00';
    private $fridayRange = '00:00-24:00';
    private $saturdayRange = '00:00-24:00';
    private $sundayRange = '00:00-24:00';

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

    public function getMondayRange()
    {
        return $this->mondayRange;
    }

    public function getTuesdayRange()
    {
        return $this->tuesdayRange;
    }

    public function getWednesdayRange()
    {
        return $this->wednesdayRange;
    }

    public function getThursdayRange()
    {
        return $this->thursdayRange;
    }

    public function getFridayRange()
    {
        return $this->fridayRange;
    }

    public function getSaturdayRange()
    {
        return $this->saturdayRange;
    }

    public function getSundayRange()
    {
        return $this->sundayRange;
    }
}
