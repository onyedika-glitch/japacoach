<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Support\Markdown\MarkdownPages;
use App\Support\Markdown\SiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['japa.frontend_url' => 'https://japacoach.com']);
    }

    public function test_every_public_page_has_a_markdown_version_or_a_reason_to_skip(): void
    {
        foreach (SiteContent::pages() as $path => $page) {
            if ($page['markdown'] ?? false) {
                $this->assertArrayHasKey($path, MarkdownPages::renderers(), "{$path} is marked markdown but has no renderer.");
            } else {
                $this->assertNotEmpty($page['skipReason'] ?? null, "{$path} has no markdown version and no skipReason.");
            }
        }
    }

    public function test_markdown_pages_render_with_agent_headers(): void
    {
        foreach (MarkdownPages::paths() as $path) {
            $url = '/api/md'.($path === '/' ? '/index' : $path);
            $response = $this->get($url)->assertOk();

            $this->assertStringStartsWith('text/markdown', $response->headers->get('Content-Type'));
            $this->assertSame('noindex, follow', $response->headers->get('X-Robots-Tag'));
            $this->assertSame(SiteContent::url($path), $response->headers->get('Content-Location'));
            $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
            $this->assertGreaterThan(0, (int) $response->headers->get('X-Markdown-Tokens'));
            $this->assertStringStartsWith('# ', $response->getContent());
            $this->assertStringContainsString('Canonical: '.SiteContent::url($path), $response->getContent());
            $this->assertStringNotContainsString('.md)', $response->getContent(), "{$path} links to .md instead of HTML.");
        }
    }

    public function test_home_and_pricing_use_the_same_facts_as_the_site(): void
    {
        Review::factory()->approved()->create(['display_name' => 'Tunde', 'comment' => 'My speaking went from 5.5 to 6.5.']);
        Review::factory()->create(['display_name' => 'Hidden', 'comment' => 'Pending review.']);

        $home = $this->get('/api/md/')->assertOk()->getContent();
        $this->assertStringContainsString('## Three steps. Every day.', $home);
        $this->assertStringContainsString('### Is the band score official?', $home);
        $this->assertStringContainsString('Tunde', $home);
        $this->assertStringNotContainsString('Hidden', $home);

        $pricing = $this->get('/api/md/pricing.md')->assertOk()->getContent();
        foreach (config('japa.plans') as $plan) {
            $this->assertStringContainsString("| {$plan['name']} | ₦".number_format($plan['price']), $pricing);
        }
    }

    public function test_md_urls_and_negotiated_page_paths_return_markdown(): void
    {
        $this->get('/pricing.md')->assertOk()->assertHeader('Content-Location', 'https://japacoach.com/pricing');
        $this->get('/index.md')->assertOk()->assertHeader('Content-Location', 'https://japacoach.com/');
        $this->get('/terms', ['Accept' => 'text/markdown'])->assertOk()->assertHeader('Content-Location', 'https://japacoach.com/terms');
        $this->get('/', ['Accept' => 'text/markdown'])->assertOk()->assertHeader('Content-Location', 'https://japacoach.com/');
        $this->get('/dashboard.md')->assertNotFound();
        $this->call('OPTIONS', '/pricing.md')->assertNoContent();
    }

    public function test_private_and_unknown_pages_return_a_markdown_404(): void
    {
        foreach (['dashboard', 'admin', 'login', 'does-not-exist'] as $path) {
            $response = $this->get("/api/md/{$path}")->assertNotFound();
            $this->assertStringStartsWith('text/markdown', $response->headers->get('Content-Type'));
            $this->assertStringStartsWith('# Not found', $response->getContent());
        }
    }

    public function test_catalog_and_site_files(): void
    {
        $this->getJson('/api/md/_catalog')->assertOk()
            ->assertJsonPath('pages.0.markdown_url', 'https://japacoach.com/index.md')
            ->assertJsonCount(count(MarkdownPages::paths()), 'pages');

        $llms = $this->get('/llms.txt')->assertOk();
        $this->assertStringStartsWith('text/plain', $llms->headers->get('Content-Type'));
        $this->assertStringContainsString('# Japa Coach', $llms->getContent());
        $this->assertStringContainsString('(https://japacoach.com/pricing.md)', $llms->getContent());
        $this->assertStringContainsString('## Optional', $llms->getContent());

        $full = $this->get('/llms-full.txt')->assertOk()->getContent();
        foreach (MarkdownPages::paths() as $path) {
            $this->assertStringContainsString('<!-- source: '.SiteContent::url($path).' -->', $full);
        }

        $ai = $this->get('/ai.txt')->assertOk()->getContent();
        foreach (['[identity]', '[permissions]', '[restrictions]', '[attribution]', '[scope]', '[contact]'] as $section) {
            $this->assertStringContainsString($section, $ai);
        }
        $this->assertStringContainsString('/dashboard', $ai);
    }

    public function test_robots_lets_ai_crawlers_read_public_pages_but_not_private_ones(): void
    {
        $robots = $this->get('/robots.txt')->assertOk()->getContent();

        foreach (['OAI-SearchBot', 'Claude-SearchBot', 'PerplexityBot', 'GPTBot', 'ClaudeBot'] as $bot) {
            $this->assertMatchesRegularExpression("/User-agent: {$bot}\nAllow: \/\nAllow: \/api\/md\/\nDisallow: \/dashboard/", $robots);
        }
        $this->assertStringContainsString('/llms.txt', $robots);
        $this->assertStringContainsString('Sitemap: https://japacoach.com/sitemap.xml', $robots);

        $sitemap = $this->get('/sitemap.xml')->getContent();
        $this->assertStringNotContainsString('.md<', $sitemap);
    }
}
