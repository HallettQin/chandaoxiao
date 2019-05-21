<?php
//cgxlm
namespace Illuminate\Database\Query\Processors;

class PostgresProcessor extends Processor
{
	public function processInsertGetId(\Illuminate\Database\Query\Builder $query, $sql, $values, $sequence = NULL)
	{
		list($result) = $query->getConnection()->selectFromWriteConnection($sql, $values);
		$sequence = $sequence ?: 'id';
		$id = (is_object($result) ? $result->$sequence : $result[$sequence]);
		return is_numeric($id) ? (int) $id : $id;
	}

	public function processColumnListing($results)
	{
		return array_map(function($result) {
			return with((object) $result)->column_name;
		}, $results);
	}
}

?>
