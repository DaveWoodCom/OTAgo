<?php
// OTAgo
// - OTA App Distribution System
// - https://github.com/DaveWoodCom/OTAgo
// - Copyright 2020 Dave Wood, Cerebral Gardens Inc.

// PHPUnit bootstrap. OTAgo is a set of plain scripts driven by globals rather than a library,
// so we set up a request-shaped environment once and let the test cases vary the globals they
// care about.
//
// PHPUnit includes this file from inside a method, so plain assignments here would be local
// variables. Everything OTAgo reads with `global` has to go into $GLOBALS explicitly.

$otagoRoot = dirname(__DIR__);

// common.php builds $baseURL from $_SERVER at include time, so it needs a plausible request.
// BaseURLTest calls detectBaseURL() directly rather than relying on these.
$_SERVER['REQUEST_SCHEME'] = 'https';
$_SERVER['HTTP_HOST'] = 'ota.example.com';
$_GET = array();

// Simple Auth's configuration. Point it at a throwaway token directory with a fixed secret so
// tests never read or write a real deployment's tokens.
$GLOBALS['simpleAuthTempDirectory'] = sys_get_temp_dir() . '/otago-tests-' . getmypid();
$GLOBALS['simpleAuthTokenLifetime'] = 3600;
$GLOBALS['simpleAuthTokenPrefix'] = 'simpleAuthToken';
$GLOBALS['simpleAuthTokenExtension'] = 'otago';
$GLOBALS['simpleAuthSecret'] = 'otago-test-secret-not-a-real-one';
$GLOBALS['users'] = array();

require_once $otagoRoot . '/common.php';

// Only one auth provider can be loaded per process: auth/none/none.php declares the same
// function names as auth/simple/simpleAuth.php.
require_once $otagoRoot . '/auth/simple/simpleAuth.php';

register_shutdown_function(function () {
	$directory = $GLOBALS['simpleAuthTempDirectory'];
	if (!is_dir($directory)) {
		return;
	}
	foreach (scandir($directory) as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}
		@unlink($directory . '/' . $entry);
	}
	@rmdir($directory);
});
