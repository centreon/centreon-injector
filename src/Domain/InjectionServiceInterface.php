<?php

namespace App\Domain;

interface InjectionServiceInterface
{
    public function getName(): string;

    public function inject(array $properties, array $injectedIds): array;

    public function purge();
}
