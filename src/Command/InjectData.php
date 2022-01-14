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
use App\Domain\ContactService;
use App\Domain\HostService;
use App\Domain\ServiceService;
use App\Domain\MetaserviceService;
use App\Domain\HostgroupService;
use App\Domain\ServicegroupService;
use App\Domain\HostCategoryService;
use App\Domain\ServiceCategoryService;
use App\Domain\BaService;
use App\Domain\KpiService;

class InjectData extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'centreon:inject-data';

    private $containerService;

    private $timeperiodService;
    private $commandService;
    private $contactService;
    private $hostService;
    private $serviceService;
    private $metaserviceService;
    private $hostgroupService;
    private $servicegroupService;
    private $hostCategoryService;
    private $serviceCategoryService;
    private $baService;
    private $kpiService;

    private $ids = [
        'timeperiod' => [],
        'command' => [],
        'contact' => [],
        'host' => [],
        'service' => [],
        'metaservice' => [],
        'hostgroup' => [],
        'servicegroup' => [],
        'hostcategory' => [],
        'servicecategory' => [],
        'ba' => [],
        'kpi' => [],
    ];

    /**
     * Constructor
     *
     * @param ContainerService $containerService
     * @param TimeperiodService $timeperiodService
     * @param CommandService $commandService
     * @param ContactService $contactService
     * @param HostService $hostService
     * @param ServiceService $serviceService
     * @param MetaserviceService $metaserviceService
     * @param HostgroupService $hostgroupService
     * @param ServicegroupService $servicegroupService
     * @param HostCategoryService $hostCategoryService
     * @param ServiceCategoryService $serviceCategoryService
     * @param BaService $baService
     * @param KpiService $kpiService
     */
    public function __construct(
        ContainerService $containerService,
        TimeperiodService $timeperiodService,
        CommandService $commandService,
        ContactService $contactService,
        HostService $hostService,
        ServiceService $serviceService,
        MetaserviceService $metaserviceService,
        HostgroupService $hostgroupService,
        ServicegroupService $servicegroupService,
        HostCategoryService $hostCategoryService,
        ServiceCategoryService $serviceCategoryService,
        BaService $baService,
        KpiService $kpiService
    ) {
        parent::__construct();

        $this->containerService = $containerService;

        $this->timeperiodService = $timeperiodService;
        $this->commandService = $commandService;
        $this->contactService = $contactService;
        $this->hostService = $hostService;
        $this->serviceService = $serviceService;
        $this->metaserviceService = $metaserviceService;
        $this->hostgroupService = $hostgroupService;
        $this->servicegroupService = $servicegroupService;
        $this->hostCategoryService = $hostCategoryService;
        $this->serviceCategoryService = $serviceCategoryService;
        $this->baService = $baService;
        $this->kpiService = $kpiService;
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
                'registry.centreon.com/mon-web-develop:centos7'
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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $useDocker = $input->getOption('docker');
        $dockerImage = $input->getOption('docker-image');
        $containerId = $input->getOption('container-id');
        $dockerHttpPort = $input->getOption('docker-http-port');
        $dockerLabel = $input->getOption('docker-label');

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


            $this->purge('kpi', $this->kpiService, $output);
            $this->purge('ba', $this->baService, $output);
            $this->purge('service categorie', $this->serviceCategoryService, $output);
            $this->purge('host categorie', $this->hostCategoryService, $output);
            $this->purge('servicegroup', $this->servicegroupService, $output);
            $this->purge('hostgroup', $this->hostgroupService, $output);
            $this->purge('metaservice', $this->serviceService, $output);
            $this->purge('service', $this->serviceService, $output);
            $this->purge('host', $this->hostService, $output);
            $this->purge('contact', $this->contactService, $output);
            $this->purge('timeperiod', $this->timeperiodService, $output);
            $this->purge('command', $this->commandService, $output);
        }


        $output->writeln([
            '',
            'Injecting data',
            '==============',
        ]);

        $this->ids['timeperiod'] = $this->inject('timeperiod', $this->timeperiodService, $configuration, $output);
        $this->ids['command'] = $this->inject('command', $this->commandService, $configuration, $output);
        $this->ids['contact'] = $this->inject('contact', $this->contactService, $configuration, $output);
        $this->ids['host'] = $this->inject('host', $this->hostService, $configuration, $output);
        $this->ids['service'] = $this->inject('service', $this->serviceService, $configuration, $output);
        $this->ids['metaservice'] = $this->inject('metaservice', $this->metaserviceService, $configuration, $output);
        $this->ids['hostgroup'] = $this->inject('hostgroup', $this->hostgroupService, $configuration, $output);
        $this->ids['servicegroup'] = $this->inject('servicegroup', $this->servicegroupService, $configuration, $output);
        $this->ids['host_category'] = $this->inject(
            'host categorie',
            $this->hostCategoryService,
            $configuration,
            $output
        );
        $this->ids['service_category'] = $this->inject(
            'service categorie',
            $this->serviceCategoryService,
            $configuration,
            $output
        );
        $this->ids['ba'] = $this->inject(
            'ba',
            $this->baService,
            $configuration,
            $output
        );
        $this->ids['kpi'] = $this->inject(
            'kpi',
            $this->kpiService,
            $configuration,
            $output
        );

        $output->writeln([
            '',
            'Applying configuration',
            '======================',
        ]);
        shell_exec('docker exec ' . $dockerLabel . ' /bin/sh -c "centreon -u admin -p centreon -a APPLYCFG -v 1"');

        return Command::SUCCESS;
    }

    /**
     * Purge data
     *
     * @param string $name
     * @param InjectionServiceInterface $injectionService
     * @param OutputInterface $output
     * @return void
     */
    private function purge(
        string $name,
        InjectionServiceInterface $injectionService,
        OutputInterface $output
    ) {
        $output->write('Purging ' . $name . 's ... ');
        $injectionService->purge();
        $output->writeln('<fg=green>OK</>');
    }

    /**
     * Inject data
     *
     * @param string $name
     * @param InjectionServiceInterface $injectionService
     * @param array $configuration
     * @param OutputInterface $output
     * @return array
     */
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
