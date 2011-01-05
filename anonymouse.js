jQuery(document).ready(function(){

	// 1. Update captcha if post fails
	$("div.Errors > ul").livequery(function(){
		var newSrc = gdn.url('plugins/Anonymouse/captcha/imagettfbox.php') + '?' + Math.random();
		$('#CaptchaBox img').first().attr('src', newSrc);

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