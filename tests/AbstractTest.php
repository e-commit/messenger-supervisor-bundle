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

use Supervisor\Process;
use Supervisor\Supervisor as SupervisorApi;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class AbstractTest extends KernelTestCase
{
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
                    'state' => Process::RUNNING,
                    'statename' => 'Running',
                    'pid' => '0011',
                ],
                'program1_2' => [
                    'group' => 'program1',
                    'name' => 'program1_2',
                    'state' => Process::RUNNING,
                    'statename' => 'Running',
                    'pid' => '0012',
                ],
                'program2_1' => [
                    'group' => 'program2',
                    'name' => 'program2_1',
                    'state' => Process::STOPPED,
                    'statename' => 'Stopped',
                    'pid' => '0',
                ],
            ]);

        return $supervisorApi;
    }

    protected function createSupervisorApiMockerStart(array $programs, bool $wait): SupervisorApi
    {
        $supervisorApi = $this->getMockBuilder(SupervisorApi::class)
            ->disableOriginalConstructor()
            ->addMethods(['startProcessGroup'])
            ->getMock();
        $consecutives = [];
        foreach ($programs as $program) {
            $consecutives[] = [$program, $wait];
        }
        $supervisorApi->expects($this->exactly(\count($programs)))
            ->method('startProcessGroup')
            ->withConsecutive(...$consecutives)
            ->willReturn([]);

        return $supervisorApi;
    }

    protected function createSupervisorApiMockerStop(array $programs, bool $wait): SupervisorApi
    {
        $supervisorApi = $this->getMockBuilder(SupervisorApi::class)
            ->disableOriginalConstructor()
            ->addMethods(['stopProcessGroup'])
            ->getMock();
        $consecutives = [];
        foreach ($programs as $program) {
            $consecutives[] = [$program, $wait];
        }
        $supervisorApi->expects($this->exactly(\count($programs)))
            ->method('stopProcessGroup')
            ->withConsecutive(...$consecutives)
            ->willReturn([]);

        return $supervisorApi;
    }
}
