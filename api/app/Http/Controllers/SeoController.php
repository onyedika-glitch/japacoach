<?php

namespace App\Http\Controllers;

use App\Support\Markdown\SiteContent;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Search engine files: the XML sitemap of public pages and robots.txt.
 */
class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $base = rtrim(config('japa.frontend_url'), '/');

        // .md versions stay out of the sitemap: they are noindex alternates of these pages.
        $urls = collect(SiteContent::pages())->map(fn (array $page, string $path): array => [
            'loc' => $base.($path === '/' ? '/' : $path),
            'lastmod' => Carbon::parse($page['updated'])->toDateString(),
            'changefreq' => $page['changefreq'],
            'priority' => $page['priority'],
        ]);

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * AI crawlers named explicitly so answer engines can always read and cite our public pages.
     * Search and user-fetch bots power citations; training bots are allowed by the owner's choice.
     */
    private const AI_CRAWLERS = [
        'OAI-SearchBot', 'ChatGPT-User', 'Claude-SearchBot', 'Claude-User', 'PerplexityBot', 'Perplexity-User',
        'GPTBot', 'ClaudeBot', 'Google-Extended', 'Applebot-Extended', 'CCBot', 'Bytespider',
    ];

    public function robots(): Response
    {
        $base = rtrim(config('japa.frontend_url'), '/');
        // The markdown catalog lives under /api but is public (the longer, more specific Allow wins).
        $disallow = ['Allow: /api/md/', ...array_map(fn (string $path): string => "Disallow: {$path}", config('japa.seo.disallow'))];

        // A bot that matches its own group ignores the * group, so each group repeats the private paths.
        $lines = [
            '# Keep in sync with /llms.txt and /ai.txt',
            "# AI agents: site guide at {$base}/llms.txt, usage policy at {$base}/ai.txt, markdown pages at /<page>.md",
            '',
            'User-agent: *',
            'Allow: /',
            ...$disallow,
            '',
        ];
        foreach (self::AI_CRAWLERS as $bot) {
            $lines = [...$lines, "User-agent: {$bot}", 'Allow: /', ...$disallow, ''];
        }
        $lines[] = "Sitemap: {$base}/sitemap.xml";

        return response(implode("\n", $lines)."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
