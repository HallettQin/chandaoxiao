<?php
//cgxlm
namespace Illuminate\Database\Eloquent\Relations;

class BelongsToMany extends Relation
{
	use Concerns\InteractsWithPivotTable;

	/**
     * The intermediate table for the relation.
     *
     * @var string
     */
	protected $table;
	/**
     * The foreign key of the parent model.
     *
     * @var string
     */
	protected $foreignKey;
	/**
     * The associated key of the relation.
     *
     * @var string
     */
	protected $relatedKey;
	/**
     * The "name" of the relationship.
     *
     * @var string
     */
	protected $relationName;
	/**
     * The pivot table columns to retrieve.
     *
     * @var array
     */
	protected $pivotColumns = array();
	/**
     * Any pivot table restrictions for where clauses.
     *
     * @var array
     */
	protected $pivotWheres = array();
	/**
     * Any pivot table restrictions for whereIn clauses.
     *
     * @var array
     */
	protected $pivotWhereIns = array();
	/**
     * The custom pivot table column for the created_at timestamp.
     *
     * @var string
     */
	protected $pivotCreatedAt;
	/**
     * The custom pivot table column for the updated_at timestamp.
     *
     * @var string
     */
	protected $pivotUpdatedAt;
	/**
     * The class name of the custom pivot model to use for the relationship.
     *
     * @var string
     */
	protected $using;
	/**
     * The count of self joins.
     *
     * @var int
     */
	static protected $selfJoinCount = 0;

	public function __construct(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $table, $foreignKey, $relatedKey, $relationName = NULL)
	{
		$this->table = $table;
		$this->relatedKey = $relatedKey;
		$this->foreignKey = $foreignKey;
		$this->relationName = $relationName;
		parent::__construct($query, $parent);
	}

	public function addConstraints()
	{
		$this->performJoin();

		if (static::$constraints) {
			$this->addWhereConstraints();
		}
	}

	protected function performJoin($query = NULL)
	{
		$query = $query ?: $this->query;
		$baseTable = $this->related->getTable();
		$key = $baseTable . '.' . $this->related->getKeyName();
		$query->join($this->table, $key, '=', $this->getQualifiedRelatedKeyName());
		return $this;
	}

	protected function addWhereConstraints()
	{
		$this->query->where($this->getQualifiedForeignKeyName(), '=', $this->parent->getKey());
		return $this;
	}

	public function addEagerConstraints(array $models)
	{
		$this->query->whereIn($this->getQualifiedForeignKeyName(), $this->getKeys($models));
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
			$dictionary[$result->pivot->{$this->foreignKey}][] = $result;
		}

