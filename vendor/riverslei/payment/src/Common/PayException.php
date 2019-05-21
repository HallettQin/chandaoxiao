<?php
//cgxlm
namespace Payment\Common;

class PayException extends \Exception
{
	public function errorMessage()
	{
		return $this->getMessage();
	}
}

?>
