<?php
// OTAgo
// - OTA App Distribution System
// - https://github.com/DaveWoodCom/OTAgo
// - Copyright 2020 Dave Wood, Cerebral Gardens Inc.

use PHPUnit\Framework\TestCase;

// Covers detectBaseURL() in common.php - the value every install/manifest/IPA URL is built
// from. These tests pin the current behaviour across the server setups OTAgo gets deployed
// on, with and without REQUEST_SCHEME, and behind a proxy with $trustProxyHeaders on and off.
final class BaseURLTest extends TestCase {

	public function testApacheOverHTTPS(): void {
		$this->assertSame('https://ota.example.com/', detectBaseURL(array(
			'REQUEST_SCHEME' => 'https',
			'HTTP_HOST' => 'ota.example.com',
			'SERVER_PORT' => '443',
		)));
	}

	public function testApacheOverPlainHTTP(): void {
		$this->assertSame('http://ota.example.com/', detectBaseURL(array(
			'REQUEST_SCHEME' => 'http',
			'HTTP_HOST' => 'ota.example.com',
			'SERVER_PORT' => '80',
		)));
	}

	// HTTP_HOST carries the port when it isn't the default for the scheme, so SERVER_PORT
	// must not be appended a second time.
	public function testNonStandardPortComesFromHTTPHostOnly(): void {
		$this->assertSame('https://ota.example.com:8443/', detectBaseURL(array(
			'REQUEST_SCHEME' => 'https',
			'HTTP_HOST' => 'ota.example.com:8443',
			'SERVER_PORT' => '8443',
		)));

		$this->assertSame('http://ota.example.com:8080/', detectBaseURL(array(
			'REQUEST_SCHEME' => 'http',
			'HTTP_HOST' => 'ota.example.com:8080',
			'SERVER_PORT' => '8080',
		)));
	}

	public function testSubdomainAndIPv6Hosts(): void {
		$this->assertSame('https://builds.ci.example.co.uk/', detectBaseURL(array(
			'REQUEST_SCHEME' => 'https',
			'HTTP_HOST' => 'builds.ci.example.co.uk',
		)));

		$this->assertSame('http://[2001:db8::1]:8080/', detectBaseURL(array(
			'REQUEST_SCHEME' => 'http',
			'HTTP_HOST' => '[2001:db8::1]:8080',
		)));
	}

	// nginx + php-fpm sets REQUEST_SCHEME through fastcgi_params on most distributions, so
	// the common case is the same as Apache's.
	public function testNginxWithRequestSchemeSet(): void {
		$this->assertSame('https://ota.example.com/', detectBaseURL(array(
			'REQUEST_SCHEME' => 'https',
			'HTTP_HOST' => 'ota.example.com',
			'HTTPS' => 'on',
		)));
	}

	// REQUEST_SCHEME is not a CGI standard variable; nginx/php-fpm setups that don't pass it,
	// and PHP's built-in server, used to get a broken '://host/' plus an undefined-key warning.
	public function testMissingRequestSchemeFallsBackToHTTPS(): void {
		$this->assertSame('https://ota.example.com/', detectBaseURL(array(
			'HTTP_HOST' => 'ota.example.com',
			'HTTPS' => 'on',
		)));
	}

	public function testMissingRequestSchemeFallsBackToPort443(): void {
		$this->assertSame('https://ota.example.com/', detectBaseURL(array(
			'HTTP_HOST' => 'ota.example.com',
			'SERVER_PORT' => '443',
		)));
	}

	public function testNothingButAHostIsPlainHTTPWithoutWarnings(): void {
		$warnings = array();
		set_error_handler(function ($number, $message) use (&$warnings) {
			$warnings[] = $message;
			return true;
		});

		try {
			$baseURL = detectBaseURL(array('HTTP_HOST' => '127.0.0.1:8080'));
		}
		finally {
			restore_error_handler();
		}

		$this->assertSame('http://127.0.0.1:8080/', $baseURL);
		$this->assertSame(array(), $warnings);
	}

	// IIS sets HTTPS to "off" on plain requests; that must not read as https.
	public function testHTTPSOffIsPlainHTTP(): void {
		$this->assertSame('http://ota.example.com/', detectBaseURL(array(
			'HTTP_HOST' => 'ota.example.com',
			'HTTPS' => 'off',
			'SERVER_PORT' => '80',
		)));
	}

