<?php
//cgxlm
namespace Illuminate\Database\Query;

class Builder
{
	use \Illuminate\Database\Concerns\BuildsQueries, \Illuminate\Support\Traits\Macroable;

	/**
     * The database connection instance.
     *
     * @var \Illuminate\Database\Connection
     */
	public $connection;
	/**
     * The database query grammar instance.
     *
     * @var \Illuminate\Database\Query\Grammars\Grammar
     */
	public $grammar;
	/**
     * The database query post processor instance.
     *
     * @var \Illuminate\Database\Query\Processors\Processor
     */
	public $processor;
	/**
     * The current query value bindings.
     *
     * @var array
     */
	public $bindings = array(
		'select' => array(),
		'join'   => array(),
		'where'  => array(),
		'having' => array(),
		'order'  => array(),
		'union'  => array()
		);
	/**
     * An aggregate function and column to be run.
     *
     * @var array
     */
	public $aggregate;
	/**
     * The columns that should be returned.
     *
     * @var array
     */
	public $columns;
	/**
     * Indicates if the query returns distinct results.
     *
     * @var bool
     */
	public $distinct = false;
	/**
     * The table which the query is targeting.
     *
     * @var string
     */
	public $from;
	/**
     * The table joins for the query.
     *
     * @var array
     */
	public $joins;
	/**
     * The where constraints for the query.
     *
     * @var array
     */
	public $wheres;
	/**
     * The groupings for the query.
     *
     * @var array
     */
	public $groups;
	/**
     * The having constraints for the query.
     *
     * @var array
     */
	public $havings;
	/**
     * The orderings for the query.
     *
     * @var array
     */
	public $orders;
	/**
     * The maximum number of records to return.
     *
     * @var int
     */
	public $limit;
	/**
     * The number of records to skip.
     *
     * @var int
     */
	public $offset;
	/**
     * The query union statements.
     *
     * @var array
     */
	public $unions;
	/**
     * The maximum number of union records to return.
     *
     * @var int
     */
	public $unionLimit;
	/**
     * The number of union records to skip.
     *
     * @var int
     */
	public $unionOffset;
	/**
     * The orderings for the union query.
     *
     * @var array
     */
	public $unionOrders;
	/**
     * Indicates whether row locking is being used.
     *
     * @var string|bool
     */
	public $lock;
	/**
     * All of the available clause operators.
     *
     * @var array
     */
	public $operators = array('=', '<', '>', '<=', '>=', '<>', '!=', '<=>', 'like', 'like binary', 'not like', 'between', 'ilike', '&', '|', '^', '<<', '>>', 'rlike', 'regexp', 'not regexp', '~', '~*', '!~', '!~*', 'similar to', 'not similar to', 'not ilike', '~~*', '!~~*');
	/**
     * Whether use write pdo for select.
     *
     * @var bool
     */
	public $useWritePdo = false;

	public function __construct(\Illuminate\Database\ConnectionInterface $connection, Grammars\Grammar $grammar = NULL, Processors\Processor $processor = NULL)
	{
		$this->connection = $connection;
		$this->grammar = $grammar ?: $connection->getQueryGrammar();
		$this->processor = $processor ?: $connection->getPostProcessor();
	}

	public function select($columns = array('*'))
	{
		$this->columns = is_array($columns) ? $columns : func_get_args();
		return $this;
	}

	public function selectRaw($expression, array $bindings = array())
	{
		$this->addSelect(new Expression($expression));

		if ($bindings) {
			$this->addBinding($bindings, 'select');
		}

		return $this;
	}

	public function selectSub($query, $as)
	{
		if ($query instanceof \Closure) {
			$callback = $query;
			$callback($query = $this->newQuery());
		}

		list($query, $bindings) = $this->parseSubSelect($query);
		return $this->selectRaw('(' . $query . ') as ' . $this->grammar->wrap($as), $bindings);
	}

