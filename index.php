<?php

/**
 * ECSHOP 首页文件
 * ============================================================================
 * 版权所有 2016-2018 产供销网络科技(广州)有限公司，并保留所有权利。
 * 网站地址: http://www.chandaoxiao.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author:  Hallett
*/

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

// qq登录
if (isset($_GET['code']) && !empty($_GET['code'])) {

    $oath_where = '';
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        $oath_where .= "&user_id=" . $_SESSION['user_id'];
        $oath_where .= "&jump=account_bind";
    }

    $redirect_url = $ecs->url() . 'user.php?act=oath_login&type=qq&code=' . $_GET['code'] . $oath_where;
    header('location:' . $redirect_url);
    exit;
}

if ((DEBUG_MODE & 2) != 2)
{
    $smarty->caching = true;
}

require(ROOT_PATH . '/includes/lib_area.php');  //ecmoban模板堂 --zhuo
require(ROOT_PATH . '/includes/lib_visual.php');

//ecmoban模板堂 --zhuo start
$area_info = get_area_info($province_id);
$area_id = $area_info['region_id'];

$where = "regionId = '$province_id'";
$date = array('parent_id');
$region_id = get_table_date('region_warehouse', $where, $date, 2);
//ecmoban模板堂 --zhuo end

$ua = strtolower($_SERVER['HTTP_USER_AGENT']);
$uachar = "/(nokia|sony|ericsson|mot|samsung|htc|sgh|lg|sharp|sie-|philips|panasonic|alcatel|lenovo|iphone|ipod|blackberry|meizu|android|netfront|symbian|ucweb|windowsce|palm|operamini|operamobi|opera mobi|openwave|nexusone|cldc|midp|wap|mobile)/i";

if(($ua == '' || preg_match($uachar, $ua))&& !strpos(strtolower($_SERVER['REQUEST_URI']),'wap'))
{
    $Loaction = 'mobile/';

    if (!empty($Loaction))
    {
        ecs_header("Location: $Loaction\n");

        exit;
    }
}

/*------------------------------------------------------ */
//-- Shopex系统地址转换
/*------------------------------------------------------ */
if (!empty($_GET['gOo']))
{
    if (!empty($_GET['gcat']))
    {
        /* 商品分类。*/
        $Loaction = 'category.php?id=' . $_GET['gcat'];
    }
    elseif (!empty($_GET['acat']))
    {
        /* 文章分类。*/
        $Loaction = 'article_cat.php?id=' . $_GET['acat'];
    }
    elseif (!empty($_GET['goodsid']))
    {
        /* 商品详情。*/
        $Loaction = 'goods.php?id=' . $_GET['goodsid'];
    }
    elseif (!empty($_GET['articleid']))
    {
        /* 文章详情。*/
        $Loaction = 'article.php?id=' . $_GET['articleid'];
    }

    if (!empty($Loaction))
    {
        ecs_header("Location: $Loaction\n");

        exit;
    }
}
//判断可视化模板
$suffix = !empty($_REQUEST['suffix']) ? trim($_REQUEST['suffix']) :  '';//预览传值
$preview = 1;
//不是预览且开启可视化后调用可视化模板
if(empty($suffix) && $_CFG['openvisual'] == 1){
    $enableTem = $db->getOne("SELECT value FROM" . $GLOBALS['ecs']->table('shop_config') . " WHERE code= 'hometheme' AND store_range = '".$GLOBALS['_CFG']['template']."'");
    $suffix = !empty($enableTem) ? trim($enableTem) :  '';
    $preview = 0;
}
$dir = ROOT_PATH . 'data/home_Templates/'.$GLOBALS['_CFG']['template']. '/'.$suffix;
if($preview == 1){
    $dir_temp = ROOT_PATH . 'data/home_Templates/'.$GLOBALS['_CFG']['template']. '/'.$suffix."/temp";
    if(is_dir($dir_temp)){
        $dir = $dir_temp;
    }
}

