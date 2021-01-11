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

    public function inject(array $properties, array $injectedIds): array
    {
        $command = new Command('cmd_name', 'echo ok');
        $ids = $this->commandRepository->inject($command, $properties['command']['count']);

        return $ids;
    }

    public function purge()
    {
        $this->commandRepository->purge();
    }
}
