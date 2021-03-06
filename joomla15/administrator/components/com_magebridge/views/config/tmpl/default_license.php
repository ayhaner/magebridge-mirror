<?php
/*
 * Joomla! component MageBridge
 *
 * @author Yireo (info@yireo.com)
 * @package MageBridge
 * @copyright Copyright 2012
 * @license GNU Public License
 * @link http://www.yireo.com
 */

defined('_JEXEC') or die('Restricted access');
?>
<fieldset class="adminform">
<legend><?php echo JText::_('Support Settings'); ?></legend>
<table class="admintable">
    <tr>
        <td class="key">
            <?php echo JText::_('Support_Key'); ?>
        </td>
        <td class="value">
            <input type="text" name="license" value="<?php echo $this->config['license']['value']; ?>" size="40" />
        </td>
        <td class="status">
        </td>
        <td class="description">
            <span><?php echo JText::_( 'SUPPORT_KEY_DESCRIPTION' ); ?></span>
        </td>
    </tr>
</table>
</fieldset>
    
