<?php
/**
 * Joomla! component MageBridge
 *
 * @author Yireo (info@yireo.com)
 * @package MageBridge
 * @copyright Copyright 2011
 * @license GNU Public License
 * @link http://www.yireo.com
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

/**
 * MageBridge Product-connector class
 *
 * @package MageBridge
 */
class MageBridgeConnectorProduct extends MageBridgeConnector
{
    /*
     * Singleton variable
     */
    private static $_instance = null;

    /*
     * Singleton method
     *
     * @param null
     * @return MageBridgeConnectorProduct
     */
    public static function getInstance()
    {
        static $instance;

        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /*
     * Method to do something on purchase
     *
     * @param string $sku
     * @param JUser $user
     * @param string $status
     * @return mixed
     */
    public function runOnPurchase($sku = null, $qty = 1, $user = null, $status = null, $arguments = null)
    {
        // Get the conditions
        $conditions = $this->getConditions($sku);
        if (empty($conditions)) {
            return null;
        }

        // Foreach of these conditions, run the appropriate connector method
        foreach ($conditions as $condition) {
            $connector = $this->getConnector($condition->connector);
            if (!empty($condition->connector_value) && !empty($connector)) {

                if (!empty($condition->params)) {
                    jimport('joomla.html.parameter');
                    $params = new JParameter($condition->params);
                    $only_completed = $params->get('only_completed', false);
                    $expire_amount = $params->get('expire_amount', 0);
                    $expire_unit = $params->get('expire_unit', 'day');
                } else {
                    $only_completed = false;
                    $expire_amount = 0;
                    $expire_unit = null;
                }

                // Do not continue if we are only supposed to run on completed orders
                if ($status != 'complete' && $only_completed == true) {
                    continue;
                }

                // Try to call the connector-method
                $rt = false;
                if (method_exists($connector, 'onPurchase')) {
                    try {
                        if ($qty < 1) $qty = 1;
                        for($i = 0; $i < $qty; $i++) {
                            $rt = $connector->onPurchase($condition->connector_value, $user, $status);
                        }
                    } catch(Exception $e) {
                        MageBridgeModelDebug::getInstance()->trace('Product Connector failed', $e->getMessage());
                    }
                    $this->saveLog($user->id, $sku, $expire_unit, $expire_amount);
                }

                // If this result is false (read: an error), do not continue with other product relations
                //if ($rt == false) break;
            }
        }

        // Refresh the user session, just in case
        MageBridge::getUser()->updateSession(JFactory::getUser());
    }

    /*
     * Method to save the actions of this connector
     *
     * @param int $user_id
     * @param string $sku
     * @param string $expire_unit
     * @param int $expire_amount
     * @return mixed
     */
    public function saveLog($user_id = 0, $sku = null, $expire_unit = null, $expire_amount = null)
    {
        // Save this connector-value in the database
        if ($user_id > 0 && $expire_amount > 0) {

            switch($expire_unit) {
                case 'week':
                    $expire_seconds = $expire_amount * 7 * 24 * 60 * 60;
                    break;
                case 'day':
                default:
                    $expire_seconds = $expire_amount * 24 * 60 * 60;
                    break;
            }            

            $create_date = time();
            $expire_date = time() + $expire_seconds;

            $db = JFactory::getDBO();
            $query = "INSERT INTO #__magebridge_products_log "
                . " SET `user_id`=".(int)$user_id.", `sku`=".$db->Quote($sku).", `create_date`=".(int)$create_date.", `expire_date`=".(int)$expire_date;
            $db->setQuery($query);
            $db->query();
        }

        return true;
    }

    /*
     * Overload methods to add an argument to it
     */
    public function getConnectors($type = null) { return parent::_getConnectors('product'); }
    public function getConnector($name) { return parent::_getConnector('product', $name); }
    public function getConnectorObject($name) { return parent::_getConnectorObject('product', $name); }
    public function getPath($file) { return parent::_getPath('product', $file); }
    public function getParams() { return parent::_getParams('product'); }

    /*
     * Method to get the current conditions
     *
     * @param string $sku
     * @return array
     */
    protected function getConditions($sku)
    {
        // Fetch all published product relations
        static $rows = null;
        if ($rows == null) {
            $db = JFactory::getDBO();
            $query = "SELECT * FROM #__magebridge_products WHERE `published`=1 ORDER BY `ordering`";
            $db->setQuery($query);
            $rows = $db->loadObjectList();
        }

        // Filter all product relations to return only applicable matches
        $conditions = array();
        if (!empty($rows)) {
            foreach ($rows as $row) {

                // Match the filter ALL
                if (strtoupper($row->sku) == 'ALL') {
                    $conditions[] = $row;

                // A direct match of the SKU
                } else if ($row->sku == $sku) {
                    $conditions[] = $row;

                // A listing of matches
                } else if (strstr($row->sku, ',')) {
                    $ss = explode(',', $row->sku);
                    foreach ($ss as $s) {
                        if (trim($s) == $sku) {
                            $conditions[] = $row;
                        }
                        break;
                    }

                // Simple simulation of LIKE-statement
                } else if (strstr($row->sku, '%')) {
                    $s = str_replace('%', '', $row->sku);

                    // Start with %
                    if (preg_match('/^\%/', $row->sku)) {
                        if (substr($sku, strlen($sku) - strlen($s)) == $s) {
                            $conditions[] = $row;
                        }
                    // End with %
                    } else if (preg_match('/\%$/', $row->sku)) {
                        if (substr($sku, 0, strlen($s)) == $s) {
                            $conditions[] = $row;
                        }
                    }
                }
            }
        }

        return $conditions;
    }
}
