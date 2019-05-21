<?php

define('IN_ECS', true);
require dirname(__FILE__) . '/includes/init.php';
require ROOT_PATH . '/includes/lib_area.php';
require ROOT_PATH . 'includes/lib_publicfunc.php';
require ROOT_PATH . 'includes/lib_wholesale.php';

//分区类型
$smarty->assign('act_type', 'wholesale');

if (empty($_REQUEST['act']))
{
	$_REQUEST['act'] = 'index';
}
if ($_REQUEST['act'] == 'index')
{
	assign_template();
	$position = assign_ur_here(0, '现货专区');
	$smarty->assign('page_title', $position['title']);
	$smarty->assign('ur_here', $position['ur_here']);
	$smarty->assign('index', $_REQUEST['act']);
	if (defined('THEME_EXTENSION'))
	{
		$business_cate = get_wholesale_cat();//get_business_category_tree
		$smarty->assign('business_cate', $business_cate);
	}
	$wholesale_limit = get_wholesale_limit();
	$smarty->assign('wholesale_limit', $wholesale_limit);
	$smarty->assign('helps', get_shop_help());
	$smarty->assign('get_wholesale_cat', get_wholesale_cat());
	$res = get_purchase_list();
	$purchase_list = $res['purchase_list'];
	$smarty->assign('purchase', $purchase_list);
	if (defined('THEME_EXTENSION'))
	{
		for ($i = 1; $i <= $_CFG['auction_ad']; $i++)
		{
			$wholesale_ad .= '\'wholesale_ad' . $i . ',';
			$wholesale_cat_ad .= '\'wholesale_cat_ad' . $i . ',';
            $wholesale_recommend .= "'wholesale_recommend".$i."',";
            $wholesale_brand .= "'wholesale_brand" . $i ."',";

            //推荐楼层
            $wholesale_recommend_floor .= "'floor_recommend_wholesale" . $i .",";
            //品牌楼层
            $wholesale_brand_floor .= "'floor_brand_wholesale" . $i .",";
		}

        //推荐楼层
        $smarty->assign('wholesale_recommend_floor', $wholesale_recommend_floor);
        //品牌楼层
        $smarty->assign('wholesale_brand_floor', $wholesale_brand_floor);

		$smarty->assign('wholesale_ad', $wholesale_ad);
		$smarty->assign('wholesale_cat_ad', $wholesale_cat_ad);
	}

    //获取推荐
    $recommends = get_adv_child($wholesale_recommend);
    $smarty->assign('recommends', $recommends);

    //获取品牌
    $brands = get_adv_child($wholesale_brand);
    $smarty->assign('brands', $brands);

    //分区类型
    $smarty->assign('act_type', 'wholesale');

    //获取一级分类
    $categorys = get_category_list();
    $smarty->assign('categorys', $categorys);

	assign_dynamic('wholesale');
	$smarty->display('wholesale_list.dwt');
}
else if ($_REQUEST['act'] == 'buy')
{
	if (defined('THEME_EXTENSION'))
	{
		$business_cate = wholesale_cat_list();
		$smarty->assign('business_cate', $business_cate);
	}
	$position = assign_ur_here();
	$smarty->assign('page_title', $position['title']);
	$smarty->assign('ur_here', $position['ur_here']);
	$smarty->assign('helps', get_shop_help());
	$smarty->assign('buy', $_REQUEST['act']);
	assign_template('wholesale');
	$smarty->display('wholesale_buy.dwt');
}
else if ($_REQUEST['act'] == 'price_list')
{
	$data = $_LANG['goods_name'] . "\t" . $_LANG['goods_attr'] . "\t" . $_LANG['number'] . "\t" . $_LANG['ws_price'] . "\t\n";
	$sql = 'SELECT * FROM ' . $ecs->table('wholesale') . 'WHERE enabled = 1 AND review_status = 3 AND CONCAT(\',\', rank_ids, \',\') LIKE \'' . '%,' . $_SESSION['user_rank'] . ',%' . '\'';
	$res = $db->query($sql);
	while ($row = $db->fetchRow($res))
	{
		$price_list = unserialize($row['prices']);
		foreach ($price_list as $attr_price )
		{
			if ($attr_price['attr'])
			{
				$sql = 'SELECT attr_value FROM ' . $ecs->table('goods_attr') . ' WHERE goods_attr_id ' . db_create_in($attr_price['attr']);
				$goods_attr = join(',', $db->getCol($sql));
			}
			else
			{
				$goods_attr = '';
			}
			foreach ($attr_price['qp_list'] as $qp )
			{
				$data .= $row['goods_name'] . "\t" . $goods_attr . "\t" . $qp['quantity'] . "\t" . $qp['price'] . "\t\n";
			}
		}
	}
	header('Content-type: application/vnd.ms-excel; charset=utf-8');
	header('Content-Disposition: attachment; filename=price_list.xls');
	if (EC_CHARSET == 'utf-8')
	{
		echo ecs_iconv('UTF8', 'GB2312', $data);
	}
	else
	{
		echo $data;
	}
}
else if ($_REQUEST['act'] == 'add_to_cart')
{
	$act_id = intval($_POST['act_id']);
	$goods_number = $_POST['goods_number'][$act_id];
	$attr_id = (isset($_POST['attr_id']) ? $_POST['attr_id'] : array());
	if (isset($attr_id[$act_id]))
	{
		$goods_attr = $attr_id[$act_id];
	}
	if (empty($goods_number) || (is_array($goods_number) && (array_sum($goods_number) <= 0)))
	{
		show_message($_LANG['ws_invalid_goods_number']);
	}
	$goods_list = array();
	if (is_array($goods_number))
	{
		foreach ($goods_number as $key => $value )
		{
			if (!($value))
			{
				unset($goods_number[$key]);
				unset($goods_attr[$key]);
				continue;
			}
			$goods_list[] = array('number' => $goods_number[$key], 'goods_attr' => $goods_attr[$key]);
		}
	}
	else
	{
		$goods_list[0] = array('number' => $goods_number, 'goods_attr' => '');
	}
	$wholesale = wholesale_info($act_id);
	if (isset($_SESSION['wholesale_goods']))
	{
		foreach ($_SESSION['wholesale_goods'] as $goods )
		{
			if ($goods['goods_id'] == $wholesale['goods_id'])
			{
				if (empty($goods_attr))
				{
					show_message($_LANG['ws_goods_attr_exists']);
				}
				else if (in_array($goods['goods_attr_id'], $goods_attr))
				{
					show_message($_LANG['ws_goods_attr_exists']);
				}
			}
		}
	}
	$attr_matching = false;
	foreach ($wholesale['price_list'] as $attr_price )
	{
		if (empty($attr_price['attr']))
		{
			$attr_matching = true;
			$goods_list[0]['qp_list'] = $attr_price['qp_list'];
			break;
		}
		else if (($key = is_attr_matching($goods_list, $attr_price['attr'])) !== false)
		{
			$attr_matching = true;
			$goods_list[$key]['qp_list'] = $attr_price['qp_list'];
		}
	}
	if (!($attr_matching))
	{
		show_message($_LANG['ws_attr_not_matching']);
	}
	foreach ($goods_list as $goods_key => $goods )
	{
		if ($goods['number'] < $goods['qp_list'][0]['quantity'])
		{
			show_message($_LANG['ws_goods_number_not_enough']);
		}
		else
		{
			$goods_price = 0;
			foreach ($goods['qp_list'] as $qp )
			{
				if ($qp['quantity'] <= $goods['number'])
				{
					$goods_list[$goods_key]['goods_price'] = $qp['price'];
				}
				else
				{
					break;
				}
			}
		}
	}
	foreach ($goods_list as $goods_key => $goods )
	{
		$goods_attr_name = '';
		if (!(empty($goods['goods_attr'])))
		{
			foreach ($goods['goods_attr'] as $key => $attr )
			{
				$attr['attr_name'] = htmlspecialchars($attr['attr_name']);
				$goods['goods_attr'][$key]['attr_name'] = $attr['attr_name'];
				$attr['attr_val'] = htmlspecialchars($attr['attr_val']);
				$goods_attr_name .= $attr['attr_name'] . '��' . $attr['attr_val'] . '&nbsp;';
			}
		}
		$total = $goods['number'] * $goods['goods_price'];
		$goods_img = $db->getOne('SELECT goods_thumb FROM ' . $ecs->table('goods') . ' WHERE goods_id = \'' . $wholesale['goods_id'] . '\'');
		$_SESSION['wholesale_goods'][] = array('goods_id' => $wholesale['goods_id'], 'goods_name' => $wholesale['goods_name'], 'goods_attr_id' => $goods['goods_attr'], 'goods_attr' => $goods_attr_name, 'goods_number' => $goods['number'], 'goods_price' => $goods['goods_price'], 'subtotal' => $total, 'formated_goods_price' => price_format($goods['goods_price'], false), 'formated_subtotal' => price_format($total, false), 'goods_url' => build_uri('goods', array('gid' => $wholesale['goods_id']), $wholesale['goods_name']), 'goods_img' => get_image_path($wholesale['goods_id'], $goods_img, true));
	}
	unset($goods_attr);
	unset($attr_id);
	unset($goods_list);
	unset($wholesale);
	unset($goods_attr_name);
	ecs_header('Location: ./wholesale.php' . "\n");
	exit();
}
else if ($_REQUEST['act'] == 'drop_goods')
{
	$key = intval($_REQUEST['key']);
	if (isset($_SESSION['wholesale_goods'][$key]))
	{
		unset($_SESSION['wholesale_goods'][$key]);
	}
	ecs_header("Location: ./wholesale.php" . "\n");
	exit();
}
else if ($_REQUEST['act'] == 'submit_order')
{
	include_once ROOT_PATH . 'includes/lib_order.php';
	if (count($_SESSION['wholesale_goods']) == 0)
	{
		show_message($_LANG['no_goods_in_cart']);
	}
	if (empty($_POST['remark']))
	{
		show_message($_LANG['ws_remark']);
	}
	$goods_amount = 0;
	foreach ($_SESSION['wholesale_goods'] as $goods )
	{
		$goods_amount += $goods['subtotal'];
	}
	$order = array('postscript' => htmlspecialchars($_POST['remark']), 'user_id' => $_SESSION['user_id'], 'add_time' => gmtime(), 'order_status' => OS_UNCONFIRMED, 'shipping_status' => SS_UNSHIPPED, 'pay_status' => PS_UNPAYED, 'goods_amount' => $goods_amount, 'order_amount' => $goods_amount);
	$error_no = 0;
	do
	{
		$order['order_sn'] = get_order_sn();
		$GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_info'), $order, 'INSERT');
		$error_no = $GLOBALS['db']->errno();
		if ((0 < $error_no) && ($error_no != 1062))
		{
			exit($GLOBALS['db']->errorMsg());
		}
	}
	while ($error_no == 1062);
	$new_order_id = $db->insert_id();
	$order['order_id'] = $new_order_id;
	foreach ($_SESSION['wholesale_goods'] as $goods )
	{
		$product_id = 0;
		if (!(empty($goods['goods_attr_id'])))
		{
			$goods_attr_id = array();
			foreach ($goods['goods_attr_id'] as $value )
			{
				$goods_attr_id[$value['attr_id']] = $value['attr_val_id'];
			}
			ksort($goods_attr_id);
			$goods_attr = implode('|', $goods_attr_id);
			$sql = 'SELECT product_id FROM ' . $ecs->table('products') . ' WHERE goods_attr = \'' . $goods_attr . '\' AND goods_id = \'' . $goods['goods_id'] . '\'';
			$product_id = $db->getOne($sql);
		}
		$sql = 'INSERT INTO ' . $ecs->table('order_goods') . '( ' . 'order_id, goods_id, goods_name, goods_sn, product_id, goods_number, market_price, ' . 'goods_price, goods_attr, is_real, extension_code, parent_id, is_gift, ru_id) ' . ' SELECT \'' . $new_order_id . '\', goods_id, goods_name, goods_sn, \'' . $product_id . '\',\'' . $goods['goods_number'] . '\', market_price, ' . '\'' . $goods['goods_price'] . '\', \'' . $goods['goods_attr'] . '\', is_real, extension_code, 0, 0 , user_id ' . ' FROM ' . $ecs->table('goods') . ' WHERE goods_id = \'' . $goods['goods_id'] . '\'';
		$db->query($sql);
	}
	if ($_CFG['service_email'] != '')
	{
		$tpl = get_mail_template('remind_of_new_order');
		$smarty->assign('order', $order);
		$smarty->assign('shop_name', $_CFG['shop_name']);
		$smarty->assign('send_date', local_date($_CFG['time_format'], gmtime()));
		$content = $smarty->fetch('str:' . $tpl['template_content']);
		send_mail($_CFG['shop_name'], $_CFG['service_email'], $tpl['template_subject'], $content, $tpl['is_html']);
	}
	if (($_CFG['sms_order_placed'] == '1') && ($_CFG['sms_shop_mobile'] != ''))
	{
		$sql = 'SELECT user_name, mobile_phone FROM ' . $GLOBALS['ecs']->table('users') . ' WHERE user_id = \'' . $_SESSION['user_id'] . '\' LIMIT 1';
		$info = $GLOBALS['db']->getRow($sql);
		$smsParams = array('shop_name' => '', 'user_name' => $_CFG['shop_name'], 'order_msg' => $msg ? $msg : '', 'consignee' => $info['user_name'], 'order_mobile' => $info['mobile_phone'], 'mobile_phone' => $_CFG['sms_shop_mobile']);
		if ($GLOBALS['_CFG']['sms_type'] == 0)
		{
			huyi_sms($smsParams, 'sms_order_placed');
		}
		else if (1 <= $GLOBALS['_CFG']['sms_type'])
		{
			$result = sms_ali($smsParams, 'sms_order_placed');
			$resp = $GLOBALS['ecs']->ali_yu($result);
		}
	}
	unset($_SESSION['wholesale_goods']);
	show_message(sprintf($_LANG['ws_order_submitted'], $order['order_sn']), $_LANG['ws_return_wholesale'], 'wholesale.php');
}
function is_attr_matching(&$goods_list, $reference)
{
	foreach ($goods_list as $key => $goods )
	{
		if (count($goods['goods_attr']) != count($reference))
		{
			break;
		}
		$is_check = true;
		if (is_array($goods['goods_attr']))
		{
			foreach ($goods['goods_attr'] as $attr )
			{
				if (!(array_key_exists($attr['attr_id'], $reference) && ($attr['attr_val_id'] == $reference[$attr['attr_id']])))
				{
					$is_check = false;
					break;
				}
			}
		}
		if ($is_check)
		{
			return $key;
			break;
		}
	}
	return false;
}
function get_wholesale_limit()
{
	$now = gmtime();
	$sql = 'SELECT w.*, g.goods_name, g.goods_thumb, g.goods_img, MIN(wvp.volume_number) AS volume_number, wvp.volume_price FROM ' . $GLOBALS['ecs']->table('wholesale') . ' AS w' . ' LEFT JOIN ' . $GLOBALS['ecs']->table('goods') . ' AS g ON w.goods_id = g.goods_id ' . ' LEFT JOIN ' . $GLOBALS['ecs']->table('wholesale_volume_price') . ' AS wvp ON wvp.goods_id = g.goods_id ' . ' WHERE w.enabled = 1 AND w.review_status = 3 AND w.is_promote = 1 AND \'' . $now . '\' BETWEEN w.start_time AND w.end_time GROUP BY goods_id';
	$res = $GLOBALS['db']->getAll($sql);
	foreach ($res as $key => $row )
	{
		$res[$key]['formated_end_date'] = local_date($GLOBALS['_CFG']['date_format'], $row['end_time']);
		$res[$key]['small_time'] = $row['end_time'] - $now;
		$res[$key]['goods_name'] = $row['goods_name'];
		$res[$key]['goods_price'] = $row['goods_price'];
		$res[$key]['moq'] = $row['moq'];
		$res[$key]['volume_number'] = $row['volume_number'];
		$res[$key]['volume_price'] = $row['volume_price'];
		$res[$key]['thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
		$res[$key]['goods_img'] = get_image_path($row['goods_id'], $row['goods_img']);
		$res[$key]['url'] = build_uri('wholesale_goods', array('aid' => $row['act_id']), $row['goods_name']);

		if ($GLOBALS['_CFG']['open_oss'] == 1)
		{
			$bucket_info = get_bucket_info();
			$res[$key]['goods_thumb'] = $bucket_info['endpoint'] . $row['goods_thumb'];
		}

	}
	return $res;
}
?>
