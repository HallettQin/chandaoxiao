<?php
//cgxlm
namespace Illuminate\Database\Schema;

class PostgresBuilder extends Builder
{
	public function hasTable($table)
	{
		if (is_array($schema = $this->connection->getConfig('schema'))) {
			$schema = head($schema);
		}

		$schema = ($schema ? $schema : 'public');
		$table = $this->connection->getTablePrefix() . $table;
		return 0 < count($this->connection->select($this->grammar->compileTableExists(), array($schema, $table)));
	}
}

?>
