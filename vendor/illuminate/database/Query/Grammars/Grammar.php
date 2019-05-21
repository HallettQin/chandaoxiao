<?php
//cgxlm
namespace Illuminate\Database\Query\Grammars;

class Grammar extends \Illuminate\Database\Grammar
{
	/**
     * The grammar specific operators.
     *
     * @var array
     */
	protected $operators = array();
	/**
     * The components that make up a select clause.
     *
     * @var array
     */
	protected $selectComponents = array('aggregate', 'columns', 'from', 'joins', 'wheres', 'groups', 'havings', 'orders', 'limit', 'offset', 'unions', 'lock');

	public function compileSelect(\Illuminate\Database\Query\Builder $query)
	{
		$original = $query->columns;

		if (is_null($query->columns)) {
			$query->columns = array('*');
		}

		$sql = trim($this->concatenate($this->compileComponents($query)));
		$query->columns = $original;
		return $sql;
	}

	protected function compileComponents(\Illuminate\Database\Query\Builder $query)
	{
		$sql = array();

		foreach ($this->selectComponents as $component) {
			if (!is_null($query->$component)) {
				$method = 'compile' . ucfirst($component);
				$sql[$component] = $this->$method($query, $query->$component);
			}
		}

		return $sql;
	}

	protected function compileAggregate(\Illuminate\Database\Query\Builder $query, $aggregate)
	{
		$column = $this->columnize($aggregate['columns']);
		if ($query->distinct && ($column !== '*')) {
			$column = 'distinct ' . $column;
		}

		return 'select ' . $aggregate['function'] . '(' . $column . ') as aggregate';
	}

	protected function compileColumns(\Illuminate\Database\Query\Builder $query, $columns)
	{
		if (!is_null($query->aggregate)) {
			return NULL;
		}

		$select = ($query->distinct ? 'select distinct ' : 'select ');
		return $select . $this->columnize($columns);
	}

	protected function compileFrom(\Illuminate\Database\Query\Builder $query, $table)
	{
		return 'from ' . $this->wrapTable($table);
	}

	protected function compileJoins(\Illuminate\Database\Query\Builder $query, $joins)
	{
		return collect($joins)->map(function($join) use($query) {
			$table = $this->wrapTable($join->table);
			return trim($join->type . ' join ' . $table . ' ' . $this->compileWheres($join));
		})->implode(' ');
	}

	protected function compileWheres(\Illuminate\Database\Query\Builder $query)
	{
		if (is_null($query->wheres)) {
			return '';
		}

		if (0 < count($sql = $this->compileWheresToArray($query))) {
			return $this->concatenateWhereClauses($query, $sql);
		}

		return '';
	}

	protected function compileWheresToArray($query)
	{
		return collect($query->wheres)->map(function($where) use($query) {
			return $where['boolean'] . ' ' . $this->{'where' . $where['type']}($query, $where);
		})->all();
	}

	protected function concatenateWhereClauses($query, $sql)
	{
		$conjunction = ($query instanceof \Illuminate\Database\Query\JoinClause ? 'on' : 'where');
		return $conjunction . ' ' . $this->removeLeadingBoolean(implode(' ', $sql));
	}

	protected function whereRaw(\Illuminate\Database\Query\Builder $query, $where)
	{
		return $where['sql'];
	}

	protected function whereBasic(\Illuminate\Database\Query\Builder $query, $where)
	{
		$value = $this->parameter($where['value']);
		return $this->wrap($where['column']) . ' ' . $where['operator'] . ' ' . $value;
	}

	protected function whereIn(\Illuminate\Database\Query\Builder $query, $where)
	{
		if (!empty($where['values'])) {
			return $this->wrap($where['column']) . ' in (' . $this->parameterize($where['values']) . ')';
		}

		return '0 = 1';
	}

	protected function whereNotIn(\Illuminate\Database\Query\Builder $query, $where)
	{
		if (!empty($where['values'])) {
			return $this->wrap($where['column']) . ' not in (' . $this->parameterize($where['values']) . ')';
		}

		return '1 = 1';
	}

	protected function whereInSub(\Illuminate\Database\Query\Builder $query, $where)
	{
		return $this->wrap($where['column']) . ' in (' . $this->compileSelect($where['query']) . ')';
	}

