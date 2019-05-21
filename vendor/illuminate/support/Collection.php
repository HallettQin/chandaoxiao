<?php
//cgxlm
namespace Illuminate\Support;

class Collection implements \ArrayAccess, \Illuminate\Contracts\Support\Arrayable, \Countable, \IteratorAggregate, \Illuminate\Contracts\Support\Jsonable, \JsonSerializable
{
	use Traits\Macroable;

	/**
     * The items contained in the collection.
     *
     * @var array
     */
	protected $items = array();
	/**
     * The methods that can be proxied.
     *
     * @var array
     */
	static protected $proxies = array('contains', 'each', 'every', 'filter', 'first', 'flatMap', 'map', 'partition', 'reject', 'sortBy', 'sortByDesc', 'sum');

	public function __construct($items = array())
	{
		$this->items = $this->getArrayableItems($items);
	}

	static public function make($items = array())
	{
		return new static($items);
	}

	static public function times($amount,  $callback = NULL)
	{
		if ($amount < 1) {
			return new static();
		}

		if (is_null($callback)) {
			return new static(range(1, $amount));
		}

		return (new static(range(1, $amount)))->map($callback);
	}

	public function all()
	{
		return $this->items;
	}

	public function avg($callback = NULL)
	{
		if ($count = $this->count()) {
			return $this->sum($callback) / $count;
		}
	}

	public function average($callback = NULL)
	{
		return $this->avg($callback);
	}

	public function median($key = NULL)
	{
		$count = $this->count();

		if ($count == 0) {
			return NULL;
		}

		$values = with(isset($key) ? $this->pluck($key) : $this)->sort()->values();
		$middle = (int) ($count / 2);

		if ($count % 2) {
			return $values->get($middle);
		}

		return (new static(array($values->get($middle - 1), $values->get($middle))))->average();
	}

	public function mode($key = NULL)
	{
		$count = $this->count();

		if ($count == 0) {
			return NULL;
		}

		$collection = (isset($key) ? $this->pluck($key) : $this);
		$counts = new self();
		$collection->each(function($value) use($counts) {
			$counts[$value] = isset($counts[$value]) ? $counts[$value] + 1 : 1;
		});
		$sorted = $counts->sort();
		$highestValue = $sorted->last();
		return $sorted->filter(function($value) use($highestValue) {
			return $value == $highestValue;
		})->sort()->keys()->all();
	}

	public function collapse()
	{
		return new static(Arr::collapse($this->items));
	}

	public function contains($key, $operator = NULL, $value = NULL)
	{
		if (func_num_args() == 1) {
			if ($this->useAsCallable($key)) {
				return !is_null($this->first($key));
			}

			return in_array($key, $this->items);
		}

		if (func_num_args() == 2) {
			$value = $operator;
			$operator = '=';
		}

		return $this->contains($this->operatorForWhere($key, $operator, $value));
	}

	public function containsStrict($key, $value = NULL)
	{
		if (func_num_args() == 2) {
			return $this->contains(function($item) use($key, $value) {
				return data_get($item, $key) === $value;
			});
		}

		if ($this->useAsCallable($key)) {
			return !is_null($this->first($key));
		}

		return in_array($key, $this->items, true);
	}

	public function crossJoin(...$lists)
	{
		return new static(Arr::crossJoin($this->items, ...array_map(array($this, 'getArrayableItems'), $lists)));
	}

	public function diff($items)
	{
		return new static(array_diff($this->items, $this->getArrayableItems($items)));
	}

	public function diffAssoc($items)
	{
		return new static(array_diff_assoc($this->items, $this->getArrayableItems($items)));
	}

	public function diffKeys($items)
	{
		return new static(array_diff_key($this->items, $this->getArrayableItems($items)));
	}

	public function each( $callback)
	{
		foreach ($this->items as $key => $item) {
			if ($callback($item, $key) === false) {
				break;
			}
		}

		return $this;
	}

	public function eachSpread( $callback)
	{
		return $this->each(function($chunk) use($callback) {
			return $callback(...$chunk);
		});
	}

