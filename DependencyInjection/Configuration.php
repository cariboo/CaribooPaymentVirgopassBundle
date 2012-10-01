<?php

namespace Cariboo\Payment\VirgopassBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

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

class Configuration
{
    public function getConfigTree()
    {
        $tb = new TreeBuilder();

        return $tb
            ->root('cariboo_payment_virgopass', 'array')
                ->children()
                    ->scalarNode('login')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('password')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('return_url')->defaultNull()->end()
                    ->scalarNode('error_url')->defaultNull()->end()
                    ->scalarNode('cancel_url')->defaultNull()->end()
                    ->children()
                        ->arrayNode('services')
                            ->useAttributeAsKey('name')
                                ->children()
                                    ->scalarNode('rate')->isRequired()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    ->booleanNode('debug')->defaultValue('%kernel.debug%')->end()
                ->end()
            ->end()
            ->buildTree();
    }
}