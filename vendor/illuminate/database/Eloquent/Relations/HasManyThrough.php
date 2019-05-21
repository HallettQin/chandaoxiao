<?php
//cgxlm
namespace Illuminate\Database\Eloquent\Relations;

class HasManyThrough extends Relation
{
	/**
     * The "through" parent model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
	protected $throughParent;
	/**
     * The far parent model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
	protected $farParent;
	/**
     * The near key on the relationship.
     *
     * @var string
     */
	protected $firstKey;
	/**
     * The far key on the relationship.
     *
     * @var string
     */
	protected $secondKey;
	/**
     * The local key on the relationship.
     *
     * @var string
     */
	protected $localKey;

	public function __construct(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $farParent, \Illuminate\Database\Eloquent\Model $throughParent, $firstKey, $secondKey, $localKey)
	{
		$this->localKey = $localKey;
		$this->firstKey = $firstKey;
		$this->secondKey = $secondKey;
		$this->farParent = $farParent;
		$this->throughParent = $throughParent;
		parent::__construct($query, $throughParent);
	}

	public function addConstraints()
	{
		$localValue = $this->farParent[$this->localKey];
		$this->performJoin();

		if (static::$constraints) {
			$this->query->where($this->getQualifiedFirstKeyName(), '=', $localValue);
		}
	}

	protected function performJoin(\Illuminate\Database\Eloquent\Builder $query = NULL)
	{
		$query = $query ?: $this->query;
		$farKey = $this->getQualifiedFarKeyName();
		$query->join($this->throughParent->getTable(), $this->getQualifiedParentKeyName(), '=', $farKey);

		if ($this->throughParentSoftDeletes()) {
			$query->whereNull($this->throughParent->getQualifiedDeletedAtColumn());
		}
	}

	public function throughParentSoftDeletes()
	{
		return in_array('Illuminate\\Database\\Eloquent\\SoftDeletes', class_uses_recursive(get_class($this->throughParent)));
	}

	public function addEagerConstraints(array $models)
	{
		$this->query->whereIn($this->getQualifiedFirstKeyName(), $this->getKeys($models, $this->localKey));
	}

	public function initRelation(array $models, $relation)
	{
		foreach ($models as $model) {
			$model->setRelation($relation, $this->related->newCollection());
		}

		return $models;
	}

	public function match(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation)
	{
		$dictionary = $this->buildDictionary($results);

		foreach ($models as $model) {
			if (isset($dictionary[$key = $model->getKey()])) {
				$model->setRelation($relation, $this->related->newCollection($dictionary[$key]));
			}
		}

		return $models;
	}

	protected function buildDictionary(\Illuminate\Database\Eloquent\Collection $results)
	{
		$dictionary = array();

		foreach ($results as $result) {
			$dictionary[$result->{$this->firstKey}][] = $result;
		}

		return $dictionary;
	}

	public function firstOrNew(array $attributes)
	{
		if (is_null($instance = $this->where($attributes)->first())) {
			$instance = $this->related->newInstance($attributes);
		}

		return $instance;
	}

	public function updateOrCreate(array $attributes, array $values = array())
	{
		$instance = $this->firstOrNew($attributes);
		$instance->fill($values)->save();
		return $instance;
	}

	public function first($columns = array('*'))
	{
		$results = $this->take(1)->get($columns);
		return 0 < count($results) ? $results->first() : null;
	}

	public function firstOrFail($columns = array('*'))
	{
		if (!is_null($model = $this->first($columns))) {
			return $model;
		}

		throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(get_class($this->related));
	}

	public function find($id, $columns = array('*'))
	{
		if (is_array($id)) {
			return $this->findMany($id, $columns);
		}

		return $this->where($this->getRelated()->getQualifiedKeyName(), '=', $id)->first($columns);
	}

	public function findMany($ids, $columns = array('*'))
	{
		if (empty($ids)) {
			return $this->getRelated()->newCollection();
		}

		return $this->whereIn($this->getRelated()->getQualifiedKeyName(), $ids)->get($columns);
	}

	public function findOrFail($id, $columns = array('*'))
	{
		$result = $this->find($id, $columns);

		if (is_array($id)) {
			if (count($result) == count(array_unique($id))) {
				return $result;
			}
		}
		else if (!is_null($result)) {
			return $result;
		}

		throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(get_class($this->related));
	}

	public function getResults()
	{
		return $this->get();
	}

	public function get($columns = array('*'))
	{
		$columns = ($this->query->getQuery()->columns ? array() : $columns);
		$builder = $this->query->applyScopes();
		$models = $builder->addSelect($this->shouldSelect($columns))->getModels();

		if (0 < count($models)) {
			$models = $builder->eagerLoadRelations($models);
		}

		return $this->related->newCollection($models);
	}

	public function paginate($perPage = NULL, $columns = array('*'), $pageName = 'page', $page = NULL)
	{
		$this->query->addSelect($this->shouldSelect($columns));
		return $this->query->paginate($perPage, $columns, $pageName, $page);
	}

	public function simplePaginate($perPage = NULL, $columns = array('*'), $pageName = 'page', $page = NULL)
	{
		$this->query->addSelect($this->shouldSelect($columns));
		return $this->query->simplePaginate($perPage, $columns, $pageName, $page);
	}

	protected function shouldSelect(array $columns = array('*'))
	{
		if ($columns == array('*')) {
			$columns = array($this->related->getTable() . '.*');
		}

		return array_merge($columns, array($this->getQualifiedFirstKeyName()));
	}

	public function getRelationExistenceQuery(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array('*'))
	{
		$this->performJoin($query);
		return $query->select($columns)->whereColumn($this->getExistenceCompareKey(), '=', $this->getQualifiedFirstKeyName());
	}

	public function getExistenceCompareKey()
	{
		return $this->farParent->getQualifiedKeyName();
	}

	public function getQualifiedFarKeyName()
	{
		return $this->getQualifiedForeignKeyName();
	}

	public function getQualifiedForeignKeyName()
	{
		return $this->related->getTable() . '.' . $this->secondKey;
	}

	public function getQualifiedFirstKeyName()
	{
		return $this->throughParent->getTable() . '.' . $this->firstKey;
	}
}

?>
