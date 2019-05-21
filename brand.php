<?php

/**
 * ECSHOP 品牌列表
 * ============================================================================
 * 版权所有 2016-2018 产供销网络科技(广州)有限公司，并保留所有权利。
 * 网站地址: http://www.chandaoxiao.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: Hallett
*/

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

if ((DEBUG_MODE & 2) != 2)
{
    $smarty->caching = true;
}

require(ROOT_PATH . '/includes/lib_area.php');  //ecmoban模板堂 --zhuo

/*------------------------------------------------------ */
//-- INPUT
/*------------------------------------------------------ */

//ecmoban模板堂 --zhuo start

$area_info = get_area_info($province_id);

$where = "regionId = '$province_id'";
$date = array('parent_id');
$region_id = get_table_date('region_warehouse', $where, $date, 2);
//ecmoban模板堂 --zhuo end

//ecmoban模板堂 --zhuo start 仓库//by wang
$smarty->assign('province_row',  get_region_info($province_id));  
$smarty->assign('city_row',  get_region_info($city_id));  
$smarty->assign('district_row',  get_region_info($district_id));
$province_list = get_warehouse_province();

$smarty->assign('province_list', $province_list); //省、直辖市

$city_list = get_region_city_county($province_id);
$smarty->assign('city_list', $city_list); //省下级市

$district_list = get_region_city_county($city_id); 
$smarty->assign('district_list',                $district_list);//市下级县

$smarty->assign('open_area_goods',$GLOBALS['_CFG']['open_area_goods']);

