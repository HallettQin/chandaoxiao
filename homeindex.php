<?php
//cgxlm
assign_template();
$position = assign_ur_here();
$smarty->assign('page_title', $position['title']);
$smarty->assign('ur_here', $position['ur_here']);
$smarty->assign('keywords', htmlspecialchars($_CFG['shop_keywords']));
$smarty->assign('description', htmlspecialchars($_CFG['shop_desc']));
$smarty->assign('flash_theme', $_CFG['flash_theme']);
$smarty->assign('feed_url', $_CFG['rewrite'] == 1 ? 'feed.xml' : 'feed.php');
$smarty->assign('warehouse_id', $region_id);
$smarty->assign('area_id', $area_id);
$smarty->assign('helps', get_shop_help());
assign_dynamic('index', $region_id, $area_id);
$replace_data = array('http://localhost/ecmoban_dsc2.0.5_20170518/', 'http://localhost/ecmoban_dsc2.2.6_20170727/', 'http://localhost/ecmoban_dsc2.3/');
$page = get_html_file($dir . '/pc_html.php');
$nav_page = get_html_file($dir . '/nav_html.php');
$topBanner = get_html_file($dir . '/topBanner.php');
$topBanner = str_replace($replace_data, $ecs->url(), $topBanner);
$page = str_replace($replace_data, $ecs->url(), $page);

if ($GLOBALS['_CFG']['open_oss'] == 1) {
	$bucket_info = get_bucket_info();
	$endpoint = $bucket_info['endpoint'];
}
else {
	$endpoint = (!empty($GLOBALS['_CFG']['site_domain']) ? $GLOBALS['_CFG']['site_domain'] : '');
}

if ($page && $endpoint) {
	$desc_preg = get_goods_desc_images_preg($endpoint, $page);
	$page = $desc_preg['goods_desc'];
}

if ($topBanner && $endpoint) {
	$desc_preg = get_goods_desc_images_preg($endpoint, $topBanner);
	$topBanner = $desc_preg['goods_desc'];
}

$user_id = (!empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);

if (!defined('THEME_EXTENSION')) {
	$categories_pro = get_category_tree_leve_one();
	$smarty->assign('categories_pro', $categories_pro);
}

if (empty($_SESSION['user_id'])) {
    //未登陆
    header("Location: welcome.php\n");
    exit;
}

$bonusadv = getleft_attr('bonusadv', 0, $suffix, $GLOBALS['_CFG']['template']);

if ($bonusadv['img_file']) {
	$bonusadv['img_file'] = get_image_path(0, $bonusadv['img_file']);

	if (strpos($bonusadv['img_file'], $_COOKIE['index_img_file']) !== false) {
		if ($_COOKIE['bonusadv'] == 1) {
			$bonusadv['img_file'] = '';
		}
		else if ($bonusadv['img_file']) {
			setcookie('bonusadv', 1, gmtime() + (3600 * 10), $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
			setcookie('index_img_file', $bonusadv['img_file'], gmtime() + (3600 * 10), $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
		}
	}
	else {
		setcookie('bonusadv', 1, gmtime() + (3600 * 10), $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
		setcookie('index_img_file', $bonusadv['img_file'], gmtime() + (3600 * 10), $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
	}
}
$categorys = get_category_list(0);
foreach ($categorys as $k => $category) {
    $floorBanner = '';
    for ($i=1; $i<=20; $i++) {
        $floorBanner .= "'floor_banner".$category['cat_id']."_".$i.",";
    }
    $categorys[$k]['floorBanner'] = $floorBanner;
}

//广告位
for($i=1; $i<=20; $i++) {
    //首页大轮播图
    $topBanner .= "'index_ad".$i.",";

    //首页推荐位
    $recommend .= "'recommend_category".$i.",";
}

$smarty->assign('topBanner', $topBanner);
$smarty->assign('recommend', $recommend);
$smarty->assign('categorys', $categorys);


//公告
$cat_id = 1001;
$count  = get_article_count($cat_id, '');
$artciles_list = get_cat_articles($cat_id, 1, $count);
$smarty->assign('artciles_list', $artciles_list);

$pc_page['tem'] = $suffix;
$smarty->assign('pc_page', $pc_page);
$smarty->assign('nav_page', $nav_page);
$smarty->assign('bonusadv', $bonusadv);
$smarty->assign('page', $page);
$smarty->assign('topBanner', $topBanner);
$smarty->assign('user_id', $user_id);
$smarty->assign('site_domain', $_CFG['site_domain']);
$smarty->display('homeindex.dwt', $cache_id);

?>
