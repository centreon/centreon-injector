<?php

namespace App\Domain;

use App\Infrastructure\MySQL\CommandRepository;
use App\Domain\Command;

class CommandService implements InjectionServiceInterface
{
    private $commandRepository;

    public function __construct(CommandRepository $commandRepository)
    {
        $this->commandRepository = $commandRepository;
    }

    public function inject(array $properties): array
    {
        $commandRepository = new Command('cmd_name', 'echo ok');
        $this->commandRepository->inject($commandRepository, $properties['count']);

        return [];
    }

    public function purge()
    {
        $this->commandRepository->purge();
    }
}
