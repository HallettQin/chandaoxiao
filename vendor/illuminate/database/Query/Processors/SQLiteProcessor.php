<?php
//cgxlm
namespace Illuminate\Database\Query\Processors;

class SQLiteProcessor extends Processor
{
	public function processColumnListing($results)
	{
		return array_map(function($result) {
			return with((object) $result)->name;
		}, $results);
	}
}

?>
