<?php if (!defined('APPLICATION')) exit();

class NewDiscussionModule extends Gdn_Module {
	
	public function AssetTarget() {
		return 'Panel';
	}
	
	public function ToString() {
		$CategoryID = GetValue('CategoryID', $this->_Sender);
		if (!is_numeric($CategoryID) || $CategoryID < 0) $CategoryID = 0;
		$String = Anchor(T('Start a New Discussion'), '/post/discussion/'.$CategoryID, 'BigButton NewDiscussion');
		return $String;
	}
}