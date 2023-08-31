<?php

namespace App\Domain;

use App\Infrastructure\MySQL\AclGroupRepository;
use App\Domain\AclGroup;

class AclGroupService implements InjectionServiceInterface
{
    public function __construct(private AclGroupRepository $aclGroupRepository)
    {
    }

    public function inject(array $properties, array $injectedIds): array
    {
        $aclGroup = new AclGroup('acl_group_name');
        $ids = $this->aclGroupRepository->inject($aclGroup, $properties, $injectedIds);

        return $ids;
    }

    public function purge()
    {
        $this->aclGroupRepository->purge();
    }
}
