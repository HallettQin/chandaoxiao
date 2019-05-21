<?php
//cgxlm
namespace Illuminate\Database\Schema\Grammars;

class MySqlGrammar extends Grammar
{
	/**
     * The possible column modifiers.
     *
     * @var array
     */
	protected $modifiers = array('VirtualAs', 'StoredAs', 'Unsigned', 'Charset', 'Collate', 'Nullable', 'Default', 'Increment', 'Comment', 'After', 'First');
	/**
     * The possible column serials.
     *
     * @var array
     */
	protected $serials = array('bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger');

	public function compileTableExists()
	{
		return 'select * from information_schema.tables where table_schema = ? and table_name = ?';
	}

	public function compileColumnListing()
	{
		return 'select column_name from information_schema.columns where table_schema = ? and table_name = ?';
	}

	public function compileCreate(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command, \Illuminate\Database\Connection $connection)
	{
		$sql = $this->compileCreateTable($blueprint, $command, $connection);
		$sql = $this->compileCreateEncoding($sql, $connection, $blueprint);
		return $this->compileCreateEngine($sql, $connection, $blueprint);
	}

	protected function compileCreateTable($blueprint, $command, $connection)
	{
		return sprintf('%s table %s (%s)', $blueprint->temporary ? 'create temporary' : 'create', $this->wrapTable($blueprint), implode(', ', $this->getColumns($blueprint)));
	}

	protected function compileCreateEncoding($sql, \Illuminate\Database\Connection $connection, \Illuminate\Database\Schema\Blueprint $blueprint)
	{
		if (isset($blueprint->charset)) {
			$sql .= ' default character set ' . $blueprint->charset;
		}
		else if (!is_null($charset = $connection->getConfig('charset'))) {
			$sql .= ' default character set ' . $charset;
		}

		if (isset($blueprint->collation)) {
			$sql .= ' collate ' . $blueprint->collation;
		}
		else if (!is_null($collation = $connection->getConfig('collation'))) {
			$sql .= ' collate ' . $collation;
		}

		return $sql;
	}

	protected function compileCreateEngine($sql, \Illuminate\Database\Connection $connection, \Illuminate\Database\Schema\Blueprint $blueprint)
	{
		if (isset($blueprint->engine)) {
			return $sql . ' engine = ' . $blueprint->engine;
		}
		else if (!is_null($engine = $connection->getConfig('engine'))) {
			return $sql . ' engine = ' . $engine;
		}

		return $sql;
	}

	public function compileAdd(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$columns = $this->prefixArray('add', $this->getColumns($blueprint));
		return 'alter table ' . $this->wrapTable($blueprint) . ' ' . implode(', ', $columns);
	}

	public function compilePrimary(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$command->name(null);
		return $this->compileKey($blueprint, $command, 'primary key');
	}

	public function compileUnique(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return $this->compileKey($blueprint, $command, 'unique');
	}

	public function compileIndex(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return $this->compileKey($blueprint, $command, 'index');
	}

	protected function compileKey(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command, $type)
	{
		return sprintf('alter table %s add %s %s%s(%s)', $this->wrapTable($blueprint), $type, $this->wrap($command->index), $command->algorithm ? ' using ' . $command->algorithm : '', $this->columnize($command->columns));
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
		$columns = $this->prefixArray('drop', $this->wrapArray($command->columns));
		return 'alter table ' . $this->wrapTable($blueprint) . ' ' . implode(', ', $columns);
	}

	public function compileDropPrimary(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		return 'alter table ' . $this->wrapTable($blueprint) . ' drop primary key';
	}

	public function compileDropUnique(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$index = $this->wrap($command->index);
		return 'alter table ' . $this->wrapTable($blueprint) . ' drop index ' . $index;
	}

	public function compileDropIndex(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$index = $this->wrap($command->index);
		return 'alter table ' . $this->wrapTable($blueprint) . ' drop index ' . $index;
	}

	public function compileDropForeign(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$index = $this->wrap($command->index);
		return 'alter table ' . $this->wrapTable($blueprint) . ' drop foreign key ' . $index;
	}

