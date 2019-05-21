<?php
//cgxlm
namespace App\Models;

class LinkGoodsDesc extends \Illuminate\Database\Eloquent\Model
{
	protected $table = 'link_goods_desc';
	public $timestamps = false;
	protected $fillable = array('goods_id', 'desc_name', 'goods_desc');
	protected $guarded = array();

	public function getGoodsId()
	{
		return $this->goods_id;
	}

	public function getDescName()
	{
		return $this->desc_name;
	}

	public function getGoodsDesc()
	{
		return $this->goods_desc;
	}

	public function setGoodsId($value)
	{
		$this->goods_id = $value;
		return $this;
	}

	public function setDescName($value)
	{
		$this->desc_name = $value;
		return $this;
	}

	public function setGoodsDesc($value)
	{
		$this->goods_desc = $value;
		return $this;
	}
}

?>
