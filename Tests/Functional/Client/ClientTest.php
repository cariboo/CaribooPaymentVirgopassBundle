<?php

namespace Cariboo\Payment\VirgopassBundle\Tests\Functional\Client;

use Cariboo\Payment\VirgopassBundle\Client\Authentication\TokenAuthenticationStrategy;
use Cariboo\Payment\VirgopassBundle\Client\Client;

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
    /**
     * @var \Cariboo\Payment\VirgopassBundle\Client\Client
     */
    protected $client;

    protected function setUp()
    {
        if (empty($_SERVER['API_USERNAME']) || empty($_SERVER['API_PASSWORD'])) {
            $this->markTestSkipped('In order to run these tests you have to configure: API_USERNAME, API_PASSWORD parameters in phpunit.xml file');
        }

        $authenticationStrategy = new TokenAuthenticationStrategy(
            $_SERVER['API_USERNAME'],
            $_SERVER['API_PASSWORD']
        );

        $this->client = new Client($authenticationStrategy, $debug = true);
    }

    public function testRequestGetToken()
    {
        $response = $this->client->requestGetToken($_SERVER['ONETIME_SERVICE_ID'], 1024, $subscription = null);
        
        $this->assertInstanceOf('Cariboo\Payment\VirgopassBundle\Client\Response', $response);
        $this->assertTrue($response->body->has('error_code'));
        $this->assertTrue($response->isSuccess());
        $this->assertEquals(0, $response->body->get('error_code'));
        $this->assertTrue($response->body->has('token'));
    }

    // public function testRequestGetServiceInfo()
    // {
    //     $response = $this->client->requestGetServiceInfo($_SERVER['ONETIME_SERVICE_ID']);

    //     $this->assertInstanceOf('Cariboo\Payment\VirgopassBundle\Client\Response', $response);
    //     $this->assertTrue($response->isSuccess());
    //     $this->assertTrue($response->body->has('subscription_id'));
    //     $this->assertTrue($response->body->has('status'));
    //     $this->assertEquals(false, $response->body->has('status'));
    // }

    // public function testRequestSetExpressCheckout()
    // {
    //     $response = $this->client->requestSetExpressCheckout(123.43, 'http://www.foo.com/returnUrl', 'http://www.foo.com/cancelUrl');

    //     $this->assertInstanceOf('JMS\Payment\PaypalBundle\Client\Response', $response);
    //     $this->assertTrue($response->body->has('TOKEN'));
    //     $this->assertTrue($response->isSuccess());
    //     $this->assertEquals('Success', $response->body->get('ACK'));
    // }

    // public function testRequestGetExpressCheckoutDetails()
    // {
    //     $response = $this->client->requestSetExpressCheckout('123', 'http://www.foo.com/', 'http://www.foo.com/');

    //     //guard
    //     $this->assertInstanceOf('JMS\Payment\PaypalBundle\Client\Response', $response);
    //     $this->assertTrue($response->body->has('TOKEN'));

    //     $response = $this->client->requestGetExpressCheckoutDetails($response->body->get('TOKEN'));

    //     $this->assertTrue($response->body->has('TOKEN'));
    //     $this->assertTrue($response->body->has('CHECKOUTSTATUS'));
    //     $this->assertEquals('PaymentActionNotInitiated', $response->body->get('CHECKOUTSTATUS'));
    //     $this->assertEquals('Success', $response->body->get('ACK'));
    // }
}