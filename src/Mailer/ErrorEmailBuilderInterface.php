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

namespace Ecommit\MessengerSupervisorBundle\Mailer;

use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

interface ErrorEmailBuilderInterface
{
    public function getBody(WorkerMessageFailedEvent $event, array $transportInfos, array $mailerParameters, bool $stop): string;

    public function getSubject(WorkerMessageFailedEvent $event, array $transportInfos, array $mailerParameters, bool $stop): string;
}
