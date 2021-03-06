<?php if (!defined('APPLICATION')) exit();

class AnonymousModel extends Gdn_Model {
	
#  	public function __construct() {
#  		parent::__construct('AnonymousComment');
#  	}
	
	public static function StaticSave($ObjectID, $FormValues, $Type) {
		$Fields['IntIp'] = RealIpAddress();
		$Name = trim(Gdn_Format::Text(ArrayValue('YourName', $FormValues)));
		if (in_array($Name, array('Your name', T('Your name')))) $Name = Null;
		if (!$Name) $Name = Null;
		$Fields['Name'] = $Name;
		$Type = ucfirst(strtolower($Type));
		$Fields[$Type.'ID'] = $ObjectID;
		return Gdn::SQL()->Insert('Anonymous'.$Type, $Fields);
	}
	
	public static function GetCommentData($CommentData) {
		if ($CommentData instanceof Gdn_DataSet) 
			$CommentData = ConsolidateArrayValuesByKey($CommentData->ResultObject(), 'CommentID');
		if (!is_array($CommentData)) $CommentData = array($CommentData);
		$DataSet = Gdn::SQL()
			->Select()
			->From('AnonymousComment')
			->WhereIn('CommentID', $CommentData)
			->Get()
			->Result();
		$Result = PromoteKey($DataSet, 'CommentID');
		return $Result;
	}
	
	public static function GetDiscussionData($Reference) {
		if ($Reference instanceof Gdn_DataSet) 
			$Reference = ConsolidateArrayValuesByKey($Reference->ResultObject(), 'DiscussionID');
		if (!is_array($Reference)) $Reference = array($Reference);
		$DataSet = Gdn::SQL()
			->Select()
			->From('AnonymousDiscussion')
			->WhereIn('DiscussionID', $Reference)
			->Get()
			->Result();
		$Result = PromoteKey($DataSet, 'DiscussionID');
		return $Result;
	}
	
}