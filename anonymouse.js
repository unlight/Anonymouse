jQuery(document).ready(function(){

	// 1. Update captcha if post fails
	$("div.Errors > ul").livequery(function(){
		var imagesrc = gdn.definition('WebRoot', '/') + '/plugins/Anonymouse/captcha/imagettfbox.php' + '?' + Math.random();
		imagesrc = imagesrc.replace(/^:\/\//, "/");
		// TODO: WAITING FOR BUG FIX https://github.com/vanillaforums/Garden/issues/859
		$('#CaptchaBox img').first().attr('src', imagesrc);
	});
	
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
	


	
});