		return $dictionary;
	}

	public function using($class)
	{
		$this->using = $class;
		return $this;
	}

	public function wherePivot($column, $operator = NULL, $value = NULL, $boolean = 'and')
	{
		$this->pivotWheres[] = func_get_args();
		return $this->where($this->table . '.' . $column, $operator, $value, $boolean);
	}

	public function wherePivotIn($column, $values, $boolean = 'and', $not = false)
	{
		$this->pivotWhereIns[] = func_get_args();
		return $this->whereIn($this->table . '.' . $column, $values, $boolean, $not);
	}

	public function orWherePivot($column, $operator = NULL, $value = NULL)
	{
		return $this->wherePivot($column, $operator, $value, 'or');
	}

	public function orWherePivotIn($column, $values)
	{
		return $this->wherePivotIn($column, $values, 'or');
	}

	public function findOrNew($id, $columns = array('*'))
	{
		if (is_null($instance = $this->find($id, $columns))) {
			$instance = $this->related->newInstance();
		}

		return $instance;
	}

	public function firstOrNew(array $attributes)
	{
		if (is_null($instance = $this->where($attributes)->first())) {
			$instance = $this->related->newInstance($attributes);
		}

		return $instance;
	}

	public function firstOrCreate(array $attributes, array $joining = array(), $touch = true)
	{
		if (is_null($instance = $this->where($attributes)->first())) {
			$instance = $this->create($attributes, $joining, $touch);
		}

		return $instance;
	}

	public function updateOrCreate(array $attributes, array $values = array(), array $joining = array(), $touch = true)
	{
		if (is_null($instance = $this->where($attributes)->first())) {
			return $this->create($values, $joining, $touch);
		}

		$instance->fill($values);
		$instance->save(array('touch' => false));
		return $instance;
	}

	public function find($id, $columns = array('*'))
	{
		return is_array($id) ? $this->findMany($id, $columns) : $this->where($this->getRelated()->getQualifiedKeyName(), '=', $id)->first($columns);
	}

	public function findMany($ids, $columns = array('*'))
	{
		return empty($ids) ? $this->getRelated()->newCollection() : $this->whereIn($this->getRelated()->getQualifiedKeyName(), $ids)->get($columns);
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

	public function getResults()
	{
		return $this->get();
	}

	public function get($columns = array('*'))
	{
		$columns = ($this->query->getQuery()->columns ? array() : $columns);
		$builder = $this->query->applyScopes();
		$models = $builder->addSelect($this->shouldSelect($columns))->getModels();
		$this->hydratePivotRelation($models);

		if (0 < count($models)) {
			$models = $builder->eagerLoadRelations($models);
		}

		return $this->related->newCollection($models);
	}

	protected function shouldSelect(array $columns = array('*'))
	{
		if ($columns == array('*')) {
			$columns = array($this->related->getTable() . '.*');
		}

		return array_merge($columns, $this->aliasedPivotColumns());
	}

	protected function aliasedPivotColumns()
	{
		$defaults = array($this->foreignKey, $this->relatedKey);
		return collect(array_merge($defaults, $this->pivotColumns))->map(function($column) {
			return $this->table . '.' . $column . ' as pivot_' . $column;
		})->unique()->all();
	}

	public function paginate($perPage = NULL, $columns = array('*'), $pageName = 'page', $page = NULL)
	{
		$this->query->addSelect($this->shouldSelect($columns));
		return tap($this->query->paginate($perPage, $columns, $pageName, $page), function($paginator) {
			$this->hydratePivotRelation($paginator->items());
		});
	}

	public function simplePaginate($perPage = NULL, $columns = array('*'), $pageName = 'page', $page = NULL)
	{
		$this->query->addSelect($this->shouldSelect($columns));
		return tap($this->query->simplePaginate($perPage, $columns, $pageName, $page), function($paginator) {
			$this->hydratePivotRelation($paginator->items());
		});
	}

	public function chunk($count,  $callback)
	{
		$this->query->addSelect($this->shouldSelect());
		return $this->query->chunk($count, function($results) use($callback) {
			$this->hydratePivotRelation($results->all());
			return $callback($results);
		});
	}

	protected function hydratePivotRelation(array $models)
	{
		foreach ($models as $model) {
			$model->setRelation('pivot', $this->newExistingPivot($this->migratePivotAttributes($model)));
		}
	}

	protected function migratePivotAttributes(\Illuminate\Database\Eloquent\Model $model)
	{
		$values = array();

		foreach ($model->getAttributes() as $key => $value) {
			if (strpos($key, 'pivot_') === 0) {
				$values[substr($key, 6)] = $value;
				unset($model->$key);
			}
		}

		return $values;
	}

	public function touchIfTouching()
	{
		if ($this->touchingParent()) {
			$this->getParent()->touch();
		}

		if ($this->getParent()->touches($this->relationName)) {
			$this->touch();
		}
	}

	protected function touchingParent()
	{
		return $this->getRelated()->touches($this->guessInverseRelation());
	}

	protected function guessInverseRelation()
	{
		return \Illuminate\Support\Str::camel(\Illuminate\Support\Str::plural(class_basename($this->getParent())));
	}

	public function touch()
	{
		$key = $this->getRelated()->getKeyName();
		$columns = array($this->related->getUpdatedAtColumn() => $this->related->freshTimestampString());

		if (0 < count($ids = $this->allRelatedIds())) {
			$this->getRelated()->newQuery()->whereIn($key, $ids)->update($columns);
		}
	}

	public function allRelatedIds()
	{
		$related = $this->getRelated();
		return $this->getQuery()->select($related->getQualifiedKeyName())->pluck($related->getKeyName());
	}

	public function save(\Illuminate\Database\Eloquent\Model $model, array $pivotAttributes = array(), $touch = true)
	{
		$model->save(array('touch' => false));
		$this->attach($model->getKey(), $pivotAttributes, $touch);
		return $model;
	}

	public function saveMany($models, array $pivotAttributes = array())
	{
		foreach ($models as $key => $model) {
			$this->save($model, (array) \Illuminate\Support\Arr::get($pivotAttributes, $key), false);
		}

		$this->touchIfTouching();
		return $models;
	}

	public function create(array $attributes, array $joining = array(), $touch = true)
	{
		$instance = $this->related->newInstance($attributes);
		$instance->save(array('touch' => false));
		$this->attach($instance->getKey(), $joining, $touch);
		return $instance;
	}

	public function createMany(array $records, array $joinings = array())
	{
		$instances = array();

		foreach ($records as $key => $record) {
			$instances[] = $this->create($record, (array) \Illuminate\Support\Arr::get($joinings, $key), false);
		}

		$this->touchIfTouching();
		return $instances;
	}

	public function getRelationExistenceQuery(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array('*'))
	{
		if ($parentQuery->getQuery()->from == $query->getQuery()->from) {
			return $this->getRelationExistenceQueryForSelfJoin($query, $parentQuery, $columns);
		}

		$this->performJoin($query);
		return parent::getRelationExistenceQuery($query, $parentQuery, $columns);
	}

	public function getRelationExistenceQueryForSelfJoin(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array('*'))
	{
		$query->select($columns);
		$query->from($this->related->getTable() . ' as ' . ($hash = $this->getRelationCountHash()));
		$this->related->setTable($hash);
		$this->performJoin($query);
		return parent::getRelationExistenceQuery($query, $parentQuery, $columns);
	}

	public function getExistenceCompareKey()
	{
		return $this->getQualifiedForeignKeyName();
	}

	public function getRelationCountHash()
	{
		return 'laravel_reserved_' . static::$selfJoinCount++;
	}

	public function withTimestamps($createdAt = NULL, $updatedAt = NULL)
	{
		$this->pivotCreatedAt = $createdAt;
		$this->pivotUpdatedAt = $updatedAt;
		return $this->withPivot($this->createdAt(), $this->updatedAt());
	}

	public function createdAt()
	{
		return $this->pivotCreatedAt ?: $this->parent->getCreatedAtColumn();
	}

	public function updatedAt()
	{
		return $this->pivotUpdatedAt ?: $this->parent->getUpdatedAtColumn();
	}

	public function getQualifiedForeignKeyName()
	{
		return $this->table . '.' . $this->foreignKey;
	}

	public function getQualifiedRelatedKeyName()
	{
		return $this->table . '.' . $this->relatedKey;
	}

	public function getTable()
	{
		return $this->table;
	}

	public function getRelationName()
	{
		return $this->relationName;
	}
}

?>
