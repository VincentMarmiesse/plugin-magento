<?php

/**
 *
 * @category    Lengow
 * @package     Lengow_Connector
 * @author      Team module <team-module@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Lengow_Connector_Model_Import_Order extends Mage_Core_Model_Abstract
{
    /**
    * integer order process state for new order not imported
    */
    const PROCESS_STATE_NEW = 0;

    /**
    * integer order process state for order imported
    */
    const PROCESS_STATE_IMPORT = 1;

    /**
    * integer order process state for order finished
    */
    const PROCESS_STATE_FINISH = 2;

    /**
     * @var array $_field_list field list for the table lengow_order_line
     * required => Required fields when creating registration
     * update   => Fields allowed when updating registration
     */
    protected $_field_list = array(
        'id_order'              => array('required' => false, 'updated' => true),
        'id_store'              => array('required' => true, 'updated' => false),
        'id_feed'               => array('required' => false, 'updated' => true),
        'delivery_address_id'   => array('required' => true, 'updated' => false),
        'delivery_country_iso'  => array('required' => false, 'updated' => true),
        'marketplace_sku'       => array('required' => true, 'updated' => false),
        'marketplace_name'      => array('required' => true, 'updated' => false),
        'marketplace_label'     => array('required' => true, 'updated' => false),
        'order_lengow_state'    => array('required' => true, 'updated' => true),
        'order_process_state'   => array('required' => false, 'updated' => true),
        'order_date'            => array('required' => true, 'updated' => false),
        'order_item'            => array('required' => false, 'updated' => true),
        'currency'              => array('required' => true, 'updated' => false),
        'total_paid'            => array('required' => false, 'updated' => true),
        'commission'            => array('required' => false, 'updated' => true),
        'customer_name'         => array('required' => false, 'updated' => true),
        'carrier'               => array('required' => false, 'updated' => true),
        'carrier_method'        => array('required' => false, 'updated' => true),
        'carrier_tracking'      => array('required' => false, 'updated' => true),
        'carrier_id_relay'      => array('required' => false, 'updated' => true),
        'sent_marketplace'      => array('required' => false, 'updated' => true),
        'is_reimported'         => array('required' => false, 'updated' => true),
        'message'               => array('required' => true, 'updated' => true),
        'extra'                 => array('required' => false, 'updated' => true)
    );

    /**
     * Constructor
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init('lengow/import_order');
    }

    /**
     * Create Lengow order
     *
     * @param array $params
     *
     */
    public function createOrder($params = array())
    {
        foreach ($this->_field_list as $key => $value) {
            if (!array_key_exists($key, $params) && $value['required']) {
                return false;
            }
        }
        foreach ($params as $key => $value) {
            $this->setData($key, $value);
        }
        $this->setData('order_process_state', self::PROCESS_STATE_NEW);
        $this->setData('created_at', Mage::getModel('core/date')->date('Y-m-d H:i:s'));
        return $this->save();
    }

    /**
     * Update Lengow order
     *
     * @param array $params
     *
     */
    public function updateOrder($params = array())
    {
        if (!$this->id) {
            return false;
        }
        $updated_fields = $this->getUpdatedFields();
        foreach ($params as $key => $value) {
            if (in_array($key, $updated_fields)) {
                $this->setData($key, $value);
            }
        }
        $this->setData('updated_at', Mage::getModel('core/date')->date('Y-m-d H:i:s'));
        return $this->save();
    }

    /**
     * Get updated fields
     *
     * @return array
     *
     */
    public function getUpdatedFields()
    {
        $updated_fields = array();
        foreach ($this->_field_list as $key => $value) {
            if ($value['updated']) {
                $updated_fields[] = $key;
            }
        }
        return $updated_fields;
    }

    /**
     * is Already Imported
     *
     * @param string    $lengow_id              Lengow order id
     * @param string    $markeplace_name        marketplace name
     * @param integer   $delivery_address_id    delivery address id
     *
     * @return mixed
     */
    public function getOrderIdFromLengowOrders($marketplace_sku, $marketplace_name, $delivery_address_id)
    {
        // get order id in lengow order table
        $results = $this->getCollection()
            ->addFieldToFilter('marketplace_sku', $marketplace_sku)
            ->addFieldToFilter('marketplace_name', $marketplace_name)
            ->addFieldToFilter('order_process_state', array('neq' => 0))
            ->addFieldToSelect('id_order')
            ->addFieldToSelect('delivery_address_id')
            ->addFieldToSelect('id_feed')
            ->getData();
        print_r($results);
        if (count($results) > 0) {
            foreach ($results as $result) {
                if ($result['delivery_address_id'] == 0 && $result['id_feed'] != 0) {
                    return $result['id_order'];
                } elseif ($result['delivery_address_id'] == $delivery_address_id) {
                    return $result['id_order'];
                }
            }
        }
        // get order id in Magento flat order table (compatibility)
        $order_results = Mage::getModel('sales/order')->getCollection()
            ->addAttributeToFilter('order_id_lengow', $marketplace_sku)
            ->addAttributeToFilter('marketplace_lengow', $marketplace_name)
            ->addAttributeToSelect('entity_id')
            ->addAttributeToSelect('delivery_address_id_lengow')
            ->addAttributeToSelect('feed_id_lengow')
            ->getData();
        print_r($order_results);
        if (count($order_results) > 0) {
            foreach ($order_results as $result) {
                if ($result['delivery_address_id_lengow'] == 0 && $result['feed_id_lengow'] != 0) {
                    return $result['entity_id'];
                } elseif ($result['delivery_address_id_lengow'] == $delivery_address_id) {
                    return $result['entity_id'];
                }
            }
        }

        return false;
    }

    /**
     * Check if an order has an error
     *
     * @param string    $marketplace_sku        Lengow order id
     * @param integer   $delivery_address_id    Id delivery address
     * @param string    $type                   Type (import or send)
     *
     * @return mixed
     */
    public function orderIsInError($marketplace_sku, $delivery_address_id, $type = 'import')
    {
        $order_error = Mage::getModel('lengow/import_ordererror');
        $log_type = $order_error->getOrderLogType($type);
        // check if log already exists for the given order id
        $results = $order_error->getCollection()
            ->join(
                array('order'=> 'lengow/import_order'),
                'order.id=main_table.id_order_lengow',
                array('marketplace_sku' => 'marketplace_sku', 'delivery_address_id' => 'delivery_address_id')
            )
            ->addFieldToFilter('marketplace_sku', $marketplace_sku)
            ->addFieldToFilter('delivery_address_id', $delivery_address_id)
            ->addFieldToFilter('type', $log_type)
            ->addFieldToFilter('is_finished', array('eq' => 0))
            ->addFieldToSelect('id')
            ->addFieldToSelect('message')
            ->addFieldToSelect('created_at')
            ->getData();
        if (count($results) == 0) {
            return false;
        }
        return $results[0];
    }
}
