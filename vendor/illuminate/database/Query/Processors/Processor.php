<?php
//cgxlm
namespace Illuminate\Database\Query\Processors;

class Processor
{
	public function processSelect(\Illuminate\Database\Query\Builder $query, $results)
	{
		return $results;
	}

	public function processInsertGetId(\Illuminate\Database\Query\Builder $query, $sql, $values, $sequence = NULL)
	{
		$query->getConnection()->insert($sql, $values);
		$id = $query->getConnection()->getPdo()->lastInsertId($sequence);
		return is_numeric($id) ? (int) $id : $id;
	}

	public function processColumnListing($results)
	{
		return $results;
	}
}


?>
