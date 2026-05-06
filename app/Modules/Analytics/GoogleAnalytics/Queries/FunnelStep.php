<?php

namespace App\Modules\Analytics\GoogleAnalytics\Queries;

/**
 * One step in a GA4 funnel.
 *
 * v1 surface: each step matches a single event name, optionally further
 * narrowed by a dimension equality / contains check (most commonly pagePath).
 * Combined with FunnelFilterExpression at compile time. Richer parameter
 * filtering can be added later without changing this DTO's shape.
 */
final class FunnelStep
{
    public const MATCH_EXACT       = 'exact';
    public const MATCH_CONTAINS    = 'contains';
    public const MATCH_BEGINS_WITH = 'begins_with';

    private function __construct(
        public readonly string $name,
        public readonly string $eventName,
        public readonly ?string $dimensionField,
        public readonly ?string $dimensionValue,
        public readonly string $matchType,
    ) {}

    /** Match any occurrence of the given event. */
    public static function event(string $stepName, string $eventName): self
    {
        return new self($stepName, $eventName, null, null, self::MATCH_EXACT);
    }

    /**
     * Match a page_view where the page_location event parameter matches.
     *
     * GA's funnel API doesn't accept "pagePath" as a dimension here — it
     * routes through the page_location event parameter instead. We default
     * to CONTAINS match since page_location is the full URL.
     */
    public static function pageView(
        string $stepName,
        string $pageMatch,
        string $matchType = self::MATCH_CONTAINS,
    ): self {
        return new self($stepName, 'page_view', 'page_location', $pageMatch, $matchType);
    }

    /** Match an event filtered by a dimension equality / contains / begins_with check. */
    public static function eventWithDimension(
        string $stepName,
        string $eventName,
        string $dimensionField,
        string $dimensionValue,
        string $matchType = self::MATCH_EXACT,
    ): self {
        return new self($stepName, $eventName, $dimensionField, $dimensionValue, $matchType);
    }

    /**
     * Build from an array shape — entry point for AI tool calls.
     * Accepted shapes:
     *  - {name, event}
     *  - {name, event, field, value, matchType?}
     *  - {name, page} (page is shortcut: event=page_view, field=pagePath, value=page, matchType=contains)
     */
    public static function fromArray(array $data): self
    {
        $name = $data['name'] ?? throw new \InvalidArgumentException('FunnelStep: missing "name".');

        if (isset($data['page'])) {
            return self::pageView(
                stepName: (string) $name,
                pageMatch: (string) $data['page'],
                matchType: $data['matchType'] ?? self::MATCH_CONTAINS,
            );
        }

        $event = $data['event'] ?? throw new \InvalidArgumentException("FunnelStep '{$name}': missing 'event' or 'page'.");

        if (isset($data['field'], $data['value'])) {
            return self::eventWithDimension(
                stepName: (string) $name,
                eventName: (string) $event,
                dimensionField: (string) $data['field'],
                dimensionValue: (string) $data['value'],
                matchType: $data['matchType'] ?? self::MATCH_EXACT,
            );
        }

        return self::event((string) $name, (string) $event);
    }

    public function signature(): array
    {
        return [
            'n' => $this->name,
            'e' => $this->eventName,
            'f' => $this->dimensionField,
            'v' => $this->dimensionValue,
            'mt' => $this->matchType,
        ];
    }
}
