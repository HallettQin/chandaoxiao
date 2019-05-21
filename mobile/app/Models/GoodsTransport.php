<?php
//cgxlm
namespace App\Models;

class GoodsTransport extends \Illuminate\Database\Eloquent\Model
{
	protected $table = 'goods_transport';
	protected $primaryKey = 'tid';
	public $timestamps = false;
	protected $fillable = array('ru_id', 'type', 'freight_type', 'title', 'update_time');
	protected $guarded = array();

	public function getRuId()
	{
		return $this->ru_id;
	}

	public function getType()
	{
		return $this->type;
	}

	public function getFreightType()
	{
		return $this->freight_type;
	}

	public function getTitle()
	{
		return $this->title;
	}

	public function getUpdateTime()
	{
		return $this->update_time;
	}

	public function setRuId($value)
	{
		$this->ru_id = $value;
		return $this;
	}

	public function setType($value)
	{
		$this->type = $value;
		return $this;
	}

	public function setFreightType($value)
	{
		$this->freight_type = $value;
		return $this;
	}

	public function setTitle($value)
	{
		$this->title = $value;
		return $this;
	}

	public function setUpdateTime($value)
	{
		$this->update_time = $value;
		return $this;
	}
}

?>
