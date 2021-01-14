<?php

namespace App\Domain;

use App\Infrastructure\MySQL\ServiceCategoryRepository;
use App\Domain\ServiceCategory;

class ServiceCategoryService implements InjectionServiceInterface
{
    private $serviceCategoryRepository;

    public function __construct(ServiceCategoryRepository $serviceCategoryRepository)
    {
        $this->serviceCategoryRepository = $serviceCategoryRepository;
    }

    public function inject(array $properties, array $injectedIds): array
    {
        $serviceCategory = new ServiceCategory('service_category_name', 'service_category_alias');
        $ids = $this->serviceCategoryRepository->inject($serviceCategory, $properties, $injectedIds);

        return $ids;
    }

    public function purge()
    {
        $this->serviceCategoryRepository->purge();
    }
}
