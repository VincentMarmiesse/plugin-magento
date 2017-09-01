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
 * @subpackage  Model
 * @author      Team module <team-module@lengow.com>
 * @copyright   2017 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Model import importorder
 */
class Lengow_Connector_Model_Import_Importorder extends Varien_Object
{
    /**
     * @var Lengow_Connector_Helper_Data Lengow helper instance
     */
    protected $_helper = null;

    /**
     * @var Lengow_Connector_Helper_Import Lengow import helper instance
     */
    protected $_importHelper = null;

    /**
     * @var Lengow_Connector_Helper_Config Lengow config helper instance
     */
    protected $_config = null;

    /**
     * @var Lengow_Connector_Model_Import_Order Lengow import order instance
     */
    protected $_modelOrder = null;

    /**
     * @var integer Magento store id
     */
    protected $_storeId = null;

    /**
     * @var boolean use preprod mode
     */
    protected $_preprodMode = false;

    /**
     * @var boolean display log messages
     */
    protected $_logOutput = false;

    /**
     * @var Lengow_Connector_Model_Import_Marketplace Lengow marketplace instance
     */
    protected $_marketplace;

    /**
     * @var string id lengow of current order
     */
    protected $_marketplaceSku;

    /**
     * @var string marketplace label
     */
    protected $_marketplaceLabel;

    /**
     * @var integer id of delivery address for current order
     */
    protected $_deliveryAddressId;

    /**
     * @var mixed order data
     */
    protected $_orderData;

    /**
     * @var mixed package data
     */
    protected $_packageData;

    /**
     * @var boolean is first package
     */
    protected $_firstPackage;

    /**
     * @var boolean re-import order
     */
    protected $_isReimported = false;

    /**
     * @var integer id of the record Lengow order table
     */
    protected $_orderLengowId;

    /**
     * @var string marketplace order state
     */
    protected $_orderStateMarketplace;

    /**
     * @var string Lengow order state
     */
    protected $_orderStateLengow;

    /**
     * @var float order processing fees
     */
    protected $_processingFee;

    /**
     * @var float order shipping costs
     */
    protected $_shippingCost;

    /**
     * @var float order amount
     */
    protected $_orderAmount;

    /**
     * @var integer order items
     */
    protected $_orderItems;

    /**
     * @var string carrier name
     */
    protected $_carrierName = null;

    /**
     * @var string carrier method
     */
    protected $_carrierMethod = null;

    /**
     * @var string carrier tracking number
     */
    protected $_trackingNumber = null;

    /**
     * @var boolean order shipped by marketplace
     */
    protected $_shippedByMp = false;

    /**
     * @var string carrier relay id
     */
    protected $_relayId = null;

    /**
     * Construct the import order manager
     *
     * @param array $params optional options
     * integer $store_id            Id store for current order
     * boolean $preprod_mode        preprod mode
     * boolean $log_output          display log messages
     * boolean $marketplace_sku     marketplace sku
     * boolean $delivery_address_id delivery address id
     * boolean $order_data          order data
     * boolean $package_data        package data
     * boolean $first_package       first package
     * boolean $import_helper       import helper
     */
    public function __construct($params = array())
    {
        // get params
        $this->_storeId = $params['store_id'];
        $this->_preprodMode = $params['preprod_mode'];
        $this->_logOutput = $params['log_output'];
        $this->_marketplaceSku = $params['marketplace_sku'];
        $this->_deliveryAddressId = $params['delivery_address_id'];
        $this->_orderData = $params['order_data'];
        $this->_packageData = $params['package_data'];
        $this->_firstPackage = $params['first_package'];
        $this->_importHelper = $params['import_helper'];
        // get helpers
        $this->_helper = Mage::helper('lengow_connector/data');
        $this->_config = Mage::helper('lengow_connector/config');
        $this->_modelOrder = Mage::getModel('lengow/import_order');
        // get marketplace and Lengow order state
        $this->_marketplace = $this->_importHelper->getMarketplaceSingleton((string)$this->_orderData->marketplace);
        $this->_marketplaceLabel = $this->_marketplace->labelName;
        $this->_orderStateMarketplace = (string)$this->_orderData->marketplace_status;
        $this->_orderStateLengow = $this->_marketplace->getStateLengow($this->_orderStateMarketplace);
    }

