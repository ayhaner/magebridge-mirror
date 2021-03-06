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

<form method="post" name="adminForm" id="adminForm">
<table cellspacing="0" cellpadding="0" border="0" width="100%">
<tbody>
<tr>
<td width="50%" valign="top">
    <fieldset class="adminform">
        <legend><?php echo JText::_( 'Details' ); ?></legend>
        <table class="admintable">
        <tbody>
        <tr>
            <td width="100" align="right" class="key">
                <label for="title">
                    <?php echo JText::_( 'Title' ); ?>:
                </label>
            </td>
            <td class="value">
                <input type="text" name="title" value="<?php echo $this->item->title; ?>" size="30" />
            </td>
        </tr>
        <tr>
            <td width="100" align="right" class="key">
                <label for="type">
                    <?php echo JText::_( 'type' ); ?>:
                </label>
            </td>
            <td class="value">
                <?php echo $this->item->type; ?>
            </td>
        </tr>
        <tr>
            <td width="100" align="right" class="key">
                <label for="name">
                    <?php echo JText::_( 'name' ); ?>:
                </label>
            </td>
            <td class="value">
                <?php echo $this->item->name; ?>
            </td>
        </tr>
        <tr>
            <td width="100" align="right" class="key">
                <label for="filename">
                    <?php echo JText::_( 'filename' ); ?>:
                </label>
            </td>
            <td class="value">
                <?php echo $this->item->filename; ?>
            </td>
        </tr>
        <tr>
            <td valign="top" align="right" class="key">
                <?php echo JText::_( 'Published' ); ?>:
            </td>
            <td class="value">
                <?php echo $this->fields['published']; ?>
            </td>
        </tr>
        <tr>
            <td valign="top" align="right" class="key">
                <label for="ordering">
                    <?php echo JText::_( 'Ordering' ); ?>:
                </label>
            </td>
            <td class="value">
                <?php echo $this->fields['ordering']; ?>
            </td>
        </tr>
        </tbody>
    </table>
    </fieldset>
</td>
<td width="50%" valign="top">
    <?php
    echo $this->pane->startPane("content-pane");

    $title = JText::_('Parameters');
    echo $this->pane->startPanel( $title, "params" );
    if ($this->params) { 
        echo $this->params->render();
    } else {
        echo JText::_('No parameters');
    }
    echo $this->pane->endPanel();

    echo $this->pane->endPane();
    ?>
</td>
</tr>
</tbody>
</table>

<input type="hidden" name="option" value="com_magebridge" />
<input type="hidden" name="cid[]" value="<?php echo $this->item->id; ?>" />
<input type="hidden" name="task" value="" />
<?php echo JHTML::_( 'form.token' ); ?>
</form>
