<?php

namespace Safecharge\Safecharge\Model\Response\Payment;

use Safecharge\Safecharge\Model\Response\AbstractPayment;
use Safecharge\Safecharge\Model\ResponseInterface;

/**
 * Safecharge Safecharge payment refund response model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Refund extends AbstractPayment implements ResponseInterface
{
    /**
     * @var int
     */
    protected $transactionId;

    /**
     * @var string
     */
    protected $authCode;

    /**
     * @return Refund
     */
    protected function processResponseData()
    {
        $body = $this->getBody();

        $this->transactionId = $body['transactionId'];
        $this->authCode = $body['authCode'];

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    protected function getRequestStatus()
    {
        if (parent::getRequestStatus() === false) {
            return false;
        }

        $body = $this->getBody();

        $responseTransactionStatus = strtolower(!empty($body['transactionStatus']) ? $body['transactionStatus'] : '');

        if ($responseTransactionStatus === 'error') {
            return false;
        }

        if ($responseTransactionStatus !== 'approved') {
            return false;
        }

        return true;
    }

    /**
     * @return int
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * @return string
     */
    public function getAuthCode()
    {
        return $this->authCode;
    }

    /**
     * @return array
     */
    protected function getRequiredResponseDataKeys()
    {
        return array_merge_recursive(
            parent::getRequiredResponseDataKeys(),
            [
                'transactionId',
                'authCode',
                'transactionStatus',
            ]
        );
    }

    /**
     * @return bool|string
     */
    protected function getErrorReason()
    {
        $body = $this->getBody();
        if (!empty($body['gwErrorReason'])) {
            return $body['gwErrorReason'];
        }

        return parent::getErrorReason();
    }
}
