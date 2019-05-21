<?php
//cgxlm
namespace Illuminate\Support\Testing\Fakes;

class QueueFake extends \Illuminate\Queue\QueueManager implements \Illuminate\Contracts\Queue\Queue
{
	/**
     * All of the jobs that have been pushed.
     *
     * @var array
     */
	protected $jobs = array();

	public function assertPushed($job, $callback = NULL)
	{
		\PHPUnit\Framework\Assert::assertTrue(0 < $this->pushed($job, $callback)->count(), 'The expected [' . $job . '] job was not pushed.');
	}

	public function assertPushedOn($queue, $job, $callback = NULL)
	{
		return $this->assertPushed($job, function($job, $pushedQueue) use($callback, $queue) {
			if ($pushedQueue !== $queue) {
				return false;
			}

			return $callback ? $callback(...func_get_args()) : true;
		});
	}

	public function assertNotPushed($job, $callback = NULL)
	{
		\PHPUnit\Framework\Assert::assertTrue($this->pushed($job, $callback)->count() === 0, 'The unexpected [' . $job . '] job was pushed.');
	}

	public function pushed($job, $callback = NULL)
	{
		if (!$this->hasPushed($job)) {
			return collect();
		}

		$callback = $callback ?: function() {
			return true;
		};
		return collect($this->jobs[$job])->filter(function($data) use($callback) {
			return $callback($data['job'], $data['queue']);
		})->pluck('job');
	}

	public function hasPushed($job)
	{
		return isset($this->jobs[$job]) && !empty($this->jobs[$job]);
	}

	public function connection($value = NULL)
	{
		return $this;
	}

	public function size($queue = NULL)
	{
		return 0;
	}

	public function push($job, $data = '', $queue = NULL)
	{
		$this->jobs[is_object($job) ? get_class($job) : $job][] = array('job' => $job, 'queue' => $queue);
	}

	public function pushRaw($payload, $queue = NULL, array $options = array())
	{
	}

	public function later($delay, $job, $data = '', $queue = NULL)
	{
		return $this->push($job, $data, $queue);
	}

	public function pushOn($queue, $job, $data = '')
	{
		return $this->push($job, $data, $queue);
	}

	public function laterOn($queue, $delay, $job, $data = '')
	{
		return $this->push($job, $data, $queue);
	}

	public function pop($queue = NULL)
	{
	}

	public function bulk($jobs, $data = '', $queue = NULL)
	{
		foreach ($this->jobs as $job) {
			$this->push($job);
		}
	}

	public function getConnectionName()
	{
	}

	public function setConnectionName($name)
	{
		return $this;
	}
}

?>
