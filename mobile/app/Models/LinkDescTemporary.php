<?php
//cgxlm
namespace App\Models;

class LinkDescTemporary extends \Illuminate\Database\Eloquent\Model
{
	protected $table = 'link_desc_temporary';
	public $timestamps = false;
	protected $fillable = array('goods_id');
	protected $guarded = array();

	public function getGoodsId()
	{
		return $this->goods_id;
	}

	public function setGoodsId($value)
	{
		$this->goods_id = $value;
		return $this;
	}
}

?>
