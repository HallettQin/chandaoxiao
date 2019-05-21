<?php
//cgxlm
namespace Illuminate\Database\Concerns;

trait BuildsQueries
{
	public function chunk($count,  $callback)
	{
		$this->enforceOrderBy();
		$page = 1;

		do {
			$results = $this->forPage($page, $count)->get();
			$countResults = $results->count();

			if ($countResults == 0) {
				break;
			}

			if ($callback($results) === false) {
				return false;
			}

			$page++;
		} while ($countResults == $count);

		return true;
	}

	public function each( $callback, $count = 1000)
	{
		return $this->chunk($count, function($results) use($callback) {
			foreach ($results as $key => $value) {
				if ($callback($value, $key) === false) {
					return false;
				}
			}
		});
	}

	public function first($columns = array('*'))
	{
		return $this->take(1)->get($columns)->first();
	}

	public function when($value, $callback, $default = NULL)
	{
		if ($value) {
			return $callback($this, $value) ?: $this;
		}
		else if ($default) {
			return $default($this, $value) ?: $this;
		}

		return $this;
	}

	protected function paginator($items, $total, $perPage, $currentPage, $options)
	{
		return \Illuminate\Container\Container::getInstance()->makeWith('Illuminate\\Pagination\\LengthAwarePaginator', compact('items', 'total', 'perPage', 'currentPage', 'options'));
	}

	protected function simplePaginator($items, $perPage, $currentPage, $options)
	{
		return \Illuminate\Container\Container::getInstance()->makeWith('Illuminate\\Pagination\\Paginator', compact('items', 'perPage', 'currentPage', 'options'));
	}
}


?>
