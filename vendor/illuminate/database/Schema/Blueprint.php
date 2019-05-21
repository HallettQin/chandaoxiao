<?php
//cgxlm
namespace Illuminate\Database\Schema;

class Blueprint
{
	/**
     * The table the blueprint describes.
     *
     * @var string
     */
	protected $table;
	/**
     * The columns that should be added to the table.
     *
     * @var array
     */
	protected $columns = array();
	/**
     * The commands that should be run for the table.
     *
     * @var array
     */
	protected $commands = array();
	/**
     * The storage engine that should be used for the table.
     *
     * @var string
     */
	public $engine;
	/**
     * The default character set that should be used for the table.
     */
	public $charset;
	/**
     * The collation that should be used for the table.
     */
	public $collation;
	/**
     * Whether to make the table temporary.
     *
     * @var bool
     */
	public $temporary = false;

	public function __construct($table, \Closure $callback = NULL)
	{
		$this->table = $table;

		if (!is_null($callback)) {
			$callback($this);
		}
	}

	public function build(\Illuminate\Database\Connection $connection, Grammars\Grammar $grammar)
	{
		foreach ($this->toSql($connection, $grammar) as $statement) {
			$connection->statement($statement);
		}
	}

	public function toSql(\Illuminate\Database\Connection $connection, Grammars\Grammar $grammar)
	{
		$this->addImpliedCommands();
		$statements = array();

		foreach ($this->commands as $command) {
			$method = 'compile' . ucfirst($command->name);

			if (method_exists($grammar, $method)) {
				if (!is_null($sql = $grammar->$method($this, $command, $connection))) {
					$statements = array_merge($statements, (array) $sql);
				}
			}
		}

		return $statements;
	}

	protected function addImpliedCommands()
	{
		if ((0 < count($this->getAddedColumns())) && !$this->creating()) {
			array_unshift($this->commands, $this->createCommand('add'));
		}

		if ((0 < count($this->getChangedColumns())) && !$this->creating()) {
			array_unshift($this->commands, $this->createCommand('change'));
		}

		$this->addFluentIndexes();
	}

	protected function addFluentIndexes()
	{
		foreach ($this->columns as $column) {
			foreach (array('primary', 'unique', 'index') as $index) {
				if ($column->$index === true) {
					$this->$index($column->name);
					continue 2;
				}
				else if (isset($column->$index)) {
					$this->$index($column->name, $column->$index);
					continue 2;
				}
			}
		}
	}

	protected function creating()
	{
		return collect($this->commands)->contains(function($command) {
			return $command->name == 'create';
		});
	}

	public function create()
	{
		return $this->addCommand('create');
	}

	public function temporary()
	{
		$this->temporary = true;
	}

	public function drop()
	{
		return $this->addCommand('drop');
	}

	public function dropIfExists()
	{
		return $this->addCommand('dropIfExists');
	}

	public function dropColumn($columns)
	{
		$columns = (is_array($columns) ? $columns : (array) func_get_args());
		return $this->addCommand('dropColumn', compact('columns'));
	}

	public function renameColumn($from, $to)
	{
		return $this->addCommand('renameColumn', compact('from', 'to'));
	}

	public function dropPrimary($index = NULL)
	{
		return $this->dropIndexCommand('dropPrimary', 'primary', $index);
	}

	public function dropUnique($index)
	{
		return $this->dropIndexCommand('dropUnique', 'unique', $index);
	}

	public function dropIndex($index)
	{
		return $this->dropIndexCommand('dropIndex', 'index', $index);
	}

	public function dropForeign($index)
	{
		return $this->dropIndexCommand('dropForeign', 'foreign', $index);
	}

	public function dropTimestamps()
	{
		$this->dropColumn('created_at', 'updated_at');
	}

	public function dropTimestampsTz()
	{
		$this->dropTimestamps();
	}

	public function dropSoftDeletes()
	{
		$this->dropColumn('deleted_at');
	}

	public function dropSoftDeletesTz()
	{
		$this->dropSoftDeletes();
	}

	public function dropRememberToken()
	{
		$this->dropColumn('remember_token');
	}

