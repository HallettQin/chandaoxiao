<?php
//cgxlm
namespace Illuminate\Database\Eloquent;

class FactoryBuilder
{
	use \Illuminate\Support\Traits\Macroable;

	/**
     * The model definitions in the container.
     *
     * @var array
     */
	protected $definitions;
	/**
     * The model being built.
     *
     * @var string
     */
	protected $class;
	/**
     * The name of the model being built.
     *
     * @var string
     */
	protected $name = 'default';
	/**
     * The model states.
     *
     * @var array
     */
	protected $states;
	/**
     * The states to apply.
     *
     * @var array
     */
	protected $activeStates = array();
	/**
     * The Faker instance for the builder.
     *
     * @var \Faker\Generator
     */
	protected $faker;
	/**
     * The number of models to build.
     *
     * @var int|null
     */
	protected $amount;

	public function __construct($class, $name, array $definitions, array $states, \Faker\Generator $faker)
	{
		$this->name = $name;
		$this->class = $class;
		$this->faker = $faker;
		$this->states = $states;
		$this->definitions = $definitions;
	}

	public function times($amount)
	{
		$this->amount = $amount;
		return $this;
	}

	public function states($states)
	{
		$this->activeStates = is_array($states) ? $states : func_get_args();
		return $this;
	}

	public function lazy(array $attributes = array())
	{
		return function() use($attributes) {
			return $this->create($attributes);
		};
	}

	public function create(array $attributes = array())
	{
		$results = $this->make($attributes);

		if ($results instanceof Model) {
			$this->store(collect(array($results)));
		}
		else {
			$this->store($results);
		}

		return $results;
	}

	protected function store($results)
	{
		$results->each(function($model) {
			$model->setConnection($model->newQueryWithoutScopes()->getConnection()->getName());
			$model->save();
		});
	}

	public function make(array $attributes = array())
	{
		if ($this->amount === null) {
			return $this->makeInstance($attributes);
		}

		if ($this->amount < 1) {
			return (new $this->class())->newCollection();
		}

		return (new $this->class())->newCollection(array_map(function() use($attributes) {
			return $this->makeInstance($attributes);
		}, range(1, $this->amount)));
	}

	public function raw(array $attributes = array())
	{
		if ($this->amount === null) {
			return $this->getRawAttributes($attributes);
		}

		if ($this->amount < 1) {
			return array();
		}

		return array_map(function() use($attributes) {
			return $this->getRawAttributes($attributes);
		}, range(1, $this->amount));
	}

	protected function getRawAttributes(array $attributes = array())
	{
		$definition = call_user_func($this->definitions[$this->class][$this->name], $this->faker, $attributes);
		return $this->expandAttributes(array_merge($this->applyStates($definition, $attributes), $attributes));
	}

	protected function makeInstance(array $attributes = array())
	{
		return Model::unguarded(function() use($attributes) {
			if (!isset($this->definitions[$this->class][$this->name])) {
				throw new \InvalidArgumentException('Unable to locate factory with name [' . $this->name . '] [' . $this->class . '].');
			}

			return new $this->class($this->getRawAttributes($attributes));
		});
	}

	protected function applyStates(array $definition, array $attributes = array())
	{
		foreach ($this->activeStates as $state) {
			if (!isset($this->states[$this->class][$state])) {
				throw new \InvalidArgumentException('Unable to locate [' . $state . '] state for [' . $this->class . '].');
			}

			$definition = array_merge($definition, call_user_func($this->states[$this->class][$state], $this->faker, $attributes));
		}

		return $definition;
	}

	protected function expandAttributes(array $attributes)
	{
		foreach ($attributes as &$attribute) {
			if ($attribute instanceof \Closure) {
				$attribute = $attribute($attributes);
			}

			if ($attribute instanceof static) {
				$attribute = $attribute->create()->getKey();
			}

			if ($attribute instanceof Model) {
				$attribute = $attribute->getKey();
			}
		}

		return $attributes;
	}
}

?>
