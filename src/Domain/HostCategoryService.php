<?php

namespace App\Domain;

use App\Infrastructure\MySQL\HostCategoryRepository;
use App\Domain\HostCategory;

class HostCategoryService implements InjectionServiceInterface
{
    private $hostCategoryRepository;

    public function __construct(HostCategoryRepository $hostCategoryRepository)
    {
        $this->hostCategoryRepository = $hostCategoryRepository;
    }

    public function inject(array $properties, array $injectedIds): array
    {
        $hostCategory = new HostCategory('host_category_name', 'host_category_alias');
        $ids = $this->hostCategoryRepository->inject($hostCategory, $properties, $injectedIds);

        return $ids;
    }

    public function purge()
    {
        $this->hostCategoryRepository->purge();
    }
}
