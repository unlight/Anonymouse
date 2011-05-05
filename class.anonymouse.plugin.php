<?php if (!defined('APPLICATION')) exit();

$PluginInfo['Anonymouse'] = array(
	'Name' => 'Anonymouse 2',
	'Description' => 'Anonymous posting.',
	'SettingsUrl' => '/settings/anonymouse',
	'Version' => '2.5.18',
	'Date' => '5 May 2011',
	'Author' => 'Anonymous',
	'RequiredApplications' => array('Vanilla' => '>=2.0.16'),
	//'RequiredPlugins' => array('Morf' => '*'),
	'AuthorUrl' => 'https://github.com/search?q=Anonymouse'
);

/* =======================

CONFIG:
$Configuration['Plugins']['Anonymouse']['NoCaptha'] = False;
// Default: False, to disable captcha set option to True.
$Configuration['Plugins']['Anonymouse']['Category'] = array(1,2);
$Configuration['Plugins']['Anonymouse']['AnonymousUserID'] = 0; // UserID
$Configuration['Plugins']['Anonymouse']['ShowAuthorName'] = 0;
How to show anonymous name:
0 - default, ex. Anonymous John
1 - name of garden's anonymous user, ex. Anonymous
2 - Name which choosed by author, ex. John 
3 - Localized 'Anonymous' string

// NOTE:
Someone says that the comment form is appearing above the thread for anonymous users.
If so, try to edit config (add this line if it is not exists)
$Configuration['Modules']['Vanilla']['Content'] = array('Content', 'AnonymousCommentForm');

TODO: 
FIX: NO PERMISSION TO VIEW, ANYWAY CAN POST

*/

if (!function_exists('UserAnchor')) {
	function UserAnchor($User, $CssClass = '') {
		static $AnonymousUserID;
		if ($AnonymousUserID === Null) $AnonymousUserID = AnonymousePlugin::StaticGetAnonymousUserID();
		if ($AnonymousUserID == $User->UserID) {
			// TODO: FIX ME, $CssClass is lost here
			return $User->Name;
		}
		if ($CssClass != '') $CssClass = ' class="'.$CssClass.'"';
		return '<a href="'.Url('/profile/'.$User->UserID.'/'.urlencode($User->Name)).'"'.$CssClass.'>'.$User->Name.'</a>';
	}
}


if (!function_exists('PromoteKey')) {
	function PromoteKey($Collection, $PromotedKey) {
		$Result = array();
		foreach($Collection as $Data) {
			$K = GetValue($PromotedKey, $Data);
			$Result[$K] = $Data;
		}
		return $Result;
	}
}

class AnonymousePlugin extends Gdn_Plugin {
	
	protected $PostValues;
	protected $bInitialized;
	protected $AnonymousUserID;
	
	protected $AnonymousCommentData = array();
	protected $AnonymousDiscussionData = array();
	
	/* static methods */
	
	public static function Config($Name, $Default = False) {
		static $AnonymousConfiguration;
		if ($AnonymousConfiguration === NULL) $AnonymousConfiguration = C('Plugins.Anonymouse');
		return GetValueR($Name, $AnonymousConfiguration, $Default);
	}
	
	public static function StaticGetAnonymousUserID() {
		$Self = Gdn::PluginManager()->GetPluginInstance(__CLASS__);
		return $Self->GetAnonymousUserID();
	}
	
	/* =============================== CONTROLLER */
	
