<?php
//cgxlm
namespace Illuminate\Database\Eloquent\Concerns;

trait HasAttributes
{
	/**
     * The model's attributes.
     *
     * @var array
     */
	protected $attributes = array();
	/**
     * The model attribute's original state.
     *
     * @var array
     */
	protected $original = array();
	/**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
	protected $casts = array();
	/**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
	protected $dates = array();
	/**
     * The storage format of the model's date columns.
     *
     * @var string
     */
	protected $dateFormat;
	/**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
	protected $appends = array();
	/**
     * Indicates whether attributes are snake cased on arrays.
     *
     * @var bool
     */
	static public $snakeAttributes = true;
	/**
     * The cache of the mutated attributes for each class.
     *
     * @var array
     */
	static protected $mutatorCache = array();

	public function attributesToArray()
	{
		$attributes = $this->addDateAttributesToArray($attributes = $this->getArrayableAttributes());
		$attributes = $this->addMutatedAttributesToArray($attributes, $mutatedAttributes = $this->getMutatedAttributes());
		$attributes = $this->addCastAttributesToArray($attributes, $mutatedAttributes);

		foreach ($this->getArrayableAppends() as $key) {
			$attributes[$key] = $this->mutateAttributeForArray($key, null);
		}

		return $attributes;
	}

	protected function addDateAttributesToArray(array $attributes)
	{
		foreach ($this->getDates() as $key) {
			if (!isset($attributes[$key])) {
				continue;
			}

			$attributes[$key] = $this->serializeDate($this->asDateTime($attributes[$key]));
		}

		return $attributes;
	}

	protected function addMutatedAttributesToArray(array $attributes, array $mutatedAttributes)
	{
		foreach ($mutatedAttributes as $key) {
			if (!array_key_exists($key, $attributes)) {
				continue;
			}

			$attributes[$key] = $this->mutateAttributeForArray($key, $attributes[$key]);
		}

		return $attributes;
	}

	protected function addCastAttributesToArray(array $attributes, array $mutatedAttributes)
	{
		foreach ($this->getCasts() as $key => $value) {
			if (!array_key_exists($key, $attributes) || in_array($key, $mutatedAttributes)) {
				continue;
			}

			$attributes[$key] = $this->castAttribute($key, $attributes[$key]);
			if ($attributes[$key] && (($value === 'date') || ($value === 'datetime'))) {
				$attributes[$key] = $this->serializeDate($attributes[$key]);
			}
		}

		return $attributes;
	}

	protected function getArrayableAttributes()
	{
		return $this->getArrayableItems($this->attributes);
	}

	protected function getArrayableAppends()
	{
		if (!count($this->appends)) {
			return array();
		}

		return $this->getArrayableItems(array_combine($this->appends, $this->appends));
	}

	public function relationsToArray()
	{
		$attributes = array();

		foreach ($this->getArrayableRelations() as $key => $value) {
			if ($value instanceof \Illuminate\Contracts\Support\Arrayable) {
				$relation = $value->toArray();
			}
			else if (is_null($value)) {
				$relation = $value;
			}

			if (static::$snakeAttributes) {
				$key = \Illuminate\Support\Str::snake($key);
			}

			if (isset($relation) || is_null($value)) {
				$attributes[$key] = $relation;
			}

			unset($relation);
		}

		return $attributes;
	}

	protected function getArrayableRelations()
	{
		return $this->getArrayableItems($this->relations);
	}

	protected function getArrayableItems(array $values)
	{
		if (0 < count($this->getVisible())) {
			$values = array_intersect_key($values, array_flip($this->getVisible()));
		}

		if (0 < count($this->getHidden())) {
			$values = array_diff_key($values, array_flip($this->getHidden()));
		}

		return $values;
	}

	public function getAttribute($key)
	{
		if (!$key) {
			return NULL;
		}

		if (array_key_exists($key, $this->attributes) || $this->hasGetMutator($key)) {
			return $this->getAttributeValue($key);
		}

		if (method_exists(self::class, $key)) {
			return NULL;
		}

		return $this->getRelationValue($key);
	}

	public function getAttributeValue($key)
	{
		$value = $this->getAttributeFromArray($key);

		if ($this->hasGetMutator($key)) {
			return $this->mutateAttribute($key, $value);
		}

		if ($this->hasCast($key)) {
			return $this->castAttribute($key, $value);
		}

		if (in_array($key, $this->getDates()) && !is_null($value)) {
			return $this->asDateTime($value);
		}

		return $value;
	}

	protected function getAttributeFromArray($key)
	{
		if (isset($this->attributes[$key])) {
			return $this->attributes[$key];
		}
	}

