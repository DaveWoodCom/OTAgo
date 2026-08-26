<?php
// OTAgo
// - OTA App Distribution System
// - https://github.com/DaveWoodCom/OTAgo
// - Copyright 2020 Dave Wood, Cerebral Gardens Inc.

// OTAgo works out the URL it's served from (scheme + host) from the request. Override it here
// if what it detects isn't correct, e.g. when the public URL differs from what PHP sees.
// $baseURL = 'https://example.com/app/';

// Only set this to true if OTAgo sits behind a reverse proxy / load balancer that terminates
// TLS and sets X-Forwarded-Proto (and optionally X-Forwarded-Host) on every request. With it
// enabled those headers are trusted for the base URL; without a proxy they'd be spoofable.
$trustProxyHeaders = false;

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
// Passwords can be stored as password_hash() digests (recommended) or as plaintext. Generate
// a digest with:
//   php -r 'echo password_hash("your-password", PASSWORD_DEFAULT), PHP_EOL;'
// Digests start with "$2y$" (bcrypt) or "$argon2"; anything else is treated as plaintext.
$users = array(
	'dave' => '$2y$12$bO3l.SZkEHK47yAAhTGiueJTwBrxeIguzsgI2BNilhSDDxTBRLBSK', // "secr3t"
	'john' => 'hunter2'  // plaintext still works, but prefer a hash
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
