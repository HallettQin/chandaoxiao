<?php

/**
 * ECSHOP 夺宝奇兵前台页面
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

require(ROOT_PATH . '/includes/lib_area.php');  //ecmoban模板堂 --zhuo

//ecmoban模板堂 --zhuo start
$area_info = get_area_info($province_id);
$area_id = $area_info['region_id'];

$where = "regionId = '$province_id'";
$date = array('parent_id');
$region_id = get_table_date('region_warehouse', $where, $date, 2);

if(isset($_COOKIE['region_id']) && !empty($_COOKIE['region_id'])){
    $region_id = $_COOKIE['region_id'];
}

assign_ur_here();

//ecmoban模板堂 --zhuo end
$smarty->assign('now_time',  gmtime());           // 当前系统时间

/*------------------------------------------------------ */
//-- 如果用没有指定活动id，将页面重定向到即将结束的活动
/*------------------------------------------------------ */

$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
$template = "snatch_list";
if (!isset($_REQUEST['act']) && !isset($_REQUEST['id']))
{
    if (defined('THEME_EXTENSION')){
        $template = "snatch_index";
    }
    $_REQUEST['act'] = 'list';
}elseif($id > 0 && !isset($_REQUEST['act'])){
    $_REQUEST['act'] = 'main';
}

