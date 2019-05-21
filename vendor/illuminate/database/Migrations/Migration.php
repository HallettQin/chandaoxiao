<?php
//cgxlm
namespace Illuminate\Database\Migrations;

abstract class Migration
{
	/**
     * The name of the database connection to use.
     *
     * @var string
     */
	protected $connection;

	public function getConnection()
	{
		return $this->connection;
	}
}


?>
