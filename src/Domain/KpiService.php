<?php

namespace App\Domain;

use App\Infrastructure\MySQL\KpiRepository;
use App\Infrastructure\MySQL\BaRepository;
use App\Domain\Kpi;

class KpiService implements InjectionServiceInterface
{
    private $kpiRepository;
    private $baRepository;

    public function __construct(KpiRepository $kpiRepository, BaRepository $baRepository)
    {
        $this->kpiRepository = $kpiRepository;
        $this->baRepository = $baRepository;
    }

    public function inject(array $properties, array $injectedIds): array
    {
        if (!$this->baRepository->isBamInstalled()) {
            return [];
        }

        $kpi = new Kpi();
        $ids = $this->kpiRepository->inject($kpi, $properties, $injectedIds);

        return $ids;
    }

    public function purge()
    {
        if ($this->baRepository->isBamInstalled()) {
            $this->kpiRepository->purge();
        }
    }
}
