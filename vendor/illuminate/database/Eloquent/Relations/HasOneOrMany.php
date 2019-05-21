<?php
//cgxlm
namespace Illuminate\Database\Eloquent\Relations;

abstract class HasOneOrMany extends Relation
{
	/**
     * The foreign key of the parent model.
     *
     * @var string
     */
	protected $foreignKey;
	/**
     * The local key of the parent model.
     *
     * @var string
     */
	protected $localKey;
	/**
     * The count of self joins.
     *
     * @var int
     */
	static protected $selfJoinCount = 0;

	public function __construct(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $foreignKey, $localKey)
	{
		$this->localKey = $localKey;
		$this->foreignKey = $foreignKey;
		parent::__construct($query, $parent);
	}

	public function make(array $attributes = array())
	{
		return tap($this->related->newInstance($attributes), function($instance) {
			$instance->setAttribute($this->getForeignKeyName(), $this->getParentKey());
		});
	}

	public function addConstraints()
	{
		if (static::$constraints) {
			$this->query->where($this->foreignKey, '=', $this->getParentKey());
			$this->query->whereNotNull($this->foreignKey);
		}
	}

	public function addEagerConstraints(array $models)
	{
		$this->query->whereIn($this->foreignKey, $this->getKeys($models, $this->localKey));
	}

	public function matchOne(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation)
	{
		return $this->matchOneOrMany($models, $results, $relation, 'one');
	}

	public function matchMany(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation)
	{
		return $this->matchOneOrMany($models, $results, $relation, 'many');
	}

	protected function matchOneOrMany(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation, $type)
	{
		$dictionary = $this->buildDictionary($results);

		foreach ($models as $model) {
			if (isset($dictionary[$key = $model->getAttribute($this->localKey)])) {
				$model->setRelation($relation, $this->getRelationValue($dictionary, $key, $type));
			}
		}

		return $models;
	}

	protected function getRelationValue(array $dictionary, $key, $type)
	{
		$value = $dictionary[$key];
		return $type == 'one' ? reset($value) : $this->related->newCollection($value);
	}

	protected function buildDictionary(\Illuminate\Database\Eloquent\Collection $results)
	{
		$dictionary = array();
		$foreign = $this->getForeignKeyName();

		foreach ($results as $result) {
			$dictionary[$result->$foreign][] = $result;
		}

		return $dictionary;
	}

	public function findOrNew($id, $columns = array('*'))
	{
		if (is_null($instance = $this->find($id, $columns))) {
			$instance = $this->related->newInstance();
			$instance->setAttribute($this->getForeignKeyName(), $this->getParentKey());
		}

		return $instance;
	}

	public function firstOrNew(array $attributes)
	{
		if (is_null($instance = $this->where($attributes)->first())) {
			$instance = $this->related->newInstance($attributes);
			$instance->setAttribute($this->getForeignKeyName(), $this->getParentKey());
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
		$model->setAttribute($this->getForeignKeyName(), $this->getParentKey());
		return $model->save() ? $model : false;
	}

	public function saveMany($models)
	{
		foreach ($models as $model) {
			$this->save($model);
		}

		return $models;
	}

	public function create(array $attributes)
	{
		return tap($this->related->newInstance($attributes), function($instance) {
			$instance->setAttribute($this->getForeignKeyName(), $this->getParentKey());
			$instance->save();
		});
	}

	public function createMany(array $records)
	{
		$instances = $this->related->newCollection();

		foreach ($records as $record) {
			$instances->push($this->create($record));
		}

		return $instances;
	}

	public function update(array $attributes)
	{
		if ($this->related->usesTimestamps()) {
			$attributes[$this->relatedUpdatedAt()] = $this->related->freshTimestampString();
		}

		return $this->query->update($attributes);
	}

	public function getRelationExistenceQuery(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array('*'))
	{
		if ($query->getQuery()->from == $parentQuery->getQuery()->from) {
			return $this->getRelationExistenceQueryForSelfRelation($query, $parentQuery, $columns);
		}

		return parent::getRelationExistenceQuery($query, $parentQuery, $columns);
	}

	public function getRelationExistenceQueryForSelfRelation(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array('*'))
	{
		$query->from($query->getModel()->getTable() . ' as ' . ($hash = $this->getRelationCountHash()));
		$query->getModel()->setTable($hash);
		return $query->select($columns)->whereColumn($this->getQualifiedParentKeyName(), '=', $hash . '.' . $this->getForeignKeyName());
	}

	public function getRelationCountHash()
	{
		return 'laravel_reserved_' . static::$selfJoinCount++;
	}

	public function getExistenceCompareKey()
	{
		return $this->getQualifiedForeignKeyName();
	}

	public function getParentKey()
	{
		return $this->parent->getAttribute($this->localKey);
	}

	public function getQualifiedParentKeyName()
	{
		return $this->parent->getTable() . '.' . $this->localKey;
	}

	public function getForeignKeyName()
	{
		$segments = explode('.', $this->getQualifiedForeignKeyName());
		return $segments[count($segments) - 1];
	}

	public function getQualifiedForeignKeyName()
	{
		return $this->foreignKey;
	}
}

?>
