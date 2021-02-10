<?php

namespace Nuvei\Payments\Model\Request\Payment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Nuvei\Payments\Model\AbstractRequest;
use Nuvei\Payments\Model\RequestInterface;

/**
 * Nuvei Payments payment request factory model.
 */
class Factory
{
    /**
     * Set of requests.
     *
     * @var array
     */
    private $invokableClasses = [
        AbstractRequest::PAYMENT_SETTLE_METHOD    => \Nuvei\Payments\Model\Request\Payment\Settle::class,
        AbstractRequest::PAYMENT_REFUND_METHOD  => \Nuvei\Payments\Model\Request\Payment\Refund::class,
        AbstractRequest::PAYMENT_VOID_METHOD    => \Nuvei\Payments\Model\Request\Payment\Cancel::class,
    ];

    /**
     * Object manager object.
     *
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * Construct
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Create request model.
     *
     * @param string       $method
     * @param OrderPayment $orderPayment
     * @param float        $amount
     *
     * @return RequestInterface
     * @throws LocalizedException
     */
    public function create($method, $orderPayment, $amount = 0.0)
    {
        $className = !empty($this->invokableClasses[$method])
            ? $this->invokableClasses[$method]
            : null;

        if ($className === null) {
            throw new LocalizedException(
                __('%1 method is not supported.', $method)
            );
        }

        $model = $this->objectManager->create(
            $className,
            [
                'orderPayment' => $orderPayment,
                'amount' => $amount,
            ]
        );
        
        if (!$model instanceof RequestInterface) {
            throw new LocalizedException(
                __(
                    "%1 doesn't implement \Nuvei\Payments\Mode\RequestInterface",
                    $className
                )
            );
        }

        return $model;
    }
}
