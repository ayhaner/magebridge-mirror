<?php
/*
 * Joomla! component MageBridge
 *
 * @author Yireo (info@yireo.com)
 * @package MageBridge
 * @copyright Copyright Yireo.com 2012
 * @license GNU Public License
 * @link http://www.yireo.com
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die( 'Restricted access' );

// Include Joomla! libraries
jimport( 'joomla.application.component.model' );

/*
 * MageBridge Check model
 */
class MagebridgeModelCheck extends JModel
{
    const CHECK_OK = 'ok';
    const CHECK_WARNING = 'warning';
    const CHECK_ERROR = 'error';

    /**
     * Method to add the result of a check to an internal array
     *
     * @package MageBridge
     * @access public
     * @param string $group
     * @param string $check
     * @param int $status
     * @param string $description
     * @return null
     */
    public function addResult($group, $check, $status, $description = '')
    {
        if (empty($this->_checks[$group])) {
            $this->_checks[$group] = array();
        }

        $this->_checks[$group][] = array(
            'text' => JText::_($check),
            'status' => $status,
            'description' => $description,
        );

        return;
    }

    /**
     * Method to get all checks
     *
     * @package MageBridge
     * @access public
     * @param bool $installer
     * @return array
     */
    public function getChecks($installer = false)
    {
        $this->doSystemChecks($installer);
        if ($installer == false) {
            $this->doExtensionChecks();
            $this->doBridgeChecks();
            $this->doPluginChecks();
            //$this->doConfigChecks();
        }

        return $this->_checks;
    }

    /**
     * Method to do all configuration checks
     *
     * @package MageBridge
     * @access public
     * @param null
     * @return null
     */
    public function doConfigChecks()
    {
        $group = 'config';
        $config = MagebridgeModelConfig::load();
        foreach ($config as $c) {
            $result = MagebridgeModelConfig::check($c['name'], $c['value']);
            if (!empty($result)) {
                $this->addResult($group, $c['name'], self::CHECK_WARNING, $result);
            } else {
                $this->addResult($group, $c['name'], self::CHECK_OK, $c['description']);
            }
        }

        return;
    }

    /**
     * Method to do all bridge-based checks
     *
     * @package MageBridge
     * @access public
     * @return null
     */
    public function doBridgeChecks()
    {
        $register = MageBridgeModelRegister::getInstance();
        $bridge = MageBridgeModelBridge::getInstance();

        // First add all the things we need to the bridge
        $version_id = $register->add('version');

        // Build the bridge
        $bridge->build();
        $magebridge_version_magento = $register->getDataById($version_id);
        $magebridge_version_joomla = MageBridgeUpdateHelper::getComponentVersion();

        if (empty($magebridge_version_magento)) {
            $this->addResult('bridge', 'Bridge version', self::CHECK_WARNING, JText::_('CHECK_BRIDGE_NO_VERSION'));
        } else {
            $result = (version_compare($magebridge_version_magento, $magebridge_version_joomla, '=')) ? self::CHECK_OK : self::CHECK_ERROR;
            $this->addResult('bridge', 'Bridge version', $result, JText::sprintf('CHECK_BRIDGE_VERSION', $magebridge_version_magento, $magebridge_version_joomla));
        }

        $result = (MageBridgeModelConfig::load('modify_url') == 1) ? self::CHECK_OK : self::CHECK_WARNING;
        $this->addResult('bridge', 'Modify URLs', $result, JText::_('CHECK_BRIDGE_MODIFY_URL'));

        $result = (MageBridgeModelConfig::load('link_to_magento') == 0) ? self::CHECK_OK : self::CHECK_WARNING;
        $this->addResult('bridge', 'Link to Magento', $result, JText::_('CHECK_BRIDGE_LINK_TO_MAGENTO'));

        //$result = (defined('MAGEBRIDGE_MODULEHELPER_OVERRIDE') == true) ? self::CHECK_OK : self::CHECK_WARNING;
        //$this->addResult('bridge', 'Modulehelper override', $result, JText::_('CHECK_BRIDGE_MODULEHELPER_OVERRIDE'));

        return;
    }

