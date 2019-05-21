<?php
//cgxlm
namespace Illuminate\Database\Eloquent\Relations;

class HasOne extends HasOneOrMany
{
	/**
     * Indicates if a default model instance should be used.
     *
     * Alternatively, may be a Closure or array.
     *
     * @var \Closure|array|bool
     */
	protected $withDefault;

	public function getResults()
	{
		return $this->query->first() ?: $this->getDefaultFor($this->parent);
	}

	public function initRelation(array $models, $relation)
	{
		foreach ($models as $model) {
			$model->setRelation($relation, $this->getDefaultFor($model));
		}

		return $models;
	}

	protected function getDefaultFor(\Illuminate\Database\Eloquent\Model $model)
	{
		if (!$this->withDefault) {
			return NULL;
		}

		$instance = $this->related->newInstance()->setAttribute($this->getForeignKeyName(), $model->getAttribute($this->localKey));

		if (is_callable($this->withDefault)) {
			return call_user_func($this->withDefault, $instance) ?: $instance;
		}

		if (is_array($this->withDefault)) {
			$instance->forceFill($this->withDefault);
		}

		return $instance;
	}

	public function match(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation)
	{
		return $this->matchOne($models, $results, $relation);
	}

	public function withDefault($callback = true)
	{
		$this->withDefault = $callback;
		return $this;
	}
}

?>
