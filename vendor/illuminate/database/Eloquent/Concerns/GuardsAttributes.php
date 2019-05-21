<?php
//cgxlm
namespace Illuminate\Database\Eloquent\Concerns;

trait GuardsAttributes
{
	/**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array();
	/**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
	protected $guarded = array('*');
	/**
     * Indicates if all mass assignment is enabled.
     *
     * @var bool
     */
	static protected $unguarded = false;

	public function getFillable()
	{
		return $this->fillable;
	}

	public function fillable(array $fillable)
	{
		$this->fillable = $fillable;
		return $this;
	}

	public function getGuarded()
	{
		return $this->guarded;
	}

	public function guard(array $guarded)
	{
		$this->guarded = $guarded;
		return $this;
	}

	static public function unguard($state = true)
	{
		static::$unguarded = $state;
	}

	static public function reguard()
	{
		static::$unguarded = false;
	}

	static public function isUnguarded()
	{
		return static::$unguarded;
	}

	static public function unguarded( $callback)
	{
		if (static::$unguarded) {
			return $callback();
		}

		static::unguard();
/* [31m * TODO FAST_CALL[0m */

		goto label21;
/* [31m * TODO FAST_RET[0m */

		goto label20;
		static::reguard();
label20:
		return NULL;
/* [31m * TODO FAST_CALL[0m */
label21:
		return $callback();
	}

	public function isFillable($key)
	{
		if (static::$unguarded) {
			return true;
		}

		if (in_array($key, $this->getFillable())) {
			return true;
		}

		if ($this->isGuarded($key)) {
			return false;
		}

		return !$this->getFillable() && !\Illuminate\Support\Str::startsWith($key, '_');
	}

	public function isGuarded($key)
	{
		return in_array($key, $this->getGuarded()) || ($this->getGuarded() == array('*'));
	}

	public function totallyGuarded()
	{
		return (count($this->getFillable()) == 0) && ($this->getGuarded() == array('*'));
	}

	protected function fillableFromArray(array $attributes)
	{
		if ((0 < count($this->getFillable())) && !static::$unguarded) {
			return array_intersect_key($attributes, array_flip($this->getFillable()));
		}

		return $attributes;
	}
}


?>
