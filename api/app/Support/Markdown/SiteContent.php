<?php

namespace App\Support\Markdown;

use Illuminate\Support\Facades\File;

/**
 * Page copy shared with the Vue app (resources/content). The website and the markdown versions of pages
 * read the same files, so they never disagree.
 */
class SiteContent
{
    /**
     * @return array<string, array<string, mixed>> keyed by path, e.g. "/pricing"
     */
    public static function pages(): array
    {
        return self::json('seo.json');
    }

    /**
     * @return array<string, mixed>
     */
    public static function json(string $file): array
    {
        return once(fn () => json_decode(File::get(resource_path("content/{$file}")), true, flags: JSON_THROW_ON_ERROR));
    }

    public static function markdown(string $file): string
    {
        return trim(File::get(resource_path("content/{$file}")));
    }

    public static function url(string $path = '/'): string
    {
        return rtrim((string) config('japa.frontend_url'), '/').($path === '/' ? '/' : $path);
    }

    /** The .md address of a page: /pricing -> /pricing.md, / -> /index.md */
    public static function markdownUrl(string $path): string
    {
        return self::url(($path === '/' ? '/index' : $path).'.md');
    }
}
