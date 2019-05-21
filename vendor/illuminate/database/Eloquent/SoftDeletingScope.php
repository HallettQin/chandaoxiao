<?php
//cgxlm
namespace Illuminate\Database\Eloquent;

class SoftDeletingScope implements Scope
{
	/**
     * All of the extensions to be added to the builder.
     *
     * @var array
     */
	protected $extensions = array('Restore', 'WithTrashed', 'WithoutTrashed', 'OnlyTrashed');

	public function apply(Builder $builder, Model $model)
	{
		$builder->whereNull($model->getQualifiedDeletedAtColumn());
	}

	public function extend(Builder $builder)
	{
		foreach ($this->extensions as $extension) {
			$this->{'add' . $extension}($builder);
		}

		$builder->onDelete(function(Builder $builder) {
			$column = $this->getDeletedAtColumn($builder);
			return $builder->update(array($column => $builder->getModel()->freshTimestampString()));
		});
	}

	protected function getDeletedAtColumn(Builder $builder)
	{
/* [31m * TODO SEPARATE[0m */
		if (0 < count($builder->getQuery()->joins)) {
			return $builder->getModel()->getQualifiedDeletedAtColumn();
		}

		return $builder->getModel()->getDeletedAtColumn();
	}

	protected function addRestore(Builder $builder)
	{
		$builder->macro('restore', function(Builder $builder) {
			$builder->withTrashed();
			return $builder->update(array($builder->getModel()->getDeletedAtColumn() => null));
		});
	}

	protected function addWithTrashed(Builder $builder)
	{
		$builder->macro('withTrashed', function(Builder $builder) {
			return $builder->withoutGlobalScope($this);
		});
	}

	protected function addWithoutTrashed(Builder $builder)
	{
		$builder->macro('withoutTrashed', function(Builder $builder) {
			$model = $builder->getModel();
			$builder->withoutGlobalScope($this)->whereNull($model->getQualifiedDeletedAtColumn());
			return $builder;
		});
	}

	protected function addOnlyTrashed(Builder $builder)
	{
		$builder->macro('onlyTrashed', function(Builder $builder) {
			$model = $builder->getModel();
			$builder->withoutGlobalScope($this)->whereNotNull($model->getQualifiedDeletedAtColumn());
			return $builder;
		});
	}
}

?>