	protected function parseSubSelect($query)
	{
		if ($query instanceof self) {
			$query->columns = array($query->columns[0]);
			return array($query->toSql(), $query->getBindings());
		}
		else if (is_string($query)) {
			return array(
	$query,
	array()
	);
		}
		else {
			throw new \InvalidArgumentException();
		}
	}

	public function addSelect($column)
	{
		$column = (is_array($column) ? $column : func_get_args());
		$this->columns = array_merge((array) $this->columns, $column);
		return $this;
	}

	public function distinct()
	{
		$this->distinct = true;
		return $this;
	}

	public function from($table)
	{
		$this->from = $table;
		return $this;
	}

	public function join($table, $first, $operator = NULL, $second = NULL, $type = 'inner', $where = false)
	{
		$join = new JoinClause($this, $type, $table);

		if ($first instanceof \Closure) {
			call_user_func($first, $join);
			$this->joins[] = $join;
			$this->addBinding($join->getBindings(), 'join');
		}
		else {
			$method = ($where ? 'where' : 'on');
			$this->joins[] = $join->$method($first, $operator, $second);
			$this->addBinding($join->getBindings(), 'join');
		}

		return $this;
	}

	public function joinWhere($table, $first, $operator, $second, $type = 'inner')
	{
		return $this->join($table, $first, $operator, $second, $type, true);
	}

	public function leftJoin($table, $first, $operator = NULL, $second = NULL)
	{
		return $this->join($table, $first, $operator, $second, 'left');
	}

	public function leftJoinWhere($table, $first, $operator, $second)
	{
		return $this->joinWhere($table, $first, $operator, $second, 'left');
	}

	public function rightJoin($table, $first, $operator = NULL, $second = NULL)
	{
		return $this->join($table, $first, $operator, $second, 'right');
	}

	public function rightJoinWhere($table, $first, $operator, $second)
	{
		return $this->joinWhere($table, $first, $operator, $second, 'right');
	}

	public function crossJoin($table, $first = NULL, $operator = NULL, $second = NULL)
	{
		if ($first) {
			return $this->join($table, $first, $operator, $second, 'cross');
		}

		$this->joins[] = new JoinClause($this, 'cross', $table);
		return $this;
	}

	public function tap($callback)
	{
		return $this->when(true, $callback);
	}

	public function mergeWheres($wheres, $bindings)
	{
		$this->wheres = array_merge((array) $this->wheres, (array) $wheres);
		$this->bindings['where'] = array_values(array_merge($this->bindings['where'], (array) $bindings));
	}

	public function where($column, $operator = NULL, $value = NULL, $boolean = 'and')
	{
		if (is_array($column)) {
			return $this->addArrayOfWheres($column, $boolean);
		}

		list($value, $operator) = $this->prepareValueAndOperator($value, $operator, func_num_args() == 2);

		if ($column instanceof \Closure) {
			return $this->whereNested($column, $boolean);
		}

		if ($this->invalidOperator($operator)) {
			list($value, $operator) = array($operator, '=');
		}

		if ($value instanceof \Closure) {
			return $this->whereSub($column, $operator, $value, $boolean);
		}

		if (is_null($value)) {
			return $this->whereNull($column, $boolean, $operator != '=');
		}

		if (\Illuminate\Support\Str::contains($column, '->') && is_bool($value)) {
			$value = new Expression($value ? 'true' : 'false');
		}

		$type = 'Basic';
		$this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');

		if (!$value instanceof Expression) {
			$this->addBinding($value, 'where');
		}

		return $this;
	}

	protected function addArrayOfWheres($column, $boolean, $method = 'where')
	{
		return $this->whereNested(function($query) use($column, $method) {
			foreach ($column as $key => $value) {
				if (is_numeric($key) && is_array($value)) {
					$query->$method(...array_values($value));
				}
				else {
					$query->$method($key, '=', $value);
				}
			}
		}, $boolean);
	}

