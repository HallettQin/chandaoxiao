<?php
//cgxlm
namespace Illuminate\Contracts\Support;

interface MessageBag
{
	public function keys();

	public function add($key, $message);

	public function merge($messages);

	public function has($key);

	public function first($key = NULL, $format = NULL);

	public function get($key, $format = NULL);

	public function all($format = NULL);

	public function getFormat();

	public function setFormat($format = ':message');

	public function isEmpty();

	public function count();

	public function toArray();
}


?>
