<?php
//cgxlm
namespace Illuminate\Database\Eloquent;

class Collection extends \Illuminate\Support\Collection implements \Illuminate\Contracts\Queue\QueueableCollection
{
	public function find($key, $default = NULL)
	{
		if ($key instanceof Model) {
			$key = $key->getKey();
		}

		if (is_array($key)) {
			if ($this->isEmpty()) {
				return new static();
			}

			return $this->whereIn($this->first()->getKeyName(), $key);
		}

		return \Illuminate\Support\Arr::first($this->items, function($model) use($key) {
			return $model->getKey() == $key;
		}, $default);
	}

	public function load($relations)
	{
		if (0 < count($this->items)) {
			if (is_string($relations)) {
				$relations = func_get_args();
			}

			$query = $this->first()->newQuery()->with($relations);
			$this->items = $query->eagerLoadRelations($this->items);
		}

		return $this;
	}

	public function add($item)
	{
		$this->items[] = $item;
		return $this;
	}

	public function contains($key, $operator = NULL, $value = NULL)
	{
		if ((1 < func_num_args()) || $this->useAsCallable($key)) {
			return parent::contains(...func_get_args());
		}

		if ($key instanceof Model) {
			return parent::contains(function($model) use($key) {
				return $model->is($key);
			});
		}

		return parent::contains(function($model) use($key) {
			return $model->getKey() == $key;
		});
	}

	public function modelKeys()
	{
		return array_map(function($model) {
			return $model->getKey();
		}, $this->items);
	}

	public function merge($items)
	{
		$dictionary = $this->getDictionary();

		foreach ($items as $item) {
			$dictionary[$item->getKey()] = $item;
		}

		return new static(array_values($dictionary));
	}

	public function map( $callback)
	{
		$result = parent::map($callback);
		return $result->contains(function($item) {
			return !$item instanceof Model;
		}) ? $result->toBase() : $result;
	}

	public function diff($items)
	{
		$diff = new static();
		$dictionary = $this->getDictionary($items);

		foreach ($this->items as $item) {
			if (!isset($dictionary[$item->getKey()])) {
				$diff->add($item);
			}
		}

		return $diff;
	}

	public function intersect($items)
	{
		$intersect = new static();
		$dictionary = $this->getDictionary($items);

		foreach ($this->items as $item) {
			if (isset($dictionary[$item->getKey()])) {
				$intersect->add($item);
			}
		}

		return $intersect;
	}

	public function unique($key = NULL, $strict = false)
	{
		if (!is_null($key)) {
			return parent::unique($key, $strict);
		}

		return new static(array_values($this->getDictionary()));
	}

	public function only($keys)
	{
		if (is_null($keys)) {
			return new static($this->items);
		}

		$dictionary = \Illuminate\Support\Arr::only($this->getDictionary(), $keys);
		return new static(array_values($dictionary));
	}

	public function except($keys)
	{
		$dictionary = \Illuminate\Support\Arr::except($this->getDictionary(), $keys);
		return new static(array_values($dictionary));
	}

	public function makeHidden($attributes)
	{
		return $this->each(function($model) use($attributes) {
			$model->addHidden($attributes);
		});
	}

	public function makeVisible($attributes)
	{
		return $this->each(function($model) use($attributes) {
			$model->makeVisible($attributes);
		});
	}

	public function getDictionary($items = NULL)
	{
		$items = (is_null($items) ? $this->items : $items);
		$dictionary = array();

		foreach ($items as $value) {
			$dictionary[$value->getKey()] = $value;
		}

		return $dictionary;
	}

	public function pluck($value, $key = NULL)
	{
		return $this->toBase()->pluck($value, $key);
	}

	public function keys()
	{
		return $this->toBase()->keys();
	}

	public function zip($items)
	{
		return call_user_func_array(array($this->toBase(), 'zip'), func_get_args());
	}

	public function collapse()
	{
		return $this->toBase()->collapse();
	}

	public function flatten($depth = INF)
	{
		return $this->toBase()->flatten($depth);
	}

	public function flip()
	{
		return $this->toBase()->flip();
	}

	public function getQueueableClass()
	{
		if ($this->count() === 0) {
			return NULL;
		}

		$class = get_class($this->first());
		$this->each(function($model) use($class) {
			if (get_class($model) !== $class) {
				throw new \LogicException('Queueing collections with multiple model types is not supported.');
			}
		});
		return $class;
	}

	public function getQueueableIds()
	{
		return $this->modelKeys();
	}
}

?>
