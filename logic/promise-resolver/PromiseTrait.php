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
 *   ->evaluate(function() {
 *     return $this->getResult() === null;
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
 *   ->finally(function() {
 *   });
 *  ->resolve()
 * ;
 */
trait PromiseTrait
{
    private bool $propagadeSuccessful = false;
    private bool $propagadeRejected = false;
    private bool $propagadeResolved = false;
    private bool $propagadeFailed = false;

    /** @var callable */
    private $callableResolver;

    /** @var callable */
    private $callbackThen;

    /** @var callable|null */
    private $callbackReject;

    /** @var callable|null */
    private $callbackEvaluate;

    /** @var callable|null */
    private $callbackCatch;

    /** @var callable|null */
    private $callbackFinally;

    private mixed $result = null;


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

    public function evaluate(callable $onEvaluate) : self
    {
        $this->callbackEvaluate = $onEvaluate;

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
                $this->propagadeFailed = true;
            }
            else if ($callable instanceof $this->callbackEvaluate)
            {
                if ($return === null || $return === false)
                {
                    $this->propagadeFailed = true;
                }
            }
            else if ($callable instanceof $this->callbackCatch)
            {
                $this->propagadeFailed = true;
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

    public function isRejected() : bool
    {
        return $this->propagadeRejected;
    }

    public function isResolved() : bool
    {
        return $this->propagadeResolved;
    }

    private function propagadeFailed() : void
    {
        $this->propagadeFailed = true;
        $this->propagadeSuccessful = false;
        $this->propagadeRejected = false;
    }

    public function isFailed() : bool
    {
        return $this->propagadeFailed;
    }

    public function isReady() : bool
    {
        return $this->isResolved() || $this->isRejected() || $this->isFailed();
    }

    public function resolver(callable $resolver) : self
    {
        $this->callableResolver = $resolver;

        return $this;
    }

    public function resolve() : void
    {
        if ($this->propagadeResolved)
        {
            return;
        }

        try
        {
            $this->call($this->callableResolver);

            if ($this->result || $this->propagadeSuccessful = true)
            {
                $this->propagadeResolved = true;
                $this->call($this->callbackEvaluate);
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
        if (!($this->isResolved() || $this->isRejected() || $this->isFailed()))
        {
            $this->resolve();
        }

        return $this->result;
    }
}
