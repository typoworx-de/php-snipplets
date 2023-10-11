<?php
declare(strict_types=1);
namespace Foo\Bar\Domain\Object;

use ArrayObject;

class IndexedArrayCollection extends ArrayObject
{
    private ?int $pointer = null;


    public function add(mixed $value) : int
    {
        $index = $this->getIndex() + 1;

        $this->offsetSet($index, $value);

        return $index;
    }

    public function getIndex() : int
    {
        if ($this->pointer > 0)
        {
            return $this->pointer;
        }

        $this->pointer = 0;

        return $this->pointer;
    }

    public function next() : mixed
    {
        $index = $this->getIndex();

        if ($index < $this->count())
        {
            $this->pointer = $index + 1;
        }

        return $this->offsetGet($this->pointer);
    }

    public function prev() : mixed
    {
        $index = $this->getIndex();

        if ($index > 0)
        {
            $this->pointer = $index - 1;
        }

        return $this->offsetGet($this->pointer);
    }

    public function current() : mixed
    {
        $this->pointer = $this->count() > 0 ? 0 : null;

        if ($this->pointer === null)
        {
            return null;
        }

        return $this->offsetGet($this->pointer) ?? null;
    }

    public function end() : mixed
    {
        $this->pointer = $this->count();

        return $this->offsetGet($this->pointer) ?? null;
    }

    public function shift() : mixed
    {
        $return = null;
        if ($this->offsetExists(0))
        {
            $return = $this->offsetGet(0);
            $this->offsetUnset(0);
        }

        return $return;
    }

    public function pop() : mixed
    {
        $pointer = $this->count();

        $return = null;
        if ($this->offsetExists($pointer))
        {
            $return = $this->offsetGet($pointer);
            $this->offsetUnset($pointer);
        }

        return $return;
    }
}
