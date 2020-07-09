<?php

namespace Safecharge\Safecharge\Model\Request;

use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\RequestInterface;

use Safecharge\Safecharge\Model\Logger as SafechargeLogger;
use Safecharge\Safecharge\Model\Config;
use Safecharge\Safecharge\Lib\Http\Client\Curl;

class GetPlansList extends AbstractRequest implements RequestInterface
{
    protected $requestFactory;
    
    public function __construct(
        SafechargeLogger $safechargeLogger,
        Config $config,
        Curl $curl,
        \Safecharge\Safecharge\Model\Response\Factory $responseFactory,
        \Safecharge\Safecharge\Model\Request\Factory $requestFactory
    ) {
        parent::__construct(
            $safechargeLogger,
            $config,
            $curl,
            $responseFactory
        );

        $this->requestFactory = $requestFactory;
    }
    
    public function process()
    {
        $this->sendRequest();

        return $this
            ->getResponseHandler()
            ->process();
    }
    
    protected function getRequestMethod()
    {
        return self::GET_MERCHANT_PAYMENT_PLANS_METHOD;
    }
    
    protected function getResponseHandlerType()
    {
        return \Safecharge\Safecharge\Model\AbstractResponse::GET_MERCHANT_PAYMENT_PLANS_HANDLER;
    }
    
    protected function getParams()
    {
        $params = array_merge_recursive(
            [
				'planStatus'	=> 'ACTIVE',
				'currency'		=> '',
			],
            parent::getParams()
        );
        
        return $params;
    }
    
    protected function getChecksumKeys()
    {
        return [
            'merchantId',
            'merchantSiteId',
            'currency',
            'planStatus',
            'timeStamp',
        ];
    }
}
