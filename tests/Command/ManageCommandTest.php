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
use Ecommit\MessengerSupervisorBundle\Supervisor\Supervisor;
use Ecommit\MessengerSupervisorBundle\Tests\AbstractTest;
use Supervisor\Supervisor as SupervisorApi;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ManageCommandTest extends AbstractTest
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
        $supervisorApi = $this->createSupervisorApiMockerStart(['program1', 'program2', 'program4'], true);
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'start',
            'programs' => ['all'],
        ]);

        $this->assertSame(0, $exitCode);

        $expected = [
            'Starting program1 program',
            'program1 program is started',
            'Starting program2 program',
            'program2 program is started',
            'Starting program4 program',
            'program4 program is started',
        ];
        $this->assertSame(implode("\n", $expected)."\n", $commandTester->getDisplay(true));
    }

    public function testStart(): void
    {
        $supervisorApi = $this->createSupervisorApiMockerStart(['program4', 'program2'], true);
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'start',
            'programs' => ['program4', 'program2'],
        ]);

        $this->assertSame(0, $exitCode);

        $expected = [
            'Starting program4 program',
            'program4 program is started',
            'Starting program2 program',
            'program2 program is started',
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
        $supervisorApi = $this->createSupervisorApiMockerStop(['program1', 'program2', 'program4'], true);
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'stop',
            'programs' => ['all'],
        ]);

        $this->assertSame(0, $exitCode);

        $expected = [
            'Stopping program1 program',
            'program1 program is stopped',
            'Stopping program2 program',
            'program2 program is stopped',
            'Stopping program4 program',
            'program4 program is stopped',
        ];
        $this->assertSame(implode("\n", $expected)."\n", $commandTester->getDisplay(true));
    }

    public function testStop(): void
    {
        $supervisorApi = $this->createSupervisorApiMockerStop(['program4', 'program2'], true);
        $commandTester = $this->createCommandTester($supervisorApi);
        $exitCode = $commandTester->execute([
            'action' => 'stop',
            'programs' => ['program4', 'program2'],
        ]);

        $this->assertSame(0, $exitCode);

        $expected = [
            'Stopping program4 program',
            'program4 program is stopped',
            'Stopping program2 program',
            'program2 program is stopped',
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

    protected function createCommandTester(SupervisorApi $supervisorApi, ?array $transports = null): CommandTester
    {
        if (null === $transports) {
            $transports = $this->getTransports();
        }
        $application = new Application();

        $supervisor = new Supervisor($supervisorApi, $transports);
        $command = new ManageCommand($supervisor);

        $application->add($command);

        return new CommandTester($application->find('ecommit:supervisor'));
    }
}