/* 获得请求的平台品牌ID */
$brand_id = isset($_REQUEST['id']) && !empty($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

if(!isset($_REQUEST['id'])){
    $brand_id = isset($_REQUEST['brand']) && !empty($_REQUEST['brand']) ? intval($_REQUEST['brand']) : 0;
}

/* 获得请求的商家品牌ID */
$mbid = isset($_REQUEST['mbid']) && !empty($_REQUEST['mbid']) ? intval($_REQUEST['mbid']) : 0;

if (empty($brand_id)) {
    if (defined('THEME_EXTENSION')) {
        $act = empty($_REQUEST['act']) ? 'default' : trim($_REQUEST['act']);
        if ($act == 'default') {
            $cat_id = empty($_REQUEST['cat_id']) ? 0 : intval($_REQUEST['cat_id']);
            $smarty->assign('brand_list', get_brands($cat_id, 'brand', 0, 1));

            for ($i = 1; $i <= $_CFG['auction_ad']; $i++) {
                $brand_index_ad .= "'brand_index_ad" . $i . ",";
            }
            $smarty->assign('brand_index_ad', $brand_index_ad);
        } elseif ($act == 'filter_category') {
            include_once('includes/cls_json.php');
            $json = new JSON;
            $result = array('error' => 0, 'content' => '');

            $cat_id = empty($_REQUEST['cat_id']) ? 0 : intval($_REQUEST['cat_id']);
            $smarty->assign('brand_list', get_brands($cat_id, 'brand', 0, 1));
            $result['content'] = $smarty->fetch('library/brand_list.lbi');
            die($json->encode($result));
        } elseif ($act == 'load_more_brand') {
            include_once('includes/cls_json.php');
            $json = new JSON;
            $result = array('error' => 0, 'content' => '');

            $cat_id = empty($_REQUEST['cat_id']) ? 0 : intval($_REQUEST['cat_id']);
            $have_num = empty($_REQUEST['have_num']) ? 0 : intval($_REQUEST['have_num']);
            $load_num = empty($_REQUEST['load_num']) ? 8 : intval($_REQUEST['load_num']);
            $page = ceil($have_num / $load_num) + 1;
            $smarty->assign('brand_list', get_brands($cat_id, 'brand', 0, $page));
            $result['content'] = $smarty->fetch('library/brand_list.lbi');
            die($json->encode($result));
        }
    }

    if ($_REQUEST['step'] == 'load_brands' && !empty($_REQUEST['cat_key'])) {
        include_once('includes/cls_json.php');
        $json = new JSON;
        $result = array('error' => 0, 'content' => ''); // zhangyh_100322
        $cat_key = intval($_REQUEST['cat_key']);
        $rome_key = intval($_REQUEST['rome_key']) + 1;
        $brand_cat = read_static_cache('cat_brand_cache');

        if (!empty($brand_cat) && is_array($brand_cat)) {
            foreach ($brand_cat[$cat_key]['cat_id'] as $k => $v) {
                $brands = get_brands($v['id']);
                if ($brands) {
                    $brand_list[$k] = $brands;
                } else {
                    unset($brand_cat[$cat_key]['cat_id'][$k]);
                }
            }
            $smarty->assign('one_brand_cat', $brand_cat[$cat_key]);
            $smarty->assign('cat_key', $cat_key);
            $smarty->assign('brand_list', $brand_list);

            if (count($brand_cat[$cat_key]['cat_id']) > 0) {
                $brand_cat_ad = '';
                /*                 * 小图 start* */
                for ($i = 1; $i <= $_CFG['auction_ad']; $i++) {
                    $brand_cat_ad .= "'brand_cat_ad" . $i . ","; //首页楼层轮播图
                }
                //用于页面的罗马数字效果，罗马数字数组
                $rome_number = array(1 => 'Ⅰ', 2 => 'Ⅱ', 3 => 'Ⅲ', 4 => 'Ⅳ', 5 => 'Ⅴ', 6 => 'Ⅵ', 7 => 'Ⅶ', 8 => 'Ⅷ', 9 => 'Ⅸ', 10 => 'Ⅹ', 11 => 'Ⅺ', 12 => 'Ⅻ', 13 => 'XIII', 14 => 'XIV', 15 => 'XV', 16 => 'XVI', 17 => 'XVII', 18 => 'XVIII', 19 => 'XIX', 20 => 'XX');
                $smarty->assign('rome_number', $rome_number[$rome_key]);
                $arr = array("ad_arr" => $brand_cat_ad, "id" => $cat_key);
                $brand_cat_ad = insert_get_adv_child($arr);
                $smarty->assign('brand_cat_ad', $brand_cat_ad);
                $result['content'] = html_entity_decode($smarty->fetch('library/load_brands.lbi'));
            }
        }

        die($json->encode($result));
    }
    /* 缓存编号 */
    $cache_id = sprintf('%X', crc32($_CFG['lang']. '-' . $_SESSION['user_id']));
    if (!$smarty->is_cached('brand.dwt', $cache_id)) {
        assign_template();
        $position = assign_ur_here('', $_LANG['all_brand']);

        $smarty->assign('page_title', $position['title']);    // 页面标题
        $smarty->assign('ur_here', $position['ur_here']);  // 当前位置

        if (!defined('THEME_EXTENSION')) {
            $categories_pro = get_category_tree_leve_one();
            $smarty->assign('categories_pro', $categories_pro); // 分类树加强版
        }
        
        $smarty->assign('helps', get_shop_help());       // 网店帮助
        
        //获得品牌的分类 by wang
        $brand_cat = read_static_cache('cat_brand_cache');

        if ($brand_cat === false) {
            $brand_cat = get_categories_tree(0, 1);
            write_static_cache('cat_brand_cache', $brand_cat);
        }

        $smarty->assign('brand_cat', $brand_cat);
    }

    $smarty->display('brand.dwt', $cache_id);
    exit;
}

if (defined('THEME_EXTENSION')) {
    if (!empty($_REQUEST['id'])) {
        $brand_id = intval($_REQUEST['id']);
        ecs_header("Location: brandn.php?act=cat&id=" . $brand_id . "\n");
    }
}

/* 初始化分页信息 */
$page = !empty($_REQUEST['page'])  && intval($_REQUEST['page'])  > 0 ? intval($_REQUEST['page'])  : 1;
$size = !empty($_CFG['page_size']) && intval($_CFG['page_size']) > 0 ? intval($_CFG['page_size']) : 10;
$cate = !empty($_REQUEST['cat'])   && intval($_REQUEST['cat'])   > 0 ? intval($_REQUEST['cat'])   : 0;
$ship = isset($_REQUEST['ship']) && !empty($_REQUEST['ship']) ? intval($_REQUEST['ship']) : 0;//by wang是否包邮
$self = isset($_REQUEST['self']) && !empty($_REQUEST['self']) ? intval($_REQUEST['self']) : 0; 

if(!isset($_REQUEST['cat'])){
    $cate = isset($_REQUEST['category']) && !empty($_REQUEST['category']) ? intval($_REQUEST['category']) : 0;
}

$price_min = !empty($_REQUEST['price_min'])  &&floatval($_REQUEST['price_min'])  > 0 ? floatval($_REQUEST['price_min'])  : 0;
$price_max = !empty($_REQUEST['price_max'])  &&floatval($_REQUEST['price_max'])  > 0 ? floatval($_REQUEST['price_max'])  : 0;

/* 排序、显示方式以及类型 */
$default_display_type = $_CFG['show_order_type'] == '0' ? 'list' : ($_CFG['show_order_type'] == '1' ? 'grid' : 'text');
$default_sort_order_method = $_CFG['sort_order_method'] == '0' ? 'DESC' : 'ASC';
$default_sort_order_type   = $_CFG['sort_order_type'] == '0' ? 'goods_id' : ($_CFG['sort_order_type'] == '1' ? 'shop_price' : 'last_update');

$sort  = (isset($_REQUEST['sort'])  && in_array(trim(strtolower($_REQUEST['sort'])), array('goods_id', 'shop_price', 'last_update', 'sales_volume','comments_number'))) ? trim($_REQUEST['sort'])  : $default_sort_order_type;
$order = (isset($_REQUEST['order']) && in_array(trim(strtoupper($_REQUEST['order'])), array('ASC', 'DESC')))? trim($_REQUEST['order']) : $default_sort_order_method;
$display  = (isset($_REQUEST['display']) && in_array(trim(strtolower($_REQUEST['display'])), array('list', 'grid', 'text'))) ? trim($_REQUEST['display'])  : (isset($_COOKIE['ECS']['display']) ? $_COOKIE['ECS']['display'] : $default_display_type);
$display  = in_array($display, array('list', 'grid', 'text')) ? $display : 'text';
setcookie('ECS[display]', $display, gmtime() + 86400 * 7, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

$smarty->assign('price_min',$price_min);
$smarty->assign('price_max',$price_max);
/*------------------------------------------------------ */
//-- PROCESSOR
/*------------------------------------------------------ */

/* 页面的缓存ID */
$cache_id = sprintf('%X', crc32($brand_id . '-' . $mbid . '-' . $display .'-'.$price_min. '-'.$price_max. '-' . $sort . '-' . $order . '-' . $page . '-' . $size . '-' . $_SESSION['user_rank'] . '-' . $_CFG['lang'] . '-' . $cate.'-'.$ship.'-'.$self));

if (!$smarty->is_cached('brand_list.dwt', $cache_id))
{
    if($mbid){
        $mact = 'merchants_brands';
        $brand_info = get_brand_info($mbid, $mact);
    }else{
        $brand_info = get_brand_info($brand_id);
    }

    if (empty($brand_info))
    {
        ecs_header("Location: ./\n");
        exit;
    }

    $smarty->assign('data_dir',    DATA_DIR);
    $smarty->assign('keywords',    htmlspecialchars($brand_info['brand_desc']));
    $smarty->assign('description', htmlspecialchars($brand_info['brand_desc']));

    /* 赋值固定内容 */
    assign_template();
	
    $position = assign_ur_here($cate, $brand_info['brand_name']);
	
    $smarty->assign('page_title',     $position['title']);   // 页面标题
    $smarty->assign('ur_here',        $position['ur_here']); // 当前位置
    $smarty->assign('brand_id',       $brand_id);
    $smarty->assign('mbid',       $mbid);
    $smarty->assign('category',       $cate);
    
    $categories_pro = get_category_tree_leve_one();
    $smarty->assign('categories_pro',  $categories_pro); // 分类树加强版
    
    $smarty->assign('helps',          get_shop_help());              // 网店帮助
    $smarty->assign('show_marketprice', $_CFG['show_marketprice']);
    $smarty->assign('brand_cat_list', brand_related_cat($brand_id)); // 相关分类
    $smarty->assign('feed_url',       ($_CFG['rewrite'] == 1) ? "feed-b$brand_id.xml" : 'feed.php?brand=' . $brand_id);

    /* 调查 */
    $vote = get_vote();
    if (!empty($vote))
    {
        $smarty->assign('vote_id',     $vote['id']);
        $smarty->assign('vote',        $vote['content']);
    }

    $smarty->assign('best_goods',      brand_recommend_goods('best', $brand_id, $cate, $region_id, $area_info['region_id']));
    $smarty->assign('promotion_goods', brand_recommend_goods('promote', $brand_id, $cate, $region_id, $area_info['region_id']));
    $smarty->assign('brand',           $brand_info);
    $smarty->assign('promotion_info', get_promotion_info());

    $count = goods_count_by_brand($brand_id, $mbid, $cate, $act,$ship,$price_min,$price_max, $region_id, $area_info['region_id'],$self);
    $goodslist = brand_get_goods($brand_id, $mbid, $cate, $size, $page, $sort, $order, $region_id, $area_info['region_id'], $act,$ship,$price_min,$price_max,$self);//by wang

    if($display == 'grid')
    {
        if(count($goodslist) % 2 != 0)
        {
            $goodslist[] = array();
        }
    }
    
    $smarty->assign('goods_list',      $goodslist);
    $smarty->assign('script_name', 'brand');
		
    /**小图 start by wang头部广告**/
    for($i=1;$i<=$_CFG['auction_ad'];$i++){
            $brand_list_left_ad   .= "'brand_list_left_ad".$i.","; //品牌商品页面头部左侧广告
            $brand_list_right_ad   .= "'brand_list_right_ad".$i.","; //品牌商品页面头部右侧广告

    }
    $smarty->assign('best_goods',get_recommend_goods('best', '', $region_id, $area_info['region_id'], $goods['user_id'], 1));
    $smarty->assign('brand_list_left_ad', $brand_list_left_ad);
    $smarty->assign('brand_list_right_ad', $brand_list_right_ad);
    
    $smarty->assign('region_id', $region_id);
    $smarty->assign('area_id', $area_info['region_id']);
    
    assign_pager('brand',$cate, $count, $size, $sort, $order, $page, '', $brand_id,$price_min,$price_max, $display, '', '', '', 0, '', '', $act,$ship ,$self, $mbid); // 分页
    assign_dynamic('brand'); // 动态内容
}

$smarty->display('brand_list.dwt', $cache_id);

/**
 * 获得指定品牌下的推荐和促销商品
 *
 * @access  private
 * @param   string  $type
 * @param   integer $brand
 * @return  array
 */
function brand_recommend_goods($type, $brand, $cat = 0, $warehouse_id = 0, $area_id = 0)
{
    static $result = NULL;

    $time = gmtime();

    if ($result === NULL)
    {
        if ($cat > 0)
        {
            $cat_where = "AND " . get_children($cat);
        }
        else
        {
            $cat_where = '';
        }
		
        //ecmoban模板堂 --zhuo start
        $leftJoin = '';	
        if($GLOBALS['_CFG']['open_area_goods'] == 1){
                $leftJoin .= " left join " .$GLOBALS['ecs']->table('link_area_goods'). " as lag on g.goods_id = lag.goods_id ";
                $cat_where .= " and lag.region_id = '$area_id' ";
        }

        $shop_price = "wg.warehouse_price, wg.warehouse_promote_price, wag.region_price, wag.region_promote_price, g.model_price, g.model_attr, ";
        $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_goods'). " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
        $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_area_goods'). " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

        if($GLOBALS['_CFG']['review_goods'] == 1){
                $cate_where .= ' AND g.review_status > 2 ';
        }
        //ecmoban模板堂 --zhuo end	

        $sql = 'SELECT g.goods_id, g.goods_name, g.market_price, g.comments_number,g.sales_volume, ' .
				'IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, ' .
				"IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, " . 
                    "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, ".
                    'promote_start_date, promote_end_date, g.goods_brief, g.goods_thumb, goods_img, ' .
                    'g.is_best, g.is_new, g.is_hot, g.is_promote ' .
                'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
                $leftJoin .
                'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp '.
                    "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' ".
                "WHERE g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 AND g.brand_id = '$brand' AND " .
                    "(g.is_best = 1 OR (g.is_promote = 1 AND promote_start_date <= '$time' AND ".
                    "promote_end_date >= '$time')) $cat_where" .
               'ORDER BY g.sort_order, g.last_update DESC';
			     
        $result = $GLOBALS['db']->getAll($sql);
    }

    /* 取得每一项的数量限制 */
    $num = 0;
    $type2lib = array('best'=>'recommend_best', 'new'=>'recommend_new', 'hot'=>'recommend_hot', 'promote'=>'recommend_promotion');
    $num = get_library_number($type2lib[$type]);

    $idx = 0;
    $goods = array();
    foreach ($result AS $row)
    {
        if ($idx >= $num)
        {
            break;
        }

        if (($type == 'best' && $row['is_best'] == 1) ||
            ($type == 'promote' && $row['is_promote'] == 1 &&
            $row['promote_start_date'] <= $time && $row['promote_end_date'] >= $time))
        {
            if ($row['promote_price'] > 0)
            {
                $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
                $goods[$idx]['promote_price'] = $promote_price > 0 ? price_format($promote_price) : '';
            }
            else
            {
                $goods[$idx]['promote_price'] = '';
            }

            $goods[$idx]['id']           = $row['goods_id'];
            $goods[$idx]['name']         = $row['goods_name'];
            $goods[$idx]['sales_volume']         = $row['sales_volume'];
            $goods[$idx]['comments_number']         = $row['comments_number'];
            /* 折扣节省计算 by ecmoban start */
            if($row['market_price'] > 0)
            {
                $discount_arr = get_discount($row['goods_id']); //函数get_discount参数goods_id
            }
            $goods[$idx]['zhekou']  = $discount_arr['discount'];  //zhekou
            $goods[$idx]['jiesheng']  = $discount_arr['jiesheng']; //jiesheng
            /* 折扣节省计算 by ecmoban end */
            $goods[$idx]['brief']        = $row['goods_brief'];
            $goods[$idx]['brand_name']   = $row['brand_name'];
            $goods[$idx]['short_style_name']   = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
                                               sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
            $goods[$idx]['market_price'] = price_format($row['market_price']);
            $goods[$idx]['shop_price']   = price_format($row['shop_price']);
            $goods[$idx]['thumb']        = get_image_path($row['goods_id'], $row['goods_thumb'], true);
            $goods[$idx]['goods_img']    = get_image_path($row['goods_id'], $row['goods_img']);
            $goods[$idx]['url']          = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);

            $idx++;
        }
    }

    return $goods;
}

/**
 * 获得指定的品牌下的商品总数
 *
 * @access  private
 * @param   integer     $brand_id
 * @param   integer     $cate
 * @return  integer
 */
function goods_count_by_brand($brand_id, $mbid = 0, $cate = 0, $act = '',$ship = 0,$price_min = 0,$price_max = 0, $warehouse_id = 0, $area_id = 0, $self = 0)
{
    $cate_where = ($cate > 0) ? 'AND ' . get_children($cate) : '';

    //ecmoban模板堂 --zhuo start
    $leftJoin = '';
    if ($GLOBALS['_CFG']['open_area_goods'] == 1) {
        $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('link_area_goods') . " AS lag ON g.goods_id = lag.goods_id ";
        $cate_where .= " AND lag.region_id = '$area_id' ";
    }

    if ($GLOBALS['_CFG']['review_goods'] == 1) {
        $cate_where .= ' AND g.review_status > 2 ';
    }

    //by wang
    $tag_where = '';
    if ($ship == 1) {
        $tag_where .= " AND g.is_shipping = 1 ";
    }
    if ($self == 1) {
        $tag_where .= " AND g.user_id = 0 ";
    }

    if ($price_min) {
        $tag_where .=" AND g.shop_price >= $price_min ";
    }

    if ($price_max) {
        $tag_where .=" AND g.shop_price <= $price_max ";
    }

    if ($sort == 'last_update') {
        $sort = 'g.last_update';
    }
    //ecmoban模板堂 --zhuo end	

    $tag_where .= " AND g.brand_id = '$brand_id'";

    /* 获得商品列表 */
    $sql = 'SELECT count(*) FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
			$leftJoin.
            'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
                "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            
            "WHERE g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 $cate_where $tag_where ";
    return $GLOBALS['db']->getOne($sql);
}

/**
 * 获得品牌下的商品
 *
 * @access  private
 * @param   integer  $brand_id
 * @return  array
 */
function brand_get_goods($brand_id, $mbid = 0, $cate, $size, $page, $sort, $order, $warehouse_id = 0, $area_id = 0, $act = '',$ship='',$price_min = 0,$price_max = 0, $self = 0)
{
    $cate_where = ($cate > 0) ? 'AND ' . get_children($cate) : '';
	
    //ecmoban模板堂 --zhuo start
    $leftJoin = '';
    if ($GLOBALS['_CFG']['open_area_goods'] == 1) {
        $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('link_area_goods') . " AS lag ON g.goods_id = lag.goods_id ";
        $cate_where .= " AND lag.region_id = '$area_id' ";
    }

    $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('warehouse_goods') . " AS wg ON g.goods_id = wg.goods_id AND wg.region_id = '$warehouse_id' ";
    $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('warehouse_area_goods') . " AS wag ON g.goods_id = wag.goods_id AND wag.region_id = '$area_id' ";

    if ($GLOBALS['_CFG']['review_goods'] == 1) {
        $cate_where .= ' AND g.review_status > 2 ';
    }

    $tag_where = '';
    if ($ship == 1) {
        $tag_where .= " AND g.is_shipping = 1 ";
    }

    if ($self == 1) {
        $tag_where .= " AND g.user_id = 0 ";
    }

    if ($price_min) {
        $tag_where.=" AND g.shop_price >= $price_min ";
    }

    if ($price_max) {
        $tag_where.=" AND g.shop_price <= $price_max ";
    }

    if ($sort == 'last_update') {
        $sort = 'g.last_update';
    }
    //ecmoban模板堂 --zhuo end	

    $tag_where .= " AND g.brand_id = '$brand_id'";

    /* 获得商品列表 */
    $sql = 'SELECT g.goods_id, g.user_id, g.goods_name, g.market_price, g.shop_price AS org_price,g.sales_volume, g.model_price, g.model_attr, ' .
            "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, " .
            "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, " .
            'g.promote_start_date, g.promote_end_date, g.is_promote, g.goods_brief, g.goods_thumb , g.goods_img, g.product_price, g.product_promote_price ' .
            'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
            $leftJoin .
            'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            "WHERE g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 $cate_where $tag_where " .
            "ORDER BY $sort $order";

    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);

    $arr = array();
    while ($row = $GLOBALS['db']->fetchRow($res))
    {
        if ($row['promote_price'] > 0)
        {
            $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
        }
        else
        {
            $promote_price = 0;
        }
        
        /**
         * 重定义商品价格
         * 商品价格 + 属性价格
         * start
         */
        $price_info = get_goods_one_attr_price($row, $warehouse_id, $area_id, $promote_price);
        $row = !empty($row) ? array_merge($row, $price_info) : $row;
        $promote_price = $row['promote_price'];
        /**
         * 重定义商品价格
         * end
         */

        $arr[$row['goods_id']]['goods_id']      = $row['goods_id'];
        if($GLOBALS['display'] == 'grid')
        {
            $arr[$row['goods_id']]['goods_name']       = $GLOBALS['_CFG']['goods_name_length'] > 0 ? sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
        }
        else
        {
            $arr[$row['goods_id']]['goods_name']       = $row['goods_name'];
        }
        
        $arr[$row['goods_id']]['sales_volume']       = $row['sales_volume'];
        $arr[$row['goods_id']]['is_promote']       = $row['is_promote'];
        $arr[$row['goods_id']]['market_price']  = price_format($row['market_price']);
        $arr[$row['goods_id']]['shop_price']    = price_format($row['shop_price']);
        $arr[$row['goods_id']]['promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';
        $arr[$row['goods_id']]['goods_brief']   = $row['goods_brief'];
        $arr[$row['goods_id']]['goods_thumb']   = get_image_path($row['goods_id'], $row['goods_thumb'], true);
        $arr[$row['goods_id']]['goods_img']     = get_image_path($row['goods_id'], $row['goods_img']);
        $arr[$row['goods_id']]['url']           = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);

        //ecmoban模板堂 --zhuo start
        $sql="select * from ".$GLOBALS['ecs']->table('seller_shopinfo')." where ru_id='" .$row['user_id']. "'";
        $basic_info = $GLOBALS['db']->getRow($sql);	
        $arr[$row['goods_id']]['kf_type'] = $basic_info['kf_type'];
        
        /*处理客服旺旺数组 by kong*/
        if($basic_info['kf_ww']){
            $kf_ww=array_filter(preg_split('/\s+/', $basic_info['kf_ww']));
            $kf_ww=explode("|",$kf_ww[0]);
            if(!empty($kf_ww[1])){
                $arr[$row['goods_id']]['kf_ww'] = $kf_ww[1];
            }else{
                $arr[$row['goods_id']]['kf_ww'] ="";
            }
            
        }else{
            $arr[$row['goods_id']]['kf_ww'] ="";
        }
        /*处理客服QQ数组 by kong*/
        if($basic_info['kf_qq']){
            $kf_qq=array_filter(preg_split('/\s+/', $basic_info['kf_qq']));
            $kf_qq=explode("|",$kf_qq[0]);
            if(!empty($kf_qq[1])){
                $arr[$row['goods_id']]['kf_qq'] = $kf_qq[1];
            }else{
                $arr[$row['goods_id']]['kf_qq'] = "";
            }
            
        }else{
            $arr[$row['goods_id']]['kf_qq'] = "";
        }

        $arr[$row['goods_id']]['rz_shopName'] = get_shop_name($row['user_id'], 1); //店铺名称	
	
        $build_uri = array(
            'urid' => $row['user_id'],
            'append' => $arr[$row['goods_id']]['rz_shopName']
        );

        $domain_url = get_seller_domain_url($row['user_id'], $build_uri);
        $arr[$row['goods_id']]['store_url'] = $domain_url['domain_name'];
		
        $goods_id = $row['goods_id'];
        $count = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('comment') . " where id_value ='$goods_id' AND status = 1 AND parent_id = 0");
        $arr[$row['goods_id']]['review_count']      = $count;

        $mc_all = ments_count_all($row['goods_id']);       //总条数
        $mc_one = ments_count_rank_num($row['goods_id'],1);		//一颗星
        $mc_two = ments_count_rank_num($row['goods_id'],2);	    //两颗星	
        $mc_three = ments_count_rank_num($row['goods_id'],3);   	//三颗星
        $mc_four = ments_count_rank_num($row['goods_id'],4);		//四颗星
        $mc_five = ments_count_rank_num($row['goods_id'],5);		//五颗星
        $arr[$row['goods_id']]['zconments'] = get_conments_stars($mc_all,$mc_one,$mc_two,$mc_three,$mc_four,$mc_five);
    	//ecmoban模板堂 --zhuo end
        
        $arr[$row['goods_id']]['pictures'] = get_goods_gallery($row['goods_id'], 6);// 商品相册
        
        /*  @author-bylu 判断当前商家是否允许"在线客服" start  */
        $shop_information = get_shop_name($row['user_id']); //通过ru_id获取到店铺信息;
        $arr[$row['goods_id']]['is_IM'] = $shop_information['is_IM']; //平台是否允许商家使用"在线客服";
        //判断当前商家是平台,还是入驻商家 bylu
        if ($row['user_id'] == 0) {
            //判断平台是否开启了IM在线客服
            if ($GLOBALS['db']->getOne("SELECT kf_im_switch FROM " . $GLOBALS['ecs']->table('seller_shopinfo') . "WHERE ru_id = 0", true)) {
                $arr[$row['goods_id']]['is_dsc'] = true;
            } else {
                $arr[$row['goods_id']]['is_dsc'] = false;
            }
        } else {
            $arr[$row['goods_id']]['is_dsc'] = false;
        }
        /*  @author-bylu  end  */
    }
    
    return $arr;
}

/**
 * 获得与指定品牌相关的分类
 *
 * @access  public
 * @param   integer $brand
 * @return  array
 */
function brand_related_cat($brand)
{
    $arr[] = array('cat_id' => 0,
                 'cat_name' => $GLOBALS['_LANG']['all_category'],
                 'url'      => build_uri('brand', array('bid' => $brand), $GLOBALS['_LANG']['all_category']));

    $sql = "SELECT c.cat_id, c.cat_name, COUNT(g.goods_id) AS goods_count FROM ".
            $GLOBALS['ecs']->table('category'). " AS c, ".
            $GLOBALS['ecs']->table('goods') . " AS g " .
            "WHERE g.brand_id = '$brand' AND c.cat_id = g.cat_id ".
            "GROUP BY g.cat_id";
    $res = $GLOBALS['db']->query($sql);

    while ($row = $GLOBALS['db']->fetchRow($res))
    {
        $row['url'] = build_uri('brand', array('cid' => $row['cat_id'], 'bid' => $brand), $row['cat_name']);
        $arr[] = $row;
    }

    return $arr;
}
?>