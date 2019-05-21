<?php
//cgxlm
namespace Illuminate\Database\Eloquent\Concerns;

trait HasRelationships
{
	/**
     * The loaded relationships for the model.
     *
     * @var array
     */
	protected $relations = array();
	/**
     * The relationships that should be touched on save.
     *
     * @var array
     */
	protected $touches = array();
	/**
     * The many to many relationship methods.
     *
     * @var array
     */
	static public $manyMethods = array('belongsToMany', 'morphToMany', 'morphedByMany', 'guessBelongsToManyRelation', 'findFirstMethodThatIsntRelation');

	public function hasOne($related, $foreignKey = NULL, $localKey = NULL)
	{
		$instance = $this->newRelatedInstance($related);
		$foreignKey = $foreignKey ?: $this->getForeignKey();
		$localKey = $localKey ?: $this->getKeyName();
		return new \Illuminate\Database\Eloquent\Relations\HasOne($instance->newQuery(), $this, $instance->getTable() . '.' . $foreignKey, $localKey);
	}

	public function morphOne($related, $name, $type = NULL, $id = NULL, $localKey = NULL)
	{
		$instance = $this->newRelatedInstance($related);
		list($type, $id) = $this->getMorphs($name, $type, $id);
		$table = $instance->getTable();
		$localKey = $localKey ?: $this->getKeyName();
		return new \Illuminate\Database\Eloquent\Relations\MorphOne($instance->newQuery(), $this, $table . '.' . $type, $table . '.' . $id, $localKey);
	}

	public function belongsTo($related, $foreignKey = NULL, $ownerKey = NULL, $relation = NULL)
	{
		if (is_null($relation)) {
			$relation = $this->guessBelongsToRelation();
		}

		$instance = $this->newRelatedInstance($related);

		if (is_null($foreignKey)) {
			$foreignKey = \Illuminate\Support\Str::snake($relation) . '_' . $instance->getKeyName();
		}

		$ownerKey = $ownerKey ?: $instance->getKeyName();
		return new \Illuminate\Database\Eloquent\Relations\BelongsTo($instance->newQuery(), $this, $foreignKey, $ownerKey, $relation);
	}

	public function morphTo($name = NULL, $type = NULL, $id = NULL)
	{
		$name = $name ?: $this->guessBelongsToRelation();
		list($type, $id) = $this->getMorphs(\Illuminate\Support\Str::snake($name), $type, $id);
		return !($class = $this->$type) ? $this->morphEagerTo($name, $type, $id) : $this->morphInstanceTo($class, $name, $type, $id);
	}

	protected function morphEagerTo($name, $type, $id)
	{
		return new \Illuminate\Database\Eloquent\Relations\MorphTo($this->newQuery()->setEagerLoads(array()), $this, $id, null, $type, $name);
	}

	protected function morphInstanceTo($target, $name, $type, $id)
	{
		$instance = $this->newRelatedInstance(static::getActualClassNameForMorph($target));
		return new \Illuminate\Database\Eloquent\Relations\MorphTo($instance->newQuery(), $this, $id, $instance->getKeyName(), $type, $name);
	}

	static public function getActualClassNameForMorph($class)
	{
		return \Illuminate\Support\Arr::get(\Illuminate\Database\Eloquent\Relations\Relation::morphMap() ?: array(), $class, $class);
	}