	protected function whereNotInSub(\Illuminate\Database\Query\Builder $query, $where)
	{
		return $this->wrap($where['column']) . ' not in (' . $this->compileSelect($where['query']) . ')';
	}

	protected function whereNull(\Illuminate\Database\Query\Builder $query, $where)
	{
		return $this->wrap($where['column']) . ' is null';
	}

	protected function whereNotNull(\Illuminate\Database\Query\Builder $query, $where)
	{
		return $this->wrap($where['column']) . ' is not null';
	}

	protected function whereBetween(\Illuminate\Database\Query\Builder $query, $where)
	{
		$between = ($where['not'] ? 'not between' : 'between');
		return $this->wrap($where['column']) . ' ' . $between . ' ? and ?';
	}

	protected function whereDate(\Illuminate\Database\Query\Builder $query, $where)
	{
		return $this->dateBasedWhere('date', $query, $where);
	}

	protected function whereTime(\Illuminate\Database\Query\Builder $query, $where)
	{
		return $this->dateBasedWhere('time', $query, $where);
	}

	protected function whereDay(\Illuminate\Database\Query\Builder $query, $where)
	{
		return $this->dateBasedWhere('day', $query, $where);
	}

	protected function whereMonth(\Illuminate\Database\Query\Builder $query, $where)
	{
		return $this->dateBasedWhere('month', $query, $where);
	}

	protected function whereYear(\Illuminate\Database\Query\Builder $query, $where)
	{
		return $this->dateBasedWhere('year', $query, $where);
	}

	protected function dateBasedWhere($type, \Illuminate\Database\Query\Builder $query, $where)
	{
		$value = $this->parameter($where['value']);
		return $type . '(' . $this->wrap($where['column']) . ') ' . $where['operator'] . ' ' . $value;
	}

	protected function whereColumn(\Illuminate\Database\Query\Builder $query, $where)
	{
		return $this->wrap($where['first']) . ' ' . $where['operator'] . ' ' . $this->wrap($where['second']);
	}

	protected function whereNested(\Illuminate\Database\Query\Builder $query, $where)
	{
		$offset = ($query instanceof \Illuminate\Database\Query\JoinClause ? 3 : 6);
		return '(' . substr($this->compileWheres($where['query']), $offset) . ')';
	}

	protected function whereSub(\Illuminate\Database\Query\Builder $query, $where)
	{
		$select = $this->compileSelect($where['query']);
		return $this->wrap($where['column']) . ' ' . $where['operator'] . ' (' . $select . ')';
	}

	protected function whereExists(\Illuminate\Database\Query\Builder $query, $where)
	{
		return 'exists (' . $this->compileSelect($where['query']) . ')';
	}

	protected function whereNotExists(\Illuminate\Database\Query\Builder $query, $where)
	{
		return 'not exists (' . $this->compileSelect($where['query']) . ')';
	}

	protected function compileGroups(\Illuminate\Database\Query\Builder $query, $groups)
	{
		return 'group by ' . $this->columnize($groups);
	}

	protected function compileHavings(\Illuminate\Database\Query\Builder $query, $havings)
	{
		$sql = implode(' ', array_map(array($this, 'compileHaving'), $havings));
		return 'having ' . $this->removeLeadingBoolean($sql);
	}

	protected function compileHaving(array $having)
	{
		if ($having['type'] === 'Raw') {
			return $having['boolean'] . ' ' . $having['sql'];
		}

		return $this->compileBasicHaving($having);
	}

	protected function compileBasicHaving($having)
	{
		$column = $this->wrap($having['column']);
		$parameter = $this->parameter($having['value']);
		return $having['boolean'] . ' ' . $column . ' ' . $having['operator'] . ' ' . $parameter;
	}

	protected function compileOrders(\Illuminate\Database\Query\Builder $query, $orders)
	{
		if (!empty($orders)) {
			return 'order by ' . implode(', ', $this->compileOrdersToArray($query, $orders));
		}

		return '';
	}

	protected function compileOrdersToArray(\Illuminate\Database\Query\Builder $query, $orders)
	{
		return array_map(function($order) {
			return !isset($order['sql']) ? $this->wrap($order['column']) . ' ' . $order['direction'] : $order['sql'];
		}, $orders);
	}

