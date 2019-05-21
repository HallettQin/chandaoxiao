<?php
//cgxlm
namespace Illuminate\Database\Query;

class Expression
{
	/**
     * The value of the expression.
     *
     * @var mixed
     */
	protected $value;

	public function __construct($value)
	{
		$this->value = $value;
	}

	public function getValue()
	{
		return $this->value;
	}

	public function __toString()
	{
		return (string) $this->getValue();
	}
}


?>
