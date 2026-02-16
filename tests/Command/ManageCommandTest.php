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

namespace Ecommit\MessengerSupervisorBundle\Tests\Command;

use Ecommit\MessengerSupervisorBundle\Command\ManageCommand;
use Ecommit\MessengerSupervisorBundle\DependencyInjection\Configuration;
use Ecommit\MessengerSupervisorBundle\Supervisor\Supervisor;
use Ecommit\MessengerSupervisorBundle\Tests\AbstractTestCase;
use Ecommit\MessengerSupervisorBundle\Tests\SupervisorApi;
use Supervisor\ProcessStates;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @phpstan-import-type Transports from Configuration
 */
class ManageCommandTest extends AbstractTestCase
{
    public function testStatusAll(): void
    {
        $supervisorApi = $this->createSupervisorApiMockerStatus();
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'status',
            'programs' => ['all'],
        ]);

        $this->assertSame(1, $exitCode);
        $expected = [
            '/^',
            '[\-\s]+',
            'Program\s+program1\s+',
            'Transport\(s\)\s+transport1, transport3\s+',
            'Process\s+program1_1\s+',
            'State\s+Running\s+',
            'PID\s+0011\s+',

            '[\-\s]+',
            'Program\s+program1\s+',
            'Transport\(s\)\s+transport1, transport3\s+',
            'Process\s+program1_2\s+',
            'State\s+Running\s+',
            'PID\s+0012\s+',

            '[\-\s]+',
            'Program\s+program2\s+',
            'Transport\(s\)\s+transport2\s+',
            'Process\s+program2_1\s+',
            'State\s+Stopped\s+',
            'PID\s+0\s+',

            '[\-\s]+',
            'Program\s+program4\s+',
            'Transport\(s\)\s+transport4\s+',
            '\s+Not found in Supervisor\s+',
            '[\-\s]+',
            '$/',
        ];
        $this->assertMatchesRegularExpression(implode('', $expected), $commandTester->getDisplay(true));
    }

    public function testStatusAllNagios(): void
    {
        $supervisorApi = $this->createSupervisorApiMockerStatus();
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'status',
            'programs' => ['all'],
            '--nagios' => true,
        ]);

        $this->assertSame(2, $exitCode);
        $this->assertSame('CRITICAL - Running processes: 2 Stopped processes: 2'."\n", $commandTester->getDisplay(true));
    }

    public function testStatus(): void
    {
        $supervisorApi = $this->createSupervisorApiMockerStatus();
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'status',
            'programs' => ['program4', 'program2'],
        ]);

        $this->assertSame(1, $exitCode);
        $expected = [
            '/^',
            '[\-\s]+',
            'Program\s+program4\s+',
            'Transport\(s\)\s+transport4\s+',
            '\s+Not found in Supervisor\s+',

            '[\-\s]+',
            'Program\s+program2\s+',
            'Transport\(s\)\s+transport2\s+',
            'Process\s+program2_1\s+',
            'State\s+Stopped\s+',
            'PID\s+0\s+',
            '[\-\s]+',
            '$/',
        ];
        $this->assertMatchesRegularExpression(implode('', $expected), $commandTester->getDisplay(true));
    }

    public function testStatusNagios(): void
    {
        $supervisorApi = $this->createSupervisorApiMockerStatus();
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'status',
            'programs' => ['program4', 'program2'],
            '--nagios' => true,
        ]);

        $this->assertSame(2, $exitCode);
        $this->assertSame('CRITICAL - Running processes: 0 Stopped processes: 2'."\n", $commandTester->getDisplay(true));
    }

    public function testStatusSuccess(): void
    {
        $supervisorApi = $this->createSupervisorApiMockerStatus();
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'status',
            'programs' => ['program1'],
        ]);

        $this->assertSame(0, $exitCode);
        $expected = [
            '/^',
            '[\-\s]+',
            'Program\s+program1\s+',
            'Transport\(s\)\s+transport1, transport3\s+',
            'Process\s+program1_1\s+',
            'State\s+Running\s+',
            'PID\s+0011\s+',

            '[\-\s]+',
            'Program\s+program1\s+',
            'Transport\(s\)\s+transport1, transport3\s+',
            'Process\s+program1_2\s+',
            'State\s+Running\s+',
            'PID\s+0012\s+',
            '[\-\s]+',
            '$/',
        ];
        $this->assertMatchesRegularExpression(implode('', $expected), $commandTester->getDisplay(true));
    }

    public function testStatusSuccessNagios(): void
    {
        $supervisorApi = $this->createSupervisorApiMockerStatus();
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'status',
            'programs' => ['program1'],
            '--nagios' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame('OK - Running processes: 2 Stopped processes: 0'."\n", $commandTester->getDisplay(true));
    }

    public function testStatusBadProgram(): void
    {
        $supervisorApi = $this->createMock(SupervisorApi::class);
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'status',
            'programs' => ['bad'],
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertSame('Bad program "bad" (Available: program1, program2, program4, all)'."\n", $commandTester->getDisplay(true));
    }

    public function testStartAll(): void
    {
        $poll = [
            $this->buildProcessInfo('program1', 'program1_1', ProcessStates::Running->value, 'RUNNING', '100'),
            $this->buildProcessInfo('program1', 'program1_2', ProcessStates::Running->value, 'RUNNING', '101'),
            $this->buildProcessInfo('program2', 'program2_1', ProcessStates::Running->value, 'RUNNING', '102'),
            $this->buildProcessInfo('program4', 'program4_1', ProcessStates::Running->value, 'RUNNING', '103'),
        ];

        $supervisorApi = $this->createSupervisorApiMockerStart(['program1', 'program2', 'program4'], [$poll]);
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'start',
            'programs' => ['all'],
        ]);

        $this->assertSame(0, $exitCode);

        $expected = [
            'Starting program1...',
            'Starting program2...',
            'Starting program4...',
            '✓ program1 started',
            '✓ program2 started',
            '✓ program4 started',
        ];
        $this->assertSame(implode("\n", $expected)."\n", $commandTester->getDisplay(true));
    }

    public function testStart(): void
    {
        $poll = [
            $this->buildProcessInfo('program4', 'program4_1', ProcessStates::Running->value, 'RUNNING', '103'),
            $this->buildProcessInfo('program2', 'program2_1', ProcessStates::Running->value, 'RUNNING', '102'),
        ];

        $supervisorApi = $this->createSupervisorApiMockerStart(['program4', 'program2'], [$poll]);
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'start',
            'programs' => ['program4', 'program2'],
        ]);

        $this->assertSame(0, $exitCode);

        $expected = [
            'Starting program4...',
            'Starting program2...',
            '✓ program4 started',
            '✓ program2 started',
        ];
        $this->assertSame(implode("\n", $expected)."\n", $commandTester->getDisplay(true));
    }

    public function testStartWithTransition(): void
    {
        $poll1 = [
            $this->buildProcessInfo('program2', 'program2_1', ProcessStates::Starting->value, 'STARTING'),
        ];
        $poll2 = [
            $this->buildProcessInfo('program2', 'program2_1', ProcessStates::Running->value, 'RUNNING', '102'),
        ];

        $supervisorApi = $this->createSupervisorApiMockerStart(['program2'], [$poll1, $poll2]);
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'start',
            'programs' => ['program2'],
        ]);

        $this->assertSame(0, $exitCode);

        $expected = [
            'Starting program2...',
            '✓ program2 started',
        ];
        $this->assertSame(implode("\n", $expected)."\n", $commandTester->getDisplay(true));
    }

    public function testStartWithDifferentPollResolution(): void
    {
        $poll1 = [
            $this->buildProcessInfo('program1', 'program1_1', ProcessStates::Running->value, 'RUNNING', '100'),
            $this->buildProcessInfo('program2', 'program2_1', ProcessStates::Starting->value, 'STARTING'),
        ];
        $poll2 = [
            $this->buildProcessInfo('program2', 'program2_1', ProcessStates::Running->value, 'RUNNING', '102'),
        ];

        $supervisorApi = $this->createSupervisorApiMockerStart(['program1', 'program2'], [$poll1, $poll2]);
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'start',
            'programs' => ['program1', 'program2'],
        ]);

        $this->assertSame(0, $exitCode);

        $expected = [
            'Starting program1...',
            'Starting program2...',
            '✓ program1 started',
            '✓ program2 started',
        ];
        $this->assertSame(implode("\n", $expected)."\n", $commandTester->getDisplay(true));
    }

    public function testStartFailure(): void
    {
        $poll1 = [
            $this->buildProcessInfo('program2', 'program2_1', ProcessStates::Starting->value, 'STARTING'),
        ];
        $poll2 = [
            $this->buildProcessInfo('program2', 'program2_1', ProcessStates::Backoff->value, 'BACKOFF'),
        ];
        $poll3 = [
            $this->buildProcessInfo('program2', 'program2_1', ProcessStates::Fatal->value, 'FATAL'),
        ];

        $supervisorApi = $this->createSupervisorApiMockerStart(['program2'], [$poll1, $poll2, $poll3]);
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'start',
            'programs' => ['program2'],
        ]);

        $this->assertSame(1, $exitCode);

        $expected = [
            'Starting program2...',
            '✗ program2 failed to start',
        ];
        $this->assertSame(implode("\n", $expected)."\n", $commandTester->getDisplay(true));
    }

    public function testStartStoppedByExternalMeans(): void
    {
        // Program starts then gets stopped externally (e.g. by the event listener after processing an error)
        // before our first poll — STOPPED must be treated as terminal success for start.
        $poll = [
            $this->buildProcessInfo('program2', 'program2_1', ProcessStates::Stopped->value, 'STOPPED'),
        ];

        $supervisorApi = $this->createSupervisorApiMockerStart(['program2'], [$poll]);
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'start',
            'programs' => ['program2'],
        ]);

        $this->assertSame(0, $exitCode);

        $expected = [
            'Starting program2...',
            '✓ program2 started',
        ];
        $this->assertSame(implode("\n", $expected)."\n", $commandTester->getDisplay(true));
    }

    public function testStartBadProgram(): void
    {
        $supervisorApi = $this->createMock(SupervisorApi::class);
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'start',
            'programs' => ['bad'],
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertSame('Bad program "bad" (Available: program1, program2, program4, all)'."\n", $commandTester->getDisplay(true));
    }

    public function testStopAll(): void
    {
        $poll = [
            $this->buildProcessInfo('program1', 'program1_1', ProcessStates::Stopped->value, 'STOPPED'),
            $this->buildProcessInfo('program1', 'program1_2', ProcessStates::Stopped->value, 'STOPPED'),
            $this->buildProcessInfo('program2', 'program2_1', ProcessStates::Stopped->value, 'STOPPED'),
            $this->buildProcessInfo('program4', 'program4_1', ProcessStates::Stopped->value, 'STOPPED'),
        ];

        $supervisorApi = $this->createSupervisorApiMockerStop(['program1', 'program2', 'program4'], [$poll]);
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'stop',
            'programs' => ['all'],
        ]);

        $this->assertSame(0, $exitCode);

        $expected = [
            'Stopping program1...',
            'Stopping program2...',
            'Stopping program4...',
            '✓ program1 stopped',
            '✓ program2 stopped',
            '✓ program4 stopped',
        ];
        $this->assertSame(implode("\n", $expected)."\n", $commandTester->getDisplay(true));
    }

    public function testStop(): void
    {
        $poll = [
            $this->buildProcessInfo('program4', 'program4_1', ProcessStates::Stopped->value, 'STOPPED'),
            $this->buildProcessInfo('program2', 'program2_1', ProcessStates::Stopped->value, 'STOPPED'),
        ];

        $supervisorApi = $this->createSupervisorApiMockerStop(['program4', 'program2'], [$poll]);
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'stop',
            'programs' => ['program4', 'program2'],
        ]);

        $this->assertSame(0, $exitCode);

        $expected = [
            'Stopping program4...',
            'Stopping program2...',
            '✓ program4 stopped',
            '✓ program2 stopped',
        ];
        $this->assertSame(implode("\n", $expected)."\n", $commandTester->getDisplay(true));
    }

    public function testStopWithTransition(): void
    {
        $poll1 = [
            $this->buildProcessInfo('program2', 'program2_1', ProcessStates::Stopping->value, 'STOPPING'),
        ];
        $poll2 = [
            $this->buildProcessInfo('program2', 'program2_1', ProcessStates::Stopped->value, 'STOPPED'),
        ];

        $supervisorApi = $this->createSupervisorApiMockerStop(['program2'], [$poll1, $poll2]);
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'stop',
            'programs' => ['program2'],
        ]);

        $this->assertSame(0, $exitCode);

        $expected = [
            'Stopping program2...',
            '✓ program2 stopped',
        ];
        $this->assertSame(implode("\n", $expected)."\n", $commandTester->getDisplay(true));
    }

    public function testStopWithDifferentPollResolution(): void
    {
        $poll1 = [
            $this->buildProcessInfo('program1', 'program1_1', ProcessStates::Stopped->value, 'STOPPED'),
            $this->buildProcessInfo('program2', 'program2_1', ProcessStates::Stopping->value, 'STOPPING'),
        ];
        $poll2 = [
            $this->buildProcessInfo('program2', 'program2_1', ProcessStates::Stopped->value, 'STOPPED'),
        ];

        $supervisorApi = $this->createSupervisorApiMockerStop(['program1', 'program2'], [$poll1, $poll2]);
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'stop',
            'programs' => ['program1', 'program2'],
        ]);

        $this->assertSame(0, $exitCode);

        $expected = [
            'Stopping program1...',
            'Stopping program2...',
            '✓ program1 stopped',
            '✓ program2 stopped',
        ];
        $this->assertSame(implode("\n", $expected)."\n", $commandTester->getDisplay(true));
    }

    public function testStopBadProgram(): void
    {
        $supervisorApi = $this->createMock(SupervisorApi::class);
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'stop',
            'programs' => ['bad'],
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertSame('Bad program "bad" (Available: program1, program2, program4, all)'."\n", $commandTester->getDisplay(true));
    }

    public function testBadAction(): void
    {
        $supervisorApi = $this->createMock(SupervisorApi::class);
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'bad',
            'programs' => ['program1'],
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertSame('Bad action "bad" (Available: start, stop, status)'."\n", $commandTester->getDisplay(true));
    }

    public function testNagiosOptionWithStart(): void
    {
        $supervisorApi = $this->createMock(SupervisorApi::class);
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'start',
            'programs' => ['program1'],
            '--nagios' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertSame('Nagios option can only be used with the "status" action'."\n", $commandTester->getDisplay(true));
    }

    public function testNagiosOptionWithStop(): void
    {
        $supervisorApi = $this->createMock(SupervisorApi::class);
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'stop',
            'programs' => ['program1'],
            '--nagios' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertSame('Nagios option can only be used with the "status" action'."\n", $commandTester->getDisplay(true));
    }

    /**
     * @param ?Transports $transports
     */
    protected function createCommandTester(SupervisorApi $supervisorApi, ?array $transports = null): CommandTester
    {
        if (null === $transports) {
            $transports = $this->getTransports();
        }
        $application = new Application();

        $supervisor = new Supervisor($supervisorApi, $transports);
        $command = new ManageCommand($supervisor);

        if (method_exists($application, 'addCommand')) {
            $application->addCommand($command);
        } else { // @legacy SF <= 7.4
            $application->add($command);
        }

        return new CommandTester($application->find('ecommit:supervisor'));
    }
}