	public function SettingsController_Anonymouse_Create($Sender) {
		$Sender->Permission('Garden.Settings.Manage');
		//$Sender->Permission('Garden.Plugins.Manage');
		
		$Sender->Title('Anonymouse settings');
		$Sender->AddSideMenu('settings/anonymouse');
		
		$Form = $Sender->Form;
		
		$Validation = new Gdn_Validation();
		$ConfigurationModel = new Gdn_ConfigurationModel($Validation);
		
		$ConfigurationModel->SetField(array(
			'Plugins.Anonymouse.Category'
		));
		
		$Sender->Form->SetModel($ConfigurationModel);
		$Validation->ApplyRule('Plugins.Anonymouse.Category', 'RequiredArray');
		
		if ($Sender->Form->AuthenticatedPostBack() != False) {
			if ($Form->ButtonExists('Reset to defaults')) {
				RemoveFromConfig('Plugins.Anonymouse.Category');
				$Sender->StatusMessage = T('Saved');
			} elseif ($Sender->Form->Save() != False)
				$Sender->StatusMessage = T('Saved');
		} else {
			$Sender->Form->SetData($ConfigurationModel->Data);
		}
	
		$CategoryModel = new Gdn_Model('Category');
		$Sender->CategoryData = $CategoryModel->GetWhere(array('AllowDiscussions' => 1));
		
		$Sender->AnonymouseCategory = self::Config('Category');

		$Sender->View = $this->GetView('settings.php');
		$Sender->Render();
	}
	
	public function PostController_Render_Before($Sender) {
		$Session = Gdn::Session();
		if ($Session->IsValid()) return;
		
		$Sender->AddCssFile('plugins/Anonymouse/anonymouse.css');
		$Sender->AddJsFile('plugins/Anonymouse/anonymouse.js');
		$Sender->Form->SetValue('YourName', $this->CookieName());
		
		if (strtolower($Sender->RequestMethod) == 'discussion') {
			$CategoryModel = new Gdn_Model('Category');
			$Permission = self::Config('Category', ArrayValue('Vanilla.Discussions.View', $Session->GetPermissions()));
			$CategoryModel->SQL->WhereIn('CategoryID', $Permission);
			$Sender->CategoryData = $CategoryModel->GetWhere(array('AllowDiscussions' => 1));
		}

	}
	
	/* =============================== MODEL */
	
	protected function GetAnonymousCommentData($CommentData) {
		if ($CommentData instanceof Gdn_DataSet) 
			$CommentData = ConsolidateArrayValuesByKey($CommentData->ResultObject(), 'CommentID');
		$DataSet = Gdn::SQL()
			->Select()
			->From('AnonymousComment')
			->WhereIn('CommentID', (array)$CommentData)
			->Get();
		$Result = PromoteKey($DataSet, 'CommentID');
		return $Result;
	}
	
	protected function GetAnonymousDiscussionData($Reference) {
		if ($Reference instanceof Gdn_DataSet) 
			$Reference = ConsolidateArrayValuesByKey($Reference->ResultObject(), 'DiscussionID');
		if (!is_array($Reference)) $Reference = array($Reference);
		$DataSet = Gdn::SQL()
			->Select()
			->From('AnonymousDiscussion')
			->WhereIn('DiscussionID', $Reference)
			->Get();
		$Result = PromoteKey($DataSet, 'DiscussionID');
		return $Result;
	}
	
	protected static function IpAddress($Ip = Null) {
		if ($Ip !== Null) {
			if (!$Ip) return $Ip;
			return (is_numeric($Ip)) ? long2ip($Ip) : ip2long($Ip);
		}
		return sprintf('%u', self::IpAddress(RemoteIp()));
	}
	
	protected function Save($ObjectID, $FormValues, $Type) {
		$Fields['IntIp'] = self::IpAddress();
		$Name = trim(Gdn_Format::Text(ArrayValue('YourName', $FormValues)));
		if (in_array($Name, array('Your name', T('Your name')))) $Name = Null;
		if (!$Name) $Name = Null;
		$Fields['Name'] = $Name;
		$Type = ucfirst(strtolower($Type));
		$Fields[$Type.'ID'] = $ObjectID;
		return Gdn::SQL()->Insert('Anonymous'.$Type, $Fields);
	}
	
