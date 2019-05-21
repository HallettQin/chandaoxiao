<?php
//cgxlm
namespace Illuminate\Database\Eloquent\Relations\Concerns;

trait InteractsWithPivotTable
{
	public function toggle($ids, $touch = true)
	{
		$changes = array(
			'attached' => array(),
			'detached' => array()
			);
		$records = $this->formatRecordsList((array) $this->parseIds($ids));
		$detach = array_values(array_intersect($this->newPivotQuery()->pluck($this->relatedKey)->all(), array_keys($records)));

		if (0 < count($detach)) {
			$this->detach($detach, false);
			$changes['detached'] = $this->castKeys($detach);
		}

		$attach = array_diff_key($records, array_flip($detach));

		if (0 < count($attach)) {
			$this->attach($attach, array(), false);
			$changes['attached'] = array_keys($attach);
		}

		if ($touch && (count($changes['attached']) || count($changes['detached']))) {
			$this->touchIfTouching();
		}

		return $changes;
	}

	public function syncWithoutDetaching($ids)
	{
		return $this->sync($ids, false);
	}

	public function sync($ids, $detaching = true)
	{
		$changes = array(
			'attached' => array(),
			'detached' => array(),
			'updated'  => array()
			);
		$current = $this->newPivotQuery()->pluck($this->relatedKey)->all();
		$detach = array_diff($current, array_keys($records = $this->formatRecordsList((array) $this->parseIds($ids))));
		if ($detaching && (0 < count($detach))) {
			$this->detach($detach);
			$changes['detached'] = $this->castKeys($detach);
		}

		$changes = array_merge($changes, $this->attachNew($records, $current, false));
		if (count($changes['attached']) || count($changes['updated'])) {
			$this->touchIfTouching();
		}

		return $changes;
	}

	protected function formatRecordsList(array $records)
	{
		return collect($records)->mapWithKeys(function($attributes, $id) {
			if (!is_array($attributes)) {
				list($id, $attributes) = array(
					$attributes,
					array()
					);
			}

			return array($id => $attributes);
		})->all();
	}

	protected function attachNew(array $records, array $current, $touch = true)
	{
		$changes = array(
			'attached' => array(),
			'updated'  => array()
			);

		foreach ($records as $id => $attributes) {
			if (!in_array($id, $current)) {
				$this->attach($id, $attributes, $touch);
				$changes['attached'][] = $this->castKey($id);
			}
			else {
				if ((0 < count($attributes)) && $this->updateExistingPivot($id, $attributes, $touch)) {
					$changes['updated'][] = $this->castKey($id);
				}
			}
		}

		return $changes;
	}

	public function updateExistingPivot($id, array $attributes, $touch = true)
	{
		if (in_array($this->updatedAt(), $this->pivotColumns)) {
			$attributes = $this->addTimestampsToAttachment($attributes, true);
		}

		$updated = $this->newPivotStatementForId($id)->update($attributes);

		if ($touch) {
			$this->touchIfTouching();
		}

		return $updated;
	}

	public function attach($id, array $attributes = array(), $touch = true)
	{
		$this->newPivotStatement()->insert($this->formatAttachRecords((array) $this->parseIds($id), $attributes));

		if ($touch) {
			$this->touchIfTouching();
		}
	}

	protected function formatAttachRecords($ids, array $attributes)
	{
		$records = array();
		$hasTimestamps = $this->hasPivotColumn($this->createdAt()) || $this->hasPivotColumn($this->updatedAt());

		foreach ($ids as $key => $value) {
			$records[] = $this->formatAttachRecord($key, $value, $attributes, $hasTimestamps);
		}

		return $records;
	}

	protected function formatAttachRecord($key, $value, $attributes, $hasTimestamps)
	{
		list($id, $attributes) = $this->extractAttachIdAndAttributes($key, $value, $attributes);
		return array_merge($this->baseAttachRecord($id, $hasTimestamps), $attributes);
	}

	protected function extractAttachIdAndAttributes($key, $value, array $attributes)
	{
		return is_array($value) ? array($key, array_merge($value, $attributes)) : array($value, $attributes);
	}

	protected function baseAttachRecord($id, $timed)
	{
		$record[$this->relatedKey] = $id;
		$record[$this->foreignKey] = $this->parent->getKey();

		if ($timed) {
			$record = $this->addTimestampsToAttachment($record);
		}

		return $record;
	}

	protected function addTimestampsToAttachment(array $record, $exists = false)
	{
		$fresh = $this->parent->freshTimestamp();
		if (!$exists && $this->hasPivotColumn($this->createdAt())) {
			$record[$this->createdAt()] = $fresh;
		}

		if ($this->hasPivotColumn($this->updatedAt())) {
			$record[$this->updatedAt()] = $fresh;
		}

		return $record;
	}

	protected function hasPivotColumn($column)
	{
		return in_array($column, $this->pivotColumns);
	}

	public function detach($ids = NULL, $touch = true)
	{
		$query = $this->newPivotQuery();

		if (!is_null($ids = $this->parseIds($ids))) {
			if (count($ids) === 0) {
				return 0;
			}

			$query->whereIn($this->relatedKey, (array) $ids);
		}

		$results = $query->delete();

		if ($touch) {
			$this->touchIfTouching();
		}

		return $results;
	}

	public function newPivot(array $attributes = array(), $exists = false)
	{
		$pivot = $this->related->newPivot($this->parent, $attributes, $this->table, $exists, $this->using);
		return $pivot->setPivotKeys($this->foreignKey, $this->relatedKey);
	}

	public function newExistingPivot(array $attributes = array())
	{
		return $this->newPivot($attributes, true);
	}

	public function newPivotStatement()
	{
		return $this->query->getQuery()->newQuery()->from($this->table);
	}

	public function newPivotStatementForId($id)
	{
		return $this->newPivotQuery()->where($this->relatedKey, $id);
	}

	protected function newPivotQuery()
	{
		$query = $this->newPivotStatement();

		foreach ($this->pivotWheres as $arguments) {
			call_user_func_array(array($query, 'where'), $arguments);
		}

		foreach ($this->pivotWhereIns as $arguments) {
			call_user_func_array(array($query, 'whereIn'), $arguments);
		}

		return $query->where($this->foreignKey, $this->parent->getKey());
	}

	public function withPivot($columns)
	{
		$this->pivotColumns = array_merge($this->pivotColumns, is_array($columns) ? $columns : func_get_args());
		return $this;
	}

	protected function parseIds($value)
	{
		if ($value instanceof \Illuminate\Database\Eloquent\Model) {
			return $value->getKey();
		}

		if ($value instanceof \Illuminate\Database\Eloquent\Collection) {
			return $value->modelKeys();
		}

		if ($value instanceof \Illuminate\Support\Collection) {
			return $value->toArray();
		}

		return $value;
	}

	protected function castKeys(array $keys)
	{
		return (array) array_map(function($v) {
			return $this->castKey($v);
		}, $keys);
	}

	protected function castKey($key)
	{
		return is_numeric($key) ? (int) $key : (string) $key;
	}
}


?>
