<?php
//cgxlm
$seller_id = (isset($_REQUEST['seller_id']) ? $base->get_intval($_REQUEST['seller_id']) : -1);
$order_id = (isset($_REQUEST['order_id']) ? $base->get_intval($_REQUEST['order_id']) : -1);
$order_sn = (isset($_REQUEST['order_sn']) ? $base->get_addslashes($_REQUEST['order_sn']) : -1);
$mobile = (isset($_REQUEST['mobile']) ? $base->get_addslashes($_REQUEST['mobile']) : -1);
$rec_id = (isset($_REQUEST['rec_id']) ? $base->get_intval($_REQUEST['rec_id']) : -1);
$goods_id = (isset($_REQUEST['goods_id']) ? $base->get_intval($_REQUEST['goods_id']) : -1);
$goods_sn = (isset($_REQUEST['goods_sn']) ? $base->get_addslashes($_REQUEST['goods_sn']) : -1);
$val = array('seller_id' => $seller_id, 'order_id' => $order_id, 'order_sn' => $order_sn, 'mobile' => $mobile, 'rec_id' => $rec_id, 'goods_id' => $goods_id, 'goods_sn' => $goods_sn, 'order_select' => $data, 'page_size' => $page_size, 'page' => $page, 'sort_by' => $sort_by, 'sort_order' => $sort_order, 'format' => $format);
$order = new \app\controller\order($val);

switch ($method) {
case 'dsc.order.list.get':
	$table = array('order' => 'order_info');
	$result = $order->get_order_list($table);
	exit($result);
	break;

case 'dsc.order.info.get':
	$table = array('order' => 'order_info');
	$result = $order->get_order_info($table);
	exit($result);
	break;

case 'dsc.order.insert.post':
	$table = array('order' => 'order_info');
	$result = $order->get_order_insert($table);
	exit($result);
	break;

case 'dsc.order.update.post':
	$table = array('order' => 'order_info');
	$result = $order->get_order_update($table);
	exit($result);
	break;

case 'dsc.order.del.post':
	$table = array('order' => 'order_info');
	$result = $order->get_order_delete($table);
	exit($result);
	break;

case 'dsc.order.goods.list.get':
	$table = array('goods' => 'order_goods');
	$result = $order->get_order_goods_list($table);
	exit($result);
	break;

case 'dsc.order.goods.info.get':
	$table = array('goods' => 'order_goods');
	$result = $order->get_order_goods_info($table);
	exit($result);
	break;

case 'dsc.order.goods.insert.post':
	$table = array('goods' => 'order_goods');
	$result = $order->get_order_goods_insert($table);
	exit($result);
	break;

case 'dsc.order.goods.update.post':
	$table = array('goods' => 'order_goods');
	$result = $order->get_order_goods_update($table);
	exit($result);
	break;

case 'dsc.order.goods.del.post':
	$table = array('goods' => 'order_goods');
	$result = $order->get_order_goods_delete($table);
	exit($result);
	break;

default:
	echo '非法接口连接';
	break;
}

?>
