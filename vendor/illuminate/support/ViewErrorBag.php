<?php
//cgxlm
namespace Illuminate\Support;

class ViewErrorBag implements \Countable
{
	/**
     * The array of the view error bags.
     *
     * @var array
     */
	protected $bags = array();

	public function hasBag($key = 'default')
	{
		return isset($this->bags[$key]);
	}

	public function getBag($key)
	{
		return Arr::get($this->bags, $key) ?: new MessageBag();
	}

	public function getBags()
	{
		return $this->bags;
	}

	public function put($key, \Illuminate\Contracts\Support\MessageBag $bag)
	{
		$this->bags[$key] = $bag;
		return $this;
	}

	public function any()
	{
		return 0 < $this->count();
	}

	public function count()
	{
		return $this->getBag('default')->count();
	}

	public function __call($method, $parameters)
	{
		return $this->getBag('default')->$method(...$parameters);
	}

	public function __get($key)
	{
		return $this->getBag($key);
	}

	public function __set($key, $value)
	{
		$this->put($key, $value);
	}
}

?>
