<?php

namespace App\Http\Controllers;

use App\Support\Markdown\MarkdownPages;
use App\Support\Markdown\SiteContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Pages and site files for AI agents: the markdown version of each public page (/pricing.md, or
 * /pricing with Accept: text/markdown; Caddy routes both here), llms.txt, llms-full.txt and ai.txt.
 * Keep robots.txt (SeoController), llms.txt and ai.txt in sync with each other.
 */
class MarkdownController extends Controller
{
    /**
     * The markdown version of a page. $path is the page path without the leading slash ("pricing", "index" or "").
     */
    public function show(?string $path = null): Response
    {
        $path = '/'.trim(preg_replace('/\.md$/', '', (string) $path) ?? '', '/');
        $path = $path === '/index' ? '/' : $path;
        $markdown = MarkdownPages::render($path);

        if ($markdown === null) {
            return $this->markdown("# Not found\n\nThere is no public page at ".SiteContent::url($path).".\n\nSee the list of pages: ".SiteContent::url('/llms.txt')."\n", 404);
        }

        $updated = SiteContent::pages()[$path]['updated'] ?? null;

        return $this->markdown($markdown, 200, SiteContent::url($path), $updated);
    }

    /**
     * A public page requested with Accept: text/markdown (Caddy only forwards those requests here).
     */
    public function negotiated(?string $page = null): Response
    {
        return $this->show($page ?? 'index');
    }

    /**
     * Every page that has a markdown version.
     */
    public function catalog(): JsonResponse
    {
        $pages = collect(MarkdownPages::paths())->map(fn (string $path): array => [
            'path' => $path,
            'title' => MarkdownPages::title($path),
            'description' => SiteContent::pages()[$path]['description'],
            'url' => SiteContent::url($path),
            'markdown_url' => SiteContent::markdownUrl($path),
            'updated' => SiteContent::pages()[$path]['updated'] ?? null,
        ]);

        return response()->json(['site' => config('japa.site.name'), 'pages' => $pages])
            ->withHeaders($this->sharedHeaders() + ['Cache-Control' => 'public, s-maxage=3600, stale-while-revalidate=86400']);
    }

    /**
     * llms.txt (llmstxt.org): who we are and where the pages are. Keep in sync with robots.txt and ai.txt.
     */
    public function llms(): Response
    {
        $site = config('japa.site');
        $line = fn (string $path): string => '- ['.(SiteContent::pages()[$path]['title'] ?? 'Home').']('.SiteContent::markdownUrl($path).'): '.SiteContent::pages()[$path]['description'];
        $legal = ['/terms', '/privacy', '/delete-account'];
        $paths = collect(MarkdownPages::paths());

        $text = implode("\n", [
            "# {$site['name']}",
            '',
            "> {$site['description']}",
            '',
            "{$site['facts']} Contact: {$site['contact_email']}.",
            '',
            '<!-- Keep in sync with /robots.txt and /ai.txt -->',
            '',
            '## Core pages',
            ...$paths->reject(fn ($p) => in_array($p, $legal, true))->map($line),
            '',
            '## Machine-readable',
            '- [Full content]('.SiteContent::url('/llms-full.txt').'): every page above in one file',
            '- [Markdown catalog]('.SiteContent::url('/api/md/_catalog').'): JSON list of pages with markdown versions',
            '- [Sitemap]('.SiteContent::url('/sitemap.xml').')',
            '- [AI usage policy]('.SiteContent::url('/ai.txt').')',
            '',
            '## Optional',
            ...$paths->filter(fn ($p) => in_array($p, $legal, true))->map($line),
        ])."\n";

        return $this->text($text, 86400);
    }

    /**
     * Every markdown page joined together.
     */
    public function llmsFull(): Response
    {
        $site = config('japa.site');
        $parts = [
            "# {$site['name']}: full content",
            "> {$site['description']}",
            'Generated: '.now()->toDateString(),
        ];
        foreach (MarkdownPages::paths() as $path) {
            $parts[] = "\n---\n<!-- source: ".SiteContent::url($path)." -->\n".MarkdownPages::render($path);
        }
        $text = implode("\n", $parts)."\n";

        if (strlen($text) > 500_000) {
            Log::warning('llms-full.txt is larger than 500k characters', ['chars' => strlen($text)]);
        }

        return $this->text($text, 86400, 604800);
    }

    /**
     * ai.txt (ai-txt spec v1.1.1): how AI systems may use our content. Keep in sync with robots.txt and llms.txt.
     */
    public function aiTxt(): Response
    {
        $site = config('japa.site');
        $disallow = config('japa.seo.disallow');

        $text = implode("\n", [
            '# ai.txt for '.SiteContent::url('/'),
            '# Keep in sync with /robots.txt and /llms.txt',
            '',
            '[identity]',
            "name: {$site['name']}",
            'url: '.SiteContent::url('/'),
            "description: {$site['description']}",
            "contact: {$site['contact_email']}",
            '',
            '[permissions]',
            'summarise: allowed',
            'quote: allowed with attribution',
            'cite: allowed',
            'index: allowed (search and answers)',
            'training: allowed on public pages',
            '',
            '[restrictions]',
            '- Do not fabricate quotes, reviews, ratings, band scores or prices.',
            '- Do not present our band estimates as official IELTS results; we are not affiliated with IELTS, the British Council, IDP or Cambridge.',
            '- Do not present our content as your own.',
            '- Never use private user data: answers, recordings, essays and account pages are not public.',
            '',
            '[attribution]',
            "format: {$site['name']} (".SiteContent::url('/').') with a link to the page\'s canonical HTML URL',
            '',
            '[scope]',
            'include: /, /pricing, /terms, /privacy, /delete-account, /llms.txt, /llms-full.txt, /*.md, /api/md/',
            'exclude: '.implode(', ', $disallow),
            '',
            '[contact]',
            "email: {$site['contact_email']}",
            '',
            'Last updated: '.collect(SiteContent::pages())->max('updated'),
        ])."\n";

        return $this->text($text, 86400);
    }

    private function markdown(string $body, int $status, ?string $canonical = null, ?string $updated = null): Response
    {
        $headers = $this->sharedHeaders() + [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'X-Markdown-Tokens' => (string) (int) ceil(strlen($body) / 4),
            'X-Robots-Tag' => 'noindex, follow',
            'Vary' => 'Accept',
            'Cache-Control' => $status === 200 ? 'public, s-maxage=3600, stale-while-revalidate=86400' : 'public, max-age=300',
        ];
        if ($canonical) {
            $headers['Content-Location'] = $canonical;
            $headers['Link'] = "<{$canonical}>; rel=\"canonical\"";
        }
        if ($updated) {
            $headers['Last-Modified'] = Carbon::parse($updated)->startOfDay()->toRfc7231String();
        }

        return response($body, $status, $headers);
    }

    private function text(string $body, int $maxAge, ?int $staleWhileRevalidate = null): Response
    {
        return response($body, 200, $this->sharedHeaders() + [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => "public, s-maxage={$maxAge}".($staleWhileRevalidate ? ", stale-while-revalidate={$staleWhileRevalidate}" : ''),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function sharedHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => 'Accept',
        ];
    }

    /**
     * CORS preflight for the markdown endpoints.
     */
    public function options(): Response
    {
        return response('', 204, $this->sharedHeaders() + ['Access-Control-Max-Age' => '86400']);
    }
}
