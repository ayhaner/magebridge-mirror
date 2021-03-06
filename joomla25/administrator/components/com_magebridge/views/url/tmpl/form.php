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

// Set toolbar items for the page
$edit = JRequest::getVar('edit',true);
$text = !$edit ? JText::_( 'New' ) : JText::_( 'Edit' );
JToolBarHelper::save();
JToolBarHelper::apply();
if (!$edit)  {
    JToolBarHelper::cancel();
} else {
    JToolBarHelper::cancel( 'cancel', 'Close' );
}
?>

<?php echo MageBridgeHelper::help('Checkout out the {tutorials:MageBridge Design Guide} on how to use combine theming'); ?>

<form method="post" name="adminForm" id="adminForm">
<table cellspacing="0" cellpadding="0" border="0" width="100%">
<tbody>
<tr>
<td width="50%" valign="top">
    <fieldset class="adminform">
        <legend><?php echo JText::_( 'Source URL' ); ?></legend>
        <table class="admintable">
        <tbody>
        <tr>
            <td width="100" align="right" class="key">
                <label for="source">
                    <?php echo JText::_( 'Source URL' ); ?>:
                </label>
            </td>
            <td>
                <input type="text" name="source" value="<?php echo $this->item->source; ?>" size="60" />
            </td>
        </tr>
        <tr>
            <td valign="top" align="right" class="key">
                <?php echo JText::_( 'Source Type' ); ?>:
            </td>
            <td>
                <?php echo $this->fields['source_type']; ?>
            </td>
        </tr>
        </tbody>
        </table>
    </fieldset>
    <fieldset class="adminform">
        <legend><?php echo JText::_( 'Destination URL' ); ?></legend>
        <table class="admintable">
        <tbody>
        <tr>
            <td width="100" align="right" class="key">
                <label for="destination">
                    <?php echo JText::_( 'Destination URL' ); ?>:
                </label>
            </td>
            <td>
                <input type="text" name="destination" value="<?php echo $this->item->destination; ?>" size="60" />
            </td>
        </tr>
        </tbody>
        </table>
    </fieldset>
    <fieldset class="adminform">
        <legend><?php echo JText::_( 'Meta information' ); ?></legend>
        <table class="admintable">
        <tbody>
        <tr>
            <td width="100" align="right" class="key">
                <label for="description">
                    <?php echo JText::_( 'Description' ); ?>:
                </label>
            </td>
            <td>
                <input type="text" name="description" value="<?php echo $this->item->description; ?>" size="40" />
            </td>
        </tr>
        <tr>
            <td valign="top" align="right" class="key">
                <?php echo JText::_( 'Published' ); ?>:
            </td>
            <td>
                <?php echo $this->fields['published']; ?>
            </td>
        </tr>
        </tbody>
        </table>
    </fieldset>
</td>
</tr>
</tbody>
</table>

<input type="hidden" name="option" value="com_magebridge" />
<input type="hidden" name="cid[]" value="<?php echo $this->item->id; ?>" />
<input type="hidden" name="task" value="" />
<?php echo JHTML::_( 'form.token' ); ?>
</form>
