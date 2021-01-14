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
        $command = new Command(
            'cmd_name',
            'perl /usr/local/src/centreon-plugins/centreon_plugins.pl --plugin apps::centreon::local::plugin --mode not-so-dummy ' .
            '--status-sequence "ok,critical,warning,ok,ok,critical,critical,critical,ok,ok,ok,ok,ok,ok,ok" ' .
            '--output "this is the output"'
        );
        $ids = $this->commandRepository->inject($command, $properties['command']['count']);

        return $ids;
    }

    public function purge()
    {
        $this->commandRepository->purge();
    }
}