	protected function prepareValueAndOperator($value, $operator, $useDefault = false)
	{
		if ($useDefault) {
			return array($operator, '=');
		}
		else if ($this->invalidOperatorAndValue($operator, $value)) {
			throw new \InvalidArgumentException('Illegal operator and value combination.');
		}

		return array($value, $operator);
	}

	protected function invalidOperatorAndValue($operator, $value)
	{
		return is_null($value) && in_array($operator, $this->operators) && !in_array($operator, array('=', '<>', '!='));
	}

	protected function invalidOperator($operator)
	{
		return !in_array(strtolower($operator), $this->operators, true) && !in_array(strtolower($operator), $this->grammar->getOperators(), true);
	}

	public function orWhere($column, $operator = NULL, $value = NULL)
	{
		return $this->where($column, $operator, $value, 'or');
	}

	public function whereColumn($first, $operator = NULL, $second = NULL, $boolean = 'and')
	{
		if (is_array($first)) {
			return $this->addArrayOfWheres($first, $boolean, 'whereColumn');
		}

		if ($this->invalidOperator($operator)) {
			list($second, $operator) = array($operator, '=');
		}

		$type = 'Column';
		$this->wheres[] = compact('type', 'first', 'operator', 'second', 'boolean');
		return $this;
	}

	public function orWhereColumn($first, $operator = NULL, $second = NULL)
	{
		return $this->whereColumn($first, $operator, $second, 'or');
	}

	public function whereRaw($sql, $bindings = array(), $boolean = 'and')
	{
		$this->wheres[] = array('type' => 'raw', 'sql' => $sql, 'boolean' => $boolean);
		$this->addBinding((array) $bindings, 'where');
		return $this;
	}

	public function orWhereRaw($sql, array $bindings = array())
	{
		return $this->whereRaw($sql, $bindings, 'or');
	}

	public function whereIn($column, $values, $boolean = 'and', $not = false)
	{
		$type = ($not ? 'NotIn' : 'In');

		if ($values instanceof static) {
			return $this->whereInExistingQuery($column, $values, $boolean, $not);
		}

		if ($values instanceof \Closure) {
			return $this->whereInSub($column, $values, $boolean, $not);
		}

		if ($values instanceof \Illuminate\Contracts\Support\Arrayable) {
			$values = $values->toArray();
		}

		$this->wheres[] = compact('type', 'column', 'values', 'boolean');

		foreach ($values as $value) {
			if (!$value instanceof Expression) {
				$this->addBinding($value, 'where');
			}
		}

		return $this;
	}

	public function orWhereIn($column, $values)
	{
		return $this->whereIn($column, $values, 'or');
	}

	public function whereNotIn($column, $values, $boolean = 'and')
	{
		return $this->whereIn($column, $values, $boolean, true);
	}

	public function orWhereNotIn($column, $values)
	{
		return $this->whereNotIn($column, $values, 'or');
	}

	protected function whereInSub($column, \Closure $callback, $boolean, $not)
	{
		$type = ($not ? 'NotInSub' : 'InSub');
		call_user_func($callback, $query = $this->newQuery());
		$this->wheres[] = compact('type', 'column', 'query', 'boolean');
		$this->addBinding($query->getBindings(), 'where');
		return $this;
	}

	protected function whereInExistingQuery($column, $query, $boolean, $not)
	{
		$type = ($not ? 'NotInSub' : 'InSub');
		$this->wheres[] = compact('type', 'column', 'query', 'boolean');
		$this->addBinding($query->getBindings(), 'where');
		return $this;
	}

	public function whereNull($column, $boolean = 'and', $not = false)
	{
		$type = ($not ? 'NotNull' : 'Null');
		$this->wheres[] = compact('type', 'column', 'boolean');
		return $this;
	}

	public function orWhereNull($column)
	{
		return $this->whereNull($column, 'or');
	}

