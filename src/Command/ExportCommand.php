<?php

declare(strict_types=1);

namespace Minvws\Zammad\Command;

use Minvws\Zammad\Service\ZammadService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExportCommand extends Command
{
    protected static $defaultName = 'export';

    protected ZammadService $zammadService;

    public function __construct(ZammadService $zammadService)
    {
        parent::__construct();

        $this->zammadService = $zammadService;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email of user to store tickets')
            ->addArgument('path', InputArgument::REQUIRED, 'Path to store to')
            ->setDescription("Exports zammad tickets from user to destination")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->zammadService->export($input->getArgument('email'), $input->getArgument('path'));
        return Command::SUCCESS;
    }
}
