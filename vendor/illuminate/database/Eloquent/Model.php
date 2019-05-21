<?php
//cgxlm
namespace Illuminate\Database\Eloquent;

abstract class Model implements \ArrayAccess, \Illuminate\Contracts\Support\Arrayable, \Illuminate\Contracts\Support\Jsonable, \JsonSerializable, \Illuminate\Contracts\Queue\QueueableEntity, \Illuminate\Contracts\Routing\UrlRoutable
{
	use Concerns\HasAttributes, Concerns\HasEvents, Concerns\HasGlobalScopes, Concerns\HasRelationships, Concerns\HasTimestamps, Concerns\HidesAttributes, Concerns\GuardsAttributes;

	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';

	/**
     * The connection name for the model.
     *
     * @var string
     */
	protected $connection;
	/**
     * The table associated with the model.
     *
     * @var string
     */
	protected $table;
	/**
     * The primary key for the model.
     *
     * @var string
     */
	protected $primaryKey = 'id';
	/**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
	protected $keyType = 'int';
	/**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
	public $incrementing = true;
	/**
     * The relations to eager load on every query.
     *
     * @var array
     */
	protected $with = array();
	/**
     * The relationship counts that should be eager loaded on every query.
     *
     * @var array
     */
	protected $withCount = array();
	/**
     * The number of models to return for pagination.
     *
     * @var int
     */
	protected $perPage = 15;
	/**
     * Indicates if the model exists.
     *
     * @var bool
     */
	public $exists = false;
	/**
     * Indicates if the model was inserted during the current request lifecycle.
     *
     * @var bool
     */
	public $wasRecentlyCreated = false;
	/**
     * The connection resolver instance.
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
	static protected $resolver;
	/**
     * The event dispatcher instance.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
	static protected $dispatcher;
	/**
     * The array of booted models.
     *
     * @var array
     */
	static protected $booted = array();
	/**
     * The array of global scopes on the model.
     *
     * @var array
     */
	static protected $globalScopes = array();

	public function __construct(array $attributes = array())
	{
		$this->bootIfNotBooted();
		$this->syncOriginal();
		$this->fill($attributes);
	}

	protected function bootIfNotBooted()
	{
		if (!isset(static::$booted[static::class])) {
			static::$booted[static::class] = true;
			$this->fireModelEvent('booting', false);
			static::boot();
			$this->fireModelEvent('booted', false);
		}
	}

	static protected function boot()
	{
		static::bootTraits();
	}

	static protected function bootTraits()
	{
		$class = static::class;

		foreach (class_uses_recursive($class) as $trait) {
			if (method_exists($class, $method = 'boot' . class_basename($trait))) {
				forward_static_call(array($class, $method));
			}
		}
	}

	static public function clearBootedModels()
	{
		static::$booted = array();
		static::$globalScopes = array();
	}

	public function fill(array $attributes)
	{
		$totallyGuarded = $this->totallyGuarded();

		foreach ($this->fillableFromArray($attributes) as $key => $value) {
			$key = $this->removeTableFromKey($key);

			if ($this->isFillable($key)) {
				$this->setAttribute($key, $value);
			}
			else if ($totallyGuarded) {
				throw new MassAssignmentException($key);
			}
		}

		return $this;
	}

	public function forceFill(array $attributes)
	{
		return static::unguarded(function() use($attributes) {
			return $this->fill($attributes);
		});
	}

	protected function removeTableFromKey($key)
	{
		return \Illuminate\Support\Str::contains($key, '.') ? last(explode('.', $key)) : $key;
	}

	public function newInstance($attributes = array(), $exists = false)
	{
		$model = new static((array) $attributes);
		$model->exists = $exists;
		$model->setConnection($this->getConnectionName());
		return $model;
	}

	public function newFromBuilder($attributes = array(), $connection = NULL)
	{
		$model = $this->newInstance(array(), true);
		$model->setRawAttributes((array) $attributes, true);
		$model->setConnection($connection ?: $this->getConnectionName());
		return $model;
	}

