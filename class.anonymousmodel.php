<?php if (!defined('APPLICATION')) exit();

class AnonymousModel extends Gdn_Model {
	
#  	public function __construct() {
#  		parent::__construct('AnonymousComment');
#  	}
	
	public static function GetCommentData($CommentData) {
		if ($CommentData instanceof Gdn_DataSet) 
			$CommentData = ConsolidateArrayValuesByKey($CommentData->ResultObject(), 'CommentID');
		$DataSet = Gdn::SQL()
			->Select()
			->From('AnonymousComment')
			->WhereIn('CommentID', (array)$CommentData)
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