<?php

namespace Cariboo\Payment\VirgopassBundle\Plugin;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use JMS\Payment\CoreBundle\Model\ExtendedDataInterface;
use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Plugin\AbstractPlugin;
use JMS\Payment\CoreBundle\Plugin\Exception\FinancialException;
use JMS\Payment\CoreBundle\Plugin\Exception\PaymentPendingException;
use JMS\Payment\CoreBundle\Plugin\Exception\InvalidPaymentInstructionException;
use JMS\Payment\CoreBundle\Plugin\Exception\Action\VisitUrl;
use JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use JMS\Payment\CoreBundle\Util\Number;
use Cariboo\Payment\VirgopassBundle\Client\Client;
use Cariboo\Payment\VirgopassBundle\Client\Response;

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

class MobileCheckoutPlugin extends AbstractPlugin
{
    /**
     */
    protected $container;

    /**
     * @var \Cariboo\Payment\VirgopassBundle\Client\Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $returnUrl;

    /**
     * @var string
     */
    protected $errorUrl;

    /**
     * @var string
     */
    protected $cancelUrl;

    /**
     * @param ContainerInterface $container
     * @param \Cariboo\Payment\VirgopassBundle\Client\Client $client
     * @param string $returnUrl
     * @param string $errorUrl
     * @param string $cancelUrl
     */
    public function __construct(ContainerInterface $container, Client $client, $returnUrl, $errorUrl, $cancelUrl)
    {
        $this->container    = $container;
        $this->client       = $client;
        $this->returnUrl    = $returnUrl;
        $this->errorUrl     = $errorUrl;
        $this->cancelUrl    = $cancelUrl;
    }

    public function approveAndDeposit(FinancialTransactionInterface $transaction, $retry)
    {
        $token = $this->obtainMobileCheckoutToken($transaction);

        // Verify transaction status
        if (FinancialTransactionInterface::STATE_NEW == $transaction->getState()
            || FinancialTransactionInterface::STATE_PENDING == $transaction->getState())
        {
            $request = $this->container->get('request');

            $code = $request->query->get('error_code');
            $session_id = $request->query->get('session_id');
            if(isset($code) && isset($session_id))
            {
                $data = $transaction->getExtendedData();
                if ($code == '0' && $session_id === $data->get('tracking_id'))
                {
                    // Payment OK
                    $service = $transaction->getExtendedData()->get('service');
                    $services = $this->container->getParameter('payment.virgopass.services');
                    $amount = $services[$service]['price'];
                    $transaction->setReferenceNumber($request->query->get('purchase_id'));
                    $transaction->setProcessedAmount($amount);
                    $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
                    $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);
                }
                else
                {
                    // Payment KO
                    $ex = new FinancialException('PaymentAction failed.');
                    $transaction->setResponseCode('Failed');
                    $transaction->setReasonCode(PluginInterface::REASON_CODE_INVALID);
                    $ex->setFinancialTransaction($transaction);

                    throw $ex;
                }
            }
            else
            {
                $actionRequest = new ActionRequiredException('User has not yet authorized the transaction.');
                $actionRequest->setFinancialTransaction($transaction);
                $actionRequest->setAction(new VisitUrl($this->client->requestPurchase($token, $this->getCallbackUrls($transaction))));
                throw $actionRequest;
            }
        }
    }

    public function processNotify(Request $request)
    {
    }

    public function processes($paymentSystemName)
    {
        return 'virgopass_mobile_checkout' === $paymentSystemName;
    }

    public function isIndependentCreditSupported()
    {
        return false;
    }

    /**
     * @param \JMS\Payment\CoreBundle\Model\FinancialTransactionInterface $transaction
     * @return string
     */
    protected function obtainMobileCheckoutToken(FinancialTransactionInterface $transaction)
    {
        // Abonnements non supportés actuellement
        $subscription = null;

        $data = $transaction->getExtendedData();
        if ($data->has('mobile_checkout_token')) {
            return $data->get('mobile_checkout_token');
        }
        $service = $data->get('service');
        $response = $this->client->requestGetToken($service, $data->get('tracking_id'), $subscription);
        $this->throwUnlessSuccessResponse($response, $transaction);

        $token = $response->body->get('token');
        $data->set('mobile_checkout_token', $token);

        return $token;
    }

    /**
     * @param \JMS\Payment\CoreBundle\Model\FinancialTransactionInterface $transaction
     * @param \Cariboo\Payment\VirgopassBundle\Client\Response $response
     * @return null
     * @throws \JMS\Payment\CoreBundle\Plugin\Exception\FinancialException
     */
    protected function throwUnlessSuccessResponse(Response $response, FinancialTransactionInterface $transaction)
    {
        if ($response->isSuccess()) {
            return;
        }

        $transaction->setResponseCode($response->body->get('error_code'));
        $transaction->setReasonCode($response->body->get('error_desc'));

        $ex = new FinancialException('Virgopass-Response was not successful: '.$response->body->get('error_code'));
        $ex->setFinancialTransaction($transaction);

        throw $ex;
    }

    protected function getCallbackUrls(FinancialTransactionInterface $transaction)
    {
        $data = $transaction->getExtendedData();
        $callbacks = array();
        $callbacks['callback_ok'] = $this->getReturnUrl($data);
        $callbacks['callback_ko'] = $this->getErrorUrl($data);
        $callbacks['callback_cancel'] = $this->getCancelUrl($data);

        return $callbacks;
    }
    protected function getReturnUrl(ExtendedDataInterface $data)
    {
        if ($data->has('return_url')) {
            return $data->get('return_url');
        }
        else if (0 !== strlen($this->returnUrl)) {
            return $this->returnUrl;
        }

        throw new \RuntimeException('You must configure a return url.');
    }

    protected function getErrorUrl(ExtendedDataInterface $data)
    {
        if ($data->has('error_url')) {
            return $data->get('error_url');
        }
        else if (0 !== strlen($this->errorUrl)) {
            return $this->errorUrl;
        }

        throw new \RuntimeException('You must configure an error url.');
    }

    protected function getCancelUrl(ExtendedDataInterface $data)
    {
        if ($data->has('cancel_url')) {
            return $data->get('cancel_url');
        }
        else if (0 !== strlen($this->cancelUrl)) {
            return $this->cancelUrl;
        }

        throw new \RuntimeException('You must configure a cancel url.');
    }
}
