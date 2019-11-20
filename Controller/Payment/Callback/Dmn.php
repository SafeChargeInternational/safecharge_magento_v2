<?php

namespace Safecharge\Safecharge\Controller\Payment\Callback;

use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObjectFactory;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Payment\State\AuthorizeCommand;
use Magento\Sales\Model\Order\Payment\State\CaptureCommand;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\OrderFactory;
use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\Config as ModuleConfig;
use Safecharge\Safecharge\Model\Logger as SafechargeLogger;
use Safecharge\Safecharge\Model\Payment;
use Safecharge\Safecharge\Model\Request\Payment\Factory as PaymentRequestFactory;

/**
 * Safecharge Safecharge payment redirect controller.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Dmn extends Action
{
    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @var AuthorizeCommand
     */
    private $authorizeCommand;

    /**
     * @var CaptureCommand
     */
    private $captureCommand;

    /**
     * @var SafechargeLogger
     */
    private $safechargeLogger;

    /**
     * @var PaymentRequestFactory
     */
    private $paymentRequestFactory;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var Onepage
     */
    private $onepageCheckout;

    /**
     * @var JsonFactory
     */
    private $jsonResultFactory;

    /**
     * Object constructor.
     *
     * @param Context                 $context
     * @param PaymentRequestFactory   $paymentRequestFactory
     * @param OrderFactory            $orderFactory
     * @param ModuleConfig            $moduleConfig
     * @param AuthorizeCommand        $authorizeCommand
     * @param CaptureCommand          $captureCommand
     * @param SafechargeLogger        $safechargeLogger
     * @param DataObjectFactory       $dataObjectFactory
     * @param CartManagementInterface $cartManagement
     * @param CheckoutSession         $checkoutSession
     * @param Onepage                 $onepageCheckout
     * @param JsonFactory             $jsonResultFactory
     */
    public function __construct(
        Context $context,
        PaymentRequestFactory $paymentRequestFactory,
        OrderFactory $orderFactory,
        ModuleConfig $moduleConfig,
        AuthorizeCommand $authorizeCommand,
        CaptureCommand $captureCommand,
        SafechargeLogger $safechargeLogger,
        DataObjectFactory $dataObjectFactory,
        CartManagementInterface $cartManagement,
        CheckoutSession $checkoutSession,
        Onepage $onepageCheckout,
        JsonFactory $jsonResultFactory
    ) {
	parent::__construct($context);

        $this->orderFactory = $orderFactory;
        $this->moduleConfig = $moduleConfig;
        $this->authorizeCommand = $authorizeCommand;
        $this->captureCommand = $captureCommand;
        $this->safechargeLogger = $safechargeLogger;
        $this->paymentRequestFactory = $paymentRequestFactory;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->cartManagement = $cartManagement;
        $this->checkoutSession = $checkoutSession;
        $this->onepageCheckout = $onepageCheckout;
        $this->jsonResultFactory = $jsonResultFactory;
		
		$this->execute();
    }

    /**
     * @return JsonFactory
     */
    public function execute()
    {
        if ($this->moduleConfig->isActive()) {
            try {
                $params = array_merge(
                    $this->getRequest()->getParams(),
                    $this->getRequest()->getPostValue()
                );
				
				$this->moduleConfig->createLog($params, 'DMN params:');

				/*
                if ($this->moduleConfig->isDebugEnabled()) {
                    $this->safechargeLogger->debug(
                        'DMN Params: '
                        . json_encode($params)
                    );
                }
				*/

                $this->validateChecksum($params);

                if (isset($params["merchant_unique_id"]) && $params["merchant_unique_id"]) {
                    $orderIncrementId = $params["merchant_unique_id"];
                } elseif (isset($params["order"]) && $params["order"]) {
                    $orderIncrementId = $params["order"];
                } elseif (isset($params["orderId"]) && $params["orderId"]) {
                    $orderIncrementId = $params["orderId"];
                } else {
                    $orderIncrementId = null;
                }

                $tryouts = 0;
                do {
                    $tryouts++;

                    /** @var Order $order */
                    $order = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);

                    if (!($order && $order->getId())) {
                        sleep(5);
                    }
                } while ($tryouts <=10 && !($order && $order->getId()));

                if (!($order && $order->getId())) {
                    throw new \Exception(__('Order #%1 not found!', $orderIncrementId));
                }

                /** @var OrderPayment $payment */
                $orderPayment		= $order->getPayment();
				$transaction_status = $orderPayment->getAdditionalInformation(Payment::TRANSACTION_STATUS);
				
				if(
					strtolower($transaction_status) == 'approved'
					&& $params['Status'] !== $transaction_status
				) {
					$this->moduleConfig->createLog('The last Order Status is APPROVED, the incoming is ' . $params['Status'] . '. Abort!');
					echo 'The last Order Status is APPROVED, the incoming is ' . $params['Status'] . '. Abort!';
					exit;
				}
				
                $transactionId = @$params['TransactionID'];
                $orderPayment->setAdditionalInformation(
                    Payment::TRANSACTION_ID,
                    $transactionId
                );

                if (isset($params['AuthCode']) && $params['AuthCode']) {
                    $orderPayment->setAdditionalInformation(
                        Payment::TRANSACTION_AUTH_CODE_KEY,
                        $params['AuthCode']
                    );
                }

                if (isset($params['payment_method']) && $params['AuthCode']) {
                    $orderPayment->setAdditionalInformation(
                        Payment::TRANSACTION_EXTERNAL_PAYMENT_METHOD,
                        $params['payment_method']
                    );
                }
				
				$orderPayment->setAdditionalInformation(
					Payment::TRANSACTION_STATUS,
					@$params['Status']
				);

                $orderPayment->setTransactionAdditionalInfo(
                    Transaction::RAW_DETAILS,
                    $params
                );

                $params['Status'] = $params['Status'] ?: null;
                if (in_array(strtolower($params['Status']), ['declined', 'error'])) {
                    $params['ErrCode'] = (isset($params['ErrCode'])) ? $params['ErrCode'] : "Unknown";
                    $params['ExErrCode'] = (isset($params['ExErrCode'])) ? $params['ExErrCode'] : "Unknown";
                    $order->addStatusHistoryComment("Payment returned a '{$params['Status']}' status (Code: {$params['ErrCode']}, Reason: {$params['ExErrCode']}).");
                } elseif ($params['Status']) {
                    $order->addStatusHistoryComment("Payment returned a '" . $params['Status'] . "' status");
                }

                if (strtolower($params['Status']) === "pending") {
                    $order->setState(Order::STATE_NEW)->setStatus('pending');
                }
				
				$message = '';

                if (in_array(strtolower($params['Status']), ['approved', 'success']) && $orderPayment->getAdditionalInformation(Payment::KEY_CHOSEN_APM_METHOD) !== Payment::APM_METHOD_CC) {
                    $params['transactionType'] = isset($params['transactionType']) ? $params['transactionType'] : null;
                    $invoiceTransactionId = $transactionId;
                    $transactionType = Transaction::TYPE_AUTH;
                    $isSettled = false;

                    switch (strtolower($params['transactionType'])) {
                        case 'auth':
                            if ($this->moduleConfig->getPaymentAction() === Payment::ACTION_AUTHORIZE_CAPTURE) {
                                $request = $this->paymentRequestFactory->create(
                                    AbstractRequest::PAYMENT_SETTLE_METHOD,
                                    $orderPayment,
                                    $order->getBaseGrandTotal()
                                );
                                $settleResponse = $request->process();
                                $invoiceTransactionId = $settleResponse->getTransactionId();
                                $message = $this->captureCommand->execute($orderPayment, $order->getBaseGrandTotal(), $order);
                                $transactionType = Transaction::TYPE_CAPTURE;
                                $isSettled = true;
                            } else {
                                $message = $this->authorizeCommand->execute($orderPayment, $order->getBaseGrandTotal(), $order);
                            }
                            break;
                        case 'sale':
                            if ($this->moduleConfig->getPaymentAction() === Payment::ACTION_AUTHORIZE_CAPTURE) {
                                $message = $this->captureCommand->execute($orderPayment, $order->getBaseGrandTotal(), $order);
                                $transactionType = Transaction::TYPE_CAPTURE;
                                $isSettled = true;
                            }
                            break;
                    }

                    $orderPayment
                        ->setTransactionId($transactionId)
                        ->setIsTransactionPending(false)
                        ->setIsTransactionClosed($isSettled ? 1 : 0);

                    if ($transactionType === Transaction::TYPE_CAPTURE) {
                        /** @var Invoice $invoice */
                        foreach ($order->getInvoiceCollection() as $invoice) {
                            $invoice
                                ->setTransactionId($invoiceTransactionId)
                                ->pay()
                                ->save();
                        }
                    }

                    $transaction = $orderPayment->addTransaction($transactionType);

                    $message = $orderPayment->prependMessage($message);
                    $orderPayment->addTransactionCommentsToOrder(
                        $transaction,
                        $message
                    );
                }

                $orderPayment->save();
                $order->save();
            } catch (\Exception $e) {
				/*
                if ($this->moduleConfig->isDebugEnabled()) {
                    $this->safechargeLogger->debug('DMN Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                }
				*/
				
				$this->moduleConfig->createLog($e->getMessage() . "\n" . $e->getTraceAsString(), 'DMN exception:');
				
				return $this->jsonResultFactory->create()
                    ->setHttpResponseCode(500)
                    ->setData(["error" => 1, "message" => $e->getMessage()]);
            }
        }

		/*
        if ($this->moduleConfig->isDebugEnabled()) {
            $this->safechargeLogger->debug('DMN Success for order #' . $orderIncrementId);
        }
		*/
		
		$this->moduleConfig->createLog('DMN Accepted');

        return $this->jsonResultFactory->create()
            ->setHttpResponseCode(\Magento\Framework\Webapi\Response::HTTP_OK)
            ->setData(["error" => 0, "message" => "SUCCESS"]);
    }

    private function validateChecksum($params)
    {
		/*
        if (!isset($params["advanceResponseChecksum"])) {
            throw new \Exception(
                __('Required key advanceResponseChecksum for checksum calculation is missing.')
            );
        }
		*/ 
    //    $concat = $this->moduleConfig->getMerchantSecretKey();
        foreach (['totalAmount', 'currency', 'responseTimeStamp', 'PPP_TransactionID', 'Status', 'productId'] as $checksumKey) {
            if (!isset($params[$checksumKey])) {
				$this->moduleConfig->createLog(__('Required key %1 for checksum calculation is missing.', $checksumKey), 'DMN Exception');
					
                throw new \Exception(
                    __('Required key %1 for checksum calculation is missing.', $checksumKey)
                );
            }
			/*
            if (is_array($params[$checksumKey])) {
                foreach ($params[$checksumKey] as $subKey => $subVal) {
                    $concat .= $subVal;
                }
            } else {
                $concat .= $params[$checksumKey];
            }
			*/ 
        }

		/*
        $checksum = hash('sha256', utf8_encode($concat));
        if ($params["advanceResponseChecksum"] !== $checksum) {
            throw new \Exception(
                __('Checksum validation failed!')
            );
        }
		*/
		
        return true;
    }
}
