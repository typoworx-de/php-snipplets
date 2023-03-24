<?php
declare(strict_types=1);
namespace TYPOworx\Promise;

class Promise implements PromiseInterface
{
    use PromiseTrait {
        PromiseTrait::evaluate as expects;
    }

    public static function create() : self
    {
        return new (static::class)();
    }
}
