<?php
//cgxlm
namespace Illuminate\Contracts\Mail;

interface Mailable
{
	public function send(Mailer $mailer);

	public function queue(\Illuminate\Contracts\Queue\Factory $queue);

	public function later($delay, \Illuminate\Contracts\Queue\Factory $queue);
}


?>
