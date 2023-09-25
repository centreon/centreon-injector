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

    public static function getDefaultPriority(): int
    {
        return InjectionPriority::Command->value;
    }

    public function getName(): string
    {
        return 'command';
    }

    public function inject(array $properties, array $injectedIds): array
    {
        $command = new Command(
            'cmd_name',
            'perl /usr/lib/centreon/plugins/centreon_plugins.pl --plugin apps::centreon::local::plugin ' .
            '--mode not-so-dummy ' .
            '--statefile-dir "/tmp" ' .
            '--output "output"'
        );
        $ids = $this->commandRepository->inject($command, $properties, $injectedIds);

        return $ids;
    }

    public function purge()
    {
        $this->commandRepository->purge();
    }
}
