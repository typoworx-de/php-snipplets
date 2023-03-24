<?php
declare(strict_types=1);
namespace TYPOworx\Promise;

class Promise implements PromiseInterface
{
    use PromiseTrait {
      // In case one wants to customize this name for custom contexts
      //PromiseTrait::evaluate as expects;
    }

    public static function create() : self
    {
        return new (static::class)();
    }
}
