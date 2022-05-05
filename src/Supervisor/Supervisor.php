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

use Ecommit\MessengerSupervisorBundle\Exception\TransportNotFoundException;
use Supervisor\Supervisor as SupervisorApi;

class Supervisor
{
    /**
     * @var SupervisorApi
     */
    protected $api;

    protected $transports;

    public function __construct(SupervisorApi $api, array $transports)
    {
        $this->api = $api;
        $this->transports = $transports;
    }

    public function getApi(): SupervisorApi
    {
        return $this->api;
    }

    public function getTransports(): array
    {
        return $this->transports;
    }

    public function getTransport(string $name): array
    {
        if (!\array_key_exists($name, $this->transports)) {
            throw new TransportNotFoundException('Transport not found: '.$name);
        }

        return $this->transports[$name];
    }

    public function getPrograms(): array
    {
        $programs = [];
        foreach ($this->transports as $transport) {
            $programs[] = $transport['program'];
        }

        return array_values(array_unique($programs));
    }

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
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress InvalidReturnType
     *
     * Fixed by Supervisor 5.
     */
    public function startProgram(string $program, bool $wait = true): array
    {
        return $this->api->startProcessGroup($program, $wait);
    }

    /**
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress InvalidReturnType
     *
     * Fixed by Supervisor 5.
     */
    public function stopProgram(string $program, bool $wait = true): array
    {
        return $this->api->stopProcessGroup($program, $wait);
    }

    public function getProgramsStatus(array $programs): array
    {
        $supervisorPrograms = [];
        foreach ($this->api->getAllProcesses() as $supervisorProgram) {
            if (\in_array($supervisorProgram['group'], $programs)) {
                $supervisorPrograms[$supervisorProgram['group']][] = $supervisorProgram;
            }
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
