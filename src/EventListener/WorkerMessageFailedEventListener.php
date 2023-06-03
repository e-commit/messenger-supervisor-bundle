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

use Ecommit\MessengerSupervisorBundle\Exception\TransportNotFoundException;
use Ecommit\MessengerSupervisorBundle\Mailer\ErrorEmailBuilderInterface;
use Ecommit\MessengerSupervisorBundle\Supervisor\Supervisor;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

class WorkerMessageFailedEventListener
{
    public const FAILURE_ACTION_ALWAYS = 'always';
    public const FAILURE_ACTION_WILL_NOT_RETRY = 'will-not-retry';
    public const FAILURE_ACTION_NEVER = 'never';

    /**
     * @var Supervisor
     */
    protected $supervisor;

    /**
     * @var ErrorEmailBuilderInterface
     */
    protected $errorEmailBuilder;

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * @var MailerInterface
     */
    protected $mailer;

    protected $mailerParameters;

    public function __construct(Supervisor $supervisor, ErrorEmailBuilderInterface $errorEmailBuilder, ?LoggerInterface $logger, MailerInterface $mailer, array $mailerParameters)
    {
        $this->supervisor = $supervisor;
        $this->errorEmailBuilder = $errorEmailBuilder;
        $this->logger = $logger;
        $this->mailer = $mailer;
        $this->mailerParameters = $mailerParameters;
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

    protected function stopProcess(WorkerMessageFailedEvent $event, array $transportInfos): bool
    {
        if (self::FAILURE_ACTION_NEVER === $transportInfos['failure']['stop_program']
            || (self::FAILURE_ACTION_WILL_NOT_RETRY === $transportInfos['failure']['stop_program']) && $event->willRetry()) {
            return false;
        }

        try {
            if ($this->logger) {
                $this->logger->info(sprintf('Stopping %s program', $transportInfos['program']));
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

    protected function sendMail(WorkerMessageFailedEvent $event, array $transportInfos, bool $stop): bool
    {
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
