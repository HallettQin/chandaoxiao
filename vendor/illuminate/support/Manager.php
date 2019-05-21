<?php
//cgxlm
namespace Illuminate\Support;

abstract class Manager
{
	/**
     * The application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
	protected $app;
	/**
     * The registered custom driver creators.
     *
     * @var array
     */
	protected $customCreators = array();
	/**
     * The array of created "drivers".
     *
     * @var array
     */
	protected $drivers = array();

	public function __construct($app)
	{
		$this->app = $app;
	}

	abstract public function getDefaultDriver();

	public function driver($driver = NULL)
	{
		$driver = $driver ?: $this->getDefaultDriver();

		if (!isset($this->drivers[$driver])) {
			$this->drivers[$driver] = $this->createDriver($driver);
		}

		return $this->drivers[$driver];
	}

	protected function createDriver($driver)
	{
		if (isset($this->customCreators[$driver])) {
			return $this->callCustomCreator($driver);
		}
		else {
			$method = 'create' . Str::studly($driver) . 'Driver';

			if (method_exists($this, $method)) {
				return $this->$method();
			}
		}

		throw new \InvalidArgumentException('Driver [' . $driver . '] not supported.');
	}

	protected function callCustomCreator($driver)
	{
		return $this->customCreators[$driver]($this->app);
	}

	public function extend($driver, \Closure $callback)
	{
		$this->customCreators[$driver] = $callback;
		return $this;
	}

	public function getDrivers()
	{
		return $this->drivers;
	}

	public function __call($method, $parameters)
	{
		return $this->driver()->$method(...$parameters);
	}
}


?>