    /**
     * Create or update order
     *
     * @throws Lengow_Connector_Model_Exception order is empty
     *
     * @return array|false
     */
    public function importOrder()
    {
        // if log import exist and not finished
        $importLog = $this->_modelOrder->orderIsInError(
            $this->_marketplaceSku,
            $this->_deliveryAddressId,
            'import'
        );
        if ($importLog) {
            $decodedMessage = $this->_helper->decodeLogMessage($importLog['message'], 'en_GB');
            $this->_helper->log(
                'Import',
                $this->_helper->setLogMessage(
                    'log.import.error_already_created',
                    array(
                        'decoded_message' => $decodedMessage,
                        'date_message' => $importLog['created_at']
                    )
                ),
                $this->_logOutput,
                $this->_marketplaceSku
            );
            return false;
        }
        // recovery id if the command has already been imported
        $orderId = $this->_modelOrder->getOrderIdIfExist(
            $this->_marketplaceSku,
            $this->_marketplace->name,
            $this->_deliveryAddressId,
            $this->_marketplace->legacyCode
        );
        // update order state if already imported
        if ($orderId) {
            $orderUpdated = $this->_checkAndUpdateOrder($orderId);
            if ($orderUpdated && isset($orderUpdated['update'])) {
                return $this->_returnResult('update', $orderUpdated['order_lengow_id'], $orderId);
            }
            if (!$this->_isReimported) {
                return false;
            }
        }
        // // checks if an external id already exists
        $orderMagentoId = $this->_checkExternalIds($this->_orderData->merchant_order_id);
        if ($orderMagentoId && !$this->_preprodMode && !$this->_isReimported) {
            $this->_helper->log(
                'Import',
                $this->_helper->setLogMessage(
                    'log.import.external_id_exist',
                    array('order_id' => $orderMagentoId)
                ),
                $this->_logOutput,
                $this->_marketplaceSku
            );
            return false;
        }
        // if order is cancelled or new -> skip
        if (!$this->_importHelper->checkState($this->_orderStateMarketplace, $this->_marketplace)) {
            $this->_helper->log(
                'Import',
                $this->_helper->setLogMessage(
                    'log.import.current_order_state_unavailable',
                    array(
                        'order_state_marketplace' => $this->_orderStateMarketplace,
                        'marketplace_name' => $this->_marketplace->name
                    )
                ),
                $this->_logOutput,
                $this->_marketplaceSku
            );
            return false;
        }
        // get a record in the lengow order table
        $this->_orderLengowId = $this->_modelOrder->getLengowOrderId(
            $this->_marketplaceSku,
            $this->_deliveryAddressId
        );
        if (!$this->_orderLengowId) {
            // created a record in the lengow order table
            if (!$this->_createLengowOrder()) {
                $this->_helper->log(
                    'Import',
                    $this->_helper->setLogMessage('log.import.lengow_order_not_saved'),
                    $this->_logOutput,
                    $this->_marketplaceSku
                );
                return false;
            } else {
                $this->_helper->log(
                    'Import',
                    $this->_helper->setLogMessage('log.import.lengow_order_saved'),
                    $this->_logOutput,
                    $this->_marketplaceSku
                );
            }
        }
        // load lengow order
        $orderLengow = $this->_modelOrder->load((int)$this->_orderLengowId);
        // checks if the required order data is present
        if (!$this->_checkOrderData()) {
            return $this->_returnResult('error', $this->_orderLengowId);
        }
        // get order amount and load processing fees and shipping cost
        $this->_orderAmount = $this->_getOrderAmount();
        // load tracking data
        $this->_loadTrackingData();
        // get customer name and email
        $customerName = $this->_getCustomerName();
        $customerEmail = (!is_null($this->_orderData->billing_address->email)
            ? (string)$this->_orderData->billing_address->email
            : (string)$this->_packageData->delivery->email
        );
        // update Lengow order with new informations
        $orderLengow->updateOrder(
            array(
                'currency' => $this->_orderData->currency->iso_a3,
                'total_paid' => $this->_orderAmount,
                'order_item' => $this->_orderItems,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'commission' => (float)$this->_orderData->commission,
                'carrier' => $this->_carrierName,
                'method' => $this->_carrierMethod,
                'tracking' => $this->_trackingNumber,
                'sent_marketplace' => $this->_shippedByMp,
                'delivery_country_iso' => $this->_packageData->delivery->common_country_iso_a2,
                'order_lengow_state' => $this->_orderStateLengow
            )
        );
        // try to import order
        try {
            // check if the order is shipped by marketplace
            if ($this->_shippedByMp) {
                $this->_helper->log(
                    'Import',
                    $this->_helper->setLogMessage(
                        'log.import.order_shipped_by_marketplace',
                        array('marketplace_name' => $this->_marketplace->name)
                    ),
                    $this->_logOutput,
                    $this->_marketplaceSku
                );
                if (!$this->_config->get('import_ship_mp_enabled', $this->_storeId)) {
                    $orderLengow->updateOrder(
                        array(
                            'order_process_state' => 2,
                            'extra' => Mage::helper('core')->jsonEncode($this->_orderData)
                        )
                    );
                    return false;
                }
            }
            // Create or Update customer with addresses
            $customer = Mage::getModel('lengow/import_customer');
            $customer->createCustomer(
                $this->_orderData,
                $this->_packageData->delivery,
                $this->_storeId,
                $this->_marketplaceSku,
                $this->_logOutput
            );
            // Create Magento Quote
            $quote = $this->_createQuote($customer);
            // Create Magento order
            $order = $this->_makeOrder($quote);
            // If order is succesfully imported
            if ($order) {
                // Save order line id in lengow_order_line table
                $orderLineSaved = $this->_saveLengowOrderLine($order, $quote);
                $this->_helper->log(
                    'Import',
                    $this->_helper->setLogMessage(
                        'log.import.lengow_order_line_saved',
                        array('order_line_saved' => $orderLineSaved)
                    ),
                    $this->_logOutput,
                    $this->_marketplaceSku
                );
                $this->_helper->log(
                    'Import',
                    $this->_helper->setLogMessage(
                        'log.import.order_successfully_imported',
                        array('order_id' => $order->getIncrementId())
                    ),
                    $this->_logOutput,
                    $this->_marketplaceSku
                );
                // Update state to shipped
                if ($this->_orderStateLengow == 'shipped' || $this->_orderStateLengow == 'closed') {
                    $this->_modelOrder->toShip(
                        $order,
                        $this->_carrierName,
                        $this->_carrierMethod,
                        $this->_trackingNumber
                    );
                    $this->_helper->log(
                        'Import',
                        $this->_helper->setLogMessage(
                            'log.import.order_state_updated',
                            array('state_name' => 'Complete')
                        ),
                        $this->_logOutput,
                        $this->_marketplaceSku
                    );
                }
                // Update Lengow order record
                $orderLengow->updateOrder(
                    array(
                        'order_id' => $order->getId(),
                        'order_sku' => $order->getIncrementId(),
                        'order_process_state' => $this->_modelOrder->getOrderProcessState($this->_orderStateLengow),
                        'extra' => Mage::helper('core')->jsonEncode($this->_orderData),
                        'order_lengow_state' => $this->_orderStateLengow,
                        'is_in_error' => 0
                    )
                );
                $this->_helper->log(
                    'Import',
                    $this->_helper->setLogMessage('log.import.lengow_order_updated'),
                    $this->_logOutput,
                    $this->_marketplaceSku
                );
            } else {
                throw new Lengow_Connector_Model_Exception(
                    $this->_helper->setLogMessage('lengow_log.exception.order_is_empty')
                );
            }
            // add quantity back for re-import order and order shipped by marketplace
            if ($this->_isReimported
                || ($this->_shippedByMp && !$this->_config->get('import_stock_ship_mp', $this->_storeId))
            ) {
                if ($this->_isReimported) {
                    $logMessage = $this->_helper->setLogMessage('log.import.quantity_back_reimported_order');
                } else {
                    $logMessage = $this->_helper->setLogMessage('log.import.quantity_back_shipped_by_marketplace');
                }
                $this->_helper->log('Import', $logMessage, $this->_logOutput, $this->_marketplaceSku);
                $this->_addQuantityBack($quote);
            }
            // Inactivate quote (Test)
            $quote->setIsActive(false)->save();
        } catch (Lengow_Connector_Model_Exception $e) {
            $errorMessage = $e->getMessage();
        } catch (Exception $e) {
            $errorMessage = '[Magento error]: "' . $e->getMessage() . '" ' . $e->getFile() . ' line ' . $e->getLine();
        }
        if (isset($errorMessage)) {
            $orderError = Mage::getModel('lengow/import_ordererror');
            $orderError->createOrderError(
                array(
                    'order_lengow_id' => $this->_orderLengowId,
                    'message' => $errorMessage,
                    'type' => 'import'
                )
            );
            $decodedMessage = $this->_helper->decodeLogMessage($errorMessage, 'en_GB');
            $this->_helper->log(
                'Import',
                $this->_helper->setLogMessage(
                    'log.import.order_import_failed',
                    array('decoded_message' => $decodedMessage)
                ),
                $this->_logOutput,
                $this->_marketplaceSku
            );
            $orderLengow->updateOrder(
                array(
                    'extra' => Mage::helper('core')->jsonEncode($this->_orderData),
                    'order_lengow_state' => $this->_orderStateLengow,
                )
            );
            return $this->_returnResult('error', $this->_orderLengowId);
        }
        return $this->_returnResult('new', $this->_orderLengowId, $order->getId());
    }

