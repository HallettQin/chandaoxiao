<?php
//cgxlm
namespace Illuminate\Database;

abstract class Seeder
{
	/**
     * The container instance.
     *
     * @var \Illuminate\Container\Container
     */
	protected $container;
	/**
     * The console command instance.
     *
     * @var \Illuminate\Console\Command
     */
	protected $command;

	public function call($class)
	{
		if (isset($this->command)) {
			$this->command->getOutput()->writeln('<info>Seeding:</info> ' . $class);
		}

		$this->resolve($class)->__invoke();
	}

	public function callSilent($class)
	{
		$this->resolve($class)->__invoke();
	}

	protected function resolve($class)
	{
		if (isset($this->container)) {
			$instance = $this->container->make($class);
			$instance->setContainer($this->container);
		}
		else {
			$instance = new $class();
		}

		if (isset($this->command)) {
			$instance->setCommand($this->command);
		}

		return $instance;
	}

	public function setContainer(\Illuminate\Container\Container $container)
	{
		$this->container = $container;
		return $this;
	}

	public function setCommand(\Illuminate\Console\Command $command)
	{
		$this->command = $command;
		return $this;
	}

	public function __invoke()
	{
		if (!method_exists($this, 'run')) {
			throw new \InvalidArgumentException('Method [run] missing from ' . get_class($this));
		}

		return isset($this->container) ? $this->container->call(array($this, 'run')) : $this->run();
	}
}


?>
