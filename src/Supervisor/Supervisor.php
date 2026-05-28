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

namespace Ecommit\MessengerSupervisorBundle\Supervisor;

use Ecommit\MessengerSupervisorBundle\DependencyInjection\Configuration;
use Ecommit\MessengerSupervisorBundle\Exception\TransportNotFoundException;
use Supervisor\ProcessInterface;
use Supervisor\Supervisor as SupervisorApi;

/**
 * @phpstan-import-type Transports from Configuration
 * @phpstan-import-type Transport from Configuration
 */
class Supervisor
{
    /**
     * @param Transports $transports
     */
    public function __construct(protected SupervisorApi $api, protected array $transports)
    {
    }

    public function getApi(): SupervisorApi
    {
        return $this->api;
    }

    /**
     * @return Transports
     */
    public function getTransports(): array
    {
        return $this->transports;
    }

    /**
     * @return Transport
     */
    public function getTransport(string $name): array
    {
        if (!\array_key_exists($name, $this->transports)) {
            throw new TransportNotFoundException('Transport not found: '.$name);
        }

        return $this->transports[$name];
    }

    /**
     * @return string[]
     */
    public function getPrograms(): array
    {
        $programs = [];
        foreach ($this->transports as $transport) {
            $programs[] = $transport['program'];
        }

        return array_values(array_unique($programs));
    }

    /**
     * @return string[]
     */
    public function getTransportsNamesByProgram(string $program): array
    {
        $transportsNames = [];
        foreach ($this->transports as $transportName => $transport) {
            if ($transport['program'] === $program) {
                $transportsNames[] = $transportName;
            }
        }

        return $transportsNames;
    }

    /**
     * @return mixed[]
     */
    public function startProgram(string $program, bool $wait = true): array
    {
        return $this->api->startProcessGroup($program, $wait);
    }

    /**
     * @return mixed[]
     */
    public function stopProgram(string $program, bool $wait = true): array
    {
        return $this->api->stopProcessGroup($program, $wait);
    }

    /**
     * @param string[] $programs
     *
     * @return array<string, ProcessInterface[]>
     */
    public function getProgramsStatus(array $programs): array
    {
        $supervisorPrograms = [];
        foreach ($this->api->getAllProcesses() as $supervisorProgram) {
            $group = $supervisorProgram['group'];
            if (!\is_string($group) || !\in_array($group, $programs)) {
                continue;
            }
            $supervisorPrograms[$group][] = $supervisorProgram;
        }

        $result = [];
        foreach ($programs as $program) {
            if (\array_key_exists($program, $supervisorPrograms)) {
                $result[$program] = $supervisorPrograms[$program];
            } else {
                $result[$program] = [];
            }
        }

        return $result;
    }
}
