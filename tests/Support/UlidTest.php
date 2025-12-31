<?php

declare(strict_types=1);

namespace Ava\Tests\Support;

use Ava\Support\Ulid;
use Ava\Testing\TestCase;

/**
 * Tests for the ULID generator.
 */
final class UlidTest extends TestCase
{
    public function testGenerateReturnsValidLength(): void
    {
        $ulid = Ulid::generate();
        $this->assertEquals(26, strlen($ulid));
    }

    public function testGenerateReturnsUppercaseAlphanumeric(): void
    {
        $ulid = Ulid::generate();
        $this->assertMatchesRegex('/^[0-9A-Z]+$/', $ulid);
    }

    public function testGenerateReturnsUniqueValues(): void
    {
        $ulids = [];
        for ($i = 0; $i < 100; $i++) {
            $ulids[] = Ulid::generate();
        }

        $unique = array_unique($ulids);
        $this->assertCount(100, $unique);
    }

    public function testIsValidReturnsTrueForValidUlid(): void
    {
        $ulid = Ulid::generate();
        $this->assertTrue(Ulid::isValid($ulid));
    }

    public function testIsValidReturnsFalseForShortString(): void
    {
        $this->assertFalse(Ulid::isValid('0123456789'));
    }

    public function testIsValidReturnsFalseForLongString(): void
    {
        $this->assertFalse(Ulid::isValid(str_repeat('0', 30)));
    }

    public function testIsValidReturnsFalseForInvalidCharacters(): void
    {
        // ULID uses Crockford Base32 which excludes I, L, O, U
        $this->assertFalse(Ulid::isValid('01ARYZ6S41IIIIIIIIIIIIIII'));
    }

    public function testIsValidIsCaseInsensitive(): void
    {
        $ulid = Ulid::generate();
        $this->assertTrue(Ulid::isValid(strtolower($ulid)));
    }

    public function testTimestampExtractsCorrectTime(): void
    {
        $before = (int) (microtime(true) * 1000);
        $ulid = Ulid::generate();
        $after = (int) (microtime(true) * 1000);

        $timestamp = Ulid::timestamp($ulid);

        $this->assertGreaterThan($before - 1, $timestamp);
        $this->assertLessThan($after + 1, $timestamp);
    }

    public function testTimestampThrowsForInvalidUlid(): void
    {
        $this->assertThrows(\InvalidArgumentException::class, function () {
            Ulid::timestamp('invalid');
        });
    }

    public function testToDateTimeReturnsCorrectDateTime(): void
    {
        $ulid = Ulid::generate();
        $datetime = Ulid::toDateTime($ulid);

        $this->assertInstanceOf(\DateTimeImmutable::class, $datetime);

        // Should be within last second
        $now = new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $datetime->getTimestamp();
        $this->assertLessThan(2, abs($diff));
    }

    public function testUlidsAreLexicographicallySortable(): void
    {
        $ulid1 = Ulid::generate();
        usleep(1000); // Wait 1ms
        $ulid2 = Ulid::generate();

        // Second ULID should be greater (later timestamp)
        $this->assertGreaterThan($ulid1, $ulid2);
    }
}
