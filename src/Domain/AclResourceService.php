<?php

namespace App\Domain;

use App\Infrastructure\MySQL\AclResourceRepository;
use App\Domain\AclResource;

class AclResourceService implements InjectionServiceInterface
{
    public function __construct(private AclResourceRepository $aclResourceRepository)
    {
    }

    public static function getDefaultPriority(): int
    {
        return InjectionPriority::AclResource->value;
    }

    public function getName(): string
    {
        return 'acl_resource';
    }

    public function inject(array $properties, array $injectedIds): array
    {
        $aclResource = new AclResource('acl_resource_name');
        $ids = $this->aclResourceRepository->inject($aclResource, $properties, $injectedIds);

        return $ids;
    }

    public function purge()
    {
        $this->aclResourceRepository->purge();
    }
}
