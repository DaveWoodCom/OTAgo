<?php
// OTAgo
// - OTA App Distribution System
// - https://github.com/DaveWoodCom/OTAgo
// - Copyright 2020 Dave Wood, Cerebral Gardens Inc.

// This is a simple authentication system, using an array of valid username/password pairs.
// You can swap in an alternate authentication system that connects to a database, or
// uses OAuth etc.

/////////////////////////////////////////////////////////////////////////////////////////
// Required Functions
/////////////////////////////////////////////////////////////////////////////////////////

// You must include a function with this signature.
// Return true/false if the current user is a valid user.
function isValidUser() {
	global $users;
	global $username;
	global $password;
	global $requestUUID;
		
	if (empty($username)) {
		return false;
	}

	if ($users[$username] == $password) {
		touch(userTokenFilename());
		return true;
	}
	else if (file_exists(userTokenFilename())) {
		return true;
	}
	
	return false;
}

// You must include a function with this signature.
// Return an associated array with name/value pairs to be appended to OTAgo URLs (to pass the authentication through).
function queryStringAuthParameters() {
	global $username;
	global $requestUUID;
	
	$parameters = array(
		'u' => $username,
		't' => $requestUUID,
	);

	return $parameters;
}

// You must include a function with this signature.
// Send a response to trigger an authentication request, then exit.
function requestAuthentication() {
	requestBasicAuthentication('Authentication Required');
    exit;
}

/////////////////////////////////////////////////////////////////////////////////////////
// Configuration Options
/////////////////////////////////////////////////////////////////////////////////////////

// You shouldn't need to change these under normal conditions, but you can if you need to
// (if you're using multiple OTAgo set ups with the same temp directory for example).
// To change these, add them to your configuration.php file, don't change them here.
if (!isset($simpleAuthTokenPrefix)) {
	$simpleAuthTokenPrefix = 'simpleAuthToken';
}
if (!isset($simpleAuthTokenExtension)) {
	$simpleAuthTokenExtension = 'otago';
}
if (!isset($simpleAuthTokenLifetime)) {
	$simpleAuthTokenLifetime = 3600; // 1 hour (3600 seconds)
}
if (!isset($simpleAuthTempDirectory)) {
	$simpleAuthTempDirectory = '/tmp';
}

/////////////////////////////////////////////////////////////////////////////////////////
// Supporting Functions
/////////////////////////////////////////////////////////////////////////////////////////

function initalizeRequest() {
	global $users;
	global $username;
	global $password;
	global $requestUUID;
	
	if (!isset($users)) {
		$users = array();
	}
	
	if (isset($_GET['u'])) {
		$username = $_GET['u'];
	}
	if (empty($username) && isset($_SERVER['PHP_AUTH_USER'])) {
		$username = $_SERVER['PHP_AUTH_USER'];
	}

	if (isset($_SERVER['PHP_AUTH_PW'])) {
		$password = $_SERVER['PHP_AUTH_PW'];
	}

	if (isset($_GET['t'])) {
		$requestUUID = $_GET['t'];
	}
	if (empty($requestUUID)) {
		$requestUUID = randomUUID();
	}
}

function randomUUID() {
	$groups = array();
	
	for ($i=0; $i<8; ++$i) {
		if (function_exists('random_bytes')) {
			$groups[] = bin2hex(random_bytes(2));
		}
		elseif (function_exists('mcrypt_create_iv')) {
			$groups[] = bin2hex(mcrypt_create_iv(2, MCRYPT_DEV_URANDOM));
		} 
		elseif (function_exists('openssl_random_pseudo_bytes')) {
			$groups[] = bin2hex(openssl_random_pseudo_bytes(2));
		}
	}
	
	return sprintf('%s%s-%s-%s-%s-%s%s%s', $groups[0], $groups[1], $groups[2], $groups[3], $groups[4], $groups[5], $groups[6], $groups[7]);
}

function userTokenFilename() {
	global $username;
	global $requestUUID;
	
	global $simpleAuthTempDirectory;
	global $simpleAuthTokenPrefix;
	global $simpleAuthTokenExtension;

	return sprintf('%s/%s_%s_%s.%s', $simpleAuthTempDirectory, $simpleAuthTokenPrefix, base64_encode($username), $requestUUID, $simpleAuthTokenExtension);
}

function cleanTempDir() {
	global $simpleAuthTempDirectory;
	global $simpleAuthTokenPrefix;
	global $simpleAuthTokenExtension;
	global $simpleAuthTokenLifetime;
	
	$tempDir = dir($simpleAuthTempDirectory);
	while (($filename = $tempDir->read()) !== false) {
		$fullFilePath = $simpleAuthTempDirectory . '/' . $filename;
		$extension = pathinfo($fullFilePath, PATHINFO_EXTENSION);
		
		// Check file extension matches our expected value
		if ($extension != $simpleAuthTokenExtension) { 
			continue;
		}

		// Check the file prefix matches our expected value		
		if (substr($filename, 0, strlen($simpleAuthTokenPrefix)) != $simpleAuthTokenPrefix) {
			continue;
		}
		
		// Check if the file is
		$fileAge = time() - filemtime($fullFilePath);
		if ($fileAge < $simpleAuthTokenLifetime) {
			continue;
		}
		
		unlink($fullFilePath);
	}
	$tempDir->close();
}

/////////////////////////////////////////////////////////////////////////////////////////
// Initialize Request
/////////////////////////////////////////////////////////////////////////////////////////
initalizeRequest();
cleanTempDir();
