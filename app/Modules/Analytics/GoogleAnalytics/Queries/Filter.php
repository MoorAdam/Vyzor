<?php

namespace App\Modules\Analytics\GoogleAnalytics\Queries;

use Google\Analytics\Data\V1beta\Filter as GaFilter;
use Google\Analytics\Data\V1beta\Filter\BetweenFilter as GaBetweenFilter;
use Google\Analytics\Data\V1beta\Filter\InListFilter as GaInListFilter;
use Google\Analytics\Data\V1beta\Filter\NumericFilter as GaNumericFilter;
use Google\Analytics\Data\V1beta\Filter\NumericFilter\Operation as GaNumericOp;
use Google\Analytics\Data\V1beta\Filter\StringFilter as GaStringFilter;
use Google\Analytics\Data\V1beta\Filter\StringFilter\MatchType as GaMatchType;
use Google\Analytics\Data\V1beta\FilterExpression as GaFilterExpression;
use Google\Analytics\Data\V1beta\FilterExpressionList as GaFilterExpressionList;
use Google\Analytics\Data\V1beta\NumericValue as GaNumericValue;

/**
 * Friendly wrapper for GA4's recursive FilterExpression structure.
 *
 * Build leaves with the static factories (equals, contains, in, gt, ...) and
 * combine them with all() / any() / not() for AND / OR / NOT logic.
 *
 * Compiles to GA4's FilterExpression via toGaExpression(). Stable hashing
 * via signature() makes it cache-key-safe.
 *
 * Used as the dimensionFilter or metricFilter on a ReportRequest. GA4 routes
 * dimension fieldNames to one slot and metric fieldNames to another, so a
 * single Filter instance can target either.
 */
final class Filter
{
    public const KIND_LEAF = 'leaf';
    public const KIND_AND  = 'and';
    public const KIND_OR   = 'or';
    public const KIND_NOT  = 'not';

    public const OP_EQUALS      = 'equals';
    public const OP_CONTAINS    = 'contains';
    public const OP_BEGINS_WITH = 'begins_with';
    public const OP_ENDS_WITH   = 'ends_with';
    public const OP_REGEXP      = 'regexp';
    public const OP_IN          = 'in';
    public const OP_GT          = 'gt';
    public const OP_GTE         = 'gte';
    public const OP_LT          = 'lt';
    public const OP_LTE         = 'lte';
    public const OP_BETWEEN     = 'between';
    public const OP_EMPTY       = 'empty';

    private function __construct(
        public readonly string $kind,
        public readonly array $payload,
    ) {}

    // ── Leaf factories ─────────────────────────────────────────────

    public static function equals(string $field, string $value, bool $caseSensitive = false): self
    {
        return self::leaf($field, self::OP_EQUALS, ['value' => $value, 'caseSensitive' => $caseSensitive]);
    }

    public static function contains(string $field, string $value, bool $caseSensitive = false): self
    {
        return self::leaf($field, self::OP_CONTAINS, ['value' => $value, 'caseSensitive' => $caseSensitive]);
    }

    public static function beginsWith(string $field, string $value, bool $caseSensitive = false): self
    {
        return self::leaf($field, self::OP_BEGINS_WITH, ['value' => $value, 'caseSensitive' => $caseSensitive]);
    }

    public static function endsWith(string $field, string $value, bool $caseSensitive = false): self
    {
        return self::leaf($field, self::OP_ENDS_WITH, ['value' => $value, 'caseSensitive' => $caseSensitive]);
    }

    public static function regexp(string $field, string $pattern, bool $caseSensitive = false): self
    {
        return self::leaf($field, self::OP_REGEXP, ['value' => $pattern, 'caseSensitive' => $caseSensitive]);
    }

    /** @param list<string> $values */
    public static function in(string $field, array $values, bool $caseSensitive = false): self
    {
        return self::leaf($field, self::OP_IN, ['values' => array_values($values), 'caseSensitive' => $caseSensitive]);
    }

    public static function gt(string $field, float $value): self
    {
        return self::leaf($field, self::OP_GT, ['value' => $value]);
    }

