<?php
// OTAgo
// - OTA App Distribution System
// - https://github.com/DaveWoodCom/OTAgo
// - Copyright 2020 Dave Wood, Cerebral Gardens Inc.

require_once('common.php');
require_once('configuration.default.php');
require_once($authFile);

$username = $_GET['u'];
$password = $_GET['p'];
if (empty($username)) {
	$username = $_SERVER['PHP_AUTH_USER'];
}
if (empty($password)) {
	$password = $_SERVER['PHP_AUTH_PW'];
}

preventCaching();

if (!isValidUser($username, $password)) {
    requestAuthentication();
    exit();
}

$manifest = file_get_contents($manifestTemplate);

$ipaURL = $baseURL . 'ipa.php?u=' . urlencode($username) . '&amp;p=' . urlencode($password);
$manifest = str_replace($ipaURLPlacehHolder, $ipaURL, $manifest);

echo $manifest;
