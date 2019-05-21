<?php
//cgxlm
namespace Illuminate\Database\Schema\Grammars;

class PostgresGrammar extends Grammar
{
	/**
     * If this Grammar supports schema changes wrapped in a transaction.
     *
     * @var bool
     */
	protected $transactions = true;
	/**
     * The possible column modifiers.
     *
     * @var array
     */
	protected $modifiers = array('Increment', 'Nullable', 'Default');
	/**
     * The columns available as serials.
     *
     * @var array
     */
	protected $serials = array('bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger');

	public function compileTableExists()
	{
		return 'select * from information_schema.tables where table_schema = ? and table_name = ?';
	}

	public function compileColumnListing($table)
	{
		return 'select column_name from information_schema.columns where table_name = \'' . $table . '\'';
	}

	public function compileCreate(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return sprintf('%s table %s (%s)', $blueprint->temporary ? 'create temporary' : 'create', $this->wrapTable($blueprint), implode(', ', $this->getColumns($blueprint)));
	}

	public function compileAdd(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return sprintf('alter table %s %s', $this->wrapTable($blueprint), implode(', ', $this->prefixArray('add column', $this->getColumns($blueprint))));
	}

	public function compilePrimary(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$columns = $this->columnize($command->columns);
		return 'alter table ' . $this->wrapTable($blueprint) . ' add primary key (' . $columns . ')';
	}

	public function compileUnique(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return sprintf('alter table %s add constraint %s unique (%s)', $this->wrapTable($blueprint), $this->wrap($command->index), $this->columnize($command->columns));
	}

	public function compileIndex(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return sprintf('create index %s on %s%s (%s)', $this->wrap($command->index), $this->wrapTable($blueprint), $command->algorithm ? ' using ' . $command->algorithm : '', $this->columnize($command->columns));
	}

	public function compileDrop(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return 'drop table ' . $this->wrapTable($blueprint);
	}

	public function compileDropIfExists(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return 'drop table if exists ' . $this->wrapTable($blueprint);
	}

	public function compileDropColumn(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$columns = $this->prefixArray('drop column', $this->wrapArray($command->columns));
		return 'alter table ' . $this->wrapTable($blueprint) . ' ' . implode(', ', $columns);
	}

	public function compileDropPrimary(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$index = $this->wrap($blueprint->getTable() . '_pkey');
		return 'alter table ' . $this->wrapTable($blueprint) . ' drop constraint ' . $index;
	}

	public function compileDropUnique(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$index = $this->wrap($command->index);
		return 'alter table ' . $this->wrapTable($blueprint) . ' drop constraint ' . $index;
	}

	public function compileDropIndex(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return 'drop index ' . $this->wrap($command->index);
	}

	public function compileDropForeign(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$index = $this->wrap($command->index);
		return 'alter table ' . $this->wrapTable($blueprint) . ' drop constraint ' . $index;
	}

	public function compileRename(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$from = $this->wrapTable($blueprint);
		return 'alter table ' . $from . ' rename to ' . $this->wrapTable($command->to);
	}

	public function compileEnableForeignKeyConstraints()
	{
		return 'SET CONSTRAINTS ALL IMMEDIATE;';
	}

	public function compileDisableForeignKeyConstraints()
	{
		return 'SET CONSTRAINTS ALL DEFERRED;';
	}

	protected function typeChar(\Illuminate\Support\Fluent $column)
	{
		return 'char(' . $column->length . ')';
	}

	protected function typeString(\Illuminate\Support\Fluent $column)
	{
		return 'varchar(' . $column->length . ')';
	}

	protected function typeText(\Illuminate\Support\Fluent $column)
	{
		return 'text';
	}

	protected function typeMediumText(\Illuminate\Support\Fluent $column)
	{
		return 'text';
	}

	protected function typeLongText(\Illuminate\Support\Fluent $column)
	{
		return 'text';
	}

