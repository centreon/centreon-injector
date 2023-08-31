<?php

namespace App\Domain;

use App\Infrastructure\MySQL\UserRepository;
use App\Domain\User;
use App\Domain\UserRole;

class UserService implements InjectionServiceInterface
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function inject(array $properties, array $injectedIds): array
    {
        $ids = [];

        $users = [
            'admin' => UserRole::Administrator,
            'editor' => UserRole::Editor,
            'user' => UserRole::User,
        ];

        foreach ($users as $userName => $userRole) {
            $user = new User($userName, $userRole);
            $ids = [
                ...$ids,
                ...$this->userRepository->inject($user, $properties, $injectedIds),
            ];
        }

        return $ids;
    }

    public function purge()
    {
        $this->userRepository->purge();
    }
}
