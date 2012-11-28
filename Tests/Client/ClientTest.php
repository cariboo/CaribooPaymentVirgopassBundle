<?php

namespace Cariboo\Payment\VirgopassBundle\Tests\Client;

use Cariboo\Payment\VirgopassBundle\Client\Client;
use Cariboo\Payment\VirgopassBundle\Client\Authentication\TokenAuthenticationStrategy;

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

class ClientTest extends \PHPUnit_Framework_TestCase
{
    // /**
    //  * @var \Cariboo\Payment\VirgopassBundle\Client\Authentication\TokenAuthenticationStrategy
    //  */
    protected $authenticationStrategy;

    protected function setUp()
    {
        if (empty($_SERVER['API_USERNAME']) || empty($_SERVER['API_PASSWORD'])) {
            $this->markTestSkipped('In order to run these tests you have to configure: API_USERNAME, API_PASSWORD parameters in phpunit.xml file');
        }

        $this->authenticationStrategy = new TokenAuthenticationStrategy(
            $_SERVER['API_USERNAME'],
            $_SERVER['API_PASSWORD']
        );
    }

    public function testShouldAllowRequestPurchaseInDebugMode()
    {
        $expectedUrl = 'http://sandbox.virgopass.com/api_v1.5.php?purchase&token=foobar';

        $token = 'foobar';

        $client = new Client($this->authenticationStrategy, $debug = true);

        $this->assertEquals($expectedUrl, $client->requestPurchase($token, array()));
    }

    public function testShouldAllowRequestPurchaseInProdMode()
    {
        $expectedUrl = 'http://billing.virgopass.com/api_v1.5.php?purchase&token=barfoo';

        $token = 'barfoo';

        $client = new Client($this->authenticationStrategy, $debug = false);

        $this->assertEquals($expectedUrl, $client->requestPurchase($token, array()));
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|\Cariboo\Payment\VirgopassBundle\Client\Authentication\AuthenticationStrategyInterface
     */
    public function createAuthenticationStrategyMock()
    {
        return $this->getMock('Cariboo\Payment\VirgopassBundle\Client\Authentication\AuthenticationStrategyInterface');
    }
}
