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

namespace Ecommit\MessengerSupervisorBundle\Tests\Functional;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Connection;
use Ecommit\MessengerSupervisorBundle\Command\ManageCommand;
use Ecommit\MessengerSupervisorBundle\Tests\AbstractTestCase;
use Ecommit\MessengerSupervisorBundle\Tests\Functional\App\Messenger\Message\MessageError;
use Ecommit\MessengerSupervisorBundle\Tests\Functional\App\Messenger\Message\MessageSuccess;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

class WorkflowTest extends AbstractTestCase
{
    protected mixed $messageSuccessId = null;
    protected mixed $messageErrorId = null;

    protected function setUp(): void
    {
        static::bootKernel();
    }

    public function testStatusBeforeStart(): void
    {
        $this->getConnection()->executeQuery('DROP TABLE IF EXISTS messenger_messages');

        $command = $this->getCommandTester();
        $command->execute([
            'action' => 'status',
            'programs' => ['all'],
            '--nagios' => true,
        ]);

        $this->assertSame(2, $command->getStatusCode());
        $this->assertSame('CRITICAL - Running processes: 0 Stopped processes: 3'."\n", $command->getDisplay(true));
    }

    #[Depends('testStatusBeforeStart')]
    public function testSendMessageSuccess(): void
    {
        $messageSuccess = new MessageSuccess();
        $bus = self::getContainer()->get(MessageBusInterface::class);
        $enveloppe = $bus->dispatch($messageSuccess);
        $stamp = $enveloppe->last(TransportMessageIdStamp::class);
        $this->assertNotNull($stamp);
        $this->messageSuccessId = $stamp->getId();

        $this->checkCountMessages(1, 'async', $this->messageSuccessId, null);
    }

    #[Depends('testSendMessageSuccess')]
    public function testStartProgramAync(): void
    {
        $command = $this->getCommandTester();
        $command->execute([
            'action' => 'start',
            'programs' => ['program_async'],
        ]);

        $this->assertSame(0, $command->getStatusCode());
    }

    #[Depends('testStartProgramAync')]
    public function testStatusAfterStartProgramAync(): void
    {
        $command = $this->getCommandTester();
        $command->execute([
            'action' => 'status',
            'programs' => ['program_async'],
            '--nagios' => true,
        ]);

        $this->assertSame(0, $command->getStatusCode());
        $this->assertSame('OK - Running processes: 1 Stopped processes: 0'."\n", $command->getDisplay(true));
        $this->checkCountMessages(0, 'async', $this->messageSuccessId, 5);
    }

    #[Depends('testStatusAfterStartProgramAync')]
    public function testStopProgramAync(): void
    {
        $command = $this->getCommandTester();
        $command->execute([
            'action' => 'stop',
            'programs' => ['program_async'],
        ]);

        $this->assertSame(0, $command->getStatusCode());
    }

    #[Depends('testStopProgramAync')]
    public function testStatusAfterStopProgramAync(): void
    {
        $command = $this->getCommandTester();
        $command->execute([
            'action' => 'status',
            'programs' => ['program_async'],
            '--nagios' => true,
        ]);

        $this->assertSame(2, $command->getStatusCode());
        $this->assertSame('CRITICAL - Running processes: 0 Stopped processes: 1'."\n", $command->getDisplay(true));
    }

    #[Depends('testStatusAfterStopProgramAync')]
    public function testSendMessageError(): void
    {
        $messageError = new MessageError();
        $bus = self::getContainer()->get(MessageBusInterface::class);
        $enveloppe = $bus->dispatch($messageError);
        $stamp = $enveloppe->last(TransportMessageIdStamp::class);
        $this->assertNotNull($stamp);
        $this->messageErrorId = $stamp->getId();

        $this->checkCountMessages(1, 'async', $this->messageErrorId, null);
        $this->checkCountMessages(0, 'email', null, null);
    }

    #[Depends('testSendMessageError')]
    public function testStartAll(): void
    {
        $command = $this->getCommandTester();
        $command->execute([
            'action' => 'start',
            'programs' => ['all'],
        ]);

        $this->assertSame(0, $command->getStatusCode());
    }

    #[Depends('testStartAll')]
    public function testFailAfterStartAll(): void
    {
        $this->checkCountMessages(0, 'async', $this->messageErrorId, 5);
    }

    #[Depends('testFailAfterStartAll')]
    public function testStatusAfterFail(): void
    {
        $command = $this->getCommandTester();
        $command->execute([
            'action' => 'status',
            'programs' => ['all'],
            '--nagios' => true,
        ]);

        $this->assertSame(2, $command->getStatusCode());
        $this->assertSame('CRITICAL - Running processes: 2 Stopped processes: 1'."\n", $command->getDisplay(true));
    }

    #[Depends('testStatusAfterFail')]
    public function testStopAll(): void
    {
        $command = $this->getCommandTester();
        $command->execute([
            'action' => 'stop',
            'programs' => ['all'],
        ]);

        $this->assertSame(0, $command->getStatusCode());
    }

    #[Depends('testStopAll')]
    public function testStatusAfterStopAll(): void
    {
        $command = $this->getCommandTester();
        $command->execute([
            'action' => 'status',
            'programs' => ['all'],
            '--nagios' => true,
        ]);

        $this->assertSame(2, $command->getStatusCode());
        $this->assertSame('CRITICAL - Running processes: 0 Stopped processes: 3'."\n", $command->getDisplay(true));
    }

    protected function getCommandTester(): CommandTester
    {
        $application = new Application();
        if (method_exists($application, 'addCommand')) {
            $application->addCommand(self::getContainer()->get(ManageCommand::class));
        } else { // @legacy SF <= 7.4
            $application->add(self::getContainer()->get(ManageCommand::class));
        }

        return new CommandTester($application->find('ecommit:supervisor'));
    }

    protected function checkCountMessages(int $expected, ?string $queue, mixed $id, ?int $timeout): void
    {
        $begin = time();

        while (true) {
            $queryBuilder = $this->getConnection()->createQueryBuilder();
            $queryBuilder->from('messenger_messages')
                ->select('count(*)');
            if ($queue) {
                $queryBuilder->andWhere('messenger_messages.queue_name = :queue')
                    ->setParameter('queue', $queue);
            }
            if ($id) {
                $queryBuilder->andWhere('messenger_messages.id = :id')
                    ->setParameter('id', $id);
            }
            $stmnt = $queryBuilder->executeQuery();
            /** @var scalar $count */
            $count = $stmnt->fetchOne();
            $count = (int) $count;

            if ($count === $expected || null === $timeout || time() - $begin > $timeout) {
                break;
            }

            usleep(100000);
        }

        $this->assertSame($expected, $count);
    }

    protected function getConnection(): Connection
    {
        /** @var Registry $doctrine */
        $doctrine = self::getContainer()->get('doctrine');
        /** @var Connection $connection */
        $connection = $doctrine->getConnection();

        return $connection;
    }
}
