<?php
//cgxlm
namespace Carbon;

class Carbon extends \DateTime
{
	const SUNDAY = 0;
	const MONDAY = 1;
	const TUESDAY = 2;
	const WEDNESDAY = 3;
	const THURSDAY = 4;
	const FRIDAY = 5;
	const SATURDAY = 6;
	const YEARS_PER_CENTURY = 100;
	const YEARS_PER_DECADE = 10;
	const MONTHS_PER_YEAR = 12;
	const MONTHS_PER_QUARTER = 3;
	const WEEKS_PER_YEAR = 52;
	const DAYS_PER_WEEK = 7;
	const HOURS_PER_DAY = 24;
	const MINUTES_PER_HOUR = 60;
	const SECONDS_PER_MINUTE = 60;
	const DEFAULT_TO_STRING_FORMAT = 'Y-m-d H:i:s';

	/**
     * Names of days of the week.
     *
     * @var array
     */
	static protected $days = array(self::SUNDAY => 'Sunday', self::MONDAY => 'Monday', self::TUESDAY => 'Tuesday', self::WEDNESDAY => 'Wednesday', self::THURSDAY => 'Thursday', self::FRIDAY => 'Friday', self::SATURDAY => 'Saturday');
	/**
     * Terms used to detect if a time passed is a relative date.
     *
     * This is here for testing purposes.
     *
     * @var array
     */
	static protected $relativeKeywords = array('+', '-', 'ago', 'first', 'last', 'next', 'this', 'today', 'tomorrow', 'yesterday');
	/**
     * Format to use for __toString method when type juggling occurs.
     *
     * @var string
     */
	static protected $toStringFormat = self::DEFAULT_TO_STRING_FORMAT;
	/**
     * First day of week.
     *
     * @var int
     */
	static protected $weekStartsAt = self::MONDAY;
	/**
     * Last day of week.
     *
     * @var int
     */
	static protected $weekEndsAt = self::SUNDAY;
	/**
     * Days of weekend.
     *
     * @var array
     */
	static protected $weekendDays = array(self::SATURDAY, self::SUNDAY);
	/**
     * A test Carbon instance to be returned when now instances are created.
     *
     * @var \Carbon\Carbon
     */
	static protected $testNow;
	/**
     * A translator to ... er ... translate stuff.
     *
     * @var \Symfony\Component\Translation\TranslatorInterface
     */
	static protected $translator;
	/**
     * The errors that can occur.
     *
     * @var array
     */
	static protected $lastErrors;
	/**
     * Will UTF8 encoding be used to print localized date/time ?
     *
     * @var bool
     */
	static protected $utf8 = false;
	static protected $monthsOverflow = true;

	static public function useMonthsOverflow($monthsOverflow = true)
	{
		static::$monthsOverflow = $monthsOverflow;
	}

	static public function resetMonthsOverflow()
	{
		static::$monthsOverflow = true;
	}

	static public function shouldOverflowMonths()
	{
		return static::$monthsOverflow;
	}

	static protected function safeCreateDateTimeZone($object)
	{
		if ($object === null) {
			return new \DateTimeZone(date_default_timezone_get());
		}

		if ($object instanceof \DateTimeZone) {
			return $object;
		}

		if (is_numeric($object)) {
			$tzName = timezone_name_from_abbr(null, $object * 3600, true);

			if ($tzName === false) {
				throw new \InvalidArgumentException('Unknown or bad timezone (' . $object . ')');
			}

			$object = $tzName;
		}

		$tz = @timezone_open((string) $object);

		if ($tz === false) {
			throw new \InvalidArgumentException('Unknown or bad timezone (' . $object . ')');
		}

		return $tz;
	}

	public function __construct($time = NULL, $tz = NULL)
	{
		if (static::hasTestNow() && (empty($time) || ($time === 'now') || static::hasRelativeKeywords($time))) {
			$testInstance = clone static::getTestNow();

			if (static::hasRelativeKeywords($time)) {
				$testInstance->modify($time);
			}

			if (($tz !== null) && ($tz !== static::getTestNow()->getTimezone())) {
				$testInstance->setTimezone($tz);
			}
			else {
				$tz = $testInstance->getTimezone();
			}

			$time = $testInstance->toDateTimeString();
		}

		parent::__construct($time, static::safeCreateDateTimeZone($tz));
	}

	static public function instance(\DateTime $dt)
	{
		if ($dt instanceof static) {
			return clone $dt;
		}

		return new static($dt->format('Y-m-d H:i:s.u'), $dt->getTimezone());
	}

	static public function parse($time = NULL, $tz = NULL)
	{
		return new static($time, $tz);
	}

	static public function now($tz = NULL)
	{
		return new static(null, $tz);
	}

	static public function today($tz = NULL)
	{
		return static::now($tz)->startOfDay();
	}

	static public function tomorrow($tz = NULL)
	{
		return static::today($tz)->addDay();
	}

