<?php
//cgxlm
namespace Illuminate\Database\Query\Grammars;

class PostgresGrammar extends Grammar
{
	/**
     * All of the available clause operators.
     *
     * @var array
     */
	protected $operators = array('=', '<', '>', '<=', '>=', '<>', '!=', 'like', 'not like', 'between', 'ilike', '&', '|', '#', '<<', '>>', '@>', '<@', '?', '?|', '?&', '||', '-', '-', '#-');

	protected function whereDate(\Illuminate\Database\Query\Builder $query, $where)
	{
		$value = $this->parameter($where['value']);
		return $this->wrap($where['column']) . '::date ' . $where['operator'] . ' ' . $value;
	}

	protected function dateBasedWhere($type, \Illuminate\Database\Query\Builder $query, $where)
	{
		$value = $this->parameter($where['value']);
		return 'extract(' . $type . ' from ' . $this->wrap($where['column']) . ') ' . $where['operator'] . ' ' . $value;
	}

	protected function compileLock(\Illuminate\Database\Query\Builder $query, $value)
	{
		if (!is_string($value)) {
			return $value ? 'for update' : 'for share';
		}

		return $value;
	}

	public function compileInsertGetId(\Illuminate\Database\Query\Builder $query, $values, $sequence)
	{
		if (is_null($sequence)) {
			$sequence = 'id';
		}

		return $this->compileInsert($query, $values) . ' returning ' . $this->wrap($sequence);
	}

	public function compileUpdate(\Illuminate\Database\Query\Builder $query, $values)
	{
		$table = $this->wrapTable($query->from);
		$columns = $this->compileUpdateColumns($values);
		$from = $this->compileUpdateFrom($query);
		$where = $this->compileUpdateWheres($query);
		return trim('update ' . $table . ' set ' . $columns . $from . ' ' . $where);
	}

	protected function compileUpdateColumns($values)
	{
		return collect($values)->map(function($value, $key) {
			return $this->wrap($key) . ' = ' . $this->parameter($value);
		})->implode(', ');
	}

	protected function compileUpdateFrom(\Illuminate\Database\Query\Builder $query)
	{
		if (!isset($query->joins)) {
			return '';
		}

		$froms = collect($query->joins)->map(function($join) {
			return $this->wrapTable($join->table);
		})->all();

		if (0 < count($froms)) {
			return ' from ' . implode(', ', $froms);
		}
	}

	protected function compileUpdateWheres(\Illuminate\Database\Query\Builder $query)
	{
		$baseWheres = $this->compileWheres($query);

		if (!isset($query->joins)) {
			return $baseWheres;
		}

		$joinWheres = $this->compileUpdateJoinWheres($query);

		if (trim($baseWheres) == '') {
			return 'where ' . $this->removeLeadingBoolean($joinWheres);
		}

		return $baseWheres . ' ' . $joinWheres;
	}

	protected function compileUpdateJoinWheres(\Illuminate\Database\Query\Builder $query)
	{
		$joinWheres = array();

		foreach ($query->joins as $join) {
			foreach ($join->wheres as $where) {
				$method = 'where' . $where['type'];
				$joinWheres[] = $where['boolean'] . ' ' . $this->$method($query, $where);
			}
		}

		return implode(' ', $joinWheres);
	}

	public function prepareBindingsForUpdate(array $bindings, array $values)
	{
		$bindingsWithoutJoin = \Illuminate\Support\Arr::except($bindings, 'join');
		return array_values(array_merge($values, $bindings['join'], \Illuminate\Support\Arr::flatten($bindingsWithoutJoin)));
	}

	public function compileTruncate(\Illuminate\Database\Query\Builder $query)
	{
		return array(
	'truncate ' . $this->wrapTable($query->from) . ' restart identity' => array()
	);
	}

	protected function wrapValue($value)
	{
		if ($value === '*') {
			return $value;
		}

		if (\Illuminate\Support\Str::contains($value, '->')) {
			return $this->wrapJsonSelector($value);
		}

		return '"' . str_replace('"', '""', $value) . '"';
	}

	protected function wrapJsonSelector($value)
	{
		$path = explode('->', $value);
		$field = $this->wrapValue(array_shift($path));
		$wrappedPath = $this->wrapJsonPathAttributes($path);
		$attribute = array_pop($wrappedPath);

		if (!empty($wrappedPath)) {
			return $field . '->' . implode('->', $wrappedPath) . '->>' . $attribute;
		}

		return $field . '->>' . $attribute;
	}

	protected function wrapJsonPathAttributes($path)
	{
		return array_map(function($attribute) {
			return '\'' . $attribute . '\'';
		}, $path);
	}
}

?>