	public function whereNotNull($column, $boolean = 'and')
	{
		return $this->whereNull($column, $boolean, true);
	}

	public function whereBetween($column, array $values, $boolean = 'and', $not = false)
	{
		$type = 'between';
		$this->wheres[] = compact('column', 'type', 'boolean', 'not');
		$this->addBinding($values, 'where');
		return $this;
	}

	public function orWhereBetween($column, array $values)
	{
		return $this->whereBetween($column, $values, 'or');
	}

	public function whereNotBetween($column, array $values, $boolean = 'and')
	{
		return $this->whereBetween($column, $values, $boolean, true);
	}

	public function orWhereNotBetween($column, array $values)
	{
		return $this->whereNotBetween($column, $values, 'or');
	}

	public function orWhereNotNull($column)
	{
		return $this->whereNotNull($column, 'or');
	}

	public function whereDate($column, $operator, $value = NULL, $boolean = 'and')
	{
		list($value, $operator) = $this->prepareValueAndOperator($value, $operator, func_num_args() == 2);
		return $this->addDateBasedWhere('Date', $column, $operator, $value, $boolean);
	}

	public function orWhereDate($column, $operator, $value)
	{
		return $this->whereDate($column, $operator, $value, 'or');
	}

	public function whereTime($column, $operator, $value, $boolean = 'and')
	{
		return $this->addDateBasedWhere('Time', $column, $operator, $value, $boolean);
	}

	public function orWhereTime($column, $operator, $value)
	{
		return $this->whereTime($column, $operator, $value, 'or');
	}

	public function whereDay($column, $operator, $value = NULL, $boolean = 'and')
	{
		list($value, $operator) = $this->prepareValueAndOperator($value, $operator, func_num_args() == 2);
		return $this->addDateBasedWhere('Day', $column, $operator, $value, $boolean);
	}

	public function whereMonth($column, $operator, $value = NULL, $boolean = 'and')
	{
		list($value, $operator) = $this->prepareValueAndOperator($value, $operator, func_num_args() == 2);
		return $this->addDateBasedWhere('Month', $column, $operator, $value, $boolean);
	}

	public function whereYear($column, $operator, $value = NULL, $boolean = 'and')
	{
		list($value, $operator) = $this->prepareValueAndOperator($value, $operator, func_num_args() == 2);
		return $this->addDateBasedWhere('Year', $column, $operator, $value, $boolean);
	}

	protected function addDateBasedWhere($type, $column, $operator, $value, $boolean = 'and')
	{
		$this->wheres[] = compact('column', 'type', 'boolean', 'operator', 'value');
		$this->addBinding($value, 'where');
		return $this;
	}

	public function whereNested(\Closure $callback, $boolean = 'and')
	{
		call_user_func($callback, $query = $this->forNestedWhere());
		return $this->addNestedWhereQuery($query, $boolean);
	}

	public function forNestedWhere()
	{
		return $this->newQuery()->from($this->from);
	}

	public function addNestedWhereQuery($query, $boolean = 'and')
	{
		if (count($query->wheres)) {
			$type = 'Nested';
			$this->wheres[] = compact('type', 'query', 'boolean');
			$this->addBinding($query->getBindings(), 'where');
		}

		return $this;
	}

	protected function whereSub($column, $operator, \Closure $callback, $boolean)
	{
		$type = 'Sub';
		call_user_func($callback, $query = $this->newQuery());
		$this->wheres[] = compact('type', 'column', 'operator', 'query', 'boolean');
		$this->addBinding($query->getBindings(), 'where');
		return $this;
	}

	public function whereExists(\Closure $callback, $boolean = 'and', $not = false)
	{
		$query = $this->newQuery();
		call_user_func($callback, $query);
		return $this->addWhereExistsQuery($query, $boolean, $not);
	}

	public function orWhereExists(\Closure $callback, $not = false)
	{
		return $this->whereExists($callback, 'or', $not);
	}

