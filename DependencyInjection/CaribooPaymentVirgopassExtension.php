<?php

namespace Cariboo\Payment\VirgopassBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/*
 * Copyright 2012 Stephane Decleire <sdecleire@cariboo-networks.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class CaribooPaymentVirgopassExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->process($configuration->getConfigTree(), $configs);

        $xmlLoader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $xmlLoader->load('services.xml');

        $container->setParameter('payment.virgopass.login', $config['login']);
        $container->setParameter('payment.virgopass.password', $config['password']);
        $container->setParameter('payment.virgopass.mobile_checkout.return_url', $config['return_url']);
        $container->setParameter('payment.virgopass.mobile_checkout.error_url', $config['error_url']);
        $container->setParameter('payment.virgopass.mobile_checkout.cancel_url', $config['cancel_url']);
        $services = array();
        foreach (array_keys($config['services']) as $name) {
            $services[$name] = $config['services'][$name];
        }
        $container->setParameter('payment.virgopass.services', $services);
        $container->setParameter('payment.virgopass.debug', $config['debug']);
    }
}