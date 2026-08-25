<?php
// OTAgo
// - OTA App Distribution System
// - https://github.com/DaveWoodCom/OTAgo
// - Copyright 2020 Dave Wood, Cerebral Gardens Inc.

// Can override the baseURL if the detected one in common.php isn't correct.
// $baseURL = 'https://example.com/app/';

// Choose one authentication method
// Current options: None, or Simple Auth

// Authentication - None
// $authFile = 'auth/none/none.php';

// Authentication - Simple Auth
$authFile = 'auth/simple/simpleAuth.php';
// Where Simple Auth stores its token files. Defaults to the `tokens` directory that ships
// with OTAgo, which is guarded by an index.html and an .htaccess. Only override this with a
// directory that other users on the host can't read or write - never a shared /tmp.
// $simpleAuthTempDirectory = '/var/lib/otago/tokens';
// Secret used to sign token files. Leave it unset and OTAgo generates one (0600) inside the
// token directory; set it explicitly if you run several servers off shared storage.
// $simpleAuthSecret = 'put-a-long-random-string-here';
$simpleAuthTokenLifetime = 3600; // 1 hour (3600 seconds)
$users = array(
	'dave' => 'secr3t',
	'john' => 'hunter2'
);

// Templates
// -- Web
$webTemplate = 'templates/webTemplate.html';
$installURLPlaceholder = '{{InstallURL}}';

// Supported OSes
// -- iOS/iPadOS
$enableIOS = true;
if ($enableIOS) {
	// -- Manifest
	$manifestTemplate = 'templates/manifest.plist';
	$ipaURLPlaceholder = '{{IPAURL}}';

	// IPA File
	$ipaFile = 'MyApp.ipa';
}

// -- Android
$enableAndroid = true;
if ($enableAndroid) {
	// APK File
	$apkFile = 'MyApp.apk';
}
