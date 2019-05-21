<?php
//cgxlm
namespace Illuminate\Database\Events;

class StatementPrepared
{
	/**
     * The database connection instance.
     *
     * @var \Illuminate\Database\Connection
     */
	public $connection;
	/**
     * The PDO statement.
     *
     * @var \PDOStatement
     */
	public $statement;

	public function __construct($connection, $statement)
	{
		$this->statement = $statement;
		$this->connection = $connection;
	}
}


?>
