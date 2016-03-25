<?php

/**
 *
 * @category    Lengow
 * @package     Lengow_Connector
 * @author      Team module <team-module@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Connector_Model_Export_Feed_Yaml extends Lengow_Connector_Model_Export_Feed_Abstract
{

    protected $_content_type = 'text/x-yaml';

    public function getContentType()
    {
        return $this->_content_type;
    }

    public function makeHeader()
    {
        return '"catalog":' . "\r\n";
    }

    public function makeData($array, $args = array())
    {
        $line = '  ' . '"product":' . "\r\n";
        foreach ($this->_fields as $name) {
            $line .= '    ' . '"' . $name . '":' .
                $this->_addSpaces($name, 22) . (isset($array[$name]) ? $array[$name] : '') . "\r\n";
        }
        return $line;
    }

    public function makeFooter()
    {
        return '';
    }

    /**
     * For YAML, add spaces to have good indentation.
     *
     * @param string $name The fielname
     * @param string $maxsize The max spaces
     *
     * @return string Spaces.
     */
    private function _addSpaces($name, $size)
    {
        $strlen = strlen($name);
        $spaces = '';
        for ($i = $strlen; $i < $size; $i++) {
            $spaces .= ' ';
        }
        return $spaces;
    }
}