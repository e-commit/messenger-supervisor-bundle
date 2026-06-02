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

namespace Ecommit\MessengerSupervisorBundle\DependencyInjection;

use Ecommit\MessengerSupervisorBundle\EventListener\WorkerMessageFailedEventListener;
use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\NoRFCWarningsValidation;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @phpstan-type ProcessedConfiguration array{
 *     transports: Transports,
 *     supervisor: SupervisorConfig,
 *     mailer: MailerConfig,
 *     failure_event_priority: int
 * }
 * @phpstan-type Transports array<string, Transport>
 * @phpstan-type Transport array{
 *      program: string,
 *      failure: array{
 *          stop_program: WorkerMessageFailedEventListener::FAILURE_ACTION_*,
 *          send_mail: WorkerMessageFailedEventListener::FAILURE_ACTION_*,
 *      },
 *  }
 * @phpstan-type SupervisorConfig array{
 *     host: string,
 *     port: int,
 *     username: ?string,
 *     password: ?string,
 *     timeout: int,
 * }
 * @phpstan-type MailerConfig array{
 *     from: ?string,
 *     to: string[],
 *     subject: string,
 * }
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ecommit_messenger_supervisor');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('transports')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static fn (string $v) => ['program' => $v])
                        ->end()
                        ->children()
                            ->scalarNode('program')->isRequired()->validate()->always(static fn (mixed $v): string => \is_scalar($v) ? (string) $v : '')->end()->end() // @legacy SF < 7.2 (string node introduced in Symfony 7.2)
                            ->arrayNode('failure')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->scalarNode('stop_program') // @legacy SF < 7.2 (string node introduced in Symfony 7.2)
                                        ->defaultValue(WorkerMessageFailedEventListener::FAILURE_ACTION_ALWAYS)
                                        ->validate()
                                            ->ifNotInArray([WorkerMessageFailedEventListener::FAILURE_ACTION_ALWAYS, WorkerMessageFailedEventListener::FAILURE_ACTION_WILL_NOT_RETRY, WorkerMessageFailedEventListener::FAILURE_ACTION_NEVER])
                                            ->thenInvalid('Invalid stop_program %s')
                                        ->end()
                                    ->end()
                                    ->scalarNode('send_mail') // @legacy SF < 7.2 (string node introduced in Symfony 7.2)
                                        ->defaultValue(WorkerMessageFailedEventListener::FAILURE_ACTION_ALWAYS)
                                        ->validate()
                                            ->ifNotInArray([WorkerMessageFailedEventListener::FAILURE_ACTION_ALWAYS, WorkerMessageFailedEventListener::FAILURE_ACTION_WILL_NOT_RETRY, WorkerMessageFailedEventListener::FAILURE_ACTION_NEVER])
                                            ->thenInvalid('Invalid send_mail %s')
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('supervisor')
                    ->isRequired()
                    ->children()
                        ->scalarNode('host')->isRequired()->validate()->always(static fn (mixed $v): string => \is_scalar($v) ? (string) $v : '')->end()->end() // @legacy SF < 7.2 (string node introduced in Symfony 7.2)
                        ->integerNode('port')->defaultValue(9001)->end()
                        ->scalarNode('username')->defaultNull()->validate()->always(static fn (mixed $v): ?string => \is_scalar($v) ? (string) $v : null)->end()->end() // @legacy SF < 7.2 (string node introduced in Symfony 7.2)
                        ->scalarNode('password')->defaultNull()->validate()->always(static fn (mixed $v): ?string => \is_scalar($v) ? (string) $v : null)->end()->end() // @legacy SF < 7.2 (string node introduced in Symfony 7.2)
                        ->integerNode('timeout')->defaultValue(3600)->end()
                    ->end()
                ->end()
                ->arrayNode('mailer')
                    ->children()
                        ->scalarNode('from') // @legacy SF < 7.2 (string node introduced in Symfony 7.2)
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(fn (mixed $v) => !$this->validateEmail($v))
                                ->thenInvalid('Invalid email %s')
                            ->end()
                        ->end()
                        ->arrayNode('to')
                            ->defaultValue([])
                            ->scalarPrototype() // @legacy SF < 7.2 (string node introduced in Symfony 7.2)
                                ->validate()
                                    ->ifTrue(fn (mixed $v) => !$this->validateEmail($v))
                                    ->thenInvalid('Invalid email %s')
                                ->end()
                            ->end()
                            ->beforeNormalization()
                                ->ifString()
                                ->then(static fn (string $v) => [$v])
                            ->end()
                        ->end()
                        ->scalarNode('subject')->defaultValue('[Supervisor][<server>][<program>] Error')->validate()->always(static fn (mixed $v): string => \is_scalar($v) ? (string) $v : '')->end()->end()  // @legacy SF < 7.2 (string node introduced in Symfony 7.2)
                    ->end()
                ->end()
                ->integerNode('failure_event_priority')->defaultValue(10)->end()
            ->end()
            ->validate()
                ->ifTrue(static function (mixed $v): bool {
                    /** @var ProcessedConfiguration $v */
                    if (0 === \count($v['transports']) || (!empty($v['mailer']['from']) && !empty($v['mailer']['to']))) {
                        return false;
                    }

                    return true;
                })
                ->thenInvalid('mailer option must be configured')
            ->end()
        ;

        return $treeBuilder;
    }

    protected function validateEmail(mixed $email): bool
    {
        $validator = new EmailValidator();

        return \is_string($email) && $validator->isValid($email, new NoRFCWarningsValidation());
    }
}
