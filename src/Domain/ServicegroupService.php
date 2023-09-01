<?php

namespace App\Domain;

use App\Infrastructure\MySQL\ServicegroupRepository;
use App\Domain\Servicegroup;

class ServicegroupService implements InjectionServiceInterface
{
    private $servicegroupRepository;

    public function __construct(ServicegroupRepository $servicegroupRepository)
    {
        $this->servicegroupRepository = $servicegroupRepository;
    }

    public static function getDefaultPriority(): int
    {
        return InjectionPriority::Servicegroup->value;
    }

    public function getName(): string
    {
        return 'servicegroup';
    }

    public function inject(array $properties, array $injectedIds): array
    {
        $servicegroup = new Servicegroup('servicegroup_name', 'servicegroup_alias');
        $ids = $this->servicegroupRepository->inject($servicegroup, $properties, $injectedIds);

        return $ids;
    }

    public function purge()
    {
        $this->servicegroupRepository->purge();
    }
}
