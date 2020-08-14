<?php

namespace Safecharge\Safecharge\Model;

use Magento\Framework\Exception\PaymentException;
use Safecharge\Safecharge\Lib\Http\Client\Curl;
use Safecharge\Safecharge\Model\Logger as SafechargeLogger;

/**
 * Safecharge Safecharge abstract response model.
 */
abstract class AbstractResponse extends AbstractApi
{
    /**
     * Response handlers.
     */
    const TOKEN_HANDLER                         = 'token';
    const PAYMENT_SETTLE_HANDLER                = 'payment_settle';
    const CREATE_USER_HANDLER                   = 'create_user';
    const GET_USER_DETAILS_HANDLER              = 'get_user_details';
    const PAYMENT_REFUND_HANDLER                = 'payment_refund';
    const PAYMENT_VOID_HANDLER                  = 'payment_void';
    const OPEN_ORDER_HANDLER                    = 'open_order';
    const PAYMENT_APM_HANDLER                   = 'payment_apm';
    const GET_MERCHANT_PAYMENT_METHODS_HANDLER  = 'get_merchant_payment_methods';
    const GET_UPOS_HANDLER						= 'get_user_upos';
    const GET_MERCHANT_PAYMENT_PLANS_HANDLER    = 'get_plans_list';
	const CREATE_SUBSCRIPTION_HANDLER			= 'create_subscription';

    /**
     * Response result const.
     */
    const STATUS_SUCCESS    = 1;
    const STATUS_FAILED        = 2;

    /**
     * @var int
     */
    protected $requestId;

    /**
     * @var Curl
     */
    protected $curl;

    /**
     * @var int
     */
    protected $status;

    /**
     * @var array
     */
    protected $headers;

    /**
     * @var array
     */
    protected $body;

    /**
     * AbstractResponse constructor.
     *
     * @param Logger $safechargeLogger
     * @param Config $config
     * @param int    $requestId
     * @param Curl   $curl
     */
    public function __construct(
        SafechargeLogger $safechargeLogger,
        Config $config,
        $requestId,
        Curl $curl
    ) {
        parent::__construct(
            $safechargeLogger,
            $config
        );

        $this->requestId = $requestId;
        $this->curl = $curl;
    }

    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function process()
    {
        $requestStatus    = $this->getRequestStatus();
        $resp_data        = $this->prepareResponseData();
        
        $this->config->createLog($resp_data['Body'], 'Response data:');

        if ($requestStatus === false) {
            throw new PaymentException($this->getErrorMessage(
                !empty($resp_data['Body']['reason']) ? $resp_data['Body']['reason'] : ''
            ));
        }

        $this->validateResponseData();
        
        return $this;
    }

    /**
     * @return \Magento\Framework\Phrase
     */
    protected function getErrorMessage($msg = '')
    {
        $errorReason = $this->getErrorReason();
        if ($errorReason !== false) {
            return __('Request to payment gateway failed. Details: "%1".', $errorReason);
        } elseif (!empty($msg)) {
            return __($msg);
        }
        
        return __('Request to payment gateway failed.');
    }

    /**
     * @return bool
     */
    protected function getErrorReason()
    {
        $body = $this->getBody();
        if (is_array($body) && !empty($body['gwErrorReason'])) {
            return $body['gwErrorReason'];
        }
        return false;
    }

    /**
     * Determine if request succeed or failed.
     *
     * @return bool
     */
    protected function getRequestStatus()
    {
        $httpStatus = $this->getStatus();
        
        if ($httpStatus !== 200 && $httpStatus !== 100) {
            return false;
        }

        $body = $this->getBody();

        $responseStatus                = strtolower(!empty($body['status']) ? $body['status'] : '');
        $responseTransactionStatus    = strtolower(!empty($body['transactionStatus']) ? $body['transactionStatus'] : '');
        $responseTransactionType    = strtolower(!empty($body['transactionType']) ? $body['transactionType'] : '');

        if (!(
                (
                    !in_array($responseTransactionType, ['auth', 'sale'])
                    && $responseStatus === 'success' && $responseTransactionType !== 'error'
                )
                || (
                    in_array($responseTransactionType, ['auth', 'sale'])
                    && $responseTransactionStatus === 'approved'
                )
            )
        ) {
            return false;
        }

        return true;
    }

    /**
     * @return int
     */
    protected function getRequestId()
    {
        return $this->requestId;
    }

    /**
     * @return int
     */
    protected function getStatus()
    {
        if ($this->status === null) {
            $this->status = $this->curl->getStatus();
        }

        return $this->status;
    }

    /**
     * @return array
     */
    protected function getHeaders()
    {
        if ($this->headers === null) {
            $this->headers = $this->curl->getHeaders();
        }

        return $this->headers;
    }

    /**
     * @return array
     */
    protected function getBody()
    {
        if ($this->body === null) {
            $this->body = json_decode($this->curl->getBody(), 1);
        }

        return $this->body;
    }

    /**
     * @return array
     */
    protected function prepareResponseData()
    {
        return [
            'Status'    => $this->getStatus(),
            'Headers'    => $this->getHeaders(),
            'Body'        => $this->getBody(),
        ];
    }

    /**
     * @return AbstractResponse
     * @throws PaymentException
     */
    protected function validateResponseData()
    {
        $requiredKeys = $this->getRequiredResponseDataKeys();
        $bodyKeys = array_keys($this->getBody());
        
        $diff = array_diff($requiredKeys, $bodyKeys);
        
        if (!empty($diff)) {
            $this->config->createLog($diff, 'Mising response parameters:');
            
            throw new PaymentException(
                __(
                    'Required response data fields are missing: %1.',
                    implode(', ', $diff)
                )
            );
        }

        return $this;
    }

    /**
     * @return array
     */
    protected function getRequiredResponseDataKeys()
    {
        return [
            'status',
        ];
    }
}
