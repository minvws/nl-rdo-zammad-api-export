<?php

declare(strict_types=1);

namespace Minvws\Zammad\Command;

use Minvws\Zammad\Service\HtmlGeneratorService;
use Minvws\Zammad\Service\ZammadService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportCommand extends Command
{
    protected static $defaultName = 'export';

    protected ZammadService $zammadService;
    protected HtmlGeneratorService $generatorService;

    public function __construct(ZammadService $zammadService)
    {
        parent::__construct();

        $this->zammadService = $zammadService;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Path to store to')
            ->addOption(
                'group',
                'g',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Group name of tickets to export',
                []
            )
            ->addOption(
                'exclude-group',
                'x',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Group name of tickets to exclude in the export',
                []
            )
            ->addOption(
                'percentage',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Which percentage of tickets should be exported (default 100%)',
                100
            )
            ->addOption(
                'search',
                's',
                InputOption::VALUE_OPTIONAL,
                'Search filter, or more complex query like "created_at:[2022-06-01 TO 2022-06-03]"',
                ''
            )
            ->setDescription("Exports Zammad tickets from user to destination")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->zammadService->setOutput($output);

        $this->zammadService->setVerbose($input->getOption('verbose'));
        $this->zammadService->export(
            $input->getOption('group'),
            $input->getOption('exclude-group'),
            $input->getArgument('path'),
            intval($input->getoption('percentage')),
            $input->getOption('search')
        );
        return Command::SUCCESS;
    }
}