	public function every($key, $operator = NULL, $value = NULL)
	{
		if (func_num_args() == 1) {
			$callback = $this->valueRetriever($key);

			foreach ($this->items as $k => $v) {
				if (!$callback($v, $k)) {
					return false;
				}
			}

			return true;
		}

		if (func_num_args() == 2) {
			$value = $operator;
			$operator = '=';
		}

		return $this->every($this->operatorForWhere($key, $operator, $value));
	}

	public function except($keys)
	{
		$keys = (is_array($keys) ? $keys : func_get_args());
		return new static(Arr::except($this->items, $keys));
	}

	public function filter( $callback = NULL)
	{
		if ($callback) {
			return new static(Arr::where($this->items, $callback));
		}

		return new static(array_filter($this->items));
	}

	public function when($value,  $callback,  $default = NULL)
	{
		if ($value) {
			return $callback($this);
		}
		else if ($default) {
			return $default($this);
		}

		return $this;
	}

	public function where($key, $operator, $value = NULL)
	{
		if (func_num_args() == 2) {
			$value = $operator;
			$operator = '=';
		}

		return $this->filter($this->operatorForWhere($key, $operator, $value));
	}

	protected function operatorForWhere($key, $operator, $value)
	{
		return function($item) use($key, $operator, $value) {
			$retrieved = data_get($item, $key);

			switch ($operator) {
			default:
			case '=':
			case '==':
				return $retrieved == $value;
			case '!=':
			case '<>':
				return $retrieved != $value;
			case '<':
				return $retrieved < $value;
			case '>':
				return $value < $retrieved;
			case '<=':
				return $retrieved <= $value;
			case '>=':
				return $value <= $retrieved;
			case '===':
				return $retrieved === $value;
			case '!==':
				return $retrieved !== $value;
			}
		};
	}

	public function whereStrict($key, $value)
	{
		return $this->where($key, '===', $value);
	}

	public function whereIn($key, $values, $strict = false)
	{
		$values = $this->getArrayableItems($values);
		return $this->filter(function($item) use($key, $values, $strict) {
			return in_array(data_get($item, $key), $values, $strict);
		});
	}

	public function whereInStrict($key, $values)
	{
		return $this->whereIn($key, $values, true);
	}

	public function whereNotIn($key, $values, $strict = false)
	{
		$values = $this->getArrayableItems($values);
		return $this->reject(function($item) use($key, $values, $strict) {
			return in_array(data_get($item, $key), $values, $strict);
		});
	}

	public function whereNotInStrict($key, $values)
	{
		return $this->whereNotIn($key, $values, true);
	}

	public function first( $callback = NULL, $default = NULL)
	{
		return Arr::first($this->items, $callback, $default);
	}

	public function flatten($depth = INF)
	{
		return new static(Arr::flatten($this->items, $depth));
	}

	public function flip()
	{
		return new static(array_flip($this->items));
	}

	public function forget($keys)
	{
		foreach ((array) $keys as $key) {
			$this->offsetUnset($key);
		}

		return $this;
	}

	public function get($key, $default = NULL)
	{
		if ($this->offsetExists($key)) {
			return $this->items[$key];
		}

		return value($default);
	}

	public function groupBy($groupBy, $preserveKeys = false)
	{
		$groupBy = $this->valueRetriever($groupBy);
		$results = array();

		foreach ($this->items as $key => $value) {
			$groupKeys = $groupBy($value, $key);

			if (!is_array($groupKeys)) {
				$groupKeys = array($groupKeys);
			}

			foreach ($groupKeys as $groupKey) {
				$groupKey = (is_bool($groupKey) ? (int) $groupKey : $groupKey);

				if (!array_key_exists($groupKey, $results)) {
					$results[$groupKey] = new static();
				}

				$results[$groupKey]->offsetSet($preserveKeys ? $key : null, $value);
			}
		}

		return new static($results);
	}