    /**
     * Method to do all system checks
     *
     * @package MageBridge
     * @access public
     * @return null
     */
    public function doSystemChecks($installer = false)
    {
        $application = JFactory::getApplication();
        $db = JFactory::getDBO();
        $config = MagebridgeModelConfig::load();

        // System Compatibility
        $result = (version_compare(phpversion(), '5.2.8', '>=')) ? self::CHECK_OK : self::CHECK_ERROR;
        $this->addResult('compatibility', 'PHP version', $result, JText::sprintf('CHECK_PHP_VERSION', '5.2.8'));

        $result = (version_compare(ini_get('memory_limit'), '31M', '>')) ? self::CHECK_OK : self::CHECK_ERROR;
        $this->addResult('compatibility', 'PHP memory', $result, JText::sprintf('CHECK_PHP_MEMORY', '32Mb', ini_get('memory_limit')));

        $jversion = new JVersion();
        $result = (version_compare($jversion->getShortVersion(), '1.5.12', '>=')) ? self::CHECK_OK : self::CHECK_ERROR;
        $this->addResult('compatibility', 'Joomla! version', $result, JText::sprintf('CHECK_JOOMLA_VERSION', '1.5.12'));

        $result = (function_exists('simplexml_load_string')) ? self::CHECK_OK : self::CHECK_ERROR;
        $this->addResult('compatibility', 'SimpleXML', $result, JText::_('CHECK_SIMPLEXML'));

        $result = (in_array('ssl', stream_get_transports())) ? self::CHECK_OK : self::CHECK_ERROR;
        $this->addResult('compatibility', 'OpenSSL', $result, JText::_('CHECK_OPENSSL'));

        $result = (function_exists('json_decode')) ? self::CHECK_OK : self::CHECK_ERROR;
        $this->addResult('compatibility', 'JSON', $result, JText::_('CHECK_JSON'));

        $result = (function_exists('curl_init')) ? self::CHECK_OK : self::CHECK_ERROR;
        $this->addResult('compatibility', 'CURL', $result, JText::_('CHECK_CURL'));

        $result = (function_exists('mcrypt_cfb')) ? self::CHECK_OK : self::CHECK_ERROR;
        $this->addResult('compatibility', 'mcrypt', $result, JText::_('CHECK_MCRYPT'));

        $result = (ini_get('magic_quotes_gpc')) ? self::CHECK_ERROR : self::CHECK_OK;
        $this->addResult('compatibility', 'magic_quotes_gpc', $result, JText::_('CHECK_MAGIC_QUOTES_GPC'));

        $result = (ini_get('safe_mode')) ? self::CHECK_ERROR : self::CHECK_OK;
        $this->addResult('compatibility', 'safe_mode', $result, JText::_('CHECK_SAFE_MODE'));

        if (function_exists('apache_get_modules')) {
            $modules = apache_get_modules();
            $result = in_array('mod_rewrite', $modules);
        } else {
            $result =  getenv('HTTP_MOD_REWRITE')=='On' ? true : false ;
        }
        $this->addResult('compatibility', 'Apache mod_rewrite', $result, JText::_('CHECK_MOD_REWRITE'));

        $result = $this->checkWebOwner();
        $this->addResult('compatibility', 'File Ownership', $result, JText::_('CHECK_FILE_OWNERSHIP'));

        // System Configuration
        $result = (file_exists(JPATH_SITE.'/.htaccess')) ? self::CHECK_OK : self::CHECK_ERROR;
        $this->addResult('system', 'htaccess', $result, JText::_('CHECK_HTACCESS'));

        $result = ($application->getCfg('sef') == 1) ? self::CHECK_OK : self::CHECK_ERROR;
        $this->addResult('system', 'SEF', $result, JText::_('CHECK_SEF'));

        $result = ($application->getCfg('sef_rewrite') == 1) ? self::CHECK_OK : self::CHECK_WARNING;
        $this->addResult('system', 'SEF Rewrites', $result, JText::_('CHECK_SEF_REWRITE'));

        $result = ($application->getCfg('caching') == 0) ? self::CHECK_OK : self::CHECK_WARNING;
        $this->addResult('system', 'Caching', $result, JText::_('CHECK_CACHING'));

        if ($installer == false) {
            $result = ((boolean)MageBridgeUrlHelper::getRootItem()) ? self::CHECK_OK : self::CHECK_WARNING;
            $this->addResult('system', 'Root item', $result, JText::_('CHECK_ROOT_ITEM'));
        }

        $result = self::checkWritable($application->getCfg('tmp_path'));
        $this->addResult('system', 'Temporary path writable', $result, JText::_('CHECK_TMP'));

        $result = self::checkWritable($application->getCfg('log_path'));
        $this->addResult('system', 'Log path writable', $result, JText::_('CHECK_LOG'));

        $result = self::checkWritable(JPATH_SITE.'/cache');
        $this->addResult('system', 'Cache writable', $result, JText::_('CHECK_CACHE'));

        return;
    }

    /**
     * Method to do all extension checks
     *
     * @package MageBridge
     * @access public
     * @return null
     */
    public function doExtensionChecks()
    {
        $application = JFactory::getApplication();
        $db = JFactory::getDBO();
        $config = MagebridgeModelConfig::load();

        if (file_exists(JPATH_SITE.'/plugins/system/rokmoduleorder.php')) $this->addResult('extension', 'RokModuleOrder', self::CHECK_ERROR, JText::_('CHECK_ROKMODULEORDER'));
        if (file_exists(JPATH_SITE.'/plugins/system/rsform.php')) $this->addResult('extension', 'RSForm', self::CHECK_ERROR, JText::_('CHECK_RSFORM'));
        if (file_exists(JPATH_SITE.'/components/com_acesef/acesef.php')) $this->addResult('extension', 'AceSEF', self::CHECK_ERROR, JText::_('CHECK_ACESEF'));
        if (file_exists(JPATH_SITE.'/components/com_sh404sef/sh404sef.php')) $this->addResult('extension', 'sh404SEF', self::CHECK_ERROR, JText::_('CHECK_SH404SEF'));
        if (file_exists(JPATH_ADMINISTRATOR.'/components/com_sef/controller.php')) $this->addResult('extension', 'JoomSEF', self::CHECK_WARNING, JText::_('CHECK_JOOMSEF'));
        if (file_exists(JPATH_SITE.'/components/com_rsfirewall/rsfirewall.php')) $this->addResult('extension', 'RSFirewall', self::CHECK_WARNING, JText::_('CHECK_RSFIREWALL'));

        return;
    }

