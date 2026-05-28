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

use Ecommit\MessengerSupervisorBundle\DependencyInjection\Configuration;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * @phpstan-import-type Transport from Configuration
 * @phpstan-import-type MailerConfig from Configuration
 */
interface ErrorEmailBuilderInterface
{
    /**
     * @param Transport    $transportInfos
     * @param MailerConfig $mailerParameters
     */
    public function getBody(WorkerMessageFailedEvent $event, array $transportInfos, array $mailerParameters, bool $stop): string;

    /**
     * @param Transport    $transportInfos
     * @param MailerConfig $mailerParameters
     */
    public function getSubject(WorkerMessageFailedEvent $event, array $transportInfos, array $mailerParameters, bool $stop): string;
}