    /**
     * Return an array of result for each order
     *
     * @param string $typeResult Type of result (new, update, error)
     * @param integer $orderLengowId Lengow order id
     * @param integer $orderId Magento order id
     *
     * @return array
     */
    protected function _returnResult($typeResult, $orderLengowId, $orderId = null)
    {
        $result = array(
            'order_id' => $orderId,
            'order_lengow_id' => $orderLengowId,
            'marketplace_sku' => $this->_marketplaceSku,
            'marketplace_name' => (string)$this->_marketplace->name,
            'lengow_state' => $this->_orderStateLengow,
            'order_new' => ($typeResult == 'new' ? true : false),
            'order_update' => ($typeResult == 'update' ? true : false),
            'order_error' => ($typeResult == 'error' ? true : false)
        );
        return $result;
    }

    /**
     * Check the command and updates data if necessary
     *
     * @param integer $orderId Magento order id
     *
     * @return array|false
     */
    protected function _checkAndUpdateOrder($orderId)
    {
        $order = Mage::getModel('sales/order')->load($orderId);
        $this->_helper->log(
            'Import',
            $this->_helper->setLogMessage(
                'log.import.order_already_imported',
                array('order_id' => $order->getIncrementId())
            ),
            $this->_logOutput,
            $this->_marketplaceSku
        );
        $orderLengowId = $this->_modelOrder->getLengowOrderIdWithOrderId($orderId);
        $result = array('order_lengow_id' => $orderLengowId);
        // Lengow -> Cancel and reimport order
        if ($order->getData('is_reimported_lengow') == 1) {
            $this->_helper->log(
                'Import',
                $this->_helper->setLogMessage(
                    'log.import.order_ready_to_reimport',
                    array('order_id' => $order->getIncrementId())
                ),
                $this->_logOutput,
                $this->_marketplaceSku
            );
            $this->_isReimported = true;
            return false;
        } else {
            // try to update magento order, lengow order and finish actions if necessary
            $orderUpdated = $this->_modelOrder->updateState(
                $order,
                $this->_orderStateLengow,
                $this->_orderData,
                $this->_packageData,
                $orderLengowId
            );
            if ($orderUpdated) {
                $result['update'] = true;
                $this->_helper->log(
                    'Import',
                    $this->_helper->setLogMessage(
                        'log.import.order_state_updated',
                        array('state_name' => $orderUpdated)
                    ),
                    $this->_logOutput,
                    $this->_marketplaceSku
                );
            }
        }
        unset($order);
        return $result;
    }

