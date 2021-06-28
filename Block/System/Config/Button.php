<?php

namespace Nuvei\Payments\Block\System\Config;

use Magento\Framework\Data\Form\Element\AbstractElement;

class Button extends \Magento\Config\Block\System\Config\Form\Field
{
    protected $_template = 'Nuvei_Payments::system/config/getPlans.phtml';
 
    private $config;
    
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Nuvei\Payments\Model\Config $config,
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
            ->createBlock(\Magento\Backend\Block\Widget\Button::class)
            ->setData([
                'id'    => 'get_plans_button',
                'label' => __('Collect Plans'),
            ]);
        
        return $button->toHtml();
    }
}
