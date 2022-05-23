<?php

declare(strict_types=1);

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
use Fuzzyness\Matchers\SpacelessLengthMatcher;

class WhereFuzzy
{
    /**
     * The weights for the pattern matching classes.
     *
     **/
    protected static array $matchers = [
        ExactMatcher::class                 => 100,
        StartOfStringMatcher::class         => 50,
        SpacelessLengthMatcher::class       => 45,
        AcronymMatcher::class               => 42,
        ConsecutiveCharactersMatcher::class => 40,
        SpacelessMatcher::class             => 35,
    ];

    protected static array $extendedMatchers = [
        StartOfWordsMatcher::class          => 35,
        StudlyCaseMatcher::class            => 32,
        InStringMatcher::class              => 30,
        TimesInStringMatcher::class         => 8,
    ];

    protected static int $ratingScale = 15;

    /**
     * Construct a fuzzy search expression.
     *
     **/
    public static function make($builder, $field, $value, $function = null, $rating = null): Builder
    {
        $value       = static::escapeValue($value);
        $nativeField = '`' . str_replace('.', '`.`', trim($field, '` ')) . '`';

        if (!is_array($builder->columns) || empty($builder->columns)) {
            $builder->columns = ['*'];
        }



        $builder
            ->addSelect([static::pipeline($field, $nativeField, $value, $function, $rating)])
            ->having('fuzzy_relevance_' . str_replace('.', '_', $field), '>', 0);

        return $builder;
    }

    /**
     * Construct a fuzzy OR search expression.
     *
     **/
    public static function makeOr($builder, $field, $value, $function = null, $rating = null): Builder
    {
        $value       = static::escapeValue($value);
        $nativeField = '`' . str_replace('.', '`.`', trim($field, '` ')) . '`';

        if (!is_array($builder->columns) || empty($builder->columns)) {
            $builder->columns = ['*'];
        }

        $builder
            ->addSelect([static::pipeline($field, $nativeField, $value, $function, $rating)])
            ->orHaving('fuzzy_relevance_' . str_replace('.', '_', $field), '>', 0);

        return $builder;
    }

    /**
     * Construct a CUSTOM fuzzy search expression.
     *
     **/
    public static function makeCustom($builder, $field, $value, array $matchers = [], $function = null): Builder
    {
        $value       = static::escapeValue($value);
        $nativeField = '`' . str_replace('.', '`.`', trim($field, '` ')) . '`';

        if (!is_array($builder->columns) || empty($builder->columns)) {
            $builder->columns = ['*'];
        }

        $builder
            ->addSelect([static::pipelineCustom($field, $nativeField, $value, $matchers, $function)])
            ->having('fuzzy_relevance_' . str_replace('.', '_', $field), '>', 0);

        return $builder;
    }

    /**
     * Construct a CUSTOM fuzzy OR search expression.
     *
     **/
    public static function makeCustomOr($builder, $field, $value, array $matchers = [], $function = null): Builder
    {
        $value       = static::escapeValue($value);
        $nativeField = '`' . str_replace('.', '`.`', trim($field, '` ')) . '`';

        if (!is_array($builder->columns) || empty($builder->columns)) {
            $builder->columns = ['*'];
        }

        $builder
            ->addSelect([static::pipelineCustom($field, $nativeField, $value, $matchers, $function)])
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
    protected static function pipeline($field, $native, $value, $function, $rating): Expression
    {
        $matchers = static::$matchers;

        $sql = collect($matchers)->map(
            fn ($multiplier, $matcher) => (new $matcher($multiplier))->buildQueryString("COALESCE($native, '')", $value)
        );

        $ratingSql = null;


        if ($rating != null && is_array($rating)) {
            $ratingSql = '(' . $rating['table'] . '.' . $rating['field'] . ' / (SELECT MAX(' . $rating['field'] . ') FROM ' . $rating['table'] . ')) * ' . self::$ratingScale;
        }

        $query = $sql->implode(' + ');
        if ($ratingSql != null) {
            $query .= ' + ' . $ratingSql;
        }

        if ($function) {
            return DB::raw($function . '(' . $query . ') AS fuzzy_relevance_' . str_replace('.', '_', $field));
        }

        return DB::raw($query . ' AS fuzzy_relevance_' . str_replace('.', '_', $field));
    }

    /**
     * Execute each of the CUSTOM pattern matching classes to generate the required SQL.
     *
     **/
    protected static function pipelineCustom($field, $native, $value, array $matchers, $function): Expression
    {
        $sql = collect($matchers)->map(
            fn ($multiplier, $matcher) => (new $matcher($multiplier))->buildQueryString("COALESCE($native, '')", $value)
        );




        return DB::raw('MAX(' . $sql->implode(' + ') . ') AS fuzzy_relevance_' . str_replace('.', '_', $field));
    }
}