    /**
     * Checks if order data are present
     *
     * @return boolean
     */
    protected function _checkOrderData()
    {
        $errorMessages = array();
        if (count($this->_packageData->cart) == 0) {
            $errorMessages[] = $this->_helper->setLogMessage('lengow_log.error.no_product');
        }
        if (!isset($this->_orderData->currency->iso_a3)) {
            $errorMessages[] = $this->_helper->setLogMessage('lengow_log.error.no_currency');
        }
        if ($this->_orderData->total_order == -1) {
            $errorMessages[] = $this->_helper->setLogMessage('lengow_log.error.no_change_rate');
        }
        if (is_null($this->_orderData->billing_address)) {
            $errorMessages[] = $this->_helper->setLogMessage('lengow_log.error.no_billing_address');
        } elseif (is_null($this->_orderData->billing_address->common_country_iso_a2)) {
            $errorMessages[] = $this->_helper->setLogMessage('lengow_log.error.no_country_for_billing_address');
        }
        if (is_null($this->_packageData->delivery->common_country_iso_a2)) {
            $errorMessages[] = $this->_helper->setLogMessage('lengow_log.error.no_country_for_delivery_address');
        }
        if (count($errorMessages) > 0) {
            foreach ($errorMessages as $errorMessage) {
                $orderError = Mage::getModel('lengow/import_ordererror');
                $orderError->createOrderError(
                    array(
                        'order_lengow_id' => $this->_orderLengowId,
                        'message' => $errorMessage,
                        'type' => 'import'
                    )
                );
                $decodedMessage = $this->_helper->decodeLogMessage($errorMessage, 'en_GB');
                $this->_helper->log(
                    'Import',
                    $this->_helper->setLogMessage(
                        'log.import.order_import_failed',
                        array('decoded_message' => $decodedMessage)
                    ),
                    $this->_logOutput,
                    $this->_marketplaceSku
                );
            };
            return false;
        }
        return true;
    }

