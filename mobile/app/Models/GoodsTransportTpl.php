<?php
//cgxlm
namespace App\Models;

class GoodsTransportTpl extends \Illuminate\Database\Eloquent\Model
{
	protected $table = 'goods_transport_tpl';
	public $timestamps = false;
	protected $fillable = array('tid', 'user_id', 'shipping_id', 'region_id', 'configure');
	protected $guarded = array();

	public function getTid()
	{
		return $this->tid;
	}

	public function getUserId()
	{
		return $this->user_id;
	}

	public function getShippingId()
	{
		return $this->shipping_id;
	}

	public function getRegionId()
	{
		return $this->region_id;
	}

	public function getConfigure()
	{
		return $this->configure;
	}

	public function setTid($value)
	{
		$this->tid = $value;
		return $this;
	}

	public function setUserId($value)
	{
		$this->user_id = $value;
		return $this;
	}

	public function setShippingId($value)
	{
		$this->shipping_id = $value;
		return $this;
	}

	public function setRegionId($value)
	{
		$this->region_id = $value;
		return $this;
	}

	public function setConfigure($value)
	{
		$this->configure = $value;
		return $this;
	}
}

?>