	protected function typeInteger(\Illuminate\Support\Fluent $column)
	{
		return $column->autoIncrement ? 'serial' : 'integer';
	}

	protected function typeBigInteger(\Illuminate\Support\Fluent $column)
	{
		return $column->autoIncrement ? 'bigserial' : 'bigint';
	}

	protected function typeMediumInteger(\Illuminate\Support\Fluent $column)
	{
		return $column->autoIncrement ? 'serial' : 'integer';
	}

	protected function typeTinyInteger(\Illuminate\Support\Fluent $column)
	{
		return $column->autoIncrement ? 'smallserial' : 'smallint';
	}

	protected function typeSmallInteger(\Illuminate\Support\Fluent $column)
	{
		return $column->autoIncrement ? 'smallserial' : 'smallint';
	}

	protected function typeFloat(\Illuminate\Support\Fluent $column)
	{
		return $this->typeDouble($column);
	}

	protected function typeDouble(\Illuminate\Support\Fluent $column)
	{
		return 'double precision';
	}

	protected function typeReal(\Illuminate\Support\Fluent $column)
	{
		return 'real';
	}

	protected function typeDecimal(\Illuminate\Support\Fluent $column)
	{
		return 'decimal(' . $column->total . ', ' . $column->places . ')';
	}

	protected function typeBoolean(\Illuminate\Support\Fluent $column)
	{
		return 'boolean';
	}

	protected function typeEnum(\Illuminate\Support\Fluent $column)
	{
		$allowed = array_map(function($a) {
			return '\'' . $a . '\'';
		}, $column->allowed);
		return 'varchar(255) check ("' . $column->name . '" in (' . implode(', ', $allowed) . '))';
	}

	protected function typeJson(\Illuminate\Support\Fluent $column)
	{
		return 'json';
	}

	protected function typeJsonb(\Illuminate\Support\Fluent $column)
	{
		return 'jsonb';
	}

	protected function typeDate(\Illuminate\Support\Fluent $column)
	{
		return 'date';
	}

	protected function typeDateTime(\Illuminate\Support\Fluent $column)
	{
		return 'timestamp(0) without time zone';
	}

	protected function typeDateTimeTz(\Illuminate\Support\Fluent $column)
	{
		return 'timestamp(0) with time zone';
	}

	protected function typeTime(\Illuminate\Support\Fluent $column)
	{
		return 'time(0) without time zone';
	}

	protected function typeTimeTz(\Illuminate\Support\Fluent $column)
	{
		return 'time(0) with time zone';
	}

	protected function typeTimestamp(\Illuminate\Support\Fluent $column)
	{
		if ($column->useCurrent) {
			return 'timestamp(0) without time zone default CURRENT_TIMESTAMP(0)';
		}

		return 'timestamp(0) without time zone';
	}

	protected function typeTimestampTz(\Illuminate\Support\Fluent $column)
	{
		if ($column->useCurrent) {
			return 'timestamp(0) with time zone default CURRENT_TIMESTAMP(0)';
		}

		return 'timestamp(0) with time zone';
	}

	protected function typeBinary(\Illuminate\Support\Fluent $column)
	{
		return 'bytea';
	}

	protected function typeUuid(\Illuminate\Support\Fluent $column)
	{
		return 'uuid';
	}

	protected function typeIpAddress(\Illuminate\Support\Fluent $column)
	{
		return 'inet';
	}

	protected function typeMacAddress(\Illuminate\Support\Fluent $column)
	{
		return 'macaddr';
	}

	protected function modifyNullable(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $column)
	{
		return $column->nullable ? ' null' : ' not null';
	}

	protected function modifyDefault(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $column)
	{
		if (!is_null($column->default)) {
			return ' default ' . $this->getDefaultValue($column->default);
		}
	}

	protected function modifyIncrement(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $column)
	{
		if (in_array($column->type, $this->serials) && $column->autoIncrement) {
			return ' primary key';
		}
	}
}

?>
