<?php declare(strict_types = 1);

namespace Fuzzyness\Tests;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    /**
     * Disable timestamps.
     *
     **/
    public $timestamps = false;
}
