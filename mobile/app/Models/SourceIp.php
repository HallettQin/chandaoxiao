<?php
//cgxlm
namespace App\Models;

class SourceIp extends \Illuminate\Database\Eloquent\Model
{
	protected $table = 'source_ip';
	protected $primaryKey = 'ipid';
	public $timestamps = false;
	protected $fillable = array('storeid', 'ipdata', 'iptime');
	protected $guarded = array();

	public function getStoreid()
	{
		return $this->storeid;
	}

	public function getIpdata()
	{
		return $this->ipdata;
	}

	public function getIptime()
	{
		return $this->iptime;
	}

	public function setStoreid($value)
	{
		$this->storeid = $value;
		return $this;
	}

	public function setIpdata($value)
	{
		$this->ipdata = $value;
		return $this;
	}

	public function setIptime($value)
	{
		$this->iptime = $value;
		return $this;
	}
}

?>
