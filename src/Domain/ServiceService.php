<?php

namespace App\Domain;

use App\Infrastructure\MySQL\ServiceRepository;
use App\Domain\Service;

class ServiceService implements InjectionServiceInterface
{
    private $serviceRepository;

    public function __construct(ServiceRepository $serviceRepository)
    {
        $this->serviceRepository = $serviceRepository;
    }

    public function inject(array $properties, array $injectedIds): array
    {
        $service = new Service('service_name', 'service_description');
        $ids = $this->serviceRepository->inject($service, $properties, $injectedIds);

        return $ids;
    }

    public function purge()
    {
        $this->serviceRepository->purge();
    }
}
