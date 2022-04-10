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

namespace Ecommit\MessengerSupervisorBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ErrorEmailBuilderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $errorEmailBuilderId = $container->getParameter('ecommit_messenger_supervisor.error_email_builder_service');

        $container->getDefinition('ecommit_messenger_supervisor.event_listener.worker_message_failed')
            ->replaceArgument(1, new Reference($errorEmailBuilderId));
    }
}
