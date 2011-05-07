<?php if (!defined('APPLICATION')) exit(); ?>

<div class="MessageForm CommentForm AnonymousCommentForm">
<?php
echo $this->Form->Open();
echo $this->Form->Errors();
$CommentOptions = array('MultiLine' => True);
/*
Caused non-root users to not be able to add comments. Must take categories
into account. Look at CheckPermission for more information.
if (!$Session->CheckPermission('Vanilla.Comment.Add')) {
	$CommentOptions['Disabled'] = 'disabled';
	$CommentOptions['Value'] = T('You do not have permission to write new comments.');
}
*/

echo AnonymousePlugin::FormInputs($this);

echo $this->Form->TextBox('Body', $CommentOptions);
echo "<div class=\"Buttons\">\n";
//$this->FireEvent('BeforeFormButtons');
$CancelText = 'Back to Discussions';
$CancelClass = 'Back';
echo Anchor(T($CancelText), 'discussions', $CancelClass);
$ButtonOptions = array('class' => 'Button CommentButton');
echo $this->Form->Button('Preview', array('class' => 'Button PreviewButton PreviewCommentButton'));
echo $this->Form->Button('Post Comment', $ButtonOptions);
//$this->FireEvent('AfterFormButtons');
echo "</div>\n";
echo $this->Form->Close();
?>
</div>