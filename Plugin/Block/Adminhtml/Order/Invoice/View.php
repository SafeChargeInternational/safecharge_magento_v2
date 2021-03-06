<?php

namespace Nuvei\Payments\Plugin\Block\Adminhtml\Order\Invoice;

use Nuvei\Payments\Model\Payment;
use Magento\Sales\Model\Order\Invoice;

class View extends \Magento\Backend\Block\Widget\Form\Container
{
    private $request;
    private $invoice;
    private $config;
    private $orderRepo;
    private $searchCriteriaBuilder;

    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        Invoice $invoice,
        \Nuvei\Payments\Model\Config $config,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepo,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->request                  = $request;
        $this->invoice                  = $invoice;
        $this->config                   = $config;
        $this->orderRepo                = $orderRepo;
        $this->searchCriteriaBuilder    = $searchCriteriaBuilder;
    }

    public function beforeSetLayout(\Magento\Sales\Block\Adminhtml\Order\Invoice\View $view)
    {
        try {
            $invoiceDetails = $this->invoice->load($this->request->getParam('invoice_id'));
            $order_incr_id  = $invoiceDetails->getOrder()->getIncrementId();

            $searchCriteria = $this->
                searchCriteriaBuilder->
                addFilter('increment_id', $order_incr_id, 'eq')->create();

            $orderList = $this->orderRepo->getList($searchCriteria)->getItems();

            if (!$orderList || empty($orderList)) {
                $this->config->createLog('Modify Order Invoice buttons error - there is no $orderList');
                return;
            }

            $order                  = current($orderList);
            $orderPayment           = $order->getPayment();
            $ord_status             = $order->getStatus();
            $ord_trans_addit_info   = $orderPayment->getAdditionalInformation(Payment::ORDER_TRANSACTIONS_DATA);
            $payment_method         = '';

            if (!empty($ord_trans_addit_info) && is_array($ord_trans_addit_info)) {
                foreach ($ord_trans_addit_info as $trans) {
                    if (!empty($trans[Payment::TRANSACTION_PAYMENT_METHOD])) {
                        $payment_method = $trans[Payment::TRANSACTION_PAYMENT_METHOD];
                        break;
                    }
                }
            }

//            $payment_method    = $orderPayment->getAdditionalInformation(
//                Payment::TRANSACTION_PAYMENT_METHOD
//            );

            if ($orderPayment->getMethod() === Payment::METHOD_CODE) {
                if (!in_array($payment_method, Payment::PAYMETNS_SUPPORT_REFUND)
                    || in_array($ord_status, [Payment::SC_VOIDED, Payment::SC_PROCESSING])
                ) {
                    $view->removeButton('credit-memo');
                }

                // hide the button all the time, looks like we have order with multi partial settled items,
                // the Void logic is different than the logic of the Void button in Information tab
                if ('cc_card' !== $payment_method
                    || in_array(
                        $ord_status,
                        [Payment::SC_REFUNDED, Payment::SC_PROCESSING]
                    )
                    || $invoiceDetails->getState() == Invoice::STATE_CANCELED
                ) {
                    $view->removeButton('void');
                }
            }
        } catch (Exception $ex) {
            $this->config->createLog($ex->getMessage(), 'admin beforeSetLayout');
        }
    }
}
