<?php

namespace Tests\Unit;

use App\Support\SilentSsoProbe;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class SilentSsoProbeTest extends TestCase
{
    public function test_return_path_drops_login_query_params(): void
    {
        $request = Request::create('https://practice.croc.ru/?login=1&silent=1');

        $this->assertSame('/', SilentSsoProbe::returnPathFor($request));
    }

    public function test_return_path_keeps_meaningful_query(): void
    {
        $request = Request::create('https://practice.croc.ru/kurs/os-alt/modul/7?tab=praktika&login=1');

        $this->assertSame(
            '/kurs/os-alt/modul/7?tab=praktika',
            SilentSsoProbe::returnPathFor($request)
        );
    }

    public function test_return_path_for_root_without_query(): void
    {
        $this->assertSame('/', SilentSsoProbe::returnPathFor(Request::create('https://practice.croc.ru/')));
    }

    public function test_sanitize_return_rejects_external_targets(): void
    {
        $this->assertNull(SilentSsoProbe::sanitizeReturn('https://evil.example/'));
        $this->assertNull(SilentSsoProbe::sanitizeReturn('//evil.example/'));
        $this->assertNull(SilentSsoProbe::sanitizeReturn('/\\evil.example/'));
        $this->assertNull(SilentSsoProbe::sanitizeReturn('account'));
        $this->assertNull(SilentSsoProbe::sanitizeReturn(''));
        $this->assertNull(SilentSsoProbe::sanitizeReturn(null));
        $this->assertNull(SilentSsoProbe::sanitizeReturn(42));
    }

    public function test_sanitize_return_accepts_portal_paths(): void
    {
        $this->assertSame('/account', SilentSsoProbe::sanitizeReturn('/account'));
        $this->assertSame('/opros/abc?x=1', SilentSsoProbe::sanitizeReturn('/opros/abc?x=1'));
    }
}
