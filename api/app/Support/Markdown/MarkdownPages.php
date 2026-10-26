<?php

namespace App\Support\Markdown;

use App\Models\Review;
use Illuminate\Support\Str;

/**
 * The markdown version of every public page, rendered from the same data as the HTML page.
 * To give a new public page a markdown version: add it to resources/content/seo.json with
 * "markdown": true and add its renderer to renderers() below. llms.txt, llms-full.txt and the
 * catalog pick it up automatically (a test fails if a markdown page has no renderer).
 */
class MarkdownPages
{
    /**
     * Page body renderers, keyed by path. Each returns the main content (without title/summary header).
     *
     * @return array<string, callable(): string>
     */
    public static function renderers(): array
    {
        return [
            '/' => self::home(...),
            '/pricing' => self::pricing(...),
            '/terms' => fn () => SiteContent::markdown('terms.md'),
            '/privacy' => fn () => SiteContent::markdown('privacy.md'),
            '/delete-account' => self::deleteAccount(...),
        ];
    }

    /**
     * Every page with a markdown version, in llms.txt order (core pages, then legal).
     *
     * @return list<string>
     */
    public static function paths(): array
    {
        return collect(SiteContent::pages())
            ->filter(fn (array $page): bool => (bool) ($page['markdown'] ?? false))
            ->keys()
            ->sortBy(fn (string $path): int => array_flip(['/', '/pricing', '/terms', '/privacy', '/delete-account'])[$path] ?? 99)
            ->values()
            ->all();
    }

    /**
     * The full markdown document for a page, or null if the path has no public markdown version.
     */
    public static function render(string $path): ?string
    {
        $page = SiteContent::pages()[$path] ?? null;
        $renderer = self::renderers()[$path] ?? null;
        if (! $page || ! ($page['markdown'] ?? false) || ! $renderer) {
            return null;
        }

        $title = self::title($path);

        return implode("\n", [
            "# {$title}",
            '',
            '> '.$page['description'],
            '',
            'Canonical: '.SiteContent::url($path),
            'Last updated: '.$page['updated'],
            '',
            trim($renderer()),
            '',
            '## Related',
            ...self::related($path),
        ])."\n";
    }

    public static function title(string $path): string
    {
        $page = SiteContent::pages()[$path] ?? [];

        return $path === '/'
            ? config('japa.site.name').': AI IELTS practice for Nigerians'
            : ($page['title'] ?? ltrim($path, '/')).' · '.config('japa.site.name');
    }

    private static function home(): string
    {
        $home = SiteContent::json('home.json');
        $out = [
            '## '.$home['hero']['title'],
            '',
            $home['hero']['subtitle'],
            '',
            '## '.$home['how']['title'],
            '',
        ];
        foreach ($home['how']['steps'] as $i => $step) {
            $out[] = ($i + 1).". **{$step['title']}:** {$step['text']}";
        }

        $out = [...$out, '', '## '.$home['scoring']['title'], '', $home['scoring']['intro'], ''];
        foreach ($home['scoring']['cards'] as $card) {
            $out[] = "- **{$card['title']}:** {$card['text']}";
        }

        $out = [...$out, '', '## '.$home['modules']['title'], '', $home['modules']['intro'], ''];
        foreach ($home['modules']['items'] as $item) {
            $out[] = "- **{$item['title']}:** {$item['text']}";
        }

        $out = [...$out, '', '## Pricing', '', self::pricingSummary(), '', 'Full details: ['.config('japa.site.name').' pricing]('.SiteContent::url('/pricing').')'];

        $reviews = Review::where('status', 'approved')->where('can_publish', true)->latest('approved_at')->limit(6)->get();
        if ($reviews->isNotEmpty()) {
            $out = [...$out, '', '## What test takers say', ''];
            foreach ($reviews as $review) {
                $who = $review->display_name.($review->location ? ", {$review->location}" : '');
                $out[] = "- {$review->rating}/5, {$who}: \"".str_replace("\n", ' ', $review->comment).'"';
            }
        }

        $out = [...$out, '', '## '.$home['faq']['title'], ''];
        foreach ($home['faq']['items'] as $item) {
            $out = [...$out, "### {$item['q']}", '', $item['a'], ''];
        }

        return implode("\n", $out);
    }

    private static function pricing(): string
    {
        $limits = config('japa.limits.free');
        $limit = fn (string $key, string $label): string => ($limits[$key] ?? null) === null
            ? 'Unlimited '.Str::plural($label).' per 30 days'
            : $limits[$key].' '.Str::plural($label, (int) $limits[$key]).' per 30 days';

        $out = [
            self::pricingSummary(),
            '',
            '## Free plan',
            '',
            '- '.$limit('speaking_mocks', 'full speaking mock'),
            '- '.$limit('speaking_drills', 'speaking drill'),
            '- '.$limit('writing', 'writing evaluation'),
            '- '.$limit('reading', 'reading test'),
            '- '.$limit('listening', 'listening test'),
            '',
            '## Paid passes',
            '',
            '| Pass | Price | Access | Includes |',
            '| --- | --- | --- | --- |',
        ];
        foreach (config('japa.plans') as $plan) {
            $out[] = "| {$plan['name']} | ".self::naira($plan['price'])." | {$plan['days']} days | ".implode('; ', $plan['features']).' |';
        }

        return implode("\n", [
            ...$out,
            '',
            '## How paying works',
            '',
            '- One-off payments in Nigerian naira through Bachs on the website. No automatic renewal.',
            '- In the Android app, passes are bought through Google Play.',
            '- Buying again adds the new days on top of any time you have left.',
        ]);
    }

    private static function deleteAccount(): string
    {
        $content = SiteContent::json('delete-account.json');

        return implode("\n", [
            $content['intro'],
            '',
            '## What gets deleted',
            '',
            ...array_map(fn (string $item): string => "- {$item}", $content['deleted']),
            '',
            $content['note'],
            '',
            '## How to delete it',
            '',
            ...array_map(fn (int $i, string $step): string => ($i + 1).". {$step}", array_keys($content['steps']), $content['steps']),
            '',
            $content['fallback'],
            '',
            'Delete your account: ['.SiteContent::url('/delete-account').']('.SiteContent::url('/delete-account').')',
        ]);
    }

    private static function pricingSummary(): string
    {
        $cheapest = collect(config('japa.plans'))->min('price');

        return 'Free to start with no card. Paid passes from '.self::naira((float) $cheapest).' a week give more practice for a fixed number of days.';
    }

    private static function naira(float $amount): string
    {
        return '₦'.number_format($amount);
    }

    /**
     * @return list<string>
     */
    private static function related(string $path): array
    {
        $links = [
            '/' => 'Home',
            '/pricing' => 'Pricing',
            '/signup' => 'Start free (create an account)',
            '/terms' => 'Terms of use',
            '/privacy' => 'Privacy policy',
        ];

        return collect($links)
            ->except($path)
            ->map(fn (string $text, string $link): string => "- [{$text}](".SiteContent::url($link).')')
            ->values()
            ->all();
    }
}
