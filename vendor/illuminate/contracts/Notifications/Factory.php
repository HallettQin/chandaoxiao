<?php
//cgxlm
namespace Illuminate\Contracts\Notifications;

interface Factory
{
	public function channel($name = NULL);

	public function send($notifiables, $notification);

	public function sendNow($notifiables, $notification);
}


?>
