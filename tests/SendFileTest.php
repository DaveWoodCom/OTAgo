<?php
// OTAgo
// - OTA App Distribution System
// - https://github.com/DaveWoodCom/OTAgo
// - Copyright 2020 Dave Wood, Cerebral Gardens Inc.

use PHPUnit\Framework\TestCase;

// Covers parseRangeHeader() in common.php - the part of sendFile() that decides whether a
// download is served whole (200), as a byte range (206) or refused as unsatisfiable (416).
// A resumed install over a flaky cellular connection depends on getting this right, and
// getting it wrong quietly serves the wrong bytes, so each shape of the header is pinned here.
//
// The contract: array(start, end) inclusive for a range to serve, null to ignore the header
// and send the whole file, false for a well-formed but unsatisfiable range.
final class SendFileTest extends TestCase {

	private const SIZE = 1000;

	// No header at all, and the shapes a client never sends but a proxy might mangle into.
	public function testNoRangeHeaderServesWholeFile(): void {
		$this->assertNull(parseRangeHeader('', self::SIZE));
	}

	public function testUnknownUnitIsIgnored(): void {
		$this->assertNull(parseRangeHeader('items=0-99', self::SIZE));
		$this->assertNull(parseRangeHeader('0-99', self::SIZE));
	}

	public function testUnitIsCaseInsensitive(): void {
		$this->assertSame(array(0, 99), parseRangeHeader('BYTES=0-99', self::SIZE));
	}

	public function testGarbageIsIgnoredRatherThanRefused(): void {
		$this->assertNull(parseRangeHeader('bytes=', self::SIZE));
		$this->assertNull(parseRangeHeader('bytes=abc-def', self::SIZE));
		$this->assertNull(parseRangeHeader('bytes=10', self::SIZE));
		$this->assertNull(parseRangeHeader('bytes=-', self::SIZE));
		$this->assertNull(parseRangeHeader('bytes=1.5-9', self::SIZE));
	}

	// "bytes=a-b"
	public function testExplicitRange(): void {
		$this->assertSame(array(0, 99), parseRangeHeader('bytes=0-99', self::SIZE));
		$this->assertSame(array(500, 599), parseRangeHeader('bytes=500-599', self::SIZE));
	}

	public function testSingleByteRange(): void {
		$this->assertSame(array(0, 0), parseRangeHeader('bytes=0-0', self::SIZE));
		$this->assertSame(array(999, 999), parseRangeHeader('bytes=999-999', self::SIZE));
	}

	public function testWholeFileAsAnExplicitRange(): void {
		$this->assertSame(array(0, 999), parseRangeHeader('bytes=0-999', self::SIZE));
	}

	// A last-byte-pos past the end is clamped, not refused - clients that don't know the size
	// routinely ask for more than there is.
	public function testEndPastEndOfFileIsClamped(): void {
		$this->assertSame(array(900, 999), parseRangeHeader('bytes=900-99999', self::SIZE));
	}

	// A backwards range is invalid syntax rather than an unsatisfiable range, so RFC 9110 says
	// ignore the header and send the whole file.
	public function testBackwardsRangeIsIgnored(): void {
		$this->assertNull(parseRangeHeader('bytes=99-0', self::SIZE));
	}

	// "bytes=a-" - the resume case: everything from where the download stopped.
	public function testOpenEndedRange(): void {
		$this->assertSame(array(100, 999), parseRangeHeader('bytes=100-', self::SIZE));
		$this->assertSame(array(0, 999), parseRangeHeader('bytes=0-', self::SIZE));
		$this->assertSame(array(999, 999), parseRangeHeader('bytes=999-', self::SIZE));
	}

	public function testOpenEndedRangeStartingPastEndIsUnsatisfiable(): void {
		$this->assertFalse(parseRangeHeader('bytes=1000-', self::SIZE));
		$this->assertFalse(parseRangeHeader('bytes=999999999-', self::SIZE));
	}

	public function testExplicitRangeStartingPastEndIsUnsatisfiable(): void {
		$this->assertFalse(parseRangeHeader('bytes=1000-1099', self::SIZE));
	}

	// "bytes=-n" - the final n bytes.
	public function testSuffixRange(): void {
		$this->assertSame(array(900, 999), parseRangeHeader('bytes=-100', self::SIZE));
		$this->assertSame(array(999, 999), parseRangeHeader('bytes=-1', self::SIZE));
	}

	// Asking for more trailing bytes than the file has yields the whole file, not a 416.
	public function testSuffixRangeLongerThanFileIsClampedToWholeFile(): void {
		$this->assertSame(array(0, 999), parseRangeHeader('bytes=-1000', self::SIZE));
		$this->assertSame(array(0, 999), parseRangeHeader('bytes=-99999', self::SIZE));
	}

	// "bytes=-0" asks for the last zero bytes, which no representation can satisfy.
	public function testZeroLengthSuffixIsUnsatisfiable(): void {
		$this->assertFalse(parseRangeHeader('bytes=-0', self::SIZE));
	}

	// A multi-range request would need a multipart/byteranges body; serving the whole file is
	// a legal answer to any Range request, so that's what sendFile() falls back to.
	public function testMultiRangeFallsBackToWholeFile(): void {
		$this->assertNull(parseRangeHeader('bytes=0-99,200-299', self::SIZE));
		$this->assertNull(parseRangeHeader('bytes=0-99, -100', self::SIZE));
	}

	public function testSurroundingWhitespaceIsTolerated(): void {
		$this->assertSame(array(0, 99), parseRangeHeader('bytes= 0-99 ', self::SIZE));
	}

	// An empty file can satisfy no range at all.
	public function testEmptyFileIsAlwaysUnsatisfiable(): void {
		$this->assertFalse(parseRangeHeader('bytes=0-0', 0));
		$this->assertFalse(parseRangeHeader('bytes=0-', 0));
		$this->assertFalse(parseRangeHeader('bytes=-1', 0));
	}

	// ...but a rangeless request for one is still a perfectly good empty 200.
	public function testEmptyFileWithoutARangeIsServedWhole(): void {
		$this->assertNull(parseRangeHeader('', 0));
	}
}
