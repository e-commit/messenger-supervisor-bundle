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

namespace Ecommit\MessengerSupervisorBundle\Tests\EventListener;

use Ecommit\MessengerSupervisorBundle\EventListener\WorkerMessageFailedEventListener;
use Ecommit\MessengerSupervisorBundle\Mailer\ErrorEmailBuilder;
use Ecommit\MessengerSupervisorBundle\Supervisor\Supervisor;
use Ecommit\MessengerSupervisorBundle\Tests\AbstractTest;
use Ecommit\MessengerSupervisorBundle\Tests\Functional\App\Messenger\Message\MessageSuccess;
use Psr\Log\LoggerInterface;
use Supervisor\Supervisor as SupervisorApi;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

class WorkerMessageFailedEventListenerTest extends AbstractTest
{
    /**
     * @dataProvider getTestOnFailureProvider
     */
    public function testOnFailure(array $failure, bool $willRetry, bool $expectedStopProgram, bool $expectedMail): void
    {
        self::bootKernel();

        $message = new MessageSuccess();
        $event = new WorkerMessageFailedEvent(
            new Envelope($message),
            'transport1',
            new \Exception('My error')
        );
        if ($willRetry) {
            $event->setForRetry();
        }

        $supervisorApi = $this->getMockBuilder(SupervisorApi::class)
            ->disableOriginalConstructor()
            ->addMethods(['stopProcessGroup'])
            ->getMock();
        if ($expectedStopProgram) {
            $supervisorApi->expects($this->once())
                ->method('stopProcessGroup')
                ->with('program1', false)
                ->willReturn([]);
        } else {
            $supervisorApi->expects($this->never())
                ->method('stopProcessGroup');
        }

        $supervisor = new Supervisor($supervisorApi, [
            'transport1' => [
                'program' => 'program1',
                'failure' => $failure,
            ],
        ]);

        $errorEmailBuilder = self::getContainer()->get(ErrorEmailBuilder::class);

        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $consecutives = [];
        if ($expectedStopProgram) {
            $consecutives[] = ['Stopping program1 program'];
        }
        if ($expectedMail) {
            $consecutives[] = ['Sending email'];
        }
        $logger->expects($this->exactly(\count($consecutives)))
            ->method('info')
            ->withConsecutive(...$consecutives);

        $mailer = $this->getMockBuilder(MailerInterface::class)->getMock();
        if ($expectedMail) {
            $mailer->expects($this->once())
                ->method('send')
                ->with($this->callback(function (TemplatedEmail $email) use ($expectedStopProgram, $message) {
                    $this->assertCount(1, $email->getFrom());
                    $this->assertSame('from@localhost', $email->getFrom()[0]->getAddress());

                    $this->assertCount(1, $email->getTo());
                    $this->assertSame('to@localhost', $email->getTo()[0]->getAddress());

                    $this->assertSame('[Supervisor][program1] Error', $email->getSubject());

                    $body = $email->getHtmlBody();

                    $this->assertStringContainsString('Error during execution of "program1" Supervisor program.', $body);
                    $this->assertStringContainsString('<li><b>Transport: </b>transport1</li>', $body);
                    if ($expectedStopProgram) {
                        $this->assertStringContainsString('<li><b>Stop program: </b>Yes</li>', $body);
                    } else {
                        $this->assertStringContainsString('<li><b>Stop program: </b>No</li>', $body);
                    }
                    $this->assertStringContainsString(json_encode($message), html_entity_decode($body));
                    $this->assertMatchesRegularExpression('/Exception: My error at .+WorkerMessageFailedEventListenerTest\.php line \d+/', html_entity_decode($body));

                    return true;
                }), $this->anything());
        } else {
            $mailer->expects($this->never())
                ->method('send');
        }

        $listener = new WorkerMessageFailedEventListener($supervisor, $errorEmailBuilder, $logger, $mailer, [
            'from' => 'from@localhost',
            'to' => ['to@localhost'],
            'subject' => '[Supervisor][<program>] Error',
        ]);

        $listener->onFailure($event);
    }

    public function getTestOnFailureProvider(): array
    {
        return [
            [['stop_program' => 'always', 'send_mail' => 'always'], true, true, true],
            [['stop_program' => 'will-not-retry', 'send_mail' => 'always'], true, false, true],
            [['stop_program' => 'always', 'send_mail' => 'will-not-retry'], true, true, false],
            [['stop_program' => 'never', 'send_mail' => 'always'], true, false, true],
            [['stop_program' => 'always', 'send_mail' => 'never'], true, true, false],
            [['stop_program' => 'never', 'send_mail' => 'never'], true, false, false],

            [['stop_program' => 'always', 'send_mail' => 'always'], false, true, true],
            [['stop_program' => 'will-not-retry', 'send_mail' => 'always'], false, true, true],
            [['stop_program' => 'always', 'send_mail' => 'will-not-retry'], false, true, true],
            [['stop_program' => 'never', 'send_mail' => 'always'], false, false, true],
            [['stop_program' => 'always', 'send_mail' => 'never'], false, true, false],
            [['stop_program' => 'never', 'send_mail' => 'never'], false, false, false],
        ];
    }
}
