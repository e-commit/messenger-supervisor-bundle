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

use Ecommit\MessengerSupervisorBundle\Tests\Functional\App\Messenger\Message\MessageError;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class MessageErrorHandler implements MessageHandlerInterface
{
    public function __invoke(MessageError $message): void
    {
        throw new \Exception('Error');
    }
}
