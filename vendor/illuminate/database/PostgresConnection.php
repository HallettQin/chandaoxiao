<?php
//cgxlm
namespace Illuminate\Database;

class PostgresConnection extends Connection
{
	protected function getDefaultQueryGrammar()
	{
		return $this->withTablePrefix(new Query\Grammars\PostgresGrammar());
	}

	public function getSchemaBuilder()
	{
		if (is_null($this->schemaGrammar)) {
			$this->useDefaultSchemaGrammar();
		}

		return new Schema\PostgresBuilder($this);
	}

	protected function getDefaultSchemaGrammar()
	{
		return $this->withTablePrefix(new Schema\Grammars\PostgresGrammar());
	}

	protected function getDefaultPostProcessor()
	{
		return new Query\Processors\PostgresProcessor();
	}

	protected function getDoctrineDriver()
	{
		return new \Doctrine\DBAL\Driver\PDOPgSql\Driver();
	}
}

?>
