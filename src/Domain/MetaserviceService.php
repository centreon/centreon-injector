<?php

namespace App\Domain;

use App\Infrastructure\MySQL\MetaserviceRepository;
use App\Domain\Metaservice;

class MetaserviceService implements InjectionServiceInterface
{
    private $metaserviceRepository;

    public function __construct(MetaserviceRepository $metaserviceRepository)
    {
        $this->metaserviceRepository = $metaserviceRepository;
    }

    public function inject(array $properties, array $injectedIds): array
    {
        $metaservice = new Metaservice('metaservice_name');
        $ids = $this->metaserviceRepository->inject($metaservice, $properties, $injectedIds);

        return $ids;
    }

    public function purge()
    {
        $this->metaserviceRepository->purge();
    }
}
