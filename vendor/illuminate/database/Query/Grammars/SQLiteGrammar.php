<?php
//cgxlm
namespace Illuminate\Database\Query\Grammars;

class SQLiteGrammar extends Grammar
{
	/**
     * The components that make up a select clause.
     *
     * @var array
     */
	protected $selectComponents = array('aggregate', 'columns', 'from', 'joins', 'wheres', 'groups', 'havings', 'orders', 'limit', 'offset', 'lock');
	/**
     * All of the available clause operators.
     *
     * @var array
     */
	protected $operators = array('=', '<', '>', '<=', '>=', '<>', '!=', 'like', 'not like', 'between', 'ilike', '&', '|', '<<', '>>');

	public function compileSelect(\Illuminate\Database\Query\Builder $query)
	{
		$sql = parent::compileSelect($query);

		if ($query->unions) {
			$sql = 'select * from (' . $sql . ') ' . $this->compileUnions($query);
		}

		return $sql;
	}

	protected function compileUnion(array $union)
	{
		$conjuction = ($union['all'] ? ' union all ' : ' union ');
		return $conjuction . 'select * from (' . $union['query']->toSql() . ')';
	}

	protected function whereDate(\Illuminate\Database\Query\Builder $query, $where)
	{
		return $this->dateBasedWhere('%Y-%m-%d', $query, $where);
	}

	protected function whereDay(\Illuminate\Database\Query\Builder $query, $where)
	{
		return $this->dateBasedWhere('%d', $query, $where);
	}

	protected function whereMonth(\Illuminate\Database\Query\Builder $query, $where)
	{
		return $this->dateBasedWhere('%m', $query, $where);
	}

	protected function whereYear(\Illuminate\Database\Query\Builder $query, $where)
	{
		return $this->dateBasedWhere('%Y', $query, $where);
	}

	protected function dateBasedWhere($type, \Illuminate\Database\Query\Builder $query, $where)
	{
		$value = str_pad($where['value'], 2, '0', STR_PAD_LEFT);
		$value = $this->parameter($value);
		return 'strftime(\'' . $type . '\', ' . $this->wrap($where['column']) . ') ' . $where['operator'] . ' ' . $value;
	}

	public function compileInsert(\Illuminate\Database\Query\Builder $query, array $values)
	{
		$table = $this->wrapTable($query->from);

		if (!is_array(reset($values))) {
			$values = array($values);
		}

		if (count($values) == 1) {
			return parent::compileInsert($query, reset($values));
		}

		$names = $this->columnize(array_keys(reset($values)));
		$columns = array();

		foreach (array_keys(reset($values)) as $column) {
			$columns[] = '? as ' . $this->wrap($column);
		}

		$columns = array_fill(0, count($values), implode(', ', $columns));
		return 'insert into ' . $table . ' (' . $names . ') select ' . implode(' union all select ', $columns);
	}

	public function compileTruncate(\Illuminate\Database\Query\Builder $query)
	{
		return array(
	'delete from sqlite_sequence where name = ?'    => array($query->from),
	'delete from ' . $this->wrapTable($query->from) => array()
	);
	}
}

?>
