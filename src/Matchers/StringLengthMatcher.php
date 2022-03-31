<?php declare(strict_types = 1);

namespace Fuzzyness\Matchers;

class StringLengthMatcher extends BaseMatcher
{
    /**
     * The operator to use for the WHERE clause.
     *
     **/
    protected string $operator = '=';

    /**
     * The process for building the query string.
     *
     **/
    public function buildQueryString(string $field, string $value) : string
    {
        $search = $this->formatSearchString($value);

        return "IF(
                    CHAR_LENGTH(REPLACE($field, ' ', '')) {$this->operator} CHAR_LENGTH('$search'),
                    {$this->multiplier},
                    0
                )";
    }

    /**
     * Format the given search term.
     *
     **/
    public function formatSearchString(string $value) : string
    {
        $value = str_replace(' ', '', $value);

        return $value;
    }
}
