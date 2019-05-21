<?php
//cgxlm
namespace Carbon\Exceptions;

class InvalidDateException extends \InvalidArgumentException
{
	/**
     * The invalid field.
     *
     * @var string
     */
	private $field;
	/**
     * The invalid value.
     *
     * @var mixed
     */
	private $value;

	public function __construct($field, $value, $code = 0, \Exception $previous = NULL)
	{
		$this->field = $field;
		$this->value = $value;
		parent::__construct($field . ' : ' . $value . ' is not a valid value.', $code, $previous);
	}

	public function getField()
	{
		return $this->field;
	}

	public function getValue()
	{
		return $this->value;
	}
}

?>