if ($_REQUEST['act'] == 'list') {

    //瀑布流 by wu start
    $smarty->assign('category_load_type', $_CFG['category_load_type']);
    $smarty->assign('query_string', preg_replace('/act=\w+&?/', '', $_SERVER['QUERY_STRING']));
    //瀑布流 by wu end	

    /* 初始化分页信息 */
    $page = isset($_REQUEST['page']) && intval($_REQUEST['page']) > 0 ? intval($_REQUEST['page']) : 1;  // 取得当前页
    $size = isset($_CFG['page_size']) && intval($_CFG['page_size']) > 0 ? intval($_CFG['page_size']) : 10; // 取得每页记录数
    $size = 15;
    $keywords = !empty($_REQUEST['keywords']) ? htmlspecialchars(trim($_REQUEST['keywords'])) : '';

    $default_sort_order_method = $_CFG['sort_order_method'] == '0' ? 'DESC' : 'ASC';
    $default_sort_order_type = "snatch_id";
    $sort = (isset($_REQUEST['sort']) && in_array(trim(strtolower($_REQUEST['sort'])), array('snatch_id', 'end_time', 'start_time'))) ? trim($_REQUEST['sort']) : $default_sort_order_type;
    $order = (isset($_REQUEST['order']) && in_array(trim(strtoupper($_REQUEST['order'])), array('ASC', 'DESC'))) ? trim($_REQUEST['order']) : $default_sort_order_method;

    assign_template();
    assign_dynamic('snatch');
    $position = assign_ur_here(1, $_LANG['snatch']);
    $smarty->assign('page_title', $position['title']);
    $smarty->assign('ur_here', $position['ur_here']);

    if (defined('THEME_EXTENSION')) {
        $categories_pro = get_category_tree_leve_one();
        $smarty->assign('categories_pro', $categories_pro); // 分类树加强版
    }

    $smarty->assign('helps', get_shop_help());       // 网店帮助
    $smarty->assign('feed_url', ($_CFG['rewrite'] == 1) ? "feed-typesnatch.xml" : 'feed.php?type=snatch'); // RSS URL

    $snatch_list = get_snatch_list($keywords, $size, $page, $sort, $order, $region_id, $area_id);
    $smarty->assign('snatch_list', $snatch_list);     //所有有效的夺宝奇兵列表

    $count = get_snatch_count($keywords);

    //瀑布流 by wu start
    if (!$_CFG['category_load_type']) {
        /* 设置分页链接 */
        $pager = get_pager('snatch.php', array('act' => 'list', 'keywords' => $keywords, 'sort' => $sort, 'order' => $order), $count, $page, $size);
        $smarty->assign('pager', $pager);
    }
    //瀑布流 by wu end	snatch
    if (defined('THEME_EXTENSION')) {
        /* 广告位 */
        for ($i = 1; $i <= $_CFG['auction_ad']; $i++) {
            $activity_top_banner .= "'activity_top_ad_snatch" . $i . ","; //轮播图
        }
        $smarty->assign('activity_top_banner', $activity_top_banner);
        //获取已拍商品数量
        $sql = " SELECT SUM(og.goods_number) FROM " . $GLOBALS['ecs']->table('order_info') . ' AS oi ' .
                " LEFT JOIN " . $GLOBALS['ecs']->table('order_goods') . " AS og ON og.order_id = oi.order_id  " .
                " WHERE oi.extension_code = 'snatch' AND oi.pay_status = 2 ";
        $snatch_goods_num = $GLOBALS['db']->getOne($sql);
        $smarty->assign('snatch_goods_num', $snatch_goods_num);
    }
    $smarty->assign('hot_goods', get_exchange_recommend_goods('hot', $region_id, $area_id));  //热门

    $smarty->display($template . '.dwt');
    exit;
}
/* 瀑布流 by wu */
elseif($_REQUEST['act'] == 'load_more_goods'){
    
     /* 初始化分页信息 */
    $page         = isset($_REQUEST['page'])   && intval($_REQUEST['page'])  > 0 ? intval($_REQUEST['page'])  : 1;  // 取得当前页
    $size         = isset($_CFG['page_size'])  && intval($_CFG['page_size']) > 0 ? intval($_CFG['page_size']) : 10; // 取得每页记录数
    $size = 15;
    $keywords   = !empty($_REQUEST['keywords'])   ? htmlspecialchars(trim($_REQUEST['keywords'])):'';

    $default_sort_order_method = $_CFG['sort_order_method'] == '0' ? 'DESC' : 'ASC';
    $default_sort_order_type = "snatch_id";
    $sort = (isset($_REQUEST['sort'])  && in_array(trim(strtolower($_REQUEST['sort'])), array('snatch_id', 'end_time', 'start_time'))) ? trim($_REQUEST['sort'])  : $default_sort_order_type;
    $order = (isset($_REQUEST['order']) && in_array(trim(strtoupper($_REQUEST['order'])), array('ASC', 'DESC'))) ? trim($_REQUEST['order']) : $default_sort_order_method;
    
    $snatch_list = get_snatch_list($keywords, $size, $page, $sort, $order, $region_id, $area_id);
    $smarty->assign('snatch_list', $snatch_list);     //所有有效的夺宝奇兵列表
	
	$smarty->assign('type',  'snatch'); 
	$result = array('error' => 0,'message' => '','cat_goods'=>'','best_goods'=>'');
	$result['cat_goods'] = html_entity_decode($smarty->fetch('library/more_goods_page.lbi'));
	die(json_encode($result));		
    
}
/* 显示页面部分 */
elseif ($_REQUEST['act'] == 'main')
{
    $goods = get_snatch($id);
    if ($goods)
    {
        if (defined('THEME_EXTENSION')){
		$position = assign_ur_here($goods['cat_id'], $goods['snatch_name'], array(), '', $goods['user_id']);
        }
	else
	{
		$position = assign_ur_here(0, $goods['snatch_name']);
	}
        $myprice = get_myprice($id);
        if ($goods['is_end'])
        {
            //如果活动已经结束,获取活动结果
            $smarty->assign('result',  get_snatch_result($id));
        }
        
        $smarty->assign('id',          $id);
        $smarty->assign('snatch_goods',       $goods); // 竞价商品
        $smarty->assign('goods',       $goods); // 竞价商品
        $smarty->assign('myprice',     $myprice);
        if (isset($goods['product_id']) && $goods['product_id'] > 0)
        {
            $goods_specifications = get_specifications_list($goods['goods_id']);

            $good_products = get_good_products($goods['goods_id'], 'AND product_id = ' . $goods['product_id']);

            $_good_products = explode('|', $good_products[0]['goods_attr']);
            $products_info = '';
            foreach ($_good_products as $value)
            {
                $products_info .= ' ' . $goods_specifications[$value]['attr_name'] . '：' . $goods_specifications[$value]['attr_value'];
            }
            $smarty->assign('products_info',     $products_info);
            unset($goods_specifications, $good_products, $_good_products,  $products_info);
        }
    }
    else
    {
        show_message($_LANG['now_not_snatch']);
    }

    /* 调查 */
    $vote = get_vote();
    if (!empty($vote))
    {
        $smarty->assign('vote_id', $vote['id']);
        $smarty->assign('vote',    $vote['content']);
    }

    assign_template();
    assign_dynamic('snatch');
    $smarty->assign('page_title',  $position['title']);
    $smarty->assign('ur_here',     $position['ur_here']);
    
    if (defined('THEME_EXTENSION')) {
        $categories_pro = get_category_tree_leve_one();
        $smarty->assign('categories_pro', $categories_pro); // 分类树加强版
    }
    
    $smarty->assign('helps',       get_shop_help());       // 网店帮助
    $smarty->assign('price_list',  get_price_list($id));
    $smarty->assign('price_list_count',  count(get_price_list($id)));
    $smarty->assign('promotion_info', get_promotion_info());
    $smarty->assign('feed_url',         ($_CFG['rewrite'] == 1) ? "feed-typesnatch.xml" : 'feed.php?type=snatch'); // RSS URL
    
    $smarty->assign('pictures',            get_goods_gallery($goods['goods_id']));                    // 商品相册
    
    //评分 start
    $mc_all = ments_count_all($goods['goods_id']);       //总条数
    $mc_one = ments_count_rank_num($goods['goods_id'],1);		//一颗星
    $mc_two = ments_count_rank_num($goods['goods_id'],2);	    //两颗星	
    $mc_three = ments_count_rank_num($goods['goods_id'],3);   	//三颗星
    $mc_four = ments_count_rank_num($goods['goods_id'],4);		//四颗星
    $mc_five = ments_count_rank_num($goods['goods_id'],5);		//五颗星
    $comment_all = get_conments_stars($mc_all,$mc_one,$mc_two,$mc_three,$mc_four,$mc_five);

    if($goods['user_id'] > 0){
            $merchants_goods_comment = get_merchants_goods_comment($goods['user_id']); //商家所有商品评分类型汇总
    }
    //评分 end 

    $smarty->assign('comment_all',  $comment_all); 
    $smarty->assign('merch_cmt',  $merchants_goods_comment);
    
    $basic_date = array('region_name');
    $basic_info['province'] = get_table_date('region', "region_id = '" . $goods['province'] . "'", $basic_date, 2);
    $basic_info['city'] = get_table_date('region', "region_id= '" . $goods['city'] . "'", $basic_date, 2) . "市";
    $basic_info['kf_type'] = $goods['kf_type'];
    $basic_info['shop_name'] = $goods['shop_name'];
      
    /*处理客服QQ数组 by kong*/
    if($goods['kf_qq']){
        $kf_qq=array_filter(preg_split('/\s+/', $goods['kf_qq']));
        $kf_qq=explode("|",$kf_qq[0]);
        if(!empty($kf_qq[1])){
            $basic_info['kf_qq'] = $kf_qq[1];
        }else{
            $basic_info['kf_qq'] = "";
        }
    }else{
        $basic_info['kf_qq'] = "";
    }
    /*处理客服旺旺数组 by kong*/
    if($goods['kf_ww']){
        $kf_ww=array_filter(preg_split('/\s+/', $goods['kf_ww']));
        $kf_ww=explode("|",$kf_ww[0]);
        if(!empty($kf_ww[1])){
            $basic_info['kf_ww'] = $kf_ww[1];
        }else{
            $basic_info['kf_ww'] = "";
        }
        $basic_info['kf_ww'] = $kf_ww[1];
    }else{
        $basic_info['kf_ww'] ="";
    }

    /*  @author-bylu 判断当前商家是否允许"在线客服" start  */
    $shop_information = get_shop_name($goods['user_id']);//通过ru_id获取到店铺信息;
    //print_arr($shop_information);
    //判断当前商家是平台,还是入驻商家 bylu
    if($goods_info['user_id'] == 0){
        //判断平台是否开启了IM在线客服
        if($db->getOne("SELECT kf_im_switch FROM ".$ecs->table('seller_shopinfo')."WHERE ru_id = 0")){
            $shop_information['is_dsc'] = true;
        }else{
            $shop_information['is_dsc'] = false;
        }
    }else{
        $shop_information['is_dsc'] = false;
    }
    $smarty->assign('shop_information',$shop_information);
    /*  @author-bylu  end  */

    $smarty->assign('basic_info',  $basic_info);
    $smarty->assign('hot_goods',       get_exchange_recommend_goods('hot', $region_id, $area_id));  //热门
    
    $smarty->display('snatch.dwt');
    exit;
}