	public function getRelationValue($key)
	{
		if ($this->relationLoaded($key)) {
			return $this->relations[$key];
		}

		if (method_exists($this, $key)) {
			return $this->getRelationshipFromMethod($key);
		}
	}

	protected function getRelationshipFromMethod($method)
	{
		$relation = $this->$method();

		if (!$relation instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
			throw new \LogicException('Relationship method must return an object of type ' . 'Illuminate\\Database\\Eloquent\\Relations\\Relation');
		}

		return tap($relation->getResults(), function($results) use($method) {
			$this->setRelation($method, $results);
		});
	}

	public function hasGetMutator($key)
	{
		return method_exists($this, 'get' . \Illuminate\Support\Str::studly($key) . 'Attribute');
	}

	protected function mutateAttribute($key, $value)
	{
		return $this->{'get' . \Illuminate\Support\Str::studly($key) . 'Attribute'}($value);
	}

	protected function mutateAttributeForArray($key, $value)
	{
		$value = $this->mutateAttribute($key, $value);
		return $value instanceof \Illuminate\Contracts\Support\Arrayable ? $value->toArray() : $value;
	}

	protected function castAttribute($key, $value)
	{
		if (is_null($value)) {
			return $value;
		}

		switch ($this->getCastType($key)) {
		case 'int':
		case 'integer':
			return (int) $value;
		case 'real':
		case 'float':
		case 'double':
			return (double) $value;
		case 'string':
			return (string) $value;
		case 'bool':
		case 'boolean':
			return (bool) $value;
		case 'object':
			return $this->fromJson($value, true);
		case 'array':
		case 'json':
			return $this->fromJson($value);
		case 'collection':
			return new \Illuminate\Support\Collection($this->fromJson($value));
		case 'date':
			return $this->asDate($value);
		case 'datetime':
			return $this->asDateTime($value);
		case 'timestamp':
			return $this->asTimestamp($value);
		default:
			return $value;
		}
	}

	protected function getCastType($key)
	{
/* [31m * TODO SEPARATE[0m */
		return trim(strtolower($this->getCasts()[$key]));
	}

	public function setAttribute($key, $value)
	{
		if ($this->hasSetMutator($key)) {
			$method = 'set' . \Illuminate\Support\Str::studly($key) . 'Attribute';
			return $this->$method($value);
		}
		else {
			if ($value && $this->isDateAttribute($key)) {
				$value = $this->fromDateTime($value);
			}
		}

		if ($this->isJsonCastable($key) && !is_null($value)) {
			$value = $this->castAttributeAsJson($key, $value);
		}

		if (\Illuminate\Support\Str::contains($key, '->')) {
			return $this->fillJsonAttribute($key, $value);
		}

		$this->attributes[$key] = $value;
		return $this;
	}

	public function hasSetMutator($key)
	{
		return method_exists($this, 'set' . \Illuminate\Support\Str::studly($key) . 'Attribute');
	}

	protected function isDateAttribute($key)
	{
		return in_array($key, $this->getDates()) || $this->isDateCastable($key);
	}

	public function fillJsonAttribute($key, $value)
	{
		list($key, $path) = explode('->', $key, 2);
		$this->attributes[$key] = $this->asJson($this->getArrayAttributeWithValue($path, $key, $value));
		return $this;
	}

	protected function getArrayAttributeWithValue($path, $key, $value)
	{
		return tap($this->getArrayAttributeByKey($key), function(&$array) use($path, $value) {
			\Illuminate\Support\Arr::set($array, str_replace('->', '.', $path), $value);
		});
	}

	protected function getArrayAttributeByKey($key)
	{
		return isset($this->attributes[$key]) ? $this->fromJson($this->attributes[$key]) : array();
	}

	protected function castAttributeAsJson($key, $value)
	{
		$value = $this->asJson($value);

		if ($value === false) {
			throw \Illuminate\Database\Eloquent\JsonEncodingException::forAttribute($this, $key, json_last_error_msg());
		}

		return $value;
	}

	protected function asJson($value)
	{
		return json_encode($value);
	}

	public function fromJson($value, $asObject = false)
	{
		return json_decode($value, !$asObject);
	}

	protected function asDate($value)
	{
		return $this->asDateTime($value)->startOfDay();
	}

	protected function asDateTime($value)
	{
		if ($value instanceof \Carbon\Carbon) {
			return $value;
		}

		if ($value instanceof \DateTimeInterface) {
			return new \Carbon\Carbon($value->format('Y-m-d H:i:s.u'), $value->getTimezone());
		}

		if (is_numeric($value)) {
			return \Carbon\Carbon::createFromTimestamp($value);
		}

		if ($this->isStandardDateFormat($value)) {
			return \Carbon\Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
		}

		return \Carbon\Carbon::createFromFormat($this->getDateFormat(), $value);
	}

