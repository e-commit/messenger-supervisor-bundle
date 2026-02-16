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
use Supervisor\ProcessInterface;
use Supervisor\ServiceStates;
use Supervisor\SupervisorInterface;

class SupervisorApi implements SupervisorInterface
{
    /**
     * @param mixed[] $arguments
     */
    public function call(string $namespace, string $method, array $arguments = []): mixed
    {
        return null;
    }

    /**
     * @param mixed[] $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return null;
    }

    public function isConnected(): bool
    {
        return false;
    }

    public function isRunning(): bool
    {
        return false;
    }

    public function getServiceState(): ServiceStates
    {
        return ServiceStates::Running;
    }

    public function checkState(int|ServiceStates $checkState): bool
    {
        return false;
    }

    /**
     * @return ProcessInterface[]
     */
    public function getAllProcesses(): array
    {
        $processes = $this->getAllProcessInfo();
        foreach ($processes as $key => $processInfo) {
            $processes[$key] = new Process($processInfo);
        }

        return $processes;
    }

    public function getProcess(string $name): ProcessInterface
    {
        return new Process([]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAllProcessInfo(): array
    {
        return [];
    }

    /**
     * @return mixed[]
     */
    public function startProcessGroup(string $name, bool $wait = true): array
    {
        return [];
    }

    /**
     * @return mixed[]
     */
    public function stopProcessGroup(string $name, bool $wait = true): array
    {
        return [];
    }
}
