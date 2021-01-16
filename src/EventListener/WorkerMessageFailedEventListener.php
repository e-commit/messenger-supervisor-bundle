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
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var MailerInterface
     */
    protected $mailer;

    protected $mailerParameters;

    public function __construct(Supervisor $supervisor, ?LoggerInterface $logger, MailerInterface $mailer, array $mailerParameters)
    {
        $this->supervisor = $supervisor;
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
        if (self::FAILURE_ACTION_NEVER === $transportInfos['failure']['stop_program'] ||
            (self::FAILURE_ACTION_WILL_NOT_RETRY === $transportInfos['failure']['stop_program']) && $event->willRetry()) {
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
        if (self::FAILURE_ACTION_NEVER === $transportInfos['failure']['send_mail'] ||
            (self::FAILURE_ACTION_WILL_NOT_RETRY === $transportInfos['failure']['send_mail']) && $event->willRetry()) {
            return false;
        }

        try {
            if ($this->logger) {
                $this->logger->info('Sending email');
            }
            $email = (new TemplatedEmail())
                ->from($this->mailerParameters['from'])
                ->to(...$this->mailerParameters['to'])
                ->subject($this->getSubjectEmail($event))
                ->htmlTemplate('@EcommitMessengerSupervisor/Email/failure.html.twig')
                ->context(array_merge([
                    'event' => $event,
                    'program' => $transportInfos['program'],
                    'transport_infos' => $transportInfos,
                    'stop_program' => $stop,
                    'throwable_message' => $this->getThrowableMessage($event->getThrowable()),
                ], $this->getContextEmail($event)))
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

    protected function getContextEmail(WorkerMessageFailedEvent $event): array
    {
        return [];
    }

    protected function getSubjectEmail(WorkerMessageFailedEvent $event): string
    {
        $program = $this->supervisor->getTransport($event->getReceiverName())['program'];

        $subject = $this->mailerParameters['subject'];
        $subject = str_replace('<program>', $program, $subject);

        return $subject;
    }

    protected function getThrowableMessage(\Throwable $throwable): string
    {
        return sprintf('%s: %s at %s line %s', \get_class($throwable), $throwable->getMessage(), $throwable->getFile(), $throwable->getLine());
    }
}