/* 最新出价列表 */
if ($_REQUEST['act'] == 'new_price_list')
{
    include_once(ROOT_PATH .'includes/cls_json.php');
    $json = new JSON();
    $result = array('error'=>0, 'content'=>'');
    
    $myprice = get_myprice($id);
    
    $smarty->assign('price_list',  $myprice['bid_price']);
    $smarty->assign('price_list_count',  count($myprice['bid_price']));
    $result['content'] = $smarty->fetch('library/snatch_price.lbi');

    $result['id'] = $id;
    die($json->encode($result));
}

/* 用户出价处理 */
if ($_REQUEST['act'] == 'bid')
{
    include_once(ROOT_PATH .'includes/cls_json.php');
    $json = new JSON();
    $result = array('error'=>0, 'content'=>'');

    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $price = round($price, 2);

    /* 测试是否登陆 */
    if (empty($_SESSION['user_id']))
    {
        $result['error'] = 1;
        $result['prompt'] = 1;
        $result['content'] = $_LANG['not_login'];
        $result['back_url'] = "snatch.php?id=".$id;
        die($json->encode($result));
    }

    /* 获取活动基本信息用于校验 */
    $sql = 'SELECT act_name AS snatch_name, end_time, ext_info FROM ' . $GLOBALS['ecs']->table('goods_activity') . " WHERE act_id ='$id' AND review_status = 3";
    $row = $db->getRow($sql, 'SILENT');

    if ($row)
    {
        $info = unserialize($row['ext_info']);
        if ($info)
        {
            foreach ($info as $key => $val)
            {
                $row[$key] = $val;
            }
        }
    }

    if (empty($row))
    {
        $result['error'] = 1;
        $result['content'] = $db->error();
        die($json->encode($result));
    }

    if ($row['end_time']< gmtime() )
    {
        $result['error'] = 1;
        $result['content'] = $_LANG['snatch_is_end'];
        die($json->encode($result));
    }

    /* 检查出价是否合理 */
    if ($price < $row['start_price'] || $price > $row['end_price'])
    {
        $result['error'] = 1;
        
        $result['content'] = sprintf($GLOBALS['_LANG']['not_in_range'],$row['start_price'], $row['end_price']);
        die($json->encode($result));
    }

    /* 检查用户是否已经出同一价格 */
    $sql = 'SELECT COUNT(*) FROM '.$GLOBALS['ecs']->table('snatch_log'). " WHERE snatch_id = '$id' AND user_id = '$_SESSION[user_id]' AND bid_price = '$price'";
    if ($GLOBALS['db']->getOne($sql) > 0)
    {
        $result['error'] = 1;
//        $result['content'] = sprintf($GLOBALS['_LANG']['also_bid'], price_format($price, false));
        $result['content'] = sprintf($GLOBALS['_LANG']['also_bid'], '￥'.$price);
        die($json->encode($result));
    }

    /* 检查用户积分是否足够 */
    $sql = 'SELECT pay_points FROM ' .$ecs->table('users'). " WHERE user_id = '" . $_SESSION['user_id']. "'";
    $pay_points = $db->getOne($sql);
    if ($row['cost_points'] > $pay_points)
    {
        $result['error'] = 1;
        $result['content'] = $_LANG['lack_pay_points'];
        die($json->encode($result));
    }

    log_account_change($_SESSION['user_id'], 0, 0, 0, 0-$row['cost_points'],sprintf($_LANG['snatch_log'], $row['snatch_name'])); //扣除用户积分
    $sql = 'INSERT INTO ' .$ecs->table('snatch_log'). '(snatch_id, user_id, bid_price, bid_time) VALUES'.
           "('$id', '" .$_SESSION['user_id']. "', '" .$price."', " .gmtime(). ")";
    $db->query($sql);
    
    $goods = get_snatch($id);
    $smarty->assign('snatch_goods',       $goods); // 竞价商品
    
    if ($goods['is_end'])
    {
        //如果活动已经结束,获取活动结果
        $smarty->assign('result',  get_snatch_result($id));
    }
    
    $smarty->assign('price_list',  get_price_list($id));
    $smarty->assign('price_list_count',  count(get_price_list($id)));
    
    $smarty->assign('myprice',  get_myprice($id));
    $smarty->assign('id',       $id);
    $result['content'] = $smarty->fetch('library/snatch.lbi');
    $result['content_price'] = $smarty->fetch('library/snatch_price.lbi');

    $result['id'] = $id;
    die($json->encode($result));
}

