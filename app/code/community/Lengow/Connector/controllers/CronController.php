<?php
/**
 * Copyright 2017 Lengow SAS
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
 * @subpackage  controllers
 * @author      Team module <team-module@lengow.com>
 * @copyright   2017 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * CronController
 */
class Lengow_Connector_CronController extends Mage_Core_Controller_Front_Action
{
    /**
     * Cron Process (Import orders, check actions and send stats)
     */
    public function indexAction()
    {
        /**
         * List params
         * string  sync                Number of products exported
         * integer days                Import period
         * integer limit               Number of orders to import
         * integer store_id            Store id to import
         * string  marketplace_sku     Lengow marketplace order id to import
         * string  marketplace_name    Lengow marketplace name to import
         * string  created_from        import of orders since
         * string  created_to          import of orders until
         * integer delivery_address_id Lengow delivery address id to import
         * boolean debug_mode          Activate debug mode
         * boolean log_output          See logs (1) or not (0)
         * boolean get_sync            See synchronisation parameters in json format (1) or not (0)
         */
        $token = $this->getRequest()->getParam('token');
        /** @var Lengow_Connector_Helper_Security $securityHelper */
        $securityHelper = Mage::helper('lengow_connector/security');
        /** @var Lengow_Connector_Helper_Data $helper */
        $helper = Mage::helper('lengow_connector');
        if ($securityHelper->checkWebserviceAccess($token)) {
            /** @var Lengow_Connector_Helper_Sync $syncHelper */
            $syncHelper = Mage::helper('lengow_connector/sync');
            // get all store data for synchronisation with Lengow
            if ($this->getRequest()->getParam('get_sync') == 1) {
                $storeData = $syncHelper->getSyncData();
                $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($storeData));
            } else {
                $force = $this->getRequest()->getParam('force') !== null
                    ? (bool)$this->getRequest()->getParam('force')
                    : false;
                $logOutput = $this->getRequest()->getParam('log_output') !== null
                    ? (bool)$this->getRequest()->getParam('log_output')
                    : false;
                // get sync action if exists
                $sync = $this->getRequest()->getParam('sync');
                // sync catalogs id between Lengow and Magento
                if (!$sync || $sync === Lengow_Connector_Helper_Sync::SYNC_CATALOG) {
                    $syncHelper->syncCatalog($force, $logOutput);
                }
                // sync orders between Lengow and Magento
                if ($sync === null || $sync === Lengow_Connector_Helper_Sync::SYNC_ORDER) {
                    // array of params for import order
                    $params = array(
                        'type' => Lengow_Connector_Model_Import::TYPE_CRON,
                        'log_output' => $logOutput,
                    );
                    // check if the GET parameters are available
                    if ($this->getRequest()->getParam('debug_mode') !== null) {
                        $params['debug_mode'] = (bool)$this->getRequest()->getParam('debug_mode');
                    }
                    if ($this->getRequest()->getParam('days') !== null) {
                        $params['days'] = (int)$this->getRequest()->getParam('days');
                    }
                    if ($this->getRequest()->getParam('created_from') !== null) {
                        $params['created_from'] = (string)$this->getRequest()->getParam('created_from');
                    }
                    if ($this->getRequest()->getParam('created_to') !== null) {
                        $params['created_to'] = (string)$this->getRequest()->getParam('created_to');
                    }
                    if ($this->getRequest()->getParam('limit') !== null) {
                        $params['limit'] = (int)$this->getRequest()->getParam('limit');
                    }
                    if ($this->getRequest()->getParam('marketplace_sku') !== null) {
                        $params['marketplace_sku'] = (string)$this->getRequest()->getParam('marketplace_sku');
                    }
                    if ($this->getRequest()->getParam('marketplace_name') !== null) {
                        $params['marketplace_name'] = (string)$this->getRequest()->getParam('marketplace_name');
                    }
                    if ($this->getRequest()->getParam('delivery_address_id') !== null) {
                        $params['delivery_address_id'] = (int)$this->getRequest()->getParam('delivery_address_id');
                    }
                    if ($this->getRequest()->getParam('store_id') !== null) {
                        $params['store_id'] = (int)$this->getRequest()->getParam('store_id');
                    }
                    // synchronise orders
                    /** @var Lengow_Connector_Model_Import $import */
                    $import = Mage::getModel('lengow/import', $params);
                    $import->exec();
                }
                // sync action between Lengow and Magento
                if ($sync === null || $sync === Lengow_Connector_Helper_Sync::SYNC_ACTION) {
                    /** @var Lengow_Connector_Model_Import_Action $action */
                    $action = Mage::getModel('lengow/import_action');
                    $action->checkFinishAction($logOutput);
                    $action->checkOldAction($logOutput);
                    $action->checkActionNotSent($logOutput);
                }
                // sync options between Lengow and Magento
                if ($sync === null || $sync === Lengow_Connector_Helper_Sync::SYNC_CMS_OPTION) {
                    $syncHelper->setCmsOption($force, $logOutput);
                }
                // sync marketplaces between Lengow and Magento
                if ($sync === Lengow_Connector_Helper_Sync::SYNC_MARKETPLACE) {
                    $syncHelper->getMarketplaces($force, $logOutput);
                }
                // sync status account between Lengow and Magento
                if ($sync === Lengow_Connector_Helper_Sync::SYNC_STATUS_ACCOUNT) {
                    $syncHelper->getStatusAccount($force, $logOutput);
                }
                // sync plugin data between Lengow and Magento
                if ($sync === Lengow_Connector_Helper_Sync::SYNC_PLUGIN_DATA) {
                    $syncHelper->getPluginData($force, $logOutput);
                }
                // sync option is not valid
                if ($sync && !$syncHelper->isSyncAction($sync)) {
                    $this->getResponse()->setHeader('HTTP/1.1', '400 Bad Request');
                    $this->getResponse()->setBody(
                        $helper->__('log.import.not_valid_action', array('action' => $sync))
                    );
                }
            }
        } else {
            if ((bool)Mage::helper('lengow_connector/config')->get('ip_enable')) {
                $errorMessage = $helper->__(
                    'log.export.unauthorised_ip',
                    array('ip' => $securityHelper->getRemoteIp())
                );
            } else {
                $errorMessage = strlen($token) > 0
                    ? $helper->__('log.export.unauthorised_token', array('token' => $token))
                    : $helper->__('log.export.empty_token');
            }
            $this->getResponse()->setHeader('HTTP/1.1', '403 Forbidden');
            $this->getResponse()->setBody($errorMessage);
        }
    }
}
