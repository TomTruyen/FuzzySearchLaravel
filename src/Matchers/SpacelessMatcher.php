<?php declare(strict_types = 1);

namespace Fuzzyness\Matchers;

class SpacelessMatcher extends BaseMatcher {
    /**
     * The operator to use for the WHERE clause.
     *
     **/
    protected string $operator = 'LIKE';

    /**
     * Format the given search term.
     *
     **/
    public function formatSearchString(string $value) : string
    {
        $value = str_replace(' ', '', $value);

        return "%$value%";
    }
}
