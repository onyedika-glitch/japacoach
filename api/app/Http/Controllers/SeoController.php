<?php

namespace App\Http\Controllers;

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

        $urls = collect(config('japa.seo.pages'))->map(fn (array $page): array => [
            'loc' => $base.($page['path'] === '/' ? '/' : $page['path']),
            'lastmod' => Carbon::parse($page['updated'])->toDateString(),
            'changefreq' => $page['changefreq'],
            'priority' => $page['priority'],
        ]);

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function robots(): Response
    {
        $lines = ['User-agent: *', 'Allow: /'];

        foreach (config('japa.seo.disallow') as $path) {
            $lines[] = "Disallow: {$path}";
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.rtrim(config('japa.frontend_url'), '/').'/sitemap.xml';

        return response(implode("\n", $lines)."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
