<?php
//cgxlm
namespace Illuminate\Database\Migrations;

class Migrator
{
	/**
     * The migration repository implementation.
     *
     * @var \Illuminate\Database\Migrations\MigrationRepositoryInterface
     */
	protected $repository;
	/**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
	protected $files;
	/**
     * The connection resolver instance.
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
	protected $resolver;
	/**
     * The name of the default connection.
     *
     * @var string
     */
	protected $connection;
	/**
     * The notes for the current operation.
     *
     * @var array
     */
	protected $notes = array();
	/**
     * The paths to all of the migration files.
     *
     * @var array
     */
	protected $paths = array();

	public function __construct(MigrationRepositoryInterface $repository, \Illuminate\Database\ConnectionResolverInterface $resolver, \Illuminate\Filesystem\Filesystem $files)
	{
		$this->files = $files;
		$this->resolver = $resolver;
		$this->repository = $repository;
	}

	public function run($paths = array(), array $options = array())
	{
		$this->notes = array();
		$files = $this->getMigrationFiles($paths);
		$this->requireFiles($migrations = $this->pendingMigrations($files, $this->repository->getRan()));
		$this->runPending($migrations, $options);
		return $migrations;
	}

	protected function pendingMigrations($files, $ran)
	{
		return \Illuminate\Support\Collection::make($files)->reject(function($file) use($ran) {
			return in_array($this->getMigrationName($file), $ran);
		})->values()->all();
	}

	public function runPending(array $migrations, array $options = array())
	{
		if (count($migrations) == 0) {
			$this->note('<info>Nothing to migrate.</info>');
			return NULL;
		}

		$batch = $this->repository->getNextBatchNumber();
		$pretend = \Illuminate\Support\Arr::get($options, 'pretend', false);
		$step = \Illuminate\Support\Arr::get($options, 'step', false);

		foreach ($migrations as $file) {
			$this->runUp($file, $batch, $pretend);

			if ($step) {
				$batch++;
			}
		}
	}

	protected function runUp($file, $batch, $pretend)
	{
		$migration = $this->resolve($name = $this->getMigrationName($file));

		if ($pretend) {
			return $this->pretendToRun($migration, 'up');
		}

		$this->note('<comment>Migrating:</comment> ' . $name);
		$this->runMigration($migration, 'up');
		$this->repository->log($name, $batch);
		$this->note('<info>Migrated:</info>  ' . $name);
	}

	public function rollback($paths = array(), array $options = array())
	{
		$this->notes = array();
		$migrations = $this->getMigrationsForRollback($options);

		if (count($migrations) === 0) {
			$this->note('<info>Nothing to rollback.</info>');
			return array();
		}
		else {
			return $this->rollbackMigrations($migrations, $paths, $options);
		}
	}

	protected function getMigrationsForRollback(array $options)
	{
		if (0 < ($steps = \Illuminate\Support\Arr::get($options, 'step', 0))) {
			return $this->repository->getMigrations($steps);
		}
		else {
			return $this->repository->getLast();
		}
	}

	protected function rollbackMigrations(array $migrations, $paths, array $options)
	{
		$rolledBack = array();
		$this->requireFiles($files = $this->getMigrationFiles($paths));

		foreach ($migrations as $migration) {
			$migration = (object) $migration;
			$rolledBack[] = $files[$migration->migration];
			$this->runDown($files[$migration->migration], $migration, \Illuminate\Support\Arr::get($options, 'pretend', false));
		}

		return $rolledBack;
	}

	public function reset($paths = array(), $pretend = false)
	{
		$this->notes = array();
		$migrations = array_reverse($this->repository->getRan());

		if (count($migrations) === 0) {
			$this->note('<info>Nothing to rollback.</info>');
			return array();
		}
		else {
			return $this->resetMigrations($migrations, $paths, $pretend);
		}
	}

	protected function resetMigrations(array $migrations, array $paths, $pretend = false)
	{
		$migrations = collect($migrations)->map(function($m) {
			return (object) array('migration' => $m);
		})->all();
		return $this->rollbackMigrations($migrations, $paths, compact('pretend'));
	}

	protected function runDown($file, $migration, $pretend)
	{
		$instance = $this->resolve($name = $this->getMigrationName($file));
		$this->note('<comment>Rolling back:</comment> ' . $name);

		if ($pretend) {
			return $this->pretendToRun($instance, 'down');
		}

		$this->runMigration($instance, 'down');
		$this->repository->delete($migration);
		$this->note('<info>Rolled back:</info>  ' . $name);
	}

	protected function runMigration($migration, $method)
	{
		$connection = $this->resolveConnection($migration->getConnection());
		$callback = function() use($migration, $method) {
			if (method_exists($migration, $method)) {
				$migration->$method();
			}
		};
		$this->getSchemaGrammar($connection)->supportsSchemaTransactions() ? $connection->transaction($callback) : $callback();
	}

	protected function pretendToRun($migration, $method)
	{
		foreach ($this->getQueries($migration, $method) as $query) {
			$name = get_class($migration);
			$this->note('<info>' . $name . ':</info> ' . $query['query']);
		}
	}

	protected function getQueries($migration, $method)
	{
		$db = $this->resolveConnection($connection = $migration->getConnection());
		return $db->pretend(function() use($migration, $method) {
			if (method_exists($migration, $method)) {
				$migration->$method();
			}
		});
	}

	public function resolve($file)
	{
		$class = \Illuminate\Support\Str::studly(implode('_', array_slice(explode('_', $file), 4)));
		return new $class();
	}

	public function getMigrationFiles($paths)
	{
		return \Illuminate\Support\Collection::make($paths)->flatMap(function($path) {
			return $this->files->glob($path . '/*_*.php');
		})->filter()->sortBy(function($file) {
			return $this->getMigrationName($file);
		})->values()->keyBy(function($file) {
			return $this->getMigrationName($file);
		})->all();
	}

	public function requireFiles(array $files)
	{
		foreach ($files as $file) {
			$this->files->requireOnce($file);
		}
	}

	public function getMigrationName($path)
	{
		return str_replace('.php', '', basename($path));
	}

	public function path($path)
	{
		$this->paths = array_unique(array_merge($this->paths, array($path)));
	}

	public function paths()
	{
		return $this->paths;
	}

	public function setConnection($name)
	{
		if (!is_null($name)) {
			$this->resolver->setDefaultConnection($name);
		}

		$this->repository->setSource($name);
		$this->connection = $name;
	}

	public function resolveConnection($connection)
	{
		return $this->resolver->connection($connection ?: $this->connection);
	}

	protected function getSchemaGrammar($connection)
	{
		if (is_null($grammar = $connection->getSchemaGrammar())) {
			$connection->useDefaultSchemaGrammar();
			$grammar = $connection->getSchemaGrammar();
		}

		return $grammar;
	}

	public function getRepository()
	{
		return $this->repository;
	}

	public function repositoryExists()
	{
		return $this->repository->repositoryExists();
	}

	public function getFilesystem()
	{
		return $this->files;
	}

	protected function note($message)
	{
		$this->notes[] = $message;
	}

	public function getNotes()
	{
		return $this->notes;
	}
}


?>
