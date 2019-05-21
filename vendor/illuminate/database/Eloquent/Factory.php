<?php
//cgxlm
namespace Illuminate\Database\Eloquent;

class Factory implements \ArrayAccess
{
	/**
     * The model definitions in the container.
     *
     * @var array
     */
	protected $definitions = array();
	/**
     * The registered model states.
     *
     * @var array
     */
	protected $states = array();
	/**
     * The Faker instance for the builder.
     *
     * @var \Faker\Generator
     */
	protected $faker;

	public function __construct(\Faker\Generator $faker)
	{
		$this->faker = $faker;
	}

	static public function construct(\Faker\Generator $faker, $pathToFactories = NULL)
	{
		$pathToFactories = $pathToFactories ?: database_path('factories');
		return (new static($faker))->load($pathToFactories);
	}

	public function defineAs($class, $name,  $attributes)
	{
		return $this->define($class, $attributes, $name);
	}

	public function define($class,  $attributes, $name = 'default')
	{
		$this->definitions[$class][$name] = $attributes;
		return $this;
	}

	public function state($class, $state,  $attributes)
	{
		$this->states[$class][$state] = $attributes;
		return $this;
	}

	public function create($class, array $attributes = array())
	{
		return $this->of($class)->create($attributes);
	}

	public function createAs($class, $name, array $attributes = array())
	{
		return $this->of($class, $name)->create($attributes);
	}

	public function make($class, array $attributes = array())
	{
		return $this->of($class)->make($attributes);
	}

	public function makeAs($class, $name, array $attributes = array())
	{
		return $this->of($class, $name)->make($attributes);
	}

	public function rawOf($class, $name, array $attributes = array())
	{
		return $this->raw($class, $attributes, $name);
	}

	public function raw($class, array $attributes = array(), $name = 'default')
	{
		return array_merge(call_user_func($this->definitions[$class][$name], $this->faker), $attributes);
	}

	public function of($class, $name = 'default')
	{
		return new FactoryBuilder($class, $name, $this->definitions, $this->states, $this->faker);
	}

	public function load($path)
	{
		$factory = $this;

		if (is_dir($path)) {
			foreach (\Symfony\Component\Finder\Finder::create()->files()->name('*.php')->in($path) as $file) {
				require $file->getRealPath();
			}
		}

		return $factory;
	}

	public function offsetExists($offset)
	{
		return isset($this->definitions[$offset]);
	}

	public function offsetGet($offset)
	{
		return $this->make($offset);
	}

	public function offsetSet($offset, $value)
	{
		return $this->define($offset, $value);
	}

	public function offsetUnset($offset)
	{
		unset($this->definitions[$offset]);
	}
}

?>
