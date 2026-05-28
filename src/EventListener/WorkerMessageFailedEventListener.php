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

namespace Ecommit\MessengerSupervisorBundle\EventListener;

use Ecommit\MessengerSupervisorBundle\DependencyInjection\Configuration;
use Ecommit\MessengerSupervisorBundle\Exception\TransportNotFoundException;
use Ecommit\MessengerSupervisorBundle\Mailer\ErrorEmailBuilderInterface;
use Ecommit\MessengerSupervisorBundle\Supervisor\Supervisor;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * @phpstan-import-type Transport from Configuration
 * @phpstan-import-type MailerConfig from Configuration
 */
class WorkerMessageFailedEventListener
{
    public const FAILURE_ACTION_ALWAYS = 'always';
    public const FAILURE_ACTION_WILL_NOT_RETRY = 'will-not-retry';
    public const FAILURE_ACTION_NEVER = 'never';

    /**
     * @param MailerConfig $mailerParameters
     */
    public function __construct(protected Supervisor $supervisor, protected ErrorEmailBuilderInterface $errorEmailBuilder, protected ?LoggerInterface $logger, protected MailerInterface $mailer, protected array $mailerParameters)
    {
    }

    public function onFailure(WorkerMessageFailedEvent $event): void
    {
        try {
            $transportInfos = $this->supervisor->getTransport($event->getReceiverName());
        } catch (TransportNotFoundException $e) {
            return;
        }

        $stop = $this->stopProcess($event, $transportInfos);
        $this->sendMail($event, $transportInfos, $stop);
    }

    /**
     * @param Transport $transportInfos
     */
    protected function stopProcess(WorkerMessageFailedEvent $event, array $transportInfos): bool
    {
        if (self::FAILURE_ACTION_NEVER === $transportInfos['failure']['stop_program']
            || (self::FAILURE_ACTION_WILL_NOT_RETRY === $transportInfos['failure']['stop_program']) && $event->willRetry()) {
            return false;
        }

        try {
            if ($this->logger) {
                $this->logger->info(\sprintf('Stopping %s program', $transportInfos['program']));
            }
            $this->supervisor->stopProgram($transportInfos['program'], false);
        } catch (\Throwable $throwable) {
            if ($this->logger) {
                $this->logger->error('Error during stopping program: '.$throwable->getMessage(), ['error' => $throwable->getMessage(), 'exception' => $throwable]);
            }

            return false;
        }

        return true;
    }

    /**
     * @param Transport $transportInfos
     */
    protected function sendMail(WorkerMessageFailedEvent $event, array $transportInfos, bool $stop): bool
    {
        if (null === $this->mailerParameters['from']) {
            return false;
        }
        if (self::FAILURE_ACTION_NEVER === $transportInfos['failure']['send_mail']
            || (self::FAILURE_ACTION_WILL_NOT_RETRY === $transportInfos['failure']['send_mail']) && $event->willRetry()) {
            return false;
        }

        try {
            if ($this->logger) {
                $this->logger->info('Sending email');
            }
            $body = $this->errorEmailBuilder->getBody($event, $transportInfos, $this->mailerParameters, $stop);
            $email = (new TemplatedEmail())
                ->from($this->mailerParameters['from'])
                ->to(...$this->mailerParameters['to'])
                ->subject($this->errorEmailBuilder->getSubject($event, $transportInfos, $this->mailerParameters, $stop))
                ->html($body)
            ;

            $this->mailer->send($email);
        } catch (\Throwable $throwable) {
            if ($this->logger) {
                $this->logger->error('Error during sending email: '.$throwable->getMessage(), ['error' => $throwable->getMessage(), 'exception' => $throwable]);
            }

            return false;
        }

        return true;
    }
}