/*------------------------------------------------------ */
//-- 购买商品
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'buy')
{
    if (empty($id))
    {
        ecs_header("Location: ./\n");
        exit;
    }

    if (empty($_SESSION['user_id']))
    {
        show_message($_LANG['not_login']);
    }

    $snatch = get_snatch($id);


    if (empty($snatch))
    {
        ecs_header("Location: ./\n");
        exit;
    }

    /* 未结束，不能购买 */
    if (empty($snatch['is_end']))
    {
        $page = build_uri('snatch', array('sid'=>$id));
        ecs_header("Location: $page\n");
        exit;
    }

    $result = get_snatch_result($id);

    if ($_SESSION['user_id'] != $result['user_id'])
    {
        show_message($_LANG['not_for_you']);
    }

    //检查是否已经购买过
    if ($result['order_count'] > 0)
    {
        show_message($_LANG['order_placed']);
    }

    /* 处理规格属性 */
    $goods_attr = '';
    $goods_attr_id = '';
    if ($snatch['product_id'] > 0)
    {
        $product_info = get_good_products($snatch['goods_id'], 'AND product_id = ' . $snatch['product_id']);

        $goods_attr_id = str_replace('|', ',', $product_info[0]['goods_attr']);

        $attr_list = array();
        $sql = "SELECT a.attr_name, g.attr_value " .
                "FROM " . $ecs->table('goods_attr') . " AS g, " .
                    $ecs->table('attribute') . " AS a " .
                "WHERE g.attr_id = a.attr_id " .
                "AND g.goods_attr_id " . db_create_in($goods_attr_id) . " ORDER BY a.sort_order, a.attr_id, g.goods_attr_id";
        $res = $db->query($sql);
        while ($row = $db->fetchRow($res))
        {
            $attr_list[] = $row['attr_name'] . ': ' . $row['attr_value'];
        }
        $goods_attr = join('', $attr_list);
    }
    else
    {
        $snatch['product_id'] = 0;
    }

    /* 清空购物车中所有商品 */
    include_once(ROOT_PATH . 'includes/lib_order.php');
    clear_cart(CART_SNATCH_GOODS);
	
	//ecmoban模板堂 --zhuo start
	if(!empty($_SESSION['user_id'])){
		$sess = "";
	}else{
		$sess = real_cart_mac_ip();
	}
	//ecmoban模板堂 --zhuo end

    /* 加入购物车 */
    $cart = array(
        'user_id'        => $_SESSION['user_id'],
        'session_id'     => $sess,
        'goods_id'       => $snatch['goods_id'],
        'product_id'     => $snatch['product_id'],
        'goods_sn'       => addslashes($snatch['goods_sn']),
        'goods_name'     => addslashes($snatch['goods_name']),
        'market_price'   => $snatch['market_price'],
        'goods_price'    => $result['buy_price'],
        'goods_number'   => 1,
        'goods_attr'     => $goods_attr,
        'goods_attr_id'  => $goods_attr_id,
        'warehouse_id'   => $region_id, //ecmoban模板堂 --zhuo 仓库
        'area_id'        => $area_id, //ecmoban模板堂 --zhuo 仓库地区
        'is_real'        => $snatch['is_real'],
        'ru_id'          => $snatch['user_id'],
        'extension_code' => addslashes($snatch['extension_code']),
        'parent_id'      => 0,
        'rec_type'       => CART_SNATCH_GOODS,
        'is_gift'        => 0
    );

    $db->autoExecute($ecs->table('cart'), $cart, 'INSERT');

    /* 记录购物流程类型：夺宝奇兵 */
    $_SESSION['flow_type'] = CART_SNATCH_GOODS;
    $_SESSION['extension_code'] = 'snatch';
    $_SESSION['extension_id'] = $id;
    $_SESSION['direct_shopping'] = 3;

    /* 进入收货人页面 */
    ecs_header("Location: ./flow.php?step=checkout&direct_shopping=3\n");
    exit;

}

