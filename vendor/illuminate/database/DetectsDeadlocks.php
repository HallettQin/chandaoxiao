<?php
//cgxlm
namespace Illuminate\Database;

trait DetectsDeadlocks
{
	protected function causedByDeadlock(\Exception $e)
	{
		$message = $e->getMessage();
		return \Illuminate\Support\Str::contains($message, array('Deadlock found when trying to get lock', 'deadlock detected', 'The database file is locked', 'database is locked', 'database table is locked', 'A table in the database is locked', 'has been chosen as the deadlock victim'));
	}
}


?>
