<?php
namespace Cariboo\Payment\VirgopassBundle\Client;

use Symfony\Component\BrowserKit\Response as RawResponse;

use JMS\Payment\CoreBundle\BrowserKit\Request;
use JMS\Payment\CoreBundle\Plugin\Exception\CommunicationException;
use Cariboo\Payment\VirgopassBundle\Client\Authentication\AuthenticationStrategyInterface;

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

class Client
{
    const API_VERSION = '1.5';

    protected static $methods = array(
        'getToken'              => 'POST',
        'purchase'              => 'GET',
        'subscription'          => 'GET',
        'resiliation'           => 'GET',
        'getUserInfo'           => 'POST',
        'isSub'                 => 'POST',
        'getServiceInfo'        => 'POST',
        'isIplusBoxEligible'    => 'POST',
    );

    protected $authenticationStrategy;

    protected $isDebug;

    protected $curlOptions;

    public function __construct(AuthenticationStrategyInterface $authenticationStrategy, $isDebug)
    {
        $this->authenticationStrategy = $authenticationStrategy;
        $this->isDebug = !!$isDebug;
        $this->curlOptions = array();
    }

    /**
     * In order to secure the billing transactions between Virgopass and partner platform, we will use
     * a token authentication mechanism. Before any payment request, the partner will generate a token,
     * that will be used in the following methods.
     *
     * @param   string  $service ID of the customer service (corresponds to a price and, a payment method and a periodicity if payment method is 'subscription')
     * @param   string  $session parameter defined by the partner to identify the transaction
     * @param   string  $subscription this parameter has to be passed only if the generated token is about a resiliation
     * @return  Response
     */
    public function requestGetToken($service, $session, $subscription = null)
    {
        return $this->sendApiRequest('getToken', array(
            'service_id'        => $service,
            'session_id'        => $session,
            'subscription_id'   => $subscription,
        ));
    }

    /**
     * Initiate One-Time payments.
     *
     * @param   string  $token token generated with the getToken method
     * @param   array   $optionalParameters Optional parameters can be found on www.virgopass.com
     * @throws  ActionRequiredException The user is redirected to the payment page of its carrier
     * @return  Response
     */
    public function requestPurchase($token, array $optionalParameters = array())
    {
        return $this->sendApiRequest('purchase', array_merge($optionalParameters, array(
            'token' => $token,
        )));
    }

    /**
     * Initiate subscription payments, either weekly or monthly.
     * In France, the renewal of the subscription is done automatically without notification, by the carrier,
     * except is the customer cancelled its subscription during the subscription period.
     *
     * @param   string  $token token generated with the getToken method
     * @param   array   $optionalParameters Optional parameters can be found on www.virgopass.com
     * @throws  ActionRequiredException The user is redirected to the carrier payment page
     * @return  Response
     */
    public function requestSubscription($token, array $optionalParameters = array())
    {
        return $this->sendApiRequest('subscription', array_merge($optionalParameters, array(
            'token' => $token,
        )));
    }

    /**
     * Cancel subscription payments.
     *
     * @param   string  $token token generated with the getToken method
     * @param   array   $optionalParameters Optional parameters can be found on www.virgopass.com
     * @throws  ActionRequiredException The user is redirected to the carrier unsubscription page
     * @return  Response
     */
    public function requestResiliation($token, array $optionalParameters = array())
    {
        return $this->sendApiRequest('resiliation', array_merge($optionalParameters, array(
            'token' => $token,
        )));
    }

    /**
     * Get all the information about a subscription.
     *
     * @param   string  $subscription unique id returned by the subscription method
     * @return  Response
     */
    public function requestGetUserInfo($subscription)
    {
        return $this->sendApiRequest('getUserInfo', array(
            'subscription_id' => $subscription,
        ));
    }

    /**
     * Check the subscription of a customer.
     *
     * @param   string  $subscription unique id returned by the subscription method
     * @return  Response
     */
    public function requestIsSub($subscription)
    {
        return $this->sendApiRequest('isSub', array(
            'subscription_id' => $subscription,
        ));
    }

