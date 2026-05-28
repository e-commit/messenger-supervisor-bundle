<?php

declare(strict_types=1);

/*
 * This file is part of the EcommitMessengerSupervisorBundle package.
 *
 * (c) E-commit <contact@e-commit.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Ecommit\MessengerSupervisorBundle\Tests\Functional\App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;

require __DIR__.'/../../vendor/autoload.php';

$kernel = new Kernel('test', true);

$application = new Application($kernel);
if (isset($argv[1]) && 'about' === $argv[1]) {
    $application->run();
} else {
    return $application;
}
