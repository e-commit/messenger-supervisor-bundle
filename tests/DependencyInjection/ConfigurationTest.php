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

namespace Ecommit\MessengerSupervisorBundle\Tests\DependencyInjection;

use Ecommit\MessengerSupervisorBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testMiniConfig(): void
    {
        $configuration = $this->processConfiguration([
            'supervisor' => [
                'host' => '127.0.0.1',
            ],
        ]);
        $expected = [
            'supervisor' => [
                'host' => '127.0.0.1',
                'port' => 9001,
                'username' => null,
                'password' => null,
                'timeout' => 3600,
            ],
            'transports' => [],
            'failure_event_priority' => 10,
        ];

        $this->assertSame($expected, $configuration);
    }

    public function testMiniConfigWithTransports(): void
    {
        $configuration = $this->processConfiguration([
            'supervisor' => [
                'host' => '127.0.0.1',
            ],
            'transports' => [
                'transport1' => [
                    'program' => 'program1',
                ],
            ],
            'mailer' => [
                'from' => 'from@domain.com',
                'to' => ['to@domain.com'],
            ],
        ]);
        $expected = [
            'supervisor' => [
                'host' => '127.0.0.1',
                'port' => 9001,
                'username' => null,
                'password' => null,
                'timeout' => 3600,
            ],
            'transports' => [
                'transport1' => [
                    'program' => 'program1',
                    'failure' => [
                        'stop_program' => 'always',
                        'send_mail' => 'always',
                    ],
                ],
            ],
            'mailer' => [
                'from' => 'from@domain.com',
                'to' => ['to@domain.com'],
                'subject' => '[Supervisor][<server>][<program>] Error',
            ],
            'failure_event_priority' => 10,
        ];

        $this->assertSame($expected, $configuration);
    }

    public function testMiniConfigWithProgramString(): void
    {
        $configuration = $this->processConfiguration([
            'supervisor' => [
                'host' => '127.0.0.1',
            ],
            'transports' => [
                'transport1' => 'program1',
            ],
            'mailer' => [
                'from' => 'from@domain.com',
                'to' => ['to@domain.com'],
            ],
        ]);
        $expected = [
            'supervisor' => [
                'host' => '127.0.0.1',
                'port' => 9001,
                'username' => null,
                'password' => null,
                'timeout' => 3600,
            ],
            'transports' => [
                'transport1' => [
                    'program' => 'program1',
                    'failure' => [
                        'stop_program' => 'always',
                        'send_mail' => 'always',
                    ],
                ],
            ],
            'mailer' => [
                'from' => 'from@domain.com',
                'to' => ['to@domain.com'],
                'subject' => '[Supervisor][<server>][<program>] Error',
            ],
            'failure_event_priority' => 10,
        ];

        $this->assertSame($expected, $configuration);
    }

    public function testConfigWithInvalidFailureStopProgram(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid stop_program "bad"');

        $this->processConfiguration([
            'supervisor' => [
                'host' => '127.0.0.1',
            ],
            'transports' => [
                'transport1' => [
                    'program' => 'program1',
                    'failure' => [
                        'stop_program' => 'bad',
                        'send_mail' => 'always',
                    ],
                ],
            ],
            'mailer' => [
                'from' => 'from@domain.com',
                'to' => ['to@domain.com'],
            ],
        ]);
    }

    public function testConfigWithInvalidFailureSendMail(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid send_mail "bad"');

        $this->processConfiguration([
            'supervisor' => [
                'host' => '127.0.0.1',
            ],
            'transports' => [
                'transport1' => [
                    'program' => 'program1',
                    'failure' => [
                        'stop_program' => 'always',
                        'send_mail' => 'bad',
                    ],
                ],
            ],
            'mailer' => [
                'from' => 'from@domain.com',
                'to' => ['to@domain.com'],
            ],
        ]);
    }

    public function testMailerToString(): void
    {
        $configuration = $this->processConfiguration([
            'supervisor' => [
                'host' => '127.0.0.1',
            ],
            'transports' => [
                'transport1' => 'program1',
            ],
            'mailer' => [
                'from' => 'from@domain.com',
                'to' => 'to@domain.com',
            ],
        ]);

        $expected = [
            'from' => 'from@domain.com',
            'to' => ['to@domain.com'],
            'subject' => '[Supervisor][<server>][<program>] Error',
        ];
        $this->assertSame($expected, $configuration['mailer']);
    }

    public function testMailerToArray(): void
    {
        $configuration = $this->processConfiguration([
            'supervisor' => [
                'host' => '127.0.0.1',
            ],
            'transports' => [
                'transport1' => 'program1',
            ],
            'mailer' => [
                'from' => 'from@domain.com',
                'to' => ['to1@domain.com', 'to2@domain.com'],
            ],
        ]);

        $expected = [
            'from' => 'from@domain.com',
            'to' => ['to1@domain.com', 'to2@domain.com'],
            'subject' => '[Supervisor][<server>][<program>] Error',
        ];
        $this->assertSame($expected, $configuration['mailer']);
    }

    public function testMailerToChidlrenNotScalar(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('scalar');
        $this->processConfiguration([
            'supervisor' => [
                'host' => '127.0.0.1',
            ],
            'transports' => [
                'transport1' => 'program1',
            ],
            'mailer' => [
                'from' => 'from@domain.com',
                'to' => [['to1@domain.com']],
            ],
        ]);
    }

    public function testMailerFromNotEmail(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid email "bad"');
        $this->processConfiguration([
            'supervisor' => [
                'host' => '127.0.0.1',
            ],
            'transports' => [
                'transport1' => 'program1',
            ],
            'mailer' => [
                'from' => 'bad',
                'to' => ['to1@domain.com'],
            ],
        ]);
    }

    public function testMailerToNotEmail(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid email "bad"');
        $this->processConfiguration([
            'supervisor' => [
                'host' => '127.0.0.1',
            ],
            'transports' => [
                'transport1' => 'program1',
            ],
            'mailer' => [
                'from' => 'from@domain.com',
                'to' => ['bad'],
            ],
        ]);
    }

    public function testMissingMailerFrom(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('mailer option must be configured');
        $this->processConfiguration([
            'supervisor' => [
                'host' => '127.0.0.1',
            ],
            'transports' => [
                'transport1' => 'program1',
            ],
            'mailer' => [
                'to' => ['to1@domain.com'],
            ],
        ]);
    }

    public function testMissingMailerTo(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('mailer option must be configured');
        $this->processConfiguration([
            'supervisor' => [
                'host' => '127.0.0.1',
            ],
            'transports' => [
                'transport1' => 'program1',
            ],
            'mailer' => [
                'from' => 'from@domain.com',
            ],
        ]);
    }

    public function testMissingMailerFromAndTo(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('mailer option must be configured');
        $this->processConfiguration([
            'supervisor' => [
                'host' => '127.0.0.1',
            ],
            'transports' => [
                'transport1' => 'program1',
            ],
        ]);
    }

    protected function processConfiguration(array $configs): array
    {
        $processor = new Processor();

        return $processor->processConfiguration(new Configuration(), ['ecommit_messenger_supervisor' => $configs]);
    }
}
