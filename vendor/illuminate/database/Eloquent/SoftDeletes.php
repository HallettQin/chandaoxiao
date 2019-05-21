<?php
//cgxlm
namespace Illuminate\Database\Eloquent;

trait SoftDeletes
{
	/**
     * Indicates if the model is currently force deleting.
     *
     * @var bool
     */
	protected $forceDeleting = false;

	static public function bootSoftDeletes()
	{
		static::addGlobalScope(new SoftDeletingScope());
	}

	public function forceDelete()
	{
		$this->forceDeleting = true;
		$deleted = $this->delete();
		$this->forceDeleting = false;
		return $deleted;
	}

	protected function performDeleteOnModel()
	{
		if ($this->forceDeleting) {
			return $this->newQueryWithoutScopes()->where($this->getKeyName(), $this->getKey())->forceDelete();
		}

		return $this->runSoftDelete();
	}

	protected function runSoftDelete()
	{
		$query = $this->newQueryWithoutScopes()->where($this->getKeyName(), $this->getKey());
		$time = $this->freshTimestamp();
		$columns = array($this->getDeletedAtColumn() => $this->fromDateTime($time));
		$this->{$this->getDeletedAtColumn()} = $time;

		if ($this->timestamps) {
			$this->{$this->getUpdatedAtColumn()} = $time;
			$columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
		}

		$query->update($columns);
	}

	public function restore()
	{
		if ($this->fireModelEvent('restoring') === false) {
			return false;
		}

		$this->{$this->getDeletedAtColumn()} = null;
		$this->exists = true;
		$result = $this->save();
		$this->fireModelEvent('restored', false);
		return $result;
	}

	public function trashed()
	{
		return !is_null($this->{$this->getDeletedAtColumn()});
	}

	static public function restoring($callback)
	{
		static::registerModelEvent('restoring', $callback);
	}

	static public function restored($callback)
	{
		static::registerModelEvent('restored', $callback);
	}

	public function isForceDeleting()
	{
		return $this->forceDeleting;
	}

	public function getDeletedAtColumn()
	{
		return defined('static::DELETED_AT') ? static::DELETED_AT : 'deleted_at';
	}

	public function getQualifiedDeletedAtColumn()
	{
		return $this->getTable() . '.' . $this->getDeletedAtColumn();
	}
}


?>
