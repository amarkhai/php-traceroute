<?php

namespace Amarkhay\Traceroute;

require __DIR__ . '/../vendor/autoload.php';

use Amarkhay\Traceroute\Command\RunServerCommand;
use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new RunServerCommand());

$application->run();