	public function rename($to)
	{
		return $this->addCommand('rename', compact('to'));
	}

	public function primary($columns, $name = NULL, $algorithm = NULL)
	{
		return $this->indexCommand('primary', $columns, $name, $algorithm);
	}

	public function unique($columns, $name = NULL, $algorithm = NULL)
	{
		return $this->indexCommand('unique', $columns, $name, $algorithm);
	}

	public function index($columns, $name = NULL, $algorithm = NULL)
	{
		return $this->indexCommand('index', $columns, $name, $algorithm);
	}

	public function foreign($columns, $name = NULL)
	{
		return $this->indexCommand('foreign', $columns, $name);
	}

	public function increments($column)
	{
		return $this->unsignedInteger($column, true);
	}

	public function tinyIncrements($column)
	{
		return $this->unsignedTinyInteger($column, true);
	}

	public function smallIncrements($column)
	{
		return $this->unsignedSmallInteger($column, true);
	}

	public function mediumIncrements($column)
	{
		return $this->unsignedMediumInteger($column, true);
	}

	public function bigIncrements($column)
	{
		return $this->unsignedBigInteger($column, true);
	}

	public function char($column, $length = NULL)
	{
		$length = $length ?: Builder::$defaultStringLength;
		return $this->addColumn('char', $column, compact('length'));
	}

	public function string($column, $length = NULL)
	{
		$length = $length ?: Builder::$defaultStringLength;
		return $this->addColumn('string', $column, compact('length'));
	}

	public function text($column)
	{
		return $this->addColumn('text', $column);
	}

	public function mediumText($column)
	{
		return $this->addColumn('mediumText', $column);
	}

	public function longText($column)
	{
		return $this->addColumn('longText', $column);
	}

	public function integer($column, $autoIncrement = false, $unsigned = false)
	{
		return $this->addColumn('integer', $column, compact('autoIncrement', 'unsigned'));
	}

	public function tinyInteger($column, $autoIncrement = false, $unsigned = false)
	{
		return $this->addColumn('tinyInteger', $column, compact('autoIncrement', 'unsigned'));
	}

	public function smallInteger($column, $autoIncrement = false, $unsigned = false)
	{
		return $this->addColumn('smallInteger', $column, compact('autoIncrement', 'unsigned'));
	}

	public function mediumInteger($column, $autoIncrement = false, $unsigned = false)
	{
		return $this->addColumn('mediumInteger', $column, compact('autoIncrement', 'unsigned'));
	}

	public function bigInteger($column, $autoIncrement = false, $unsigned = false)
	{
		return $this->addColumn('bigInteger', $column, compact('autoIncrement', 'unsigned'));
	}

	public function unsignedInteger($column, $autoIncrement = false)
	{
		return $this->integer($column, $autoIncrement, true);
	}

	public function unsignedTinyInteger($column, $autoIncrement = false)
	{
		return $this->tinyInteger($column, $autoIncrement, true);
	}

	public function unsignedSmallInteger($column, $autoIncrement = false)
	{
		return $this->smallInteger($column, $autoIncrement, true);
	}

	public function unsignedMediumInteger($column, $autoIncrement = false)
	{
		return $this->mediumInteger($column, $autoIncrement, true);
	}

	public function unsignedBigInteger($column, $autoIncrement = false)
	{
		return $this->bigInteger($column, $autoIncrement, true);
	}

	public function float($column, $total = 8, $places = 2)
	{
		return $this->addColumn('float', $column, compact('total', 'places'));
	}

	public function double($column, $total = NULL, $places = NULL)
	{
		return $this->addColumn('double', $column, compact('total', 'places'));
	}

	public function decimal($column, $total = 8, $places = 2)
	{
		return $this->addColumn('decimal', $column, compact('total', 'places'));
	}

	public function boolean($column)
	{
		return $this->addColumn('boolean', $column);
	}

	public function enum($column, array $allowed)
	{
		return $this->addColumn('enum', $column, compact('allowed'));
	}

	public function json($column)
	{
		return $this->addColumn('json', $column);
	}

