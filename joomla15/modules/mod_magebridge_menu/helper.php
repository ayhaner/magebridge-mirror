<?php
/**
 * Joomla! module MageBridge: Menu
 *
 * @author Yireo (info@yireo.com)
 * @package MageBridge
 * @copyright Copyright 2012
 * @license GNU Public License
 * @link http://www.yireo.com
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

/*
 * Helper-class for the module
 */
class modMageBridgeMenuHelper extends MageBridgeModuleHelper
{
    /*
     * Method to get the API-arguments based upon the module parameters
     * 
     * @access public
     * @param JParameter $params
     * @return array
     */
    static public function getArguments($params = null)
    {
        static $arguments = array();
        $id = md5(var_export($params, true));
        if (!isset($arguments[$id])) {
            $arguments[$id] = array();
            if ($params->get('include_product_count') == 1) {
                $arguments[$id]['include_product_count'] = 1;
            }
            if (empty($arguments[$id])) $arguments[$id] = null;
        }

        return $arguments[$id];
    }

    /*
     * Method to be called once the MageBridge is loaded
     * 
     * @access public
     * @param JParameter $params
     * @return array
     */
    public function register($params = null)
    {
        $arguments = modMageBridgeMenuHelper::getArguments($params);
        return array(
            array('api', 'magebridge_category.tree', $arguments),
        );
    }

    /*
     * Fetch the content from the bridge
     * 
     * @access public
     * @param JParameter $params
     * @return mixed
     */
    public function build($params = null)
    {
        $arguments = modMageBridgeMenuHelper::getArguments($params);
        return parent::getCall('getAPI', 'magebridge_category.tree', $arguments);
    }

    /*
     * Helper-method to return a specified root-category from a tree
     * 
     * @access public
     * @param array $tree
     * @param int $root_id
     * @return array
     */
    public function setRoot($tree = null, $root_id = null)
    {
        // If no root-category is configured, just return all children
        if (!$root_id > 0) {
            return $tree['children'];
        }

        // If the current level contains the configured root-category, return it's children
        if (isset($tree['category_id']) && $tree['category_id'] == $root_id) {
            return $tree['children'];
        }

        // Loop through the children to find the configured root-category
        if (isset($tree['children']) && is_array($tree['children']) && count($tree['children']) > 0) {
            foreach ($tree['children'] as $item) {
                $subtree = modMageBridgeMenuHelper::setRoot($item, $root_id);
                if (!empty($subtree)) return $subtree;
            }
        }
        return array();
    }

    /*
     * Parse the categories of a tree for display
     * 
     * @access public
     * @param array $tree
     * @param int $endLevel
     * @return mixed
     */
    public function parseTree($tree, $endLevel = 99 ) 
    {
        if (is_array($tree) && count($tree) > 0) {
            foreach ($tree as $index => $item) {

                if (empty($item)) {
                    unset($tree[$index]);
                    continue;
                }

                // Remove disabled categories
                if ($item['is_active'] != 1) {
                    unset($tree[$index]);
                    continue;
                }

                // Remove categories that should not be in the menu
                if (isset($item['include_in_menu']) && $item['include_in_menu'] != 1) {
                    unset($tree[$index]);
                    continue;
                }

                // Remove items from the wrong level
                if ($item['level'] >= $endLevel) {
                    unset($tree[$index]);
                    continue;
                }

                // Handle HTML-entities in the title
                if (isset($item['name'])) {
                    $item['name'] = htmlspecialchars($item['name']);
                }

                // Parse the children-tree
                if (!empty($item['children'])) {
                    $item['children'] = modMageBridgeMenuHelper::parseTree($item['children'], $endLevel );
                } else {
                    $item['children'] = array();
                }

                // Translate the URL into Joomla! SEF URL
                if (empty($item['url'])) {
                    $item['url'] = '';
                } else {
                    $item['url'] = MageBridgeUrlHelper::route($item['url']);
                }

                $tree[$index] = $item;
            }
        }

        return $tree;
    }

    /*
     * Helper-method to return a CSS-class string
     * 
     * @access public
     * @param JParameter $params
     * @param array $item
     * @param int $level
     * @param int $counter
     * @param array $tree
     * @return string
     */
    public function getCssClass($params, $item, $level, $counter, $tree)
    {
        $config = MageBridge::getBridge()->getMageConfig();
        $current_category_id = (isset($config['current_category_id'])) ? $config['current_category_id'] : null;
        $current_category_path = (isset($config['current_category_path'])) ? explode('/', $config['current_category_path']) : array();

        $class = array();

        if (isset($item['entity_id'])) {
            if ($item['entity_id'] == $current_category_id) {
                $class[] = 'current';
            } elseif (in_array($item['entity_id'], $current_category_path)) {
                $class[] = 'active';
            }
        }

        if (isset($item['children_count']) && $item['children_count'] > 0) {
            $class[] = 'parent';
        }

        //if (isset($item['product_count']) && $item['product_count'] > 0) {
        //    $class[] = 'hasproducts';
        //}

        if ($params->get('css_level', 0) == 1) {
            $class[] = 'level'.$level;
        }

        if ($params->get('css_firstlast', 0) == 1) {
            if ($counter == 0) $class[] = 'first';
            if ($counter == count($tree)) $class[] = 'last';
        }

        if ($params->get('css_evenodd', 0) == 1) { 
            if ($counter % 2 == 0) $class[] = 'even';
            if ($counter % 2 == 1) $class[] = 'odd';
        }

        $class = array_unique($class);
        $class = implode(' ', $class);
        return $class;
    }
}
