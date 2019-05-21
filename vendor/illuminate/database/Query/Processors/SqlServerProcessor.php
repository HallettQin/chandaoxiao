<?php
//cgxlm
namespace Illuminate\Database\Query\Processors;

class SqlServerProcessor extends Processor
{
	public function processInsertGetId(\Illuminate\Database\Query\Builder $query, $sql, $values, $sequence = NULL)
	{
		$connection = $query->getConnection();
		$connection->insert($sql, $values);

		if ($connection->getConfig('odbc') === true) {
			$id = $this->processInsertGetIdForOdbc($connection);
		}
		else {
			$id = $connection->getPdo()->lastInsertId();
		}

		return is_numeric($id) ? (int) $id : $id;
	}

	protected function processInsertGetIdForOdbc(\Illuminate\Database\Connection $connection)
	{
		$result = $connection->selectFromWriteConnection('SELECT CAST(COALESCE(SCOPE_IDENTITY(), @@IDENTITY) AS int) AS insertid');

		if (!$result) {
			throw new \Exception('Unable to retrieve lastInsertID for ODBC.');
		}

		$row = $result[0];
		return is_object($row) ? $row->insertid : $row['insertid'];
	}

	public function processColumnListing($results)
	{
		return array_map(function($result) {
			return with((object) $result)->name;
		}, $results);
	}
}

?>
