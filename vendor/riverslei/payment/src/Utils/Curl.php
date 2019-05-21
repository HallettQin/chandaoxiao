<?php
//cgxlm
namespace Payment\Utils;

class Curl
{
	private $post;
	private $retry;
	private $option;
	private $default;
	private $download;
	static private $instance;

	public function __construct()
	{
		$this->retry = 0;
		$this->default = array('CURLOPT_TIMEOUT' => 30, 'CURLOPT_ENCODING' => '', 'CURLOPT_IPRESOLVE' => 1, 'CURLOPT_RETURNTRANSFER' => true, 'CURLOPT_SSL_VERIFYPEER' => false, 'CURLOPT_CONNECTTIMEOUT' => 10, 'CURLOPT_HEADER' => 0);
	}

	static public function init()
	{
		if (static::$instance === null) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	public function get($url)
	{
		return $this->set('CURLOPT_URL', $url)->exec();
	}

	public function post($data, $value = '')
	{
		if (is_array($data)) {
			foreach ($data as $key => $value) {
				$this->post[$key] = $value;
			}
		}
		else if ($value) {
			$this->post[$data] = $value;
		}
		else {
			$this->post = $data;
		}

		return $this;
	}

	public function upload($field, $path, $type, $name)
	{
		$name = basename($name);

		if (class_exists('CURLFile')) {
			$this->set('CURLOPT_SAFE_UPLOAD', true);
			$file = curl_file_create($path, $type, $name);
		}
		else {
			$file = '@' . $path . ';type=' . $type . ';filename=' . $name;
		}

		return $this->post($field, $file);
	}

	public function submit($url)
	{
		if (!$this->post) {
			return array('error' => 1, 'message' => '未设置POST信息');
		}

		return $this->set('CURLOPT_URL', $url)->exec();
	}

	public function download($url)
	{
		$this->download = true;
		return $this->set('CURLOPT_URL', $url);
	}

	public function save($path)
	{
		if (!$this->download) {
			return array('error' => 1, 'message' => '未设置下载地址');
		}

		$result = $this->exec();

		if ($result['error'] === 0) {
			$fp = @fopen($path, 'w');
			fwrite($fp, $result['body']);
			fclose($fp);
		}

		return $result;
	}

	public function set($item, $value = '')
	{
		if (is_array($item)) {
			foreach ($item as $key => &$value) {
				$this->option[$key] = $value;
			}
		}
		else {
			$this->option[$item] = $value;
		}

		return $this;
	}

	public function retry($times = 0)
	{
		$this->retry = $times;
		return $this;
	}

	private function exec($retry = 0)
	{
		$ch = curl_init();
		$options = array_merge($this->default, $this->option);

		foreach ($options as $key => $val) {
			if (is_string($key)) {
				$key = constant(strtoupper($key));
			}

			curl_setopt($ch, $key, $val);
		}

		if ($this->post) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->postFieldsBuild($this->post));
		}

		$body = curl_exec($ch);
		$info = curl_getinfo($ch);
		$errno = curl_errno($ch);
		if (($errno === 0) && (400 <= $info['http_code'])) {
			$errno = $info['http_code'];
		}

		curl_close($ch);
		if ($errno && ($retry < $this->retry)) {
			$this->exec($retry + 1);
		}

		$this->post = null;
		$this->retry = null;
		$this->option = null;
		$this->download = null;
		return array('error' => $errno ? 1 : 0, 'message' => $errno, 'body' => $body, 'info' => $info);
	}

	private function postFieldsBuild($input, $pre = NULL)
	{
		if (is_array($input)) {
			$output = array();

			foreach ($input as $key => $value) {
				$index = (is_null($pre) ? $key : $pre . '[' . $key . ']');

				if (is_array($value)) {
					$output = array_merge($output, $this->postFieldsBuild($value, $index));
				}
				else {
					$output[$index] = $value;
				}
			}

			return $output;
		}

		return $input;
	}
}


?>
