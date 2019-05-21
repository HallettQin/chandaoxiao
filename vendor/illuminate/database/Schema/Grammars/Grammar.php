<?php
//cgxlm
namespace Illuminate\Database\Schema\Grammars;

abstract class Grammar extends \Illuminate\Database\Grammar
{
	/**
     * If this Grammar supports schema changes wrapped in a transaction.
     *
     * @var bool
     */
	protected $transactions = false;

	public function compileRenameColumn(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command, \Illuminate\Database\Connection $connection)
	{
		return RenameColumn::compile($this, $blueprint, $command, $connection);
	}

	public function compileChange(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command, \Illuminate\Database\Connection $connection)
	{
		return ChangeColumn::compile($this, $blueprint, $command, $connection);
	}

	public function compileForeign(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$sql = sprintf('alter table %s add constraint %s ', $this->wrapTable($blueprint), $this->wrap($command->index));
		$sql .= sprintf('foreign key (%s) references %s (%s)', $this->columnize($command->columns), $this->wrapTable($command->on), $this->columnize((array) $command->references));

		if (!is_null($command->onDelete)) {
			$sql .= ' on delete ' . $command->onDelete;
		}

		if (!is_null($command->onUpdate)) {
			$sql .= ' on update ' . $command->onUpdate;
		}

		return $sql;
	}

	protected function getColumns(\Illuminate\Database\Schema\Blueprint $blueprint)
	{
		$columns = array();

		foreach ($blueprint->getAddedColumns() as $column) {
			$sql = $this->wrap($column) . ' ' . $this->getType($column);
			$columns[] = $this->addModifiers($sql, $blueprint, $column);
		}

		return $columns;
	}

	protected function getType(\Illuminate\Support\Fluent $column)
	{
		return $this->{'type' . ucfirst($column->type)}($column);
	}

	protected function addModifiers($sql, \Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $column)
	{
		foreach ($this->modifiers as $modifier) {
			if (method_exists($this, $method = 'modify' . $modifier)) {
				$sql .= $this->$method($blueprint, $column);
			}
		}

		return $sql;
	}

	protected function getCommandByName(\Illuminate\Database\Schema\Blueprint $blueprint, $name)
	{
		$commands = $this->getCommandsByName($blueprint, $name);

		if (0 < count($commands)) {
			return reset($commands);
		}
	}

	protected function getCommandsByName(\Illuminate\Database\Schema\Blueprint $blueprint, $name)
	{
		return array_filter($blueprint->getCommands(), function($value) use($name) {
			return $value->name == $name;
		});
	}

	public function prefixArray($prefix, array $values)
	{
		return array_map(function($value) use($prefix) {
			return $prefix . ' ' . $value;
		}, $values);
	}

	public function wrapTable($table)
	{
		return parent::wrapTable($table instanceof \Illuminate\Database\Schema\Blueprint ? $table->getTable() : $table);
	}

	public function wrap($value, $prefixAlias = false)
	{
		return parent::wrap($value instanceof \Illuminate\Support\Fluent ? $value->name : $value, $prefixAlias);
	}

	protected function getDefaultValue($value)
	{
		if ($value instanceof \Illuminate\Database\Query\Expression) {
			return $value;
		}

		return is_bool($value) ? '\'' . (int) $value . '\'' : '\'' . strval($value) . '\'';
	}

	public function getDoctrineTableDiff(\Illuminate\Database\Schema\Blueprint $blueprint, \Doctrine\DBAL\Schema\AbstractSchemaManager $schema)
	{
		$table = $this->getTablePrefix() . $blueprint->getTable();
		return tap(new \Doctrine\DBAL\Schema\TableDiff($table), function($tableDiff) use($schema, $table) {
			$tableDiff->fromTable = $schema->listTableDetails($table);
		});
	}

	public function supportsSchemaTransactions()
	{
		return $this->transactions;
	}
}

?>
