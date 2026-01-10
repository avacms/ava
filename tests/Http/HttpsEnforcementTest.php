<?php

declare(strict_types=1);

namespace Ava\Tests\Http;

use Ava\Http\Request;
use Ava\Testing\TestCase;

/**
 * Tests for HTTPS enforcement via Request::isLocalhost() and Request::isSecure().
 */
final class HttpsEnforcementTest extends TestCase
{
    public function testIsSecureReturnsTrueForHttps(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $request = new Request('GET', '/admin', []);
        
        $this->assertTrue($request->isSecure());
        
        unset($_SERVER['HTTPS']);
    }

    public function testIsSecureReturnsFalseForHttp(): void
    {
        $_SERVER['HTTPS'] = 'off';
        $request = new Request('GET', '/admin', []);
        
        $this->assertFalse($request->isSecure());
        
        unset($_SERVER['HTTPS']);
    }

    public function testIsSecureDoesNotTrustXForwardedProtoByDefault(): void
    {
        $_SERVER['HTTPS'] = 'off';
        $request = new Request('GET', '/admin', [], ['X-Forwarded-Proto' => 'https']);
        
        $this->assertFalse($request->isSecure());
        
        unset($_SERVER['HTTPS']);
    }

    public function testIsLocalhostReturnsTrueForIPv4Loopback(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $request = new Request('GET', '/admin', []);
        
        $this->assertTrue($request->isLocalhost());
        
        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testIsLocalhostReturnsTrueForIPv6Loopback(): void
    {
        $_SERVER['REMOTE_ADDR'] = '::1';
        $request = new Request('GET', '/admin', []);
        
        $this->assertTrue($request->isLocalhost());
        
        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testIsLocalhostReturnsFalseForPublicIP(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $request = new Request('GET', '/admin', [], ['Host' => 'example.com']);
        
        $this->assertFalse($request->isLocalhost());
        
        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testIsLocalhostReturnsTrueForLocalhostHostname(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $request = new Request('GET', '/admin', [], ['Host' => 'localhost']);
        
        $this->assertTrue($request->isLocalhost());
        
        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testIsLocalhostReturnsTrueForLocalhostWithPort(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $request = new Request('GET', '/admin', [], ['Host' => 'localhost:8080']);
        
        $this->assertTrue($request->isLocalhost());
        
        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testIsLocalhostReturnsFalseForProductionDomain(): void
    {
        $_SERVER['REMOTE_ADDR'] = '1.2.3.4';
        $request = new Request('GET', '/admin', [], ['Host' => 'example.com']);
        
        $this->assertFalse($request->isLocalhost());
        
        unset($_SERVER['REMOTE_ADDR']);
    }
}
