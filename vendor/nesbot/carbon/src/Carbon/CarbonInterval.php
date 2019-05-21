<?php
//cgxlm
namespace Carbon;

class CarbonInterval extends \DateInterval
{
	const PERIOD_PREFIX = 'P';
	const PERIOD_YEARS = 'Y';
	const PERIOD_MONTHS = 'M';
	const PERIOD_DAYS = 'D';
	const PERIOD_TIME_PREFIX = 'T';
	const PERIOD_HOURS = 'H';
	const PERIOD_MINUTES = 'M';
	const PERIOD_SECONDS = 'S';
	const PHP_DAYS_FALSE = -99999;

	/**
     * A translator to ... er ... translate stuff
     *
     * @var \Symfony\Component\Translation\TranslatorInterface
     */
	static protected $translator;

	static private function wasCreatedFromDiff(\DateInterval $interval)
	{
		return ($interval->days !== false) && ($interval->days !== static::PHP_DAYS_FALSE);
	}

	public function __construct($years = 1, $months = NULL, $weeks = NULL, $days = NULL, $hours = NULL, $minutes = NULL, $seconds = NULL)
	{
		$spec = static::PERIOD_PREFIX;
		$spec .= (0 < $years ? $years . static::PERIOD_YEARS : '');
		$spec .= (0 < $months ? $months . static::PERIOD_MONTHS : '');
		$specDays = 0;
		$specDays += (0 < $weeks ? $weeks * Carbon::DAYS_PER_WEEK : 0);
		$specDays += (0 < $days ? $days : 0);
		$spec .= (0 < $specDays ? $specDays . static::PERIOD_DAYS : '');
		if ((0 < $hours) || (0 < $minutes) || (0 < $seconds)) {
			$spec .= static::PERIOD_TIME_PREFIX;
			$spec .= (0 < $hours ? $hours . static::PERIOD_HOURS : '');
			$spec .= (0 < $minutes ? $minutes . static::PERIOD_MINUTES : '');
			$spec .= (0 < $seconds ? $seconds . static::PERIOD_SECONDS : '');
		}

		if ($spec === static::PERIOD_PREFIX) {
			$spec .= '0' . static::PERIOD_YEARS;
		}

		parent::__construct($spec);
	}

	static public function create($years = 1, $months = NULL, $weeks = NULL, $days = NULL, $hours = NULL, $minutes = NULL, $seconds = NULL)
	{
		return new static($years, $months, $weeks, $days, $hours, $minutes, $seconds);
	}

	static public function __callStatic($name, $args)
	{
		$arg = (count($args) === 0 ? 1 : $args[0]);

		switch ($name) {
		case 'years':
		case 'year':
			return new static($arg);
		case 'months':
		case 'month':
			return new static(null, $arg);
		case 'weeks':
		case 'week':
			return new static(null, null, $arg);
		case 'days':
		case 'dayz':
		case 'day':
			return new static(null, null, null, $arg);
		case 'hours':
		case 'hour':
			return new static(null, null, null, null, $arg);
		case 'minutes':
		case 'minute':
			return new static(null, null, null, null, null, $arg);
		case 'seconds':
		case 'second':
			return new static(null, null, null, null, null, null, $arg);
		}
	}

	static public function instance(\DateInterval $di)
	{
		if (static::wasCreatedFromDiff($di)) {
			throw new \InvalidArgumentException('Can not instance a DateInterval object created from DateTime::diff().');
		}

		$instance = new static($di->y, $di->m, 0, $di->d, $di->h, $di->i, $di->s);
		$instance->invert = $di->invert;
		$instance->days = $di->days;
		return $instance;
	}

	static protected function translator()
	{
		if (static::$translator === null) {
			static::$translator = new \Symfony\Component\Translation\Translator('en');
			static::$translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
			static::setLocale('en');
		}

		return static::$translator;
	}

	static public function getTranslator()
	{
		return static::translator();
	}

	static public function setTranslator(\Symfony\Component\Translation\TranslatorInterface $translator)
	{
		static::$translator = $translator;
	}

	static public function getLocale()
	{
		return static::translator()->getLocale();
	}

