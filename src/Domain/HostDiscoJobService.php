<?php

namespace App\Domain;

use App\Infrastructure\MySQL\HostDiscoJobRepository;
use App\Domain\HostDiscoJob;

class HostDiscoJobService implements InjectionServiceInterface
{
    private $hostDiscoJobRepository;

    public function __construct(HostDiscoJobRepository $hostDiscoJobRepository)
    {
        $this->hostDiscoJobRepository = $hostDiscoJobRepository;
    }

    public function inject(array $properties, array $injectedIds): array
    {
        if (!$this->hostDiscoJobRepository->isHostDiscoInstalled()) {
            return [];
        }

        $hostDiscoJob = new HostDiscoJob(
            'Job name',
            1,
            0,
            0,
            0,
            1,
            34,
            null,
            '1',
        );
        $ids = $this->hostDiscoJobRepository->inject($hostDiscoJob, $properties, $injectedIds);

        return $ids;
    }

    public function purge()
    {
        if ($this->hostDiscoJobRepository->isHostDiscoInstalled()) {
            $this->hostDiscoJobRepository->purge();
        }
    }
}
