<?php

/**
 *
 * @category    Lengow
 * @package     Lengow_Connector
 * @author      Team module <team-module@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Lengow_Connector_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @var integer    life of log files in days
     */
    const LOG_LIFE = 20;

    /**
     * User another translation system (key based)
     *
     * @return string
     */
    public function __()
    {
        $args = func_get_args();
        $t = Mage::helper('lengow_connector/translation');
        if ($args[0]=="") {
            return "";
        }

        if (isset($args[1]) && is_array($args[1])) {
            return $t->t($args[0], $args[1]);
        }
        return $t->t($args[0]);
    }

    /**
     * Write log
     *
     * @param string    $category           Category
     * @param string    $message            log message
     * @param boolean   $display            display on screen
     * @param string    $marketplace_sku    lengow order id
     *
     * @return boolean
     */
    public function log($category, $message = "", $display = false, $marketplace_sku = null)
    {
        if (strlen($message) == 0) {
            return false;
        }
        $decoded_message = $message; //= LengowMain::decodeLogMessage($message, 'en');
        $finalMessage = (empty($category) ? '' : '['.$category.'] ');
        $finalMessage.= ''.(empty($marketplace_sku) ? '' : 'order '.$marketplace_sku.' : ');
        $finalMessage.= $decoded_message;
        if ($display) {
            echo $finalMessage.'<br />';
            flush();
        }
        $log = Mage::getModel('lengow/log');
        return $log->createLog(array('message' => $finalMessage));
    }

    /**
     * Delete log files when too old
     */
    public function cleanLog()
    {
        $nbDays = (int)$nbDays;
        $resource = Mage::getSingleton('core/resource');
        $writeConnection = $resource->getConnection('core_write');
        $table = $resource->getTableName('lengow/log');
        $query = "DELETE FROM ".$table." WHERE `date` < DATE_SUB(NOW(),INTERVAL ".self::LOG_LIFE." DAY)";
        $writeConnection->query($query);
    }

    /**
     * Convert specials chars to html chars
     * Clean None utf-8 characters
     *
     * @param string $value The content
     * @param boolean $convert If convert specials chars
     * @param boolean $html Keep html
     *
     * @return string $value
     */
    public function cleanData($value, $convert = false, $html = false)
    {
        if ($convert && $html) {
            $value = htmlentities($value);
        }
        if (is_array($value)) {
            return $value;
        }
        $value = nl2br($value);
        $value = Mage::helper('core/string')->cleanString($value);
        // Reject overly long 2 byte sequences, as well as characters above U+10000 and replace with blank
        $value = preg_replace('/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]' .
            '|[\x00-\x7F][\x80-\xBF]+' .
            '|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*' .
            '|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})' .
            '|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/S', '', $value);
        // Reject overly long 3 byte sequences and UTF-16 surrogates and replace with blank
        $value = preg_replace('/\xE0[\x80-\x9F][\x80-\xBF]' .
            '|\xED[\xA0-\xBF][\x80-\xBF]/S', '', $value);
        if (!$html) {
            $pattern = '@<[\/\!]*?[^<>]*?>@si'; //nettoyage du code HTML
            $value = preg_replace($pattern, ' ', $value);
        }
        $value = preg_replace('/[\s]+/', ' ', $value); //nettoyage des espaces multiples
        $value = trim($value);
        $value = str_replace(
            array(
                '&nbsp;',
                '|',
                '"',
                '’',
                '&#39;',
                '&#150;',
                chr(9),
                chr(10),
                chr(13),
                chr(31),
                chr(30),
                chr(29),
                chr(28),
                "\n",
                "\r"
            ),
            array(
            ' ',
            ' ',
            '\'',
            '\'',
            ' ',
            '-',
            ' ',
            ' ',
            ' ',
            '',
            '',
            '',
            '',
            '',
            ''
            ),
            $value
        );
        return $value;
    }
}
