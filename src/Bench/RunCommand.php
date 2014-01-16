<?php
namespace Bench;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('run')
            ->setDescription('Run a benchmark config')
            ->addArgument('config', InputArgument::REQUIRED, "The config file")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $input->getArgument('config');

        require $config;
        $output->writeln("Starting a benchmark run");

        $runner = new Runner(new \Config());
        $runner->run();
    }
}