	public function compileRandom($seed)
	{
		return 'RANDOM()';
	}

	protected function compileLimit(\Illuminate\Database\Query\Builder $query, $limit)
	{
		return 'limit ' . (int) $limit;
	}

	protected function compileOffset(\Illuminate\Database\Query\Builder $query, $offset)
	{
		return 'offset ' . (int) $offset;
	}

	protected function compileUnions(\Illuminate\Database\Query\Builder $query)
	{
		$sql = '';

		foreach ($query->unions as $union) {
			$sql .= $this->compileUnion($union);
		}

		if (!empty($query->unionOrders)) {
			$sql .= ' ' . $this->compileOrders($query, $query->unionOrders);
		}

		if (isset($query->unionLimit)) {
			$sql .= ' ' . $this->compileLimit($query, $query->unionLimit);
		}

		if (isset($query->unionOffset)) {
			$sql .= ' ' . $this->compileOffset($query, $query->unionOffset);
		}

		return ltrim($sql);
	}

	protected function compileUnion(array $union)
	{
		$conjuction = ($union['all'] ? ' union all ' : ' union ');
		return $conjuction . $union['query']->toSql();
	}

	public function compileExists(\Illuminate\Database\Query\Builder $query)
	{
		$select = $this->compileSelect($query);
		return 'select exists(' . $select . ') as ' . $this->wrap('exists');
	}

	public function compileInsert(\Illuminate\Database\Query\Builder $query, array $values)
	{
		$table = $this->wrapTable($query->from);

		if (!is_array(reset($values))) {
			$values = array($values);
		}

		$columns = $this->columnize(array_keys(reset($values)));
		$parameters = collect($values)->map(function($record) {
			return '(' . $this->parameterize($record) . ')';
		})->implode(', ');
		return 'insert into ' . $table . ' (' . $columns . ') values ' . $parameters;
	}

	public function compileInsertGetId(\Illuminate\Database\Query\Builder $query, $values, $sequence)
	{
		return $this->compileInsert($query, $values);
	}

	public function compileUpdate(\Illuminate\Database\Query\Builder $query, $values)
	{
		$table = $this->wrapTable($query->from);
		$columns = collect($values)->map(function($value, $key) {
			return $this->wrap($key) . ' = ' . $this->parameter($value);
		})->implode(', ');
		$joins = '';

		if (isset($query->joins)) {
			$joins = ' ' . $this->compileJoins($query, $query->joins);
		}

		$wheres = $this->compileWheres($query);
		return trim('update ' . $table . $joins . ' set ' . $columns . ' ' . $wheres);
	}

	public function prepareBindingsForUpdate(array $bindings, array $values)
	{
		$bindingsWithoutJoin = \Illuminate\Support\Arr::except($bindings, 'join');
		return array_values(array_merge($bindings['join'], $values, \Illuminate\Support\Arr::flatten($bindingsWithoutJoin)));
	}

	public function compileDelete(\Illuminate\Database\Query\Builder $query)
	{
		$wheres = (is_array($query->wheres) ? $this->compileWheres($query) : '');
		return trim('delete from ' . $this->wrapTable($query->from) . ' ' . $wheres);
	}

	public function compileTruncate(\Illuminate\Database\Query\Builder $query)
	{
		return array(
	'truncate ' . $this->wrapTable($query->from) => array()
	);
	}

	protected function compileLock(\Illuminate\Database\Query\Builder $query, $value)
	{
		return is_string($value) ? $value : '';
	}

	public function supportsSavepoints()
	{
		return true;
	}

	public function compileSavepoint($name)
	{
		return 'SAVEPOINT ' . $name;
	}

	public function compileSavepointRollBack($name)
	{
		return 'ROLLBACK TO SAVEPOINT ' . $name;
	}

	protected function concatenate($segments)
	{
		return implode(' ', array_filter($segments, function($value) {
			return (string) $value !== '';
		}));
	}

	protected function removeLeadingBoolean($value)
	{
		return preg_replace('/and |or /i', '', $value, 1);
	}

	public function getOperators()
	{
		return $this->operators;
	}
}

?>
