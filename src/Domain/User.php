<?php

namespace App\Domain;

use App\Domain\UserRole;

class User
{
    private ?int $id = null;

    public function __construct(private string $name, private UserRole $userRole)
    {
        $this->name = $name;
    }

    public function getId(): ?int
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

    public function getRole(): UserRole
    {
        return $this->userRole;
    }
}
