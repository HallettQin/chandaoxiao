<?php
//cgxlm
namespace Illuminate\Contracts\Queue;

class EntityNotFoundException extends \InvalidArgumentException
{
	public function __construct($type, $id)
	{
		$id = (string) $id;
		parent::__construct('Queueable entity [' . $type . '] not found for ID [' . $id . '].');
	}
}

?>
