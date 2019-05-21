<?php
//cgxlm
namespace Illuminate\Database\Eloquent\Relations;

abstract class Relation
{
	use \Illuminate\Support\Traits\Macroable;

	/**
     * The Eloquent query builder instance.
     *
     * @var \Illuminate\Database\Eloquent\Builder
     */
	protected $query;
	/**
     * The parent model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
	protected $parent;
	/**
     * The related model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
	protected $related;
	/**
     * Indicates if the relation is adding constraints.
     *
     * @var bool
     */
	static protected $constraints = true;
	/**
     * An array to map class names to their morph names in database.
     *
     * @var array
     */
	static protected $morphMap = array();

	public function __construct(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent)
	{
		$this->query = $query;
		$this->parent = $parent;
		$this->related = $query->getModel();
		$this->addConstraints();
	}

	static public function noConstraints(\Closure $callback)
	{
		$previous = static::$constraints;
		static::$constraints = false;
/* [31m * TODO FAST_CALL[0m */

		goto label19;
/* [31m * TODO FAST_RET[0m */

		goto label18;
		static::$constraints = $previous;
label18:
		return NULL;
/* [31m * TODO FAST_CALL[0m */
label19:
		return call_user_func($callback);
	}

	abstract public function addConstraints();

	abstract public function addEagerConstraints(array $models);

	abstract public function initRelation(array $models, $relation);

	abstract public function match(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation);

	abstract public function getResults();

	public function getEager()
	{
		return $this->get();
	}

	public function touch()
	{
		$column = $this->getRelated()->getUpdatedAtColumn();
		$this->rawUpdate(array($column => $this->getRelated()->freshTimestampString()));
	}

	public function rawUpdate(array $attributes = array())
	{
		return $this->query->update($attributes);
	}

	public function getRelationExistenceCountQuery(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery)
	{
		return $this->getRelationExistenceQuery($query, $parentQuery, new \Illuminate\Database\Query\Expression('count(*)'));
	}

	public function getRelationExistenceQuery(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Builder $parentQuery, $columns = array('*'))
	{
		return $query->select($columns)->whereColumn($this->getQualifiedParentKeyName(), '=', $this->getExistenceCompareKey());
	}

	protected function getKeys(array $models, $key = NULL)
	{
		return collect($models)->map(function($value) use($key) {
			return $key ? $value->getAttribute($key) : $value->getKey();
		})->values()->unique()->sort()->all();
	}

	public function getQuery()
	{
		return $this->query;
	}

	public function getBaseQuery()
	{
		return $this->query->getQuery();
	}

	public function getParent()
	{
		return $this->parent;
	}

	public function getQualifiedParentKeyName()
	{
		return $this->parent->getQualifiedKeyName();
	}

	public function getRelated()
	{
		return $this->related;
	}

	public function createdAt()
	{
		return $this->parent->getCreatedAtColumn();
	}

	public function updatedAt()
	{
		return $this->parent->getUpdatedAtColumn();
	}

	public function relatedUpdatedAt()
	{
		return $this->related->getUpdatedAtColumn();
	}

	static public function morphMap(array $map = NULL, $merge = true)
	{
		$map = static::buildMorphMapFromModels($map);

		if (is_array($map)) {
			static::$morphMap = ($merge && static::$morphMap ? array_merge(static::$morphMap, $map) : $map);
		}

		return static::$morphMap;
	}

	static protected function buildMorphMapFromModels(array $models = NULL)
	{
		if (is_null($models) || \Illuminate\Support\Arr::isAssoc($models)) {
			return $models;
		}

		return array_combine(array_map(function($model) {
			return (new $model())->getTable();
		}, $models), $models);
	}

	public function __call($method, $parameters)
	{
		if (static::hasMacro($method)) {
			return $this->macroCall($method, $parameters);
		}

		$result = call_user_func_array(array($this->query, $method), $parameters);

		if ($result === $this->query) {
			return $this;
		}

		return $result;
	}

	public function __clone()
	{
		$this->query = clone $this->query;
	}
}

?>