    /**
     * Checks if an external id already exists
     *
     * @param array $externalIds API external ids
     *
     * @return integer|false
     */
    protected function _checkExternalIds($externalIds)
    {
        $lineId = false;
        $orderMagentoId = false;
        if (!is_null($externalIds) && count($externalIds) > 0) {
            foreach ($externalIds as $externalId) {
                $lineId = $this->_modelOrder->getOrderIdWithDeliveryAddress(
                    (int)$externalId,
                    (int)$this->_deliveryAddressId
                );
                if ($lineId) {
                    $orderMagentoId = $externalId;
                    break;
                }
            }
        }
        return $orderMagentoId;
    }

    /**
     * Get order amount
     *
     * @return float
     */
    protected function _getOrderAmount()
    {
        $this->_processingFee = (float)$this->_orderData->processing_fee;
        $this->_shippingCost = (float)$this->_orderData->shipping;
        // rewrite processing fees and shipping cost
        if ($this->_firstPackage == false) {
            $this->_processingFee = 0;
            $this->_shippingCost = 0;
            $this->_helper->log(
                'Import',
                $this->_helper->setLogMessage('log.import.rewrite_processing_fee'),
                $this->_logOutput,
                $this->_marketplaceSku
            );
            $this->_helper->log(
                'Import',
                $this->_helper->setLogMessage('log.import.rewrite_shipping_cost'),
                $this->_logOutput,
                $this->_marketplaceSku
            );
        }
        // get total amount and the number of items
        $nbItems = 0;
        $totalAmount = 0;
        foreach ($this->_packageData->cart as $product) {
            // check whether the product is canceled for amount
            if (!is_null($product->marketplace_status)) {
                $stateProduct = $this->_marketplace->getStateLengow((string)$product->marketplace_status);
                if ($stateProduct == 'canceled' || $stateProduct == 'refused') {
                    continue;
                }
            }
            $nbItems += (int)$product->quantity;
            $totalAmount += (float)$product->amount;
        }
        $this->_orderItems = $nbItems;
        $orderAmount = $totalAmount + $this->_processingFee + $this->_shippingCost;
        return $orderAmount;
    }

    /**
     * Get tracking data and update Lengow order record
     */
    protected function _loadTrackingData()
    {
        $trackings = $this->_packageData->delivery->trackings;
        if (count($trackings) > 0) {
            $this->_carrierName = (!is_null($trackings[0]->carrier) ? (string)$trackings[0]->carrier : null);
            $this->_carrierMethod = (!is_null($trackings[0]->method) ? (string)$trackings[0]->method : null);
            $this->_trackingNumber = (!is_null($trackings[0]->number) ? (string)$trackings[0]->number : null);
            $this->_relayId = (!is_null($trackings[0]->relay->id) ? (string)$trackings[0]->relay->id : null);
            if (!is_null($trackings[0]->is_delivered_by_marketplace) && $trackings[0]->is_delivered_by_marketplace) {
                $this->_shippedByMp = true;
            }
        }
    }

    /**
     * Get customer name
     *
     * @return string
     */
    protected function _getCustomerName()
    {
        $firstname = (string)$this->_orderData->billing_address->first_name;
        $lastname = (string)$this->_orderData->billing_address->last_name;
        $firstname = ucfirst(strtolower($firstname));
        $lastname = ucfirst(strtolower($lastname));
        if (empty($firstname) && empty($lastname)) {
            return (string)$this->_orderData->billing_address->full_name;
        } else {
            return $firstname . ' ' . $lastname;
        }
    }

