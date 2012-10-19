<?php
/**
 * MageBridge
 *
 * @author Yireo
 * @package MageBridge
 * @copyright Copyright 2012
 * @license Yireo EULA (www.yireo.com)
 * @link http://www.yireo.com
 */

class Yireo_MageBridge_Model_Rewrite_Store extends Mage_Core_Model_Store
{
    /*
     * Add a new method to overwrite an existing cached-value
     * 
     * @param string $path
     * @param mixed $value
     * @return null
     */
    public function overrideCachedConfig($path = null, $value = null)
    {
        $this->_configCache[$path] = $value;
    }
}
