<?php
//cgxlm
namespace Illuminate\Database\Schema\Grammars;

class RenameColumn
{
	static public function compile(Grammar $grammar, \Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command, \Illuminate\Database\Connection $connection)
	{
		$column = $connection->getDoctrineColumn($grammar->getTablePrefix() . $blueprint->getTable(), $command->from);
		$schema = $connection->getDoctrineSchemaManager();
		return (array) $schema->getDatabasePlatform()->getAlterTableSQL(static::getRenamedDiff($grammar, $blueprint, $command, $column, $schema));
	}

	static protected function getRenamedDiff(Grammar $grammar, \Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command, \Doctrine\DBAL\Schema\Column $column, \Doctrine\DBAL\Schema\AbstractSchemaManager $schema)
	{
		return static::setRenamedColumns($grammar->getDoctrineTableDiff($blueprint, $schema), $command, $column);
	}

	static protected function setRenamedColumns(\Doctrine\DBAL\Schema\TableDiff $tableDiff, \Illuminate\Support\Fluent $command, \Doctrine\DBAL\Schema\Column $column)
	{
		$tableDiff->renamedColumns = array($command->from => new \Doctrine\DBAL\Schema\Column($command->to, $column->getType(), $column->toArray()));
		return $tableDiff;
	}
}


?>