	protected function isStandardDateFormat($value)
	{
		return preg_match('/^(\\d{4})-(\\d{1,2})-(\\d{1,2})$/', $value);
	}

	public function fromDateTime($value)
	{
		return $this->asDateTime($value)->format($this->getDateFormat());
	}

	protected function asTimestamp($value)
	{
		return $this->asDateTime($value)->getTimestamp();
	}

	protected function serializeDate(\DateTimeInterface $date)
	{
		return $date->format($this->getDateFormat());
	}

	public function getDates()
	{
		$defaults = array(static::CREATED_AT, static::UPDATED_AT);
		return $this->usesTimestamps() ? array_merge($this->dates, $defaults) : $this->dates;
	}

	protected function getDateFormat()
	{
		return $this->dateFormat ?: $this->getConnection()->getQueryGrammar()->getDateFormat();
	}

	public function setDateFormat($format)
	{
		$this->dateFormat = $format;
		return $this;
	}

	public function hasCast($key, $types = NULL)
	{
		if (array_key_exists($key, $this->getCasts())) {
			return $types ? in_array($this->getCastType($key), (array) $types, true) : true;
		}

		return false;
	}

	public function getCasts()
	{
		if ($this->getIncrementing()) {
			return array_merge(array($this->getKeyName() => $this->getKeyType()), $this->casts);
		}

		return $this->casts;
	}

	protected function isDateCastable($key)
	{
		return $this->hasCast($key, array('date', 'datetime'));
	}

	protected function isJsonCastable($key)
	{
		return $this->hasCast($key, array('array', 'json', 'object', 'collection'));
	}

	public function getAttributes()
	{
		return $this->attributes;
	}

	public function setRawAttributes(array $attributes, $sync = false)
	{
		$this->attributes = $attributes;

		if ($sync) {
			$this->syncOriginal();
		}

		return $this;
	}

	public function getOriginal($key = NULL, $default = NULL)
	{
		return \Illuminate\Support\Arr::get($this->original, $key, $default);
	}

	public function syncOriginal()
	{
		$this->original = $this->attributes;
		return $this;
	}

	public function syncOriginalAttribute($attribute)
	{
		$this->original[$attribute] = $this->attributes[$attribute];
		return $this;
	}

	public function isDirty($attributes = NULL)
	{
		$dirty = $this->getDirty();

		if (is_null($attributes)) {
			return 0 < count($dirty);
		}

		$attributes = (is_array($attributes) ? $attributes : func_get_args());

		foreach ($attributes as $attribute) {
			if (array_key_exists($attribute, $dirty)) {
				return true;
			}
		}

		return false;
	}

	public function isClean($attributes = NULL)
	{
		return !$this->isDirty(...func_get_args());
	}

	public function getDirty()
	{
		$dirty = array();

		foreach ($this->attributes as $key => $value) {
			if (!array_key_exists($key, $this->original)) {
				$dirty[$key] = $value;
			}
			else {
				if (($value !== $this->original[$key]) && !$this->originalIsNumericallyEquivalent($key)) {
					$dirty[$key] = $value;
				}
			}
		}

		return $dirty;
	}

	protected function originalIsNumericallyEquivalent($key)
	{
		$current = $this->attributes[$key];
		$original = $this->original[$key];
		return is_numeric($current) && is_numeric($original) && (strcmp((string) $current, (string) $original) === 0);
	}

	public function append($attributes)
	{
		$this->appends = array_unique(array_merge($this->appends, is_string($attributes) ? func_get_args() : $attributes));
		return $this;
	}

	public function setAppends(array $appends)
	{
		$this->appends = $appends;
		return $this;
	}

	public function getMutatedAttributes()
	{
		$class = static::class;

		if (!isset(static::$mutatorCache[$class])) {
			static::cacheMutatedAttributes($class);
		}

		return static::$mutatorCache[$class];
	}

	static public function cacheMutatedAttributes($class)
	{
		static::$mutatorCache[$class] = collect(static::getMutatorMethods($class))->map(function($match) {
			return lcfirst(static::$snakeAttributes ? \Illuminate\Support\Str::snake($match) : $match);
		})->all();
	}

	static protected function getMutatorMethods($class)
	{
		preg_match_all('/(?<=^|;)get([^;]+?)Attribute(;|$)/', implode(';', get_class_methods($class)), $matches);
		return $matches[1];
	}
}


?>
