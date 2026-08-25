<?php

namespace Tests\Feature;

use Tests\TestCase;

class WebAppInstallTest extends TestCase
{
    public function test_login_page_exposes_iphone_web_app_metadata(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('rel="manifest"', false);
        $response->assertSee('rel="apple-touch-icon"', false);
        $response->assertSee('name="apple-mobile-web-app-capable" content="yes"', false);
        $response->assertSee('name="apple-mobile-web-app-title" content="Nakatsuka DX"', false);
    }

    public function test_web_app_manifest_has_standalone_launch_and_icons(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(public_path('manifest.webmanifest')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame('Nakatsuka DX', $manifest['name']);
        $this->assertSame('/index.php/dashboard', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/icon-192.png', $manifest['icons'][0]['src']);
        $this->assertSame('/icon-512.png', $manifest['icons'][1]['src']);
        $this->assertFileExists(public_path('apple-touch-icon.png'));
        $this->assertFileExists(public_path('icon-192.png'));
        $this->assertFileExists(public_path('icon-512.png'));
    }
}
