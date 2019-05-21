<?php
//cgxlm
namespace Illuminate\Support;

class HigherOrderCollectionProxy
{
	/**
     * The collection being operated on.
     *
     * @var \Illuminate\Support\Collection
     */
	protected $collection;
	/**
     * The method being proxied.
     *
     * @var string
     */
	protected $method;

	public function __construct(Collection $collection, $method)
	{
		$this->method = $method;
		$this->collection = $collection;
	}

	public function __get($key)
	{
		return $this->collection->{$this->method}(function($value) use($key) {
			return is_array($value) ? $value[$key] : $value->$key;
		});
	}

	public function __call($method, $parameters)
	{
		return $this->collection->{$this->method}(function($value) use($method, $parameters) {
			return $value->$method(...$parameters);
		});
	}
}


?>
