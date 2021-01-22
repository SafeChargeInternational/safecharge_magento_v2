<?php

namespace Nuvei\Payments\Model;

use Magento\Customer\Model\Session as CustomerSession;
//use Magento\Framework\Exception\PaymentException;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\CcConfig;
use Magento\Payment\Model\CcGenericConfigProvider;
//use Magento\Vault\Api\PaymentTokenManagementInterface;
use Nuvei\Payments\Model\Config as ModuleConfig;
use Nuvei\Payments\Model\Request\Factory as RequestFactory;

/**
 * Nuvei Payments config provider model.
 */
class ConfigProvider extends CcGenericConfigProvider
{
    /**
     * @var Config
     */
    private $moduleConfig;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var PaymentTokenManagementInterface
     */
//    private $paymentTokenManagement;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var RequestFactory
     */
    private $requestFactory;
    
    private $apmsRequest;
    private $storeManager;
    private $scopeConfig;
    private $cart;

    /**
     * ConfigProvider constructor.
     *
     * @param CcConfig                        $ccConfig
     * @param PaymentHelper                   $paymentHelper
     * @param Config                          $moduleConfig
     * @param CustomerSession                 $customerSession
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param UrlInterface                    $urlBuilder
     * @param RequestFactory                  $requestFactory
     * @param array                           $methodCodes
     */
    public function __construct(
        CcConfig $ccConfig,
        PaymentHelper $paymentHelper,
        ModuleConfig $moduleConfig,
        CustomerSession $customerSession,
//        PaymentTokenManagementInterface $paymentTokenManagement,
        UrlInterface $urlBuilder,
        RequestFactory $requestFactory,
        array $methodCodes,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Cart $cart
    ) {
        $this->moduleConfig                = $moduleConfig;
        $this->customerSession            = $customerSession;
//        $this->paymentTokenManagement    = $paymentTokenManagement;
        $this->urlBuilder                = $urlBuilder;
        $this->requestFactory            = $requestFactory;
        $this->storeManager                = $storeManager;
        $this->scopeConfig                = $scopeConfig;
        $this->cart                        = $cart;

        $methodCodes = array_merge_recursive(
            $methodCodes,
            [Payment::METHOD_CODE]
        );

        parent::__construct(
            $ccConfig,
            $paymentHelper,
            $methodCodes
        );
    }

    /**
     * Return config array.
     *
     * @return array
     */
    public function getConfig()
    {
        if (!$this->moduleConfig->isActive()) {
            return [];
        }
        
        $objectManager  = \Magento\Framework\App\ObjectManager::getInstance();
        $locale            = $this->scopeConfig->getValue(
            'general/locale/code',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        
        $config = [
            'payment' => [
                Payment::METHOD_CODE => [
                    'countryId'                     => $this->moduleConfig->getQuoteCountryCode(),
                    'redirectUrl'                   => $this->urlBuilder->getUrl('nuvei_payments/payment/redirect'),
                    'paymentApmUrl'                 => $this->urlBuilder->getUrl('nuvei_payments/payment/apm'),
                    'getMerchantPaymentMethodsUrl'  => $this->urlBuilder->getUrl('nuvei_payments/payment/GetMerchantPaymentMethods'),
                    'getUPOsUrl'					=> $this->urlBuilder->getUrl('nuvei_payments/payment/GetUpos'),
					'getUpdateOrderUrl'				=> $this->urlBuilder->getUrl('nuvei_payments/payment/OpenOrder'),
                    'updateQuotePM'					=> $this->urlBuilder->getUrl('nuvei_payments/payment/UpdateQuotePaymentMethod'),
                    'useUPOs'						=> $this->moduleConfig->useUPOs(),
                    // we need this for the WebSDK
                    'merchantSiteId'                => $this->moduleConfig->getMerchantSiteId(),
                    'merchantId'                    => $this->moduleConfig->getMerchantId(),
                    'isTestMode'                    => $this->moduleConfig->isTestModeEnabled(),
                    'locale'                        => substr($locale, 0, 2),
                    'total'                         => (string) number_format($this->cart->getQuote()->getGrandTotal(), 2, '.', ''),
                    'currency'                      => trim($this->storeManager->getStore()->getCurrentCurrencyCode()),
                    'webMasterId'                   => $this->moduleConfig->getSourcePlatformField(),
                    'sourceApplication'             => $this->moduleConfig->getSourceApplication(),
                ],
            ],
        ];

        return $config;
    }
}
