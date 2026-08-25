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
    exit(); // Backstop in case a custom auth provider's requestAuthentication() forgets to exit
}

if (!$enableAndroid) {
    returnInvalidConfiguration();
    exit();
}

// Android's package installer needs the correct type, a size to show progress against, and a
// filename ending in .apk, none of which it can infer from the apk.php URL itself.
header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="' . basename($apkFile) . '"');
$apkFileSize = @filesize($apkFile);
if ($apkFileSize !== false) {
	header('Content-Length: ' . $apkFileSize);
}

readfile($apkFile);
