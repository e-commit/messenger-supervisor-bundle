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
 * @psalm-suppress UndefinedMethod
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
                            ->then(fn (string $v) => ['program' => $v])
                        ->end()
                        ->children()
                            ->scalarNode('program')->isRequired()->end()
                            ->arrayNode('failure')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->scalarNode('stop_program')
                                        ->defaultValue(WorkerMessageFailedEventListener::FAILURE_ACTION_ALWAYS)
                                        ->validate()
                                            ->ifNotInArray([WorkerMessageFailedEventListener::FAILURE_ACTION_ALWAYS, WorkerMessageFailedEventListener::FAILURE_ACTION_WILL_NOT_RETRY, WorkerMessageFailedEventListener::FAILURE_ACTION_NEVER])
                                            ->thenInvalid('Invalid stop_program %s')
                                        ->end()
                                    ->end()
                                    ->scalarNode('send_mail')
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
                        ->scalarNode('host')->isRequired()->end()
                        ->integerNode('port')->defaultValue(9001)->end()
                        ->scalarNode('username')->defaultNull()->end()
                        ->scalarNode('password')->defaultNull()->end()
                        ->integerNode('timeout')->defaultValue(3600)->end()
                    ->end()
                ->end()
                ->arrayNode('mailer')
                    ->children()
                        ->scalarNode('from')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(fn (mixed $v) => !$this->validateEmail($v))
                                ->thenInvalid('Invalid email %s')
                            ->end()
                        ->end()
                        ->arrayNode('to')
                            ->defaultValue([])
                            ->scalarPrototype()
                                ->validate()
                                    ->ifTrue(fn (mixed $v) => !$this->validateEmail($v))
                                    ->thenInvalid('Invalid email %s')
                                ->end()
                            ->end()
                            ->beforeNormalization()
                                ->ifString()
                                ->then(fn (string $v) => [$v])
                            ->end()
                        ->end()
                        ->scalarNode('subject')->defaultValue('[Supervisor][<server>][<program>] Error')->end()
                    ->end()
                ->end()
                ->integerNode('failure_event_priority')->defaultValue(10)->end()
            ->end()
            ->validate()
                ->ifTrue(function (mixed $v) {
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
