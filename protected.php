<?php
// OTAgo
// - OTA App Distribution System
// - https://github.com/DaveWoodCom/OTAgo
// - Copyright 2020 Dave Wood, Cerebral Gardens Inc.

require_once('common.php');
if (file_exists('configuration.php')) {
	require_once('configuration.php');
}
else {
	require_once('configuration.default.php');
}
require_once($authFile);

preventCaching();

if (!isValidUser()) {
    requestAuthentication();
    exit();
}

$userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
if ($enableAndroid && stripos($userAgent, 'android') !== false) {
	// Android
	$installURL = $baseURL . urlencode($apkFile);
}
elseif ($enableIOS) {
    // Sadly we can't detect iOS/iPadOS via the User-Agent, so for now, just assuming iOS
    // TODO: Use a client side method to detect iPhone vs iPad (or macOS/Linux etc)
    $manifestURL = $baseURL . 'manifest.php?' . makeURLQueryString(queryStringAuthParameters(), '&');
	$installURL = 'itms-services://?action=download-manifest&url=' . urlencode($manifestURL);
}
else {
    returnInvalidConfiguration();
    exit();
}

$html = file_get_contents($webTemplate);
$html = str_replace($installURLPlacehHolder, $installURL, $html);
echo $html;