	protected function CookieName($Name = Null) {
		if ($Name === Null) return ArrayValue('YourName', $_COOKIE);
		if (!is_string($Name)) $Name = (string) GetValue('YourName', $Name);
		// 100% cpu usage here?.. why
		setcookie('YourName', $Name, strtotime('+1 year'), '/');
	}
	
	protected function BecomeUnAuthenticatedUser() {
		$Session = Gdn::Session();
		if (!$Session->IsValid()) return;
		$Session->UserID = 0;
		$Session->User = False;
	}
	
	protected function BecomeAnonymousUser() {
		$AnonymousUserID = $this->GetAnonymousUserID();
		$Session = Gdn::Session();
		if ($Session->IsValid()) return;
		// $Session->User = Gdn_Dummy(); // may be useful
		$Session->UserID = $AnonymousUserID;
		$Session->User = new StdClass(); // for php < 5.3 
		$Session->User->UserID = $AnonymousUserID;
		$Session->User->Admin = 0;
		$Session->User->HourOffset = 0;
		$Session->User->Name = 'Anonymous';
		$Session->User->CountNotifications = 0;
		$Session->User->Photo = '';
	}
	
	protected function ResetCaptchaKey() {
		$_SESSION['CaptchaKey'] = Null;
	}
	
	
	protected function ReplaceAnonymousNameForDiscussion($Discussion, $Prefix = 'First') {
		if (array_key_exists($Discussion->DiscussionID, $this->AnonymousDiscussionData)) {
			$AnonymousInfo = $this->AnonymousDiscussionData[$Discussion->DiscussionID];
			$Discussion->{$Prefix.'Name'} = $this->GetAnonymousName($Discussion->{$Prefix.'Name'}, $AnonymousInfo);
		}
	}
	
	protected function ReplaceAnonymousNameForComment($Comment) {
		if (array_key_exists($Comment->CommentID, $this->AnonymousCommentData)) {
			$AnonymousInfo = $this->AnonymousCommentData[$Comment->CommentID];
			$Comment->InsertName = $this->GetAnonymousName($Comment->InsertName, $AnonymousInfo);
		}
	}
	
	protected function GetAnonymousName($AnonymousUserName, $AnonymousInfo) {
		static $bCanViewIp, $ShowAuthorName;
		if ($ShowAuthorName === Null) $ShowAuthorName = C('Plugins.Anonymouse.ShowAuthorName');
		$NewName = Null;
		switch ($ShowAuthorName) {
			case 1: $Format = '%1$s'; break; // Anonymous
			case 2: $Format = '%2$s'; break; // ex. John
			case 3: $NewName = T('Anonymous'); // Localized 'Anonymous' string
			default: $Format = '%1$s %2$s'; break; // ex. Anonymous John
		}
		if ($NewName === Null) $NewName = sprintf($Format, $AnonymousUserName, $AnonymousInfo->Name);
		// TODO: OTHER PERMISSION NAME TO SHOW IP
		if ($bCanViewIp === Null) $bCanViewIp = CheckPermission('Garden.Users.Edit');
		if ($bCanViewIp) {
			$Ip = self::IpAddress($AnonymousInfo->IntIp);
			if ($Ip) $NewName .= ' (' . $Ip .')';
		}
		
		return $NewName;
	}
	
	
	public function GetAnonymousUserID() {
		if ($this->AnonymousUserID === Null) 
			$this->AnonymousUserID = C('Plugins.Anonymouse.AnonymousUserID', 0);
		return $this->AnonymousUserID;
	}
	
	/* =============================== HOOKS */
	
	public function PostController_BeforeFormInputs_Handler($Sender) {
		$Session = Gdn::Session();
		if ($Session->IsValid()) return;
		$AnonymousFormInputs = $Sender->Form->TextBox('YourName', array('placeholder' => T('Your name')));
		if (!self::Config('NoCaptha')) {
			$CapthaImage = Img('plugins/Anonymouse/captcha/imagettfbox.php');
			$CapthaInput = $Sender->Form->TextBox('CaptchaCode', array('placeholder' => T('Code from image')));
			$AnonymousFormInputs .= Wrap($CapthaImage . $CapthaInput, 'div', array('id' => 'CaptchaBox'));
		}				
		echo Wrap($AnonymousFormInputs, 'div');
	}
	