$smarty->assign('cfg_bonus_adv',$_CFG['bonus_adv']);
if(!empty($suffix) && file_exists($dir) && defined('THEME_EXTENSION')){
    
    /**
     * 首页可视化
     * 下载OSS模板文件
     */
    get_down_hometemplates($suffix);

    require(ROOT_PATH . 'homeindex.php');
    exit;
}else{
    /*------------------------------------------------------ */
    //-- 判断是否存在缓存，如果存在则调用缓存，反之读取相应内容
    /*------------------------------------------------------ */
    /* 缓存编号 */
    $cache_id = sprintf('%X', crc32($_SESSION['user_rank'] . '-' . $_CFG['lang']));

    if (!$smarty->is_cached('index.dwt', $cache_id))
    {
        assign_template();

        $position = assign_ur_here();
        $smarty->assign('page_title',      $position['title']);    // 页面标题
        $smarty->assign('ur_here',         $position['ur_here']);  // 当前位置

        /* meta information */
        $smarty->assign('keywords',        htmlspecialchars($_CFG['shop_keywords']));
        $smarty->assign('description',     htmlspecialchars($_CFG['shop_desc']));
        $smarty->assign('flash_theme',     $_CFG['flash_theme']);  // Flash轮播图片模板

        $smarty->assign('feed_url',        ($_CFG['rewrite'] == 1) ? 'feed.xml' : 'feed.php'); // RSS URL

         /**小图 start**/
         for ($i = 1; $i <= $_CFG['auction_ad']; $i++) {
            $ad_arr .= "'c" . $i . ","; // 分类广告位
            $index_ad .= "'index_ad" . $i . ","; //首页轮播图
            $cat_goods_banner .= "'cat_goods_banner" . $i . ","; //首页楼层轮播图
            $cat_goods_hot .= "'cat_goods_hot" . $i . ","; //首页楼层轮播图
            $index_brand_banner .= "'index_brand_banner" . $i . ","; //首页品牌街轮播图
            $index_brand_street .= "'index_brand_street" . $i . ","; //首页品牌街品牌
            $index_group_banner .= "'index_group_banner" . $i . ","; //首页团购活动
            $index_banner_group .= "'index_banner_group" . $i . ","; //首页轮播图团购促销
            if (defined('THEME_EXTENSION')) {
                $recommend_category .= "'recommend_category" . $i . ","; //新首页推荐分类广告 liu
                $index_expert_field .= "'expert_field_ad" . $i . ","; //新首页达人专区广告 liu
                $recommend_merchants .= "'recommend_merchants" . $i . ","; //新首页推荐店铺广告 liu
            }
        }

        $smarty->assign('adarr',       $ad_arr);
        $smarty->assign('index_ad',       $index_ad);

        if (defined('THEME_EXTENSION')) {
            $smarty->assign('rec_cat', $recommend_category); //liu
            $smarty->assign('expert_field', $index_expert_field); //liu
            $smarty->assign('recommend_merchants', $recommend_merchants); //liu
        }

        $smarty->assign('cat_goods_banner',       $cat_goods_banner);
        $smarty->assign('cat_goods_hot',       $cat_goods_hot);
        $smarty->assign('index_brand_banner',       $index_brand_banner);
        $smarty->assign('index_brand_street',       $index_brand_street);
        $smarty->assign('index_group_banner',       $index_group_banner);
        $smarty->assign('index_banner_group',       $index_banner_group);
        $smarty->assign('top_banner',        'top_banner');

        $smarty->assign('warehouse_id',       $region_id);
        $smarty->assign('area_id',       $area_id);
        /**小图 end**/

        $smarty->assign('helps',           get_shop_help());       // 网店帮助
        
        if (!defined('THEME_EXTENSION')) {
            $categories_pro = get_category_tree_leve_one();
            $smarty->assign('categories_pro', $categories_pro); // 分类树加强版
        }
        
        if (defined('THEME_EXTENSION')){
            for ($i = 1; $i <= $_CFG['auction_ad']; $i++) {
                $bonushome .= "'bonushome" . $i . ","; //首页楼层左侧广告图
            }
            $smarty->assign('bonushome', $bonushome);
            $guess_num = 10;
            $smarty->assign('floor_data', get_floor_data('index'));
        }else{
            $guess_num = 9;
            $smarty->assign('guess_store', get_guess_store($_SESSION['user_id'], 2));

            $smarty->assign('new_goods', get_recommend_goods('new', '', $region_id, $area_id));     // 最新商品
            $smarty->assign('best_goods', get_recommend_goods('best', '', $region_id, $area_id));    // 推荐商品
            $smarty->assign('hot_goods', get_recommend_goods('hot', '', $region_id, $area_id));     // 热卖商品
            $smarty->assign('promotion_goods', get_promote_goods('', $region_id, $area_id)); // 特价商品
        }

        $smarty->assign('guess_goods',     get_guess_goods($_SESSION['user_id'], 1, 1, $guess_num,$region_id, $area_id));
        $smarty->assign('data_dir',        DATA_DIR);       // 数据目录

        /* 页面中的动态内容 */
        assign_dynamic('index', $region_id, $area_id);
    }

    $smarty->display('index.dwt', $cache_id);

    /*------------------------------------------------------ */
    //-- PRIVATE FUNCTIONS
    /*------------------------------------------------------ */

    /**
     * 调用发货单查询
     *
     * @access  private
     * @return  array
     */
    function index_get_invoice_query()
    {
        $sql = 'SELECT o.order_sn, o.invoice_no, s.shipping_code FROM ' . $GLOBALS['ecs']->table('order_info') . ' AS o' .
                ' LEFT JOIN ' . $GLOBALS['ecs']->table('shipping') . ' AS s ON s.shipping_id = o.shipping_id' .
                " WHERE invoice_no > '' AND shipping_status = " . SS_SHIPPED .
                ' ORDER BY shipping_time DESC LIMIT 10';
        $all = $GLOBALS['db']->getAll($sql);

        foreach ($all AS $key => $row)
        {
            $plugin = ROOT_PATH . 'includes/modules/shipping/' . $row['shipping_code'] . '.php';

            if (file_exists($plugin))
            {
                include_once($plugin);

                $shipping = new $row['shipping_code'];
                $all[$key]['invoice_no'] = $shipping->query((string)$row['invoice_no']);
            }
        }

        clearstatcache();

        return $all;
    }

    /**
     * 获得最新的文章列表。
     *
     * @access  private
     * @return  array
     */
    function index_get_new_articles()
    {
        $sql = 'SELECT a.article_id, a.title, ac.cat_name, a.add_time, a.file_url, a.open_type, ac.cat_id, ac.cat_name ' .
                ' FROM ' . $GLOBALS['ecs']->table('article') . ' AS a, ' .
                    $GLOBALS['ecs']->table('article_cat') . ' AS ac' .
                ' WHERE a.is_open = 1 AND a.cat_id = ac.cat_id AND ac.cat_type = 1' .
                ' ORDER BY a.article_type DESC, a.add_time DESC LIMIT ' . $GLOBALS['_CFG']['article_number'];
        $res = $GLOBALS['db']->getAll($sql);

        $arr = array();
        foreach ($res AS $idx => $row)
        {
            $arr[$idx]['id']          = $row['article_id'];
            $arr[$idx]['title']       = $row['title'];
            $arr[$idx]['short_title'] = $GLOBALS['_CFG']['article_title_length'] > 0 ?
                                            sub_str($row['title'], $GLOBALS['_CFG']['article_title_length']) : $row['title'];
            $arr[$idx]['cat_name']    = $row['cat_name'];
            $arr[$idx]['add_time']    = local_date($GLOBALS['_CFG']['date_format'], $row['add_time']);
            $arr[$idx]['url']         = $row['open_type'] != 1 ?
                                            build_uri('article', array('aid' => $row['article_id']), $row['title']) : trim($row['file_url']);
            $arr[$idx]['cat_url']     = build_uri('article_cat', array('acid' => $row['cat_id']), $row['cat_name']);
        }

        return $arr;
    }

    /**
     * 获得最新的团购活动
     *
     * @access  private
     * @return  array
     */
    function index_get_group_buy()
    {
        $time = gmtime();
        $limit = get_library_number('group_buy', 'index');

        $group_buy_list = array();
        if ($limit > 0)
        {
            $sql = 'SELECT gb.act_id AS group_buy_id, gb.goods_id, gb.ext_info, gb.goods_name, gb.start_time, gb.end_time, g.goods_thumb, g.goods_img, g.market_price ' .
                    'FROM ' . $GLOBALS['ecs']->table('goods_activity') . ' AS gb, ' .
                        $GLOBALS['ecs']->table('goods') . ' AS g ' .
                    "WHERE gb.act_type = '" . GAT_GROUP_BUY . "' " .
                    "AND g.goods_id = gb.goods_id AND gb.review_status = 3 " .
                    "AND gb.start_time <= '" . $time . "' " .
                    "AND gb.end_time >= '" . $time . "' " .
                    "AND g.is_delete = 0 " .
                    "ORDER BY gb.act_id DESC " .
                    "LIMIT $limit" ;
            $res = $GLOBALS['db']->query($sql);

            while ($row = $GLOBALS['db']->fetchRow($res))
            {
                /* 如果缩略图为空，使用默认图片 */
                $row['goods_img'] = get_image_path($row['goods_id'], $row['goods_img']);
                $row['thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);

                /* 根据价格阶梯，计算最低价 */
                $ext_info = unserialize($row['ext_info']);


                $price_ladder = $ext_info['price_ladder'];
                if (!is_array($price_ladder) || empty($price_ladder))
                {
                    $row['last_price'] = price_format(0);
                }
                else
                {
                    foreach ($price_ladder AS $amount_price)
                    {
                        $price_ladder[$amount_price['amount']] = $amount_price['price'];
                    }
                }
                ksort($price_ladderp);

                $row['last_price'] = price_format(end($price_ladder));

                            /*团购节省和折扣计算 by ecmoban start*/
                            $price    = $row['market_price']; //原价 
                            $nowprice = $row['last_price']; //现价
                            $row['jiesheng'] = $price-$nowprice; //节省金额 
                            if($nowprice > 0)
                            {
                                    $row['zhekou'] = round(10 / ($price / $nowprice), 1);
                            }
                            else 
                            { 
                                    $row['zhekou'] = 0;
                            }

                            $activity_row = $GLOBALS['db']->getRow($sql);
                            $stat = group_buy_stat($row['group_buy_id'], $ext_info['deposit']);

                            $row['cur_amount'] = $stat['valid_goods'];         // 当前数量
                            $row['start_time'] = $row['start_time'];         // 开始时间
                            $row['end_time'] = $row['end_time'];         // 结束时间


                            /*团购节省和折扣计算 by ecmoban end*/
                $row['url'] = build_uri('group_buy', array('gbid' => $row['group_buy_id']));
                $row['short_name']   = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
                                               sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
                $row['short_style_name']   = add_style($row['short_name'],'');
                $group_buy_list[] = $row;

            }
        }

        return $group_buy_list;
    }

    /**
     * 取得拍卖活动列表
     * @return  array
     */
    function index_get_auction()
    {
        $now = gmtime();
        $limit = get_library_number('auction', 'index');
        $sql = "SELECT a.act_id, a.goods_id, a.goods_name, a.ext_info, g.goods_thumb ".
                "FROM " . $GLOBALS['ecs']->table('goods_activity') . " AS a," .
                          $GLOBALS['ecs']->table('goods') . " AS g" .
                " WHERE a.goods_id = g.goods_id AND a.review_status = 3" .
                " AND a.act_type = '" . GAT_AUCTION . "'" .
                " AND a.is_finished = 0" .
                " AND a.start_time <= '$now'" .
                " AND a.end_time >= '$now'" .
                " AND g.is_delete = 0" .
                " ORDER BY a.start_time DESC" .
                " LIMIT $limit";
        $res = $GLOBALS['db']->query($sql);

        $list = array();
        while ($row = $GLOBALS['db']->fetchRow($res))
        {
            $ext_info = unserialize($row['ext_info']);
            $arr = array_merge($row, $ext_info);
            $arr['formated_start_price'] = price_format($arr['start_price']);
            $arr['formated_end_price'] = price_format($arr['end_price']);
            $arr['thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
            $arr['url'] = build_uri('auction', array('auid' => $arr['act_id']));
            $arr['short_name']   = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
                                               sub_str($arr['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $arr['goods_name'];
            $arr['short_style_name']   = add_style($arr['short_name'],'');
            $list[] = $arr;
        }

        return $list;
    }
}



?>