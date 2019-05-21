<?php
//cgxlm
namespace Illuminate\Support\Testing\Fakes;

class EventFake implements \Illuminate\Contracts\Events\Dispatcher
{
	/**
     * All of the events that have been dispatched keyed by type.
     *
     * @var array
     */
	protected $events = array();

	public function assertDispatched($event, $callback = NULL)
	{
		\PHPUnit\Framework\Assert::assertTrue(0 < $this->dispatched($event, $callback)->count(), 'The expected [' . $event . '] event was not dispatched.');
	}

	public function assertNotDispatched($event, $callback = NULL)
	{
		\PHPUnit\Framework\Assert::assertTrue($this->dispatched($event, $callback)->count() === 0, 'The unexpected [' . $event . '] event was dispatched.');
	}

	public function dispatched($event, $callback = NULL)
	{
		if (!$this->hasDispatched($event)) {
			return collect();
		}

		$callback = $callback ?: function() {
			return true;
		};
		return collect($this->events[$event])->filter(function($arguments) use($callback) {
			return $callback(...$arguments);
		});
	}

	public function hasDispatched($event)
	{
		return isset($this->events[$event]) && !empty($this->events[$event]);
	}

	public function listen($events, $listener)
	{
	}

	public function hasListeners($eventName)
	{
	}

	public function push($event, $payload = array())
	{
	}

	public function subscribe($subscriber)
	{
	}

	public function flush($event)
	{
	}

	public function fire($event, $payload = array(), $halt = false)
	{
		return $this->dispatch($event, $payload, $halt);
	}

	public function dispatch($event, $payload = array(), $halt = false)
	{
		$name = (is_object($event) ? get_class($event) : (string) $event);
		$this->events[$name][] = func_get_args();
	}

	public function forget($event)
	{
	}

	public function forgetPushed()
	{
	}

	public function until($event, $payload = array())
	{
		return $this->dispatch($event, $payload, true);
	}
}

?>
