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

readfile($ipaFile);
