<?php
//cgxlm
namespace Illuminate\Database;

class Connection implements ConnectionInterface
{
	use DetectsDeadlocks, DetectsLostConnections, Concerns\ManagesTransactions;

	/**
     * The active PDO connection.
     *
     * @var PDO
     */
	protected $pdo;
	/**
     * The active PDO connection used for reads.
     *
     * @var PDO
     */
	protected $readPdo;
	/**
     * The name of the connected database.
     *
     * @var string
     */
	protected $database;
	/**
     * The table prefix for the connection.
     *
     * @var string
     */
	protected $tablePrefix = '';
	/**
     * The database connection configuration options.
     *
     * @var array
     */
	protected $config = array();
	/**
     * The reconnector instance for the connection.
     *
     * @var callable
     */
	protected $reconnector;
	/**
     * The query grammar implementation.
     *
     * @var \Illuminate\Database\Query\Grammars\Grammar
     */
	protected $queryGrammar;
	/**
     * The schema grammar implementation.
     *
     * @var \Illuminate\Database\Schema\Grammars\Grammar
     */
	protected $schemaGrammar;
	/**
     * The query post processor implementation.
     *
     * @var \Illuminate\Database\Query\Processors\Processor
     */
	protected $postProcessor;
	/**
     * The event dispatcher instance.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
	protected $events;
	/**
     * The default fetch mode of the connection.
     *
     * @var int
     */
	protected $fetchMode = \PDO::FETCH_OBJ;
	/**
     * The number of active transactions.
     *
     * @var int
     */
	protected $transactions = 0;
	/**
     * All of the queries run against the connection.
     *
     * @var array
     */
	protected $queryLog = array();
	/**
     * Indicates whether queries are being logged.
     *
     * @var bool
     */
	protected $loggingQueries = false;
	/**
     * Indicates if the connection is in a "dry run".
     *
     * @var bool
     */
	protected $pretending = false;
	/**
     * The instance of Doctrine connection.
     *
     * @var \Doctrine\DBAL\Connection
     */
	protected $doctrineConnection;
	/**
     * The connection resolvers.
     *
     * @var array
     */
	static protected $resolvers = array();

	public function __construct($pdo, $database = '', $tablePrefix = '', array $config = array())
	{
		$this->pdo = $pdo;
		$this->database = $database;
		$this->tablePrefix = $tablePrefix;
		$this->config = $config;
		$this->useDefaultQueryGrammar();
		$this->useDefaultPostProcessor();
	}

	public function useDefaultQueryGrammar()
	{
		$this->queryGrammar = $this->getDefaultQueryGrammar();
	}

	protected function getDefaultQueryGrammar()
	{
		return new Query\Grammars\Grammar();
	}

	public function useDefaultSchemaGrammar()
	{
		$this->schemaGrammar = $this->getDefaultSchemaGrammar();
	}

	protected function getDefaultSchemaGrammar()
	{
	}

	public function useDefaultPostProcessor()
	{
		$this->postProcessor = $this->getDefaultPostProcessor();
	}

	protected function getDefaultPostProcessor()
	{
		return new Query\Processors\Processor();
	}

	public function getSchemaBuilder()
	{
		if (is_null($this->schemaGrammar)) {
			$this->useDefaultSchemaGrammar();
		}

		return new Schema\Builder($this);
	}

	public function table($table)
	{
		return $this->query()->from($table);
	}

	public function query()
	{
		return new Query\Builder($this, $this->getQueryGrammar(), $this->getPostProcessor());
	}

	public function selectOne($query, $bindings = array(), $useReadPdo = true)
	{
		$records = $this->select($query, $bindings, $useReadPdo);
		return array_shift($records);
	}

	public function selectFromWriteConnection($query, $bindings = array())
	{
		return $this->select($query, $bindings, false);
	}

	public function select($query, $bindings = array(), $useReadPdo = true)
	{
		return $this->run($query, $bindings, function($query, $bindings) use($useReadPdo) {
			if ($this->pretending()) {
				return array();
			}

			$statement = $this->prepared($this->getPdoForSelect($useReadPdo)->prepare($query));
			$this->bindValues($statement, $this->prepareBindings($bindings));
			$statement->execute();
			return $statement->fetchAll();
		});
	}

	public function cursor($query, $bindings = array(), $useReadPdo = true)
	{
		$statement = $this->run($query, $bindings, function($query, $bindings) use($useReadPdo) {
			if ($this->pretending()) {
				return array();
			}

			$statement = $this->prepared($this->getPdoForSelect($useReadPdo)->prepare($query));
			$this->bindValues($statement, $this->prepareBindings($bindings));
			$statement->execute();
			return $statement;
		});

		while ($record = $statement->fetch()) {
			yield $record;
		}
	}

