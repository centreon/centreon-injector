<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

use App\Domain\ContainerService;
use App\Domain\InjectionServiceInterface;

class InjectData extends Command
{
    private array $resourceInjectors;

    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'centreon:inject-data';

    private $ids = [];

    public function __construct(
        \Traversable $resourceInjectors,
        private ContainerService $containerService,
    ) {
        $this->resourceInjectors = iterator_to_array($resourceInjectors);
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->setDescription('Inject data in Centreon')
            ->setHelp('This command allows you to inject centreon objects directly in database...')
            ->addOption(
                'docker',
                null,
                InputOption::VALUE_NONE,
                'Start docker container instead of configured database connection',
                null
            )
            ->addOption(
                'docker-image',
                'i',
                InputOption::VALUE_OPTIONAL,
                'Docker image to use',
                'docker.centreon.com/centreon/centreon-web-alma9:develop'
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
            )
            ->addOption(
                'docker-http-port',
                null,
                InputOption::VALUE_OPTIONAL,
                'Docker http port to use',
                '80'
            )
            ->addOption(
                'docker-label',
                null,
                InputOption::VALUE_OPTIONAL,
                'Docker label to set',
                'injector'
            )
            ->addOption(
                'password',
                null,
                InputOption::VALUE_OPTIONAL,
                'Centreon password to use',
                'Centreon!2021'
            );
    }

    /**
     * @inheritDoc
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
    }

    /**
     * @inheritDoc
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $useDocker = $input->getOption('docker');
        $dockerImage = $input->getOption('docker-image');
        $containerId = $input->getOption('container-id');
        $dockerHttpPort = $input->getOption('docker-http-port');
        $dockerLabel = $input->getOption('docker-label');
        $password = $input->getOption('password');

        $configurationFile = $input->getOption('configurationFile');
        $filePath = realpath($configurationFile);

        $purge = $input->getOption('purge');

        if (!file_exists($filePath)) {
            $output->writeln("Configuration file {$configurationFile} does not exist");
            return Command::FAILURE;
        }

        $configuration = Yaml::parseFile($filePath);

        if ($useDocker === true) {
            $output->writeln([
                '',
                'Starting container',
                '==================',
            ]);
            $container = $this->containerService->run($dockerImage, $containerId, $dockerHttpPort, $dockerLabel);
            $output->writeln([
                'Container Id : ' . $container->getId(),
                'URL          : http://127.0.0.1:' . $container->getHttpPort() . '/centreon',
                'MySQL        : mysql -u root -pcentreon -P ' . $container->getMysqlPort(),
            ]);
        } else {
            $output->writeln([
                '',
                'Using database connection configured in .env file',
            ]);
        }

        if ($purge === true) {
            $output->writeln([
                '',
                'Purging data',
                '============',
            ]);

            foreach (array_reverse($this->resourceInjectors) as $resourceInjector) {
                $this->purge($resourceInjector, $output);
            }
        }


        $output->writeln([
            '',
            'Injecting data',
            '==============',
        ]);

        foreach ($this->resourceInjectors as $resourceInjector) {
            $this->ids[$resourceInjector->getName()] = $this->inject($resourceInjector, $configuration, $output);
        }

        $output->writeln([
            '',
            'Applying configuration',
            '======================',
        ]);

        if ($useDocker) {
            $clapiExportCommand = 'centreon -u admin -p ' . $password . ' -a APPLYCFG -v 1';
            shell_exec('docker exec ' . $dockerLabel . ' /bin/sh -c "' . $clapiExportCommand . '"');
        } else {
            system(__DIR__ . "/../../script/clapi_APPLYCFG.sh");
        }

        return Command::SUCCESS;
    }

    /**
     * Purge data
     *
     * @param InjectionServiceInterface $injectionService
     * @param OutputInterface $output
     * @return void
     */
    private function purge(
        InjectionServiceInterface $injectionService,
        OutputInterface $output
    ) {
        $output->write('Purging ' . $injectionService->getName() . 's ... ');
        $injectionService->purge();
        $output->writeln('<fg=green>OK</>');
    }

    /**
     * Inject data
     *
     * @param InjectionServiceInterface $injectionService
     * @param array $configuration
     * @param OutputInterface $output
     * @return array
     */
    private function inject(
        InjectionServiceInterface $injectionService,
        array $configuration,
        OutputInterface $output
    ): array {
        $output->write('Injecting ' . $injectionService->getName() . 's ... ');
        $injectedObjects = $injectionService->inject($configuration, $this->ids);
        $output->writeln('<fg=green>OK</>');

        return $injectedObjects;
    }
}
