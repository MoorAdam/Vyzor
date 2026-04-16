<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches and cleans HTML from a URL.
 *
 * Returns the visible text content of a page, stripped of scripts, styles,
 * and excessive whitespace. Designed for AI consumption — the output is
 * compact enough to fit in a prompt while retaining semantic structure.
 */
class HtmlFetcherService
{
    /** Maximum body size to accept (2 MB). */
    protected const MAX_BODY_BYTES = config('services.htmlFetcher.max_body_bytes', 2 * 1024 * 1024);

    /** Request timeout in seconds. */
    protected const TIMEOUT = config('services.htmlFetcher.timeout', 10);

    /**
     * Fetch a URL and return cleaned text content.
     *
     * Returns null on failure (network error, non-HTML response, etc.).
     * Errors are logged but never thrown — callers can always fall back
     * to a URL-only prompt.
     */
    public function fetch(string $url): ?string
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'User-Agent' => 'VyzorBot/1.0 (page-analysis)',
                    'Accept' => 'text/html',
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::warning("HtmlFetcherService: HTTP {$response->status()} for {$url}");
                return null;
            }

            $body = $response->body();

            // Guard against excessively large responses.
            if (strlen($body) > self::MAX_BODY_BYTES) {
                $body = substr($body, 0, self::MAX_BODY_BYTES);
            }

            return $this->clean($body);
        } catch (\Throwable $e) {
            Log::warning("HtmlFetcherService: failed to fetch {$url} — {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Strip HTML down to readable text while keeping semantic markers
     * (headings, links, alt text) that are useful for AI analysis.
     */
    protected function clean(string $html): string
    {
        // Extract <title>
        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
            $title = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }

        // Extract meta description
        $metaDesc = '';
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/si', $html, $m)) {
            $metaDesc = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }

        // Extract meta keywords (if present)
        $metaKeywords = '';
        if (preg_match('/<meta[^>]+name=["\']keywords["\'][^>]+content=["\'](.*?)["\']/si', $html, $m)) {
            $metaKeywords = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }

        // Extract canonical
        $canonical = '';
        if (preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\'](.*?)["\']/si', $html, $m)) {
            $canonical = trim($m[1]);
        }

        // Extract hreflang tags
        $hreflangs = [];
        if (preg_match_all('/<link[^>]+rel=["\']alternate["\'][^>]+hreflang=["\']([^"\']+)["\'][^>]+href=["\']([^"\']+)["\']/si', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $hreflangs[] = "{$match[1]}: {$match[2]}";
            }
        }

        // Extract OG tags
        $ogTags = [];
        if (preg_match_all('/<meta[^>]+property=["\']og:([^"\']+)["\'][^>]+content=["\'](.*?)["\']/si', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $ogTags[] = "og:{$match[1]}: {$match[2]}";
            }
        }

        // Remove non-visible content.
        $text = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        $text = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $text);
        $text = preg_replace('/<noscript[^>]*>.*?<\/noscript>/si', '', $text);
        $text = preg_replace('/<!--.*?-->/s', '', $text);

        // Convert headings to markdown-style markers so the AI sees structure.
        $text = preg_replace('/<h1[^>]*>(.*?)<\/h1>/si', "\n# $1\n", $text);
        $text = preg_replace('/<h2[^>]*>(.*?)<\/h2>/si', "\n## $1\n", $text);
        $text = preg_replace('/<h3[^>]*>(.*?)<\/h3>/si', "\n### $1\n", $text);
        $text = preg_replace('/<h4[^>]*>(.*?)<\/h4>/si', "\n#### $1\n", $text);
        $text = preg_replace('/<h5[^>]*>(.*?)<\/h5>/si', "\n##### $1\n", $text);
        $text = preg_replace('/<h6[^>]*>(.*?)<\/h6>/si', "\n###### $1\n", $text);

        // Preserve link hrefs: <a href="...">text</a> → [text](href)
        $text = preg_replace('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/si', '[$2]($1)', $text);

        // Preserve image alt text: <img alt="..."> → [image: alt]
        $text = preg_replace('/<img[^>]+alt=["\']([^"\']+)["\'][^>]*\/?>/si', '[image: $1]', $text);

        // Convert list items to bullet points.
        $text = preg_replace('/<li[^>]*>/si', "\n- ", $text);

        // Line breaks for block elements.
        $text = preg_replace('/<\/(p|div|section|article|header|footer|main|nav|aside|blockquote|tr)>/si', "\n", $text);
        $text = preg_replace('/<br\s*\/?>/si', "\n", $text);

        // Strip remaining tags.
        $text = strip_tags($text);

        // Decode entities.
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Collapse whitespace: multiple blank lines → one, trim each line.
        $lines = array_map('trim', explode("\n", $text));
        $lines = array_filter($lines, fn($line) => $line !== '');
        $content = implode("\n", $lines);

        // Build the final output with metadata header.
        $output = '';

        if ($title) {
            $output .= "Title: {$title}\n";
        }
        if ($metaDesc) {
            $output .= "Meta description: {$metaDesc}\n";
        }
        if ($metaKeywords) {
            $output .= "Meta keywords: {$metaKeywords}\n";
        }
        if ($canonical) {
            $output .= "Canonical: {$canonical}\n";
        }
        if ($hreflangs) {
            $output .= "Hreflang: " . implode(' | ', $hreflangs) . "\n";
        }
        if ($ogTags) {
            $output .= implode("\n", $ogTags) . "\n";
        }

        $output .= "\n---\n\n" . $content;

        // Truncate to keep prompt size reasonable (~60k chars ≈ ~15k tokens).
        if (strlen($output) > 60000) {
            $output = substr($output, 0, 60000) . "\n\n[Content truncated]";
        }

        return $output;
    }
}
