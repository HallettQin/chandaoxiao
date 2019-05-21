<?php
//cgxlm
namespace Illuminate\Database\Eloquent\Relations;

class MorphPivot extends Pivot
{
	/**
     * The type of the polymorphic relation.
     *
     * Explicitly define this so it's not included in saved attributes.
     *
     * @var string
     */
	protected $morphType;
	/**
     * The value of the polymorphic relation.
     *
     * Explicitly define this so it's not included in saved attributes.
     *
     * @var string
     */
	protected $morphClass;

	protected function setKeysForSaveQuery(\Illuminate\Database\Eloquent\Builder $query)
	{
		$query->where($this->morphType, $this->morphClass);
		return parent::setKeysForSaveQuery($query);
	}

	public function delete()
	{
		$query = $this->getDeleteQuery();
		$query->where($this->morphType, $this->morphClass);
		return $query->delete();
	}

	public function setMorphType($morphType)
	{
		$this->morphType = $morphType;
		return $this;
	}

	public function setMorphClass($morphClass)
	{
		$this->morphClass = $morphClass;
		return $this;
	}
}

?>
