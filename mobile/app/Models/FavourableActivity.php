<?php
//cgxlm
namespace App\Models;

class FavourableActivity extends \Illuminate\Database\Eloquent\Model
{
	const FAT_GOODS = 0;
	const FAT_PRICE = 1;
	const FAT_DISCOUNT = 2;
	const FAR_ALL = 0;
	const FAR_CATEGORY = 1;
	const FAR_BRAND = 2;
	const FAR_GOODS = 3;

	protected $table = 'favourable_activity';
	protected $primaryKey = 'act_id';
	public $timestamps = false;
	protected $fillable = array('act_name', 'start_time', 'end_time', 'user_rank', 'act_range', 'act_range_ext', 'min_amount', 'max_amount', 'act_type', 'act_type_ext', 'activity_thumb', 'gift', 'sort_order', 'user_id', 'userFav_type', 'review_status', 'review_content');
	protected $guarded = array();

	public function getActName()
	{
		return $this->act_name;
	}

	public function getStartTime()
	{
		return $this->start_time;
	}

	public function getEndTime()
	{
		return $this->end_time;
	}

	public function getUserRank()
	{
		return $this->user_rank;
	}

	public function getActRange()
	{
		return $this->act_range;
	}

	public function getActRangeExt()
	{
		return $this->act_range_ext;
	}

	public function getMinAmount()
	{
		return $this->min_amount;
	}

	public function getMaxAmount()
	{
		return $this->max_amount;
	}

	public function getActType()
	{
		return $this->act_type;
	}

	public function getActTypeExt()
	{
		return $this->act_type_ext;
	}

	public function getActivityThumb()
	{
		return $this->activity_thumb;
	}

	public function getGift()
	{
		return $this->gift;
	}

	public function getSortOrder()
	{
		return $this->sort_order;
	}

	public function getUserId()
	{
		return $this->user_id;
	}

	public function getUserFavType()
	{
		return $this->userFav_type;
	}

	public function getReviewStatus()
	{
		return $this->review_status;
	}

	public function getReviewContent()
	{
		return $this->review_content;
	}

	public function setActName($value)
	{
		$this->act_name = $value;
		return $this;
	}

	public function setStartTime($value)
	{
		$this->start_time = $value;
		return $this;
	}

	public function setEndTime($value)
	{
		$this->end_time = $value;
		return $this;
	}

	public function setUserRank($value)
	{
		$this->user_rank = $value;
		return $this;
	}

	public function setActRange($value)
	{
		$this->act_range = $value;
		return $this;
	}

	public function setActRangeExt($value)
	{
		$this->act_range_ext = $value;
		return $this;
	}

	public function setMinAmount($value)
	{
		$this->min_amount = $value;
		return $this;
	}

	public function setMaxAmount($value)
	{
		$this->max_amount = $value;
		return $this;
	}

	public function setActType($value)
	{
		$this->act_type = $value;
		return $this;
	}

	public function setActTypeExt($value)
	{
		$this->act_type_ext = $value;
		return $this;
	}

	public function setActivityThumb($value)
	{
		$this->activity_thumb = $value;
		return $this;
	}

	public function setGift($value)
	{
		$this->gift = $value;
		return $this;
	}

	public function setSortOrder($value)
	{
		$this->sort_order = $value;
		return $this;
	}

	public function setUserId($value)
	{
		$this->user_id = $value;
		return $this;
	}

	public function setUserFavType($value)
	{
		$this->userFav_type = $value;
		return $this;
	}

	public function setReviewStatus($value)
	{
		$this->review_status = $value;
		return $this;
	}

	public function setReviewContent($value)
	{
		$this->review_content = $value;
		return $this;
	}
}

?>