	public function CategoriesController_Render_Before($Sender) {
		$this->DiscussionsController_Render_Before($Sender);
	}
	
	public function DiscussionsController_Render_Before($Sender) {
		if (!isset($Sender->DiscussionData)) return;
		$this->AnonymousDiscussionData = $this->GetAnonymousDiscussionData($Sender->DiscussionData);
		foreach ($Sender->DiscussionData as $Discussion) {
			$this->ReplaceAnonymousNameForDiscussion($Discussion);
		}
	}
	
	public function DiscussionController_Render_Before($Sender) {
		$Session = Gdn::Session();
		if (empty($Sender->CommentData)) return;
		$this->AnonymousCommentData = $this->GetAnonymousCommentData($Sender->CommentData);
		$DiscussionID = GetValueR('Discussion.DiscussionID', $Sender);
		$this->AnonymousDiscussionData = $this->GetAnonymousDiscussionData($DiscussionID);
		
		foreach ($Sender->CommentData as $Comment) $this->ReplaceAnonymousNameForComment($Comment);
		$this->ReplaceAnonymousNameForDiscussion($Sender->Discussion, 'Insert');
		
		$Permission = self::Config('Category', ArrayValue('Vanilla.Discussions.View', $Session->GetPermissions()));
		$Session->SetPermission('Vanilla.Comments.Add', $Permission);
		$AddCommentsPermission = $Session->CheckPermission('Vanilla.Comments.Add', TRUE, 'Category', $Sender->CategoryID);
		
		if (!$Session->IsValid() && $AddCommentsPermission) {
			
			$Sender->AddCssFile('plugins/Anonymouse/anonymouse.css');
			$Sender->AddJsFile('plugins/Anonymouse/anonymouse.js');
			
			$Sender->Form->SetValue('YourName', $this->CookieName());
			$Sender->CaptchaImageSource = 'plugins/Anonymouse/captcha/imagettfbox.php';
			$View = $this->GetView('comment.php');
			$CommentFormHtml = $Sender->FetchView($View);
			$Sender->AddAsset('Content', $CommentFormHtml, 'AnonymousCommentForm');
		}
	}
	