	public function whereNotExists(\Closure $callback, $boolean = 'and')
	{
		return $this->whereExists($callback, $boolean, true);
	}

	public function orWhereNotExists(\Closure $callback)
	{
		return $this->orWhereExists($callback, true);
	}

	public function addWhereExistsQuery(Builder $query, $boolean = 'and', $not = false)
	{
		$type = ($not ? 'NotExists' : 'Exists');
		$this->wheres[] = compact('type', 'operator', 'query', 'boolean');
		$this->addBinding($query->getBindings(), 'where');
		return $this;
	}

	public function dynamicWhere($method, $parameters)
	{
		$finder = substr($method, 5);
		$segments = preg_split('/(And|Or)(?=[A-Z])/', $finder, -1, PREG_SPLIT_DELIM_CAPTURE);
		$connector = 'and';
		$index = 0;

		foreach ($segments as $segment) {
			if (($segment != 'And') && ($segment != 'Or')) {
				$this->addDynamic($segment, $connector, $parameters, $index);
				$index++;
			}
			else {
				$connector = $segment;
			}
		}

		return $this;
	}

	protected function addDynamic($segment, $connector, $parameters, $index)
	{
		$bool = strtolower($connector);
		$this->where(\Illuminate\Support\Str::snake($segment), '=', $parameters[$index], $bool);
	}

	public function groupBy(...$groups)
	{
		foreach ($groups as $group) {
			$this->groups = array_merge((array) $this->groups, array_wrap($group));
		}

		return $this;
	}

	public function having($column, $operator = NULL, $value = NULL, $boolean = 'and')
	{
		$type = 'Basic';
		list($value, $operator) = $this->prepareValueAndOperator($value, $operator, func_num_args() == 2);

		if ($this->invalidOperator($operator)) {
			list($value, $operator) = array($operator, '=');
		}

		$this->havings[] = compact('type', 'column', 'operator', 'value', 'boolean');

		if (!$value instanceof Expression) {
			$this->addBinding($value, 'having');
		}

		return $this;
	}

	public function orHaving($column, $operator = NULL, $value = NULL)
	{
		return $this->having($column, $operator, $value, 'or');
	}

	public function havingRaw($sql, array $bindings = array(), $boolean = 'and')
	{
		$type = 'Raw';
		$this->havings[] = compact('type', 'sql', 'boolean');
		$this->addBinding($bindings, 'having');
		return $this;
	}

	public function orHavingRaw($sql, array $bindings = array())
	{
		return $this->havingRaw($sql, $bindings, 'or');
	}

	public function orderBy($column, $direction = 'asc')
	{
		$this->{$this->unions ? 'unionOrders' : 'orders'}[] = array('column' => $column, 'direction' => strtolower($direction) == 'asc' ? 'asc' : 'desc');
		return $this;
	}

	public function orderByDesc($column)
	{
		return $this->orderBy($column, 'desc');
	}

	public function latest($column = 'created_at')
	{
		return $this->orderBy($column, 'desc');
	}

	public function oldest($column = 'created_at')
	{
		return $this->orderBy($column, 'asc');
	}

	public function inRandomOrder($seed = '')
	{
		return $this->orderByRaw($this->grammar->compileRandom($seed));
	}

	public function orderByRaw($sql, $bindings = array())
	{
		$type = 'Raw';
		$this->{$this->unions ? 'unionOrders' : 'orders'}[] = compact('type', 'sql');
		$this->addBinding($bindings, 'order');
		return $this;
	}

	public function skip($value)
	{
		return $this->offset($value);
	}

	public function offset($value)
	{
		$property = ($this->unions ? 'unionOffset' : 'offset');
		$this->$property = max(0, $value);
		return $this;
	}

	public function take($value)
	{
		return $this->limit($value);
	}

	public function limit($value)
	{
		$property = ($this->unions ? 'unionLimit' : 'limit');

		if (0 <= $value) {
			$this->$property = $value;
		}

		return $this;
	}

