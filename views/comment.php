<?php if (!defined('APPLICATION')) exit(); ?>

<div class="MessageForm CommentForm AnonymousCommentForm">
<?php
//d($this->Form);
//d();d($this->Form->GetValue('Name'));
// TODO: HTML5 Storage Session , FIN PLUGIN
//$this->Form->SetFormValue('Body', $this->Comment->Body);
// TODO: /post/anonymouscomment ? [HOLD]
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
// TODO: ADD OTHER FIELD EMAIL OR URL

$AnonymousFormInputs = '';

$CaptchaImageSource = $this->CaptchaImageSource;
//$AnonymousFormInputs = $this->Form->TextBox('YourName', array('placeholder' => T('Your name')));
$AnonymousFormInputs = Wrap(Img($CaptchaImageSource)
	. $this->Form->TextBox('CaptchaCode', array('placeholder' => T('Code from image'))),
	'div', array('id' => 'CaptchaBox')
);

echo Wrap($this->Form->TextBox('YourName', array('placeholder' => 'Your name')).$AnonymousFormInputs,
	'div', array('class' => 'YourName'));



echo $this->Form->TextBox('Body', $CommentOptions);
//d($this->Form);
echo "<div class=\"Buttons\">\n";
//$this->FireEvent('BeforeFormButtons');
$CancelText = 'Back to Discussions';
$CancelClass = 'Back';
echo Anchor(T($CancelText), 'discussions', $CancelClass);
$ButtonOptions = array('class' => 'Button CommentButton');
echo $this->Form->Button('Post Comment', $ButtonOptions);
//$this->FireEvent('AfterFormButtons');
echo "</div>\n";
echo $this->Form->Close();
?>
</div>