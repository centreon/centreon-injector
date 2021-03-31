<?php

namespace App\Domain;

use App\Infrastructure\MySQL\BaRepository;
use App\Domain\Ba;

class BaService implements InjectionServiceInterface
{
    private $baRepository;

    public function __construct(BaRepository $baRepository)
    {
        $this->baRepository = $baRepository;
    }

    public function inject(array $properties, array $injectedIds): array
    {
        if (!$this->baRepository->isBamInstalled()) {
            return [];
        }

        $ba = new Ba(
            'ba_name',
            'ba_description'
        );
        $ids = $this->baRepository->inject($ba, $properties, $injectedIds);

        return $ids;
    }

    public function purge()
    {
        if ($this->baRepository->isBamInstalled()) {
            $this->baRepository->purge();
        }
    }
}
