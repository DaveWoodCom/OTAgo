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

	if (!isset($users[$username])) {
		// Unknown user. Bail out before any comparison: a missing entry evaluates to NULL,
		// and NULL == NULL (no Authorization header) or NULL == '' (empty password) used to
		// authenticate anybody who guessed at the query string.
		return false;
	}

	$expectedPassword = $users[$username];

	// Both sides must be real strings before we compare. $password is only set when
	// PHP_AUTH_PW is present, so it is NULL on an unauthenticated request. hash_equals()
	// is a byte-exact, timing-safe comparison, which also avoids the numeric-string
	// coercion '==' would apply (e.g. '1e3' == '1000').
	if (is_string($password) && is_scalar($expectedPassword) && hash_equals((string)$expectedPassword, $password)) {
		// Only a successful password authentication mints/refreshes a token.
		if (!issueUserToken()) {
			// The password was right, so this request is authenticated, but without a token
			// the follow-up manifest.php/ipa.php requests (which carry no password) will fail.
			trigger_error('OTAgo: could not write a Simple Auth token file. Check that ' . $GLOBALS['simpleAuthTempDirectory'] . ' exists and is writable by the web server.', E_USER_WARNING);
		}
		return true;
	}
	
	// Otherwise fall back to a token issued by an earlier successful password auth.
	if (hasValidToken()) {
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
// Where token files are stored. The default is a `tokens` directory inside the deployment,
// which ships with index.html and .htaccess guards so the web server won't serve it. Don't
// point this at a shared directory such as /tmp: on a multi-user host every local user can
// read the token filenames (which are the credential) and drop in files of their own.
if (!isset($simpleAuthTempDirectory)) {
	$simpleAuthTempDirectory = __DIR__ . '/../../tokens';
}
// Secret used to sign token files. Set $simpleAuthSecret in configuration.php to a long
// random string (and keep it out of version control). If it is left unset, a random secret
// is generated once and stored, 0600, in the token directory.
if (!isset($simpleAuthSecret)) {
	$simpleAuthSecret = null;
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

	// Defence in depth: $requestUUID is interpolated into a filesystem path by
	// userTokenFilename(), so only accept a well-formed UUID. Anything else is
	// treated as absent, and a fresh UUID is generated below (which simply fails
	// to match an existing token file, so authentication fails naturally).
	if (isset($_GET['t']) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $_GET['t'])) {
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

	// The username goes into the filename, so encode it url-safe: standard base64 can contain
	// '/', which would push the token file out of the token directory.
	$encodedUsername = rtrim(strtr(base64_encode($username), '+/', '-_'), '=');

	return sprintf('%s/%s_%s_%s.%s', $simpleAuthTempDirectory, $simpleAuthTokenPrefix, $encodedUsername, $requestUUID, $simpleAuthTokenExtension);
}

// Returns the token directory, creating it 0700 if it doesn't exist yet.
// Returns null if it can't be created, read or written - in which case token
// authentication fails closed rather than degrading to "any file will do".
function simpleAuthTokenDirectory() {
	global $simpleAuthTempDirectory;

	if (!is_dir($simpleAuthTempDirectory)) {
		if (!@mkdir($simpleAuthTempDirectory, 0700, true) && !is_dir($simpleAuthTempDirectory)) {
			return null;
		}
	}

	if (!is_readable($simpleAuthTempDirectory) || !is_writable($simpleAuthTempDirectory)) {
		return null;
	}

	return $simpleAuthTempDirectory;
}

// The secret token files are signed with. Prefers $simpleAuthSecret from configuration.php;
// otherwise generates a random secret once and stores it 0600 inside the token directory.
// Returns null when no secret can be obtained.
function simpleAuthSecret() {
	global $simpleAuthSecret;

	static $resolvedSecret = null;
	if ($resolvedSecret !== null) {
		return $resolvedSecret;
	}

	if (is_string($simpleAuthSecret) && $simpleAuthSecret !== '') {
		$resolvedSecret = $simpleAuthSecret;
		return $resolvedSecret;
	}

	$directory = simpleAuthTokenDirectory();
	if ($directory === null) {
		return null;
	}

	$secretFilename = $directory . '/.simpleAuthSecret';

	$contents = @file_get_contents($secretFilename);
	if (is_string($contents) && trim($contents) !== '') {
		$resolvedSecret = trim($contents);
		return $resolvedSecret;
	}

	// 'xb' fails rather than truncating if a concurrent request just created the file.
	$handle = @fopen($secretFilename, 'xb');
	if ($handle !== false) {
		@chmod($secretFilename, 0600);
		fwrite($handle, bin2hex(random_bytes(32)));
		fclose($handle);

		$contents = @file_get_contents($secretFilename);
		if (is_string($contents) && trim($contents) !== '') {
			$resolvedSecret = trim($contents);
			return $resolvedSecret;
		}
	}

	return null;
}

// The HMAC stored inside the token file. It binds the token to this username and request
// UUID, so a token file can't be repurposed for another user and a file dropped into the
// token directory by someone without the secret won't verify.
function userTokenSignature() {
	global $username;
	global $requestUUID;

	$secret = simpleAuthSecret();
	if ($secret === null) {
		return null;
	}

	return hash_hmac('sha256', $username . "\0" . $requestUUID, $secret);
}

// Writes (or refreshes) the token file for the current user/request, 0600.
// Returns true if the token is now on disk.
function issueUserToken() {
	if (simpleAuthTokenDirectory() === null) {
		return false;
	}

	$signature = userTokenSignature();
	if ($signature === null) {
		return false;
	}

	$filename = userTokenFilename();

	// Create the file empty, lock down the permissions, and only then write the signature.
	$handle = @fopen($filename, 'cb');
	if ($handle === false) {
		return false;
	}
	@chmod($filename, 0600);

	$written = false;
	if (flock($handle, LOCK_EX)) {
		ftruncate($handle, 0);
		$written = (fwrite($handle, $signature) !== false);
		fflush($handle);
		flock($handle, LOCK_UN);
	}
	fclose($handle);

	return $written;
}

// Token authentication. The file has to exist, still be within $simpleAuthTokenLifetime, and
// carry the expected HMAC. Age is checked here, at use time, and expired files are removed on
// sight - so expiry no longer depends on cleanTempDir() having been able to read the directory.
function hasValidToken() {
	global $simpleAuthTokenLifetime;

	$filename = userTokenFilename();
	if (!is_file($filename)) {
		return false;
	}

	$modifiedTime = @filemtime($filename);
	if ($modifiedTime === false || (time() - $modifiedTime) >= $simpleAuthTokenLifetime) {
		@unlink($filename);
		return false;
	}

	$signature = userTokenSignature();
	if ($signature === null) {
		return false;
	}

	$contents = @file_get_contents($filename);
	if (!is_string($contents) || !hash_equals($signature, trim($contents))) {
		return false;
	}

	return true;
}

function cleanTempDir() {
	global $simpleAuthTempDirectory;
	global $simpleAuthTokenPrefix;
	global $simpleAuthTokenExtension;
	global $simpleAuthTokenLifetime;
	
	// Best effort housekeeping only. Expiry is enforced by hasValidToken() at use time, so an
	// unreadable directory here can no longer leave tokens valid forever.
	if (!is_dir($simpleAuthTempDirectory) || !is_readable($simpleAuthTempDirectory)) {
		return;
	}

	$tempDir = @dir($simpleAuthTempDirectory);
	if ($tempDir === false) {
		return;
	}

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