	protected function prepared(\PDOStatement $statement)
	{
		$statement->setFetchMode($this->fetchMode);
		$this->event(new Events\StatementPrepared($this, $statement));
		return $statement;
	}

	protected function getPdoForSelect($useReadPdo = true)
	{
		return $useReadPdo ? $this->getReadPdo() : $this->getPdo();
	}

	public function insert($query, $bindings = array())
	{
		return $this->statement($query, $bindings);
	}

	public function update($query, $bindings = array())
	{
		return $this->affectingStatement($query, $bindings);
	}

	public function delete($query, $bindings = array())
	{
		return $this->affectingStatement($query, $bindings);
	}

	public function statement($query, $bindings = array())
	{
		return $this->run($query, $bindings, function($query, $bindings) {
			if ($this->pretending()) {
				return true;
			}

			$statement = $this->getPdo()->prepare($query);
			$this->bindValues($statement, $this->prepareBindings($bindings));
			return $statement->execute();
		});
	}

	public function affectingStatement($query, $bindings = array())
	{
		return $this->run($query, $bindings, function($query, $bindings) {
			if ($this->pretending()) {
				return 0;
			}

			$statement = $this->getPdo()->prepare($query);
			$this->bindValues($statement, $this->prepareBindings($bindings));
			$statement->execute();
			return $statement->rowCount();
		});
	}

	public function unprepared($query)
	{
		return $this->run($query, array(), function($query) {
			if ($this->pretending()) {
				return true;
			}

			return (bool) $this->getPdo()->exec($query);
		});
	}

	public function pretend(\Closure $callback)
	{
		return $this->withFreshQueryLog(function() use($callback) {
			$this->pretending = true;
			$callback($this);
			$this->pretending = false;
			return $this->queryLog;
		});
	}

	protected function withFreshQueryLog($callback)
	{
		$loggingQueries = $this->loggingQueries;
		$this->enableQueryLog();
		$this->queryLog = array();
		$result = $callback();
		$this->loggingQueries = $loggingQueries;
		return $result;
	}

