<?php
//cgxlm
namespace Illuminate\Database\Eloquent\Relations;

class BelongsTo extends Relation
{
	/**
     * The child model instance of the relation.
     */
	protected $child;
	/**
     * The foreign key of the parent model.
     *
     * @var string
     */
	protected $foreignKey;
	/**
     * The associated key on the parent model.
     *
     * @var string
     */
	protected $ownerKey;
	/**
     * The name of the relationship.
     *
     * @var string
     */
	protected $relation;
	/**
     * The count of self joins.
     *
     * @var int
     */
	static protected $selfJoinCount = 0;

	public function __construct(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $child, $foreignKey, $ownerKey, $relation)
	{
		$this->ownerKey = $ownerKey;
		$this->relation = $relation;
		$this->foreignKey = $foreignKey;
		$this->child = $child;
		parent::__construct($query, $child);
	}

	public function getResults()
	{
		return $this->query->first();
	}

	public function addConstraints()
	{
		if (static::$constraints) {
			$table = $this->related->getTable();
			$this->query->where($table . '.' . $this->ownerKey, '=', $this->child->{$this->foreignKey});
		}
	}

	public function addEagerConstraints(array $models)
	{
		$key = $this->related->getTable() . '.' . $this->ownerKey;
		$this->query->whereIn($key, $this->getEagerModelKeys($models));
	}

	protected function getEagerModelKeys(array $models)
	{
		$keys = array();

		foreach ($models as $model) {
			if (!is_null($value = $model->{$this->foreignKey})) {
				$keys[] = $value;
			}
		}

		if (count($keys) === 0) {
			return array($this->relationHasIncrementingId() ? 0 : null);
		}

		sort($keys);
		return array_values(array_unique($keys));
	}

	public function initRelation(array $models, $relation)
	{
		foreach ($models as $model) {
			$model->setRelation($relation, null);
		}

		return $models;
	}

	public function match(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation)
	{
		$foreign = $this->foreignKey;
		$owner = $this->ownerKey;
		$dictionary = array();

		foreach ($results as $result) {
			$dictionary[$result->getAttribute($owner)] = $result;
		}

		foreach ($models as $model) {
			if (isset($dictionary[$model->$foreign])) {
				$model->setRelation($relation, $dictionary[$model->$foreign]);
			}
		}

		return $models;
	}

	public function update(array $attributes)
	{
		return $this->getResults()->fill($attributes)->save();
	}

	public function associate($model)
	{
		$ownerKey = ($model instanceof \Illuminate\Database\Eloquent\Model ? $model->getAttribute($this->ownerKey) : $model);
		$this->child->setAttribute($this->foreignKey, $ownerKey);

		if ($model instanceof \Illuminate\Database\Eloquent\Model) {
			$this->child->setRelation($this->relation, $model);
		}

		return $this->child;
	}

	public function dissociate()
	{
		$this->child->setAttribute($this->foreignKey, null);
		return $this->child->setRelation($this->relation, null);
	}

	public function getRelationExistenceQuery(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array('*'))
	{
		if ($parentQuery->getQuery()->from == $query->getQuery()->from) {
			return $this->getRelationExistenceQueryForSelfRelation($query, $parentQuery, $columns);
		}

		return $query->select($columns)->whereColumn($this->getQualifiedForeignKey(), '=', $query->getModel()->getTable() . '.' . $this->ownerKey);
	}

	public function getRelationExistenceQueryForSelfRelation(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array('*'))
	{
		$query->select($columns)->from($query->getModel()->getTable() . ' as ' . ($hash = $this->getRelationCountHash()));
		$query->getModel()->setTable($hash);
		return $query->whereColumn($hash . '.' . $query->getModel()->getKeyName(), '=', $this->getQualifiedForeignKey());
	}

	public function getRelationCountHash()
	{
		return 'laravel_reserved_' . static::$selfJoinCount++;
	}

	protected function relationHasIncrementingId()
	{
		return $this->related->getIncrementing() && ($this->related->getKeyType() === 'int');
	}

	public function getForeignKey()
	{
		return $this->foreignKey;
	}

	public function getQualifiedForeignKey()
	{
		return $this->child->getTable() . '.' . $this->foreignKey;
	}

	public function getOwnerKey()
	{
		return $this->ownerKey;
	}

	public function getQualifiedOwnerKeyName()
	{
		return $this->related->getTable() . '.' . $this->ownerKey;
	}

	public function getRelation()
	{
		return $this->relation;
	}
}

?>