/**
 * 取得用户对当前活动的所出过的价格
 *
 * @access  public
 * @param
 *
 * @return void
 */
function get_myprice($id)
{
    $my_only_price  = array();
    $my_price_time       = array();
    $pay_points     = 0;
    $bid_price      = array();
    if (!empty($_SESSION['user_id']))
    {
        /* 取得用户所有价格 */
        $sql = 'SELECT bid_price, bid_time FROM '.$GLOBALS['ecs']->table('snatch_log'). " WHERE snatch_id = '$id' AND user_id = '$_SESSION[user_id]' ORDER BY bid_time DESC";
        $my_price_time = $GLOBALS['db']->GetAll($sql);
        
        $sql = 'SELECT bid_price FROM '.$GLOBALS['ecs']->table('snatch_log'). " WHERE snatch_id = '$id' AND user_id = '$_SESSION[user_id]' ORDER BY bid_time DESC";
        $my_price = $GLOBALS['db']->GetCol($sql);
        if ($my_price_time)
        {
            /* 取得用户唯一价格 */
            $sql = 'SELECT bid_price , count(*) AS num FROM '.$GLOBALS['ecs']->table('snatch_log'). "  WHERE snatch_id ='$id' AND bid_price " . db_create_in(join(',', $my_price)). ' GROUP BY bid_price HAVING num = 1';
            $my_only_price = $GLOBALS['db']->GetCol($sql);
        }

        $user_name = $GLOBALS['db']->getOne("SELECT user_name FROM " . $GLOBALS['ecs']->table('users'). " WHERE user_id = '$_SESSION[user_id]' ");
        for ($i = 0, $count = count($my_price_time); $i < $count; $i++)
        {
            $bid_price[] = array(
                                'price' => price_format($my_price_time[$i]['bid_price'], false),
                                'bid_price' => price_format($my_price_time[$i]['bid_price'], false),
                                'user_name' => $user_name,
                                'bid_date' => local_date('Y-m-d H:i:s',$my_price_time[$i]['bid_time']),
                                 'is_only' => in_array($my_price_time[$i]['bid_price'],$my_only_price)
                                );
        }

        $sql = 'SELECT pay_points FROM '. $GLOBALS['ecs']->table('users')." WHERE user_id = '$_SESSION[user_id]'";
        $pay_points = $GLOBALS['db']->GetOne($sql);
        $pay_points = $pay_points.$GLOBALS['_CFG']['integral_name'];
    }

    /* 活动结束时间 */
    $sql = 'SELECT end_time FROM ' .$GLOBALS['ecs']->table('goods_activity').
           " WHERE act_id = '$id' AND review_status = 3 AND act_type=" . GAT_SNATCH;
    $end_time = $GLOBALS['db']->getOne($sql);
    $my_price_time = array(
        'pay_points'    => $pay_points,
        'bid_price'     => $bid_price,
        'bid_price_count'     => count($bid_price),
        'is_end'        => gmtime() > $end_time
        );

    return $my_price_time;
}

