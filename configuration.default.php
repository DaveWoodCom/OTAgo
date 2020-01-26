<?php
// OTAgo
// - OTA App Distribution System
// - https://github.com/DaveWoodCom/OTAgo
// - Copyright 2020 Dave Wood, Cerebral Gardens Inc.

// Choose one authentication method
// Current options: None, or Simple Auth

// Authentication - None
// $authFile = 'auth/none/none.php';

// Authentication - Simple Auth
$authFile = 'auth/simple/simpleAuth.php';
$simpleAuthTempDirectory = '/tmp';
$simpleAuthTokenLifetime = 3600; // 1 hour (3600 seconds)
$users = array(
	'dave' => 'secr3t',
	'john' => 'hunter2'
);

// Templates
// -- Web
$webTemplate = 'templates/webTemplate.html';
$installURLPlacehHolder = '{{InstallURL}}';

// -- Manifest
$manifestTemplate = 'templates/manifest.plist';
$ipaURLPlacehHolder = '{{IPAURL}}';

// IPA File
$ipaFile = 'MyApp.ipa';

// Can override the baseURL if the detected one in common.php isn't correct.
// $baseURL = 'https://example.com/app/';
