<?php

namespace Nuvei\Payments\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Nuvei\Payments\Model\Config;

class Button extends \Magento\Config\Block\System\Config\Form\Field
{
    protected $_template = 'Nuvei_Payments::system/config/getPlans.phtml';
 
    private $config;
    
    public function __construct(
        Context $context,
		Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
        
        $this->config = $config;
    }
 
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }
    
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }
    
    public function getAjaxUrl()
    {
        return $this->getUrl('nuvei_payments/system_config/getPlans');
    }
    
    public function getButtonHtml()
    {
        $button = $this->getLayout()
            ->createBlock('Magento\Backend\Block\Widget\Button')
            ->setData([
                'id' => 'get_plans_button',
                'label' => __('Collect Plans'),
            ]);
        
        return $button->toHtml();
    }
}
