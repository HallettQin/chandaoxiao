<?php
//cgxlm
namespace Illuminate\Database\Schema\Grammars;

class ChangeColumn
{
	static public function compile($grammar, \Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command, \Illuminate\Database\Connection $connection)
	{
		if (!$connection->isDoctrineAvailable()) {
			throw new \RuntimeException(sprintf('Changing columns for table "%s" requires Doctrine DBAL; install "doctrine/dbal".', $blueprint->getTable()));
		}

		$tableDiff = static::getChangedDiff($grammar, $blueprint, $schema = $connection->getDoctrineSchemaManager());

		if ($tableDiff !== false) {
			return (array) $schema->getDatabasePlatform()->getAlterTableSQL($tableDiff);
		}

		return array();
	}

	static protected function getChangedDiff($grammar, \Illuminate\Database\Schema\Blueprint $blueprint, \Doctrine\DBAL\Schema\AbstractSchemaManager $schema)
	{
		$current = $schema->listTableDetails($grammar->getTablePrefix() . $blueprint->getTable());
		return (new \Doctrine\DBAL\Schema\Comparator())->diffTable($current, static::getTableWithColumnChanges($blueprint, $current));
	}

	static protected function getTableWithColumnChanges(\Illuminate\Database\Schema\Blueprint $blueprint, \Doctrine\DBAL\Schema\Table $table)
	{
		$table = clone $table;

		foreach ($blueprint->getChangedColumns() as $fluent) {
			$column = static::getDoctrineColumn($table, $fluent);

			foreach ($fluent->getAttributes() as $key => $value) {
				if (!is_null($option = static::mapFluentOptionToDoctrine($key))) {
					if (method_exists($column, $method = 'set' . ucfirst($option))) {
						$column->$method(static::mapFluentValueToDoctrine($option, $value));
					}
				}
			}
		}

		return $table;
	}

	static protected function getDoctrineColumn(\Doctrine\DBAL\Schema\Table $table, \Illuminate\Support\Fluent $fluent)
	{
		return $table->changeColumn($fluent['name'], static::getDoctrineColumnChangeOptions($fluent))->getColumn($fluent['name']);
	}

	static protected function getDoctrineColumnChangeOptions(\Illuminate\Support\Fluent $fluent)
	{
		$options = array('type' => static::getDoctrineColumnType($fluent['type']));

		if (in_array($fluent['type'], array('text', 'mediumText', 'longText'))) {
			$options['length'] = static::calculateDoctrineTextLength($fluent['type']);
		}

		return $options;
	}

	static protected function getDoctrineColumnType($type)
	{
		$type = strtolower($type);

		switch ($type) {
		case 'biginteger':
			$type = 'bigint';
			break;

		case 'smallinteger':
			$type = 'smallint';
			break;

		case 'mediumtext':
		case 'longtext':
			$type = 'text';
			break;

		case 'binary':
			$type = 'blob';
			break;
		}

		return \Doctrine\DBAL\Types\Type::getType($type);
	}

	static protected function calculateDoctrineTextLength($type)
	{
		switch ($type) {
		case 'mediumText':
			return 65535 + 1;
		case 'longText':
			return 16777215 + 1;
		default:
			return 255 + 1;
		}
	}

	static protected function mapFluentOptionToDoctrine($attribute)
	{
		switch ($attribute) {
		case 'type':
		case 'name':
			return NULL;
		case 'nullable':
			return 'notnull';
		case 'total':
			return 'precision';
		case 'places':
			return 'scale';
		default:
			return $attribute;
		}
	}

	static protected function mapFluentValueToDoctrine($option, $value)
	{
		return $option == 'notnull' ? !$value : $value;
	}
}


?>
