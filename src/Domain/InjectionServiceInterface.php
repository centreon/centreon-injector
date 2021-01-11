<?php

namespace App\Domain;

interface InjectionServiceInterface
{
    public function inject(array $properties, array $injectedIds): array;

    public function purge();
}
