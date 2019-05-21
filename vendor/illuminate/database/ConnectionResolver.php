<?php
//cgxlm
namespace Illuminate\Database;

class ConnectionResolver implements ConnectionResolverInterface
{
	/**
     * All of the registered connections.
     *
     * @var array
     */
	protected $connections = array();
	/**
     * The default connection name.
     *
     * @var string
     */
	protected $default;

	public function __construct(array $connections = array())
	{
		foreach ($connections as $name => $connection) {
			$this->addConnection($name, $connection);
		}
	}

	public function connection($name = NULL)
	{
		if (is_null($name)) {
			$name = $this->getDefaultConnection();
		}

		return $this->connections[$name];
	}

	public function addConnection($name, ConnectionInterface $connection)
	{
		$this->connections[$name] = $connection;
	}

	public function hasConnection($name)
	{
		return isset($this->connections[$name]);
	}

	public function getDefaultConnection()
	{
		return $this->default;
	}

	public function setDefaultConnection($name)
	{
		$this->default = $name;
	}
}

?>