	static public function on($connection = NULL)
	{
		$instance = new static();
		$instance->setConnection($connection);
		return $instance->newQuery();
	}

	static public function onWriteConnection()
	{
		$instance = new static();
		return $instance->newQuery()->useWritePdo();
	}

	static public function all($columns = array('*'))
	{
		return (new static())->newQuery()->get(is_array($columns) ? $columns : func_get_args());
	}

	static public function with($relations)
	{
		return (new static())->newQuery()->with(is_string($relations) ? func_get_args() : $relations);
	}

	public function load($relations)
	{
		$query = $this->newQuery()->with(is_string($relations) ? func_get_args() : $relations);
		$query->eagerLoadRelations(array($this));
		return $this;
	}

	protected function increment($column, $amount = 1, array $extra = array())
	{
		return $this->incrementOrDecrement($column, $amount, $extra, 'increment');
	}

	protected function decrement($column, $amount = 1, array $extra = array())
	{
		return $this->incrementOrDecrement($column, $amount, $extra, 'decrement');
	}

	protected function incrementOrDecrement($column, $amount, $extra, $method)
	{
		$query = $this->newQuery();

		if (!$this->exists) {
			return $query->$method($column, $amount, $extra);
		}

		$this->incrementOrDecrementAttributeValue($column, $amount, $extra, $method);
		return $query->where($this->getKeyName(), $this->getKey())->$method($column, $amount, $extra);
	}

	protected function incrementOrDecrementAttributeValue($column, $amount, $extra, $method)
	{
		$this->$column = $this->$column + ($method == 'increment' ? $amount : $amount * -1);
		$this->forceFill($extra);
		$this->syncOriginalAttribute($column);
	}

	public function update(array $attributes = array(), array $options = array())
	{
		if (!$this->exists) {
			return false;
		}

		return $this->fill($attributes)->save($options);
	}

	public function push()
	{
		if (!$this->save()) {
			return false;
		}

		foreach ($this->relations as $models) {
			$models = ($models instanceof Collection ? $models->all() : array($models));

			foreach (array_filter($models) as $model) {
				if (!$model->push()) {
					return false;
				}
			}
		}

		return true;
	}

	public function save(array $options = array())
	{
		$query = $this->newQueryWithoutScopes();

		if ($this->fireModelEvent('saving') === false) {
			return false;
		}

		if ($this->exists) {
			$saved = ($this->isDirty() ? $this->performUpdate($query) : true);
		}
		else {
			$saved = $this->performInsert($query);
		}

		if ($saved) {
			$this->finishSave($options);
		}

		return $saved;
	}

	public function saveOrFail(array $options = array())
	{
		return $this->getConnection()->transaction(function() use($options) {
			return $this->save($options);
		});
	}

	protected function finishSave(array $options)
	{
		$this->fireModelEvent('saved', false);
		$this->syncOriginal();

		if (\Illuminate\Support\Arr::get($options, 'touch', true)) {
			$this->touchOwners();
		}
	}

	protected function performUpdate(Builder $query)
	{
		if ($this->fireModelEvent('updating') === false) {
			return false;
		}

		if ($this->usesTimestamps()) {
			$this->updateTimestamps();
		}

		$dirty = $this->getDirty();

		if (0 < count($dirty)) {
			$this->setKeysForSaveQuery($query)->update($dirty);
			$this->fireModelEvent('updated', false);
		}

		return true;
	}

	protected function setKeysForSaveQuery(Builder $query)
	{
		$query->where($this->getKeyName(), '=', $this->getKeyForSaveQuery());
		return $query;
	}

	protected function getKeyForSaveQuery()
	{
		return isset($this->original[$this->getKeyName()]) ? $this->original[$this->getKeyName()] : $this->getAttribute($this->getKeyName());
	}

