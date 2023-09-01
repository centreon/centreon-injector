<?php

namespace App\Domain;

use App\Infrastructure\MySQL\TimeperiodRepository;
use App\Domain\Timeperiod;

class TimeperiodService implements InjectionServiceInterface
{
    private $timeperiodRepository;

    public function __construct(TimeperiodRepository $timeperiodRepository)
    {
        $this->timeperiodRepository = $timeperiodRepository;
    }

    public static function getDefaultPriority(): int
    {
        return InjectionPriority::Timeperiod->value;
    }

    public function getName(): string
    {
        return 'timeperiod';
    }

    public function inject(array $properties, array $injectedIds): array
    {
        $timeperiod = new Timeperiod('tp_name', 'tp_alias');
        $ids = $this->timeperiodRepository->inject($timeperiod, $properties, $injectedIds);

        return $ids;
    }

    public function purge()
    {
        $this->timeperiodRepository->purge();
    }
}
