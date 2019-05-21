<?php
//cgxlm
namespace Illuminate\Database\Connectors;

class Connector
{
	use \Illuminate\Database\DetectsLostConnections;

	/**
     * The default PDO connection options.
     *
     * @var array
     */
	protected $options = array(\PDO::ATTR_CASE => \PDO::CASE_NATURAL, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_ORACLE_NULLS => \PDO::NULL_NATURAL, \PDO::ATTR_STRINGIFY_FETCHES => false, \PDO::ATTR_EMULATE_PREPARES => false);

	public function createConnection($dsn, array $config, array $options)
	{
		list($username, $password) = array(\Illuminate\Support\Arr::get($config, 'username'), \Illuminate\Support\Arr::get($config, 'password'));

		try {
			return $this->createPdoConnection($dsn, $username, $password, $options);
		}
		catch (\Exception $e) {
			return $this->tryAgainIfCausedByLostConnection($e, $dsn, $username, $password, $options);
		}
	}

	protected function createPdoConnection($dsn, $username, $password, $options)
	{
		if (class_exists('Doctrine\\DBAL\\Driver\\PDOConnection') && !$this->isPersistentConnection($options)) {
			return new \Doctrine\DBAL\Driver\PDOConnection($dsn, $username, $password, $options);
		}

		return new \PDO($dsn, $username, $password, $options);
	}

	protected function isPersistentConnection($options)
	{
		return isset($options[\PDO::ATTR_PERSISTENT]) && $options[\PDO::ATTR_PERSISTENT];
	}

	protected function tryAgainIfCausedByLostConnection(\Exception $e, $dsn, $username, $password, $options)
	{
		if ($this->causedByLostConnection($e)) {
			return $this->createPdoConnection($dsn, $username, $password, $options);
		}

		throw $e;
	}

	public function getOptions(array $config)
	{
		$options = \Illuminate\Support\Arr::get($config, 'options', array());
		return array_diff_key($this->options, $options) + $options;
	}

	public function getDefaultOptions()
	{
		return $this->options;
	}

	public function setDefaultOptions(array $options)
	{
		$this->options = $options;
	}
}

?>
