Anonymouse = {
	SaveDraftTimer: 15000
}; // TODO: CONFIG

jQuery(document).ready(function(){
	
	// PREVENT BUG: https://github.com/vanillaforums/Garden/issues/859
	var WebRoot = gdn.combinePaths(gdn.definition('WebRoot', '/'), '/');
	
	// 1. Update captcha if post fails
	$("div.Errors > ul").livequery(function(){
		var imagesrc = WebRoot + 'plugins/Anonymouse/captcha/imagettfbox.php' + '?' + Math.random();
		var $img = $('#CaptchaBox img');
		if ($img.length > 0) $img.first().attr('src', imagesrc);
	});
	
	// 2. Preview
	$('#Form_Comment div.Preview').livequery(function(){
		var sender = this;
		// copy from applications/vanilla/js/discussion.js
		function resetCommentForm() {
			var parent = $(sender).parents('div.CommentForm');
			$(parent).find('li.Active').removeClass('Active');
			$('a.WriteButton').parents('li').addClass('Active');
			$(parent).find('div.Preview').remove();
			$(parent).find('textarea').show();
			$('span.TinyProgress').remove();
		}
		$(this).click(resetCommentForm);
	});
	
	// 3. Save draft (using localStorage if available)
	var DiscussionID = gdn.definition('DiscussionID', false);
	var AnonymousCommentForm = $('.AnonymousCommentForm').first();
	var FormBody = $('#Form_Body', AnonymousCommentForm);
	var Anonymouse_SaveDraft = function() {
		var text = $.jStorage.get('Discussion_'+DiscussionID, '');
		if (text != FormBody.val()) {
			$.jStorage.set('Discussion_'+DiscussionID, FormBody.val());
			gdn.inform('Draft saved …');
		}
		setTimeout(Anonymouse_SaveDraft, Anonymouse.SaveDraftTimer);
	}

	if (DiscussionID && AnonymousCommentForm.size() > 0) {
		$.getScript(WebRoot + 'plugins/Anonymouse/vendors/jStorage/jstorage.min.js', function(){
			if ($.jStorage.storageAvailable()) {
				DiscussionDraft = $.jStorage.get('Discussion_'+DiscussionID, '');
				if (typeof(DiscussionDraft) == 'string' && DiscussionDraft.length > 0) FormBody.val(DiscussionDraft);
				setTimeout(Anonymouse_SaveDraft, Anonymouse.SaveDraftTimer);
			}
		});
	}
	
});