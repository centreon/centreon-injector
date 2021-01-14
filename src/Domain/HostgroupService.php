<?php

namespace App\Domain;

use App\Infrastructure\MySQL\HostgroupRepository;
use App\Domain\Hostgroup;

class HostgroupService implements InjectionServiceInterface
{
    private $hostgroupRepository;

    public function __construct(HostgroupRepository $hostgroupRepository)
    {
        $this->hostgroupRepository = $hostgroupRepository;
    }

    public function inject(array $properties, array $injectedIds): array
    {
        $hostgroup = new Hostgroup('hostgroup_name', 'hostgroup_alias');
        $ids = $this->hostgroupRepository->inject($hostgroup, $properties, $injectedIds);

        return $ids;
    }

    public function purge()
    {
        $this->hostgroupRepository->purge();
    }
}
