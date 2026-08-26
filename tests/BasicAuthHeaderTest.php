<?php
// OTAgo
// - OTA App Distribution System
// - https://github.com/DaveWoodCom/OTAgo
// - Copyright 2020 Dave Wood, Cerebral Gardens Inc.

use PHPUnit\Framework\TestCase;

// Covers the HTTP_AUTHORIZATION fallback in auth/simple/simpleAuth.php (#31): under php-fpm/CGI
// the Authorization header may reach PHP as HTTP_AUTHORIZATION or REDIRECT_HTTP_AUTHORIZATION
// rather than PHP_AUTH_USER / PHP_AUTH_PW.
final class BasicAuthHeaderTest extends TestCase {

	private array $savedServer;
	private array $savedGet;

	protected function setUp(): void {
		$this->savedServer = $_SERVER;
		$this->savedGet = $_GET;
		foreach (array('PHP_AUTH_USER', 'PHP_AUTH_PW', 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION') as $key) {
			unset($_SERVER[$key]);
		}
		$_GET = array();
		unset($GLOBALS['username'], $GLOBALS['password'], $GLOBALS['requestUUID']);
	}

	protected function tearDown(): void {
		$_SERVER = $this->savedServer;
		$_GET = $this->savedGet;
		unset($GLOBALS['username'], $GLOBALS['password'], $GLOBALS['requestUUID']);
	}

	private static function basic(string $raw): string {
		return 'Basic ' . base64_encode($raw);
	}

	/////////////////////////////////////////////////////////////////////////////////////
	// basicAuthCredentialsFromHeader()
	/////////////////////////////////////////////////////////////////////////////////////

	public function testDecodesAWellFormedBasicHeader(): void {
		$this->assertSame(array('dave', 'secr3t'), basicAuthCredentialsFromHeader(self::basic('dave:secr3t')));
	}

	public function testSchemeIsCaseInsensitiveAndWhitespaceTolerant(): void {
		$this->assertSame(array('dave', 'secr3t'), basicAuthCredentialsFromHeader('  basic   ' . base64_encode('dave:secr3t') . ' '));
	}

	public function testPasswordMayContainColonsAndBeEmpty(): void {
		$this->assertSame(array('dave', 'a:b:c'), basicAuthCredentialsFromHeader(self::basic('dave:a:b:c')));
		$this->assertSame(array('dave', ''), basicAuthCredentialsFromHeader(self::basic('dave:')));
	}

	public function testRejectsOtherSchemesGarbageAndMissingSeparator(): void {
		$this->assertNull(basicAuthCredentialsFromHeader('Bearer abc.def.ghi'));
		$this->assertNull(basicAuthCredentialsFromHeader('Basic not*base64!'));
		$this->assertNull(basicAuthCredentialsFromHeader(self::basic('no-separator')));
		$this->assertNull(basicAuthCredentialsFromHeader(self::basic(':empty-user')));
		$this->assertNull(basicAuthCredentialsFromHeader(''));
		$this->assertNull(basicAuthCredentialsFromHeader(null));
	}

	/////////////////////////////////////////////////////////////////////////////////////
	// initalizeRequest() integration
	/////////////////////////////////////////////////////////////////////////////////////

	public function testHTTPAuthorizationPopulatesCredentialsWhenPHPAuthIsAbsent(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = self::basic('dave:secr3t');
		initalizeRequest();
		$this->assertSame('dave', $GLOBALS['username']);
		$this->assertSame('secr3t', $GLOBALS['password']);
	}

	public function testRedirectHTTPAuthorizationIsAlsoAccepted(): void {
		$_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = self::basic('dave:secr3t');
		initalizeRequest();
		$this->assertSame('dave', $GLOBALS['username']);
		$this->assertSame('secr3t', $GLOBALS['password']);
	}

	public function testPHPAuthUserStillWinsOverTheHeader(): void {
		$_SERVER['PHP_AUTH_USER'] = 'john';
		$_SERVER['PHP_AUTH_PW'] = 'hunter2';
		$_SERVER['HTTP_AUTHORIZATION'] = self::basic('dave:secr3t');
		initalizeRequest();
		$this->assertSame('john', $GLOBALS['username']);
		$this->assertSame('hunter2', $GLOBALS['password']);
	}

	public function testQueryStringUsernameStillWinsOverTheHeader(): void {
		$_GET['u'] = 'john';
		$_SERVER['HTTP_AUTHORIZATION'] = self::basic('dave:secr3t');
		initalizeRequest();
		$this->assertSame('john', $GLOBALS['username']);
		$this->assertSame('secr3t', $GLOBALS['password']);
	}

	public function testMalformedHeaderLeavesPasswordUnsetSoAuthFails(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ???';
		initalizeRequest();
		$this->assertNull($GLOBALS['password'] ?? null);
		$this->assertNull($GLOBALS['username'] ?? null);
	}
}
