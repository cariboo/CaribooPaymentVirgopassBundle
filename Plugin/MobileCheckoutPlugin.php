<?php

namespace Cariboo\Payment\VirgopassBundle\Plugin;

use JMS\Payment\CoreBundle\Model\ExtendedDataInterface;
use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Plugin\AbstractPlugin;
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
     * @var \Cariboo\Payment\VirgopassBundle\Client\Client
     */
    protected $client;

    /**
     */
    protected $services;

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
     */
    protected $logger;

    /**
     * @param \Cariboo\Payment\VirgopassBundle\Client\Client $client
     * @param $services
     * @param string $returnUrl
     * @param string $errorUrl
     * @param string $cancelUrl
     * @param $logger
     */
    public function __construct(Client $client, $services, $returnUrl, $errorUrl, $cancelUrl, $logger)
    {
        $this->client       = $client;
        $this->services     = $services;
        $this->returnUrl    = $returnUrl;
        $this->errorUrl     = $errorUrl;
        $this->cancelUrl    = $cancelUrl;

        $this->logger       = $logger;
    }

    public function approveAndDeposit(FinancialTransactionInterface $transaction, $retry)
    {
        $service = $transaction->getPayment()->getPaymentInstruction()->getExtendedData()->get('level');
        $this->logger->debug('service: '.$service);

        $token = $this->obtainMobileCheckoutToken($transaction, $service);
        $this->logger->info('token: '.$token);

        // $details = $this->client->requestGetExpressCheckoutDetails($token);
        // $this->throwUnlessSuccessResponse($details, $transaction);

        // verify checkout status
        // switch ($details->body->get('CHECKOUTSTATUS')) {
        //     case 'PaymentActionFailed':
        //         $ex = new FinancialException('PaymentAction failed.');
        //         $transaction->setResponseCode('Failed');
        //         $transaction->setReasonCode('PaymentActionFailed');
        //         $ex->setFinancialTransaction($transaction);

        //         throw $ex;

        //     case 'PaymentCompleted':
        //         break;

        //     case 'PaymentActionNotInitiated':
        //         break;

        //     default:
        //         $actionRequest = new ActionRequiredException('User has not yet authorized the transaction.');
        //         $actionRequest->setFinancialTransaction($transaction);
        //         $actionRequest->setAction(new VisitUrl($this->client->getAuthenticateMobileCheckoutTokenUrl($token)));

        //         throw $actionRequest;
        // }

        $actionRequest = new ActionRequiredException('User has not yet authorized the transaction.');
        $actionRequest->setFinancialTransaction($transaction);
        $actionRequest->setAction(new VisitUrl($this->client->getAuthenticateMobileCheckoutTokenUrl($token)));

        throw $actionRequest;

        // complete the transaction
        $data = $transaction->getExtendedData();
        $data->set('virgopass_purchase_id', $details->body->get('purchase_id'));

        $response = $this->client->requestDoExpressCheckoutPayment(
            $data->get('express_checkout_token'),
            $transaction->getRequestedAmount(),
            $paymentAction,
            $details->body->get('PAYERID'),
            array('PAYMENTREQUEST_0_CURRENCYCODE' => $transaction->getPayment()->getPaymentInstruction()->getCurrency())
        );
        $this->throwUnlessSuccessResponse($response, $transaction);

        switch($response->body->get('PAYMENTINFO_0_PAYMENTSTATUS')) {
            case 'Completed':
                break;

            case 'Pending':
                $transaction->setReferenceNumber($response->body->get('PAYMENTINFO_0_TRANSACTIONID'));
                
                throw new PaymentPendingException('Payment is still pending: '.$response->body->get('PAYMENTINFO_0_PENDINGREASON'));

            default:
                $ex = new FinancialException('PaymentStatus is not completed: '.$response->body->get('PAYMENTINFO_0_PAYMENTSTATUS'));
                $ex->setFinancialTransaction($transaction);
                $transaction->setResponseCode('Failed');
                $transaction->setReasonCode($response->body->get('PAYMENTINFO_0_PAYMENTSTATUS'));

                throw $ex;
        }

        $transaction->setReferenceNumber($response->body->get('PAYMENTINFO_0_TRANSACTIONID'));
        $transaction->setProcessedAmount($response->body->get('PAYMENTINFO_0_AMT'));
        $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
        $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);
    }

    /**
     * @param \JMS\Payment\CoreBundle\Model\FinancialTransactionInterface $transaction
     * @return string
     */
    protected function obtainMobileCheckoutToken(FinancialTransactionInterface $transaction, $service, $subscription = null)
    {
        $data = $transaction->getExtendedData();
        if ($data->has('mobile_checkout_token')) {
            return $data->get('mobile_checkout_token');
        }

        $opts = $data->has('checkout_params') ? $data->get('checkout_params') : array();

        $response = $this->client->requestGetToken($service, $transaction->getId(), $subscription);
        $token = $response->body->get('token');
        $this->logger->info('token: '.$token);
        $this->throwUnlessSuccessResponse($response, $transaction);

        $data->set('mobile_checkout_token', $token);

        // $authenticateTokenUrl = $this->client->getAuthenticateMobileCheckoutTokenUrl($response->body->get('token'));
        // $actionRequest = new ActionRequiredException('User must authorize the transaction.');
        // $actionRequest->setFinancialTransaction($transaction);
        // $actionRequest->setAction(new VisitUrl($authenticateTokenUrl));

        // throw $actionRequest;

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

        $ex = new FinancialException('Virgopass-Response was not successful: '.$response);
        $ex->setFinancialTransaction($transaction);

        throw $ex;
    }

    protected function getReturnUrl(ExtendedDataInterface $data)
    {
        if ($data->has('return_url')) {
            return $data->get('return_url');
        }
        return $this->returnUrl;
    }

    protected function getErrorUrl(ExtendedDataInterface $data)
    {
        if ($data->has('error_url')) {
            return $data->get('error_url');
        }
        return $this->errorUrl;
    }

    protected function getCancelUrl(ExtendedDataInterface $data)
    {
        if ($data->has('cancel_url')) {
            return $data->get('cancel_url');
        }
        return $this->cancelUrl;
    }

    public function processes($paymentSystemName)
    {
        return 'virgopass_mobile_checkout' === $paymentSystemName;
    }

    public function isIndependentCreditSupported()
    {
        return false;
    }
}