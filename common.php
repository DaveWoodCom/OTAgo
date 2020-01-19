<?php
// OTAgo
// - OTA App Distribution System
// - https://github.com/DaveWoodCom/OTAgo
// - Copyright 2020 Dave Wood, Cerebral Gardens Inc.

$includePort = $_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443;
$baseURL = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . ($includePort ? ':' . $_SERVER['SERVER_PORT'] : '') . '/';

function preventCaching() {
	header('Cache-Control: no-cache, no-store, must-revalidate');
	header('Pragma: no-cache');
	header('Expires: 0');
}

function requestBasicAuthentication($realm) {
	header('WWW-Authenticate: Basic realm="' . $realm . '"');
    http_response_code(401);
    exit;
}
