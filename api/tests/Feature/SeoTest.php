<?php

namespace Tests\Feature;

use Tests\TestCase;

class SeoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['japa.frontend_url' => 'https://japacoach.com']);
    }

    public function test_sitemap_lists_only_public_pages_with_absolute_urls(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        $this->assertStringStartsWith('application/xml', $response->headers->get('Content-Type'));

        $xml = simplexml_load_string($response->getContent());
        $locations = [];
        foreach ($xml->url as $url) {
            $locations[] = (string) $url->loc;
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $url->lastmod);
        }

        $this->assertContains('https://japacoach.com/', $locations);
        $this->assertContains('https://japacoach.com/pricing', $locations);
        $this->assertNotContains('https://japacoach.com/dashboard', $locations);
    }

    public function test_robots_blocks_private_areas_and_points_to_the_sitemap(): void
    {
        $body = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString("Disallow: /dashboard\n", $body);
        $this->assertStringContainsString("Disallow: /admin\n", $body);
        $this->assertStringNotContainsString('Disallow: /pricing', $body);
        $this->assertStringContainsString('Sitemap: https://japacoach.com/sitemap.xml', $body);
    }
}
