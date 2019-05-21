<?php
//cgxlm
namespace app\model;

abstract class orderModel extends \app\func\common
{
	private $alias;

	public function __construct()
	{
	}

	public function get_where($val = array(), $alias = '')
	{
		$where = 1;
		$conditions = '';
		if ((0 < $val['seller_id']) || (0 < $val['mobile'])) {
			$conditions .= ' AND (SELECT count(*) FROM ' . $GLOBALS['ecs']->table('order_info') . ' AS oi2 WHERE oi2.main_order_id = ' . $alias . 'order_id) = 0';
		}

		if ($val['seller_id'] != -1) {
			$conditions .= ' AND (SELECT og.ru_id FROM ' . $GLOBALS['ecs']->table('order_goods') . ' AS og WHERE ' . $alias . 'order_id = og.order_id LIMIT 1)' . \app\func\base::db_create_in($val['seller_id']);
			$where .= \app\func\base::get_where(0, '', $conditions);
		}

		$where .= \app\func\base::get_where($val['order_id'], $alias . 'order_id');
		$where .= \app\func\base::get_where($val['order_sn'], $alias . 'order_sn');
		$where .= \app\func\base::get_where($val['mobile'], $alias . 'mobile');
		$where .= \app\func\base::get_where($val['rec_id'], $alias . 'rec_id');
		$where .= \app\func\base::get_where($val['goods_sn'], $alias . 'goods_sn');
		$where .= \app\func\base::get_where($val['goods_id'], $alias . 'goods_id');
		return $where;
	}

	public function get_select_list($table, $select, $where, $page_size, $page, $sort_by, $sort_order, $alias = '')
	{
		$table_alias = '';

		if (!empty($alias)) {
			$table_alias = ' AS ' . str_replace('.', '', $alias);
			$sort_by = $alias . $sort_by;
		}

		$sql = 'SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table($table) . $table_alias . ' WHERE ' . $where;
		$result['record_count'] = $GLOBALS['db']->getOne($sql);

		if ($sort_by) {
			$where .= ' ORDER BY ' . $sort_by . ' ' . $sort_order . ' ';
		}

		$where .= ' LIMIT ' . (($page - 1) * $page_size) . ',' . $page_size;
		$sql = 'SELECT ' . $select . ' FROM ' . $GLOBALS['ecs']->table($table) . $table_alias . ' WHERE ' . $where;
		$result['list'] = $GLOBALS['db']->getAll($sql);
		return $result;
	}

	public function get_select_info($table, $select, $where, $alias = '')
	{
		$table_alias = '';

		if (!empty($alias)) {
			$table_alias = ' AS ' . str_replace('.', '', $alias);
		}

		$sql = 'SELECT ' . $select . ' FROM ' . $GLOBALS['ecs']->table($table) . $table_alias . ' WHERE ' . $where . ' LIMIT 1';
		$result = $GLOBALS['db']->getRow($sql);
		return $result;
	}

	public function get_insert($table, $select, $format)
	{
		$orderLang = \languages\orderLang::lang_order_insert();
		$GLOBALS['db']->autoExecute($GLOBALS['ecs']->table($table), $select, 'INSERT');
		$id = $GLOBALS['db']->insert_id();
		$common_data = array('result' => empty($id) ? 'failure' : 'success', 'msg' => empty($id) ? $orderLang['msg_failure']['failure'] : $orderLang['msg_success']['success'], 'error' => empty($id) ? $orderLang['msg_failure']['error'] : $orderLang['msg_success']['error'], 'format' => $format);
		\app\func\common::common($common_data);
		return \app\func\common::data_back();
	}

	public function get_update($table, $select, $where, $format)
	{
		$orderLang = \languages\orderLang::lang_order_update();

		if (strlen($where) != 1) {
			$info = $this->get_select_info($table, '*', $where);

			if (!$info) {
				$common_data = array('result' => 'failure', 'msg' => $orderLang['null_failure']['failure'], 'error' => $orderLang['null_failure']['error'], 'format' => $format);
			}
			else {
				$GLOBALS['db']->autoExecute($GLOBALS['ecs']->table($table), $select, 'UPDATE', $where);
				$common_data = array('result' => empty($select) ? 'failure' : 'success', 'msg' => empty($select) ? $orderLang['msg_failure']['failure'] : $orderLang['msg_success']['success'], 'error' => empty($select) ? $orderLang['msg_failure']['error'] : $orderLang['msg_success']['error'], 'format' => $format);
			}
		}
		else {
			$common_data = array('result' => 'failure', 'msg' => $orderLang['where_failure']['failure'], 'error' => $orderLang['where_failure']['error'], 'format' => $format);
		}

		\app\func\common::common($common_data);
		return \app\func\common::data_back();
	}

	public function get_delete($table, $where, $format)
	{
		$orderLang = \languages\orderLang::lang_order_delete();

		if (strlen($where) != 1) {
			$sql = 'DELETE FROM ' . $GLOBALS['ecs']->table($table) . ' WHERE ' . $where;
			$GLOBALS['db']->query($sql);
			$common_data = array('result' => 'success', 'msg' => $orderLang['msg_success']['success'], 'error' => $orderLang['msg_success']['error'], 'format' => $format);
		}
		else {
			$common_data = array('result' => 'failure', 'msg' => $orderLang['where_failure']['failure'], 'error' => $orderLang['where_failure']['error'], 'format' => $format);
		}

		\app\func\common::common($common_data);
		return \app\func\common::data_back();
	}

	public function get_list_common_data($result, $page_size, $page, $orderLang, $format)
	{
		$common_data = array('page_size' => $page_size, 'page' => $page, 'result' => empty($result) ? 'failure' : 'success', 'msg' => empty($result) ? $orderLang['msg_failure']['failure'] : $orderLang['msg_success']['success'], 'error' => empty($result) ? $orderLang['msg_failure']['error'] : $orderLang['msg_success']['error'], 'format' => $format);
		\app\func\common::common($common_data);
		$result = \app\func\common::data_back($result, 1);
		return $result;
	}

	public function get_info_common_data_fs($result, $orderLang, $format)
	{
		$common_data = array('result' => empty($result) ? 'failure' : 'success', 'msg' => empty($result) ? $orderLang['msg_failure']['failure'] : $orderLang['msg_success']['success'], 'error' => empty($result) ? $orderLang['msg_failure']['error'] : $orderLang['msg_success']['error'], 'format' => $format);
		\app\func\common::common($common_data);
		$result = \app\func\common::data_back($result);
		return $result;
	}

	public function get_info_common_data_f($orderLang, $format)
	{
		$result = array();
		$common_data = array('result' => 'failure', 'msg' => $orderLang['where_failure']['failure'], 'error' => $orderLang['where_failure']['error'], 'format' => $format);
		\app\func\common::common($common_data);
		$result = \app\func\common::data_back($result);
		return $result;
	}
}

?>