	public function keyBy($keyBy)
	{
		$keyBy = $this->valueRetriever($keyBy);
		$results = array();

		foreach ($this->items as $key => $item) {
			$resolvedKey = $keyBy($item, $key);

			if (is_object($resolvedKey)) {
				$resolvedKey = (string) $resolvedKey;
			}

			$results[$resolvedKey] = $item;
		}

		return new static($results);
	}

	public function has($key)
	{
		return $this->offsetExists($key);
	}

	public function implode($value, $glue = NULL)
	{
		$first = $this->first();
		if (is_array($first) || is_object($first)) {
			return implode($glue, $this->pluck($value)->all());
		}

		return implode($value, $this->items);
	}

	public function intersect($items)
	{
		return new static(array_intersect($this->items, $this->getArrayableItems($items)));
	}

	public function isEmpty()
	{
		return empty($this->items);
	}

	public function isNotEmpty()
	{
		return !$this->isEmpty();
	}

	protected function useAsCallable($value)
	{
		return !is_string($value) && is_callable($value);
	}

	public function keys()
	{
		return new static(array_keys($this->items));
	}

	public function last( $callback = NULL, $default = NULL)
	{
		return Arr::last($this->items, $callback, $default);
	}

	public function pluck($value, $key = NULL)
	{
		return new static(Arr::pluck($this->items, $value, $key));
	}

	public function map( $callback)
	{
		$keys = array_keys($this->items);
		$items = array_map($callback, $this->items, $keys);
		return new static(array_combine($keys, $items));
	}

	public function mapSpread( $callback)
	{
		return $this->map(function($chunk) use($callback) {
			return $callback(...$chunk);
		});
	}

	public function mapToGroups( $callback)
	{
		$groups = $this->map($callback)->reduce(function($groups, $pair) {
			$groups[key($pair)][] = reset($pair);
			return $groups;
		}, array());
		return (new static($groups))->map(array($this, 'make'));
	}

	public function mapWithKeys( $callback)
	{
		$result = array();

		foreach ($this->items as $key => $value) {
			$assoc = $callback($value, $key);

			foreach ($assoc as $mapKey => $mapValue) {
				$result[$mapKey] = $mapValue;
			}
		}

		return new static($result);
	}

	public function flatMap( $callback)
	{
		return $this->map($callback)->collapse();
	}

	public function max($callback = NULL)
	{
		$callback = $this->valueRetriever($callback);
		return $this->filter(function($value) {
			return !is_null($value);
		})->reduce(function($result, $item) use($callback) {
			$value = $callback($item);
			return is_null($result) || ($result < $value) ? $value : $result;
		});
	}

	public function merge($items)
	{
		return new static(array_merge($this->items, $this->getArrayableItems($items)));
	}

	public function combine($values)
	{
		return new static(array_combine($this->all(), $this->getArrayableItems($values)));
	}

	public function union($items)
	{
		return new static($this->items + $this->getArrayableItems($items));
	}

	public function min($callback = NULL)
	{
		$callback = $this->valueRetriever($callback);
		return $this->filter(function($value) {
			return !is_null($value);
		})->reduce(function($result, $item) use($callback) {
			$value = $callback($item);
			return is_null($result) || ($value < $result) ? $value : $result;
		});
	}

	public function nth($step, $offset = 0)
	{
		$new = array();
		$position = 0;

		foreach ($this->items as $item) {
			if (($position % $step) === $offset) {
				$new[] = $item;
			}

			$position++;
		}

		return new static($new);
	}

	public function only($keys)
	{
		if (is_null($keys)) {
			return new static($this->items);
		}

		$keys = (is_array($keys) ? $keys : func_get_args());
		return new static(Arr::only($this->items, $keys));
	}

	public function forPage($page, $perPage)
	{
		return $this->slice(($page - 1) * $perPage, $perPage);
	}

	public function partition($callback)
	{
		$partitions = array(new static(), new static());
		$callback = $this->valueRetriever($callback);

		foreach ($this->items as $key => $item) {
			$partitions[(int) !$callback($item)][$key] = $item;
		}

		return new static($partitions);
	}