	public function jsonb($column)
	{
		return $this->addColumn('jsonb', $column);
	}

	public function date($column)
	{
		return $this->addColumn('date', $column);
	}

	public function dateTime($column)
	{
		return $this->addColumn('dateTime', $column);
	}

	public function dateTimeTz($column)
	{
		return $this->addColumn('dateTimeTz', $column);
	}

	public function time($column)
	{
		return $this->addColumn('time', $column);
	}

	public function timeTz($column)
	{
		return $this->addColumn('timeTz', $column);
	}

	public function timestamp($column)
	{
		return $this->addColumn('timestamp', $column);
	}

	public function timestampTz($column)
	{
		return $this->addColumn('timestampTz', $column);
	}

	public function timestamps()
	{
		$this->timestamp('created_at')->nullable();
		$this->timestamp('updated_at')->nullable();
	}

	public function nullableTimestamps()
	{
		$this->timestamps();
	}

	public function timestampsTz()
	{
		$this->timestampTz('created_at')->nullable();
		$this->timestampTz('updated_at')->nullable();
	}

	public function softDeletes($column = 'deleted_at')
	{
		return $this->timestamp($column)->nullable();
	}

	public function softDeletesTz()
	{
		return $this->timestampTz('deleted_at')->nullable();
	}

	public function binary($column)
	{
		return $this->addColumn('binary', $column);
	}

	public function uuid($column)
	{
		return $this->addColumn('uuid', $column);
	}

	public function ipAddress($column)
	{
		return $this->addColumn('ipAddress', $column);
	}

	public function macAddress($column)
	{
		return $this->addColumn('macAddress', $column);
	}

	public function morphs($name, $indexName = NULL)
	{
		$this->unsignedInteger($name . '_id');
		$this->string($name . '_type');
		$this->index(array($name . '_id', $name . '_type'), $indexName);
	}

	public function nullableMorphs($name, $indexName = NULL)
	{
		$this->unsignedInteger($name . '_id')->nullable();
		$this->string($name . '_type')->nullable();
		$this->index(array($name . '_id', $name . '_type'), $indexName);
	}

	public function rememberToken()
	{
		return $this->string('remember_token', 100)->nullable();
	}

	protected function indexCommand($type, $columns, $index, $algorithm = NULL)
	{
		$columns = (array) $columns;
		$index = $index ?: $this->createIndexName($type, $columns);
		return $this->addCommand($type, compact('index', 'columns', 'algorithm'));
	}

	protected function dropIndexCommand($command, $type, $index)
	{
		$columns = array();

		if (is_array($index)) {
			$index = $this->createIndexName($type, $columns = $index);
		}

		return $this->indexCommand($command, $columns, $index);
	}

	protected function createIndexName($type, array $columns)
	{
		$index = strtolower($this->table . '_' . implode('_', $columns) . '_' . $type);
		return str_replace(array('-', '.'), '_', $index);
	}

	public function addColumn($type, $name, array $parameters = array())
	{
		$this->columns[] = $column = new \Illuminate\Support\Fluent(array_merge(compact('type', 'name'), $parameters));
		return $column;
	}

	public function removeColumn($name)
	{
		$this->columns = array_values(array_filter($this->columns, function($c) use($name) {
			return $c['attributes']['name'] != $name;
		}));
		return $this;
	}

	protected function addCommand($name, array $parameters = array())
	{
		$this->commands[] = $command = $this->createCommand($name, $parameters);
		return $command;
	}

	protected function createCommand($name, array $parameters = array())
	{
		return new \Illuminate\Support\Fluent(array_merge(compact('name'), $parameters));
	}

	public function getTable()
	{
		return $this->table;
	}

	public function getColumns()
	{
		return $this->columns;
	}

	public function getCommands()
	{
		return $this->commands;
	}

	public function getAddedColumns()
	{
		return array_filter($this->columns, function($column) {
			return !$column->change;
		});
	}

	public function getChangedColumns()
	{
		return array_filter($this->columns, function($column) {
			return (bool) $column->change;
		});
	}
}


?>
