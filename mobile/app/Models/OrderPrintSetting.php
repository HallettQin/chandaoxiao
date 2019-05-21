<?php
//cgxlm
namespace App\Models;

class OrderPrintSetting extends \Illuminate\Database\Eloquent\Model
{
	protected $table = 'order_print_setting';
	public $timestamps = false;
	protected $fillable = array('ru_id', 'specification', 'printer', 'is_default');
	protected $guarded = array();

	public function getRuId()
	{
		return $this->ru_id;
	}

	public function getSpecification()
	{
		return $this->specification;
	}

	public function getPrinter()
	{
		return $this->printer;
	}

	public function getIsDefault()
	{
		return $this->is_default;
	}

	public function setRuId($value)
	{
		$this->ru_id = $value;
		return $this;
	}

	public function setSpecification($value)
	{
		$this->specification = $value;
		return $this;
	}

	public function setPrinter($value)
	{
		$this->printer = $value;
		return $this;
	}

	public function setIsDefault($value)
	{
		$this->is_default = $value;
		return $this;
	}
}

?>