	public function PostController_All_Handler($Sender) {
		if (!isset($Sender->Category)) $Sender->Category = NULL;
		if (!isset($Sender->ShowCategorySelector)) $Sender->ShowCategorySelector = NULL;
		
		$Session = Gdn::Session();
		if ($Session->IsValid()) return;
		if ($this->PostValues !== Null) return;
		
		$Permission = self::Config('Category', ArrayValue('Vanilla.Discussions.View', $Session->GetPermissions()));
		$Session->SetPermission('Vanilla.Comments.Add', $Permission);
		$Session->SetPermission('Vanilla.Discussions.Add');
		
		if ($Sender->Form->IsPostBack() != False) {
			// Start session
			if (!isset($_SESSION)) session_start();
			$Form = $Sender->Form;
			
			$RequestMethod = strtolower($Sender->RequestMethod);

			if ($RequestMethod == 'discussion') {
				$Form->InputPrefix = 'Discussion';
				$DiscussionModel = $Sender->DiscussionModel;
				
				if ($Form->ButtonExists('Preview')) {
					
					$Sender->Discussion = new stdClass();
					$Sender->Discussion->Name = $Form->GetValue('Name', '');
					$Sender->Comment = new stdClass();
					$Sender->Comment->InsertUserID = 0;
					$Sender->Comment->InsertName = $Form->GetFormValue('YourName', 'Anonymous');
					$Sender->Comment->InsertPhoto = '';
					$Sender->Comment->DateInserted = Gdn_Format::Date();
					$Sender->Comment->Body = $Form->GetFormValue('Body', '');
            
/*					try {
						// Too dangerous to fire this event, we can catch some errors
						//$Sender->FireEvent('BeforeDiscussionPreview');
						// Oh. Cant fire anyway, getting fatal error: Allowed memory size of 134217728 bytes exhausted
					} catch (Exception $Ex) {
						// TODO: SHOW ERRORS
					}*/
					
					// TODO: TEST PREVIEW IF JAVASCRIPT IS DISABLED
					if ($Sender->DeliveryType() != DELIVERY_TYPE_ALL) {
						$Sender->View = 'preview';
						return $Sender->Render();
					}
					//$Sender->AddAsset('Content', $Sender->FetchView('preview'));

				}

				if ($Form->ButtonExists('Post Discussion')) {
					
					// TODO: FIX ME, SAME CODE FOR COMMENT
					$this->PostValues = $Form->FormValues();
					$this->CookieName($this->PostValues);
					
					if (!self::Config('NoCaptha')) {
						$bValidCaptcha = $this->IsCapthaValid();
						if (!$bValidCaptcha) $DiscussionModel->Validation->AddValidationResult('Captcha', '%s: Invalid code from image');
					}
					
					$Sender->CategoryID = ArrayValue('CategoryID', $this->PostValues, 0);
					
					foreach (array('Announce', 'Close', 'Sink') as $Field) unset($this->PostValues[$Field]);
               
					// Make sure that the title will not be invisible after rendering
					$Name = $Form->GetFormValue('Name', '');
					if ($Name != '' && Gdn_Format::Text($Name) == '')
						$Form->AddError(T('You have entered an invalid discussion title'), 'Name');
					
					$this->BecomeAnonymousUser();
					$DiscussionModel->SpamCheck = False;
					// Save vanilla discussion
					$DiscussionID = $DiscussionModel->Save($this->PostValues);
					$this->BecomeUnAuthenticatedUser();
					if ($DiscussionID != False ) {
						$this->Save($DiscussionID, $this->PostValues, 'Discussion');
						$Sender->RedirectUrl = Url('/discussion/'.$DiscussionID);
						if ($Sender->DeliveryType() == DELIVERY_TYPE_ALL) Redirect($Sender->RedirectUrl);
						$Sender->Render();
					} else {
						$Form->SetValidationResults( $DiscussionModel->ValidationResults() );
						$Sender->StatusMessage = $Form->Errors();
					}
				}
			} elseif ($RequestMethod == 'comment') {
				$Form->InputPrefix = 'Comment';
				
				$this->PostValues = $Form->FormValues();

				$this->CookieName($this->PostValues);
				
				if ($Form->ButtonExists('MyPreview') || GetIncomingValue('Type') == 'Preview') {
					$Sender->Comment = new StdClass();
					$Sender->Comment->InsertUserID = 0;
					$Sender->Comment->InsertName = $Form->GetFormValue('YourName', 'Anonymous');
					$Sender->Comment->InsertPhoto = '';
					$Sender->Comment->DateInserted = Gdn_Format::Date();
					$Sender->Comment->Body = $Form->GetFormValue('Body', '');
					
					// TODO: TEST PREVIEW IF JAVASCRIPT IS DISABLED
					if ($Sender->DeliveryType() != DELIVERY_TYPE_ALL) {
						$Sender->View = 'preview';
						return $Sender->Render();
					}
				}
				
				if (!self::Config('NoCaptha')) {
					$bValidCaptcha = $this->IsCapthaValid();
					if (!$bValidCaptcha) $Sender->CommentModel->Validation->AddValidationResult('Captcha', '%s: Invalid code from image');
				}

				$this->BecomeAnonymousUser();
				$Sender->CommentModel->SpamCheck = False;
				
				// Save vanilla comment
				
				$CommentID = $Sender->CommentModel->Save($this->PostValues);
				$this->BecomeUnAuthenticatedUser();
				if ($CommentID != False) {
					// Save anonymous comment
					$this->Save($CommentID, $this->PostValues, 'Comment');
					$Sender->RedirectUrl = Url("discussion/comment/$CommentID/#Comment_$CommentID");
					if ($Sender->DeliveryType() == DELIVERY_TYPE_ALL) Redirect($Sender->RedirectUrl);
					$Sender->Render();				
				} else {
					$Form->SetValidationResults( $Sender->CommentModel->ValidationResults() );
					$Sender->StatusMessage = $Form->Errors();
				}
			}
			// Reset captcha key for discussion and comment
			$this->ResetCaptchaKey();
		}
	}
	