	public function testHTTPSBeatsAStaleRequestScheme(): void {
		$this->assertSame('https://ota.example.com/', detectBaseURL(array(
			'REQUEST_SCHEME' => 'http',
			'HTTPS' => 'on',
			'HTTP_HOST' => 'ota.example.com',
		)));
	}

	// Behind a TLS-terminating proxy the request reaching PHP is plain HTTP. X-Forwarded-*
	// headers are client-settable when there is no proxy, so by default they are ignored...
	public function testProxyHeadersAreIgnoredByDefault(): void {
		$this->assertSame('http://internal.example.net:8080/', detectBaseURL(array(
			'REQUEST_SCHEME' => 'http',
			'HTTP_HOST' => 'internal.example.net:8080',
			'HTTP_X_FORWARDED_PROTO' => 'https',
			'HTTP_X_FORWARDED_HOST' => 'ota.example.com',
			'SERVER_PORT' => '8080',
		)));
	}

	// ...and honoured when $trustProxyHeaders is on.
	public function testProxyHeadersAreHonouredWhenTrusted(): void {
		$this->assertSame('https://ota.example.com/', detectBaseURL(array(
			'REQUEST_SCHEME' => 'http',
			'HTTP_HOST' => 'internal.example.net:8080',
			'HTTP_X_FORWARDED_PROTO' => 'https',
			'HTTP_X_FORWARDED_HOST' => 'ota.example.com',
			'SERVER_PORT' => '8080',
		), true));

		// A chain of proxies appends: the first value is the client-facing one.
		$this->assertSame('https://ota.example.com/', detectBaseURL(array(
			'HTTP_HOST' => 'internal.example.net',
			'HTTP_X_FORWARDED_PROTO' => 'https, http',
			'HTTP_X_FORWARDED_HOST' => 'ota.example.com, internal.example.net',
		), true));

		// Garbage in X-Forwarded-Proto falls back to local detection rather than being echoed.
		$this->assertSame('https://ota.example.com/', detectBaseURL(array(
			'HTTP_HOST' => 'ota.example.com',
			'HTTPS' => 'on',
			'HTTP_X_FORWARDED_PROTO' => 'javascript',
		), true));
	}

	// resolveBaseURL() runs after configuration.php, so an explicit $baseURL there wins and
	// $trustProxyHeaders is known by the time detection happens.
	public function testResolveBaseURLKeepsAConfiguredOverride(): void {
		$saved = array($GLOBALS['baseURL'] ?? null, $GLOBALS['trustProxyHeaders'] ?? null, $_SERVER);
		try {
			$GLOBALS['baseURL'] = 'https://public.example.com/ota/';
			resolveBaseURL();
			$this->assertSame('https://public.example.com/ota/', $GLOBALS['baseURL']);

			unset($GLOBALS['baseURL']);
			$GLOBALS['trustProxyHeaders'] = true;
			$_SERVER['HTTP_HOST'] = 'internal.example.net';
			$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
			$_SERVER['HTTP_X_FORWARDED_HOST'] = 'ota.example.com';
			resolveBaseURL();
			$this->assertSame('https://ota.example.com/', $GLOBALS['baseURL']);
		}
		finally {
			list($GLOBALS['baseURL'], $GLOBALS['trustProxyHeaders'], $_SERVER) = $saved;
			if ($GLOBALS['baseURL'] === null) { unset($GLOBALS['baseURL']); }
			if ($GLOBALS['trustProxyHeaders'] === null) { unset($GLOBALS['trustProxyHeaders']); }
		}
	}

	// Whatever the base URL is, the links built on it have to stay well formed.
	public function testBaseURLComposesIntoAManifestURL(): void {
		$baseURL = detectBaseURL(array(
			'REQUEST_SCHEME' => 'https',
			'HTTP_HOST' => 'ota.example.com:8443',
		));

		$manifestURL = $baseURL . 'manifest.php?' . makeURLQueryString(array(
			'u' => 'dave',
			't' => '2b4d1f6c-1f0e-4a5b-9c3d-0e1f2a3b4c5d',
		), '&');

		$this->assertSame('https://ota.example.com:8443/manifest.php?u=dave&t=2b4d1f6c-1f0e-4a5b-9c3d-0e1f2a3b4c5d', $manifestURL);
	}

	public function testMakeURLQueryStringEscapesValues(): void {
		$this->assertSame('u=a%2Bb+c&t=x%26y', makeURLQueryString(array('u' => 'a+b c', 't' => 'x&y'), '&'));
	}
}
