<?php
declare(strict_types=1);
namespace TYPOworx\Promise\Promise;

interface PromiseInterface
{
    public function resolver(callable $resolver) : PromiseInterface;

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): PromiseInterface;

    public function reject(callable $onRejected) : PromiseInterface;

    public function catch(callable $onCatchException) : PromiseInterface;

    public function finally(callable $onFulfilledOrRejected): PromiseInterface;

    public function resolve() : void;

    public function getResult() : mixed;
}