/**
 * 取得当前活动的前n个出价
 *
 * @access  public
 * @param   int  $num  列表个数(取前5个)
 *
 * @return void
 */
function get_price_list($id)
{
    $sql = 'SELECT t1.log_id, t1.bid_price, t1.bid_time, t2.user_name FROM '.$GLOBALS['ecs']->table('snatch_log').' AS t1, '.$GLOBALS['ecs']->table('users')." AS t2 "
            . "WHERE snatch_id = '$id' AND t1.user_id = t2.user_id ORDER BY t1.log_id DESC";
    $res = $GLOBALS['db']->query($sql);
    
    $price_list = array();
    while ($row = $GLOBALS['db']->FetchRow($res))
    {
		$row['user_name']=setAnonymous($row['user_name']); //处理用户名 by wu
        $price_list[] = array('bid_price'=>price_format($row['bid_price'], false),'user_name'=>$row['user_name'], 'bid_date' => local_date("Y-m-d H:i",$row['bid_time']));
    }
    return $price_list;
}

/**
 * 取的最近的几次活动。
 *
 * @access  public
 * @param
 *
 * @return void
 */
function get_snatch_list($keywords = '', $size, $page, $sort, $order, $warehouse_id, $area_id)
{
    $where = '';
    $leftJoin = '';
    $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_goods'). " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_area_goods'). " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
    
    if ($keywords)
    {
        $where = " AND (ga.act_name LIKE '%$keywords%' OR g.goods_name LIKE '%$keywords%') ";
    }
    
    $now = gmtime();
    $sql = "SELECT ga.act_id AS snatch_id, ga.act_name AS snatch_name, ga.end_time, ga.start_time, ga.ext_info, IFNULL(g.goods_thumb, '') AS goods_thumb, g.market_price, ".
            "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, " .
            "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, ".
            "g.promote_start_date, g.promote_end_date" .
           ' FROM ' . $GLOBALS['ecs']->table('goods_activity') . " AS ga " .
           "LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON ga.goods_id = g.goods_id " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            $leftJoin . 
           " WHERE ga.review_status = 3 AND ga.start_time <= '$now' AND g.goods_id <> '' AND ga.act_type=" . GAT_SNATCH . $where . 
           " ORDER BY $sort $order ";
    
    $snatch_list = array();
    $overtime = 0;
    
	//瀑布流 by wu start
	if(isset($_REQUEST['act']) && $_REQUEST['act'] == 'load_more_goods')
	{
		$start = intval($_REQUEST['goods_num']);
	}
	else
	{
		$start = ($page - 1) * $size;
	}
	$res = $GLOBALS['db']->selectLimit($sql, $size, $start);
	//瀑布流 by wu end	
	
    //$res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);
    while ($row = $GLOBALS['db']->FetchRow($res))
    {
        $overtime = $row['end_time'] > $now ? 0 : 1;
        
        $ext_info = unserialize($row['ext_info']);
        $snatch = array_merge($row, $ext_info);
        $row['start_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['start_time']);
        $row['end_time']   = local_date($GLOBALS['_CFG']['time_format'], $row['end_time']);
        
        $promote_price          = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
        $shop_price = ($promote_price > 0) ? $promote_price : $row['shop_price'];
        
        $snatch['max_price'] = price_format($snatch['max_price']);
        $snatch['end_time_date'] = local_date("Y-m-d H:i:s", $snatch['end_time']);
        
        $snatch_list[] = array(
            'snatch_id' => $row['snatch_id'],
            'snatch_name' => $row['snatch_name'],
            'snatch' => $snatch,
            'start_time' => $row['start_time'],
            'max_price' => price_format($snatch['max_price']), //
            'end_time' => $row['end_time'],
            'current_time' => local_date('Y-m-d H:i:s', gmtime()),
            'overtime' => $overtime,
            'formated_market_price'  => price_format($row['market_price']),
            'formated_shop_price'    => price_format($shop_price),
            'goods_thumb' => get_image_path($row['goods_id'], $row['goods_thumb'], true),
            'price_list_count' => count(get_price_list($row['snatch_id'])), // 围观次数
            'url'=>build_uri('snatch', array('sid'=>$row['snatch_id']))
        );
    }
    
    return $snatch_list;

}

function get_snatch_count($keywords = ''){
    
    $where = '';
            
    if ($keywords)
    {
        $where = " AND (ga.act_name LIKE '%$keywords%' OR g.goods_name LIKE '%$keywords%') ";
    }
    
    $now = gmtime();
    $sql = 'SELECT count(*) '.
           ' FROM ' . $GLOBALS['ecs']->table('goods_activity') ." AS ga ".
            "LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON ga.goods_id = g.goods_id " .
           " WHERE ga.review_status = 3 AND start_time <= '$now' AND g.goods_id <> '' AND act_type=" . GAT_SNATCH . $where;
    
    return $GLOBALS['db']->getOne($sql);
}

