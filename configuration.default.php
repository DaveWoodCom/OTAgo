<?php
// OTAgo
// - OTA App Distribution System
// - https://github.com/DaveWoodCom/OTAgo
// - Copyright 2020 Dave Wood, Cerebral Gardens Inc.

// Authentication
$authFile = 'auth/simple/simpleAuth.php';
$users = array(
	'dave' => 'secr3t',
	'john' => 'hunter2'
);

// Web Template
$webTemplate = 'templates/webTemplate.html';
$installURLPlacehHolder = '{{InstallURL}}';

// Manifest
$manifestTemplate = 'templates/manifest.plist';
$ipaURLPlacehHolder = '{{IPAURL}}';

// IPA File
$ipaFile = 'MyApp.ipa';

// Can override the baseURL if the detected one in common.php isn't correct.
// $baseURL = 'https://example.com/app/';
