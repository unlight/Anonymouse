<?php
# CAPTCHA
# Date: 12 Sep 2009
# Released under the terms and conditions of the GNU GPL2 [www.gnu.org]
# Based on PHP-Class hn_captcha by Horst Nogajski, coding@nogajski.de
# http://hn273.users.phpclasses.org/browse/package/1569.html

# Dont forget reset CaptchaKey (set to random value) if login/try is failed

$Configuration = array();
$Configuration['NumChars'] = 3;
$Configuration['CharRange'] = '/[0-9]/'; // preg_match expression
$Configuration['CharWidth'] = 25;
$Configuration['CharHeight'] = 25;
$Configuration['CharSpace'] = $Configuration['CharWidth'] * 0.1;
$Configuration['Padding'] = 5;
$Configuration['Noise'] = 5;
//$Configuration['BorderColor'] = '0,0,0'; // rgb color
$Configuration['glitf.ttf'] = '/[014]/'; // not this chars (looks ugly)
$Configuration['Bodiemf.ttf'] = '/1/';
$Configuration['StarlightSansJL.ttf'] = '/1/';
$Configuration['AvanteDrp.ttf'] = '/7/';
$Configuration['Moonstar.ttf'] = '/[^2356]/'; // only this chars
$Configuration['actionj.ttf'] = '/[97]/';
$Configuration['DenneAliens.ttf'] = '/[9]/';
$Configuration['patriot.ttf'] = '/[1]/';

// $_SESSION['CaptchaKey'] = 'private key will be here';

set_error_handler('ErrorHandler');
error_reporting(E_ALL);
ini_set('html_errors', 0);
session_start();

$Fonts = glob(dirname(__FILE__).'/fonts/*.ttf');
$CountFonts = count($Fonts);
$PrivateKey = '';
$CharImages = array();

$Dummy = ImageCreateTrueColor(1, 1);
$Color = RandomColor(210, 255);
$BackgroundColor = ImageColorAllocate($Dummy, $Color->R, $Color->G, $Color->B);
ImageDestroy($Dummy);

for ($i = 1; $i <= $Configuration['NumChars']; $i++) {
	
	$Index = array_rand($Fonts);
	$Font = $Fonts[$Index];
	if ($i < count($Fonts)) unset($Fonts[$Index]);
	
	$Custom = '';
	$FontTTF = basename($Font);
	if (array_key_exists($FontTTF, $Configuration)) $Custom = $Configuration[$FontTTF];
	$Char = RandomChar($Configuration['CharRange'], $Custom);
	
	$PrivateKey .= $Char;

	$Box = ImageTTFBbox(100, 0, $Font, $Char);
	$CharWidth = abs($Box[4] - $Box[0]) * 1.1;
	$CharHeight = abs($Box[5] - $Box[1]) * 1.01;
	if($CharWidth == 0 || $CharHeight == 0) trigger_error('Failed calcualte bounding box. Font: '.basename($Font));
	
	$CharImage = ImageCreateTrueColor($CharWidth, $CharHeight);
	ImageFilledRectangle($CharImage, 0, 0, $CharWidth, $CharHeight, $BackgroundColor);
	
	// shadow 1
	$C = RandomColor(127, 255);
	$Shadow = ImageColorAllocate($CharImage, $C->R, $C->G, $C->B);
	ImageTTFText($CharImage, 99, 0, 20, abs($Box[5]), $Shadow, $Font, $Char);
	
	// shadow 2
	// not yet
	
	// text
	$C = RandomColor(0, 120);
	$Color = ImageColorAllocate($CharImage, $C->R, $C->G, $C->B);
	ImageTTFText($CharImage, 100, 0, 2, abs($Box[5]), $Color, $Font, $Char);
	
	$CharImages[] = array(
		'Resource' => $CharImage,
		'Width' => $CharWidth,
		'Height' => $CharHeight
	);
	//Header('Content-type: image/jpeg');	ImageJpeg($CharImage);	die;
}

// set session private key
$_SESSION['CaptchaKey'] = $PrivateKey;

// captcha height and width
$W = $Configuration['CharWidth'] * $Configuration['NumChars']; // chars width
$W += ($Configuration['NumChars'] - 1) * $Configuration['CharSpace']; // space between chars
$W += $Configuration['Padding'] * 2; // padding left/right
$H = $Configuration['CharHeight'] + 2 * $Configuration['Padding']; // padding top/bottom

// captcha image
$Image = ImageCreateTrueColor($W, $H);
ImageFill($Image, 0, 0, $BackgroundColor); # debug
$CurX = $Configuration['Padding'];

// todo: transparent text
foreach ($CharImages as $CharImage) {
	// $Configuration['Padding'] = padding-top
	ImageCopyResampled($Image, $CharImage['Resource'], $CurX, $Configuration['Padding'], 0, 0, 
		$Configuration['CharWidth'], $Configuration['CharHeight'], $CharImage['Width'], $CharImage['Height']);
	$CurX += $Configuration['CharWidth'] + $Configuration['CharSpace'];
}

// border
if (array_key_exists('BorderColor', $Configuration)) {
	list($R, $G, $B) = explode(',', $Configuration['BorderColor']);
	$BorderColor = ImageColorAllocate($Image, $R, $G, $B);
	ImageRectangle($Image, 0, 0, $W-1, $H-1, $BorderColor);
}

header('Content-type: image/jpeg');
ImageJpeg($Image);

# ==================================================
####################################################

function ErrorHandler($N, $S, $File, $Line) {
	error_log("$S (Line: $Line)\n", 3, date('d-M-Y').'.log');
	$ErrorImage = ImageCreateTrueColor(150, 50);
	$ErrorColor = ImageColorAllocate($ErrorImage, 250, 10, 10);
	$N = 0;
	foreach(explode(': ', $S) as $Error) ImageString($ErrorImage, 2, 5, $N++ * 12,  $Error, $ErrorColor);
	@Header('Content-type: image/jpeg');
	ImageJPEG($ErrorImage);
	ImageDestroy($ErrorImage);
	die();
}

function Clamp($V, $A, $B) {
	if ($V > $B) return $B;
	else if ($V < $A) return $A;
	else return $V;
}

function RandomChar($Match, $Custom = '') {
	$RandomString = '';
	do {
		for ($i = 0; $i < 18; $i++) $RandomString .= chr(mt_rand(48, 122));
		if ($Custom != '') $RandomString = preg_replace($Custom, '', $RandomString);
		$Length = strlen($RandomString);
		$C = '';
		if ($Length > 0) {
			$Index = mt_rand(0, $Length - 1);
			$C = $RandomString{$Index};
		}
	} while(!($C != '' && preg_match($Match, $C)));
	return $C;
}

function RandomColor($Min, $Max) {
	$C = new StdClass();
	$C->R = mt_rand($Min, $Max);
	$C->G = mt_rand($Min, $Max);
	$C->B = mt_rand($Min, $Max);
	return $C;
}


function d() {
	$a = func_get_args();
	echo "<pre>";
	foreach($a as $k => $v) printf("%s\n", print_r($v, 1));
	die;
}



