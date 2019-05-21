<?php
//cgxlm
namespace Illuminate\Database\Eloquent\Relations;

class MorphMany extends MorphOneOrMany
{
	public function getResults()
	{
		return $this->query->get();
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
		return $this->matchMany($models, $results, $relation);
	}
}

?>