	static public function yesterday($tz = NULL)
	{
		return static::today($tz)->subDay();
	}

	static public function maxValue()
	{
		if (PHP_INT_SIZE === 4) {
			return static::createFromTimestamp(PHP_INT_MAX);
		}

		return static::create(9999, 12, 31, 23, 59, 59);
	}

	static public function minValue()
	{
		if (PHP_INT_SIZE === 4) {
			return static::createFromTimestamp(~PHP_INT_MAX);
		}

		return static::create(1, 1, 1, 0, 0, 0);
	}

	static public function create($year = NULL, $month = NULL, $day = NULL, $hour = NULL, $minute = NULL, $second = NULL, $tz = NULL)
	{
		$now = (static::hasTestNow() ? static::getTestNow()->getTimestamp() : time());
		$defaults = array_combine(array('year', 'month', 'day', 'hour', 'minute', 'second'), explode('-', date('Y-n-j-G-i-s', $now)));
		$year = ($year === null ? $defaults['year'] : $year);
		$month = ($month === null ? $defaults['month'] : $month);
		$day = ($day === null ? $defaults['day'] : $day);

		if ($hour === null) {
			$hour = $defaults['hour'];
			$minute = ($minute === null ? $defaults['minute'] : $minute);
			$second = ($second === null ? $defaults['second'] : $second);
		}
		else {
			$minute = ($minute === null ? 0 : $minute);
			$second = ($second === null ? 0 : $second);
		}

		$fixYear = null;

		if ($year < 0) {
			$fixYear = $year;
			$year = 0;
		}
		else if (9999 < $year) {
			$fixYear = $year - 9999;
			$year = 9999;
		}

		$instance = static::createFromFormat('Y-n-j G:i:s', sprintf('%s-%s-%s %s:%02s:%02s', $year, $month, $day, $hour, $minute, $second), $tz);

		if ($fixYear !== null) {
			$instance->addYears($fixYear);
		}

		return $instance;
	}

	static public function createSafe($year = NULL, $month = NULL, $day = NULL, $hour = NULL, $minute = NULL, $second = NULL, $tz = NULL)
	{
		$fields = array(
			'year'   => array(0, 9999),
			'month'  => array(0, 12),
			'day'    => array(0, 31),
			'hour'   => array(0, 24),
			'minute' => array(0, 59),
			'second' => array(0, 59)
			);

		foreach ($fields as $field => $range) {
			if (($$field !== null) && (!is_int($$field) || ($$field < $range[0]) || ($range[1] < $$field))) {
				throw new Exceptions\InvalidDateException($field, $$field);
			}
		}

		$instance = static::create($year, $month, 1, $hour, $minute, $second, $tz);
		if (($day !== null) && ($instance->daysInMonth < $day)) {
			throw new Exceptions\InvalidDateException('day', $day);
		}

		return $instance->day($day);
	}

	static public function createFromDate($year = NULL, $month = NULL, $day = NULL, $tz = NULL)
	{
		return static::create($year, $month, $day, null, null, null, $tz);
	}

	static public function createFromTime($hour = NULL, $minute = NULL, $second = NULL, $tz = NULL)
	{
		return static::create(null, null, null, $hour, $minute, $second, $tz);
	}

	static public function createFromFormat($format, $time, $tz = NULL)
	{
		if ($tz !== null) {
			$dt = parent::createFromFormat($format, $time, static::safeCreateDateTimeZone($tz));
		}
		else {
			$dt = parent::createFromFormat($format, $time);
		}

		static::setLastErrors($lastErrors = parent::getLastErrors());

		if ($dt instanceof \DateTime) {
			return static::instance($dt);
		}

		throw new \InvalidArgumentException(implode(PHP_EOL, $lastErrors['errors']));
	}

	static private function setLastErrors(array $lastErrors)
	{
		static::$lastErrors = $lastErrors;
	}

	static public function getLastErrors()
	{
		return static::$lastErrors;
	}

	static public function createFromTimestamp($timestamp, $tz = NULL)
	{
		return static::now($tz)->setTimestamp($timestamp);
	}

	static public function createFromTimestampUTC($timestamp)
	{
		return new static('@' . $timestamp);
	}

	public function copy()
	{
		return clone $this;
	}

