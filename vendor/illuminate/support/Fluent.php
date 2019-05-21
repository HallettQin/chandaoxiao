<?php
//cgxlm
namespace Illuminate\Support;

class Fluent implements \ArrayAccess, \Illuminate\Contracts\Support\Arrayable, \Illuminate\Contracts\Support\Jsonable, \JsonSerializable
{
	/**
     * All of the attributes set on the container.
     *
     * @var array
     */
	protected $attributes = array();

	public function __construct($attributes = array())
	{
		foreach ($attributes as $key => $value) {
			$this->attributes[$key] = $value;
		}
	}

	public function get($key, $default = NULL)
	{
		if (array_key_exists($key, $this->attributes)) {
			return $this->attributes[$key];
		}

		return value($default);
	}

	public function getAttributes()
	{
		return $this->attributes;
	}

	public function toArray()
	{
		return $this->attributes;
	}

	public function jsonSerialize()
	{
		return $this->toArray();
	}

	public function toJson($options = 0)
	{
		return json_encode($this->jsonSerialize(), $options);
	}

	public function offsetExists($offset)
	{
		return isset($this->$offset);
	}

	public function offsetGet($offset)
	{
		return $this->$offset;
	}

	public function offsetSet($offset, $value)
	{
		$this->$offset = $value;
	}

	public function offsetUnset($offset)
	{
		unset($this->$offset);
	}

	public function __call($method, $parameters)
	{
		$this->attributes[$method] = 0 < count($parameters) ? $parameters[0] : true;
		return $this;
	}

	public function __get($key)
	{
		return $this->get($key);
	}

	public function __set($key, $value)
	{
		$this->attributes[$key] = $value;
	}

	public function __isset($key)
	{
		return isset($this->attributes[$key]);
	}

	public function __unset($key)
	{
		unset($this->attributes[$key]);
	}
}

?>
