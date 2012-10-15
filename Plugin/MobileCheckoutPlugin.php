<?php

namespace Cariboo\Payment\VirgopassBundle\Plugin;

use Symfony\Component\DependencyInjection\ContainerInterface;
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
    public function __construct(ContainerInterface $container, Client $client, $services, $returnUrl, $errorUrl, $cancelUrl, $logger)
    {
        $this->container    = $container;
        $this->client       = $client;
        $this->services     = $services;
        $this->returnUrl    = $returnUrl;
        $this->errorUrl     = $errorUrl;
        $this->cancelUrl    = $cancelUrl;

        $this->logger       = $logger;
    }

    public function approveAndDeposit(FinancialTransactionInterface $transaction, $retry)
    {
        $token = $this->obtainMobileCheckoutToken($transaction);
        $this->logger->info('token: '.$token);

        // Verify transaction status
        $this->logger->info('transaction state: '.$transaction->getState());
        if (FinancialTransactionInterface::STATE_PENDING == $transaction->getState())
        {
            $code = $this->container->get('request')->query->get('error_code');
            $session_id = $this->container->get('request')->query->get('session_id');
            if(!empty($code) && !empty($session_id) && $session_id == $transaction->getTrackingId())
            {
                if ($code == '0')
                {
                    // Payment OK
                    $service = $transaction->getPayment()->getPaymentInstruction()->getExtendedData()->get('level');
                    $amount = $this->services[$service];
                    $transaction->setReferenceNumber($this->container->get('request')->query->get('purchase_id'));
                    $transaction->setProcessedAmount($amount);
                    $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
                    $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);
                    $transaction->setState(FinancialTransactionInterface::STATE_SUCCESS);
                }
                else
                {
                    // Payment KO
                    $ex = new FinancialException('PaymentAction failed.');
                    $transaction->setResponseCode('Failed');
                    $transaction->setReasonCode('PaymentActionFailed');
                    $transaction->setState(FinancialTransactionInterface::STATE_FAILED);
                    $ex->setFinancialTransaction($transaction);

                    throw $ex;
                }
            }
            else
            {
                $actionRequest = new ActionRequiredException('User has not yet authorized the transaction.');
                $actionRequest->setFinancialTransaction($transaction);
                $actionRequest->setAction(new VisitUrl($this->client->requestPurchase($token, $this->getCallbacksUrl($transaction))));
                throw $actionRequest;
            }
        }


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

        // complete the transaction
        // $data = $transaction->getExtendedData();
        // $data->set('virgopass_purchase_id', $details->body->get('purchase_id'));

        // $response = $this->client->requestDoExpressCheckoutPayment(
        //     $data->get('express_checkout_token'),
        //     $transaction->getRequestedAmount(),
        //     $paymentAction,
        //     $details->body->get('PAYERID'),
        //     array('PAYMENTREQUEST_0_CURRENCYCODE' => $transaction->getPayment()->getPaymentInstruction()->getCurrency())
        // );
        // $this->throwUnlessSuccessResponse($response, $transaction);

        // switch($response->body->get('PAYMENTINFO_0_PAYMENTSTATUS')) {
        //     case 'Completed':
        //         break;

        //     case 'Pending':
        //         $transaction->setReferenceNumber($response->body->get('PAYMENTINFO_0_TRANSACTIONID'));
                
        //         throw new PaymentPendingException('Payment is still pending: '.$response->body->get('PAYMENTINFO_0_PENDINGREASON'));

        //     default:
        //         $ex = new FinancialException('PaymentStatus is not completed: '.$response->body->get('PAYMENTINFO_0_PAYMENTSTATUS'));
        //         $ex->setFinancialTransaction($transaction);
        //         $transaction->setResponseCode('Failed');
        //         $transaction->setReasonCode($response->body->get('PAYMENTINFO_0_PAYMENTSTATUS'));

        //         throw $ex;
        // }

        // $transaction->setReferenceNumber($response->body->get('PAYMENTINFO_0_TRANSACTIONID'));
        // $transaction->setProcessedAmount($response->body->get('PAYMENTINFO_0_AMT'));
        // $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
        // $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);
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

        $service = $transaction->getPayment()->getPaymentInstruction()->getExtendedData()->get('level');
        $this->logger->info('service: '.$service);

        $trackingId = $this->getTrackingId();
        $transaction->setTrackingId($trackingId);

        $this->logger->info('session: '.$transaction->getTrackingId());
        $response = $this->client->requestGetToken($service, $trackingId, $subscription, $this->logger);
        $this->throwUnlessSuccessResponse($response, $transaction);

        $token = $response->body->get('token');
        $data->set('mobile_checkout_token', $token);

        $transaction->setState(FinancialTransactionInterface::STATE_PENDING);

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

    protected function getCallbacksUrl(FinancialTransactionInterface $transaction)
    {
        $callbacks = array();
        $callbacks['callback_ok'] = $transaction->getPayment()->getPaymentInstruction()->getExtendedData()->get('return_url');
        $callbacks['callback_ko'] = $transaction->getPayment()->getPaymentInstruction()->getExtendedData()->get('error_url');
        $callbacks['callback_cancel'] = $transaction->getPayment()->getPaymentInstruction()->getExtendedData()->get('cancel_url');

        return $callbacks;
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

    protected function getTrackingId()
    {
        $now = new \DateTime();

        $id = $now->format('YmdHis');
        $id .= 'V';
        for ($i=0; $i < 3; $i++)
        { 
            $id .= chr(mt_rand(ord('A'), ord('Z')));
        }
        $this->logger->debug('session_id: '.$id);

        return $id;
    }
}
