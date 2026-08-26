<?php
// OTAgo
// - OTA App Distribution System
// - https://github.com/DaveWoodCom/OTAgo
// - Copyright 2020 Dave Wood, Cerebral Gardens Inc.

// OTAgo requires PHP 8.1 or newer (PHP 7 reached end of life in November 2022). This file
// deliberately avoids PHP 8-only syntax so that an older interpreter gets this clear message
// instead of a parse error from one of the files loaded after it.
if (PHP_VERSION_ID < 80100) {
	http_response_code(500);
	header('Content-Type: text/plain');
	exit('OTAgo requires PHP 8.1 or newer; this server is running PHP ' . PHP_VERSION . '.');
}

// OTAgo's own version. Bump this with each release (see the release steps in README.md).
define('OTAGO_VERSION', '1.1.0');

// Placeholder token replaced with OTAGO_VERSION when rendering the web template.
// Not user-configurable, so it lives here rather than in configuration.php.
define('OTAGO_VERSION_PLACEHOLDER', '{{OTAGOVERSION}}');

// Builds the base URL OTAgo links back to itself with, from a $_SERVER-shaped array.
//
// The scheme is worked out portably: REQUEST_SCHEME is Apache-only and unset on nginx/php-fpm
// and PHP's built-in server, so HTTPS and SERVER_PORT are consulted too. HTTP_HOST already
// includes the port when it's a non-standard one, so SERVER_PORT is never appended.
//
// Behind a TLS-terminating proxy the request that reaches PHP is plain HTTP, so the only way
// to know the browser used https:// is the proxy's X-Forwarded-Proto / X-Forwarded-Host
// headers. Those are client-settable when there is no proxy in front, so they are only
// honoured when $trustProxyHeaders is true (set it in configuration.php only if every request
// really does arrive through a proxy that overwrites them).
//
// If detection still isn't right for your setup, set $baseURL in configuration.php directly.
function detectBaseURL($server, $trustProxyHeaders = false) {
	$scheme = null;
	$host = isset($server['HTTP_HOST']) ? $server['HTTP_HOST'] : '';

	if ($trustProxyHeaders) {
		if (!empty($server['HTTP_X_FORWARDED_PROTO'])) {
			// A chain of proxies appends values: "https, http". The first is the client's.
			$forwarded = strtolower(trim(explode(',', $server['HTTP_X_FORWARDED_PROTO'])[0]));
			if ($forwarded === 'https' || $forwarded === 'http') {
				$scheme = $forwarded;
			}
		}
		if (!empty($server['HTTP_X_FORWARDED_HOST'])) {
			$host = trim(explode(',', $server['HTTP_X_FORWARDED_HOST'])[0]);
		}
	}

	if ($scheme === null) {
		if (!empty($server['HTTPS']) && strtolower($server['HTTPS']) !== 'off') {
			$scheme = 'https';
		}
		elseif (!empty($server['REQUEST_SCHEME'])) {
			$scheme = strtolower($server['REQUEST_SCHEME']);
		}
		elseif (isset($server['SERVER_PORT']) && (int)$server['SERVER_PORT'] === 443) {
			$scheme = 'https';
		}
		else {
			$scheme = 'http';
		}
	}

	return $scheme . '://' . $host . '/';
}

// Sets $baseURL unless configuration.php already did. Call this after configuration.php has
// been loaded so that $trustProxyHeaders is known; the entry points that build URLs do.
function resolveBaseURL() {
	if (isset($GLOBALS['baseURL'])) {
		return;
	}
	$GLOBALS['baseURL'] = detectBaseURL($_SERVER, !empty($GLOBALS['trustProxyHeaders']));
}

function preventCaching() {
	header('Cache-Control: no-cache, no-store, must-revalidate');
	header('Pragma: no-cache');
	header('Expires: 0');

	// Basic security headers. Note: Strict-Transport-Security is deliberately not sent here;
	// HSTS is a sticky, site-wide policy that belongs in your web server's configuration.
	header('X-Content-Type-Options: nosniff');
	header('Referrer-Policy: no-referrer');
}

function requestBasicAuthentication($realm) {
	header('WWW-Authenticate: Basic realm="' . $realm . '"');
    http_response_code(401);
    exit;
}

function returnInvalidConfiguration() {
    http_response_code(501);
    exit;
}

function makeURLQueryString($parameters, $joinString) {
	$pairs = array();
	
	foreach ($parameters as $name => $value) {
		$pairs[] = urlencode($name) . '=' . urlencode($value);
	}
	
	return implode($joinString, $pairs);
}

// Back-compat for the pre-2.0 "PlacehHolder" typo in configuration.php. If the new name is
// unset but the old one is, copy it across and emit a deprecation notice. Call this after
// configuration.php has been loaded. The old names will be dropped in a future release.
function resolveDeprecatedPlaceholderNames() {
	$renames = array(
		'installURLPlacehHolder' => 'installURLPlaceholder',
		'ipaURLPlacehHolder' => 'ipaURLPlaceholder',
	);
	foreach ($renames as $old => $new) {
		if (!isset($GLOBALS[$new]) && isset($GLOBALS[$old])) {
			$GLOBALS[$new] = $GLOBALS[$old];
			trigger_error('$' . $old . ' is deprecated; rename it to $' . $new . ' in configuration.php', E_USER_DEPRECATED);
		}
	}
}