	protected function IsCapthaValid() {
		$CaptchaCode = ArrayValue('CaptchaCode', $this->PostValues);
		$CaptchaKey = ArrayValue('CaptchaKey', $_SESSION);
		$bValidCaptcha = ($CaptchaKey && $CaptchaKey == $CaptchaCode);
		return $bValidCaptcha;
	}
	
	public function Gdn_Dispatcher_BeforeControllerMethod_Handler($Sender) {
		$EnabledApplication = $Sender->EventArguments['EnabledApplication'];
		if ($EnabledApplication == 'Vanilla') {
			include_once dirname(__FILE__) . '/modules/class.newanonymousdiscussionmodule.php';
		}
	}
	
	/* ============================== SETUP */
	
	// update from version 2.2.X
	public function UtilityController_AnonymousePluginStructure_Create($Sender) {
		$Sender->Permission('Garden.Settings.Manage');
		$this->Structure();
		Redirect('dashboard/settings/plugins');
	}
	
	public function Structure() {
		$Explicit = False;
		$Construct = Gdn::Structure();
		$SQL = Gdn::SQL();
		$Prefix = $SQL->Database->DatabasePrefix;
		foreach(array('AnonymousComment', 'AnonymousDiscussion') as $TableName) {
			$TableSchema = False;
			try {
				$TableSchema = $SQL->FetchTableSchema($TableName);
			} catch (Exception $Exception) {
			}
			if ($TableSchema == False) continue;
			if (GetValueR('IntIp.Unsigned', $TableSchema) == True) continue;
			$Construct->Query("alter table {$Prefix}$TableName add column _IntIp int(10) unsigned null after IntIp");
			$Construct->Query("update {$Prefix}$TableName set _IntIp = convert(IntIp, unsigned integer)");
			$Construct->Query("alter table {$Prefix}$TableName change column IntIp IntIp int(10) unsigned not null after Name");
			$Construct->Query("update {$Prefix}$TableName set IntIp = _IntIp");
			$Construct->Query("alter table {$Prefix}$TableName drop column _IntIp");
		}

		Gdn::Structure()
			->Table('AnonymousComment')
			->Column('CommentID', 'uint', False, 'primary')
			->Column('Name', 'varchar(30)', True)
			->Column('IntIp', 'uint')
			->Set($Explicit);
		Gdn::Structure()
			->Table('AnonymousDiscussion')
			->Column('DiscussionID', 'uint', False, 'primary')
			->Column('Name', 'varchar(30)', True)
			->Column('IntIp', 'uint')
			->Set($Explicit);
	}
	
	public function Setup(){
		//$UserModel = Gdn::UserModel();
		$AnonymousUserID = C('Plugins.Anonymouse.AnonymousUserID', 0);

		if (!$AnonymousUserID) {
			$Fields = array('Name' => 'Anonymous', 'DateInserted' => Gdn_Format::ToDateTime(), 'Password' => '', 'Email' => '');
			$AnonymousUserID = (int) Gdn::SQL()->Insert('User', $Fields);
			if ($AnonymousUserID > 0) 
				SaveToConfig('Plugins.Anonymouse.AnonymousUserID', $AnonymousUserID);
		}
		
		$this->Structure();
		
	}
}
	