	public function bindValues($statement, $bindings)
	{
		foreach ($bindings as $key => $value) {
			$statement->bindValue(is_string($key) ? $key : $key + 1, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
		}
	}

	public function prepareBindings(array $bindings)
	{
		$grammar = $this->getQueryGrammar();

		foreach ($bindings as $key => $value) {
			if ($value instanceof \DateTimeInterface) {
				$bindings[$key] = $value->format($grammar->getDateFormat());
			}
			else if ($value === false) {
				$bindings[$key] = 0;
			}
		}

		return $bindings;
	}

	protected function run($query, $bindings, \Closure $callback)
	{
		$this->reconnectIfMissingConnection();
		$start = microtime(true);

		try {
			$result = $this->runQueryCallback($query, $bindings, $callback);
		}
		catch (QueryException $e) {
			$result = $this->handleQueryException($e, $query, $bindings, $callback);
		}

		$this->logQuery($query, $bindings, $this->getElapsedTime($start));
		return $result;
	}

	protected function runQueryCallback($query, $bindings, \Closure $callback)
	{
		try {
			$result = $callback($query, $bindings);
		}
		catch (\Exception $e) {
			throw new QueryException($query, $this->prepareBindings($bindings), $e);
		}

		return $result;
	}

	public function logQuery($query, $bindings, $time = NULL)
	{
		$this->event(new Events\QueryExecuted($query, $bindings, $time, $this));

		if ($this->loggingQueries) {
			$this->queryLog[] = compact('query', 'bindings', 'time');
		}
	}

	protected function getElapsedTime($start)
	{
		return round((microtime(true) - $start) * 1000, 2);
	}

	protected function handleQueryException($e, $query, $bindings, \Closure $callback)
	{
		if (1 <= $this->transactions) {
			throw $e;
		}

		return $this->tryAgainIfCausedByLostConnection($e, $query, $bindings, $callback);
	}

	protected function tryAgainIfCausedByLostConnection(QueryException $e, $query, $bindings, \Closure $callback)
	{
		if ($this->causedByLostConnection($e->getPrevious())) {
			$this->reconnect();
			return $this->runQueryCallback($query, $bindings, $callback);
		}

		throw $e;
	}

	public function reconnect()
	{
		if (is_callable($this->reconnector)) {
			return call_user_func($this->reconnector, $this);
		}

		throw new \LogicException('Lost connection and no reconnector available.');
	}

	protected function reconnectIfMissingConnection()
	{
		if (is_null($this->pdo)) {
			$this->reconnect();
		}
	}

	public function disconnect()
	{
		$this->setPdo(null)->setReadPdo(null);
	}

	public function listen(\Closure $callback)
	{
		if (isset($this->events)) {
			$this->events->listen('Illuminate\\Database\\Events\\QueryExecuted', $callback);
		}
	}

	protected function fireConnectionEvent($event)
	{
		if (!isset($this->events)) {
			return NULL;
		}

		switch ($event) {
		case 'beganTransaction':
			return $this->events->dispatch(new Events\TransactionBeginning($this));
		case 'committed':
			return $this->events->dispatch(new Events\TransactionCommitted($this));
		case 'rollingBack':
			return $this->events->dispatch(new Events\TransactionRolledBack($this));
		}
	}

	protected function event($event)
	{
		if (isset($this->events)) {
			$this->events->dispatch($event);
		}
	}

	public function raw($value)
	{
		return new Query\Expression($value);
	}

	public function isDoctrineAvailable()
	{
		return class_exists('Doctrine\\DBAL\\Connection');
	}

	public function getDoctrineColumn($table, $column)
	{
		$schema = $this->getDoctrineSchemaManager();
		return $schema->listTableDetails($table)->getColumn($column);
	}

	public function getDoctrineSchemaManager()
	{
		return $this->getDoctrineDriver()->getSchemaManager($this->getDoctrineConnection());
	}

	public function getDoctrineConnection()
	{
		if (is_null($this->doctrineConnection)) {
			$data = array('pdo' => $this->getPdo(), 'dbname' => $this->getConfig('database'));
			$this->doctrineConnection = new \Doctrine\DBAL\Connection($data, $this->getDoctrineDriver());
		}

		return $this->doctrineConnection;
	}

	public function getPdo()
	{
		if ($this->pdo instanceof \Closure) {
			return $this->pdo = call_user_func($this->pdo);
		}

		return $this->pdo;
	}

	public function getReadPdo()
	{
		if (1 <= $this->transactions) {
			return $this->getPdo();
		}

		if ($this->readPdo instanceof \Closure) {
			return $this->readPdo = call_user_func($this->readPdo);
		}

		return $this->readPdo ?: $this->getPdo();
	}

	public function setPdo($pdo)
	{
		$this->transactions = 0;
		$this->pdo = $pdo;
		return $this;
	}

	public function setReadPdo($pdo)
	{
		$this->readPdo = $pdo;
		return $this;
	}

	public function setReconnector( $reconnector)
	{
		$this->reconnector = $reconnector;
		return $this;
	}

	public function getName()
	{
		return $this->getConfig('name');
	}

	public function getConfig($option = NULL)
	{
		return \Illuminate\Support\Arr::get($this->config, $option);
	}

	public function getDriverName()
	{
		return $this->getConfig('driver');
	}

	public function getQueryGrammar()
	{
		return $this->queryGrammar;
	}

	public function setQueryGrammar(Query\Grammars\Grammar $grammar)
	{
		$this->queryGrammar = $grammar;
	}

	public function getSchemaGrammar()
	{
		return $this->schemaGrammar;
	}

	public function setSchemaGrammar(Schema\Grammars\Grammar $grammar)
	{
		$this->schemaGrammar = $grammar;
	}

	public function getPostProcessor()
	{
		return $this->postProcessor;
	}

	public function setPostProcessor(Query\Processors\Processor $processor)
	{
		$this->postProcessor = $processor;
	}

	public function getEventDispatcher()
	{
		return $this->events;
	}

	public function setEventDispatcher(\Illuminate\Contracts\Events\Dispatcher $events)
	{
		$this->events = $events;
	}

	public function pretending()
	{
		return $this->pretending === true;
	}

	public function getQueryLog()
	{
		return $this->queryLog;
	}

	public function flushQueryLog()
	{
		$this->queryLog = array();
	}

	public function enableQueryLog()
	{
		$this->loggingQueries = true;
	}

	public function disableQueryLog()
	{
		$this->loggingQueries = false;
	}

	public function logging()
	{
		return $this->loggingQueries;
	}

	public function getDatabaseName()
	{
		return $this->database;
	}

	public function setDatabaseName($database)
	{
		$this->database = $database;
	}

	public function getTablePrefix()
	{
		return $this->tablePrefix;
	}

	public function setTablePrefix($prefix)
	{
		$this->tablePrefix = $prefix;
		$this->getQueryGrammar()->setTablePrefix($prefix);
	}

	public function withTablePrefix(Grammar $grammar)
	{
		$grammar->setTablePrefix($this->tablePrefix);
		return $grammar;
	}

	static public function resolverFor($driver, \Closure $callback)
	{
		static::$resolvers[$driver] = $callback;
	}

	static public function getResolver($driver)
	{
		return isset(static::$resolvers[$driver]) ? static::$resolvers[$driver] : null;
	}
}

?>
