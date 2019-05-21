<?php
//cgxlm
namespace Illuminate\Database;

class SqlServerConnection extends Connection
{
	public function transaction(\Closure $callback, $attempts = 1)
	{
		for ($a = 1; $a <= $attempts; $a++) {
			if ($this->getDriverName() == 'sqlsrv') {
				return parent::transaction($callback);
			}

			$this->getPdo()->exec('BEGIN TRAN');

			try {
				$result = $callback($this);
				$this->getPdo()->exec('COMMIT TRAN');
			}
			catch (\Exception $e) {
				$this->getPdo()->exec('ROLLBACK TRAN');
				throw $e;
			}
			catch (\Throwable $e) {
				$this->getPdo()->exec('ROLLBACK TRAN');
				throw $e;
			}

			return $result;
		}
	}

	protected function getDefaultQueryGrammar()
	{
		return $this->withTablePrefix(new Query\Grammars\SqlServerGrammar());
	}

	protected function getDefaultSchemaGrammar()
	{
		return $this->withTablePrefix(new Schema\Grammars\SqlServerGrammar());
	}

	protected function getDefaultPostProcessor()
	{
		return new Query\Processors\SqlServerProcessor();
	}

	protected function getDoctrineDriver()
	{
		return new \Doctrine\DBAL\Driver\PDOSqlsrv\Driver();
	}
}

?>
