<?php

/**
 *
 * @category    Lengow
 * @package     Lengow_Connector
 * @author      Team module <team-module@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Lengow_Connector_Block_Adminhtml_Product_Renderer_Lengow extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {
        $value =  $row->getData($this->getColumn()->getIndex());
        if ($value==1) {
            $value = $this->__('Yes');
            $class = 'green';
        } else {
            $value = $this->__('No');
            $class = 'red';
        }
        return '<span class="publish_on_lengow '.$class.'">'.$value.'</span>';
    }
}