    /**
     * Create quote
     *
     * @param Lengow_Connector_Model_Import_Customer $customer Lengow customer instance
     *
     * @return Lengow_Connector_Model_Import_Quote
     */
    protected function _createQuote(Lengow_Connector_Model_Import_Customer $customer)
    {
        $quote = Mage::getModel('lengow/import_quote')
            ->setIsMultiShipping(false)
            ->setStore(Mage::app()->getStore($this->_storeId))
            ->setIsSuperMode(true); // set quote to supermode
        // import customer addresses into quote
        // Set billing Address
        $customerBillingAddress = Mage::getModel('customer/address')->load($customer->getDefaultBilling());
        $billingAddress = Mage::getModel('sales/quote_address')
            ->setShouldIgnoreValidation(true)
            ->importCustomerAddress($customerBillingAddress)
            ->setSaveInAddressBook(0);
        // Set shipping Address
        $customerShippingAddress = Mage::getModel('customer/address')->load($customer->getDefaultShipping());
        $shippingAddress = Mage::getModel('sales/quote_address')
            ->setShouldIgnoreValidation(true)
            ->importCustomerAddress($customerShippingAddress)
            ->setSaveInAddressBook(0)
            ->setSameAsBilling(0);
        $quote->assignCustomerWithAddressChange($customer, $billingAddress, $shippingAddress);
        // check if store include tax (Product and shipping cost)
        $priceIncludeTax = Mage::helper('tax')->priceIncludesTax($quote->getStore());
        $shippingIncludeTax = Mage::helper('tax')->shippingPriceIncludesTax($quote->getStore());
        // add product in quote
        $quote->addLengowProducts(
            $this->_packageData->cart,
            $this->_marketplace,
            $this->_marketplaceSku,
            $this->_logOutput,
            $priceIncludeTax
        );
        // Get shipping cost with tax
        $shippingCost = $this->_processingFee + $this->_shippingCost;
        // if shipping cost not include tax -> get shipping cost without tax
        if (!$shippingIncludeTax) {
            $basedOn = Mage::getStoreConfig(
                Mage_Tax_Model_Config::CONFIG_XML_PATH_BASED_ON,
                $quote->getStore()
            );
            $countryId = ($basedOn == 'shipping')
                ? $shippingAddress->getCountryId()
                : $billingAddress->getCountryId();
            $shippingTaxClass = Mage::getStoreConfig(
                Mage_Tax_Model_Config::CONFIG_XML_PATH_SHIPPING_TAX_CLASS,
                $quote->getStore()
            );
            $taxCalculator = Mage::getModel('tax/calculation');
            $taxRequest = new Varien_Object();
            $taxRequest->setCountryId($countryId)
                ->setCustomerClassId($customer->getTaxClassId())
                ->setProductClassId($shippingTaxClass);
            $taxRate = (float)$taxCalculator->getRate($taxRequest);
            $taxShippingCost = (float)$taxCalculator->calcTaxAmount($shippingCost, $taxRate, true);
            $shippingCost = $shippingCost - $taxShippingCost;
        }
        // update shipping rates for current order
        $rates = $quote->getShippingAddress()
            ->setCollectShippingRates(true)
            ->collectShippingRates()
            ->getShippingRatesCollection();
        $shippingMethod = $this->_updateRates($rates, $shippingCost);
        // set shipping price and shipping method for current order
        $quote->getShippingAddress()
            ->setShippingPrice($shippingCost)
            ->setShippingMethod($shippingMethod);
        // collect totals
        $quote->collectTotals();
        // Re-ajuste cents for item quote
        // Conversion Tax Include > Tax Exclude > Tax Include maybe make 0.01 amount error
        if (!$priceIncludeTax) {
            if ($quote->getGrandTotal() != $this->_orderAmount) {
                $quoteItems = $quote->getAllItems();
                foreach ($quoteItems as $item) {
                    $lengowProduct = $quote->getLengowProducts((string)$item->getProduct()->getId());
                    if ($lengowProduct['amount'] != $item->getRowTotalInclTax()) {
                        $diff = $lengowProduct['amount'] - $item->getRowTotalInclTax();
                        $item->setPriceInclTax($item->getPriceInclTax() + ($diff / $item->getQty()));
                        $item->setBasePriceInclTax($item->getPriceInclTax());
                        $item->setPrice($item->getPrice() + ($diff / $item->getQty()));
                        $item->setOriginalPrice($item->getPrice());
                        $item->setRowTotal($item->getRowTotal() + $diff);
                        $item->setBaseRowTotal($item->getRowTotal());
                        $item->setRowTotalInclTax($lengowProduct['amount']);
                        $item->setBaseRowTotalInclTax($item->getRowTotalInclTax());
                    }
                }
            }
        }
        // get payment informations
        $paymentInfo = '';
        if (count($this->_orderData->payments) > 0) {
            $payment = $this->_orderData->payments[0];
            $paymentInfo .= ' - ' . (string)$payment->type;
            if (isset($payment->payment_terms->external_transaction_id)) {
                $paymentInfo .= ' - ' . (string)$payment->payment_terms->external_transaction_id;
            }
        }
        // set payment method lengow
        $quote->getPayment()->importData(
            array(
                'method' => 'lengow',
                'marketplace' => (string)$this->_orderData->marketplace . $paymentInfo,
            )
        );
        $quote->save();
        return $quote;
    }