    public static function gte(string $field, float $value): self
    {
        return self::leaf($field, self::OP_GTE, ['value' => $value]);
    }

    public static function lt(string $field, float $value): self
    {
        return self::leaf($field, self::OP_LT, ['value' => $value]);
    }

    public static function lte(string $field, float $value): self
    {
        return self::leaf($field, self::OP_LTE, ['value' => $value]);
    }

    public static function between(string $field, float $from, float $to): self
    {
        return self::leaf($field, self::OP_BETWEEN, ['from' => $from, 'to' => $to]);
    }

    public static function isEmpty(string $field): self
    {
        return self::leaf($field, self::OP_EMPTY, []);
    }

    private static function leaf(string $field, string $op, array $extra): self
    {
        return new self(self::KIND_LEAF, ['field' => $field, 'op' => $op, ...$extra]);
    }

    // ── Combinators ────────────────────────────────────────────────

    public static function all(self ...$filters): self
    {
        return new self(self::KIND_AND, ['filters' => $filters]);
    }

    public static function any(self ...$filters): self
    {
        return new self(self::KIND_OR, ['filters' => $filters]);
    }

    public static function not(self $filter): self
    {
        return new self(self::KIND_NOT, ['filter' => $filter]);
    }

    /**
     * Build from an array shape — convenient entry point for AI tool calls.
     *
     * Accepted shapes:
     *  - Single leaf:    ['field' => 'deviceCategory', 'op' => 'equals', 'value' => 'mobile']
     *  - List of leaves: [{...}, {...}]   → ANDed together
     *  - Combinator:     ['op' => 'and'|'or', 'filters' => [{...}, {...}]]
     *  - Negation:       ['op' => 'not', 'filter' => {...}]
     */
    public static function fromArray(array $data): self
    {
        if (array_is_list($data)) {
            $children = array_map(fn ($d) => self::fromArray($d), $data);
            return self::all(...$children);
        }

        $op = $data['op'] ?? null;

        if ($op === self::KIND_AND || $op === self::KIND_OR) {
            $children = array_map(fn ($d) => self::fromArray($d), $data['filters'] ?? []);
            return $op === self::KIND_AND ? self::all(...$children) : self::any(...$children);
        }
        if ($op === self::KIND_NOT) {
            return self::not(self::fromArray($data['filter']));
        }

        $field = $data['field'] ?? throw new \InvalidArgumentException('Filter: missing "field".');
        $cs    = (bool) ($data['caseSensitive'] ?? false);

        return match ($op) {
            self::OP_EQUALS      => self::equals($field, (string) $data['value'], $cs),
            self::OP_CONTAINS    => self::contains($field, (string) $data['value'], $cs),
            self::OP_BEGINS_WITH => self::beginsWith($field, (string) $data['value'], $cs),
            self::OP_ENDS_WITH   => self::endsWith($field, (string) $data['value'], $cs),
            self::OP_REGEXP      => self::regexp($field, (string) $data['value'], $cs),
            self::OP_IN          => self::in($field, (array) $data['values'], $cs),
            self::OP_GT          => self::gt($field, (float) $data['value']),
            self::OP_GTE         => self::gte($field, (float) $data['value']),
            self::OP_LT          => self::lt($field, (float) $data['value']),
            self::OP_LTE         => self::lte($field, (float) $data['value']),
            self::OP_BETWEEN     => self::between($field, (float) $data['from'], (float) $data['to']),
            self::OP_EMPTY       => self::isEmpty($field),
            default              => throw new \InvalidArgumentException("Filter: unknown op '{$op}'."),
        };
    }

    /**
     * Stable signature suitable for cache keys. Identical filter trees
     * (regardless of construction path) produce identical signatures.
     */
    public function signature(): string
    {
        return sha1(json_encode($this->normalize(), JSON_UNESCAPED_SLASHES));
    }

