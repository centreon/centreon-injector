<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class InjectData extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'centreon:inject-data';

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
          ->setDescription('Inject data in Centreon')
          ->setHelp('This command allows you to inject centreon objects directly in database...');

        $this->addArgument(
            'configurationFile',
            InputArgument::OPTIONAL,
            'Configuration file'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Configuration File : ' . $input->getArgument('configurationFile'));

        $output->writeln([
            'Injecting data',
            '==============',
            '',
        ]);

        return 0;
    }
}