    /**
     * Update Rates with shipping cost
     *
     * @param Mage_Sales_Model_Quote_Address_Rate $rates Magento rates
     * @param float $shippingCost shipping cost
     * @param string $shippingMethod Magento shipping method
     * @param boolean $first stop recursive effect
     *
     * @return boolean
     */
    protected function _updateRates($rates, $shippingCost, $shippingMethod = null, $first = true)
    {
        if (!$shippingMethod) {
            $shippingMethod = $this->_config->get('import_shipping_method', $this->_storeId);
        }
        if (empty($shippingMethod)) {
            $shippingMethod = 'lengow_lengow';
        }
        foreach ($rates as &$rate) {
            // make sure the chosen shipping method is correct
            if ($rate->getCode() == $shippingMethod) {
                if ($rate->getPrice() != $shippingCost) {
                    $rate->setPrice($shippingCost);
                    $rate->setCost($shippingCost);
                }
                return $rate->getCode();
            }
        }
        // stop recursive effect
        if (!$first) {
            return 'lengow_lengow';
        }
        // get lengow shipping method if selected shipping method is unavailable
        $this->_helper->log(
            'Import',
            $this->_helper->setLogMessage('log.import.shipping_method_unavailable'),
            $this->_logOutput,
            $this->_marketplaceSku
        );
        return $this->updateRates($rates, $shippingCost, 'lengow_lengow', false);
    }

    /**
     * Add quantity back to stock
     *
     * @param Lengow_Connector_Model_Import_Quote $quote Lengow quote instance
     *
     * @return Lengow_Connector_Model_Import_Importorder
     */
    protected function _addQuantityBack($quote)
    {
        $lengowProducts = $quote->getLengowProducts();
        foreach ($lengowProducts as $productId => $product) {
            Mage::getModel('cataloginventory/stock')->backItemQty($productId, $product['quantity']);
        }
        return $this;
    }

    /**
     * Create order
     *
     * @param Lengow_Connector_Model_Import_Quote $quote Lengow quote instance
     *
     * @throws Lengow_Connector_Model_Exception order failed with quote
     *
     * @return Mage_Sales_Model_Order
     */
    protected function _makeOrder(Lengow_Connector_Model_Import_Quote $quote)
    {
        $additionalDatas = array(
            'from_lengow' => true,
            'follow_by_lengow' => true,
            'marketplace_lengow' => (string)$this->_orderData->marketplace,
            'order_id_lengow' => (string)$this->_marketplaceSku,
            'delivery_address_id_lengow' => (int)$this->_deliveryAddressId,
            'is_reimported_lengow' => false,
            'global_currency_code' => (string)$this->_orderData->currency->iso_a3,
            'base_currency_code' => (string)$this->_orderData->currency->iso_a3,
            'store_currency_code' => (string)$this->_orderData->currency->iso_a3,
            'order_currency_code' => (string)$this->_orderData->currency->iso_a3
        );
        $service = Mage::getModel('sales/service_quote', $quote);
        $service->setOrderData($additionalDatas);
        if (method_exists($service, 'submitAll')) {
            $service->submitAll();
            $order = $service->getOrder();
        } else {
            $order = $service->submit();
        }
        if (!$order) {
            throw new Lengow_Connector_Model_Exception(
                $this->_helper->setLogMessage('lengow_log.exception.order_failed_with_quote')
            );
        }
        // modify order dates to use actual dates
        // Get all params to create order
        if (!is_null($this->_orderData->marketplace_order_date)) {
            $orderDate = (string)$this->_orderData->marketplace_order_date;
        } else {
            $orderDate = (string)$this->_orderData->imported_at;
        }
        $order->setCreatedAt(Mage::getModel('core/date')->date('Y-m-d H:i:s', strtotime($orderDate)));
        $order->setUpdatedAt(Mage::getModel('core/date')->date('Y-m-d H:i:s', strtotime($orderDate)));
        $order->save();
        // Re-ajuste cents for total and shipping cost
        // Conversion Tax Include > Tax Exclude > Tax Include maybe make 0.01 amount error
        $priceIncludeTax = Mage::helper('tax')->priceIncludesTax($quote->getStore());
        $shippingIncludeTax = Mage::helper('tax')->shippingPriceIncludesTax($quote->getStore());
        if (!$priceIncludeTax || !$shippingIncludeTax) {
            if ($order->getGrandTotal() != $this->_orderAmount) {
                // check Grand Total
                $diff = $this->_orderAmount - $order->getGrandTotal();
                $order->setGrandTotal($this->_orderAmount);
                $order->setBaseGrandTotal($order->getGrandTotal());
                // if the difference is only on the grand total, removing the difference of shipping cost
                if (($order->getSubtotalInclTax() + $order->getShippingInclTax()) == $this->_orderAmount) {
                    $order->setShippingAmount($order->getShippingAmount() + $diff);
                    $order->setBaseShippingAmount($order->getShippingAmount());
                } else {
                    // check Shipping Cost
                    $diffShipping = 0;
                    $shippingCost = $this->_processingFee + $this->_shippingCost;
                    if ($order->getShippingInclTax() != $shippingCost) {
                        $diffShipping = ($shippingCost - $order->getShippingInclTax());
                        $order->setShippingAmount($order->getShippingAmount() + $diffShipping);
                        $order->setBaseShippingAmount($order->getShippingAmount());
                        $order->setShippingInclTax($shippingCost);
                        $order->setBaseShippingInclTax($order->getShippingInclTax());
                    }
                    // update Subtotal without shipping cost
                    $order->setSubtotalInclTax($order->getSubtotalInclTax() + ($diff - $diffShipping));
                    $order->setBaseSubtotalInclTax($order->getSubtotalInclTax());
                    $order->setSubtotal($order->getSubtotal() + ($diff - $diffShipping));
                    $order->setBaseSubtotal($order->getSubtotal());
                }
            }
            $order->save();
        }
        // generate invoice for order
        if ($order->canInvoice()) {
            $this->_modelOrder->toInvoice($order);
        }
        $carrierName = $this->_carrierName;
        if (is_null($carrierName) || $carrierName == 'None') {
            $carrierName = $this->_carrierMethod;
        }
        $order->setShippingDescription(
            $order->getShippingDescription() . ' [marketplace shipping method : ' . $carrierName . ']'
        );
        $order->save();
        return $order;
    }

