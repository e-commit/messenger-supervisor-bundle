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

namespace Ecommit\MessengerSupervisorBundle\Tests\Supervisor;

use Ecommit\MessengerSupervisorBundle\Exception\TransportNotFoundException;
use Ecommit\MessengerSupervisorBundle\Supervisor\Supervisor;
use Ecommit\MessengerSupervisorBundle\Tests\AbstractTestCase;
use Supervisor\Process;
use Supervisor\Supervisor as SupervisorApi;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class SupervisorTest extends AbstractTestCase
{
    public function testServiceClass(): void
    {
        self::bootKernel();

        $service = self::getContainer()->get('ecommit_messenger_supervisor.supervisor');
        $this->assertInstanceOf(Supervisor::class, $service);
    }

    public function testAliasServiceClass(): void
    {
        self::bootKernel();

        $service = self::getContainer()->get(Supervisor::class);
        $this->assertInstanceOf(Supervisor::class, $service);
    }

    public function testServiceIsNotPublic(): void
    {
        self::bootKernel();

        $this->expectException(ServiceNotFoundException::class);
        self::$kernel->getContainer()->get('ecommit_messenger_supervisor.supervisor');
    }

    public function testAliasServiceIsNotPublic(): void
    {
        self::bootKernel();

        $this->expectException(ServiceNotFoundException::class);
        self::$kernel->getContainer()->get(Supervisor::class);
    }

    public function testGetApi(): void
    {
        $supervisor = new Supervisor($this->createMock(SupervisorApi::class), []);
        $this->assertInstanceOf(SupervisorApi::class, $supervisor->getApi());
    }

    public function testGetTransports(): Supervisor
    {
        $supervisor = new Supervisor($this->createMock(SupervisorApi::class), $this->getTransports());
        $this->assertSame($this->getTransports(), $supervisor->getTransports());

        return $supervisor;
    }

    /**
     * @depends testGetTransports
     */
    public function testGetTransport(Supervisor $supervisor): Supervisor
    {
        $this->assertSame($this->getTransports()['transport1'], $supervisor->getTransport('transport1'));

        return $supervisor;
    }

    /**
     * @depends testGetTransports
     */
    public function testGetTransportNotFound(Supervisor $supervisor): Supervisor
    {
        $this->expectException(TransportNotFoundException::class);
        $this->expectExceptionMessage('Transport not found: bad');

        $supervisor->getTransport('bad');

        return $supervisor;
    }

    /**
     * @depends testGetTransports
     */
    public function testGetPrograms(Supervisor $supervisor): Supervisor
    {
        $expected = ['program1', 'program2', 'program4'];
        $this->assertSame($expected, $supervisor->getPrograms());

        return $supervisor;
    }

    /**
     * @depends testGetTransports
     */
    public function testGetProgramsWithDoublons(Supervisor $supervisor): Supervisor
    {
        $expected = ['program1', 'program2', 'program4'];
        $this->assertSame($expected, $supervisor->getPrograms());

        return $supervisor;
    }

    /**
     * @depends testGetTransports
     */
    public function testGetTransportsNamesByProgram(Supervisor $supervisor): Supervisor
    {
        $expected = ['transport1', 'transport3'];
        $this->assertSame($expected, $supervisor->getTransportsNamesByProgram('program1'));

        return $supervisor;
    }

    /**
     * @depends testGetTransports
     */
    public function testGetTransportsNamesByProgramEmpty(Supervisor $supervisor): Supervisor
    {
        $this->assertSame([], $supervisor->getTransportsNamesByProgram('bad'));

        return $supervisor;
    }

    public function testStartProgram(): void
    {
        $supervisorApi = $this->createSupervisorApiMockerStart(['program1'], true);
        $supervisor = new Supervisor($supervisorApi, $this->getTransports());
        $this->assertSame([], $supervisor->startProgram('program1'));
    }

    public function testStopProgram(): void
    {
        $supervisorApi = $this->createSupervisorApiMockerStop(['program1'], true);
        $supervisor = new Supervisor($supervisorApi, $this->getTransports());
        $this->assertSame([], $supervisor->stopProgram('program1'));
    }

    public function testGetProgramsStatus(): void
    {
        $expected = [
            'program1' => [
                new Process([
                    'group' => 'program1',
                    'name' => 'program1_1',
                    'state' => Process::RUNNING,
                    'statename' => 'Running',
                    'pid' => '0011',
                ]),
                new Process([
                    'group' => 'program1',
                    'name' => 'program1_2',
                    'state' => Process::RUNNING,
                    'statename' => 'Running',
                    'pid' => '0012',
                ]),
            ],
            'bad' => [],
        ];

        $supervisorApi = $this->createSupervisorApiMockerStatus();
        $supervisor = new Supervisor($supervisorApi, $this->getTransports());
        $this->assertEquals($expected, $supervisor->getProgramsStatus(['program1', 'bad']));
    }
}
