<?php

namespace BuckarooPayment\Components\JsonApi\Payload;

use JsonSerializable;
use ArrayAccess;
use BuckarooPayment\Components\Base\Arrayable;
use BuckarooPayment\Components\Helpers;

class Request implements JsonSerializable, ArrayAccess, Arrayable
{
    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var array
     */
    protected $headers = [];

    public function __construct($data = [])
    {
        $this->data = $data;
    }

    /** Implement ArrayAccess */
    public function offsetSet($offset, $value) : void
    {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    /** Implement ArrayAccess */
    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    /** Implement ArrayAccess */
    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    /** Implement ArrayAccess */
    public function offsetGet($offset)
    {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    /** Implement JsonSerializable */
    public function jsonSerialize()
    {
        return $this->data;
    }

    /**
     * Redirect all method calls prefixed with 'get' or 'set'
     * to check if a param exists with that name
     * Return or set the param if it does
     *
     * @param  string $method
     * @param  array  $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        $prefix = substr($method, 0, 3);
        $param = substr($method, 3);
        $arg = isset($args[0]) ? $args[0] : null;

        if( $prefix === 'set' )
        {
            return $this->offsetSet($param, $arg);
        }
        else if( $prefix === 'get' )
        {
            return $this->offsetGet($param);
        }

        throw new \Exception("Call to undefined method " . __CLASS__ . '::' . $method);
    }

    /** Implement Arrayable */
    public function toArray()
    {
        return $this->data;
    }

    /**
     * @param string $name
     * @param string $value
     */
    public function setHeader($name, $value)
    {
        $this->headers[strtolower($name)] = $value;
    }

    /**
     * @param  string $name
     * @return string
     */
    public function getHeader($name)
    {
        if( isset($this->headers[strtolower($name)]) )
        {
            return $this->headers[strtolower($name)];
        }

        return null;
    }

    /**
     * @return array [ string ]
     */
    public function getHeaders()
    {
        return Helpers::arrayMap($this->headers, function($value, $key) {
            return $key . ': ' . $value;
        });
    }
}
