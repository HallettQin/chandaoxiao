<?php
//cgxlm
namespace Illuminate\Contracts\Notifications;

interface Dispatcher
{
	public function send($notifiables, $notification);

	public function sendNow($notifiables, $notification);
}


?>
