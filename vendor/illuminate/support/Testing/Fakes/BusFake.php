<?php
//cgxlm
namespace Illuminate\Support\Testing\Fakes;

class BusFake implements \Illuminate\Contracts\Bus\Dispatcher
{
	/**
     * The commands that have been dispatched.
     *
     * @var array
     */
	protected $commands = array();

	public function assertDispatched($command, $callback = NULL)
	{
		\PHPUnit\Framework\Assert::assertTrue(0 < $this->dispatched($command, $callback)->count(), 'The expected [' . $command . '] job was not dispatched.');
	}

	public function assertNotDispatched($command, $callback = NULL)
	{
		\PHPUnit\Framework\Assert::assertTrue($this->dispatched($command, $callback)->count() === 0, 'The unexpected [' . $command . '] job was dispatched.');
	}

	public function dispatched($command, $callback = NULL)
	{
		if (!$this->hasDispatched($command)) {
			return collect();
		}

		$callback = $callback ?: function() {
			return true;
		};
		return collect($this->commands[$command])->filter(function($command) use($callback) {
			return $callback($command);
		});
	}

	public function hasDispatched($command)
	{
		return isset($this->commands[$command]) && !empty($this->commands[$command]);
	}

	public function dispatch($command)
	{
		return $this->dispatchNow($command);
	}

	public function dispatchNow($command, $handler = NULL)
	{
		$this->commands[get_class($command)][] = $command;
	}

	public function pipeThrough(array $pipes)
	{
	}
}

?>
