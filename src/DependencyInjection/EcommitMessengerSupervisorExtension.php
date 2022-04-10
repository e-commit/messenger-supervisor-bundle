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

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class EcommitMessengerSupervisorExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.xml');

        $container->setParameter('ecommit_messenger_supervisor.transports', $config['transports']);
        $container->setParameter('ecommit_messenger_supervisor.supervisor', $config['supervisor']);
        $container->setParameter('ecommit_messenger_supervisor.mailer', $config['mailer']);
        $container->setParameter('ecommit_messenger_supervisor.failure_event_priority', $config['failure_event_priority']);
    }
}
