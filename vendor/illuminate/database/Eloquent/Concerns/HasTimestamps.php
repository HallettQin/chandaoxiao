<?php
//cgxlm
namespace Illuminate\Database\Eloquent\Concerns;

trait HasTimestamps
{
	/**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
	public $timestamps = true;

	public function touch()
	{
		if (!$this->usesTimestamps()) {
			return false;
		}

		$this->updateTimestamps();
		return $this->save();
	}

	protected function updateTimestamps()
	{
		$time = $this->freshTimestamp();

		if (!$this->isDirty(static::UPDATED_AT)) {
			$this->setUpdatedAt($time);
		}

		if (!$this->exists && !$this->isDirty(static::CREATED_AT)) {
			$this->setCreatedAt($time);
		}
	}

	public function setCreatedAt($value)
	{
		$this->{static::CREATED_AT} = $value;
		return $this;
	}

	public function setUpdatedAt($value)
	{
		$this->{static::UPDATED_AT} = $value;
		return $this;
	}

	public function freshTimestamp()
	{
		return new \Carbon\Carbon();
	}

	public function freshTimestampString()
	{
		return $this->fromDateTime($this->freshTimestamp());
	}

	public function usesTimestamps()
	{
		return $this->timestamps;
	}

	public function getCreatedAtColumn()
	{
		return static::CREATED_AT;
	}

	public function getUpdatedAtColumn()
	{
		return static::UPDATED_AT;
	}
}


?>
