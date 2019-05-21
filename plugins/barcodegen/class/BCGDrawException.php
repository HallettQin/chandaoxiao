<?php
//cgxlm
class BCGDrawException extends Exception
{
	public function __construct($message)
	{
		parent::__construct($message, 30000);
	}
}

?>
