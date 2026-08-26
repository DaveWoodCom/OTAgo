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

// The download is cached nowhere: preventCaching() sends no-store because the URL carries the
// user's credentials in its query string, so an intermediary must not keep a copy. Resumed
// installs still work - that's what the Range support in sendFile() below is for, rather than
// a cache revalidating a partial response.
preventCaching();

if (!isValidUser()) {
    requestAuthentication();
    exit(); // Backstop in case a custom auth provider's requestAuthentication() forgets to exit
}

if (!$enableIOS) {
    returnInvalidConfiguration();
    exit();
}

// iOS's install agent needs the correct type, a size to show progress against and a filename
// ending in .ipa, none of which it can infer from the ipa.php URL itself.
sendFile($ipaFile, 'application/octet-stream');
