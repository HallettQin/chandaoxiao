<?php
//DATA:2017-09-29
function replace_img_view($data)
{
	$data = strstr($data, 'data');
	return $data;
}

function category_get_goods($children, $intro = '', $brand, $user_id, $sort = '', $warehouse_id = 0, $area_id = 0, $limit = 10)
{
	$display = (isset($_COOKIE['ECS']['display']) ? $_COOKIE['ECS']['display'] : 'grid');
	$where = 'g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 ';
	$get_extension_goods = get_extension_goods($children);
	if (!empty($get_extension_goods) && !empty($children)) {
		$where .= ' AND (' . $children . ' OR ' . $get_extension_goods . ')';
	}

	if ($user_id) {
		$where .= ' AND g.user_id = ' . $user_id;
	}

	if ($intro) {
		switch ($intro) {
		case 'best':
			$where .= ((0 < $user_id) && (0 < $goodsnum) ? ' AND  g.store_best = \'1\' ' : (0 < $user_id ? ' AND 1 ' : ' AND is_best = \'1\' '));
			break;

		case 'new':
			$where .= (0 < $user_id ? ' AND  g.store_new = \'1\' ' : ' AND  g.is_new = \'1\' ');
			break;

		case 'hot':
			$where .= (0 < $user_id ? ' AND  g.store_hot = \'1\' ' : ' AND  g.is_hot = \'1\' ');
			break;

		case 'promotion':
			$time = gmtime();
			$where .= ' AND g.promote_price > 0 AND g.promote_start_date <= \'' . $time . '\' AND g.promote_end_date >= \'' . $time . '\' ';
			break;

		default:
			$where .= '';
		}
	}

	$leftJoin = '';

	if ($brand) {
		$where_brand = ' AND b.brand_id IN(' . $brand . ')';
		$sel_msb = '(g.brand_id IN(SELECT msb.bid FROM ' . $GLOBALS['ecs']->table('brand') . ' AS b, ' . $GLOBALS['ecs']->table('link_brand') . ' AS lb, ' . $GLOBALS['ecs']->table('merchants_shop_brand') . ' AS msb' . ' WHERE b.is_show = 1 AND b.brand_id = lb.brand_id AND lb.bid = msb.bid AND msb.is_show = 1 AND msb.audit_status = 1 ' . $where_brand . ') AND g.user_id > 0)';
		$sel_brand = '(g.brand_id IN(SELECT b.brand_id FROM ' . $GLOBALS['ecs']->table('brand') . ' AS b' . ' WHERE b.is_show = 1 ' . $where_brand . ') AND g.user_id = 0)';
		$where .= ' AND ( ' . $sel_brand . ' OR ' . $sel_msb . ')';
	}

	$shop_price = 'wg.warehouse_price, wg.warehouse_promote_price, wag.region_price, wag.region_promote_price, g.model_price, g.model_attr, ';
	$leftJoin .= ' left join ' . $GLOBALS['ecs']->table('warehouse_goods') . ' as wg on g.goods_id = wg.goods_id and wg.region_id = \'' . $warehouse_id . '\' ';
	$leftJoin .= ' left join ' . $GLOBALS['ecs']->table('warehouse_area_goods') . ' as wag on g.goods_id = wag.goods_id and wag.region_id = \'' . $area_id . '\' ';
	$leftJoin .= ' LEFT JOIN ' . $GLOBALS['ecs']->table('merchants_shop_information') . ' as msi on msi.user_id = g.user_id ';

	if ($GLOBALS['_CFG']['open_area_goods'] == 1) {
		$leftJoin .= ' left join ' . $GLOBALS['ecs']->table('link_area_goods') . ' as lag on g.goods_id = lag.goods_id ';
		$where .= ' AND lag.region_id = \'' . $area_id . '\' ';
	}

	if ($sort == '') {
		$sort = 'g.last_update';
	}

	if ($GLOBALS['_CFG']['review_goods'] == 1) {
		$where .= ' AND g.review_status > 2 ';
	}

	$sql = 'SELECT g.goods_id, g.user_id, g.goods_name, ' . $shop_price . ' g.goods_name_style, g.comments_number,g.sales_volume,g.market_price, g.is_new, g.is_best, g.is_hot, ' . ' IF(g.model_price < 1, g.goods_number, IF(g.model_price < 2, wg.region_number, wag.region_number)) AS goods_number, ' . ' IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, g.model_price, ' . 'IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * \'' . $_SESSION['discount'] . '\'), g.shop_price * \'' . $_SESSION['discount'] . '\')  AS shop_price, ' . 'IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, g.goods_type, ' . 'g.promote_start_date, g.promote_end_date, g.is_promote, g.goods_brief, g.goods_thumb , g.goods_img, msi.self_run ' . 'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' . $leftJoin . 'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' . 'ON mp.goods_id = g.goods_id AND mp.user_rank = \'' . $_SESSION['user_rank'] . '\' ' . 'WHERE ' . $where . '  ORDER BY ' . $sort . ' LIMIT ' . $limit;
	$res = $GLOBALS['db']->query($sql);
	$arr = array();

	foreach ($res as $row) {
		if (0 < $row['promote_price']) {
			$promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
		}
		else {
			$promote_price = 0;
		}

		$price_other = array('market_price' => $row['market_price'], 'org_price' => $row['org_price'], 'shop_price' => $row['shop_price'], 'promote_price' => $promote_price);
		$price_info = get_goods_one_attr_price($row['goods_id'], $warehouse_id, $area_id, $price_other);
		$row = (!empty($row) ? array_merge($row, $price_info) : $row);
		$promote_price = $row['promote_price'];
		$arr[$row['goods_id']]['org_price'] = $row['org_price'];
		$arr[$row['goods_id']]['model_price'] = $row['model_price'];
		$arr[$row['goods_id']]['warehouse_price'] = $row['warehouse_price'];
		$arr[$row['goods_id']]['warehouse_promote_price'] = $row['warehouse_promote_price'];
		$arr[$row['goods_id']]['region_price'] = $row['region_price'];
		$arr[$row['goods_id']]['region_promote_price'] = $row['region_promote_price'];
		$watermark_img = '';

		if ($promote_price != 0) {
			$watermark_img = 'watermark_promote_small';
		}
		else if ($row['is_new'] != 0) {
			$watermark_img = 'watermark_new_small';
		}
		else if ($row['is_best'] != 0) {
			$watermark_img = 'watermark_best_small';
		}
		else if ($row['is_hot'] != 0) {
			$watermark_img = 'watermark_hot_small';
		}

		if ($watermark_img != '') {
			$arr[$row['goods_id']]['watermark_img'] = $watermark_img;
		}

		$arr[$row['goods_id']]['goods_id'] = $row['goods_id'];

		if ($display == 'grid') {
			$arr[$row['goods_id']]['goods_name'] = 0 < $GLOBALS['_CFG']['goods_name_length'] ? sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
		}
		else {
			$arr[$row['goods_id']]['goods_name'] = $row['goods_name'];
		}

		$arr[$row['goods_id']]['title'] = $row['goods_name'];
		$arr[$row['goods_id']]['goods_brief'] = $row['goods_brief'];
		$arr[$row['goods_id']]['sale'] = $row['sales_volume'];
		$arr[$row['goods_id']]['comments_number'] = $row['comments_number'];
		$arr[$row['goods_id']]['is_promote'] = $row['is_promote'];
		$arr[$row['goods_id']]['promote_start_date'] = $row['promote_start_date'];
		$arr[$row['goods_id']]['promote_end_date'] = $row['promote_end_date'];

		if (0 < $row['market_price']) {
			$discount_arr = get_discount($row['goods_id']);
		}

		$arr[$row['goods_id']]['zhekou'] = $discount_arr['discount'];
		$arr[$row['goods_id']]['jiesheng'] = $discount_arr['jiesheng'];
		$arr[$row['goods_id']]['goods_style_name'] = add_style($row['goods_name'], $row['goods_name_style']);
		$goods_id = $row['goods_id'];
		$count = $GLOBALS['db']->getOne('SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table('comment') . ' where id_value =\'' . $goods_id . '\' AND status = 1 AND parent_id = 0');
		$arr[$row['goods_id']]['review_count'] = $count;
		$arr[$row['goods_id']]['marketPrice'] = price_format($row['market_price']);
		$arr[$row['goods_id']]['shop_price'] = price_format($row['shop_price']);
		$arr[$row['goods_id']]['type'] = $row['goods_type'];
		$arr[$row['goods_id']]['price'] = 0 < $promote_price ? price_format(min($promote_price, $row['shop_price'])) : '';
		$arr[$row['goods_id']]['img'] = get_wechat_image_path($row['goods_thumb']);
		$arr[$row['goods_id']]['goods_img'] = get_wechat_image_path($row['goods_img']);
		$arr[$row['goods_id']]['url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
		$arr[$row['goods_id']]['is_hot'] = $row['is_hot'];
		$arr[$row['goods_id']]['is_best'] = $row['is_best'];
		$arr[$row['goods_id']]['is_new'] = $row['is_new'];
		$arr[$row['goods_id']]['self_run'] = $row['self_run'];
		$arr[$row['goods_id']]['is_shipping'] = $row['is_shipping'];

		if ($row['model_attr'] == 1) {
			$table_products = 'products_warehouse';
			$type_files = ' and warehouse_id = \'' . $warehouse_id . '\'';
		}
		else if ($row['model_attr'] == 2) {
			$table_products = 'products_area';
			$type_files = ' and area_id = \'' . $area_id . '\'';
		}
		else {
			$table_products = 'products';
			$type_files = '';
		}

		$sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table($table_products) . ' WHERE goods_id = \'' . $row['goods_id'] . '\'' . $type_files . ' LIMIT 0, 1';
		$arr[$row['goods_id']]['prod'] = $GLOBALS['db']->getRow($sql);

		if (empty($prod)) {
			$arr[$row['goods_id']]['prod'] = 1;
		}
		else {
			$arr[$row['goods_id']]['prod'] = 0;
		}

		$arr[$row['goods_id']]['stock'] = $row['goods_number'];
		$sql = 'select * from ' . $GLOBALS['ecs']->table('seller_shopinfo') . ' where ru_id=\'' . $row['user_id'] . '\'';
		$basic_info = $GLOBALS['db']->getRow($sql);
		$arr[$row['goods_id']]['user_id'] = $row['user_id'];
		$arr[$row['goods_id']]['store_url'] = build_uri('merchants_store', array('urid' => $row['user_id']), $arr[$row['goods_id']]['rz_shopName']);
		$arr[$row['goods_id']]['count'] = selled_count($row['goods_id']);
	}

	return array_values($arr);
}


?>
