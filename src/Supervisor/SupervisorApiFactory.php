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

use fXmlRpc\Client as fXmlRpcClient;
use fXmlRpc\Transport\PsrTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Supervisor\Supervisor;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

class SupervisorApiFactory
{
    public static function createSupervisor(array $supervisorParameters): Supervisor
    {
        $auth = null;
        if (null !== $supervisorParameters['username'] || null !== $supervisorParameters['password']) {
            if (null === $supervisorParameters['username'] || null === $supervisorParameters['password']) {
                throw new \Exception('Missing username or password');
            }

            $auth = [$supervisorParameters['username'], $supervisorParameters['password']];
        }

        $httpClient = HttpClient::create([
            'auth_basic' => $auth,
            'timeout' => $supervisorParameters['timeout'],
        ]);

        $psr18Client = new Psr18Client($httpClient);

        $fXmlRpcClient = new fXmlRpcClient(
            static::getUrl($supervisorParameters['host'], $supervisorParameters['port']),
            new PsrTransport(new Psr17Factory(), $psr18Client)
        );

        return new Supervisor($fXmlRpcClient);
    }

    protected static function getUrl(string $host, int $port): string
    {
        return \sprintf('http://%s:%s/RPC2', $host, $port);
    }
}
