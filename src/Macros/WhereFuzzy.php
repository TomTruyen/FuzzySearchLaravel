<?php declare(strict_types=1);

namespace Fuzzyness\Macros;

use Fuzzyness\Matchers\ExactMatcher;
use Illuminate\Support\Facades\DB;
use Fuzzyness\Matchers\AcronymMatcher;
use Illuminate\Database\Query\Builder;
use Fuzzyness\Matchers\StartOfStringMatcher;
use Illuminate\Database\Query\Expression;
use Fuzzyness\Matchers\ConsecutiveCharactersMatcher;

class WhereFuzzy
{
    /**
     * The weights for the pattern matching classes.
     *
     **/
    protected static array $matchers = [
        ExactMatcher::class                 => 100,
        StartOfStringMatcher::class         => 50,
        AcronymMatcher::class               => 42,
        ConsecutiveCharactersMatcher::class => 40,
    ];

    /**
     * Construct a fuzzy search expression.
     *
     **/
    public static function make($builder, $field, $value): Builder
    {
        $value       = static::escapeValue($value);
        $nativeField = '`' . str_replace('.', '`.`', trim($field, '` ')) . '`';

        if (! is_array($builder->columns) || empty($builder->columns)) {
            $builder->columns = ['*'];
        }

        $builder
            ->addSelect([static::pipeline($field, $nativeField, $value)])
            ->having('fuzzy_relevance_' . str_replace('.', '_', $field), '>', 0);

        return $builder;
    }

    /**
     * Construct a fuzzy OR search expression.
     *
     **/
    public static function makeOr($builder, $field, $value): Builder
    {
        $value       = static::escapeValue($value);
        $nativeField = '`' . str_replace('.', '`.`', trim($field, '` ')) . '`';

        if (! is_array($builder->columns) || empty($builder->columns)) {
            $builder->columns = ['*'];
        }

        $builder
            ->addSelect([static::pipeline($field, $nativeField, $value)])
            ->orHaving('fuzzy_relevance_' . str_replace('.', '_', $field), '>', 0);

        return $builder;
    }

    /**
     * Escape value input for fuzzy search.
     */
    protected static function escapeValue($value)
    {
        $value = str_replace(['"', "'", '`'], '', $value);
        $value = substr(DB::connection()->getPdo()->quote($value), 1, -1);

        return $value;
    }

    /**
     * Execute each of the pattern matching classes to generate the required SQL.
     *
     **/
    protected static function pipeline($field, $native, $value): Expression
    {
        $sql = collect(static::$matchers)->map(
            fn($multiplier, $matcher) => (new $matcher($multiplier))->buildQueryString("COALESCE($native, '')", $value)
        );

        return DB::raw($sql->implode(' + ') . ' AS fuzzy_relevance_' . str_replace('.', '_', $field));
    }
}
