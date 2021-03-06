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
 * @subpackage  Helper
 * @author      Team module <team-module@lengow.com>
 * @copyright   2017 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Helper toolbox
 */
class Lengow_Connector_Helper_Toolbox extends Mage_Core_Helper_Abstract
{
    /**
     * @var Lengow_Connector_Helper_Data Lengow helper instance
     */
    protected $_helper;

    /**
     * @var Lengow_Connector_Helper_Security Lengow security helper instance
     */
    protected $_securityHelper;

    /**
     * @var Lengow_Connector_Helper_Config Lengow config helper instance
     */
    protected $_configHelper;

    /**
     * @var Lengow_Connector_Helper_Import Lengow import helper instance
     */
    protected $_importHelper;

    /**
     * Construct
     */
    public function __construct()
    {
        $this->_helper = Mage::helper('lengow_connector');
        $this->_securityHelper = Mage::helper('lengow_connector/security');
        $this->_configHelper = Mage::helper('lengow_connector/config');
        $this->_importHelper = Mage::helper('lengow_connector/import');
    }

    /**
     * Get array of plugin informations
     *
     * @return string
     */
    public function getPluginInformations()
    {
        $checklist = array();
        $checklist[] = array(
            'title' => $this->_helper->__('lengow_setting.global_magento_version_title'),
            'message' => Mage::getVersion(),
        );
        $checklist[] = array(
            'title' => $this->_helper->__('lengow_setting.global_plugin_version_title'),
            'message' => $this->_securityHelper->getPluginVersion(),
        );
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.server_ip'),
            'message' => $_SERVER['SERVER_ADDR'],
        );
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.authorized_ip_enable'),
            'state' => (bool)$this->_configHelper->get('ip_enable'),
        );
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.authorized_ip'),
            'message' => $this->_configHelper->get('authorized_ip'),
        );
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.export_on_a_file'),
            'state' => (bool)$this->_configHelper->get('file_enable'),
        );
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.export_cron_enable'),
            'state' => (bool)$this->_configHelper->get('export_cron_enable'),
        );
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.pre_production_enable'),
            'state' => !$this->_configHelper->debugModeIsActive(),
        );
        $filePath = Mage::getBaseDir('media') . DS . 'lengow' . DS . 'test.txt';
        $file = fopen($filePath, 'w+');
        if (!$file) {
            $state = false;
        } else {
            $state = true;
            unlink($filePath);
        }
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.write_permission'),
            'state' => $state,
        );
        return $this->_getContent($checklist);
    }

    /**
     * Get array of import information
     *
     * @return string
     */
    public function getImportInformations()
    {
        $checklist = array();
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.global_token'),
            'message' => $this->_configHelper->getToken(),
        );
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.url_import'),
            'message' => $this->_helper->getCronUrl(),
        );
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.import_cron_enable'),
            'state' => (bool)$this->_configHelper->get('import_cron_enable'),
        );
        /** @var Lengow_Connector_Model_Import_Order $order */
        $order = Mage::getModel('lengow/import_order');
        $nbOrderImported = $order->countOrderImportedByLengow();
        $orderWithError = $order->countOrderWithError();
        $orderToBeSent = $order->countOrderToBeSent();
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.nb_order_imported'),
            'message' => $nbOrderImported,
        );
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.nb_order_to_be_sent'),
            'message' => $orderToBeSent,
        );
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.nb_order_with_error'),
            'message' => $orderWithError,
        );
        $lastImport = $this->_importHelper->getLastImport();
        $lastImportDate = $lastImport['timestamp'] === 'none'
            ? $this->_helper->__('toolbox.screen.last_import_none')
            : $this->_helper->getDateInCorrectFormat($lastImport['timestamp'], true);
        if ($lastImport['type'] === 'none') {
            $lastImportType = $this->_helper->__('toolbox.screen.last_import_none');
        } elseif ($lastImport['type'] === Lengow_Connector_Model_Import::TYPE_CRON) {
            $lastImportType = $this->_helper->__('toolbox.screen.last_import_cron');
        } else {
            $lastImportType = $this->_helper->__('toolbox.screen.last_import_manual');
        }
        if ($this->_importHelper->importIsInProcess()) {
            $importInProgress = $this->_helper->__(
                'toolbox.screen.rest_time_to_import',
                array('rest_time' => $this->_importHelper->restTimeToImport())
            );
        } else {
            $importInProgress = $this->_helper->__('toolbox.screen.no_import');
        }
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.import_in_progress'),
            'message' => $importInProgress,
        );
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.store_last_import'),
            'message' => $lastImportDate,
        );
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.store_type_import'),
            'message' => $lastImportType,
        );
        return $this->_getContent($checklist);
    }

    /**
     * Get array of export informations
     *
     * @param Mage_Core_Model_Store $store Magento store instance
     *
     * @return string
     */
    public function getExportInformations($store)
    {
        /** @var Lengow_Connector_Model_Export $export */
        $export = Mage::getModel('lengow/export', array("store_id" => $store->getId()));
        $checklist = array();
        try {
            $checklist[] = array('header' => $store->getName() . ' (' . $store->getId() . ') ' . $store->getBaseUrl());
        } catch (\Exception $e) {
            $checklist[] = array('header' => $store->getName() . ' (' . $store->getId() . ')');
        }
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.store_active'),
            'state' => (bool)$this->_configHelper->get('store_enable', $store->getId()),
        );
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.store_catalog_id'),
            'message' => $this->_configHelper->get('catalog_id', $store->getId()),
        );
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.store_product_total'),
            'message' => $export->getTotalProduct(),
        );
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.store_product_exported'),
            'message' => $export->getTotalExportedProduct(),
        );
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.store_export_token'),
            'message' => $this->_configHelper->getToken($store->getId()),
        );
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.url_export'),
            'message' => $this->_helper->getExportUrl($store->getId()),
        );
        $lastExportDate = $this->_configHelper->get('last_export', $store->getId());
        $lastExportMessage = $lastExportDate === ''
            ? $this->_helper->__('toolbox.screen.last_import_none')
            : $this->_helper->getDateInCorrectFormat($lastExportDate, true);
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.store_last_export'),
            'message' => $lastExportMessage,
        );
        return $this->_getContent($checklist);
    }

    /**
     * Get array of file informations
     *
     * @param Mage_Core_Model_Store $store Magento store instance
     *
     * @return string
     */
    public function getFileInformations($store)
    {
        $folderPath = Mage::getBaseDir('media') . DS . 'lengow' . DS . $store->getCode() . DS;
        $folderUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'lengow' . DS . $store->getCode() . DS;
        $files = @array_diff(scandir($folderPath), array('..', '.'));
        $checklist = array();
        try {
            $checklist[] = array('header' => $store->getName() . ' (' . $store->getId() . ') ' . $store->getBaseUrl());
        } catch (\Exception $e) {
            $checklist[] = array('header' => $store->getName() . ' (' . $store->getId() . ')');
        }
        $checklist[] = array(
            'title' => $this->_helper->__('toolbox.screen.folder_path'),
            'message' => $folderPath,
        );
        if (!empty($files)) {
            $checklist[] = array(
                'simple' => $this->_helper->__('toolbox.screen.file_list'),
            );
            foreach ($files as $file) {
                $fileTimestamp = filectime($folderPath . $file);
                $fileLink = '<a href="' . $folderUrl . $file . '" target="_blank">' . $file . '</a>';
                $checklist[] = array(
                    'title' => $fileLink,
                    'message' => $this->_helper->getDateInCorrectFormat($fileTimestamp, true),
                );
            }
        } else {
            $checklist[] = array(
                'simple' => $this->_helper->__('toolbox.screen.no_file_exported'),
            );
        }
        return $this->_getContent($checklist);
    }

    /**
     * Get array of file informations
     *
     * @param string $type cron type (export or import)
     *
     * @return string
     */
    public function getCronInformation($type)
    {
        $jobCode = $type == 'import' ? 'import_cron_lengow' : 'export_cron_lengow';
        $cronJobs = Mage::getModel('cron/schedule')->getCollection()->getData();
        $lengowCronJobs = array();
        foreach ($cronJobs as $cronJob) {
            if ($cronJob['job_code'] === $jobCode) {
                $lengowCronJobs[] = $cronJob;
            }
        }
        $lengowCronJobs = array_slice(array_reverse($lengowCronJobs), 0, 20);
        return $this->_getCronContent($lengowCronJobs);
    }

    /**
     * Get files checksum
     *
     * @return string
     */
    public function checkFileMd5()
    {
        $checklist = array();
        $fileName = Mage::getModuleDir('etc', 'Lengow_Connector') . DS . 'checkmd5.csv';
        $html = '<h3><i class="fa fa-commenting"></i> ' . $this->_helper->__('toolbox.screen.summary') . '</h3>';
        $fileCounter = 0;
        if (file_exists($fileName)) {
            $fileErrors = array();
            $fileDeletes = array();
            if (($file = fopen($fileName, 'r')) !== false) {
                while (($data = fgetcsv($file, 1000, '|')) !== false) {
                    $fileCounter++;
                    $filePath = Mage::getBaseDir() . $data[0];
                    if (file_exists($filePath)) {
                        $fileMd = md5_file($filePath);
                        if ($fileMd !== $data[1]) {
                            $fileErrors[] = array(
                                'title' => $filePath,
                                'state' => false,
                            );
                        }
                    } else {
                        $fileDeletes[] = array(
                            'title' => $filePath,
                            'state' => false,
                        );
                    }
                }
                fclose($file);
            }
            $checklist[] = array(
                'title' => $this->_helper->__('toolbox.screen.file_checked', array('nb_file' => $fileCounter)),
                'state' => true,
            );
            $checklist[] = array(
                'title' => $this->_helper->__('toolbox.screen.file_modified', array('nb_file' => count($fileErrors))),
                'state' => !empty($fileErrors) ? false : true,
            );
            $checklist[] = array(
                'title' => $this->_helper->__('toolbox.screen.file_deleted', array('nb_file' => count($fileDeletes))),
                'state' => !empty($fileDeletes) ? false : true,
            );
            $html .= $this->_getContent($checklist);
            if (!empty($fileErrors)) {
                $html .= '<h3><i class="fa fa-list"></i> '
                    . $this->_helper->__('toolbox.screen.list_modified_file') . '</h3>';
                $html .= $this->_getContent($fileErrors);
            }
            if (!empty($fileDeletes)) {
                $html .= '<h3><i class="fa fa-list"></i> '
                    . $this->_helper->__('toolbox.screen.list_deleted_file') . '</h3>';
                $html .= $this->_getContent($fileDeletes);
            }
        } else {
            $checklist[] = array(
                'title' => $this->_helper->__('toolbox.screen.file_not_exists'),
                'state' => false,
            );
            $html .= $this->_getContent($checklist);
        }
        return $html;
    }

    /**
     * Get HTML Table content of checklist
     *
     * @param array $checklist all information for toolbox
     *
     * @return string
     */
    protected function _getContent($checklist = array())
    {
        if (empty($checklist)) {
            return null;
        }
        $out = '<table cellpadding="0" cellspacing="0">';
        foreach ($checklist as $check) {
            $out .= '<tr>';
            if (isset($check['header'])) {
                $out .= '<td colspan="2" align="center" style="border:0"><h4>' . $check['header'] . '</h4></td>';
            } elseif (isset($check['simple'])) {
                $out .= '<td colspan="2" align="center"><h5>' . $check['simple'] . '</h5></td>';
            } else {
                $out .= '<td><b>' . $check['title'] . '</b></td>';
                if (isset($check['state'])) {
                    if ($check['state']) {
                        $out .= '<td align="right"><i class="fa fa-check lengow-green"></td>';
                    } else {
                        $out .= '<td align="right"><i class="fa fa-times lengow-red"></td>';
                    }
                } else {
                    $out .= '<td align="right">' . $check['message'] . '</td>';
                }
            }
            $out .= '</tr>';
        }
        $out .= '</table>';
        return $out;
    }

    /**
     * Get HTML Table content of cron job
     *
     * @param array $lengowCronJobs Lengow cron jobs
     *
     * @return string
     */
    protected function _getCronContent($lengowCronJobs = array())
    {
        $out = '<table cellpadding="0" cellspacing="0">';
        if (empty($lengowCronJobs)) {
            $out .= '<tr><td style="border:0">' . $this->_helper->__('toolbox.screen.no_cron_job_yet') . '</td></tr>';
        } else {
            $out .= '<tr>';
            $out .= '<th>' . $this->_helper->__('toolbox.screen.cron_status') . '</th>';
            $out .= '<th>' . $this->_helper->__('toolbox.screen.cron_message') . '</th>';
            $out .= '<th>' . $this->_helper->__('toolbox.screen.cron_scheduled_at') . '</th>';
            $out .= '<th>' . $this->_helper->__('toolbox.screen.cron_executed_at') . '</th>';
            $out .= '<th>' . $this->_helper->__('toolbox.screen.cron_finished_at') . '</th>';
            $out .= '</tr>';
            foreach ($lengowCronJobs as $lengowCronJob) {
                $out .= '<tr>';
                $out .= '<td>' . $lengowCronJob['status'] . '</td>';
                if ($lengowCronJob['messages'] != '') {
                    $out .= '<td><a class="lengow_tooltip" href="#">'
                        . $this->_helper->__('toolbox.screen.cron_see_message')
                        . '<span class="lengow_toolbox_message">'
                        . $lengowCronJob['messages'] . '</span></a></td>';
                } else {
                    $out .= '<td></td>';
                }
                $scheduledAt = $lengowCronJob['scheduled_at'] !== null
                    ? Mage::getModel('core/date')->date('Y-m-d H:i:s', strtotime($lengowCronJob['scheduled_at']))
                    : '';
                $out .= '<td>' . $scheduledAt . '</td>';
                $executedAt = $lengowCronJob['executed_at'] !== null
                    ? Mage::getModel('core/date')->date('Y-m-d H:i:s', strtotime($lengowCronJob['executed_at']))
                    : '';
                $out .= '<td>' . $executedAt . '</td>';
                $finishedAt = $lengowCronJob['finished_at'] !== null
                    ? Mage::getModel('core/date')->date('Y-m-d H:i:s', strtotime($lengowCronJob['finished_at']))
                    : '';
                $out .= '<td>' . $finishedAt . '</td>';
                $out .= '</tr>';
            }
        }
        $out .= '</table>';
        return $out;
    }
}
