<?php
/**
 *
 * @category    Lengow
 * @package     Lengow_Connector
 * @author      Team module <team-module@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Lengow_Connector_Adminhtml_Lengow_ProductController extends Mage_Adminhtml_Controller_Action
{

    protected function _initAction()
    {
        $this->loadLayout()->_setActiveMenu('lengowtab');
        return $this;
    }

    public function indexAction()
    {
        if ($this->getRequest()->getParam('isAjax')) {
            $action = Mage::app()->getRequest()->getParam('action');
            if ($action) {
                switch ($action) {
                    case 'change_option_selected':
                        $state = Mage::app()->getRequest()->getParam('state');
                        $shopId = Mage::app()->getRequest()->getParam('id_shop');
                        if ($state !== null) {
                            Mage::helper('lengow_connector/config')->set('selection_enable', $state, $shopId);
                            $this->getResponse()->setBody($state);
                        }
                        break;
                    case 'change_option_product_out_of_stock':
                        $shopId = Mage::app()->getRequest()->getParam('id_shop');
                        $state = Mage::app()->getRequest()->getParam('state');
                        if ($state !== null) {
                            Mage::helper('lengow_connector/config')->set('out_stock', $state, $shopId);
                        }
                        break;
                    case 'check_shop':
                        $shopId = Mage::app()->getRequest()->getParam('id_shop');
                        $checkShop = Mage::getModel('lengow/connector')->getConnectorByStore($shopId);
                        $this->getResponse()->setBody($checkShop);
                        break;

                }
            }
        } else {
            $this->_initAction()->renderLayout();
            return $this;
        }
        
    }

    /**
     * Product grid for AJAX request
     */
    public function gridAction()
    {
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('lengow/adminhtml_product_grid')->toHtml()
        );
    }

    public function massPublishAction()
    {
        $product_ids = (array)$this->getRequest()->getParam('product');
        $store_id = (integer)$this->getRequest()->getParam('store', Mage::app()->getStore()->getId());
        $publish = (integer)$this->getRequest()->getParam('publish');
        //update all attribute in one query
        $product_action = Mage::getSingleton('catalog/product_action');
        if ($store_id != 0) {
            $defaultStoreProductToUpdate = array();
            foreach ($product_ids as $product_id) {
                $lengow_product_value = Mage::getResourceModel('catalog/product')->getAttributeRawValue(
                    $product_id,
                    'lengow_product',
                    0
                );
                if ($lengow_product_value === false) {
                    $defaultStoreProductToUpdate[] = $product_id;
                }
            }
            // need to set default value if not set
            if (count($defaultStoreProductToUpdate) > 0) {
                $product_action->updateAttributes($defaultStoreProductToUpdate, array('lengow_product' => 0), 0);
            }
            if ($store_id != 0) {
                //set value for other store
                $product_action->updateAttributes($product_ids, array('lengow_product' => $publish), $store_id);
            }
        } else {
            $product_action->updateAttributes($product_ids, array('lengow_product' => $publish), $store_id);
        }
    }

    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session');
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('lengow_connector/product');
    }
}
