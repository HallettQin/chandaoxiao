<?php
//cgxlm
namespace Illuminate\Database\Schema\Grammars;

class SqlServerGrammar extends Grammar
{
	/**
     * The possible column modifiers.
     *
     * @var array
     */
	protected $modifiers = array('Increment', 'Collate', 'Nullable', 'Default');
	/**
     * The columns available as serials.
     *
     * @var array
     */
	protected $serials = array('tinyInteger', 'smallInteger', 'mediumInteger', 'integer', 'bigInteger');

	public function compileTableExists()
	{
		return 'select * from sysobjects where type = \'U\' and name = ?';
	}

	public function compileColumnListing($table)
	{
		return "select col.name from sys.columns as col\n                join sys.objects as obj on col.object_id = obj.object_id\n                where obj.type = 'U' and obj.name = '" . $table . '\'';
	}

	public function compileCreate(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$columns = implode(', ', $this->getColumns($blueprint));
		return 'create table ' . $this->wrapTable($blueprint) . ' (' . $columns . ')';
	}

	public function compileAdd(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return sprintf('alter table %s add %s', $this->wrapTable($blueprint), implode(', ', $this->getColumns($blueprint)));
	}

	public function compilePrimary(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return sprintf('alter table %s add constraint %s primary key (%s)', $this->wrapTable($blueprint), $this->wrap($command->index), $this->columnize($command->columns));
	}

	public function compileUnique(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return sprintf('create unique index %s on %s (%s)', $this->wrap($command->index), $this->wrapTable($blueprint), $this->columnize($command->columns));
	}

	public function compileIndex(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return sprintf('create index %s on %s (%s)', $this->wrap($command->index), $this->wrapTable($blueprint), $this->columnize($command->columns));
	}

	public function compileDrop(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return 'drop table ' . $this->wrapTable($blueprint);
	}

	public function compileDropIfExists(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return sprintf('if exists (select * from INFORMATION_SCHEMA.TABLES where TABLE_NAME = %s) drop table %s', '\'' . str_replace('\'', '\'\'', $this->getTablePrefix() . $blueprint->getTable()) . '\'', $this->wrapTable($blueprint));
	}

	public function compileDropColumn(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$columns = $this->wrapArray($command->columns);
		return 'alter table ' . $this->wrapTable($blueprint) . ' drop column ' . implode(', ', $columns);
	}

	public function compileDropPrimary(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$index = $this->wrap($command->index);
		return 'alter table ' . $this->wrapTable($blueprint) . ' drop constraint ' . $index;
	}

	public function compileDropUnique(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$index = $this->wrap($command->index);
		return 'drop index ' . $index . ' on ' . $this->wrapTable($blueprint);
	}

	public function compileDropIndex(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$index = $this->wrap($command->index);
		return 'drop index ' . $index . ' on ' . $this->wrapTable($blueprint);
	}

	public function compileDropForeign(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$index = $this->wrap($command->index);
		return 'alter table ' . $this->wrapTable($blueprint) . ' drop constraint ' . $index;
	}

	public function compileRename(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$from = $this->wrapTable($blueprint);
		return 'sp_rename ' . $from . ', ' . $this->wrapTable($command->to);
	}

	public function compileEnableForeignKeyConstraints()
	{
		return 'EXEC sp_msforeachtable @command1="print \'?\'", @command2="ALTER TABLE ? WITH CHECK CHECK CONSTRAINT all";';
	}

	public function compileDisableForeignKeyConstraints()
	{
		return 'EXEC sp_msforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all";';
	}

	protected function typeChar(\Illuminate\Support\Fluent $column)
	{
		return 'nchar(' . $column->length . ')';
	}

	protected function typeString(\Illuminate\Support\Fluent $column)
	{
		return 'nvarchar(' . $column->length . ')';
	}

	protected function typeText(\Illuminate\Support\Fluent $column)
	{
		return 'nvarchar(max)';
	}

	protected function typeMediumText(\Illuminate\Support\Fluent $column)
	{
		return 'nvarchar(max)';
	}

	protected function typeLongText(\Illuminate\Support\Fluent $column)
	{
		return 'nvarchar(max)';
	}

	protected function typeInteger(\Illuminate\Support\Fluent $column)
	{
		return 'int';
	}

	protected function typeBigInteger(\Illuminate\Support\Fluent $column)
	{
		return 'bigint';
	}

	protected function typeMediumInteger(\Illuminate\Support\Fluent $column)
	{
		return 'int';
	}

	protected function typeTinyInteger(\Illuminate\Support\Fluent $column)
	{
		return 'tinyint';
	}

	protected function typeSmallInteger(\Illuminate\Support\Fluent $column)
	{
		return 'smallint';
	}

	protected function typeFloat(\Illuminate\Support\Fluent $column)
	{
		return 'float';
	}

	protected function typeDouble(\Illuminate\Support\Fluent $column)
	{
		return 'float';
	}

	protected function typeDecimal(\Illuminate\Support\Fluent $column)
	{
		return 'decimal(' . $column->total . ', ' . $column->places . ')';
	}

	protected function typeBoolean(\Illuminate\Support\Fluent $column)
	{
		return 'bit';
	}

	protected function typeEnum(\Illuminate\Support\Fluent $column)
	{
		return 'nvarchar(255)';
	}

	protected function typeJson(\Illuminate\Support\Fluent $column)
	{
		return 'nvarchar(max)';
	}

	protected function typeJsonb(\Illuminate\Support\Fluent $column)
	{
		return 'nvarchar(max)';
	}

	protected function typeDate(\Illuminate\Support\Fluent $column)
	{
		return 'date';
	}

	protected function typeDateTime(\Illuminate\Support\Fluent $column)
	{
		return 'datetime';
	}

	protected function typeDateTimeTz(\Illuminate\Support\Fluent $column)
	{
		return 'datetimeoffset(0)';
	}

	protected function typeTime(\Illuminate\Support\Fluent $column)
	{
		return 'time';
	}

	protected function typeTimeTz(\Illuminate\Support\Fluent $column)
	{
		return 'time';
	}

	protected function typeTimestamp(\Illuminate\Support\Fluent $column)
	{
		if ($column->useCurrent) {
			return 'datetime default CURRENT_TIMESTAMP';
		}

		return 'datetime';
	}

	protected function typeTimestampTz(\Illuminate\Support\Fluent $column)
	{
		if ($column->useCurrent) {
			return 'datetimeoffset(0) default CURRENT_TIMESTAMP';
		}

		return 'datetimeoffset(0)';
	}

	protected function typeBinary(\Illuminate\Support\Fluent $column)
	{
		return 'varbinary(max)';
	}

	protected function typeUuid(\Illuminate\Support\Fluent $column)
	{
		return 'uniqueidentifier';
	}

	protected function typeIpAddress(\Illuminate\Support\Fluent $column)
	{
		return 'nvarchar(45)';
	}

	protected function typeMacAddress(\Illuminate\Support\Fluent $column)
	{
		return 'nvarchar(17)';
	}

	protected function modifyCollate(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $column)
	{
		if (!is_null($column->collation)) {
			return ' collate ' . $column->collation;
		}
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
			return ' identity primary key';
		}
	}
}

?>
