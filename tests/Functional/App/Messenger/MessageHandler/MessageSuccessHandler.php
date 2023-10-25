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

namespace Ecommit\MessengerSupervisorBundle\Tests\Functional\App\Messenger\MessageHandler;

use Ecommit\MessengerSupervisorBundle\Tests\Functional\App\Messenger\Message\MessageSuccess;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class MessageSuccessHandler
{
    public function __invoke(MessageSuccess $message): void
    {
    }
}
