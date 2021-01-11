<?php

namespace App\Command;

use App\Domain\ContainerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

use App\Domain\InjectionServiceInterface;
use App\Domain\TimeperiodService;
use App\Domain\CommandService;
use App\Domain\HostService;

class InjectData extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'centreon:inject-data';

    private $containerService;

    private $timeperiodService;
    private $commandService;
    private $hostService;

    private $ids = [
        'timeperiod' => [],
        'command' => [],
        'host' => [],
    ];

    public function __construct(
        ContainerService $containerService,
        TimeperiodService $timeperiodService,
        CommandService $commandService,
        HostService $hostService
    ) {
        parent::__construct();

        $this->containerService = $containerService;

        $this->timeperiodService = $timeperiodService;
        $this->commandService = $commandService;
        $this->hostService = $hostService;
    }

    protected function configure()
    {
        $this
            ->setDescription('Inject data in Centreon')
            ->setHelp('This command allows you to inject centreon objects directly in database...')
            ->addOption(
                'docker-image',
                'i',
                InputOption::VALUE_OPTIONAL,
                'Docker image to use',
                'registry.centreon.com/mon-web-21.04:centos7'
            )
            ->addOption(
                'container-id',
                null,
                InputOption::VALUE_OPTIONAL,
                'Existing container id to use',
                null
            )
            ->addOption(
                'configurationFile',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Configuration file path',
                'data.yaml'
            )
            ->addOption(
                'purge',
                'p',
                InputOption::VALUE_NONE,
                'Purge data'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dockerImage = $input->getOption('docker-image');
        $containerId = $input->getOption('container-id');

        $configurationFile = $input->getOption('configurationFile');
        $filePath = realpath($configurationFile);

        $purge = $input->getOption('purge');

        if (!file_exists($filePath)) {
            $output->writeln("Configuration file {$configurationFile} does not exist");
            return Command::FAILURE;
        }

        $configuration = Yaml::parseFile($filePath);

        $output->writeln([
            '',
            'Running container',
            '=================',
        ]);
        $container = $this->containerService->run($dockerImage, $containerId);
        $output->writeln([
            'Container Id : ' . $container->getId(),
            'URL          : http://127.0.0.1:' . $container->getHttpPort() . '/centreon',
            'MySQL        : mysql -u root -pcentreon -P ' . $container->getMysqlPort(),
        ]);

        if ($purge === true) {
            $output->writeln([
                '',
                'Purging data',
                '============',
            ]);

            $this->purge('timeperiod', $this->timeperiodService, $output);
            $this->purge('command', $this->commandService, $output);
            $this->purge('host', $this->hostService, $output);
        }


        $output->writeln([
            '',
            'Injecting data',
            '==============',
        ]);

        $this->ids['timeperiod'] = $this->inject('timeperiod', $this->timeperiodService, $configuration, $output);
        $this->ids['command'] = $this->inject('command', $this->commandService, $configuration, $output);
        $this->ids['host'] = $this->inject('host', $this->hostService, $configuration, $output);

        //shell_exec('docker kill ' . $container->getId());
        //shell_exec('docker rm ' . $container->getId());

        return Command::SUCCESS;
    }

    private function purge(
        string $name,
        InjectionServiceInterface $injectionService,
        OutputInterface $output
    ) {
        $output->write('Purging ' . $name . 's ... ');
        $injectionService->purge();
        $output->writeln('<fg=green>OK</>');
    }

    private function inject(
        string $name,
        InjectionServiceInterface $injectionService,
        array $configuration,
        OutputInterface $output
    ): array {
        $output->write('Injecting ' . $name . 's ... ');
        $injectedObjects = $injectionService->inject($configuration, $this->ids);
        $output->writeln('<fg=green>OK</>');

        return $injectedObjects;
    }
}
