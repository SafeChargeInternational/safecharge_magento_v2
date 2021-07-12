<?php

namespace Nuvei\Payments\Controller\Frontend\Order;

use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Nuvei\Payments\Model\Config;
use Magento\Framework\App\CsrfAwareActionInterface;

class SubscriptionsHistory extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    protected $resultPageFactory;
    
    private $httpRequest;
    private $jsonResultFactory;
    private $productRepository;
    private $request;
    private $configurable;
    private $helper;
    private $eavAttribute;
    private $config;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\App\Request\Http $httpRequest,
        \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\ConfigurableProduct\Model\Product\Type\Configurable $configurable,
        \Magento\Framework\Pricing\Helper\Data $helper,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute $eavAttribute,
        Config $config
    ) {
        $this->resultPageFactory    = $resultPageFactory;
        $this->httpRequest          = $httpRequest;
        $this->jsonResultFactory    = $jsonResultFactory;
        $this->productRepository    = $productRepository;
        $this->request              = $request;
        $this->configurable         = $configurable;
        $this->helper               = $helper;
        $this->eavAttribute         = $eavAttribute;
        $this->config               = $config;
        
        parent::__construct($context);
    }
    
    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
    
    /**
     * Customer order subscriptions history
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        if ($this->httpRequest->isAjax()) {
            $data = $this->getProductDetails();
            
            $jsonOutput = $this->jsonResultFactory->create();
            
            $jsonOutput->setHttpResponseCode(200);
            $jsonOutput->setData(json_encode($data));
            
            return $jsonOutput;
        }
        
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('My Nuvei Subscriptions'));

//        $block = $resultPage->getLayout()->getBlock('customer.account.link.back');
//        if ($block) {
//            $block->setRefererUrl($this->_redirect->getRefererUrl());
//        }
        return $resultPage;
    }
    
    /**
     * Get Product and Product child based on attributes combination.
     *
     * @return array
     */
    private function getProductDetails()
    {
        try {
            $params         = $this->request->getParams();
            $hash_params    = [];
            $prod_options   = []; // the final array to pass

            $this->config->createLog($params, 'SubscriptionsHistory $params');
            
            if (empty($params)
                || empty($params['prodId'])
                || !is_numeric($params['prodId'])
                || empty($params['params'])
            ) {
                return [];
            }
            
            if (is_string($params['params'])) {
                parse_str($params['params'], $hash_params);
            } else {
                $hash_params = $params['params'];
            }
            
            if (empty($hash_params)
                || !is_array($hash_params)
            ) {
                return [];
            }
            
            // sometimes the key can be the options codes, we need the IDs
            foreach ($hash_params as $key => $val) {
                if (is_numeric($key)) {
                    $prod_options[$key] = $val;
                    continue;
                }
                
                // get the option ID by its key
                $attributeId = $this->eavAttribute->getIdByCode('catalog_product', $key);
                
                if (!$attributeId) {
                    $this->config->createLog($attributeId, 'SubscriptionsHistory Error - attribute ID must be int.');
                    continue;
                }
                
                $prod_options[$attributeId] = $val;
            }
            
            if (empty($prod_options)) {
                return [];
            }
            
            $product_data = $this->config->getProductPlanData($params['prodId'], $prod_options);
            
            if (empty($product_data) || !is_array($product_data)) {
                return [];
            }
            
            $units      = [
                'day'       => __('day'),
                'days'      => __('days'),
                'month'     => __('month'),
                'months'    => __('months'),
                'year'      => __('year'),
                'years'     => __('years'),
            ];
            
            //
            $period     = current($product_data['endAfter']);
            $unit       = current(array_keys($product_data['endAfter']));
            $rec_len    = $period . ' ';

            if ($period > 1) {
                $rec_len .= $units[$unit . 's'];
            } else {
                $rec_len .= $units[$unit];
            }
            
            //
            $period     = current($product_data['recurringPeriod']);
            $unit       = current(array_keys($product_data['recurringPeriod']));
            $rec_period = __('Every') . ' ' . $period . ' ';
                
            if ($period > 1) {
                $rec_period .= $units[$unit . 's'];
            } else {
                $rec_period .= $units[$unit];
            }
            
            //
            $period         = current($product_data['startAfter']);
            $unit           = current(array_keys($product_data['startAfter']));
            $trial_period   = $period . ' ';
                
            if ($period > 1) {
                $trial_period .= $units[$unit . 's'];
            } elseif (1 == $period) {
                $trial_period .= $units[$unit];
            } else {
                $trial_period = __('None');
            }
            
            return [
                'rec_enabled'   => 1,
                'rec_len'       => $rec_len,
                'rec_period'    => $rec_period,
                'trial_period'  => $trial_period,
                'rec_amount'    => $this->helper->currency(
                    $product_data['recurringAmount'],
                    true,
                    false
                ),
            ];
        } catch (Exception $e) {
            $this->config->createLog($e->getMessage(), 'SubscriptionsHistory getProductDetails() Exception:');
            return [];
        }
    }
}
