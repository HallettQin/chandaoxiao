<?php
//cgxlm
namespace Illuminate\Support;

class Str
{
	use Traits\Macroable;

	/**
     * The cache of snake-cased words.
     *
     * @var array
     */
	static protected $snakeCache = array();
	/**
     * The cache of camel-cased words.
     *
     * @var array
     */
	static protected $camelCache = array();
	/**
     * The cache of studly-cased words.
     *
     * @var array
     */
	static protected $studlyCache = array();

	static public function after($subject, $search)
	{
		if ($search == '') {
			return $subject;
		}

		$pos = strpos($subject, $search);

		if ($pos === false) {
			return $subject;
		}

		return substr($subject, $pos + strlen($search));
	}

	static public function ascii($value)
	{
		foreach (static::charsArray() as $key => $val) {
			$value = str_replace($val, $key, $value);
		}

		return preg_replace('/[^\\x20-\\x7E]/u', '', $value);
	}

	static public function camel($value)
	{
		if (isset(static::$camelCache[$value])) {
			return static::$camelCache[$value];
		}

		return static::$camelCache[$value] = lcfirst(static::studly($value));
	}

	static public function contains($haystack, $needles)
	{
		foreach ((array) $needles as $needle) {
			if (($needle != '') && (mb_strpos($haystack, $needle) !== false)) {
				return true;
			}
		}

		return false;
	}

	static public function endsWith($haystack, $needles)
	{
		foreach ((array) $needles as $needle) {
			if (substr($haystack, 0 - strlen($needle)) === (string) $needle) {
				return true;
			}
		}

		return false;
	}

	static public function finish($value, $cap)
	{
		$quoted = preg_quote($cap, '/');
		return preg_replace('/(?:' . $quoted . ')+$/u', '', $value) . $cap;
	}

	static public function is($pattern, $value)
	{
		if ($pattern == $value) {
			return true;
		}

		$pattern = preg_quote($pattern, '#');
		$pattern = str_replace('\\*', '.*', $pattern);
		return (bool) preg_match('#^' . $pattern . '\\z#u', $value);
	}

	static public function kebab($value)
	{
		return static::snake($value, '-');
	}

	static public function length($value, $encoding = NULL)
	{
		if ($encoding) {
			return mb_strlen($value, $encoding);
		}

		return mb_strlen($value);
	}

	static public function limit($value, $limit = 100, $end = '...')
	{
		if (mb_strwidth($value, 'UTF-8') <= $limit) {
			return $value;
		}

		return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
	}

	static public function lower($value)
	{
		return mb_strtolower($value, 'UTF-8');
	}

	static public function words($value, $words = 100, $end = '...')
	{
		preg_match('/^\\s*+(?:\\S++\\s*+){1,' . $words . '}/u', $value, $matches);
		if (!isset($matches[0]) || (static::length($value) === static::length($matches[0]))) {
			return $value;
		}

		return rtrim($matches[0]) . $end;
	}

	static public function parseCallback($callback, $default = NULL)
	{
		return static::contains($callback, '@') ? explode('@', $callback, 2) : array($callback, $default);
	}

	static public function plural($value, $count = 2)
	{
		return Pluralizer::plural($value, $count);
	}

	static public function random($length = 16)
	{
		$string = '';

		while (($len = strlen($string)) < $length) {
			$size = $length - $len;
			$bytes = random_bytes($size);
			$string .= substr(str_replace(array('/', '+', '='), '', base64_encode($bytes)), 0, $size);
		}

		return $string;
	}

	static public function quickRandom($length = 16)
	{
		if (5 < PHP_MAJOR_VERSION) {
			return static::random($length);
		}

		$pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		return substr(str_shuffle(str_repeat($pool, $length)), 0, $length);
	}

	static public function replaceArray($search, array $replace, $subject)
	{
		foreach ($replace as $value) {
			$subject = static::replaceFirst($search, $value, $subject);
		}

		return $subject;
	}

	static public function replaceFirst($search, $replace, $subject)
	{
		if ($search == '') {
			return $subject;
		}

		$position = strpos($subject, $search);

		if ($position !== false) {
			return substr_replace($subject, $replace, $position, strlen($search));
		}

		return $subject;
	}

	static public function replaceLast($search, $replace, $subject)
	{
		$position = strrpos($subject, $search);

		if ($position !== false) {
			return substr_replace($subject, $replace, $position, strlen($search));
		}

		return $subject;
	}

	static public function upper($value)
	{
		return mb_strtoupper($value, 'UTF-8');
	}

	static public function title($value)
	{
		return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
	}

	static public function singular($value)
	{
		return Pluralizer::singular($value);
	}

	static public function slug($title, $separator = '-')
	{
		$title = static::ascii($title);
		$flip = ($separator == '-' ? '_' : '-');
		$title = preg_replace('![' . preg_quote($flip) . ']+!u', $separator, $title);
		$title = preg_replace('![^' . preg_quote($separator) . '\\pL\\pN\\s]+!u', '', mb_strtolower($title));
		$title = preg_replace('![' . preg_quote($separator) . '\\s]+!u', $separator, $title);
		return trim($title, $separator);
	}

	static public function snake($value, $delimiter = '_')
	{
		$key = $value;

		if (isset(static::$snakeCache[$key][$delimiter])) {
			return static::$snakeCache[$key][$delimiter];
		}

		if (!ctype_lower($value)) {
			$value = preg_replace('/\\s+/u', '', $value);
			$value = static::lower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
		}

		return static::$snakeCache[$key][$delimiter] = $value;
	}

	static public function startsWith($haystack, $needles)
	{
		foreach ((array) $needles as $needle) {
			if (($needle != '') && (substr($haystack, 0, strlen($needle)) === (string) $needle)) {
				return true;
			}
		}

		return false;
	}

	static public function studly($value)
	{
		$key = $value;

		if (isset(static::$studlyCache[$key])) {
			return static::$studlyCache[$key];
		}

		$value = ucwords(str_replace(array('-', '_'), ' ', $value));
		return static::$studlyCache[$key] = str_replace(' ', '', $value);
	}

	static public function substr($string, $start, $length = NULL)
	{
		return mb_substr($string, $start, $length, 'UTF-8');
	}

	static public function ucfirst($string)
	{
		return static::upper(static::substr($string, 0, 1)) . static::substr($string, 1);
	}

	static protected function charsArray()
	{
		static $charsArray;

		if (isset($charsArray)) {
			return $charsArray;
		}

		return $charsArray = array(
	0      => array('°', '