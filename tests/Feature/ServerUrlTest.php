<?php

namespace Tests\Feature;

use App\Exceptions\InvalidServerUrl;
use App\Support\ServerUrl;
use Tests\TestCase;

class ServerUrlTest extends TestCase
{
    public function test_it_assumes_https_when_the_scheme_is_missing(): void
    {
        $url = ServerUrl::fromUserInput('homeside.cloud');

        $this->assertSame('https://homeside.cloud', $url->baseUrl);
        $this->assertSame('https://homeside.cloud/api/v1', $url->apiBase);
    }

    public function test_it_trims_whitespace_and_trailing_slashes(): void
    {
        $url = ServerUrl::fromUserInput('  https://casa.example.com/  ');

        $this->assertSame('https://casa.example.com', $url->baseUrl);
    }

    public function test_it_keeps_a_subdirectory_install(): void
    {
        $url = ServerUrl::fromUserInput('https://example.com/homeside/');

        $this->assertSame('https://example.com/homeside', $url->baseUrl);
        $this->assertSame('https://example.com/homeside/api/v1', $url->apiBase);
        $this->assertSame('https://example.com/homeside/api/v1/health', $url->healthUrl());
    }

    public function test_it_allows_http_on_localhost_with_a_port(): void
    {
        $url = ServerUrl::fromUserInput('http://localhost:8000');

        $this->assertSame('http://localhost:8000', $url->baseUrl);
        $this->assertFalse($url->isSecure());
    }

    public function test_it_allows_http_on_a_private_network_address(): void
    {
        $url = ServerUrl::fromUserInput('http://192.168.1.42:8000');

        $this->assertSame('http://192.168.1.42:8000', $url->baseUrl);
    }

    public function test_it_rejects_http_on_a_public_host(): void
    {
        $this->expectException(InvalidServerUrl::class);

        ServerUrl::fromUserInput('http://homeside.cloud');
    }

    public function test_it_rejects_an_empty_value(): void
    {
        $this->expectException(InvalidServerUrl::class);

        ServerUrl::fromUserInput('   ');
    }

    public function test_it_rejects_an_unsupported_scheme(): void
    {
        $this->expectException(InvalidServerUrl::class);

        ServerUrl::fromUserInput('ftp://homeside.cloud');
    }

    public function test_it_rejects_a_query_string(): void
    {
        $this->expectException(InvalidServerUrl::class);

        ServerUrl::fromUserInput('https://homeside.cloud/?foo=bar');
    }

    public function test_it_rejects_embedded_credentials(): void
    {
        $this->expectException(InvalidServerUrl::class);

        ServerUrl::fromUserInput('https://user:pass@homeside.cloud');
    }

    public function test_default_comes_from_config(): void
    {
        config()->set('homeside.default_server', 'https://homeside.cloud');

        $this->assertSame('https://homeside.cloud', ServerUrl::default()->baseUrl);
    }
}
