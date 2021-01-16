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
use fXmlRpc\Transport\HttpAdapterTransport;
use GuzzleHttp\Client as GuzzleClient;
use Http\Adapter\Guzzle7\Client as AdapterGuzzleClient;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use Supervisor\Supervisor;

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

        $guzzleClient = new GuzzleClient([
            'auth' => $auth,
            'timeout' => $supervisorParameters['timeout'],
        ]);

        $client = new fXmlRpcClient(
            static::getUrl($supervisorParameters['host'], $supervisorParameters['port']),
            new HttpAdapterTransport(
                new GuzzleMessageFactory(),
                new AdapterGuzzleClient($guzzleClient)
            )
        );

        return new Supervisor($client);
    }

    protected static function getUrl(string $host, int $port): string
    {
        return sprintf('http://%s:%s/RPC2', $host, $port);
    }
}