    /**
     * Get all the subscriptions active for a service.
     *
     * @param   string  $service service configured with the partner, and corresponding to a fixed price
     * @return  Response
     */
    public function requestGetServiceInfo($service)
    {
        return $this->sendApiRequest('getServiceInfo', array(
            'service_id' => $service,
        ));
    }

    /**
     * To know if an end user is I+ Box eligible based on his IP address.
     *
     * @param   string  $ipAddress end-user IP address in format "127.0.0.1"
     * @return  Response
     */
    public function requestIsIplusBoxEligible($ipAddress)
    {
        return $this->sendApiRequest('isIplusBoxEligible', array(
            'ip_address' => $ipAddress,
        ));
    }

    protected function sendApiRequest($method, array $parameters)
    {
        // setup request
        $request = new Request(
            $this->authenticationStrategy->getApiEndpoint(self::API_VERSION, $method, $this->isDebug),
            self::$methods[$method],
            $parameters
        );

        // authenticate request if token is not defined
        if (!array_key_exists('token', $parameters))
        {
            $this->authenticationStrategy->authenticate($request);
        }

        $response = $this->request($request);
        if (200 !== $response->getStatus()) {
            throw new CommunicationException('The API request was not successful (Status: '.$response->getStatus().'): '.$response->getContent());
        }

        $parameters = array();
        parse_str($response->getContent(), $parameters);

        return new Response($parameters);
    }

    protected function setCurlOption($name, $value)
    {
        $this->curlOptions[$name] = $value;
    }

    /**
     * A small helper to url-encode an array
     *
     * @param array $encode
     * @return string
     */
    protected function urlEncodeArray(array $encode)
    {
        $encoded = '';
        foreach ($encode as $name => $value) {
            $encoded .= '&'.urlencode($name).'='.urlencode($value);
        }

        return substr($encoded, 1);
    }

    /**
     * Performs a request to an external payment service
     *
     * @throws CommunicationException when an curl error occurs
     * @param Request $request
     * @param mixed $parameters either an array for form-data, or an url-encoded string
     * @return Response
     */
    protected function request(Request $request)
    {
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('The cURL extension must be loaded.');
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt_array($curl, $this->curlOptions);
        curl_setopt($curl, CURLOPT_URL, $request->getUri());
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);

        // add headers
        $headers = array();
        foreach ($request->headers->all() as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $subValue) {
                    $headers[] = sprintf('%s: %s', $name, $subValue);
                }
            } else {
                $headers[] = sprintf('%s: %s', $name, $value);
            }
        }
        if (count($headers) > 0) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        // set method
        $method = strtoupper($request->getMethod());
        if ('POST' === $method) {
            curl_setopt($curl, CURLOPT_POST, true);

            if (!$request->headers->has('Content-Type') || 'multipart/form-data' !== $request->headers->get('Content-Type')) {
                $postFields = $this->urlEncodeArray($request->request->all());
            } else {
                $postFields = $request->request->all();
            }

            curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);
        } else if ('PUT' === $method) {
            curl_setopt($curl, CURLOPT_PUT, true);
        } else if ('HEAD' === $method) {
            curl_setopt($curl, CURLOPT_NOBODY, true);
        }

        // perform the request
        if (false === $returnTransfer = curl_exec($curl)) {
            throw new CommunicationException(
                'cURL Error: '.curl_error($curl), curl_errno($curl)
            );
        }

        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $headers = array();
        if (preg_match_all('#^([^:\r\n]+):\s+([^\n\r]+)#m', substr($returnTransfer, 0, $headerSize), $matches)) {
            foreach ($matches[1] as $key => $name) {
                $headers[$name] = $matches[2][$key];
            }
        }

        $response = new RawResponse(
            substr($returnTransfer, $headerSize),
            curl_getinfo($curl, CURLINFO_HTTP_CODE),
            $headers
        );
        curl_close($curl);

        return $response;
    }
}