	protected function guessBelongsToRelation()
	{
		list($one, $two, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
		return $caller['function'];
	}

	public function hasMany($related, $foreignKey = NULL, $localKey = NULL)
	{
		$instance = $this->newRelatedInstance($related);
		$foreignKey = $foreignKey ?: $this->getForeignKey();
		$localKey = $localKey ?: $this->getKeyName();
		return new \Illuminate\Database\Eloquent\Relations\HasMany($instance->newQuery(), $this, $instance->getTable() . '.' . $foreignKey, $localKey);
	}

	public function hasManyThrough($related, $through, $firstKey = NULL, $secondKey = NULL, $localKey = NULL)
	{
		$through = new $through();
		$firstKey = $firstKey ?: $this->getForeignKey();
		$secondKey = $secondKey ?: $through->getForeignKey();
		$localKey = $localKey ?: $this->getKeyName();
		$instance = $this->newRelatedInstance($related);
		return new \Illuminate\Database\Eloquent\Relations\HasManyThrough($instance->newQuery(), $this, $through, $firstKey, $secondKey, $localKey);
	}

	public function morphMany($related, $name, $type = NULL, $id = NULL, $localKey = NULL)
	{
		$instance = $this->newRelatedInstance($related);
		list($type, $id) = $this->getMorphs($name, $type, $id);
		$table = $instance->getTable();
		$localKey = $localKey ?: $this->getKeyName();
		return new \Illuminate\Database\Eloquent\Relations\MorphMany($instance->newQuery(), $this, $table . '.' . $type, $table . '.' . $id, $localKey);
	}

	public function belongsToMany($related, $table = NULL, $foreignKey = NULL, $relatedKey = NULL, $relation = NULL)
	{
		if (is_null($relation)) {
			$relation = $this->guessBelongsToManyRelation();
		}

		$instance = $this->newRelatedInstance($related);
		$foreignKey = $foreignKey ?: $this->getForeignKey();
		$relatedKey = $relatedKey ?: $instance->getForeignKey();

		if (is_null($table)) {
			$table = $this->joiningTable($related);
		}

		return new \Illuminate\Database\Eloquent\Relations\BelongsToMany($instance->newQuery(), $this, $table, $foreignKey, $relatedKey, $relation);
	}

	public function morphToMany($related, $name, $table = NULL, $foreignKey = NULL, $relatedKey = NULL, $inverse = false)
	{
		$caller = $this->guessBelongsToManyRelation();
		$instance = $this->newRelatedInstance($related);
		$foreignKey = $foreignKey ?: ($name . '_id');
		$relatedKey = $relatedKey ?: $instance->getForeignKey();
		$table = $table ?: \Illuminate\Support\Str::plural($name);
		return new \Illuminate\Database\Eloquent\Relations\MorphToMany($instance->newQuery(), $this, $name, $table, $foreignKey, $relatedKey, $caller, $inverse);
	}

	public function morphedByMany($related, $name, $table = NULL, $foreignKey = NULL, $relatedKey = NULL)
	{
		$foreignKey = $foreignKey ?: $this->getForeignKey();
		$relatedKey = $relatedKey ?: ($name . '_id');
		return $this->morphToMany($related, $name, $table, $foreignKey, $relatedKey, true);
	}

	protected function guessBelongsToManyRelation()
	{
		$caller = \Illuminate\Support\Arr::first(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), function($trace) {
			return !in_array($trace['function'], \Illuminate\Database\Eloquent\Model::$manyMethods);
		});
		return !is_null($caller) ? $caller['function'] : null;
	}

	public function joiningTable($related)
	{
		$models = array(\Illuminate\Support\Str::snake(class_basename($related)), \Illuminate\Support\Str::snake(class_basename($this)));
		sort($models);
		return strtolower(implode('_', $models));
	}

	public function touches($relation)
	{
		return in_array($relation, $this->touches);
	}

	public function touchOwners()
	{
		foreach ($this->touches as $relation) {
			$this->$relation()->touch();

			if ($this->$relation instanceof self) {
				$this->$relation->fireModelEvent('saved', false);
				$this->$relation->touchOwners();
			}
			else if ($this->$relation instanceof \Illuminate\Database\Eloquent\Collection) {
				$this->$relation->each(function(\Illuminate\Database\Eloquent\Model $relation) {
					$relation->touchOwners();
				});
			}
		}
	}

	protected function getMorphs($name, $type, $id)
	{
		return array($type ?: ($name . '_type'), $id ?: ($name . '_id'));
	}

	public function getMorphClass()
	{
		$morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
		if (!empty($morphMap) && in_array(static::class, $morphMap)) {
			return array_search(static::class, $morphMap, true);
		}

		return static::class;
	}

	protected function newRelatedInstance($class)
	{
		return tap(new $class(), function($instance) {
			if (!$instance->getConnectionName()) {
				$instance->setConnection($this->connection);
			}
		});
	}

	public function getRelations()
	{
		return $this->relations;
	}

	public function getRelation($relation)
	{
		return $this->relations[$relation];
	}

	public function relationLoaded($key)
	{
		return array_key_exists($key, $this->relations);
	}

	public function setRelation($relation, $value)
	{
		$this->relations[$relation] = $value;
		return $this;
	}

	public function setRelations(array $relations)
	{
		$this->relations = $relations;
		return $this;
	}

	public function getTouchedRelations()
	{
		return $this->touches;
	}

	public function setTouchedRelations(array $touches)
	{
		$this->touches = $touches;
		return $this;
	}
}


?>