	public function pipe( $callback)
	{
		return $callback($this);
	}

	public function pop()
	{
		return array_pop($this->items);
	}

	public function prepend($value, $key = NULL)
	{
		$this->items = Arr::prepend($this->items, $value, $key);
		return $this;
	}

	public function push($value)
	{
		$this->offsetSet(null, $value);
		return $this;
	}

	public function concat($source)
	{
		$result = new static($this);

		foreach ($source as $item) {
			$result->push($item);
		}

		return $result;
	}

	public function pull($key, $default = NULL)
	{
		return Arr::pull($this->items, $key, $default);
	}

	public function put($key, $value)
	{
		$this->offsetSet($key, $value);
		return $this;
	}

	public function random($amount = 1)
	{
		if (($count = $this->count()) < $amount) {
			throw new \InvalidArgumentException('You requested ' . $amount . ' items, but there are only ' . $count . ' items in the collection.');
		}

		$keys = array_rand($this->items, $amount);

		if (count(func_get_args()) == 0) {
			return $this->items[$keys];
		}

		$keys = array_wrap($keys);
		return new static(array_intersect_key($this->items, array_flip($keys)));
	}

	public function reduce( $callback, $initial = NULL)
	{
		return array_reduce($this->items, $callback, $initial);
	}

	public function reject($callback)
	{
		if ($this->useAsCallable($callback)) {
			return $this->filter(function($value, $key) use($callback) {
				return !$callback($value, $key);
			});
		}

		return $this->filter(function($item) use($callback) {
			return $item != $callback;
		});
	}

	public function reverse()
	{
		return new static(array_reverse($this->items, true));
	}

	public function search($value, $strict = false)
	{
		if (!$this->useAsCallable($value)) {
			return array_search($value, $this->items, $strict);
		}

		foreach ($this->items as $key => $item) {
			if (call_user_func($value, $item, $key)) {
				return $key;
			}
		}

		return false;
	}

	public function shift()
	{
		return array_shift($this->items);
	}

	public function shuffle($seed = NULL)
	{
		$items = $this->items;

		if (is_null($seed)) {
			shuffle($items);
		}
		else {
			srand($seed);
			usort($items, function() {
				return rand(-1, 1);
			});
		}

		return new static($items);
	}

	public function slice($offset, $length = NULL)
	{
		return new static(array_slice($this->items, $offset, $length, true));
	}

	public function split($numberOfGroups)
	{
		if ($this->isEmpty()) {
			return new static();
		}

		$groupSize = ceil($this->count() / $numberOfGroups);
		return $this->chunk($groupSize);
	}

	public function chunk($size)
	{
		if ($size <= 0) {
			return new static();
		}

		$chunks = array();

		foreach (array_chunk($this->items, $size, true) as $chunk) {
			$chunks[] = new static($chunk);
		}

		return new static($chunks);
	}

	public function sort( $callback = NULL)
	{
		$items = $this->items;
		$callback ? uasort($items, $callback) : asort($items);
		return new static($items);
	}

	public function sortBy($callback, $options = SORT_REGULAR, $descending = false)
	{
		$results = array();
		$callback = $this->valueRetriever($callback);

		foreach ($this->items as $key => $value) {
			$results[$key] = $callback($value, $key);
		}

		$descending ? arsort($results, $options) : asort($results, $options);

		foreach (array_keys($results) as $key) {
			$results[$key] = $this->items[$key];
		}

		return new static($results);
	}

	public function sortByDesc($callback, $options = SORT_REGULAR)
	{
		return $this->sortBy($callback, $options, true);
	}

	public function splice($offset, $length = NULL, $replacement = array())
	{
		if (func_num_args() == 1) {
			return new static(array_splice($this->items, $offset));
		}

		return new static(array_splice($this->items, $offset, $length, $replacement));
	}

	public function sum($callback = NULL)
	{
		if (is_null($callback)) {
			return array_sum($this->items);
		}

		$callback = $this->valueRetriever($callback);
		return $this->reduce(function($result, $item) use($callback) {
			return $result + $callback($item);
		}, 0);
	}

