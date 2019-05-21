<?php
//cgxlm
namespace Illuminate\Database\Eloquent\Concerns;

trait QueriesRelationships
{
	public function has($relation, $operator = '>=', $count = 1, $boolean = 'and', \Closure $callback = NULL)
	{
		if (strpos($relation, '.') !== false) {
			return $this->hasNested($relation, $operator, $count, $boolean, $callback);
		}

		$relation = $this->getRelationWithoutConstraints($relation);
		$method = ($this->canUseExistsForExistenceCheck($operator, $count) ? 'getRelationExistenceQuery' : 'getRelationExistenceCountQuery');
		$hasQuery = $relation->$method($relation->getRelated()->newQuery(), $this);

		if ($callback) {
			$hasQuery->callScope($callback);
		}

		return $this->addHasWhere($hasQuery, $relation, $operator, $count, $boolean);
	}

	protected function hasNested($relations, $operator = '>=', $count = 1, $boolean = 'and', $callback = NULL)
	{
		$relations = explode('.', $relations);
		$closure = function($q) use(&$closure, &$relations, $operator, $count, $boolean, $callback) {
			1 < count($relations) ? $q->whereHas(array_shift($relations), $closure) : $q->has(array_shift($relations), $operator, $count, 'and', $callback);
		};
		return $this->has(array_shift($relations), '>=', 1, $boolean, $closure);
	}

	public function orHas($relation, $operator = '>=', $count = 1)
	{
		return $this->has($relation, $operator, $count, 'or');
	}

	public function doesntHave($relation, $boolean = 'and', \Closure $callback = NULL)
	{
		return $this->has($relation, '<', 1, $boolean, $callback);
	}

	public function whereHas($relation, \Closure $callback = NULL, $operator = '>=', $count = 1)
	{
		return $this->has($relation, $operator, $count, 'and', $callback);
	}

	public function orWhereHas($relation, \Closure $callback = NULL, $operator = '>=', $count = 1)
	{
		return $this->has($relation, $operator, $count, 'or', $callback);
	}

	public function whereDoesntHave($relation, \Closure $callback = NULL)
	{
		return $this->doesntHave($relation, 'and', $callback);
	}

	public function withCount($relations)
	{
		if (empty($relations)) {
			return $this;
		}

		if (is_null($this->query->columns)) {
			$this->query->select(array($this->query->from . '.*'));
		}

		$relations = (is_array($relations) ? $relations : func_get_args());

		foreach ($this->parseWithRelations($relations) as $name => $constraints) {
			$segments = explode(' ', $name);
			unset($alias);
			if ((count($segments) == 3) && (\Illuminate\Support\Str::lower($segments[1]) == 'as')) {
				list($name, $alias) = array($segments[0], $segments[2]);
			}

			$relation = $this->getRelationWithoutConstraints($name);
			$query = $relation->getRelationExistenceCountQuery($relation->getRelated()->newQuery(), $this);
			$query->callScope($constraints);
			$query->mergeConstraintsFrom($relation->getQuery());
			$column = snake_case(isset($alias) ? $alias : $name) . '_count';
			$this->selectSub($query->toBase(), $column);
		}

		return $this;
	}

	protected function addHasWhere(\Illuminate\Database\Eloquent\Builder $hasQuery, \Illuminate\Database\Eloquent\Relations\Relation $relation, $operator, $count, $boolean)
	{
		$hasQuery->mergeConstraintsFrom($relation->getQuery());
		return $this->canUseExistsForExistenceCheck($operator, $count) ? $this->addWhereExistsQuery($hasQuery->toBase(), $boolean, $not = ($operator === '<') && ($count === 1)) : $this->addWhereCountQuery($hasQuery->toBase(), $operator, $count, $boolean);
	}

	public function mergeConstraintsFrom(\Illuminate\Database\Eloquent\Builder $from)
	{
/* [31m * TODO SEPARATE[0m */
		$whereBindings = \Illuminate\Support\Arr::get($from->getQuery()->getRawBindings(), 'where', array());
		return $this->withoutGlobalScopes($from->removedScopes())->mergeWheres($from->getQuery()->wheres, $whereBindings);
	}

	protected function addWhereCountQuery(\Illuminate\Database\Query\Builder $query, $operator = '>=', $count = 1, $boolean = 'and')
	{
		$this->query->addBinding($query->getBindings(), 'where');
		return $this->where(new \Illuminate\Database\Query\Expression('(' . $query->toSql() . ')'), $operator, is_numeric($count) ? new \Illuminate\Database\Query\Expression($count) : $count, $boolean);
	}

	protected function getRelationWithoutConstraints($relation)
	{
		return \Illuminate\Database\Eloquent\Relations\Relation::noConstraints(function() use($relation) {
			return $this->getModel()->$relation();
		});
	}

	protected function canUseExistsForExistenceCheck($operator, $count)
	{
		return (($operator === '>=') || ($operator === '<')) && ($count === 1);
	}
}


?>
