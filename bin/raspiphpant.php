<?php

use Aura\Cli\CliFactory;
use Aura\Cli\Status;
use Cvuorinen\Raspicam\Raspistill;
use Cvuorinen\RaspiPHPant\RaspiPHPantCommand;

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/parameters.php';

$cliFactory = new CliFactory;
$context    = $cliFactory->newContext($GLOBALS);
$stdio      = $cliFactory->newStdio();

$twitter = new Twitter(
    $config['twitter']['consumer-key'],
    $config['twitter']['consumer-secret'],
    $config['twitter']['access-token'],
    $config['twitter']['access-token-secret']
);

$camera = new Raspistill($config['raspicam']);

$command = new RaspiPHPantCommand(
    $stdio,
    $twitter,
    $camera,
    $config['pics-dir'],
    $config['hashtag']
);

$command->run($config['interval']);

exit(Status::SUCCESS);
