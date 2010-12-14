jQuery(document).ready(function(){
	
	$('#Error_Form_Captcha').livequery(function(){
		var newSrc = gdn.url('plugins/Anonymouse/captcha/imagettfbox.php') + '?' + Math.random();
		console.log(newSrc);
		$('#CaptchaBox img').first().attr('src', newSrc);

	});
	
});