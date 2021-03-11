<?php

namespace LaravelCloudSearch;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Traits\ForwardsCalls;
use LaravelCloudSearch\Contracts\FieldType;
use LaravelCloudSearch\Exceptions\MissingDomainException;
use LaravelCloudSearch\Traits\HasFields;

/**
 * Class Document
 *
 * @mixin Query
 * @mixin Builder
 */
abstract class Document implements Arrayable, ArrayAccess, FieldType
{
    use ForwardsCalls,
        HasFields;

    /** @var string **/
    protected $domain = '';

    /** @var int|string */
    protected $id;

    /**
     * The primary key for the domain.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * @return \LaravelCloudSearch\Document
     */
    public function newInstance(): self
    {
        return new static;
    }

    /**
     * @return Builder
     */
    public function newQuery(): Builder
    {
        return (new Builder(new Query()))->setDocument($this);
    }

    /**
     * @return Builder
     */
    public static function query(): Builder
    {
        return (new static)->newQuery();
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * @return string
     *
     * @throws \LaravelCloudSearch\Exceptions\MissingDomainException
     */
    public function getDomain(): string
    {
        // See if a domain is specified
        if ($this->domain) {
            return $this->domain;
        }

        throw new MissingDomainException($this);
    }

    /**
     * @param int|string $id
     *
     * @return $this
     */
    public function setId($id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @param int|string $id
     *
     * @return int|string
     */
    public function getId($id)
    {
        return $this->id;
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->forwardCallTo($this->newQuery(), $method, $parameters);
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->getField($key);
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->setField($key, $value);
    }

    /**
     * Determine if the given attribute exists.
     *
     * @param  mixed  $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return !is_null($this->getField($offset));
    }

    /**
     * Get the value for a given offset.
     *
     * @param  mixed  $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->getField($offset);
    }

    /**
     * Set the value for a given offset.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->setField($offset, $value);
    }

    /**
     * Unset the value for a given offset.
     *
     * @param  mixed  $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->fields[$offset]);
    }

    /**
     * Determine if an attribute or relation exists on the model.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return $this->offsetExists($key);
    }

    /**
     * Unset an attribute on the model.
     *
     * @param  string  $key
     * @return void
     */
    public function __unset($key)
    {
        $this->offsetUnset($key);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->getFields();
    }
}
