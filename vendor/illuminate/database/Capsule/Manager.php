<?php
//cgxlm
namespace Illuminate\Database\Capsule;

class Manager
{
	use \Illuminate\Support\Traits\CapsuleManagerTrait;

	/**
     * The database manager instance.
     *
     * @var \Illuminate\Database\DatabaseManager
     */
	protected $manager;

	public function __construct(\Illuminate\Container\Container $container = NULL)
	{
		$this->setupContainer($container ?: new \Illuminate\Container\Container());
		$this->setupDefaultConfiguration();
		$this->setupManager();
	}

	protected function setupDefaultConfiguration()
	{
		$this->container['config']['database.fetch'] = \PDO::FETCH_OBJ;
		$this->container['config']['database.default'] = 'default';
	}

	protected function setupManager()
	{
		$factory = new \Illuminate\Database\Connectors\ConnectionFactory($this->container);
		$this->manager = new \Illuminate\Database\DatabaseManager($this->container, $factory);
	}

	static public function connection($connection = NULL)
	{
		return static::$instance->getConnection($connection);
	}

	static public function table($table, $connection = NULL)
	{
		return static::$instance->connection($connection)->table($table);
	}

	static public function schema($connection = NULL)
	{
		return static::$instance->connection($connection)->getSchemaBuilder();
	}

	public function getConnection($name = NULL)
	{
		return $this->manager->connection($name);
	}

	public function addConnection(array $config, $name = 'default')
	{
		$connections = $this->container['config']['database.connections'];
		$connections[$name] = $config;
		$this->container['config']['database.connections'] = $connections;
	}

	public function bootEloquent()
	{
		\Illuminate\Database\Eloquent\Model::setConnectionResolver($this->manager);

		if ($dispatcher = $this->getEventDispatcher()) {
			\Illuminate\Database\Eloquent\Model::setEventDispatcher($dispatcher);
		}
	}

	public function setFetchMode($fetchMode)
	{
		$this->container['config']['database.fetch'] = $fetchMode;
		return $this;
	}

	public function getDatabaseManager()
	{
		return $this->manager;
	}

	public function getEventDispatcher()
	{
		if ($this->container->bound('events')) {
			return $this->container['events'];
		}
	}

	public function setEventDispatcher(\Illuminate\Contracts\Events\Dispatcher $dispatcher)
	{
		$this->container->instance('events', $dispatcher);
	}

	static public function __callStatic($method, $parameters)
	{
		return static::connection()->$method(...$parameters);
	}
}

?>
