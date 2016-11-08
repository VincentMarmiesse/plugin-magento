<?php

/**
 *
 * @category    Lengow
 * @package     Lengow_Connector
 * @author      Team module <team-module@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Connector_Model_System_Config_Source_Status extends Mage_Core_Model_Config_Data
{
    /**
     * Get option array for settings
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED,
                'label' => Mage::helper('adminhtml')->__('Enabled')
            ),
            array(
                'value' => Mage_Catalog_Model_Product_Status::STATUS_DISABLED,
                'label' => Mage::helper('adminhtml')->__('Disabled')
            ),
            array(
                'value' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED
                    .','.Mage_Catalog_Model_Product_Status::STATUS_DISABLED,
                'label' => Mage::helper('adminhtml')->__('Enabled').', '.Mage::helper('adminhtml')->__('Disabled')
            ),
        );
    }
}