	public function compileRename(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command)
	{
		$from = $this->wrapTable($blueprint);
		return 'rename table ' . $from . ' to ' . $this->wrapTable($command->to);
	}

	public function compileEnableForeignKeyConstraints()
	{
		return 'SET FOREIGN_KEY_CHECKS=1;';
	}

	public function compileDisableForeignKeyConstraints()
	{
		return 'SET FOREIGN_KEY_CHECKS=0;';
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
		return 'mediumtext';
	}

	protected function typeLongText(\Illuminate\Support\Fluent $column)
	{
		return 'longtext';
	}

	protected function typeBigInteger(\Illuminate\Support\Fluent $column)
	{
		return 'bigint';
	}

	protected function typeInteger(\Illuminate\Support\Fluent $column)
	{
		return 'int';
	}

	protected function typeMediumInteger(\Illuminate\Support\Fluent $column)
	{
		return 'mediumint';
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
		return $this->typeDouble($column);
	}

	protected function typeDouble(\Illuminate\Support\Fluent $column)
	{
		if ($column->total && $column->places) {
			return 'double(' . $column->total . ', ' . $column->places . ')';
		}

		return 'double';
	}

	protected function typeDecimal(\Illuminate\Support\Fluent $column)
	{
		return 'decimal(' . $column->total . ', ' . $column->places . ')';
	}

	protected function typeBoolean(\Illuminate\Support\Fluent $column)
	{
		return 'tinyint(1)';
	}

	protected function typeEnum(\Illuminate\Support\Fluent $column)
	{
		return 'enum(\'' . implode('\', \'', $column->allowed) . '\')';
	}

	protected function typeJson(\Illuminate\Support\Fluent $column)
	{
		return 'json';
	}

	protected function typeJsonb(\Illuminate\Support\Fluent $column)
	{
		return 'json';
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
			return 'timestamp default CURRENT_TIMESTAMP';
		}

		return 'timestamp';
	}

	protected function typeTimestampTz(\Illuminate\Support\Fluent $column)
	{
		if ($column->useCurrent) {
			return 'timestamp default CURRENT_TIMESTAMP';
		}

		return 'timestamp';
	}

	protected function typeBinary(\Illuminate\Support\Fluent $column)
	{
		return 'blob';
	}

	protected function typeUuid(\Illuminate\Support\Fluent $column)
	{
		return 'char(36)';
	}

	protected function typeIpAddress(\Illuminate\Support\Fluent $column)
	{
		return 'varchar(45)';
	}

	protected function typeMacAddress(\Illuminate\Support\Fluent $column)
	{
		return 'varchar(17)';
	}

	protected function modifyVirtualAs(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $column)
	{
		if (!is_null($column->virtualAs)) {
			return ' as (' . $column->virtualAs . ')';
		}
	}

	protected function modifyStoredAs(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $column)
	{
		if (!is_null($column->storedAs)) {
			return ' as (' . $column->storedAs . ') stored';
		}
	}

	protected function modifyUnsigned(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $column)
	{
		if ($column->unsigned) {
			return ' unsigned';
		}
	}

	protected function modifyCharset(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $column)
	{
		if (!is_null($column->charset)) {
			return ' character set ' . $column->charset;
		}
	}

	protected function modifyCollate(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $column)
	{
		if (!is_null($column->collation)) {
			return ' collate ' . $column->collation;
		}
	}

	protected function modifyNullable(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $column)
	{
		if (is_null($column->virtualAs) && is_null($column->storedAs)) {
			return $column->nullable ? ' null' : ' not null';
		}
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
			return ' auto_increment primary key';
		}
	}

	protected function modifyFirst(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $column)
	{
		if (!is_null($column->first)) {
			return ' first';
		}
	}

	protected function modifyAfter(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $column)
	{
		if (!is_null($column->after)) {
			return ' after ' . $this->wrap($column->after);
		}
	}

	protected function modifyComment(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $column)
	{
		if (!is_null($column->comment)) {
			return ' comment \'' . $column->comment . '\'';
		}
	}

	protected function wrapValue($value)
	{
		if ($value !== '*') {
			return '`' . str_replace('`', '``', $value) . '`';
		}

		return $value;
	}
}

?>
