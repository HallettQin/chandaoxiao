<?php
//cgxlm
namespace Illuminate\Database\Eloquent\Relations;

class MorphTo extends BelongsTo
{
	/**
     * The type of the polymorphic relation.
     *
     * @var string
     */
	protected $morphType;
	/**
     * The models whose relations are being eager loaded.
     *
     * @var \Illuminate\Database\Eloquent\Collection
     */
	protected $models;
	/**
     * All of the models keyed by ID.
     *
     * @var array
     */
	protected $dictionary = array();
	/**
     * A buffer of dynamic calls to query macros.
     *
     * @var array
     */
	protected $macroBuffer = array();

	public function __construct(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $foreignKey, $ownerKey, $type, $relation)
	{
		$this->morphType = $type;
		parent::__construct($query, $parent, $foreignKey, $ownerKey, $relation);
	}

	public function addEagerConstraints(array $models)
	{
		$this->buildDictionary($this->models = \Illuminate\Database\Eloquent\Collection::make($models));
	}

	protected function buildDictionary(\Illuminate\Database\Eloquent\Collection $models)
	{
		foreach ($models as $model) {
			if ($model->{$this->morphType}) {
				$this->dictionary[$model->{$this->morphType}][$model->{$this->foreignKey}][] = $model;
			}
		}
	}

	public function getResults()
	{
		return $this->ownerKey ? $this->query->first() : null;
	}

	public function getEager()
	{
		foreach (array_keys($this->dictionary) as $type) {
			$this->matchToMorphParents($type, $this->getResultsByType($type));
		}

		return $this->models;
	}

	protected function getResultsByType($type)
	{
		$instance = $this->createModelByType($type);
		$query = $this->replayMacros($instance->newQuery())->mergeConstraintsFrom($this->getQuery())->with($this->getQuery()->getEagerLoads());
		return $query->whereIn($instance->getTable() . '.' . $instance->getKeyName(), $this->gatherKeysByType($type))->get();
	}

	protected function gatherKeysByType($type)
	{
		return collect($this->dictionary[$type])->map(function($models) {
			return head($models)->{$this->foreignKey};
		})->values()->unique()->all();
	}

	public function createModelByType($type)
	{
		$class = \Illuminate\Database\Eloquent\Model::getActualClassNameForMorph($type);
		return new $class();
	}

	public function match(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation)
	{
		return $models;
	}

	protected function matchToMorphParents($type, \Illuminate\Database\Eloquent\Collection $results)
	{
		foreach ($results as $result) {
			if (isset($this->dictionary[$type][$result->getKey()])) {
				foreach ($this->dictionary[$type][$result->getKey()] as $model) {
					$model->setRelation($this->relation, $result);
				}
			}
		}
	}

	public function associate($model)
	{
		$this->parent->setAttribute($this->foreignKey, $model->getKey());
		$this->parent->setAttribute($this->morphType, $model->getMorphClass());
		return $this->parent->setRelation($this->relation, $model);
	}

	public function dissociate()
	{
		$this->parent->setAttribute($this->foreignKey, null);
		$this->parent->setAttribute($this->morphType, null);
		return $this->parent->setRelation($this->relation, null);
	}

	public function getMorphType()
	{
		return $this->morphType;
	}

	public function getDictionary()
	{
		return $this->dictionary;
	}

	protected function replayMacros(\Illuminate\Database\Eloquent\Builder $query)
	{
		foreach ($this->macroBuffer as $macro) {
			$query->$macro['method'](...$macro['parameters']);
		}

		return $query;
	}

	public function __call($method, $parameters)
	{
		try {
			return parent::__call($method, $parameters);
		}
		catch (\BadMethodCallException $e) {
			$this->macroBuffer[] = compact('method', 'parameters');
			return $this;
		}
	}
}

?>
