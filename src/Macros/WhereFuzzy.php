<?php declare(strict_types=1);

namespace Fuzzyness\Macros;

use Illuminate\Support\Facades\DB;
use Fuzzyness\Matchers\ExactMatcher;
use Fuzzyness\Matchers\AcronymMatcher;
use Illuminate\Database\Query\Builder;
use Fuzzyness\Matchers\InStringMatcher;
use Fuzzyness\Matchers\SpacelessMatcher;
use Fuzzyness\Matchers\StudlyCaseMatcher;
use Illuminate\Database\Query\Expression;
use Fuzzyness\Matchers\StartOfWordsMatcher;
use Fuzzyness\Matchers\StartOfStringMatcher;
use Fuzzyness\Matchers\TimesInStringMatcher;
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
        SpacelessMatcher::class             => 45,
        AcronymMatcher::class               => 42,
        ConsecutiveCharactersMatcher::class => 40,
    ];

    protected static array $extendedMatchers = [
        StartOfWordsMatcher::class          => 35,
        StudlyCaseMatcher::class            => 32,
        InStringMatcher::class              => 30,
        TimesInStringMatcher::class         => 8,
    ];

    /**
     * Construct a fuzzy search expression.
     *
     **/
    public static function make($builder, $field, $value, bool $extended = false): Builder
    {
        $value       = static::escapeValue($value);
        $nativeField = '`' . str_replace('.', '`.`', trim($field, '` ')) . '`';

        if (! is_array($builder->columns) || empty($builder->columns)) {
            $builder->columns = ['*'];
        }

        $builder
            ->addSelect([static::pipeline($field, $nativeField, $value, $extended)])
            ->having('fuzzy_relevance_' . str_replace('.', '_', $field), '>', 0);

        return $builder;
    }

    /**
     * Construct a fuzzy OR search expression.
     *
     **/
    public static function makeOr($builder, $field, $value, bool $extended = false): Builder
    {
        $value       = static::escapeValue($value);
        $nativeField = '`' . str_replace('.', '`.`', trim($field, '` ')) . '`';

        if (! is_array($builder->columns) || empty($builder->columns)) {
            $builder->columns = ['*'];
        }

        $builder
            ->addSelect([static::pipeline($field, $nativeField, $value, $extended)])
            ->orHaving('fuzzy_relevance_' . str_replace('.', '_', $field), '>', 0);

        return $builder;
    }

    /**
     * Construct a CUSTOM fuzzy search expression.
     *
     **/
    public static function makeCustom($builder, $field, $value, array $matchers = []): Builder
    {
        $value       = static::escapeValue($value);
        $nativeField = '`' . str_replace('.', '`.`', trim($field, '` ')) . '`';

        if (! is_array($builder->columns) || empty($builder->columns)) {
            $builder->columns = ['*'];
        }

        $builder
            ->addSelect([static::pipelineCustom($field, $nativeField, $value, $matchers)])
            ->having('fuzzy_relevance_' . str_replace('.', '_', $field), '>', 0);

        return $builder;
    }

    /**
     * Construct a CUSTOM fuzzy OR search expression.
     *
     **/
    public static function makeCustomOr($builder, $field, $value, array $matchers = []): Builder
    {
        $value       = static::escapeValue($value);
        $nativeField = '`' . str_replace('.', '`.`', trim($field, '` ')) . '`';

        if (! is_array($builder->columns) || empty($builder->columns)) {
            $builder->columns = ['*'];
        }

        $builder
            ->addSelect([static::pipelineCustom($field, $nativeField, $value, $matchers)])
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
    protected static function pipeline($field, $native, $value, bool $extended = false): Expression
    {
        $matchers = static::$matchers;

        if($extended) {
            $matchers = array_merge(static::$matchers, static::$extendedMatchers);
        }

        $sql = collect($matchers)->map(
            fn($multiplier, $matcher) => (new $matcher($multiplier))->buildQueryString("COALESCE($native, '')", $value)
        );

        return DB::raw($sql->implode(' + ') . ' AS fuzzy_relevance_' . str_replace('.', '_', $field));
    }

     /**
     * Execute each of the CUSTOM pattern matching classes to generate the required SQL.
     *
     **/
    protected static function pipelineCustom($field, $native, $value, array $matchers): Expression
    {
        $sql = collect($matchers)->map(
            fn($multiplier, $matcher) => (new $matcher($multiplier))->buildQueryString("COALESCE($native, '')", $value)
        );

        return DB::raw($sql->implode(' + ') . ' AS fuzzy_relevance_' . str_replace('.', '_', $field));
    }
}
