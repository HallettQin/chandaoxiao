<?php
//cgxlm
namespace Illuminate\Database\Eloquent\Relations;

abstract class MorphOneOrMany extends HasOneOrMany
{
	/**
     * The foreign key type for the relationship.
     *
     * @var string
     */
	protected $morphType;
	/**
     * The class name of the parent model.
     *
     * @var string
     */
	protected $morphClass;

	public function __construct(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $type, $id, $localKey)
	{
		$this->morphType = $type;
		$this->morphClass = $parent->getMorphClass();
		parent::__construct($query, $parent, $id, $localKey);
	}

	public function make(array $attributes = array())
	{
		return tap($this->related->newInstance($attributes), function($instance) {
			$this->setForeignAttributesForCreate($instance);
		});
	}

	public function addConstraints()
	{
		if (static::$constraints) {
			parent::addConstraints();
			$this->query->where($this->morphType, $this->morphClass);
		}
	}

	public function addEagerConstraints(array $models)
	{
		parent::addEagerConstraints($models);
		$this->query->where($this->morphType, $this->morphClass);
	}

	public function findOrNew($id, $columns = array('*'))
	{
		if (is_null($instance = $this->find($id, $columns))) {
			$instance = $this->related->newInstance();
			$this->setForeignAttributesForCreate($instance);
		}

		return $instance;
	}

	public function firstOrNew(array $attributes)
	{
		if (is_null($instance = $this->where($attributes)->first())) {
			$instance = $this->related->newInstance($attributes);
			$this->setForeignAttributesForCreate($instance);
		}

		return $instance;
	}

	public function firstOrCreate(array $attributes)
	{
		if (is_null($instance = $this->where($attributes)->first())) {
			$instance = $this->create($attributes);
		}

		return $instance;
	}

	public function updateOrCreate(array $attributes, array $values = array())
	{
		return tap($this->firstOrNew($attributes), function($instance) use($values) {
			$instance->fill($values);
			$instance->save();
		});
	}

	public function save(\Illuminate\Database\Eloquent\Model $model)
	{
		$model->setAttribute($this->getMorphType(), $this->morphClass);
		return parent::save($model);
	}

	public function create(array $attributes)
	{
		$instance = $this->related->newInstance($attributes);
		$this->setForeignAttributesForCreate($instance);
		$instance->save();
		return $instance;
	}

	protected function setForeignAttributesForCreate(\Illuminate\Database\Eloquent\Model $model)
	{
		$model->{$this->getForeignKeyName()} = $this->getParentKey();
		$model->{$this->getMorphType()} = $this->morphClass;
	}

	public function getRelationExistenceQuery(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array('*'))
	{
		return parent::getRelationExistenceQuery($query, $parentQuery, $columns)->where($this->morphType, $this->morphClass);
	}

	public function getQualifiedMorphType()
	{
		return $this->morphType;
	}

	public function getMorphType()
	{
		return last(explode('.', $this->morphType));
	}

	public function getMorphClass()
	{
		return $this->morphClass;
	}
}

?>
