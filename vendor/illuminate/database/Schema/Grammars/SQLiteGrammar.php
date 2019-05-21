<?php
//cgxlm
namespace Illuminate\Database\Schema\Grammars;

class SQLiteGrammar extends Grammar
{
	/**
     * The possible column modifiers.
     *
     * @var array
     */
	protected $modifiers = array('Nullable', 'Default', 'Increment');
	/**
     * The columns available as serials.
     *
     * @var array
     */
	protected $serials = array('bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger');

	public function compileTableExists()
	{
		return 'select * from sqlite_master where type = \'table\' and name = ?';
	}

	public function compileColumnListing($table)
	{
		return 'pragma table_info(' . $this->wrapTable(str_replace('.', '__', $table)) . ')';
	}

	public function compileCreate(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return sprintf('%s table %s (%s%s%s)', $blueprint->temporary ? 'create temporary' : 'create', $this->wrapTable($blueprint), implode(', ', $this->getColumns($blueprint)), (string) $this->addForeignKeys($blueprint), (string) $this->addPrimaryKeys($blueprint));
	}

	protected function addForeignKeys(\Illuminate\Database\Schema\Blueprint $blueprint)
	{
		$foreigns = $this->getCommandsByName($blueprint, 'foreign');
		return collect($foreigns)->reduce(function($sql, $foreign) {
			$sql .= $this->getForeignKey($foreign);

			if (!is_null($foreign->onDelete)) {
				$sql .= ' on delete ' . $foreign->onDelete;
			}

			if (!is_null($foreign->onUpdate)) {
				$sql .= ' on update ' . $foreign->onUpdate;
			}

			return $sql;
		}, '');
	}

	protected function getForeignKey($foreign)
	{
		return sprintf(', foreign key(%s) references %s(%s)', $this->columnize($foreign->columns), $this->wrapTable($foreign->on), $this->columnize((array) $foreign->references));
	}

	protected function addPrimaryKeys(\Illuminate\Database\Schema\Blueprint $blueprint)
	{
		if (!is_null($primary = $this->getCommandByName($blueprint, 'primary'))) {
			return ', primary key (' . $this->columnize($primary->columns) . ')';
		}
	}

	public function compileAdd(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$columns = $this->prefixArray('add column', $this->getColumns($blueprint));
		return collect($columns)->map(function($column) use($blueprint) {
			return 'alter table ' . $this->wrapTable($blueprint) . ' ' . $column;
		})->all();
	}

	public function compileUnique(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return sprintf('create unique index %s on %s (%s)', $this->wrap($command->index), $this->wrapTable($blueprint), $this->columnize($command->columns));
	}

	public function compileIndex(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return sprintf('create index %s on %s (%s)', $this->wrap($command->index), $this->wrapTable($blueprint), $this->columnize($command->columns));
	}

	public function compileForeign(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
	}

	public function compileDrop(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return 'drop table ' . $this->wrapTable($blueprint);
	}

	public function compileDropIfExists(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return 'drop table if exists ' . $this->wrapTable($blueprint);
	}

	public function compileDropColumn(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command, \Illuminate\Database\Connection $connection)
	{
		$tableDiff = $this->getDoctrineTableDiff($blueprint, $schema = $connection->getDoctrineSchemaManager());

		foreach ($command->columns as $name) {
			$column = $connection->getDoctrineColumn($blueprint->getTable(), $name);
			$tableDiff->removedColumns[$name] = $column;
		}

		return (array) $schema->getDatabasePlatform()->getAlterTableSQL($tableDiff);
	}

	public function compileDropUnique(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$index = $this->wrap($command->index);
		return 'drop index ' . $index;
	}

	public function compileDropIndex(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$index = $this->wrap($command->index);
		return 'drop index ' . $index;
	}

	public function compileRename(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$from = $this->wrapTable($blueprint);
		return 'alter table ' . $from . ' rename to ' . $this->wrapTable($command->to);
	}

	public function compileEnableForeignKeyConstraints()
	{
		return 'PRAGMA foreign_keys = ON;';
	}

	public function compileDisableForeignKeyConstraints()
	{
		return 'PRAGMA foreign_keys = OFF;';
	}

	protected function typeChar(\Illuminate\Support\Fluent $column)
	{
		return 'varchar';
	}

	protected function typeString(\Illuminate\Support\Fluent $column)
	{
		return 'varchar';
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
		return 'integer';
	}

	protected function typeBigInteger(\Illuminate\Support\Fluent $column)
	{
		return 'integer';
	}

	protected function typeMediumInteger(\Illuminate\Support\Fluent $column)
	{
		return 'integer';
	}

	protected function typeTinyInteger(\Illuminate\Support\Fluent $column)
	{
		return 'integer';
	}

	protected function typeSmallInteger(\Illuminate\Support\Fluent $column)
	{
		return 'integer';
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
		return 'numeric';
	}

	protected function typeBoolean(\Illuminate\Support\Fluent $column)
	{
		return 'tinyint(1)';
	}

	protected function typeEnum(\Illuminate\Support\Fluent $column)
	{
		return 'varchar';
	}

	protected function typeJson(\Illuminate\Support\Fluent $column)
	{
		return 'text';
	}

	protected function typeJsonb(\Illuminate\Support\Fluent $column)
	{
		return 'text';
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
		return 'datetime';
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
			return 'datetime default CURRENT_TIMESTAMP';
		}

		return 'datetime';
	}

	protected function typeBinary(\Illuminate\Support\Fluent $column)
	{
		return 'blob';
	}

	protected function typeUuid(\Illuminate\Support\Fluent $column)
	{
		return 'varchar';
	}

	protected function typeIpAddress(\Illuminate\Support\Fluent $column)
	{
		return 'varchar';
	}

	protected function typeMacAddress(\Illuminate\Support\Fluent $column)
	{
		return 'varchar';
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
			return ' primary key autoincrement';
		}
	}
}

?>
