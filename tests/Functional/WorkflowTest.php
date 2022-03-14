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

use Doctrine\DBAL\Query\QueryBuilder;
use Ecommit\MessengerSupervisorBundle\Command\ManageCommand;
use Ecommit\MessengerSupervisorBundle\Tests\Functional\App\Messenger\Message\MessageError;
use Ecommit\MessengerSupervisorBundle\Tests\Functional\App\Messenger\Message\MessageSuccess;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

class WorkflowTest extends KernelTestCase
{
    protected $messageSuccessId;
    protected $messageErrorId;

    protected function setUp(): void
    {
        static::bootKernel();
    }

    public function testStatusBeforeStart(): void
    {
        self::getContainer()->get('doctrine')->getConnection()->query('DROP TABLE IF EXISTS messenger_messages');

        $command = $this->getCommandTester();
        $command->execute([
            'action' => 'status',
            'programs' => ['all'],
            '--nagios' => true,
        ]);

        $this->assertSame(2, $command->getStatusCode());
        $this->assertSame('CRITICAL - Running processes: 0 Stopped processes: 3'."\n", $command->getDisplay(true));
    }

    /**
     * @depends testStatusBeforeStart
     */
    public function testSendMessageSuccess(): void
    {
        $messageSuccess = new MessageSuccess();
        $bus = self::getContainer()->get(MessageBusInterface::class);
        $enveloppe = $bus->dispatch($messageSuccess);
        $this->messageSuccessId = $enveloppe->last(TransportMessageIdStamp::class)->getId();

        $this->checkCountMessages(1, 'async', $this->messageSuccessId, null);
    }

    /**
     * @depends testSendMessageSuccess
     */
    public function testStartProgramAync(): void
    {
        $command = $this->getCommandTester();
        $command->execute([
            'action' => 'start',
            'programs' => ['program_async'],
        ]);

        $this->assertSame(0, $command->getStatusCode());
    }

    /**
     * @depends testStartProgramAync
     */
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

    /**
     * @depends testStatusAfterStartProgramAync
     */
    public function testStopProgramAync(): void
    {
        $command = $this->getCommandTester();
        $command->execute([
            'action' => 'stop',
            'programs' => ['program_async'],
        ]);

        $this->assertSame(0, $command->getStatusCode());
    }

    /**
     * @depends testStopProgramAync
     */
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

    /**
     * @depends testStatusAfterStopProgramAync
     */
    public function testSendMessageError(): void
    {
        $messageError = new MessageError();
        $bus = self::getContainer()->get(MessageBusInterface::class);
        $enveloppe = $bus->dispatch($messageError);
        $this->messageErrorId = $enveloppe->last(TransportMessageIdStamp::class)->getId();

        $this->checkCountMessages(1, 'async', $this->messageErrorId, null);
        $this->checkCountMessages(0, 'email', null, null);
    }

    /**
     * @depends testSendMessageError
     */
    public function testStartAll(): void
    {
        $command = $this->getCommandTester();
        $command->execute([
            'action' => 'start',
            'programs' => ['all'],
        ]);

        $this->assertSame(0, $command->getStatusCode());
    }

    /**
     * @depends testStartAll
     */
    public function testFailAfterStartAll(): void
    {
        $this->checkCountMessages(0, 'async', $this->messageErrorId, 5);
        if (\PHP_VERSION_ID >= 70400) { // Bug: email messages fails when sending async with PHP < 7.4
            $this->checkCountMessages(1, 'email', null, 5);
        }
    }

    /**
     * @depends testFailAfterStartAll
     */
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

    /**
     * @depends testStatusAfterFail
     */
    public function testStopAll(): void
    {
        $command = $this->getCommandTester();
        $command->execute([
            'action' => 'stop',
            'programs' => ['all'],
        ]);

        $this->assertSame(0, $command->getStatusCode());
    }

    /**
     * @depends testStopAll
     */
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
        $application->add(self::getContainer()->get(ManageCommand::class));

        return new CommandTester($application->find('ecommit:supervisor'));
    }

    protected function checkCountMessages(int $expected, ?string $queue, ?string $id, ?int $timeout): void
    {
        $begin = time();

        while (true) {
            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = self::getContainer()->get('doctrine')->getConnection()->createQueryBuilder();
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
            $stmnt = $queryBuilder->execute();
            $count = (int) $stmnt->fetchOne();

            if ($count === $expected || null === $timeout || time() - $begin > $timeout) {
                break;
            }

            usleep(100000);
        }

        $this->assertSame($expected, $count);
    }
}