	public function __get($name)
	{
		switch (true) {
		case array_key_exists($name, $formats = array('year' => 'Y', 'yearIso' => 'o', 'month' => 'n', 'day' => 'j', 'hour' => 'G', 'minute' => 'i', 'second' => 's', 'micro' => 'u', 'dayOfWeek' => 'w', 'dayOfYear' => 'z', 'weekOfYear' => 'W', 'daysInMonth' => 't', 'timestamp' => 'U')):
			return (int) $this->format($formats[$name]);
		case $name === 'weekOfMonth':
			return (int) ceil($this->day / static::DAYS_PER_WEEK);
		case $name === 'age':
			return $this->diffInYears();
		case $name === 'quarter':
			return (int) ceil($this->month / static::MONTHS_PER_QUARTER);
		case $name === 'offset':
			return $this->getOffset();
		case $name === 'offsetHours':
			return $this->getOffset() / static::SECONDS_PER_MINUTE / static::MINUTES_PER_HOUR;
		case $name === 'dst':
			return $this->format('I') === '1';
		case $name === 'local':
			return $this->getOffset() === $this->copy()->setTimezone(date_default_timezone_get())->getOffset();
		case $name === 'utc':
			return $this->getOffset() === 0;
		case ($name === 'timezone') || ($name === 'tz'):
			return $this->getTimezone();
		case ($name === 'timezoneName') || ($name === 'tzName'):
			return $this->getTimezone()->getName();
		default:
			throw new \InvalidArgumentException(sprintf('Unknown getter \'%s\'', $name));
		}
	}

	public function __isset($name)
	{
		try {
			$this->__get($name);
		}
		catch (\InvalidArgumentException $e) {
			return false;
		}

		return true;
	}

	public function __set($name, $value)
	{
		switch ($name) {
		case 'year':
		case 'month':
		case 'day':
		case 'hour':
		case 'minute':
		case 'second':
			list($year, $month, $day, $hour, $minute, $second) = explode('-', $this->format('Y-n-j-G-i-s'));
			$$name = $value;
			$this->setDateTime($year, $month, $day, $hour, $minute, $second);
			break;

		case 'timestamp':
			parent::setTimestamp($value);
			break;

		case 'timezone':
		case 'tz':
			$this->setTimezone($value);
			break;

		default:
			throw new \InvalidArgumentException(sprintf('Unknown setter \'%s\'', $name));
		}
	}

	public function year($value)
	{
		$this->year = $value;
		return $this;
	}

	public function month($value)
	{
		$this->month = $value;
		return $this;
	}

	public function day($value)
	{
		$this->day = $value;
		return $this;
	}

	public function hour($value)
	{
		$this->hour = $value;
		return $this;
	}

	public function minute($value)
	{
		$this->minute = $value;
		return $this;
	}

	public function second($value)
	{
		$this->second = $value;
		return $this;
	}

	public function setDate($year, $month, $day)
	{
		$this->modify('+0 day');
		return parent::setDate($year, $month, $day);
	}

	public function setDateTime($year, $month, $day, $hour, $minute, $second = 0)
	{
		return $this->setDate($year, $month, $day)->setTime($hour, $minute, $second);
	}

	public function setTimeFromTimeString($time)
	{
		$time = explode(':', $time);
		$hour = $time[0];
		$minute = (isset($time[1]) ? $time[1] : 0);
		$second = (isset($time[2]) ? $time[2] : 0);
		return $this->setTime($hour, $minute, $second);
	}

	public function timestamp($value)
	{
		return $this->setTimestamp($value);
	}

	public function timezone($value)
	{
		return $this->setTimezone($value);
	}

	public function tz($value)
	{
		return $this->setTimezone($value);
	}

	public function setTimezone($value)
	{
		return parent::setTimezone(static::safeCreateDateTimeZone($value));
	}

	static public function getWeekStartsAt()
	{
		return static::$weekStartsAt;
	}

	static public function setWeekStartsAt($day)
	{
		static::$weekStartsAt = $day;
	}

	static public function getWeekEndsAt()
	{
		return static::$weekEndsAt;
	}

	static public function setWeekEndsAt($day)
	{
		static::$weekEndsAt = $day;
	}

	static public function getWeekendDays()
	{
		return static::$weekendDays;
	}

	static public function setWeekendDays($days)
	{
		static::$weekendDays = $days;
	}

	static public function setTestNow($testNow = NULL)
	{
		static::$testNow = (is_string($testNow) ? static::parse($testNow) : $testNow);
	}

	static public function getTestNow()
	{
		return static::$testNow;
	}

	static public function hasTestNow()
	{
		return static::getTestNow() !== null;
	}

	static public function hasRelativeKeywords($time)
	{
		if (preg_match('/\\d{4}-\\d{1,2}-\\d{1,2}/', $time) !== 1) {
			foreach (static::$relativeKeywords as $keyword) {
				if (stripos($time, $keyword) !== false) {
					return true;
				}
			}
		}

		return false;
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
		$locale = preg_replace_callback('/\\b([a-z]{2})[-_](?:([a-z]{4})[-_])?([a-z]{2})\\b/', function($matches) {
			return $matches[1] . '_' . (!empty($matches[2]) ? ucfirst($matches[2]) . '_' : '') . strtoupper($matches[3]);
		}, strtolower($locale));

		if (file_exists($filename = __DIR__ . '/Lang/' . $locale . '.php')) {
			static::translator()->setLocale($locale);
			static::translator()->addResource('array', require $filename, $locale);
			return true;
		}

		return false;
	}

