<?php

namespace App\Domain;

interface InjectionServiceInterface
{
    public function inject(array $properties): array;

    public function purge();
}
