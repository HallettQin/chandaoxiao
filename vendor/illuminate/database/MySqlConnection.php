<?php
//cgxlm
namespace Illuminate\Database;

class MySqlConnection extends Connection
{
	protected function getDefaultQueryGrammar()
	{
		return $this->withTablePrefix(new Query\Grammars\MySqlGrammar());
	}

	public function getSchemaBuilder()
	{
		if (is_null($this->schemaGrammar)) {
			$this->useDefaultSchemaGrammar();
		}

		return new Schema\MySqlBuilder($this);
	}

	protected function getDefaultSchemaGrammar()
	{
		return $this->withTablePrefix(new Schema\Grammars\MySqlGrammar());
	}

	protected function getDefaultPostProcessor()
	{
		return new Query\Processors\MySqlProcessor();
	}

	protected function getDoctrineDriver()
	{
		return new \Doctrine\DBAL\Driver\PDOMySql\Driver();
	}

	public function bindValues($statement, $bindings)
	{
		foreach ($bindings as $key => $value) {
			$statement->bindValue(is_string($key) ? $key : $key + 1, $value, is_int($value) || is_float($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
		}
	}
}

?>