	static public function setUtf8($utf8)
	{
		static::$utf8 = $utf8;
	}

	public function formatLocalized($format)
	{
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			$format = preg_replace('#(?<!%)((?:%%)*)%e#', '\\1%#d', $format);
		}

		$formatted = strftime($format, strtotime($this));
		return static::$utf8 ? utf8_encode($formatted) : $formatted;
	}

	static public function resetToStringFormat()
	{
		static::setToStringFormat(static::DEFAULT_TO_STRING_FORMAT);
	}

	static public function setToStringFormat($format)
	{
		static::$toStringFormat = $format;
	}

	public function __toString()
	{
		return $this->format(static::$toStringFormat);
	}

	public function toDateString()
	{
		return $this->format('Y-m-d');
	}

	public function toFormattedDateString()
	{
		return $this->format('M j, Y');
	}

	public function toTimeString()
	{
		return $this->format('H:i:s');
	}

	public function toDateTimeString()
	{
		return $this->format('Y-m-d H:i:s');
	}

	public function toDayDateTimeString()
	{
		return $this->format('D, M j, Y g:i A');
	}

	public function toAtomString()
	{
		return $this->format(static::ATOM);
	}

	public function toCookieString()
	{
		return $this->format(static::COOKIE);
	}

	public function toIso8601String()
	{
		return $this->toAtomString();
	}

	public function toRfc822String()
	{
		return $this->format(static::RFC822);
	}

	public function toRfc850String()
	{
		return $this->format(static::RFC850);
	}

	public function toRfc1036String()
	{
		return $this->format(static::RFC1036);
	}

	public function toRfc1123String()
	{
		return $this->format(static::RFC1123);
	}

	public function toRfc2822String()
	{
		return $this->format(static::RFC2822);
	}

	public function toRfc3339String()
	{
		return $this->format(static::RFC3339);
	}

	public function toRssString()
	{
		return $this->format(static::RSS);
	}

	public function toW3cString()
	{
		return $this->format(static::W3C);
	}

	public function eq(Carbon $dt)
	{
		return $this == $dt;
	}

	public function equalTo(Carbon $dt)
	{
		return $this->eq($dt);
	}

	public function ne(Carbon $dt)
	{
		return !$this->eq($dt);
	}

	public function notEqualTo(Carbon $dt)
	{
		return $this->ne($dt);
	}

	public function gt(Carbon $dt)
	{
		return $dt < $this;
	}

	public function greaterThan(Carbon $dt)
	{
		return $this->gt($dt);
	}

	public function gte(Carbon $dt)
	{
		return $dt <= $this;
	}

	public function greaterThanOrEqualTo(Carbon $dt)
	{
		return $this->gte($dt);
	}

	public function lt(Carbon $dt)
	{
		return $this < $dt;
	}

	public function lessThan(Carbon $dt)
	{
		return $this->lt($dt);
	}

	public function lte(Carbon $dt)
	{
		return $this <= $dt;
	}

	public function lessThanOrEqualTo(Carbon $dt)
	{
		return $this->lte($dt);
	}

	public function between(Carbon $dt1, Carbon $dt2, $equal = true)
	{
		if ($dt1->gt($dt2)) {
			$temp = $dt1;
			$dt1 = $dt2;
			$dt2 = $temp;
		}

		if ($equal) {
			return $this->gte($dt1) && $this->lte($dt2);
		}

		return $this->gt($dt1) && $this->lt($dt2);
	}

	public function closest(Carbon $dt1, Carbon $dt2)
	{
		return $this->diffInSeconds($dt1) < $this->diffInSeconds($dt2) ? $dt1 : $dt2;
	}

	public function farthest(Carbon $dt1, Carbon $dt2)
	{
		return $this->diffInSeconds($dt2) < $this->diffInSeconds($dt1) ? $dt1 : $dt2;
	}

	public function min(Carbon $dt = NULL)
	{
		$dt = $dt ?: static::now($this->getTimezone());
		return $this->lt($dt) ? $this : $dt;
	}

	public function minimum(Carbon $dt = NULL)
	{
		return $this->min($dt);
	}

	public function max(Carbon $dt = NULL)
	{
		$dt = $dt ?: static::now($this->getTimezone());
		return $this->gt($dt) ? $this : $dt;
	}

	public function maximum(Carbon $dt = NULL)
	{
		return $this->max($dt);
	}

	public function isWeekday()
	{
		return !$this->isWeekend();
	}

	public function isWeekend()
	{
		return in_array($this->dayOfWeek, static::$weekendDays);
	}

	public function isYesterday()
	{
		return $this->toDateString() === static::yesterday($this->getTimezone())->toDateString();
	}

	public function isToday()
	{
		return $this->toDateString() === static::now($this->getTimezone())->toDateString();
	}

	public function isTomorrow()
	{
		return $this->toDateString() === static::tomorrow($this->getTimezone())->toDateString();
	}

	public function isNextWeek()
	{
		return $this->weekOfYear === static::now($this->getTimezone())->addWeek()->weekOfYear;
	}

	public function isLastWeek()
	{
		return $this->weekOfYear === static::now($this->getTimezone())->subWeek()->weekOfYear;
	}

	public function isNextMonth()
	{
		return $this->month === static::now($this->getTimezone())->addMonthNoOverflow()->month;
	}

	public function isLastMonth()
	{
		return $this->month === static::now($this->getTimezone())->subMonthNoOverflow()->month;
	}

	public function isNextYear()
	{
		return $this->year === static::now($this->getTimezone())->addYear()->year;
	}

	public function isLastYear()
	{
		return $this->year === static::now($this->getTimezone())->subYear()->year;
	}

	public function isFuture()
	{
		return $this->gt(static::now($this->getTimezone()));
	}

	public function isPast()
	{
		return $this->lt(static::now($this->getTimezone()));
	}

	public function isLeapYear()
	{
		return $this->format('L') === '1';
	}

	public function isLongYear()
	{
		return static::create($this->year, 12, 28, 0, 0, 0, $this->tz)->weekOfYear === 53;
	}

	public function isSameAs($format, Carbon $dt = NULL)
	{
		$dt = $dt ?: static::now($this->tz);
		return $this->format($format) === $dt->format($format);
	}

	public function isCurrentYear()
	{
		return $this->isSameYear();
	}

	public function isSameYear(Carbon $dt = NULL)
	{
		return $this->isSameAs('Y', $dt);
	}

	public function isCurrentMonth()
	{
		return $this->isSameMonth();
	}

	public function isSameMonth(Carbon $dt = NULL, $ofSameYear = false)
	{
		$format = ($ofSameYear ? 'Y-m' : 'm');
		return $this->isSameAs($format, $dt);
	}

	public function isSameDay(Carbon $dt)
	{
		return $this->toDateString() === $dt->toDateString();
	}

	public function isSunday()
	{
		return $this->dayOfWeek === static::SUNDAY;
	}

	public function isMonday()
	{
		return $this->dayOfWeek === static::MONDAY;
	}

	public function isTuesday()
	{
		return $this->dayOfWeek === static::TUESDAY;
	}

	public function isWednesday()
	{
		return $this->dayOfWeek === static::WEDNESDAY;
	}

	public function isThursday()
	{
		return $this->dayOfWeek === static::THURSDAY;
	}

	public function isFriday()
	{
		return $this->dayOfWeek === static::FRIDAY;
	}

	public function isSaturday()
	{
		return $this->dayOfWeek === static::SATURDAY;
	}

	public function addYears($value)
	{
		return $this->modify((int) $value . ' year');
	}

	public function addYear($value = 1)
	{
		return $this->addYears($value);
	}

	public function subYear($value = 1)
	{
		return $this->subYears($value);
	}

	public function subYears($value)
	{
		return $this->addYears(-1 * $value);
	}

	public function addQuarters($value)
	{
		return $this->addMonths(static::MONTHS_PER_QUARTER * $value);
	}

	public function addQuarter($value = 1)
	{
		return $this->addQuarters($value);
	}

	public function subQuarter($value = 1)
	{
		return $this->subQuarters($value);
	}

	public function subQuarters($value)
	{
		return $this->addQuarters(-1 * $value);
	}

	public function addCenturies($value)
	{
		return $this->addYears(static::YEARS_PER_CENTURY * $value);
	}

	public function addCentury($value = 1)
	{
		return $this->addCenturies($value);
	}

	public function subCentury($value = 1)
	{
		return $this->subCenturies($value);
	}

	public function subCenturies($value)
	{
		return $this->addCenturies(-1 * $value);
	}

	public function addMonths($value)
	{
		if (static::shouldOverflowMonths()) {
			return $this->addMonthsWithOverflow($value);
		}

		return $this->addMonthsNoOverflow($value);
	}

	public function addMonth($value = 1)
	{
		return $this->addMonths($value);
	}

	public function subMonth($value = 1)
	{
		return $this->subMonths($value);
	}

	public function subMonths($value)
	{
		return $this->addMonths(-1 * $value);
	}

	public function addMonthsWithOverflow($value)
	{
		return $this->modify((int) $value . ' month');
	}

	public function addMonthWithOverflow($value = 1)
	{
		return $this->addMonthsWithOverflow($value);
	}

	public function subMonthWithOverflow($value = 1)
	{
		return $this->subMonthsWithOverflow($value);
	}

	public function subMonthsWithOverflow($value)
	{
		return $this->addMonthsWithOverflow(-1 * $value);
	}

	public function addMonthsNoOverflow($value)
	{
		$day = $this->day;
		$this->modify((int) $value . ' month');

		if ($day !== $this->day) {
			$this->modify('last day of previous month');
		}

		return $this;
	}

	public function addMonthNoOverflow($value = 1)
	{
		return $this->addMonthsNoOverflow($value);
	}

	public function subMonthNoOverflow($value = 1)
	{
		return $this->subMonthsNoOverflow($value);
	}

	public function subMonthsNoOverflow($value)
	{
		return $this->addMonthsNoOverflow(-1 * $value);
	}

	public function addDays($value)
	{
		return $this->modify((int) $value . ' day');
	}

	public function addDay($value = 1)
	{
		return $this->addDays($value);
	}

	public function subDay($value = 1)
	{
		return $this->subDays($value);
	}

	public function subDays($value)
	{
		return $this->addDays(-1 * $value);
	}

	public function addWeekdays($value)
	{
		$t = $this->toTimeString();
		$this->modify((int) $value . ' weekday');
		return $this->setTimeFromTimeString($t);
	}

	public function addWeekday($value = 1)
	{
		return $this->addWeekdays($value);
	}

	public function subWeekday($value = 1)
	{
		return $this->subWeekdays($value);
	}

	public function subWeekdays($value)
	{
		return $this->addWeekdays(-1 * $value);
	}

	public function addWeeks($value)
	{
		return $this->modify((int) $value . ' week');
	}

	public function addWeek($value = 1)
	{
		return $this->addWeeks($value);
	}

	public function subWeek($value = 1)
	{
		return $this->subWeeks($value);
	}

	public function subWeeks($value)
	{
		return $this->addWeeks(-1 * $value);
	}

	public function addHours($value)
	{
		return $this->modify((int) $value . ' hour');
	}

	public function addHour($value = 1)
	{
		return $this->addHours($value);
	}

	public function subHour($value = 1)
	{
		return $this->subHours($value);
	}

	public function subHours($value)
	{
		return $this->addHours(-1 * $value);
	}

	public function addMinutes($value)
	{
		return $this->modify((int) $value . ' minute');
	}

	public function addMinute($value = 1)
	{
		return $this->addMinutes($value);
	}

	public function subMinute($value = 1)
	{
		return $this->subMinutes($value);
	}

	public function subMinutes($value)
	{
		return $this->addMinutes(-1 * $value);
	}

	public function addSeconds($value)
	{
		return $this->modify((int) $value . ' second');
	}

	public function addSecond($value = 1)
	{
		return $this->addSeconds($value);
	}

	public function subSecond($value = 1)
	{
		return $this->subSeconds($value);
	}

	public function subSeconds($value)
	{
		return $this->addSeconds(-1 * $value);
	}

	public function diffInYears(Carbon $dt = NULL, $abs = true)
	{
		$dt = $dt ?: static::now($this->getTimezone());
		return (int) $this->diff($dt, $abs)->format('%r%y');
	}

	public function diffInMonths(Carbon $dt = NULL, $abs = true)
	{
		$dt = $dt ?: static::now($this->getTimezone());
		return ($this->diffInYears($dt, $abs) * static::MONTHS_PER_YEAR) + (int) $this->diff($dt, $abs)->format('%r%m');
	}

	public function diffInWeeks(Carbon $dt = NULL, $abs = true)
	{
		return (int) ($this->diffInDays($dt, $abs) / static::DAYS_PER_WEEK);
	}

	public function diffInDays(Carbon $dt = NULL, $abs = true)
	{
		$dt = $dt ?: static::now($this->getTimezone());
		return (int) $this->diff($dt, $abs)->format('%r%a');
	}

	public function diffInDaysFiltered(\Closure $callback, Carbon $dt = NULL, $abs = true)
	{
		return $this->diffFiltered(CarbonInterval::day(), $callback, $dt, $abs);
	}

	public function diffInHoursFiltered(\Closure $callback, Carbon $dt = NULL, $abs = true)
	{
		return $this->diffFiltered(CarbonInterval::hour(), $callback, $dt, $abs);
	}

	public function diffFiltered(CarbonInterval $ci, \Closure $callback, Carbon $dt = NULL, $abs = true)
	{
		$start = $this;
		$end = $dt ?: static::now($this->getTimezone());
		$inverse = false;

		if ($end < $start) {
			$start = $end;
			$end = $this;
			$inverse = true;
		}

		$period = new \DatePeriod($start, $ci, $end);
		$vals = array_filter(iterator_to_array($period), function(\DateTime $date) use($callback) {
			return call_user_func($callback, Carbon::instance($date));
		});
		$diff = count($vals);
		return $inverse && !$abs ? 0 - $diff : $diff;
	}

	public function diffInWeekdays(Carbon $dt = NULL, $abs = true)
	{
		return $this->diffInDaysFiltered(function(Carbon $date) {
			return $date->isWeekday();
		}, $dt, $abs);
	}

	public function diffInWeekendDays(Carbon $dt = NULL, $abs = true)
	{
		return $this->diffInDaysFiltered(function(Carbon $date) {
			return $date->isWeekend();
		}, $dt, $abs);
	}

	public function diffInHours(Carbon $dt = NULL, $abs = true)
	{
		return (int) ($this->diffInSeconds($dt, $abs) / static::SECONDS_PER_MINUTE / static::MINUTES_PER_HOUR);
	}

	public function diffInMinutes(Carbon $dt = NULL, $abs = true)
	{
		return (int) ($this->diffInSeconds($dt, $abs) / static::SECONDS_PER_MINUTE);
	}

	public function diffInSeconds(Carbon $dt = NULL, $abs = true)
	{
		$dt = $dt ?: static::now($this->getTimezone());
		$value = $dt->getTimestamp() - $this->getTimestamp();
		return $abs ? abs($value) : $value;
	}

	public function secondsSinceMidnight()
	{
		return $this->diffInSeconds($this->copy()->startOfDay());
	}

	public function secondsUntilEndOfDay()
	{
		return $this->diffInSeconds($this->copy()->endOfDay());
	}

	public function diffForHumans(Carbon $other = NULL, $absolute = false, $short = false)
	{
		$isNow = $other === null;

		if ($isNow) {
			$other = static::now($this->getTimezone());
		}

		$diffInterval = $this->diff($other);

		switch (true) {
		case 0 < $diffInterval->y:
			$unit = ($short ? 'y' : 'year');
			$count = $diffInterval->y;
			break;

		case 0 < $diffInterval->m:
			$unit = ($short ? 'm' : 'month');
			$count = $diffInterval->m;
			break;

		case 0 < $diffInterval->d:
			$unit = ($short ? 'd' : 'day');
			$count = $diffInterval->d;

			if (static::DAYS_PER_WEEK <= $count) {
				$unit = ($short ? 'w' : 'week');
				$count = (int) ($count / static::DAYS_PER_WEEK);
			}

			break;

		case 0 < $diffInterval->h:
			$unit = ($short ? 'h' : 'hour');
			$count = $diffInterval->h;
			break;

		case 0 < $diffInterval->i:
			$unit = ($short ? 'min' : 'minute');
			$count = $diffInterval->i;
			break;

		default:
			$count = $diffInterval->s;
			$unit = ($short ? 's' : 'second');
			break;
		}

		if ($count === 0) {
			$count = 1;
		}

		$time = static::translator()->transChoice($unit, $count, array(':count' => $count));

		if ($absolute) {
			return $time;
		}

		$isFuture = $diffInterval->invert === 1;
		$transId = ($isNow ? ($isFuture ? 'from_now' : 'ago') : ($isFuture ? 'after' : 'before'));
		$tryKeyExists = $unit . '_' . $transId;

		if ($tryKeyExists !== static::translator()->transChoice($tryKeyExists, $count)) {
			$time = static::translator()->transChoice($tryKeyExists, $count, array(':count' => $count));
		}

		return static::translator()->trans($transId, array(':time' => $time));
	}

	public function startOfDay()
	{
		return $this->setTime(0, 0, 0);
	}

	public function endOfDay()
	{
		return $this->setTime(23, 59, 59);
	}

	public function startOfMonth()
	{
		return $this->setDateTime($this->year, $this->month, 1, 0, 0, 0);
	}

	public function endOfMonth()
	{
		return $this->setDateTime($this->year, $this->month, $this->daysInMonth, 23, 59, 59);
	}

	public function startOfQuarter()
	{
		$month = (($this->quarter - 1) * static::MONTHS_PER_QUARTER) + 1;
		return $this->setDateTime($this->year, $month, 1, 0, 0, 0);
	}

	public function endOfQuarter()
	{
		return $this->startOfQuarter()->addMonths(static::MONTHS_PER_QUARTER - 1)->endOfMonth();
	}

	public function startOfYear()
	{
		return $this->setDateTime($this->year, 1, 1, 0, 0, 0);
	}

	public function endOfYear()
	{
		return $this->setDateTime($this->year, 12, 31, 23, 59, 59);
	}

	public function startOfDecade()
	{
		$year = $this->year - ($this->year % static::YEARS_PER_DECADE);
		return $this->setDateTime($year, 1, 1, 0, 0, 0);
	}

	public function endOfDecade()
	{
		$year = (($this->year - ($this->year % static::YEARS_PER_DECADE)) + static::YEARS_PER_DECADE) - 1;
		return $this->setDateTime($year, 12, 31, 23, 59, 59);
	}

	public function startOfCentury()
	{
		$year = $this->year - (($this->year - 1) % static::YEARS_PER_CENTURY);
		return $this->setDateTime($year, 1, 1, 0, 0, 0);
	}

	public function endOfCentury()
	{
		$year = ($this->year - 1 - (($this->year - 1) % static::YEARS_PER_CENTURY)) + static::YEARS_PER_CENTURY;
		return $this->setDateTime($year, 12, 31, 23, 59, 59);
	}

	public function startOfWeek()
	{
		while ($this->dayOfWeek !== static::$weekStartsAt) {
			$this->subDay();
		}

		return $this->startOfDay();
	}

	public function endOfWeek()
	{
		while ($this->dayOfWeek !== static::$weekEndsAt) {
			$this->addDay();
		}

		return $this->endOfDay();
	}

	public function next($dayOfWeek = NULL)
	{
		if ($dayOfWeek === null) {
			$dayOfWeek = $this->dayOfWeek;
		}

		return $this->startOfDay()->modify('next ' . static::$days[$dayOfWeek]);
	}

	private function nextOrPreviousDay($weekday = true, $forward = true)
	{
		$step = ($forward ? 1 : -1);

		do {
			$this->addDay($step);
		} while ($weekday ? $this->isWeekend() : $this->isWeekday());

		return $this;
	}

	public function nextWeekday()
	{
		return $this->nextOrPreviousDay();
	}

	public function previousWeekday()
	{
		return $this->nextOrPreviousDay(true, false);
	}

	public function nextWeekendDay()
	{
		return $this->nextOrPreviousDay(false);
	}

	public function previousWeekendDay()
	{
		return $this->nextOrPreviousDay(false, false);
	}

	public function previous($dayOfWeek = NULL)
	{
		if ($dayOfWeek === null) {
			$dayOfWeek = $this->dayOfWeek;
		}

		return $this->startOfDay()->modify('last ' . static::$days[$dayOfWeek]);
	}

	public function firstOfMonth($dayOfWeek = NULL)
	{
		$this->startOfDay();

		if ($dayOfWeek === null) {
			return $this->day(1);
		}

		return $this->modify('first ' . static::$days[$dayOfWeek] . ' of ' . $this->format('F') . ' ' . $this->year);
	}

	public function lastOfMonth($dayOfWeek = NULL)
	{
		$this->startOfDay();

		if ($dayOfWeek === null) {
			return $this->day($this->daysInMonth);
		}

		return $this->modify('last ' . static::$days[$dayOfWeek] . ' of ' . $this->format('F') . ' ' . $this->year);
	}

	public function nthOfMonth($nth, $dayOfWeek)
	{
		$dt = $this->copy()->firstOfMonth();
		$check = $dt->format('Y-m');
		$dt->modify('+' . $nth . ' ' . static::$days[$dayOfWeek]);
		return $dt->format('Y-m') === $check ? $this->modify($dt) : false;
	}

	public function firstOfQuarter($dayOfWeek = NULL)
	{
		return $this->setDate($this->year, ($this->quarter * static::MONTHS_PER_QUARTER) - 2, 1)->firstOfMonth($dayOfWeek);
	}

	public function lastOfQuarter($dayOfWeek = NULL)
	{
		return $this->setDate($this->year, $this->quarter * static::MONTHS_PER_QUARTER, 1)->lastOfMonth($dayOfWeek);
	}

	public function nthOfQuarter($nth, $dayOfWeek)
	{
		$dt = $this->copy()->day(1)->month($this->quarter * static::MONTHS_PER_QUARTER);
		$lastMonth = $dt->month;
		$year = $dt->year;
		$dt->firstOfQuarter()->modify('+' . $nth . ' ' . static::$days[$dayOfWeek]);
		return ($lastMonth < $dt->month) || ($year !== $dt->year) ? false : $this->modify($dt);
	}

	public function firstOfYear($dayOfWeek = NULL)
	{
		return $this->month(1)->firstOfMonth($dayOfWeek);
	}

	public function lastOfYear($dayOfWeek = NULL)
	{
		return $this->month(static::MONTHS_PER_YEAR)->lastOfMonth($dayOfWeek);
	}

	public function nthOfYear($nth, $dayOfWeek)
	{
		$dt = $this->copy()->firstOfYear()->modify('+' . $nth . ' ' . static::$days[$dayOfWeek]);
		return $this->year === $dt->year ? $this->modify($dt) : false;
	}

	public function average(Carbon $dt = NULL)
	{
		$dt = $dt ?: static::now($this->getTimezone());
		return $this->addSeconds((int) ($this->diffInSeconds($dt, false) / 2));
	}

	public function isBirthday(Carbon $dt = NULL)
	{
		return $this->isSameAs('md', $dt);
	}

	public function modify($modify)
	{
		if ($this->local) {
			return parent::modify($modify);
		}

		$timezone = $this->getTimezone();
		$this->setTimezone('UTC');
		$instance = parent::modify($modify);
		$this->setTimezone($timezone);
		return $instance;
	}

	public function serialize()
	{
		return serialize($this);
	}

	static public function fromSerialized($value)
	{
		$instance = @unserialize($value);

		if (!$instance instanceof static) {
			throw new \InvalidArgumentException('Invalid serialized value.');
		}

		return $instance;
	}
}

?>