    private function normalize(): array
    {
        if ($this->kind === self::KIND_LEAF) {
            return ['k' => 'l', 'p' => $this->payload];
        }
        if ($this->kind === self::KIND_NOT) {
            return ['k' => 'n', 'f' => $this->payload['filter']->normalize()];
        }
        // and / or — sort children by their own signature for stability
        $children = array_map(fn (self $f) => $f->normalize(), $this->payload['filters']);
        usort($children, fn ($a, $b) => json_encode($a) <=> json_encode($b));
        return ['k' => $this->kind === self::KIND_AND ? 'a' : 'o', 'c' => $children];
    }

    public function toGaExpression(): GaFilterExpression
    {
        return match ($this->kind) {
            self::KIND_LEAF => (new GaFilterExpression())->setFilter($this->buildGaFilter()),
            self::KIND_NOT  => (new GaFilterExpression())
                ->setNotExpression($this->payload['filter']->toGaExpression()),
            self::KIND_AND  => (new GaFilterExpression())
                ->setAndGroup(new GaFilterExpressionList([
                    'expressions' => array_map(fn (self $f) => $f->toGaExpression(), $this->payload['filters']),
                ])),
            self::KIND_OR   => (new GaFilterExpression())
                ->setOrGroup(new GaFilterExpressionList([
                    'expressions' => array_map(fn (self $f) => $f->toGaExpression(), $this->payload['filters']),
                ])),
        };
    }

    private function buildGaFilter(): GaFilter
    {
        $filter = (new GaFilter())->setFieldName($this->payload['field']);

        return match ($this->payload['op']) {
            self::OP_EQUALS, self::OP_CONTAINS, self::OP_BEGINS_WITH, self::OP_ENDS_WITH, self::OP_REGEXP
                => $filter->setStringFilter($this->buildStringFilter()),
            self::OP_IN
                => $filter->setInListFilter(new GaInListFilter([
                    'values' => $this->payload['values'],
                    'case_sensitive' => $this->payload['caseSensitive'],
                ])),
            self::OP_GT, self::OP_GTE, self::OP_LT, self::OP_LTE
                => $filter->setNumericFilter($this->buildNumericFilter()),
            self::OP_BETWEEN
                => $filter->setBetweenFilter(new GaBetweenFilter([
                    'from_value' => $this->numericValue($this->payload['from']),
                    'to_value'   => $this->numericValue($this->payload['to']),
                ])),
            self::OP_EMPTY
                => $filter->setEmptyFilter(new \Google\Analytics\Data\V1beta\Filter\EmptyFilter()),
        };
    }

    private function buildStringFilter(): GaStringFilter
    {
        $matchType = match ($this->payload['op']) {
            self::OP_EQUALS      => GaMatchType::EXACT,
            self::OP_BEGINS_WITH => GaMatchType::BEGINS_WITH,
            self::OP_ENDS_WITH   => GaMatchType::ENDS_WITH,
            self::OP_CONTAINS    => GaMatchType::CONTAINS,
            self::OP_REGEXP      => GaMatchType::FULL_REGEXP,
        };
        return new GaStringFilter([
            'value'          => $this->payload['value'],
            'match_type'     => $matchType,
            'case_sensitive' => $this->payload['caseSensitive'],
        ]);
    }

    private function buildNumericFilter(): GaNumericFilter
    {
        $op = match ($this->payload['op']) {
            self::OP_GT  => GaNumericOp::GREATER_THAN,
            self::OP_GTE => GaNumericOp::GREATER_THAN_OR_EQUAL,
            self::OP_LT  => GaNumericOp::LESS_THAN,
            self::OP_LTE => GaNumericOp::LESS_THAN_OR_EQUAL,
        };
        return new GaNumericFilter([
            'operation' => $op,
            'value'     => $this->numericValue($this->payload['value']),
        ]);
    }

    private function numericValue(float $n): GaNumericValue
    {
        // GA4 distinguishes int64_value from double_value — pick by whether n is whole.
        $v = new GaNumericValue();
        if (floor($n) === $n && abs($n) < PHP_INT_MAX) {
            $v->setInt64Value((int) $n);
        } else {
            $v->setDoubleValue($n);
        }
        return $v;
    }
}
