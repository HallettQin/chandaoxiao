<?php
//cgxlm
namespace Illuminate\Contracts\Mail;

interface Mailer
{
	public function raw($text, $callback);

	public function send($view, array $data = array(), $callback = NULL);

	public function failures();
}


?>
