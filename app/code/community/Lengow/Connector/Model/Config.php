<?php

/**
 *
 * @category    Lengow
 * @package     Lengow_Connector
 * @author      Team module <team-module@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Connector_Model_Config extends Mage_Core_Model_Config
{
    /**
     * Path for Lengow options
     */
    protected $_lengowOptions = array(
        'lengow_global_options',
        'lengow_export_options',
        'lengow_import_options'
    );

    /**
     * Excludes attributes for export
     */
    protected $_excludeOptions = array(
        'import_in_progress',
        'last_import_manual',
        'last_import_cron',
        'export_last_export',
        'last_statistic_update',
        'order_statistic',
        'see_migrate_block',
        'last_status_update',
        'account_status',
        'last_option_cms_update',
    );

    /**
     * Override Save config to store lengow changes
     *
     * @param string  $path
     * @param string  $value
     * @param string  $scope
     * @param integer $scopeId
     *
     * @return Mage_Core_Store_Config
     */
    public function saveConfig($path, $value, $scope = 'default', $scopeId = 0)
    {
        $pathExplode = explode("/", $path);
        if (isset($pathExplode[0]) && in_array($pathExplode[0], $this->_lengowOptions)) {
            if ($scope == 'default' || $scope == 'stores') {
                $oldValue = Mage::getStoreConfig($path, $scopeId);
                if ($oldValue!= $value && !in_array($pathExplode[2], $this->_excludeOptions)) {
                    if ($pathExplode[2] == 'global_access_token' || $pathExplode[2] == 'global_secret_token') {
                        $newValue = preg_replace("/[a-zA-Z0-9]/", '*', $value);
                        $oldValue = preg_replace("/[a-zA-Z0-9]/", '*', $oldValue);
                    } else {
                        $newValue = $value;
                    }
                    if ($scope == 'stores') {
                        $message = Mage::helper('lengow_connector/translation')->t(
                            'log.setting.setting_change_for_store',
                            array(
                                'key'       => $path,
                                'old_value' => $oldValue,
                                'value'     => $newValue,
                                'store_id'  => $scopeId
                            )
                        );
                    } else {
                        $message = Mage::helper('lengow_connector/translation')->t(
                            'log.setting.setting_change',
                            array(
                                'key'       => $path,
                                'old_value' => $oldValue,
                                'value'     => $newValue,
                            )
                        );
                    }
                    Mage::helper('lengow_connector')->log('Config', $message);
                }
            }
        }
        parent::saveConfig($path, $value, $scope, $scopeId);
        return $this;
    }
}