    /**
     * Create a order in lengow orders table
     *
     * @return boolean
     */
    protected function _createLengowOrder()
    {
        // Get all params to create order
        if (!is_null($this->_orderData->marketplace_order_date)) {
            $orderDate = (string)$this->_orderData->marketplace_order_date;
        } else {
            $orderDate = (string)$this->_orderData->imported_at;
        }
        $params = array(
            'store_id' => (int)$this->_storeId,
            'marketplace_sku' => $this->_marketplaceSku,
            'marketplace_name' => strtolower((string)$this->_orderData->marketplace),
            'marketplace_label' => (string)$this->_marketplaceLabel,
            'delivery_address_id' => (int)$this->_deliveryAddressId,
            'order_lengow_state' => $this->_orderStateLengow,
            'order_date' => Mage::getModel('core/date')->date('Y-m-d H:i:s', strtotime($orderDate)),
            'is_in_error' => 1
        );
        if (isset($this->_orderData->comments) && is_array($this->_orderData->comments)) {
            $params['message'] = join(',', $this->_orderData->comments);
        } else {
            $params['message'] = (string)$this->_orderData->comments;
        }
        // Create lengow order
        $this->_modelOrder->createOrder($params);
        // Get lengow order id
        $this->_orderLengowId = $this->_modelOrder->getLengowOrderId(
            $this->_marketplaceSku,
            $this->_deliveryAddressId
        );
        if (!$this->_orderLengowId) {
            return false;
        }
        return true;
    }

    /**
     * Save order line in lengow orders line table
     *
     * @param Mage_Sales_Model_Order $order Magento order instance
     * @param Lengow_Connector_Model_Import_Quote $quote Lengow quote instance
     *
     * @return string
     */
    protected function _saveLengowOrderLine($order, $quote)
    {
        $orderLineSaved = false;
        $lengowProducts = $quote->getLengowProducts();
        foreach ($lengowProducts as $product) {
            foreach ($product['order_line_ids'] as $idOrderLine) {
                $orderLine = Mage::getModel('lengow/import_orderline');
                $orderLine->createOrderLine(
                    array(
                        'order_id' => (int)$order->getId(),
                        'order_line_id' => $idOrderLine
                    )
                );
                $orderLineSaved .= !$orderLineSaved ? $idOrderLine : ' / ' . $idOrderLine;
            }
        }
        return $orderLineSaved;
    }
}
