<?php

namespace App\Domain;

use App\Infrastructure\MySQL\AclMenuRepository;

class AclMenuService implements InjectionServiceInterface
{
    public function __construct(private AclMenuRepository $aclMenuRepository)
    {
    }

    public function inject(array $properties, array $injectedIds): array
    {
        $ids = $this->aclMenuRepository->inject($properties, $injectedIds);

        return $ids;
    }

    public function purge()
    {
        $this->aclMenuRepository->purge();
    }
}
