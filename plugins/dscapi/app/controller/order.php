<?php
//cgxlm
namespace app\controller;

class order extends \app\model\orderModel
{
	private $table;
	private $alias = '';
	private $order_select = array();
	private $select;
	private $seller_id = 0;
	private $order_id = 0;
	private $order_sn = 0;
	private $mobile = 0;
	private $goods_sn = '';
	private $goods_id = 0;
	private $rec_id = 0;
	private $format = 'json';
	private $page_size = 10;
	private $page = 1;
	private $wehre_val;
	private $goodsLangList;
	private $sort_by;
	private $sort_order;

	public function __construct($where = array())
	{
		$this->order($where);
		$this->wehre_val = array('seller_id' => $this->seller_id, 'order_id' => $this->order_id, 'order_sn' => $this->order_sn, 'mobile' => $this->mobile, 'rec_id' => $this->rec_id, 'goods_sn' => $this->goods_sn, 'goods_id' => $this->goods_id);
		if ((0 < $this->seller_id) || (0 < $this->mobile)) {
			$this->alias = 'o.';
		}

		$this->where = \app\model\orderModel::get_where($this->wehre_val, $this->alias);
		$this->select = \app\func\base::get_select_field($this->order_select);
	}

	public function order($where = array())
	{
		$this->seller_id = $where['seller_id'];
		$this->order_id = $where['order_id'];
		$this->order_sn = $where['order_sn'];
		$this->mobile = $where['mobile'];
		$this->rec_id = $where['rec_id'];
		$this->goods_sn = $where['goods_sn'];
		$this->goods_id = $where['goods_id'];
		$this->order_select = $where['order_select'];
		$this->format = $where['format'];
		$this->page_size = $where['page_size'];
		$this->page = $where['page'];
		$this->sort_by = $where['sort_by'];
		$this->sort_order = $where['sort_order'];
		$this->goodsLangList = \languages\orderLang::lang_order_request();
	}

	public function get_order_list($table)
	{
		$this->table = $table['order'];
		$result = \app\model\orderModel::get_select_list($this->table, $this->select, $this->where, $this->page_size, $this->page, $this->sort_by, $this->sort_order, $this->alias);
		$result = \app\model\orderModel::get_list_common_data($result, $this->page_size, $this->page, $this->goodsLangList, $this->format);
		return $result;
	}

	public function get_order_info($table)
	{
		$this->table = $table['order'];
		$result = \app\model\orderModel::get_select_info($this->table, $this->select, $this->where, $this->alias);

		if (strlen($this->where) != 1) {
			$result = \app\model\orderModel::get_info_common_data_fs($result, $this->goodsLangList, $this->format);
		}
		else {
			$result = \app\model\orderModel::get_info_common_data_f($this->goodsLangList, $this->format);
		}

		return $result;
	}

	public function get_order_insert($table)
	{
		$this->table = $table['order'];
		return \app\model\orderModel::get_insert($this->table, $this->order_select, $this->format);
	}

	public function get_order_update($table)
	{
		$this->table = $table['order'];
		return \app\model\orderModel::get_update($this->table, $this->order_select, $this->where, $this->format);
	}

	public function get_order_delete($table)
	{
		$this->table = $table['order'];
		return \app\model\orderModel::get_delete($this->table, $this->where, $this->format);
	}

	public function get_order_goods_list($table)
	{
		$this->table = $table['goods'];
		$result = \app\model\orderModel::get_select_list($this->table, $this->select, $this->where, $this->page_size, $this->page, $this->sort_by, $this->sort_order);
		$result = \app\model\orderModel::get_list_common_data($result, $this->page_size, $this->page, $this->goodsLangList, $this->format);
		return $result;
	}

	public function get_order_goods_info($table)
	{
		$this->table = $table['goods'];
		$result = \app\model\orderModel::get_select_info($this->table, $this->select, $this->where);

		if (strlen($this->where) != 1) {
			$result = \app\model\orderModel::get_info_common_data_fs($result, $this->goodsLangList, $this->format);
		}
		else {
			$result = \app\model\orderModel::get_info_common_data_f($this->goodsLangList, $this->format);
		}

		return $result;
	}

	public function get_order_goods_insert($table)
	{
		$this->table = $table['goods'];
		return \app\model\orderModel::get_insert($this->table, $this->order_select, $this->format);
	}

	public function get_order_goods_update($table)
	{
		$this->table = $table['goods'];
		return \app\model\orderModel::get_update($this->table, $this->order_select, $this->where, $this->format);
	}

	public function get_order_goods_delete($table)
	{
		$this->table = $table['goods'];
		return \app\model\orderModel::get_delete($this->table, $this->where, $this->format);
	}
}

?>
