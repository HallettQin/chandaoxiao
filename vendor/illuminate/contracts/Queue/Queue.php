<?php
//cgxlm
namespace Illuminate\Contracts\Queue;

interface Queue
{
	public function size($queue = NULL);

	public function push($job, $data = '', $queue = NULL);

	public function pushOn($queue, $job, $data = '');

	public function pushRaw($payload, $queue = NULL, array $options = array());

	public function later($delay, $job, $data = '', $queue = NULL);

	public function laterOn($queue, $delay, $job, $data = '');

	public function bulk($jobs, $data = '', $queue = NULL);

	public function pop($queue = NULL);

	public function getConnectionName();

	public function setConnectionName($name);
}


?>
