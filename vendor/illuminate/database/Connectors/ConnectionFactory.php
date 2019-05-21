<?php
//cgxlm
namespace Illuminate\Database\Connectors;

class ConnectionFactory
{
	/**
     * The IoC container instance.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
	protected $container;

	public function __construct(\Illuminate\Contracts\Container\Container $container)
	{
		$this->container = $container;
	}

	public function make(array $config, $name = NULL)
	{
		$config = $this->parseConfig($config, $name);

		if (isset($config['read'])) {
			return $this->createReadWriteConnection($config);
		}

		return $this->createSingleConnection($config);
	}

	protected function parseConfig(array $config, $name)
	{
		return \Illuminate\Support\Arr::add(\Illuminate\Support\Arr::add($config, 'prefix', ''), 'name', $name);
	}

	protected function createSingleConnection(array $config)
	{
		$pdo = $this->createPdoResolver($config);
		return $this->createConnection($config['driver'], $pdo, $config['database'], $config['prefix'], $config);
	}

	protected function createReadWriteConnection(array $config)
	{
		$connection = $this->createSingleConnection($this->getWriteConfig($config));
		return $connection->setReadPdo($this->createReadPdo($config));
	}

	protected function createReadPdo(array $config)
	{
		return $this->createPdoResolver($this->getReadConfig($config));
	}

	protected function getReadConfig(array $config)
	{
		return $this->mergeReadWriteConfig($config, $this->getReadWriteConfig($config, 'read'));
	}

	protected function getWriteConfig(array $config)
	{
		return $this->mergeReadWriteConfig($config, $this->getReadWriteConfig($config, 'write'));
	}

	protected function getReadWriteConfig(array $config, $type)
	{
		return isset($config[$type][0]) ? $config[$type][array_rand($config[$type])] : $config[$type];
	}

	protected function mergeReadWriteConfig(array $config, array $merge)
	{
		return \Illuminate\Support\Arr::except(array_merge($config, $merge), array('read', 'write'));
	}

	protected function createPdoResolver(array $config)
	{
		return array_key_exists('host', $config) ? $this->createPdoResolverWithHosts($config) : $this->createPdoResolverWithoutHosts($config);
	}

	protected function createPdoResolverWithHosts(array $config)
	{
		return function() use($config) {
			foreach (\Illuminate\Support\Arr::shuffle($hosts = $this->parseHosts($config)) as $key => $host) {
				$config['host'] = $host;

				try {
					return $this->createConnector($config)->connect($config);
				}
				catch (\PDOException $e) {
					if (((count($hosts) - 1) === $key) && $this->container->bound('Illuminate\\Contracts\\Debug\\ExceptionHandler')) {
						$this->container->make('Illuminate\\Contracts\\Debug\\ExceptionHandler')->report($e);
					}
				}
			}

			throw $e;
		};
	}

	protected function parseHosts(array $config)
	{
		$hosts = array_wrap($config['host']);

		if (empty($hosts)) {
			throw new \InvalidArgumentException('Database hosts array is empty.');
		}

		return $hosts;
	}

	protected function createPdoResolverWithoutHosts(array $config)
	{
		return function() use($config) {
			return $this->createConnector($config)->connect($config);
		};
	}

	public function createConnector(array $config)
	{
		if (!isset($config['driver'])) {
			throw new \InvalidArgumentException('A driver must be specified.');
		}

		if ($this->container->bound($key = 'db.connector.' . $config['driver'])) {
			return $this->container->make($key);
		}

		switch ($config['driver']) {
		case 'mysql':
			return new MySqlConnector();
		case 'pgsql':
			return new PostgresConnector();
		case 'sqlite':
			return new SQLiteConnector();
		case 'sqlsrv':
			return new SqlServerConnector();
		}

		throw new \InvalidArgumentException('Unsupported driver [' . $config['driver'] . ']');
	}

	protected function createConnection($driver, $connection, $database, $prefix = '', array $config = array())
	{
		if ($resolver = \Illuminate\Database\Connection::getResolver($driver)) {
			return $resolver($connection, $database, $prefix, $config);
		}

		switch ($driver) {
		case 'mysql':
			return new \Illuminate\Database\MySqlConnection($connection, $database, $prefix, $config);
		case 'pgsql':
			return new \Illuminate\Database\PostgresConnection($connection, $database, $prefix, $config);
		case 'sqlite':
			return new \Illuminate\Database\SQLiteConnection($connection, $database, $prefix, $config);
		case 'sqlsrv':
			return new \Illuminate\Database\SqlServerConnection($connection, $database, $prefix, $config);
		}

		throw new \InvalidArgumentException('Unsupported driver [' . $driver . ']');
	}
}


?>
