jQuery(document).ready(function(){

	$("div.Errors > ul").livequery(function(){
		var newSrc = gdn.url('plugins/Anonymouse/captcha/imagettfbox.php') + '?' + Math.random();
		$('#CaptchaBox img').first().attr('src', newSrc);

	});
	
});