    /**
     * Method to do all plugin checks
     *
     * @package MageBridge
     * @access public
     * @param null
     * @return null
     */
    public function doPluginChecks()
    {
        $application = JFactory::getApplication();
        $db = JFactory::getDBO();

        $plugins = array(
            array('authentication', 'magebridge', 'Authentication - MageBridge', 'CHECK_PLUGIN_AUTHENTICATION'),
            array('magento', 'magebridge', 'Magento - MageBridge', 'CHECK_PLUGIN_MAGENTO'),
            array('magebridge', 'magebridge', 'MageBridge - Core', 'CHECK_PLUGIN_MAGEBRIDGE'),
            array('user', 'magebridge', 'User - MageBridge', 'CHECK_PLUGIN_USER'),
            array('system', 'magebridge', 'System - MageBridge', 'CHECK_PLUGIN_SYSTEM'),
            array('system', 'magebridgepre', 'System - MageBridge Preloader', 'CHECK_PLUGIN_PRELOADER'),
        );

        foreach ($plugins as $plugin) {

            $group = $plugin[0];
            $name = $plugin[1];
            $title = $plugin[2];
            $description = $plugin[3];

            if (MageBridgeHelper::isJoomla15()) {
                $result = (file_exists(JPATH_SITE.'/plugins/'.$group.'/'.$name.'.php')) ? self::CHECK_OK : self::CHECK_ERROR;
            } else {
                $result = (file_exists(JPATH_SITE.'/plugins/'.$group.'/'.$name.'/'.$name.'.php')) ? self::CHECK_OK : self::CHECK_ERROR;
            }

            if ($result == self::CHECK_ERROR) {
                $description = JText::_('CHECK_PLUGIN_NOT_INSTALLED');
                $this->addResult('extensions', $title, self::CHECK_ERROR, $description);

            } else {

                $pluginObject = JPluginHelper::getPlugin($group, 'magebridge');

                if (MageBridgeHelper::isJoomla15()) {
                    $db->setQuery('SELECT id,published AS enabled FROM #__plugins WHERE `element`="magebridge" AND `folder`="'.$group.'" LIMIT 1');
                } else {
                    $db->setQuery('SELECT extension_id AS id,enabled FROM #__extensions WHERE `type`="plugin" AND `element`="magebridge" AND `folder`="'.$group.'" LIMIT 1');
                }

                $row = $db->loadObject();
                if (empty($row)) {
                    $description = JText::_('CHECK_PLUGIN_NOT_INSTALLED');
                    $this->addResult('extensions', $title, self::CHECK_ERROR, $description);
                    continue;
                }

                $url = 'index.php?option=com_plugins';
                if ($row->enabled == 0) {
                    $description = JText::sprintf('CHECK_PLUGIN_DISABLED', $url);
                    $this->addResult('extensions', $title, self::CHECK_ERROR, $description);
                    continue;
                }

                $this->addResult('extensions', $plugin[2], (bool)$pluginObject, JText::_('CHECK_PLUGIN_ENABLED'));
            }
        }

        return;
    }

    /**
     * Method to do all plugin checks
     *
     * @package MageBridge
     * @access public
     * @param null
     * @return null
     */
    public function checkWebOwner()
    {
        $files = array(
            JPATH_SITE.'/configuration.php',
            JPATH_SITE.'/components/com_magebridge',
            JPATH_SITE.'/administrator/components/com_magebridge',
            JPATH_SITE.'/plugins/system/magebridge.php',
        );

        $user = getmyuid();
        foreach ($files as $file) {
            if (is_file($file) == false) continue;
            if (fileowner($file) !== $user) {
                return self::CHECK_WARNING;
            }
        }

        return self::CHECK_OK;
    }

    /**
     * Method to do all plugin checks
     *
     * @package MageBridge
     * @access public
     * @param null
     * @return null
     */
    public function checkWritable($path)
    {
        // Return a warning because we can't check this with JFTP enabled
        if (JFactory::getConfig()->getValue('ftp_enable') == 1) {
            return self::CHECK_WARNING;
        }

        // Regular check
        if (!is_writable($path)) {
            return self::CHECK_ERROR;
        }

        return self::CHECK_OK;
    }
}
