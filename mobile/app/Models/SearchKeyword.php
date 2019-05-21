<?php
//cgxlm
namespace App\Models;

class SearchKeyword extends \Illuminate\Database\Eloquent\Model
{
	protected $table = 'search_keyword';
	protected $primaryKey = 'keyword_id';
	public $timestamps = false;
	protected $fillable = array('keyword', 'pinyin', 'is_on', 'count', 'addtime', 'pinyin_keyword');
	protected $guarded = array();

	public function getKeyword()
	{
		return $this->keyword;
	}

	public function getPinyin()
	{
		return $this->pinyin;
	}

	public function getIsOn()
	{
		return $this->is_on;
	}

	public function getCount()
	{
		return $this->count;
	}

	public function getAddtime()
	{
		return $this->addtime;
	}

	public function getPinyinKeyword()
	{
		return $this->pinyin_keyword;
	}

	public function setKeyword($value)
	{
		$this->keyword = $value;
		return $this;
	}

	public function setPinyin($value)
	{
		$this->pinyin = $value;
		return $this;
	}

	public function setIsOn($value)
	{
		$this->is_on = $value;
		return $this;
	}

	public function setCount($value)
	{
		$this->count = $value;
		return $this;
	}

	public function setAddtime($value)
	{
		$this->addtime = $value;
		return $this;
	}

	public function setPinyinKeyword($value)
	{
		$this->pinyin_keyword = $value;
		return $this;
	}
}

?>
