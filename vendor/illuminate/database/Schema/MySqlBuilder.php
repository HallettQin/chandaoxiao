<?php
//cgxlm
namespace Illuminate\Database\Schema;

class MySqlBuilder extends Builder
{
	public function hasTable($table)
	{
		$table = $this->connection->getTablePrefix() . $table;
		return 0 < count($this->connection->select($this->grammar->compileTableExists(), array($this->connection->getDatabaseName(), $table)));
	}

	public function getColumnListing($table)
	{
		$table = $this->connection->getTablePrefix() . $table;
		$results = $this->connection->select($this->grammar->compileColumnListing(), array($this->connection->getDatabaseName(), $table));
		return $this->connection->getPostProcessor()->processColumnListing($results);
	}
}

?>