	public function forPage($page, $perPage = 15)
	{
		return $this->skip(($page - 1) * $perPage)->take($perPage);
	}

	public function forPageAfterId($perPage = 15, $lastId = 0, $column = 'id')
	{
		$this->orders = $this->removeExistingOrdersFor($column);
		return $this->where($column, '>', $lastId)->orderBy($column, 'asc')->take($perPage);
	}

	protected function removeExistingOrdersFor($column)
	{
		return \Illuminate\Support\Collection::make($this->orders)->reject(function($order) use($column) {
			return $order['column'] === $column;
		})->values()->all();
	}

	public function union($query, $all = false)
	{
		if ($query instanceof \Closure) {
			call_user_func($query, $query = $this->newQuery());
		}

		$this->unions[] = compact('query', 'all');
		$this->addBinding($query->getBindings(), 'union');
		return $this;
	}

	public function unionAll($query)
	{
		return $this->union($query, true);
	}

	public function lock($value = true)
	{
		$this->lock = $value;

		if (!is_null($this->lock)) {
			$this->useWritePdo();
		}

		return $this;
	}

	public function lockForUpdate()
	{
		return $this->lock(true);
	}

	public function sharedLock()
	{
		return $this->lock(false);
	}

	public function toSql()
	{
		return $this->grammar->compileSelect($this);
	}

	public function find($id, $columns = array('*'))
	{
		return $this->where('id', '=', $id)->first($columns);
	}

	public function value($column)
	{
		$result = (array) $this->first(array($column));
		return 0 < count($result) ? reset($result) : null;
	}

	public function get($columns = array('*'))
	{
		$original = $this->columns;

		if (is_null($original)) {
			$this->columns = $columns;
		}

		$results = $this->processor->processSelect($this, $this->runSelect());
		$this->columns = $original;
		return collect($results);
	}

	protected function runSelect()
	{
		return $this->connection->select($this->toSql(), $this->getBindings(), !$this->useWritePdo);
	}

