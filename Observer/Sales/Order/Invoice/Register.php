<?php

namespace Nuvei\Payments\Observer\Sales\Order\Invoice;

use Nuvei\Payments\Model\Payment;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment as OrderPayment;

/**
 * Nuvei Payments sales order invoice register observer.
 *
 * Here we just set status to pending, and will wait for the DMN to confirm the payment.
 *
 */
class Register implements ObserverInterface
{
    private $config;
    
    public function __construct(\Nuvei\Payments\Model\Config $config)
    {
        $this->config = $config;
    }
    
    /**
     * Function execute
     *
     * @param Observer $observer
     * @return Register
     */
    public function execute(Observer $observer)
    {
        $this->config->createLog('Invoice Register Observer.');
        
        /** @var Order $order */
        $order = $observer->getOrder();
        
        if (!is_object($order)) {
            return $this;
        }

        /** @var OrderPayment $payment */
        $payment = $order->getPayment();
        
        if (!is_object($payment)) {
            return $this;
        }

        if ($payment->getMethod() !== Payment::METHOD_CODE) {
            $this->config->createLog($payment->getMethod(), 'Invoice Register - payment method is not Nuvei, but');
            
            return $this;
        }

        /** @var Invoice $invoice */
        $invoice    = $observer->getInvoice();
        $inv_state  = Invoice::STATE_OPEN; // in case of auth we will change it when DMN come
        
        $ord_trans_addit_info = $payment->getAdditionalInformation(Payment::ORDER_TRANSACTIONS_DATA);
        
        // in case of Sale
        if (!is_array($ord_trans_addit_info) || count($ord_trans_addit_info) < 1) {
            $inv_state  = Invoice::STATE_PAID;
        }
        
        $invoice->setState($inv_state);
        
        return $this;
    }
}
