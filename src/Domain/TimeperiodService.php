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

    public function inject(array $properties): array
    {
        $timeperiod = new Timeperiod('tp_name', 'tp_alias');
        $this->timeperiodRepository->inject($timeperiod, $properties['count']);

        return [];
    }

    public function purge()
    {
        $this->timeperiodRepository->purge();
    }
}