	public function paginate($perPage = 15, $columns = array('*'), $pageName = 'page', $page = NULL)
	{
		$page = $page ?: \Illuminate\Pagination\Paginator::resolveCurrentPage($pageName);
		$total = $this->getCountForPagination($columns);
		$results = ($total ? $this->forPage($page, $perPage)->get($columns) : collect());
		return $this->paginator($results, $total, $perPage, $page, array('path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(), 'pageName' => $pageName));
	}

	public function simplePaginate($perPage = 15, $columns = array('*'), $pageName = 'page', $page = NULL)
	{
		$page = $page ?: \Illuminate\Pagination\Paginator::resolveCurrentPage($pageName);
		$this->skip(($page - 1) * $perPage)->take($perPage + 1);
		return $this->simplePaginator($this->get($columns), $perPage, $page, array('path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(), 'pageName' => $pageName));
	}

	public function getCountForPagination($columns = array('*'))
	{
		$results = $this->runPaginationCountQuery($columns);

		if (isset($this->groups)) {
			return count($results);
		}
		else if (!isset($results[0])) {
			return 0;
		}
		else if (is_object($results[0])) {
			return (int) $results[0]->aggregate;
		}
		else {
			return (int) array_change_key_case((array) $results[0])['aggregate'];
		}
	}

	protected function runPaginationCountQuery($columns = array('*'))
	{
		return $this->cloneWithout(array('columns', 'orders', 'limit', 'offset'))->cloneWithoutBindings(array('select', 'order'))->setAggregate('count', $this->withoutSelectAliases($columns))->get()->all();
	}

	protected function withoutSelectAliases(array $columns)
	{
		return array_map(function($column) {
			return is_string($column) && (($aliasPosition = strpos(strtolower($column), ' as ')) !== false) ? substr($column, 0, $aliasPosition) : $column;
		}, $columns);
	}

	public function cursor()
	{
		if (is_null($this->columns)) {
			$this->columns = array('*');
		}

		return $this->connection->cursor($this->toSql(), $this->getBindings(), !$this->useWritePdo);
	}

	public function chunkById($count,  $callback, $column = 'id', $alias = NULL)
	{
		$alias = $alias ?: $column;
		$lastId = 0;

		do {
			$clone = clone $this;
			$results = $clone->forPageAfterId($count, $lastId, $column)->get();
			$countResults = $results->count();

			if ($countResults == 0) {
				break;
			}

			if ($callback($results) === false) {
				return false;
			}

			$lastId = $results->last()->$alias;
		} while ($countResults == $count);

		return true;
	}

	protected function enforceOrderBy()
	{
		if (empty($this->orders) && empty($this->unionOrders)) {
			throw new \RuntimeException('You must specify an orderBy clause when using this function.');
		}
	}

	public function pluck($column, $key = NULL)
	{
		$results = $this->get(is_null($key) ? array($column) : array($column, $key));
		return $results->pluck($this->stripTableForPluck($column), $this->stripTableForPluck($key));
	}

	protected function stripTableForPluck($column)
	{
		return is_null($column) ? $column : last(preg_split('~\\.| ~', $column));
	}

	public function implode($column, $glue = '')
	{
		return $this->pluck($column)->implode($glue);
	}

	public function exists()
	{
		$results = $this->connection->select($this->grammar->compileExists($this), $this->getBindings(), !$this->useWritePdo);

		if (isset($results[0])) {
			$results = (array) $results[0];
			return (bool) $results['exists'];
		}

		return false;
	}

	public function count($columns = '*')
	{
		return (int) $this->aggregate('count', array_wrap($columns));
	}

	public function min($column)
	{
		return $this->aggregate('min', array($column));
	}

	public function max($column)
	{
		return $this->aggregate('max', array($column));
	}

	public function sum($column)
	{
		$result = $this->aggregate('sum', array($column));
		return $result ?: 0;
	}

	public function avg($column)
	{
		return $this->aggregate('avg', array($column));
	}

	public function average($column)
	{
		return $this->avg($column);
	}

	public function aggregate($function, $columns = array('*'))
	{
		$results = $this->cloneWithout(array('columns'))->cloneWithoutBindings(array('select'))->setAggregate($function, $columns)->get($columns);

		if (!$results->isEmpty()) {
			return array_change_key_case((array) $results[0])['aggregate'];
		}
	}

	public function numericAggregate($function, $columns = array('*'))
	{
		$result = $this->aggregate($function, $columns);

		if (!$result) {
			return 0;
		}

		if (is_int($result) || is_float($result)) {
			return $result;
		}

		return strpos((string) $result, '.') === false ? (int) $result : (double) $result;
	}

	protected function setAggregate($function, $columns)
	{
		$this->aggregate = compact('function', 'columns');

		if (empty($this->groups)) {
			$this->orders = null;
			$this->bindings['order'] = array();
		}

		return $this;
	}

	public function insert(array $values)
	{
		if (empty($values)) {
			return true;
		}

		if (!is_array(reset($values))) {
			$values = array($values);
		}
		else {
			foreach ($values as $key => $value) {
				ksort($value);
				$values[$key] = $value;
			}
		}

		return $this->connection->insert($this->grammar->compileInsert($this, $values), $this->cleanBindings(\Illuminate\Support\Arr::flatten($values, 1)));
	}

	public function insertGetId(array $values, $sequence = NULL)
	{
		$sql = $this->grammar->compileInsertGetId($this, $values, $sequence);
		$values = $this->cleanBindings($values);
		return $this->processor->processInsertGetId($this, $sql, $values, $sequence);
	}

	public function update(array $values)
	{
		$sql = $this->grammar->compileUpdate($this, $values);
		return $this->connection->update($sql, $this->cleanBindings($this->grammar->prepareBindingsForUpdate($this->bindings, $values)));
	}

	public function updateOrInsert(array $attributes, array $values = array())
	{
		if (!$this->where($attributes)->exists()) {
			return $this->insert(array_merge($attributes, $values));
		}

		return (bool) $this->take(1)->update($values);
	}

	public function increment($column, $amount = 1, array $extra = array())
	{
		if (!is_numeric($amount)) {
			throw new \InvalidArgumentException('Non-numeric value passed to increment method.');
		}

		$wrapped = $this->grammar->wrap($column);
		$columns = array_merge(array($column => $this->raw($wrapped . ' + ' . $amount)), $extra);
		return $this->update($columns);
	}

	public function decrement($column, $amount = 1, array $extra = array())
	{
		if (!is_numeric($amount)) {
			throw new \InvalidArgumentException('Non-numeric value passed to decrement method.');
		}

		$wrapped = $this->grammar->wrap($column);
		$columns = array_merge(array($column => $this->raw($wrapped . ' - ' . $amount)), $extra);
		return $this->update($columns);
	}

	public function delete($id = NULL)
	{
		if (!is_null($id)) {
			$this->where($this->from . '.id', '=', $id);
		}

		return $this->connection->delete($this->grammar->compileDelete($this), $this->getBindings());
	}

	public function truncate()
	{
		foreach ($this->grammar->compileTruncate($this) as $sql => $bindings) {
			$this->connection->statement($sql, $bindings);
		}
	}

	public function newQuery()
	{
		return new static($this->connection, $this->grammar, $this->processor);
	}

	public function raw($value)
	{
		return $this->connection->raw($value);
	}

	public function getBindings()
	{
		return \Illuminate\Support\Arr::flatten($this->bindings);
	}

	public function getRawBindings()
	{
		return $this->bindings;
	}

	public function setBindings(array $bindings, $type = 'where')
	{
		if (!array_key_exists($type, $this->bindings)) {
			throw new \InvalidArgumentException('Invalid binding type: ' . $type . '.');
		}

		$this->bindings[$type] = $bindings;
		return $this;
	}

	public function addBinding($value, $type = 'where')
	{
		if (!array_key_exists($type, $this->bindings)) {
			throw new \InvalidArgumentException('Invalid binding type: ' . $type . '.');
		}

		if (is_array($value)) {
			$this->bindings[$type] = array_values(array_merge($this->bindings[$type], $value));
		}
		else {
			$this->bindings[$type][] = $value;
		}

		return $this;
	}

	public function mergeBindings(Builder $query)
	{
		$this->bindings = array_merge_recursive($this->bindings, $query->bindings);
		return $this;
	}

	protected function cleanBindings(array $bindings)
	{
		return array_values(array_filter($bindings, function($binding) {
			return !$binding instanceof Expression;
		}));
	}

	public function getConnection()
	{
		return $this->connection;
	}

	public function getProcessor()
	{
		return $this->processor;
	}

	public function getGrammar()
	{
		return $this->grammar;
	}

	public function useWritePdo()
	{
		$this->useWritePdo = true;
		return $this;
	}

	public function cloneWithout(array $except)
	{
		return tap(clone $this, function($clone) use($except) {
			foreach ($except as $property) {
				$clone->$property = null;
			}
		});
	}

	public function cloneWithoutBindings(array $except)
	{
		return tap(clone $this, function($clone) use($except) {
			foreach ($except as $type) {
				$clone->bindings[$type] = array();
			}
		});
	}

	public function __call($method, $parameters)
	{
		if (static::hasMacro($method)) {
			return $this->macroCall($method, $parameters);
		}

		if (\Illuminate\Support\Str::startsWith($method, 'where')) {
			return $this->dynamicWhere($method, $parameters);
		}

		$className = static::class;
		throw new \BadMethodCallException('Call to undefined method ' . $className . '::' . $method . '()');
	}
}

?>
