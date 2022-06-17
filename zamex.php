<?php

require __DIR__.'/vendor/autoload.php';

use Minvws\Zammad\Service\HtmlGeneratorService;
use Minvws\Zammad\Service\ZammadService;
use Minvws\Zammad\Command\ExportCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Dotenv\Dotenv;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

$loader = new FilesystemLoader('./templates');
$twigService = new Environment($loader);
$htmlGenerator = new HtmlGeneratorService($twigService);

$zammadService = new ZammadService($_ENV['ZAMMAD_URL'], $_ENV['ZAMMAD_TOKEN'], $htmlGenerator, $_ENV['ZAMMAD_VERBOSE']);
$exportCommand = new ExportCommand($zammadService);

$application = new Application('Zammad Exporteur DeLuxe', '1.0.0');
$application->add($exportCommand);
$application->run();
