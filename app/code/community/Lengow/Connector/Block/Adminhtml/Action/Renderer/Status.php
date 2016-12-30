<?php
/**
 * Copyright 2017 Lengow SAS.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category    Lengow
 * @package     Lengow_Connector
 * @subpackage  Block
 * @author      Team module <team-module@lengow.com>
 * @copyright   2017 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Block adminhtml action renderer status
 */
class Lengow_Connector_Block_Adminhtml_Action_Renderer_Status
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Decorate status values
     *
     * @param Varien_Object $row Magento varian object instance
     *
     * @return string
     */
    public function render(Varien_Object $row)
    {
        $status = $row->getData($this->getColumn()->getIndex());
        if ($status == 0) {
            return '<span class="lgw-label orange">'
                .Mage::helper('lengow_connector')->__('toolbox.table.state_processing').'</span>';
        } else {
            return '<span class="lgw-label">'
                .Mage::helper('lengow_connector')->__('toolbox.table.state_complete').'</span>';
        }
    }
}