	public function take($limit)
	{
		if ($limit < 0) {
			return $this->slice($limit, abs($limit));
		}

		return $this->slice(0, $limit);
	}

	public function tap( $callback)
	{
		$callback(new static($this->items));
		return $this;
	}

	public function transform( $callback)
	{
		$this->items = $this->map($callback)->all();
		return $this;
	}

	public function unique($key = NULL, $strict = false)
	{
		if (is_null($key)) {
			return new static(array_unique($this->items, SORT_REGULAR));
		}

		$callback = $this->valueRetriever($key);
		$exists = array();
		return $this->reject(function($item, $key) use($callback, $strict, &$exists) {
			if (in_array($id = $callback($item, $key), $exists, $strict)) {
				return true;
			}

			$exists[] = $id;
		});
	}

	public function uniqueStrict($key = NULL)
	{
		return $this->unique($key, true);
	}

	public function values()
	{
		return new static(array_values($this->items));
	}

	protected function valueRetriever($value)
	{
		if ($this->useAsCallable($value)) {
			return $value;
		}

		return function($item) use($value) {
			return data_get($item, $value);
		};
	}

	public function zip($items)
	{
		$arrayableItems = array_map(function($items) {
			return $this->getArrayableItems($items);
		}, func_get_args());
		$params = array_merge(array(function() {
			return new static(func_get_args());
		}, $this->items), $arrayableItems);
		return new static(call_user_func_array('array_map', $params));
	}

	public function toArray()
	{
		return array_map(function($value) {
			return $value instanceof \Illuminate\Contracts\Support\Arrayable ? $value->toArray() : $value;
		}, $this->items);
	}

	public function jsonSerialize()
	{
		return array_map(function($value) {
			if ($value instanceof \JsonSerializable) {
				return $value->jsonSerialize();
			}
			else if ($value instanceof \Illuminate\Contracts\Support\Jsonable) {
				return json_decode($value->toJson(), true);
			}
			else if ($value instanceof \Illuminate\Contracts\Support\Arrayable) {
				return $value->toArray();
			}
			else {
				return $value;
			}
		}, $this->items);
	}

	public function toJson($options = 0)
	{
		return json_encode($this->jsonSerialize(), $options);
	}

	public function getIterator()
	{
		return new \ArrayIterator($this->items);
	}

	public function getCachingIterator($flags = \CachingIterator::CALL_TOSTRING)
	{
		return new \CachingIterator($this->getIterator(), $flags);
	}

	public function count()
	{
		return count($this->items);
	}

	public function toBase()
	{
		return new self($this);
	}

	public function offsetExists($key)
	{
		return array_key_exists($key, $this->items);
	}

	public function offsetGet($key)
	{
		return $this->items[$key];
	}

	public function offsetSet($key, $value)
	{
		if (is_null($key)) {
			$this->items[] = $value;
		}
		else {
			$this->items[$key] = $value;
		}
	}

	public function offsetUnset($key)
	{
		unset($this->items[$key]);
	}

	public function __toString()
	{
		return $this->toJson();
	}

	protected function getArrayableItems($items)
	{
		if (is_array($items)) {
			return $items;
		}
		else if ($items instanceof self) {
			return $items->all();
		}
		else if ($items instanceof \Illuminate\Contracts\Support\Arrayable) {
			return $items->toArray();
		}
		else if ($items instanceof \Illuminate\Contracts\Support\Jsonable) {
			return json_decode($items->toJson(), true);
		}
		else if ($items instanceof \JsonSerializable) {
			return $items->jsonSerialize();
		}
		else if ($items instanceof \Traversable) {
			return iterator_to_array($items);
		}

		return (array) $items;
	}

	static public function proxy($method)
	{
		static::$proxies[] = $method;
	}

	public function __get($key)
	{
		if (!in_array($key, static::$proxies)) {
			throw new \Exception('Property [' . $key . '] does not exist on this collection instance.');
		}

		return new HigherOrderCollectionProxy($this, $key);
	}
}

?>
