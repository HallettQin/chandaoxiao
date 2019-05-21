<?php
//cgxlm
namespace Illuminate\Database\Query\Grammars;

class SqlServerGrammar extends Grammar
{
	/**
     * All of the available clause operators.
     *
     * @var array
     */
	protected $operators = array('=', '<', '>', '<=', '>=', '!<', '!>', '<>', '!=', 'like', 'not like', 'between', 'ilike', '&', '&=', '|', '|=', '^', '^=');

	public function compileSelect(\Illuminate\Database\Query\Builder $query)
	{
		if (!$query->offset) {
			return parent::compileSelect($query);
		}

		if (is_null($query->columns)) {
			$query->columns = array('*');
		}

		return $this->compileAnsiOffset($query, $this->compileComponents($query));
	}

	protected function compileColumns(\Illuminate\Database\Query\Builder $query, $columns)
	{
		if (!is_null($query->aggregate)) {
			return NULL;
		}

		$select = ($query->distinct ? 'select distinct ' : 'select ');
		if ((0 < $query->limit) && ($query->offset <= 0)) {
			$select .= 'top ' . $query->limit . ' ';
		}

		return $select . $this->columnize($columns);
	}

	protected function compileFrom(\Illuminate\Database\Query\Builder $query, $table)
	{
		$from = parent::compileFrom($query, $table);

		if (is_string($query->lock)) {
			return $from . ' ' . $query->lock;
		}

		if (!is_null($query->lock)) {
			return $from . ' with(rowlock,' . ($query->lock ? 'updlock,' : '') . 'holdlock)';
		}

		return $from;
	}

	protected function whereDate(\Illuminate\Database\Query\Builder $query, $where)
	{
		$value = $this->parameter($where['value']);
		return 'cast(' . $this->wrap($where['column']) . ' as date) ' . $where['operator'] . ' ' . $value;
	}

	protected function compileAnsiOffset(\Illuminate\Database\Query\Builder $query, $components)
	{
		if (empty($components['orders'])) {
			$components['orders'] = 'order by (select 0)';
		}

		$components['columns'] .= $this->compileOver($components['orders']);
		unset($components['orders']);
		$sql = $this->concatenate($components);
		return $this->compileTableExpression($sql, $query);
	}

	protected function compileOver($orderings)
	{
		return ', row_number() over (' . $orderings . ') as row_num';
	}

	protected function compileTableExpression($sql, $query)
	{
		$constraint = $this->compileRowConstraint($query);
		return 'select * from (' . $sql . ') as temp_table where row_num ' . $constraint;
	}

	protected function compileRowConstraint($query)
	{
		$start = $query->offset + 1;

		if (0 < $query->limit) {
			$finish = $query->offset + $query->limit;
			return 'between ' . $start . ' and ' . $finish;
		}

		return '>= ' . $start;
	}

	public function compileRandom($seed)
	{
		return 'NEWID()';
	}

	protected function compileLimit(\Illuminate\Database\Query\Builder $query, $limit)
	{
		return '';
	}

	protected function compileOffset(\Illuminate\Database\Query\Builder $query, $offset)
	{
		return '';
	}

	protected function compileLock(\Illuminate\Database\Query\Builder $query, $value)
	{
		return '';
	}

	public function compileExists(\Illuminate\Database\Query\Builder $query)
	{
		$existsQuery = clone $query;
		$existsQuery->columns = array();
		return $this->compileSelect($existsQuery->selectRaw('1 [exists]')->limit(1));
	}

	public function compileDelete(\Illuminate\Database\Query\Builder $query)
	{
		$table = $this->wrapTable($query->from);
		$where = (is_array($query->wheres) ? $this->compileWheres($query) : '');
		return isset($query->joins) ? $this->compileDeleteWithJoins($query, $table, $where) : trim('delete from ' . $table . ' ' . $where);
	}

	protected function compileDeleteWithJoins(\Illuminate\Database\Query\Builder $query, $table, $where)
	{
		$joins = ' ' . $this->compileJoins($query, $query->joins);
		$alias = (strpos(strtolower($table), ' as ') !== false ? explode(' as ', $table)[1] : $table);
		return trim('delete ' . $alias . ' from ' . $table . $joins . ' ' . $where);
	}

	public function compileTruncate(\Illuminate\Database\Query\Builder $query)
	{
		return array(
	'truncate table ' . $this->wrapTable($query->from) => array()
	);
	}

	public function compileUpdate(\Illuminate\Database\Query\Builder $query, $values)
	{
		list($table, $alias) = $this->parseUpdateTable($query->from);
		$columns = collect($values)->map(function($value, $key) {
			return $this->wrap($key) . ' = ' . $this->parameter($value);
		})->implode(', ');
		$joins = '';

		if (isset($query->joins)) {
			$joins = ' ' . $this->compileJoins($query, $query->joins);
		}

		$where = $this->compileWheres($query);

		if (!empty($joins)) {
			return trim('update ' . $alias . ' set ' . $columns . ' from ' . $table . $joins . ' ' . $where);
		}

		return trim('update ' . $table . $joins . ' set ' . $columns . ' ' . $where);
	}

	protected function parseUpdateTable($table)
	{
		$table = $alias = $this->wrapTable($table);

		if (strpos(strtolower($table), '] as [') !== false) {
			$alias = '[' . explode('] as [', $table)[1];
		}

		return array($table, $alias);
	}

	public function prepareBindingsForUpdate(array $bindings, array $values)
	{
		$bindingsWithoutJoin = \Illuminate\Support\Arr::except($bindings, 'join');
		return array_values(array_merge($values, $bindings['join'], \Illuminate\Support\Arr::flatten($bindingsWithoutJoin)));
	}

	public function supportsSavepoints()
	{
		return true;
	}

	public function compileSavepoint($name)
	{
		return 'SAVE TRANSACTION ' . $name;
	}

	public function compileSavepointRollBack($name)
	{
		return 'ROLLBACK TRANSACTION ' . $name;
	}

	public function getDateFormat()
	{
		return 'Y-m-d H:i:s.000';
	}

	protected function wrapValue($value)
	{
		return $value === '*' ? $value : '[' . str_replace(']', ']]', $value) . ']';
	}

	public function wrapTable($table)
	{
		return $this->wrapTableValuedFunction(parent::wrapTable($table));
	}

	protected function wrapTableValuedFunction($table)
	{
		if (preg_match('/^(.+?)(\\(.*?\\))]$/', $table, $matches) === 1) {
			$table = $matches[1] . ']' . $matches[2];
		}

		return $table;
	}
}

?>