	protected function performInsert(Builder $query)
	{
		if ($this->fireModelEvent('creating') === false) {
			return false;
		}

		if ($this->usesTimestamps()) {
			$this->updateTimestamps();
		}

		$attributes = $this->attributes;

		if ($this->getIncrementing()) {
			$this->insertAndSetId($query, $attributes);
		}
		else {
			if (empty($attributes)) {
				return true;
			}

			$query->insert($attributes);
		}

		$this->exists = true;
		$this->wasRecentlyCreated = true;
		$this->fireModelEvent('created', false);
		return true;
	}

	protected function insertAndSetId(Builder $query, $attributes)
	{
		$id = $query->insertGetId($attributes, $keyName = $this->getKeyName());
		$this->setAttribute($keyName, $id);
	}

	static public function destroy($ids)
	{
		$count = 0;
		$ids = (is_array($ids) ? $ids : func_get_args());
		$key = with($instance = new static())->getKeyName();

		foreach ($instance->whereIn($key, $ids)->get() as $model) {
			if ($model->delete()) {
				$count++;
			}
		}

		return $count;
	}

	public function delete()
	{
		if (is_null($this->getKeyName())) {
			throw new \Exception('No primary key defined on model.');
		}

		if (!$this->exists) {
			return NULL;
		}

		if ($this->fireModelEvent('deleting') === false) {
			return false;
		}

		$this->touchOwners();
		$this->performDeleteOnModel();
		$this->exists = false;
		$this->fireModelEvent('deleted', false);
		return true;
	}

	public function forceDelete()
	{
		return $this->delete();
	}

	protected function performDeleteOnModel()
	{
		$this->setKeysForSaveQuery($this->newQueryWithoutScopes())->delete();
	}

	static public function query()
	{
		return (new static())->newQuery();
	}

	public function newQuery()
	{
		$builder = $this->newQueryWithoutScopes();

		foreach ($this->getGlobalScopes() as $identifier => $scope) {
			$builder->withGlobalScope($identifier, $scope);
		}

		return $builder;
	}

	public function newQueryWithoutScopes()
	{
		$builder = $this->newEloquentBuilder($this->newBaseQueryBuilder());
		return $builder->setModel($this)->with($this->with)->withCount($this->withCount);
	}

	public function newQueryWithoutScope($scope)
	{
		$builder = $this->newQuery();
		return $builder->withoutGlobalScope($scope);
	}

	public function newEloquentBuilder($query)
	{
		return new Builder($query);
	}

	protected function newBaseQueryBuilder()
	{
		$connection = $this->getConnection();
		return new \Illuminate\Database\Query\Builder($connection, $connection->getQueryGrammar(), $connection->getPostProcessor());
	}

	public function newCollection(array $models = array())
	{
		return new Collection($models);
	}

	public function newPivot(Model $parent, array $attributes, $table, $exists, $using = NULL)
	{
		return $using ? $using::fromRawAttributes($parent, $attributes, $table, $exists) : new Relations\Pivot($parent, $attributes, $table, $exists);
	}

	public function toArray()
	{
		return array_merge($this->attributesToArray(), $this->relationsToArray());
	}

	public function toJson($options = 0)
	{
		$json = json_encode($this->jsonSerialize(), $options);

		if (JSON_ERROR_NONE !== json_last_error()) {
			throw JsonEncodingException::forModel($this, json_last_error_msg());
		}

		return $json;
	}

	public function jsonSerialize()
	{
		return $this->toArray();
	}

	public function fresh($with = array())
	{
		if (!$this->exists) {
			return NULL;
		}

		return static::newQueryWithoutScopes()->with(is_string($with) ? func_get_args() : $with)->where($this->getKeyName(), $this->getKey())->first();
	}

	public function refresh()
	{
		if (!$this->exists) {
			return NULL;
		}
/* [31m * TODO SEPARATE[0m */

		$this->load(array_keys($this->relations));
		$this->setRawAttributes(static::findOrFail($this->getKey())->attributes);
	}