/**
 * 取得当前活动信息
 *
 * @access  public
 *
 * @return 活动名称
 */
function get_snatch($id)
{
    $sql = "SELECT g.goods_id,g.cat_id, ga.act_ensure,g.goods_desc as goods_desc_old,  g.goods_sn, g.is_real, g.goods_name, g.extension_code, g.market_price, g.shop_price AS org_price, " .
            "g.goods_img, g.user_id, ga.product_id, " .
            "IFNULL(mp.user_price, g.shop_price * '$_SESSION[discount]') AS shop_price, " .
            "g.promote_price, g.promote_start_date, g.promote_end_date, g.goods_brief, g.goods_thumb, " .
            "ga.act_name AS snatch_name, ga.start_time, ga.end_time, ga.ext_info, ga.act_desc AS `desc`, ga.act_promise,  ga.act_ensure " .
            "FROM " . $GLOBALS['ecs']->table('goods_activity') . " AS ga " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g " .
            "ON g.goods_id = ga.goods_id " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            "WHERE ga.act_id = '$id' AND g.goods_id <> '' AND ga.review_status = 3 AND g.is_delete = 0";

    $goods = $GLOBALS['db']->GetRow($sql);

    if ($goods)
    {
        $promote_price          = bargain_price($goods['promote_price'], $goods['promote_start_date'], $goods['promote_end_date']);
        $goods['formated_market_price']  = price_format($goods['market_price']);
        $goods['formated_shop_price']    = price_format($goods['shop_price']);
        $goods['formated_promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';
        $goods['goods_thumb']   = get_image_path($goods['goods_id'], $goods['goods_thumb'], true);
        $goods['goods_img']   = get_image_path($goods['goods_id'], $goods['goods_img'], true);
        $goods['url']           = build_uri('goods', array('gid'=>$goods['goods_id']), $goods['goods_name']);
        $goods['start_time']    = local_date($GLOBALS['_CFG']['time_format'], $goods['start_time']);

        $info = unserialize($goods['ext_info']);
        if ($info)
        {
            foreach ($info as $key => $val)
            {
                $goods[$key] = $val;
            }
            $goods['is_end'] = gmtime() > $goods['end_time'];
            $goods['formated_start_price'] = price_format($goods['start_price']);
            $goods['formated_end_price'] = price_format($goods['end_price']);
            $goods['formated_max_price'] = price_format($goods['max_price']);
        }
        /* 将结束日期格式化为格林威治标准时间时间戳 */
        $goods['gmt_end_time']  = local_date("Y-m-d H:i:s", $goods['end_time']);
        $goods['end_time']      = local_date($GLOBALS['_CFG']['time_format'], $goods['end_time']);
        $goods['snatch_time']   = sprintf($GLOBALS['_LANG']['snatch_start_time'], $goods['start_time'], $goods['end_time']);
        
        //ecmoban模板堂 --zhuo
        $goods['rz_shopName'] = get_shop_name($goods['user_id'], 1); //店铺名称	
        
        $goods['shopinfo'] = get_shop_name($goods['user_id'], 2);

        $goods['shopinfo']['brand_thumb'] = str_replace(array('../'), '', $goods['shopinfo']['brand_thumb']);
        
        $build_uri = array(
            'urid' => $goods['user_id'],
            'append' => $goods['rz_shopName']
        );

        $domain_url = get_seller_domain_url($goods['user_id'], $build_uri);
        $goods['store_url'] = $domain_url['domain_name'];
        
        $basic_info = get_seller_shopinfo($goods['user_id']);
        
        $goods['province'] = $basic_info['province'];
        $goods['city'] = $basic_info['city'];
        $goods['kf_type'] = $basic_info['kf_type'];
        $goods['[kf_ww'] = $basic_info['kf_ww'];
        $goods['kf_qq'] = $basic_info['kf_qq'];
        /*处理客服QQ数组 by kong*/
        if($basic_info['kf_qq']){
            $kf_qq=array_filter(preg_split('/\s+/', $basic_info['kf_qq']));
            $kf_qq=explode("|",$kf_qq[0]);
            if(!empty($kf_qq[1])){
                $goods['kf_qq'] = $kf_qq[1];
            }else{
                $goods['kf_qq'] = '';
            }
            
        }else{
            $$goods['kf_qq'] = "";
        }
        /*处理客服旺旺数组 by kong*/
        if($basic_info['kf_ww']){
            $kf_ww=array_filter(preg_split('/\s+/', $basic_info['kf_ww']));
            $kf_ww=explode("|",$kf_ww[0]);
            if(!empty($kf_ww[1])){
                $goods['[kf_ww'] = $kf_ww[1];
            }else{
                $goods['[kf_ww'] = '';
            }
        }else{
            $goods['[kf_ww'] ="";
        }
        $goods['shop_name'] = $basic_info['shop_name'];
        $goods['org_price_int'] = intval($goods['org_price']);
//        print_arr($goods);
        return $goods;
    }
    else
    {
        return false;
    }
}

/**
 * 获取最近要到期的活动id，没有则返回 0
 *
 * @access  public
 * @param
 *
 * @return void
 */
function get_last_snatch()
{
    $now = gmtime();
    $sql = 'SELECT act_id FROM ' . $GLOBALS['ecs']->table('goods_activity').
           " WHERE  start_time < '$now' AND end_time > '$now' AND review_status = 3 AND act_type = " . GAT_SNATCH .
           " ORDER BY end_time ASC LIMIT 1";
    return $GLOBALS['db']->GetOne($sql);
}

/**
 * 获得指定分类下的推荐商品
 *
 * @access  public
 * @param   string      $type       推荐类型，可以是 best, new, hot, promote
 * @param   string      $cats       分类的ID
 * @param   integer     $min        商品积分下限
 * @param   integer     $max        商品积分上限
 * @param   string      $ext        商品扩展查询
 * @return  array
 */
function get_exchange_recommend_goods($type = '', $warehouse_id, $area_id)
{
    $leftJoin = '';
    $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_goods'). " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_area_goods'). " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
    
    $now = gmtime();
    $sql =  'SELECT g.goods_id, g.goods_name, g.goods_name_style, g.market_price, ' .
            'g.goods_brief, g.goods_thumb, goods_img, b.brand_name, ' .
            
            "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, " .
            "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, ".
            "g.promote_start_date, g.promote_end_date," .
            
            'ga.act_name, ga.act_id, ga.ext_info, ga.start_time, ga.start_time, ga.end_time '.
            
            'FROM ' . $GLOBALS['ecs']->table('goods_activity') . ' AS ga ' .
            'LEFT JOIN ' . $GLOBALS['ecs']->table('goods') . ' AS g ON g.goods_id = ga.goods_id ' .
            'LEFT JOIN ' . $GLOBALS['ecs']->table('brand') . ' AS b ON b.brand_id = g.brand_id ' .
             "LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            $leftJoin .
            "WHERE ga.act_type = '" . GAT_SNATCH . "' " .
            "AND ga.review_status = 3 AND ga.start_time <= '$now' AND ga.end_time >= '$now' AND ga.is_finished < 2 ";
    
    $num = 11;
    switch ($type)
    {
        case 'best':
            $sql .= ' AND ga.is_best = 1';
            break;
        case 'new':
            $sql .= ' AND ga.is_new = 1';
            break;
        case 'hot':
            $sql .= ' AND ga.is_hot = 1';
            break;
    }

    $sql .= ($order_type == 0) ? ' ORDER BY g.sort_order, g.last_update DESC' : ' ORDER BY RAND()';
    
    $res = $GLOBALS['db']->selectLimit($sql, $num);

    $idx = 0;
    $snatch = array();
    while ($row = $GLOBALS['db']->fetchRow($res))
    {
        $snatch[$idx]['id']                = $row['goods_id'];
        $snatch[$idx]['name']              = $row['goods_name'];
        $snatch[$idx]['brief']             = $row['goods_brief'];
        $snatch[$idx]['brand_name']        = $row['brand_name'];
        $snatch[$idx]['short_name']        = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
                                                sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
        $snatch[$idx]['exchange_integral'] = $row['exchange_integral'];
        $snatch[$idx]['thumb']             = get_image_path($row['goods_id'], $row['goods_thumb'], true);
        $snatch[$idx]['goods_img']         = get_image_path($row['goods_id'], $row['goods_img']);
        $snatch[$idx]['url']               = build_uri('snatch', array('sid'=>$row['act_id']));
        
        $promote_price          = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
        $snatch[$idx]['formated_shop_price']    = price_format($row['shop_price']);
        $snatch[$idx]['formated_shop_price'] = ($promote_price > 0) ? price_format($promote_price) : $snatch[$idx]['formated_shop_price'];
        $snatch[$idx]['formated_market_price'] = price_format($row['market_price']);
        
        $ext_info = unserialize($row['ext_info']);
        $snatch_info = array_merge($row, $ext_info);
        $snatch[$idx]['auction'] = $snatch_info;
        $snatch[$idx]['status_no'] = auction_status($snatch_info);
        $snatch[$idx]['count'] = snatch_log($row['act_id']);
        $snatch[$idx]['price_list_count'] = count(get_price_list($row['act_id']));
        $snatch[$idx]['end_time_date'] = local_date("Y-m-d H:i:s", $row['end_time']);
        $snatch[$idx]['short_style_name']  = add_style($snatch[$idx]['short_name'], $row['goods_name_style']);
        $idx++;
    }

    return $snatch;
}

?>