<?php
//cgxlm
namespace Illuminate\Contracts\Mail;

interface MailQueue
{
	public function queue($view, array $data, $callback, $queue = NULL);

	public function later($delay, $view, array $data, $callback, $queue = NULL);
}


?>
