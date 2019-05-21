<?php
//cgxlm
namespace Illuminate\Database\Eloquent\Relations;

class Pivot extends \Illuminate\Database\Eloquent\Model
{
	/**
     * The parent model of the relationship.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
	public $parent;
	/**
     * The name of the foreign key column.
     *
     * @var string
     */
	protected $foreignKey;
	/**
     * The name of the "other key" column.
     *
     * @var string
     */
	protected $relatedKey;
	/**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
	protected $guarded = array();

	public function __construct(\Illuminate\Database\Eloquent\Model $parent, $attributes, $table, $exists = false)
	{
		parent::__construct();
		$this->setConnection($parent->getConnectionName())->setTable($table)->forceFill($attributes)->syncOriginal();
		$this->parent = $parent;
		$this->exists = $exists;
		$this->timestamps = $this->hasTimestampAttributes();
	}

	static public function fromRawAttributes(\Illuminate\Database\Eloquent\Model $parent, $attributes, $table, $exists = false)
	{
		$instance = new static($parent, $attributes, $table, $exists);
		$instance->setRawAttributes($attributes, true);
		return $instance;
	}

	protected function setKeysForSaveQuery(\Illuminate\Database\Eloquent\Builder $query)
	{
		$query->where($this->foreignKey, $this->getAttribute($this->foreignKey));
		return $query->where($this->relatedKey, $this->getAttribute($this->relatedKey));
	}

	public function delete()
	{
		return $this->getDeleteQuery()->delete();
	}

	protected function getDeleteQuery()
	{
		return $this->newQuery()->where(array($this->foreignKey => $this->getAttribute($this->foreignKey), $this->relatedKey => $this->getAttribute($this->relatedKey)));
	}

	public function getForeignKey()
	{
		return $this->foreignKey;
	}

	public function getRelatedKey()
	{
		return $this->relatedKey;
	}

	public function getOtherKey()
	{
		return $this->getRelatedKey();
	}

	public function setPivotKeys($foreignKey, $relatedKey)
	{
		$this->foreignKey = $foreignKey;
		$this->relatedKey = $relatedKey;
		return $this;
	}

	public function hasTimestampAttributes()
	{
		return array_key_exists($this->getCreatedAtColumn(), $this->attributes);
	}

	public function getCreatedAtColumn()
	{
		return $this->parent->getCreatedAtColumn();
	}

	public function getUpdatedAtColumn()
	{
		return $this->parent->getUpdatedAtColumn();
	}
}

?>
