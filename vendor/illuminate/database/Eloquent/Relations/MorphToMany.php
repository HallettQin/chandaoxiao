<?php
//cgxlm
namespace Illuminate\Database\Eloquent\Relations;

class MorphToMany extends BelongsToMany
{
	/**
     * The type of the polymorphic relation.
     *
     * @var string
     */
	protected $morphType;
	/**
     * The class name of the morph type constraint.
     *
     * @var string
     */
	protected $morphClass;
	/**
     * Indicates if we are connecting the inverse of the relation.
     *
     * This primarily affects the morphClass constraint.
     *
     * @var bool
     */
	protected $inverse;

	public function __construct(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $name, $table, $foreignKey, $relatedKey, $relationName = NULL, $inverse = false)
	{
		$this->inverse = $inverse;
		$this->morphType = $name . '_type';
		$this->morphClass = $inverse ? $query->getModel()->getMorphClass() : $parent->getMorphClass();
		parent::__construct($query, $parent, $table, $foreignKey, $relatedKey, $relationName);
	}

	protected function addWhereConstraints()
	{
		parent::addWhereConstraints();
		$this->query->where($this->table . '.' . $this->morphType, $this->morphClass);
		return $this;
	}

	public function addEagerConstraints(array $models)
	{
		parent::addEagerConstraints($models);
		$this->query->where($this->table . '.' . $this->morphType, $this->morphClass);
	}

	protected function baseAttachRecord($id, $timed)
	{
		return \Illuminate\Support\Arr::add(parent::baseAttachRecord($id, $timed), $this->morphType, $this->morphClass);
	}

	public function getRelationExistenceQuery(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array('*'))
	{
		return parent::getRelationExistenceQuery($query, $parentQuery, $columns)->where($this->table . '.' . $this->morphType, $this->morphClass);
	}

	protected function newPivotQuery()
	{
		return parent::newPivotQuery()->where($this->morphType, $this->morphClass);
	}

	public function newPivot(array $attributes = array(), $exists = false)
	{
		$using = $this->using;
		$pivot = ($using ? $using::fromRawAttributes($this->parent, $attributes, $this->table, $exists) : new MorphPivot($this->parent, $attributes, $this->table, $exists));
		$pivot->setPivotKeys($this->foreignKey, $this->relatedKey)->setMorphType($this->morphType)->setMorphClass($this->morphClass);
		return $pivot;
	}

	public function getMorphType()
	{
		return $this->morphType;
	}

	public function getMorphClass()
	{
		return $this->morphClass;
	}
}

?>
