<?php if (!defined('APPLICATION')) exit();

$PluginInfo['Anonymouse'] = array(
	'Name' => 'Anonymouse 2',
	'Description' => 'Anonymous posting.',
	'SettingsUrl' => '/plugin/anonymouse',
	'Version' => '2.0.alpha',
	'Date' => '13 Dec 2010',
	'Author' => '',
	'RequiredApplications' => array('Vanilla' => '>=2.0.16'),
	'AuthorUrl' => False
);

/* =======================
CONFIG:
$Configuration['Plugins']['Anonymouse']['Category'] = array(1,2);

TODO:
Captcha
*/

if (!function_exists('UserAnchor')) {
	function UserAnchor($User, $CssClass = '') {
		static $AnonymousUserID;
		if ($AnonymousUserID === Null) 
			$AnonymousUserID = Gdn::Config('Plugins.Anonymouse.AnonymousUserID', 0);
		if ($CssClass != '') $CssClass = ' class="'.$CssClass.'"';

		if ($AnonymousUserID == $User->UserID) {
			// TODO: FIX ME, $CssClass is lost here
			return $User->Name;
		}
		
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
	
	public $PostValues;
	public $bInitialized;
	
	/* =============================== CONTROLLER */
	
	// TODO: Settings
	
	public function PostController_Render_Before($Sender) {
		$RequestMethod = strtolower($Sender->RequestMethod);
		$Session = Gdn::Session();
		if ($Session->IsValid()) return;
		
		$Sender->AddCssFile($this->GetWebResource('anonymouse.css'));
		$Sender->Form->SetValue('YourName', $this->CookieName());
		
		if ($RequestMethod == 'discussion') {
			$CategoryModel = new Gdn_Model('Category');
			$Permission = C('Plugins.Anonymouse.Category', ArrayValue('Vanilla.Discussions.View', $Session->GetPermissions()));
			$CategoryModel->SQL->WhereIn('CategoryID', $Permission);
			$Sender->CategoryData = $CategoryModel->GetWhere(array('AllowDiscussions' => 1));
		}

	}
	
	/* =============================== MODEL */
	
	protected function GetAnonymousCommentData($CommentData) {
		if ($CommentData instanceof Gdn_DataSet) 
			$CommentData = ConsolidateArrayValuesByKey($CommentData, 'CommentID');
		$DataSet = Gdn::SQL()
			->Select()
			->From('AnonymousComment')
			->WhereIn('CommentID', $CommentData)
			->Get();
		$Result = PromoteKey($DataSet, 'CommentID');
		return $Result;
	}
	
	protected function GetAnonymousDiscussionData($Reference) {
		if (!is_array($Reference)) $Reference = array($Reference);
		$DataSet = Gdn::SQL()
			->Select()
			->From('AnonymousDiscussion')
			->WhereIn('DiscussionID', $Reference)
			->Get();
		$Result = PromoteKey($DataSet, 'DiscussionID');
		return $Result;
	}
	
	protected function Save($ObjectID, $FormValues, $Type) {
		$Fields['IntIp'] = ip2long(RemoteIp());
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
		if (!is_string($Name)) $Name = GetValue('YourName', $Name);
		setcookie('YourName', $Name, PHP_INT_MAX, '/');
	}
	
	protected function BecomeUnAuthenticatedUser() {
		$Session = Gdn::Session();
		if (!$Session->IsValid()) return;
		$Session->UserID = 0;
		$Session->User = False;
	}
	
	protected function BecomeAnonymousUser() {
		static $AnonymousUserID;
		if ($AnonymousUserID === Null) 
			$AnonymousUserID = Gdn::Config('Plugins.Anonymouse.AnonymousUserID', 0);
		$Session = Gdn::Session();
		if ($Session->IsValid()) return;
		$Session->UserID = $AnonymousUserID;
		$Session->User = new StdClass(); // for php < 5.3 
		$Session->User->UserID = $AnonymousUserID;
		$Session->User->Admin = 0;
		$Session->User->HourOffset = 0;
		$Session->User->Name = 'Anonymous';
		$Session->User->CountNotifications = 0;
	}
	
	/* =============================== HOOKS */
	
	public function PostController_BeforeFormInputs_Handler($Sender) {
		$Session = Gdn::Session();
		if ($Session->IsValid()) return;
		echo $Sender->Form->TextBox('YourName', array('placeholder' => T('Your name')));
	}
	
	public function DiscussionController_BeforeCommentMeta_Handler($Sender) {
		$Author = $Sender->EventArguments['Author'];
		$Object = $Sender->EventArguments['Object'];
		$Type = $Sender->EventArguments['Type'];
		if ($Type == 'Comment') {
			if (array_key_exists($Object->CommentID, $this->AnonymousCommentData)) {
				$Anonymous = $this->AnonymousCommentData[$Object->CommentID];
				//$Author->UserID = -PHP_INT_MAX;
				// Change username Anonymous to Anonymous X
				$Author->Name = sprintf('%s %s', $Author->Name, $Anonymous->Name);
			}
		}/* elseif ($Type == 'Discussion') {
			if (array_key_exists($Object->DiscussionID, $this->AnonymousDiscussionData)) {
				$Anonymous = $this->AnonymousDiscussionData[$Object->DiscussionID];
				$Author->Name = sprintf('%s %s', $Author->Name, $Anonymous->Name);
			}
		}*/
	}
	
	public function DiscussionController_Render_Before($Sender) {
		$Session = Gdn::Session();
		$this->AnonymousCommentData = $this->GetAnonymousCommentData($Sender->CommentData);
		$DiscussionID = GetValueR('Discussion.DiscussionID', $Sender);
		$this->AnonymousDiscussionData = $this->GetAnonymousDiscussionData($DiscussionID);
		
		$Permission = C('Plugins.Anonymouse.Category', ArrayValue('Vanilla.Discussions.View', $Session->GetPermissions()));
		$Session->SetPermission('Vanilla.Comments.Add', $Permission);
		$AddCommentsPermission = $Session->CheckPermission('Vanilla.Comments.Add', TRUE, 'Category', $Sender->CategoryID);
		
		if (!$Session->IsValid() && $AddCommentsPermission) {
			$Sender->AddCssFile($this->GetWebResource('anonymouse.css'));
			$Sender->Form->SetValue('YourName', $this->CookieName());
			$View = $this->GetView('comment.php');
			$CommentFormHtml = $Sender->FetchView($View);
			$Sender->AddAsset('Content', $CommentFormHtml);
		}
	}

	public function PostController_All_Handler($Sender) {
		$Session = Gdn::Session();
		if ($Session->IsValid()) return;
		if ($this->PostValues !== Null) return;
		
		$Permission = C('Plugins.Anonymouse.Category', ArrayValue('Vanilla.Discussions.View', $Session->GetPermissions()));
		$Session->SetPermission('Vanilla.Comments.Add', $Permission);
		$Session->SetPermission('Vanilla.Discussions.Add');
		
		if ($Sender->Form->IsPostBack() != False) {
			$Form = $Sender->Form;
			$RequestMethod = strtolower($Sender->RequestMethod);
			if ($RequestMethod == 'discussion' || $RequestMethod == 'anonymousdiscussion') {
				$Form->InputPrefix = 'Discussion';
				if ($Form->ButtonExists('Post Discussion')) {
					$this->PostValues = $Form->FormValues();
					$Sender->CategoryID = ArrayValue('CategoryID', $this->PostValues, 0);
					$this->CookieName($this->PostValues);
					$DiscussionModel = $Sender->DiscussionModel;
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
				// TODO: Preview
			} elseif ($RequestMethod == 'comment') {
				$Form->InputPrefix = 'Comment';
				$this->PostValues = $Form->FormValues();
				$this->CookieName($this->PostValues);
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
		}
	}
	
	public function Gdn_Dispatcher_BeforeControllerMethod_Handler($Sender) {
		$EnabledApplication = $Sender->EventArguments['EnabledApplication'];
		if ($EnabledApplication == 'Vanilla') {
			include_once dirname(__FILE__) . '/modules/class.newanonymousdiscussionmodule.php';
			$this->bInitialized = True;
		}
	}
	
	/* ============================== SETUP */
	
	public function Structure() {
		Gdn::Structure()
			->Table('AnonymousComment')
			->Column('CommentID', 'uint', False, 'primary')
			->Column('Name', 'varchar(30)', True)
			->Column('IntIp', 'int')
			->Set();
		Gdn::Structure()
			->Table('AnonymousDiscussion')
			->Column('DiscussionID', 'uint', False, 'primary')
			->Column('Name', 'varchar(30)', True)
			->Column('IntIp', 'int')
			->Set();
	}
	
	public function Setup(){
		//$UserModel = Gdn::UserModel();
		$AnonymousUserID = Gdn::Config('Plugins.Anonymouse.AnonymousUserID', 0);

		if (!$AnonymousUserID) {
			$Fields = array('Name' => 'Anonymous', 'DateInserted' => Gdn_Format::ToDateTime(), 'Password' => '', 'Email' => '');
			$AnonymousUserID = (int) Gdn::SQL()->Insert('User', $Fields);
			if ($AnonymousUserID > 0) 
				SaveToConfig('Plugins.Anonymouse.AnonymousUserID', $AnonymousUserID);
		}
		
		$this->Structure();
		
	}
}
	
	// Settings
/*	public function SettingsController_Anonymouse_Create($Sender) {
		$Sender->Permission('Garden.Settings.Manage');
		$Sender->Title('Anonymouse');
		$Sender->AddSideMenu('settings/anonymouse');
		$SQL = Gdn::SQL();
		$Sender->TagData = $SQL
			->Select('t.*')
			->From('Tag t')
			->OrderBy('t.Name', 'asc')
			->OrderBy('t.CountDiscussions', 'desc')
			->Get();
		//$Sender->Render('plugins/Tagging/views/tagging.php');
	}*/


	
	/*	public function PostController_AnonymousDiscussion_Create($Sender) {
		$CategoryID = ArrayValue(0, $Sender->RequestArgs);
		$Session = Gdn::Session();
		
		$Sender->AddJsFile('jquery.autogrow.js');
		$Sender->AddJsFile('post.js');
		
		$UseCategories = C('Vanilla.Categories.Use');
		if ($UseCategories) {
			$CategoryModel = new Gdn_Model('Category');
			$Permission = C('Plugins.Anonymouse.Category', ArrayValue('Vanilla.Discussions.View', $Session->GetPermissions()));
			$CategoryModel->SQL->WhereIn('CategoryID', $Permission);
			$Sender->CategoryData = $CategoryModel->GetWhere(array('AllowDiscussions' => 1));
		} else $CategoryID = 0;
		$Sender->CategoryID = $CategoryID;
		
		$Sender->AddJsFile('jquery.autogrow.js');
		$Form = $Sender->Form;
		$DiscussionModel = $Sender->DiscussionModel;

		if ($Form->IsPostBack() != False) {
			$Preview = $Form->ButtonExists('Preview') ? True : False;
			
		} else {
			$Form->SetData(array('CategoryID' => $CategoryID));
		}
		$Sender->View = $this->GetView('discussion.php');
		$Sender->Render();
		
		
		$this->BecomeAnonymousUser();
		
		// DUPPY FIX ME
		$Session = Gdn::Session();
		$Permission = C('Plugins.Anonymouse.Category', ArrayValue('Vanilla.Discussions.View', $Session->GetPermissions()));
		$Session->SetPermission('Vanilla.Comments.Add', $Permission);
		
		$Session->SetPermission('Vanilla.Discussions.Add');
		
		$Sender->View = 'Discussion';
		$Sender->Discussion($CategoryID);
		$this->BecomeUnAuthenticatedUser();
	}*/
	



	
/*	// FAIL. Fires for Authenticated User only
	public function Base_AfterGetSession_Handler($Sender) {
		$User =& $Sender->EventArguments['User'];
		$User->Permissions = Gdn_Format::Unserialize($User->Permissions);
		//$Session = Gdn::Session();
		//$Session->UserID = 13;
		//$Session->SetPermission('Vanilla.Discussions.Add', array(1)); // second method
		//d($Session);
	}*/
	
/*	public function DiscussionController_AfterComment_Handler($Sender) {
		static $LastCommentID;
		//$LastComment = $CommentData->LastRow();
		//$CommentData = $Sender->Data['CommentData'];
		$Object = $Sender->EventArguments['Object'];
		$Type = $Sender->EventArguments['Type'];
		if ($Type == 'Discussion') {
			$LastCommentID = $Object->LastCommentID;
		} elseif ($Type == 'Comment') {
			// TODO: FIX ME IF NOT LAST PAGE

			if ($Object->CommentID == $LastCommentID) {
				$Session = Gdn::Session();
				$Session->UserID = 14;
				$Session->User->UserID = 14;
				$Session->User->Admin = 0;
				$Session->User->HourOffset = 0;
				$Session->User->Name = 'Anonymouse';
				$Session->User->CountNotifications = 0;
				
				$Session->SetPermission('Vanilla.Comments.Add', array(1));
			}
		}
	}*/
	
	// OBSOLETE
/*	public function DiscussionController_AfterCommentTabs_Handler() {
		// DISABLE FAKE USER ID
		$Session = Gdn::Session();
		$Session->UserID = 0;
	}*/





















// workspace
//$AnonymousePlugin = new AnonymousePlugin(); $AnonymousePlugin->Setup(); d('setup ok');