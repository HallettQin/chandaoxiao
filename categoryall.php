<?php
//cgxlm
define('IN_ECS', true);
require dirname(__FILE__) . '/includes/init.php';

if ((DEBUG_MODE & 2) != 2) {
	$smarty->caching = true;
}

require ROOT_PATH . '/includes/lib_area.php';
$area_info = get_area_info($province_id);
$area_id = $area_info['region_id'];
$where = 'regionId = \'' . $province_id . '\'';
$date = array('parent_id');
$region_id = get_table_date('region_warehouse', $where, $date, 2);

//商品模式
$mode     = (isset($_REQUEST['mode'])) ? trim($_REQUEST['mode']) : '';
if (!in_array($mode, ['group_buy', 'presale', 'sample', 'wholesale'])) {
    $mode = 'group_buy';
}

$cache_id = sprintf('%X', crc32($_REQUEST['id'] . '-' . $_CFG['lang'].'-'.$mode));

if (!$smarty->is_cached('category_all.dwt', $cache_id)) {
	$position = assign_ur_here(0, $_LANG['all_category']);
	$smarty->assign('page_title', $position['title']);
	$smarty->assign('ur_here', $position['ur_here']);

	for ($i = 1; $i <= $_CFG['auction_ad']; $i++) {
		$category_all_left .= '\'category_all_left' . $i . ',';
		$category_all_right .= '\'category_all_right' . $i . ',';
	}

	$smarty->assign('category_all_left', $category_all_left);
	$smarty->assign('category_all_right', $category_all_right);
	$categories_list = get_category_tree_leve_one(0, 1);
    $_mode = $mode;
    if ('presale' == $mode) {
        $act = 'category';
    } elseif ('group_buy' == $mode) {
        $act = 'list';
    } elseif ('sample' == $mode) {
        $act = 'list';
    } elseif ('wholesale' == $mode) {
        $_mode = 'wholesale_cat';
        $act = 'list';
    }

    foreach ($categories_list as $key1 => $categories) {
        $categories_list[$key1]['url'] = build_uri($_mode, ['act'=>$act, 'cid'=>$categories['id']]);
        if ($categories['child_two']) {
            foreach ($categories['child_two'] as $key2 => $categories2) {
                $categories_list[$key1]['child_two'][$key2]['url'] = build_uri($_mode, ['act'=>$act, 'cid'=>$categories2['cat_id']]);
            }
        }

        if ($categories['child_tree']) {

            foreach ($categories['child_tree'] as $key2 => $categories3) {
                $categories_list[$key1]['child_tree'][$key2]['url'] = build_uri($_mode, ['act'=>$act, 'cid'=>$categories3['cat_id']]);

                if ($categories3['child_tree']) {
                    foreach ($categories3['child_tree'] as $key3 => $categories4) {
                        $categories_list[$key1]['child_tree'][$key2]['child_tree'][$key3]['url'] = build_uri($_mode, ['act' => $act, 'cid' => $categories4['id']]);
                    }
                }
            }
        }
    }

	$smarty->assign('categories_list', $categories_list);
	$categories_pro = get_category_tree_leve_one();
	$smarty->assign('categories_pro', $categories_pro);
	$top_goods = get_top10(0, '', 0, $region_id, $area_id);
	$smarty->assign('top_goods', $top_goods);
	$smarty->assign('helps', get_shop_help());
	assign_dynamic('category_all');
	assign_template('c', $catlist);
}

$smarty->display('category_all.dwt', $cache_id);

?>
