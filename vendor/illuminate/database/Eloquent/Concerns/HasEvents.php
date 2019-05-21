<?php
//cgxlm
namespace Illuminate\Database\Eloquent\Concerns;

trait HasEvents
{
	/**
     * The event map for the model.
     *
     * Allows for object-based events for native Eloquent events.
     *
     * @var array
     */
	protected $events = array();
	/**
     * User exposed observable events.
     *
     * These are extra user-defined events observers may subscribe to.
     *
     * @var array
     */
	protected $observables = array();

	static public function observe($class)
	{
		$instance = new static();
		$className = (is_string($class) ? $class : get_class($class));

		foreach ($instance->getObservableEvents() as $event) {
			if (method_exists($class, $event)) {
				static::registerModelEvent($event, $className . '@' . $event);
			}
		}
	}

	public function getObservableEvents()
	{
		return array_merge(array('creating', 'created', 'updating', 'updated', 'deleting', 'deleted', 'saving', 'saved', 'restoring', 'restored'), $this->observables);
	}

	public function setObservableEvents(array $observables)
	{
		$this->observables = $observables;
		return $this;
	}

	public function addObservableEvents($observables)
	{
		$this->observables = array_unique(array_merge($this->observables, is_array($observables) ? $observables : func_get_args()));
	}

	public function removeObservableEvents($observables)
	{
		$this->observables = array_diff($this->observables, is_array($observables) ? $observables : func_get_args());
	}

	static protected function registerModelEvent($event, $callback)
	{
		if (isset(static::$dispatcher)) {
			$name = static::class;
			static::$dispatcher->listen('eloquent.' . $event . ': ' . $name, $callback);
		}
	}

	protected function fireModelEvent($event, $halt = true)
	{
		if (!isset(static::$dispatcher)) {
			return true;
		}

		$method = ($halt ? 'until' : 'fire');
		$result = $this->filterModelEventResults($this->fireCustomModelEvent($event, $method));

		if ($result === false) {
			return false;
		}

		return !empty($result) ? $result : static::$dispatcher->$method('eloquent.' . $event . ': ' . static::class, $this);
	}

	protected function fireCustomModelEvent($event, $method)
	{
		if (!isset($this->events[$event])) {
			return NULL;
		}

		$result = static::$dispatcher->$method(new $this->events[$event]($this));

		if (!is_null($result)) {
			return $result;
		}
	}

	protected function filterModelEventResults($result)
	{
		if (is_array($result)) {
			$result = array_filter($result, function($response) {
				return !is_null($response);
			});
		}

		return $result;
	}

	static public function saving($callback)
	{
		static::registerModelEvent('saving', $callback);
	}

	static public function saved($callback)
	{
		static::registerModelEvent('saved', $callback);
	}

	static public function updating($callback)
	{
		static::registerModelEvent('updating', $callback);
	}

	static public function updated($callback)
	{
		static::registerModelEvent('updated', $callback);
	}

	static public function creating($callback)
	{
		static::registerModelEvent('creating', $callback);
	}

	static public function created($callback)
	{
		static::registerModelEvent('created', $callback);
	}

	static public function deleting($callback)
	{
		static::registerModelEvent('deleting', $callback);
	}

	static public function deleted($callback)
	{
		static::registerModelEvent('deleted', $callback);
	}

	static public function flushEventListeners()
	{
		if (!isset(static::$dispatcher)) {
			return NULL;
		}

		$instance = new static();

		foreach ($instance->getObservableEvents() as $event) {
			static::$dispatcher->forget('eloquent.' . $event . ': ' . static::class);
		}
	}

	static public function getEventDispatcher()
	{
		return static::$dispatcher;
	}

	static public function setEventDispatcher(\Illuminate\Contracts\Events\Dispatcher $dispatcher)
	{
		static::$dispatcher = $dispatcher;
	}

	static public function unsetEventDispatcher()
	{
		static::$dispatcher = null;
	}
}


?>
