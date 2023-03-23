<?php
declare(strict_types=1);
namespace TYPOworx\Promise;

/**
 * Promise/Resolver
 *
 * Promise::create()
 *   ->resolver(function() use($request, $options) {
 *       // $this is in scope of Promise!
 *       $this->propagadeRejected();    // Will force our promise to be rejected
 *       $this->propagadeSuccess();     // Will force our promise to be successful even if we didn't process it at all
 *
 *       return $request->apiRequest($options);
 *   })
 *   ->then(function() {
 *      // Proceed on success
 *      var_dump($this->getResult());
 *   })
 *   ->rejected(function() {
 *      // React on reject
 *   })
 *   ->catch(function($exception) {
 *    var_dump($exception);
 *   })
 *  ->resolve()
 * ;
 */
class Promise implements PromiseInterface
{
    private bool $isResolved = false;
    private bool $propagadeRejected = false;
    private bool $propagadeSuccessful = false;

    /** @var callable */
    private $callableResolver;

    /** @var callable */
    private $callbackThen;

    /** @var callable|null */
    private $callbackReject;

    /** @var callable|null */
    private $callbackCatch;

    /** @var callable|null */
    private $callbackFinally;

    private mixed $result = null;


    public static function create() : self
    {
        return new (static::class)();
    }

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null) : self
    {
        $this->callbackThen = $onFulfilled;
        $this->callbackReject = $onRejected;

        return $this;
    }

    public function reject(callable $onRejected) : self
    {
        $this->callbackReject = $onRejected;

        return $this;
    }

    public function catch(callable $onCatchException) : self
    {
        $this->callbackCatch = $onCatchException;

        return $this;
    }

    public function finally(callable $onFulfilledOrRejected) : self
    {
        $this->callbackFinally = $onFulfilledOrRejected;

        return $this;
    }

    private function call($callable, mixed ...$arguments) : self
    {
        if (is_callable($callable))
        {
            $return = \Closure::fromCallable($callable)
                ->call($this, $arguments)
            ;

            if ($callable instanceof $this->callableResolver)
            {
                $this->result = $return;
            }
            else if ($callable instanceof $this->callbackReject)
            {
                $this->isFailed = true;
            }
            else if ($callable instanceof $this->callbackCatch)
            {
                $this->isFailed = true;
            }
        }

        return $this;
    }

    private function propagadeSuccessful() : void
    {
        $this->propagadeSuccessful = true;
        $this->propagadeRejected = false;
    }

    private function isSuccessful() : bool
    {
        return $this->propagadeSuccessful;
    }

    private function propagadeRejected() : void
    {
        $this->propagadeSuccessful = false;
        $this->propagadeRejected = true;

        throw new RejectedThrowable();
    }

    private function isRejected() : bool
    {
        return $this->propagadeRejected;
    }

    private function isFailed() : bool
    {
        return $this->isFailed;
    }

    private function isResolved() : bool
    {
        return $this->isResolved;
    }

    public function resolver(callable $resolver) : self
    {
        $this->callableResolver = $resolver;

        return $this;
    }

    public function resolve() : void
    {
        if ($this->isResolved)
        {
            return;
        }

        try
        {
            $this->call($this->callableResolver);

            if ($this->result || $this->propagadeSuccessful = true)
            {
                $this->isResolved = true;
                $this->call($this->callbackThen);
            }
            else
            {
                $this->call($this->callbackReject);
            }

            $this->call($this->callbackFinally);
        }
        catch (RejectedThrowable $e)
        {
            $this->call($this->callbackReject);
        }
        catch (\Throwable $e)
        {
            if ($this->callbackCatch)
            {
                $this->call($this->callbackCatch, $e);
            }

            throw $e;
        }
    }

    public function getResult() : mixed
    {
        if ($this->isResolved)
        {
            $this->resolve();
        }
        
        return $this->result;
    }
}
