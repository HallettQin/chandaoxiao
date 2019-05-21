<?php
//cgxlm
namespace Illuminate\Database\Migrations;

class DatabaseMigrationRepository implements MigrationRepositoryInterface
{
	/**
     * The database connection resolver instance.
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
	protected $resolver;
	/**
     * The name of the migration table.
     *
     * @var string
     */
	protected $table;
	/**
     * The name of the database connection to use.
     *
     * @var string
     */
	protected $connection;

	public function __construct(\Illuminate\Database\ConnectionResolverInterface $resolver, $table)
	{
		$this->table = $table;
		$this->resolver = $resolver;
	}

	public function getRan()
	{
		return $this->table()->orderBy('batch', 'asc')->orderBy('migration', 'asc')->pluck('migration')->all();
	}

	public function getMigrations($steps)
	{
		$query = $this->table()->where('batch', '>=', '1');
		return $query->orderBy('migration', 'desc')->take($steps)->get()->all();
	}

	public function getLast()
	{
		$query = $this->table()->where('batch', $this->getLastBatchNumber());
		return $query->orderBy('migration', 'desc')->get()->all();
	}

	public function log($file, $batch)
	{
		$record = array('migration' => $file, 'batch' => $batch);
		$this->table()->insert($record);
	}

	public function delete($migration)
	{
		$this->table()->where('migration', $migration->migration)->delete();
	}

	public function getNextBatchNumber()
	{
		return $this->getLastBatchNumber() + 1;
	}

	public function getLastBatchNumber()
	{
		return $this->table()->max('batch');
	}

	public function createRepository()
	{
		$schema = $this->getConnection()->getSchemaBuilder();
		$schema->create($this->table, function($table) {
			$table->increments('id');
			$table->string('migration');
			$table->integer('batch');
		});
	}

	public function repositoryExists()
	{
		$schema = $this->getConnection()->getSchemaBuilder();
		return $schema->hasTable($this->table);
	}

	protected function table()
	{
		return $this->getConnection()->table($this->table)->useWritePdo();
	}

	public function getConnectionResolver()
	{
		return $this->resolver;
	}

	public function getConnection()
	{
		return $this->resolver->connection($this->connection);
	}

	public function setSource($name)
	{
		$this->connection = $name;
	}
}

?>
