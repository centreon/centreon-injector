<?php

namespace App\Domain;

use App\Infrastructure\MySQL\KpiRepository;
use App\Domain\Kpi;

class KpiService implements InjectionServiceInterface
{
    private $kpiRepository;

    public function __construct(KpiRepository $kpiRepository)
    {
        $this->kpiRepository = $kpiRepository;
    }

    public function inject(array $properties, array $injectedIds): array
    {
        $kpi = new Kpi();
        $ids = $this->kpiRepository->inject($kpi, $properties, $injectedIds);

        return $ids;
    }

    public function purge()
    {
        $this->kpiRepository->purge();
    }
}
