<?php declare(strict_types = 1);

namespace Fuzzyness\Matchers;

class ExactMatcher extends BaseMatcher
{
    /**
     * The operator to use for the WHERE clause.
     *
     **/
    protected string $operator = '=';
}