	static public function setLocale($locale)
	{
		static::translator()->setLocale($locale);
		static::translator()->addResource('array', require __DIR__ . '/Lang/' . $locale . '.php', $locale);
	}

	public function __get($name)
	{
		switch ($name) {
		case 'years':
			return $this->y;
		case 'months':
			return $this->m;
		case 'dayz':
			return $this->d;
		case 'hours':
			return $this->h;
		case 'minutes':
			return $this->i;
		case 'seconds':
			return $this->s;
		case 'weeks':
			return (int) floor($this->d / Carbon::DAYS_PER_WEEK);
		case 'daysExcludeWeeks':
		case 'dayzExcludeWeeks':
			return $this->d % Carbon::DAYS_PER_WEEK;
		default:
			throw new \InvalidArgumentException(sprintf('Unknown getter \'%s\'', $name));
		}
	}

	public function __set($name, $val)
	{
		switch ($name) {
		case 'years':
			$this->y = $val;
			break;

		case 'months':
			$this->m = $val;
			break;

		case 'weeks':
			$this->d = $val * Carbon::DAYS_PER_WEEK;
			break;

		case 'dayz':
			$this->d = $val;
			break;

		case 'hours':
			$this->h = $val;
			break;

		case 'minutes':
			$this->i = $val;
			break;

		case 'seconds':
			$this->s = $val;
			break;
		}
	}

	public function weeksAndDays($weeks, $days)
	{
		$this->dayz = ($weeks * Carbon::DAYS_PER_WEEK) + $days;
		return $this;
	}

	public function __call($name, $args)
	{
		$arg = (count($args) === 0 ? 1 : $args[0]);

		switch ($name) {
		case 'years':
		case 'year':
			$this->years = $arg;
			break;

		case 'months':
		case 'month':
			$this->months = $arg;
			break;

		case 'weeks':
		case 'week':
			$this->dayz = $arg * Carbon::DAYS_PER_WEEK;
			break;

		case 'days':
		case 'dayz':
		case 'day':
			$this->dayz = $arg;
			break;

		case 'hours':
		case 'hour':
			$this->hours = $arg;
			break;

		case 'minutes':
		case 'minute':
			$this->minutes = $arg;
			break;

		case 'seconds':
		case 'second':
			$this->seconds = $arg;
			break;
		}

		return $this;
	}

	public function forHumans()
	{
		$periods = array('year' => $this->years, 'month' => $this->months, 'week' => $this->weeks, 'day' => $this->daysExcludeWeeks, 'hour' => $this->hours, 'minute' => $this->minutes, 'second' => $this->seconds);
		$parts = array();

		foreach ($periods as $unit => $count) {
			if (0 < $count) {
				array_push($parts, static::translator()->transChoice($unit, $count, array(':count' => $count)));
			}
		}

		return implode(' ', $parts);
	}

	public function __toString()
	{
		return $this->forHumans();
	}

	public function add(\DateInterval $interval)
	{
		$sign = ($interval->invert === 1 ? -1 : 1);

		if (static::wasCreatedFromDiff($interval)) {
			$this->dayz += $interval->days * $sign;
		}
		else {
			$this->years += $interval->y * $sign;
			$this->months += $interval->m * $sign;
			$this->dayz += $interval->d * $sign;
			$this->hours += $interval->h * $sign;
			$this->minutes += $interval->i * $sign;
			$this->seconds += $interval->s * $sign;
		}

		return $this;
	}

	public function spec()
	{
		$date = array_filter(array(static::PERIOD_YEARS => $this->y, static::PERIOD_MONTHS => $this->m, static::PERIOD_DAYS => $this->d));
		$time = array_filter(array(static::PERIOD_HOURS => $this->h, static::PERIOD_MINUTES => $this->i, static::PERIOD_SECONDS => $this->s));
		$specString = static::PERIOD_PREFIX;

		foreach ($date as $key => $value) {
			$specString .= $value . $key;
		}

		if (0 < count($time)) {
			$specString .= static::PERIOD_TIME_PREFIX;

			foreach ($time as $key => $value) {
				$specString .= $value . $key;
			}
		}

		return $specString === static::PERIOD_PREFIX ? 'PT0S' : $specString;
	}
}

?>
