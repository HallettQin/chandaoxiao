<?php
//cgxlm
namespace Illuminate\Support;

class MessageBag implements \Illuminate\Contracts\Support\Arrayable, \Countable, \Illuminate\Contracts\Support\Jsonable, \JsonSerializable, \Illuminate\Contracts\Support\MessageBag, \Illuminate\Contracts\Support\MessageProvider
{
	/**
     * All of the registered messages.
     *
     * @var array
     */
	protected $messages = array();
	/**
     * Default format for message output.
     *
     * @var string
     */
	protected $format = ':message';

	public function __construct(array $messages = array())
	{
		foreach ($messages as $key => $value) {
			$this->messages[$key] = (array) $value;
		}
	}

	public function keys()
	{
		return array_keys($this->messages);
	}

	public function add($key, $message)
	{
		if ($this->isUnique($key, $message)) {
			$this->messages[$key][] = $message;
		}

		return $this;
	}

	protected function isUnique($key, $message)
	{
		$messages = (array) $this->messages;
		return !isset($messages[$key]) || !in_array($message, $messages[$key]);
	}

	public function merge($messages)
	{
		if ($messages instanceof \Illuminate\Contracts\Support\MessageProvider) {
			$messages = $messages->getMessageBag()->getMessages();
		}

		$this->messages = array_merge_recursive($this->messages, $messages);
		return $this;
	}

	public function has($key)
	{
		if (is_null($key)) {
			return $this->any();
		}

		$keys = (is_array($key) ? $key : func_get_args());

		foreach ($keys as $key) {
			if ($this->first($key) === '') {
				return false;
			}
		}

		return true;
	}

	public function hasAny($keys = array())
	{
		$keys = (is_array($keys) ? $keys : func_get_args());

		foreach ($keys as $key) {
			if ($this->has($key)) {
				return true;
			}
		}

		return false;
	}

	public function first($key = NULL, $format = NULL)
	{
		$messages = (is_null($key) ? $this->all($format) : $this->get($key, $format));
		$firstMessage = Arr::first($messages, null, '');
		return is_array($firstMessage) ? Arr::first($firstMessage) : $firstMessage;
	}

	public function get($key, $format = NULL)
	{
		if (array_key_exists($key, $this->messages)) {
			return $this->transform($this->messages[$key], $this->checkFormat($format), $key);
		}

		if (Str::contains($key, '*')) {
			return $this->getMessagesForWildcardKey($key, $format);
		}

		return array();
	}

	protected function getMessagesForWildcardKey($key, $format)
	{
		return collect($this->messages)->filter(function($messages, $messageKey) use($key) {
			return Str::is($key, $messageKey);
		})->map(function($messages, $messageKey) use($format) {
			return $this->transform($messages, $this->checkFormat($format), $messageKey);
		})->all();
	}

	public function all($format = NULL)
	{
		$format = $this->checkFormat($format);
		$all = array();

		foreach ($this->messages as $key => $messages) {
			$all = array_merge($all, $this->transform($messages, $format, $key));
		}

		return $all;
	}

	public function unique($format = NULL)
	{
		return array_unique($this->all($format));
	}

	protected function transform($messages, $format, $messageKey)
	{
		return collect((array) $messages)->map(function($message) use($format, $messageKey) {
			return str_replace(array(':message', ':key'), array($message, $messageKey), $format);
		})->all();
	}

	protected function checkFormat($format)
	{
		return $format ?: $this->format;
	}

	public function messages()
	{
		return $this->messages;
	}

	public function getMessages()
	{
		return $this->messages();
	}

	public function getMessageBag()
	{
		return $this;
	}

	public function getFormat()
	{
		return $this->format;
	}

	public function setFormat($format = ':message')
	{
		$this->format = $format;
		return $this;
	}

	public function isEmpty()
	{
		return !$this->any();
	}

	public function any()
	{
		return 0 < $this->count();
	}

	public function count()
	{
		return count($this->messages, COUNT_RECURSIVE) - count($this->messages);
	}

	public function toArray()
	{
		return $this->getMessages();
	}

	public function jsonSerialize()
	{
		return $this->toArray();
	}

	public function toJson($options = 0)
	{
		return json_encode($this->jsonSerialize(), $options);
	}

	public function __toString()
	{
		return $this->toJson();
	}
}

?>