	public function replicate(array $except = NULL)
	{
		$defaults = array($this->getKeyName(), $this->getCreatedAtColumn(), $this->getUpdatedAtColumn());
		$attributes = \Illuminate\Support\Arr::except($this->attributes, $except ? array_unique(array_merge($except, $defaults)) : $defaults);
		return tap(new static(), function($instance) use($attributes) {
			$instance->setRawAttributes($attributes);
			$instance->setRelations($this->relations);
		});
	}

	public function is(Model $model)
	{
		return ($this->getKey() === $model->getKey()) && ($this->getTable() === $model->getTable()) && ($this->getConnectionName() === $model->getConnectionName());
	}

	public function getConnection()
	{
		return static::resolveConnection($this->getConnectionName());
	}

	public function getConnectionName()
	{
		return $this->connection;
	}

	public function setConnection($name)
	{
		$this->connection = $name;
		return $this;
	}

	static public function resolveConnection($connection = NULL)
	{
		return static::$resolver->connection($connection);
	}

	static public function getConnectionResolver()
	{
		return static::$resolver;
	}

	static public function setConnectionResolver(\Illuminate\Database\ConnectionResolverInterface $resolver)
	{
		static::$resolver = $resolver;
	}

	static public function unsetConnectionResolver()
	{
		static::$resolver = null;
	}

	public function getTable()
	{
		if (!isset($this->table)) {
			return str_replace('\\', '', \Illuminate\Support\Str::snake(\Illuminate\Support\Str::plural(class_basename($this))));
		}

		return $this->table;
	}

	public function setTable($table)
	{
		$this->table = $table;
		return $this;
	}

	public function getKeyName()
	{
		return $this->primaryKey;
	}

	public function setKeyName($key)
	{
		$this->primaryKey = $key;
		return $this;
	}

	public function getQualifiedKeyName()
	{
		return $this->getTable() . '.' . $this->getKeyName();
	}

	public function getKeyType()
	{
		return $this->keyType;
	}

	public function setKeyType($type)
	{
		$this->keyType = $type;
		return $this;
	}

	public function getIncrementing()
	{
		return $this->incrementing;
	}

	public function setIncrementing($value)
	{
		$this->incrementing = $value;
		return $this;
	}

	public function getKey()
	{
		return $this->getAttribute($this->getKeyName());
	}

	public function getQueueableId()
	{
		return $this->getKey();
	}

	public function getRouteKey()
	{
		return $this->getAttribute($this->getRouteKeyName());
	}

	public function getRouteKeyName()
	{
		return $this->getKeyName();
	}

	public function getForeignKey()
	{
		return \Illuminate\Support\Str::snake(class_basename($this)) . '_' . $this->primaryKey;
	}

	public function getPerPage()
	{
		return $this->perPage;
	}

	public function setPerPage($perPage)
	{
		$this->perPage = $perPage;
		return $this;
	}

	public function __get($key)
	{
		return $this->getAttribute($key);
	}

	public function __set($key, $value)
	{
		$this->setAttribute($key, $value);
	}

	public function offsetExists($offset)
	{
		return isset($this->$offset);
	}

	public function offsetGet($offset)
	{
		return $this->$offset;
	}

	public function offsetSet($offset, $value)
	{
		$this->$offset = $value;
	}

	public function offsetUnset($offset)
	{
		unset($this->$offset);
	}

	public function __isset($key)
	{
		return !is_null($this->getAttribute($key));
	}

	public function __unset($key)
	{
		unset($this->attributes[$key]);
		unset($this->relations[$key]);
	}

	public function __call($method, $parameters)
	{
		if (in_array($method, array('increment', 'decrement'))) {
			return $this->$method(...$parameters);
		}

		return $this->newQuery()->$method(...$parameters);
	}

	static public function __callStatic($method, $parameters)
	{
		return (new static())->$method(...$parameters);
	}

	public function __toString()
	{
		return $this->toJson();
	}

	public function __wakeup()
	{
		$this->bootIfNotBooted();
	}
}

?>
