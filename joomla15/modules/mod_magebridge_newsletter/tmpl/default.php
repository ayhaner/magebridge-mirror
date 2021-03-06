<?php
/**
 * Joomla! module MageBridge: Newsletter
 *
 * @author Yireo (info@yireo.com)
 * @package MageBridge
 * @copyright Copyright 2012
 * @license GNU Public License
 * @link http://www.yireo.com
 */

// No direct access
defined('_JEXEC') or die('Restricted access');
?>
<div id="magebridge.newsletter" class="magebridge-module">
    <div class="box base-mini mini-newsletter">
    <div class="head">
        <h4><?php echo JText::_('MOD_MAGEBRIDGE_NEWSLETTER_NEWSLETTER'); ?><a name="newsletter-box"></a></h4>
    </div>
    <form action="<?php echo $form_url; ?>" method="post" id="newsletter-validate-detail">
    <fieldset class="content">
        <legend><?php echo JText::_('MOD_MAGEBRIDGE_NEWSLETTER_NEWSLETTER'); ?></legend>
        <label for="newsletter"><?php echo JText::_('MOD_MAGEBRIDGE_NEWSLETTER_SIGNUP'); ?>:</label>
        <input name="email" type="text" id="newsletter" class="required-entry validate-email input-text" value="<?php echo $user->email; ?>" />
        <input type="submit" class="form-button-alt" value="<?php echo JText::_('MOD_MAGEBRIDGE_NEWSLETTER_SUBSCRIBE'); ?>"/>
    </fieldset>
</form>
</div>
</div>
<div style="clear:both"></div>

