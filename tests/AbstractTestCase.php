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

namespace Ecommit\MessengerSupervisorBundle\Tests;

use Ecommit\MessengerSupervisorBundle\DependencyInjection\Configuration;
use Supervisor\Supervisor as SupervisorApi;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @phpstan-import-type Transports from Configuration
 *
 * @phpstan-type ProcessInfo array{group: string, name: string, state: int, statename: string, pid: string}
 */
abstract class AbstractTestCase extends KernelTestCase
{
    /**
     * @return Transports
     */
    protected function getTransports(): array
    {
        return [
            'transport1' => [
                'program' => 'program1',
                'failure' => [
                    'stop_program' => 'always',
                    'send_mail' => 'always',
                ],
            ],
            'transport2' => [
                'program' => 'program2',
                'failure' => [
                    'stop_program' => 'always',
                    'send_mail' => 'always',
                ],
            ],
            'transport3' => [
                'program' => 'program1',
                'failure' => [
                    'stop_program' => 'always',
                    'send_mail' => 'always',
                ],
            ],
            'transport4' => [
                'program' => 'program4',
                'failure' => [
                    'stop_program' => 'always',
                    'send_mail' => 'always',
                ],
            ],
        ];
    }

    protected function createSupervisorApiMockerStatus(): SupervisorApi
    {
        $supervisorApi = $this->getMockBuilder(SupervisorApi::class)
            ->disableOriginalConstructor()
            ->addMethods(['getAllProcessInfo'])
            ->getMock();
        $supervisorApi->expects($this->once())
            ->method('getAllProcessInfo')
            ->willReturn([
                'program1_1' => [
                    'group' => 'program1',
                    'name' => 'program1_1',
                    'state' => $this->getProcessRunningState(),
                    'statename' => 'Running',
                    'pid' => '0011',
                ],
                'program1_2' => [
                    'group' => 'program1',
                    'name' => 'program1_2',
                    'state' => $this->getProcessRunningState(),
                    'statename' => 'Running',
                    'pid' => '0012',
                ],
                'program2_1' => [
                    'group' => 'program2',
                    'name' => 'program2_1',
                    'state' => $this->getProcessStoppedState(),
                    'statename' => 'Stopped',
                    'pid' => '0',
                ],
            ]);

        return $supervisorApi;
    }

    /**
     * @param array<string>           $programs    Programs to start, in call order
     * @param list<list<ProcessInfo>> $pollResults getAllProcessInfo return value for each consecutive poll
     */
    protected function createSupervisorApiMockerStart(array $programs, array $pollResults): SupervisorApi
    {
        $supervisorApi = $this->getMockBuilder(SupervisorApi::class)
            ->disableOriginalConstructor()
            ->addMethods(['startProcessGroup', 'getAllProcessInfo'])
            ->getMock();

        $startMatcher = $this->exactly(\count($programs));
        $supervisorApi->expects($startMatcher)
            ->method('startProcessGroup')
            ->willReturnCallback(function (string $program, bool $wait) use ($startMatcher, $programs): array {
                $this->assertSame($programs[$startMatcher->numberOfInvocations() - 1], $program);
                $this->assertFalse($wait);

                return [];
            });

        $pollMatcher = $this->exactly(\count($pollResults));
        $supervisorApi->expects($pollMatcher)
            ->method('getAllProcessInfo')
            ->willReturnCallback(static fn (): array => $pollResults[$pollMatcher->numberOfInvocations() - 1]);

        return $supervisorApi;
    }

    /**
     * @param array<string>           $programs    Programs to stop, in call order
     * @param list<list<ProcessInfo>> $pollResults getAllProcessInfo return value for each consecutive poll
     */
    protected function createSupervisorApiMockerStop(array $programs, array $pollResults): SupervisorApi
    {
        $supervisorApi = $this->getMockBuilder(SupervisorApi::class)
            ->disableOriginalConstructor()
            ->addMethods(['stopProcessGroup', 'getAllProcessInfo'])
            ->getMock();

        $stopMatcher = $this->exactly(\count($programs));
        $supervisorApi->expects($stopMatcher)
            ->method('stopProcessGroup')
            ->willReturnCallback(function (string $program, bool $wait) use ($stopMatcher, $programs): array {
                $this->assertSame($programs[$stopMatcher->numberOfInvocations() - 1], $program);
                $this->assertFalse($wait);

                return [];
            });

        $pollMatcher = $this->exactly(\count($pollResults));
        $supervisorApi->expects($pollMatcher)
            ->method('getAllProcessInfo')
            ->willReturnCallback(static fn (): array => $pollResults[$pollMatcher->numberOfInvocations() - 1]);

        return $supervisorApi;
    }

    /**
     * @return ProcessInfo
     */
    protected function buildProcessInfo(string $group, string $name, int $state, string $stateName, string $pid = '0'): array
    {
        return [
            'group' => $group,
            'name' => $name,
            'state' => $state,
            'statename' => $stateName,
            'pid' => $pid,
        ];
    }

    protected function getProcessRunningState(): int
    {
        return 20;
    }

    protected function getProcessStoppedState(): int
    {
        return 0;
    }
}
