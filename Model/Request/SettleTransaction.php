<?php

namespace Nuvei\Payments\Model\Request;

use Nuvei\Payments\Model\AbstractRequest;
use Nuvei\Payments\Model\AbstractResponse;
use Nuvei\Payments\Model\RequestInterface;
use Nuvei\Payments\Model\Payment;
use Magento\Framework\Exception\PaymentException;

class SettleTransaction extends AbstractRequest implements RequestInterface
{
    protected $config;
    protected $amount;
    protected $payment;
    
    private $invoice_id;
    
    /**
     * @param Logger           $logger
     * @param Config           $config
     * @param Curl             $curl
     * @param ResponseFactory  $responseFactory
     */
    public function __construct(
        \Nuvei\Payments\Model\Logger $logger,
        \Nuvei\Payments\Model\Config $config,
        \Nuvei\Payments\Lib\Http\Client\Curl $curl,
        \Nuvei\Payments\Model\Response\Factory $responseFactory
    ) {
        parent::__construct(
            $logger,
            $config,
            $curl,
            $responseFactory
        );

        $this->config = $config;
    }
    
    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws PaymentException
     */
    public function process()
    {
        $resp = $this->sendRequest(true);
        
        return $resp;
    }
    
    public function setInvoiceId($invoice_id)
    {
        $this->invoice_id = $invoice_id;
        
        return $this;
    }
    
    public function setInvoiceAmount($ivoice_amount)
    {
        $this->amount = $ivoice_amount;
        
        return $this;
    }
    
    public function setPayment($payment)
    {
        $this->payment = $payment;
        
        return $this;
    }
    
    /**
     * {@inheritdoc}
     *
     * @return array
     */
    protected function getParams()
    {
        $order                  = $this->payment->getOrder();
        $ord_trans_addit_info   = $this->payment->getAdditionalInformation(Payment::ORDER_TRANSACTIONS_DATA);
        $trans_to_settle        = [];
        
        $this->config->createLog($ord_trans_addit_info, 'getParams');
        
        if (!empty($ord_trans_addit_info) && is_array($ord_trans_addit_info)) {
            foreach (array_reverse($ord_trans_addit_info) as $trans) {
                if (strtolower($trans[Payment::TRANSACTION_STATUS]) == 'approved'
                    && strtolower($trans[Payment::TRANSACTION_TYPE]) == 'auth'
                ) {
                    $trans_to_settle = $trans;
                    break;
                }
            }
        }
        
        if (empty($trans_to_settle[Payment::TRANSACTION_AUTH_CODE])
            || empty($trans_to_settle[Payment::TRANSACTION_ID])
        ) {
            $msg = 'Settle Error - Missing Auth paramters.';
            
            $this->config->createLog($trans_to_settle, $msg);
            
            throw new PaymentException(__($msg));
        }
        
        $getIncrementId = $order->getIncrementId();

        $params = [
            'clientUniqueId'            => $getIncrementId,
            'amount'                    => (float) $this->amount,
            'currency'                  => $order->getBaseCurrencyCode(),
            'relatedTransactionId'      => $trans_to_settle[Payment::TRANSACTION_ID],
            'authCode'                  => $trans_to_settle[Payment::TRANSACTION_AUTH_CODE],
            'urlDetails'                => [
                'notificationUrl' => $this->config
                    ->getCallbackDmnUrl($getIncrementId, null, ['invoice_id' => $this->invoice_id]),
            ],
        ];
        
        $params = array_merge_recursive(parent::getParams(), $params);

        return $params;
    }
    
    /**
     * {@inheritdoc}
     *
     * @return array
     */
    protected function getChecksumKeys()
    {
        return [
            'merchantId',
            'merchantSiteId',
            'clientRequestId',
            'clientUniqueId',
            'amount',
            'currency',
            'relatedTransactionId',
            'authCode',
            'urlDetails',
            'timeStamp',
        ];
    }
    
    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return self::SETTLE_METHOD;
    }
    
    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return '';
    }
}
