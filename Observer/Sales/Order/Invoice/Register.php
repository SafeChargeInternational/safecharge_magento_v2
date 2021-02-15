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
            $this->config->createLog($payment->getMethod(), 'Invoice Register Observer Error - payment method is');
            
            return $this;
        }

        /** @var Invoice $invoice */
        $invoice = $observer->getInvoice();
        $invoice->setState(Invoice::STATE_OPEN); // we will set it to Paid when get the DMN
        
//        $this->config->createLog(@$invoice->getId());
//        $this->config->createLog(@$invoice->getState());

//        if ($invoice->getState() !== Invoice::STATE_PAID) {
//            $this->config->createLog($invoice->getState(), 'Invoice Register Observer Error - $invoice state is');
//
//            return $this;
//        }

        /*
        $status = Payment::SC_SETTLED;

        $totalDue = $order->getBaseTotalDue();
        if ((float)$totalDue > 0.0) {
            $status = Payment::SC_PARTIALLY_SETTLED;
        }

        $order->setStatus($status);
         */

        return $this;
    }
}
