<?php

namespace Nuvei\Payments\Model\Request;

use Nuvei\Payments\Lib\Http\Client\Curl;
use Nuvei\Payments\Model\AbstractRequest;
use Nuvei\Payments\Model\AbstractResponse;
use Nuvei\Payments\Model\Config;
use Nuvei\Payments\Model\RequestInterface;
use Nuvei\Payments\Model\Response\Factory as ResponseFactory;
use Magento\Framework\Exception\PaymentException;
use Nuvei\Payments\Model\Request\Factory as RequestFactory;

/**
 * Nuvei Payments open order request model.
 */
class UpdateOrder extends AbstractRequest implements RequestInterface
{
    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * @var array
     */
    protected $orderData;
    
    private $billingAddress;
    private $cart;
    
    /**
     * @param Config           $config
     * @param Curl             $curl
     * @param ResponseFactory  $responseFactory
     * @param Factory          $requestFactory
     */
    public function __construct(
        \Nuvei\Payments\Model\Logger $logger,
        Config $config,
        Curl $curl,
        ResponseFactory $responseFactory,
        RequestFactory $requestFactory,
        \Magento\Checkout\Model\Cart $cart
    ) {
        parent::__construct(
            $logger,
            $config,
            $curl,
            $responseFactory
        );

        $this->requestFactory   = $requestFactory;
        $this->cart             = $cart;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return self::UPDATE_ORDER_METHOD;
    }

    /**
     * @param array $orderData
     *
     * @return OpenOrder
     */
    public function setOrderData(array $orderData)
    {
        $this->orderData = $orderData;
        return $this;
    }
    
    public function setBillingAddress($billingAddress)
    {
        $this->billingAddress = $billingAddress;
        return $this;
    }
    
    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws PaymentException
     */
    public function process()
    {
        $req_resp = $this->sendRequest(true, true);
        
        return $req_resp;
    }
    
    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::OPEN_ORDER_HANDLER;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     * @throws \Magento\Framework\Exception\PaymentException
     */
    protected function getParams()
    {
        if (null === $this->cart || empty($this->cart)) {
            $this->config->createLog('UpdateOrder Error - There is no Cart data.');
            
            throw new PaymentException(__('There is no Cart data.'));
        }
        
        // check in the cart for Nuvei Payment Plan
        $quote = $this->cart->getQuote();
        // iterate over Items and search for Subscriptions
        $items_data = $this->config->getProductPlanData();
        
        $this->config->setNuveiUseCcOnly(!empty($items_data['subs_data']) ? true : false);
        
        $currency = $quote->getOrderCurrencyCode();
        if (empty($currency)) {
            $currency = $quote->getStoreCurrencyCode();
        }
        if (empty($currency)) {
            $currency = $this->config->getQuoteBaseCurrency();
        }
        
        $billing_address = $this->config->getQuoteBillingAddress();
        
        if (!empty($this->billingAddress)) {
            $billing_address['firstName']   = $this->billingAddress['firstname'] ?: $billing_address['firstName'];
            $billing_address['lastName']    = $this->billingAddress['lastname'] ?: $billing_address['lastName'];
            
            if (is_array($this->billingAddress['street']) && !empty($this->billingAddress['street'])) {
                $billing_address['address'] = implode(' ', $this->billingAddress['street']);
            }
            
            $billing_address['phone']   = $this->billingAddress['telephone'] ?: $billing_address['phone'];
            $billing_address['zip']     = $this->billingAddress['postcode'] ?: $billing_address['zip'];
            $billing_address['city']    = $this->billingAddress['city'] ?: $billing_address['city'];
            $billing_address['country'] = $this->billingAddress['countryId'] ?: $billing_address['country'];
        }
        
        $params = array_merge_recursive(
            parent::getParams(),
            [
                'currency'          => $currency,
                'amount'            => (string) number_format($quote->getGrandTotal(), 2, '.', ''),
                'billingAddress'    => $billing_address,
                'shippingAddress'   => $this->config->getQuoteShippingAddress(),
                
                'items'             => [[
                    'name'      => 'magento_order',
                    'price'     => (string) number_format($quote->getGrandTotal(), 2, '.', ''),
                    'quantity'  => 1,
                ]],
                
                'merchantDetails'   => [
                    // pass amount
                    'customField1'  => (string) number_format($quote->getGrandTotal(), 2, '.', ''),
                    // subscription data
                    'customField2'  => isset($items_data['subs_data'])
                        ? json_encode($items_data['subs_data']) : '',
                    # customField3 is passed in AbstractRequest
                    // time when we create the request
                    'customField4'  => time(),
                    // list of Order items
                    'customField5'  => isset($items_data['items_data'])
                        ? json_encode($items_data['items_data']) : '',
                ],
            ]
        );
        
        $params['userDetails']      = $params['billingAddress'];
        $params['sessionToken']     = $this->orderData['sessionToken'];
        $params['orderId']          = isset($this->orderData['orderId']) ? $this->orderData['orderId'] : '';
        $params['clientRequestId']  = isset($this->orderData['clientRequestId']) ? $this->orderData['clientRequestId'] : '';
        
        // for rebilling
        if(!empty($this->config->getProductPlanData())) {
            $params['isRebilling'] = 0;
            $params['paymentOption']['card']['threeD']['rebillFrequency']   = 1;
            $params['paymentOption']['card']['threeD']['rebillExpiry']      
                = date('Ymd', strtotime("+10 years"));
        } else { // for normal transaction
            $params['isRebilling'] = 1;
            $params['paymentOption']['card']['threeD']['rebillExpiry']      = date('Ymd', time());
            $params['paymentOption']['card']['threeD']['rebillFrequency']   = 0;
        }
        
        $params['checksum'] = hash(
            $this->config->getHash(),
            $this->config->getMerchantId() . $this->config->getMerchantSiteId() . $params['clientRequestId']
                . $params['amount'] . $params['currency'] . $params['timeStamp'] . $this->config->getMerchantSecretKey()
        );
        
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
            'amount',
            'currency',
            'timeStamp',
        ];
    }
    
    /**
     * Get attribute options
     *
     * @param \Magento\Eav\Api\Data\AttributeInterface $attribute
     * @return array
     */
    private function getOptions(\Magento\Eav\Api\Data\AttributeInterface $attribute) : array
    {
        $return = [];

        $options = $attribute->getOptions();
        foreach ($options as $option) {
            if ($option->getValue()) {
                $return[] = [
                    'value' => $option->getLabel(),
                    'label' => $option->getLabel(),
                    'parentAttributeLabel' => $attribute->getDefaultFrontendLabel()
                ];
            }
        }

        return $return;
    }
}
