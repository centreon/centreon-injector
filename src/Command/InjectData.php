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
use App\Domain\HostDiscoJobService;
use App\Domain\AclMenuService;
use App\Domain\AclResourceService;
use App\Domain\AclGroupService;
use App\Domain\UserService;

class InjectData extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'centreon:inject-data';

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
        'hostdiscojob' => [],
        'aclresource' => [],
        'aclgroup' => [],
        'user' => [],
    ];

    public function __construct(
        private ContainerService $containerService,
        private TimeperiodService $timeperiodService,
        private CommandService $commandService,
        private ContactService $contactService,
        private HostService $hostService,
        private ServiceService $serviceService,
        private MetaserviceService $metaserviceService,
        private HostgroupService $hostgroupService,
        private ServicegroupService $servicegroupService,
        private HostCategoryService $hostCategoryService,
        private ServiceCategoryService $serviceCategoryService,
        private BaService $baService,
        private KpiService $kpiService,
        private HostDiscoJobService $hostDiscoJobService,
        private AclMenuService $aclMenuService,
        private AclResourceService $aclResourceService,
        private AclGroupService $aclGroupService,
        private UserService $userService,
    ) {
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

            $this->purge('user', $this->userService, $output);
            $this->purge('aclresource', $this->aclResourceService, $output);
            $this->purge('aclgroup', $this->aclGroupService, $output);
            $this->purge('aclmenu', $this->aclGroupService, $output);
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
            $this->purge('hostdiscojob', $this->hostDiscoJobService, $output);
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
        $this->ids['hostdiscojob'] = $this->inject(
            'hostdiscojob',
            $this->hostDiscoJobService,
            $configuration,
            $output
        );
        $this->ids['aclmenu'] = $this->inject(
            'aclmenu',
            $this->aclMenuService,
            $configuration,
            $output
        );
        $this->ids['aclresource'] = $this->inject(
            'aclresource',
            $this->aclResourceService,
            $configuration,
            $output
        );
        $this->ids['aclgroup'] = $this->inject(
            'aclgroup',
            $this->aclGroupService,
            $configuration,
            $output
        );
        $this->ids['user'] = $this->inject(
            'user',
            $this->userService,
            $configuration,
            $output
        );

        $output->writeln([
            '',
            'Applying configuration',
            '======================',
        ]);

        $clapiExportCommand = 'centreon -u admin -p ' . $password . ' -a APPLYCFG -v 1';
        if ($useDocker) {
            shell_exec('docker exec ' . $dockerLabel . ' /bin/sh -c "' . $clapiExportCommand . '"');
        } else {
            shell_exec($clapiExportCommand);
        }

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
