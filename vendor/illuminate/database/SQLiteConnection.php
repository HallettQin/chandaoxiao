<?php
//cgxlm
namespace Illuminate\Database;

class SQLiteConnection extends Connection
{
	protected function getDefaultQueryGrammar()
	{
		return $this->withTablePrefix(new Query\Grammars\SQLiteGrammar());
	}

	protected function getDefaultSchemaGrammar()
	{
		return $this->withTablePrefix(new Schema\Grammars\SQLiteGrammar());
	}

	protected function getDefaultPostProcessor()
	{
		return new Query\Processors\SQLiteProcessor();
	}

	protected function getDoctrineDriver()
	{
		return new \Doctrine\DBAL\Driver\PDOSqlite\Driver();
	}
}

?>
