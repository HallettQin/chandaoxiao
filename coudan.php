<?php

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

require(ROOT_PATH . '/includes/lib_area.php');  //ecmoban模板堂 --zhuo
require(ROOT_PATH . '/includes/lib_order.php');
if ((DEBUG_MODE & 2) != 2)
{
    $smarty->caching = true;
}

if (!isset($_REQUEST['act']))
{
    $_REQUEST['act'] = 'index';
}

if(!empty($_SESSION['user_id'])){
	$sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
	
	$a_sess = " a.user_id = '" . $_SESSION['user_id'] . "' ";
	$b_sess = " b.user_id = '" . $_SESSION['user_id'] . "' ";
	$c_sess = " c.user_id = '" . $_SESSION['user_id'] . "' ";
	
	$sess = "";
}else{
	$sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
	
	$a_sess = " a.session_id = '" . real_cart_mac_ip() . "' ";
	$b_sess = " b.session_id = '" . real_cart_mac_ip() . "' ";
	$c_sess = " c.session_id = '" . real_cart_mac_ip() . "' ";
	
	$sess = real_cart_mac_ip();
}

// 地区
$area_info = get_area_info($province_id);
$area_id = $area_info['region_id'];

$where = "regionId = '$province_id'";
$date = array('parent_id');
$region_id = get_table_date('region_warehouse', $where, $date, 2);
if(isset($_COOKIE['region_id']) && !empty($_COOKIE['region_id'])){
    $region_id = $_COOKIE['region_id'];
}
$smarty->assign('helps',           get_shop_help());       // 网店帮助
//凑单页面
if ($_REQUEST['act'] == 'index')
{
    $position = assign_ur_here(0, "购物凑单");
    $smarty->assign('page_title',       $position['title']);    // 页面标题
	
	assign_template();
	$categories_pro = get_category_tree_leve_one();
	$smarty->assign('categories_pro',  $categories_pro); // 分类树加强版
	$smarty->assign('guess_goods',     get_guess_goods($_SESSION['user_id'], 1, 1, 7,$region_id, $area_id)); //猜你喜欢
    
    $active_id = !empty($_REQUEST['id'])? intval($_REQUEST['id']): 0;
    
    // 判断活动是否存在
    $active_num = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM ".$GLOBALS['ecs']->table('favourable_activity')." WHERE review_status = 3 AND act_id = '$active_id' ");
    if ($active_num == 0)
    {
        show_message($_LANG['activity_error']);
    }
    $smarty->assign('active_id', $active_id);
    
    // 获取页面url中的查询参数
    $sort  = (isset($_REQUEST['sort'])  && in_array(trim(strtolower($_REQUEST['sort'])), array('goods_id', 'sales_volume', 'shop_price'))) ? trim($_REQUEST['sort'])  : 'goods_id';
    $order = (isset($_REQUEST['order']) && in_array(trim(strtoupper($_REQUEST['order'])), array('ASC', 'DESC'))) ? trim($_REQUEST['order']) : 'DESC';
    
    $count = get_favourable_goods_count($_SESSION['user_rank'], $active_id);
    /* 取得每页记录数 */
    $size = 30;

    /* 计算总页数 */
    $page_count = ceil($count / $size);

    /* 取得当前页 */
    $page = isset($_REQUEST['page']) && intval($_REQUEST['page']) > 0 ? intval($_REQUEST['page']) : 1;
    $page = $page > $page_count && !empty($page_count) ? $page_count : $page;
    $pager = get_pager('coudan.php', array('id' => $active_id, 'sort' => $sort, 'order' => $order), $count, $page, $size);
    $smarty->assign('pager', $pager);
    
    // 查询活动包含的所有商品
    $user_id = isset($_SESSION['user_id'])? $_SESSION['user_id'] : 0;
    $order_area = get_user_order_area($user_id);
    $user_area = get_user_area_reg($user_id); //2014-02-25

    if($order_area['province'] && $user_id > 0){
            $province_id = $order_area['province'];
    }else{
            if($user_area['province'] > 0){
                    $province_id = $user_area['province'];
                    setcookie('province', $user_area['province'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
                    $region_id = get_province_id_warehouse($province_id);
            }else{

                    $sql = "select region_name from " .$ecs->table('region_warehouse'). " where regionId = '" .$province_info['region_id']. "'";
                    $warehouse_name = $db->getOne($sql);

                    $province_id = $province_info['region_id'];
                    $cangku_name = $warehouse_name;
                    $region_id = get_warehouse_name_id(0,$cangku_name);
            }

    }
    $area_info = get_area_info($province_id);
    
    $favourable_goods_list = favourable_goods_list($_SESSION['user_rank'], $active_id, $sort, $order, $size, $page,$region_id, $area_info['region_id']);
    
    $smarty->assign('favourable_goods', $favourable_goods_list);
    
    //凑单活动类型 满减-满换-打折
    $smarty->assign('act_type_txt', get_act_type($_SESSION['user_rank'], $active_id));
    
    $smarty->assign('favourable_id', $active_id);
    // 查询活动中 已加入进货单的商品
    $cart_fav_goods = cart_favourable_goods($active_id);
    
    $smarty->assign('cart_favourable_goods', $cart_fav_goods);
    
    $cart_fav_num = 0;
    $cart_fav_total = 0;
    foreach ($cart_fav_goods as $key => $row)
    {
        $cart_fav_num += $row['goods_number'];
        $cart_fav_total += $row['shop_price']*$row['goods_number'];
    }
    $smarty->assign('cart_fav_num', $cart_fav_num);
    $smarty->assign('cart_fav_total', price_format($cart_fav_total));
    
    // 同一优惠活动添加到进货单的商品数量
    
    
    $smarty->assign('region_id', $region_id);
    $smarty->assign('area_id', $area_info['region_id']);
    $smarty->display('coudan.dwt');
}

// 凑单列表页面添加进货单
elseif ($_REQUEST['act']== 'ajax_update_cart')
{
    include_once('includes/cls_json.php');
    $_POST['goods']=strip_tags(urldecode($_POST['goods']));
    $_POST['goods'] = json_str_iconv($_POST['goods']);
	
    if (!empty($_REQUEST['goods_id']) && empty($_POST['goods']))
    {
        if (!is_numeric($_REQUEST['goods_id']) || intval($_REQUEST['goods_id']) <= 0)
        {
            ecs_header("Location:./\n");
        }
        $goods_id = intval($_REQUEST['goods_id']);
        exit;
    }

    $result = array('error' => 0, 'message' => '', 'content' => '', 'goods_id' => '');
    $json  = new JSON;

    if (empty($_POST['goods']))
    {
        $result['error'] = 1;
        die($json->encode($result));
    }
    
    $goods = $json->decode($_POST['goods']);
	
    $warehouse_id = intval($goods->warehouse_id);
    $area_id = intval($goods->area_id);
    
    /* 检查：该地区是否支持配送 ecmoban模板堂 --zhuo */  
    if($GLOBALS['_CFG']['open_area_goods'] == 1){
        $leftJoin = '';
        $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_goods'). " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
        $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_area_goods'). " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
        
        $sql = "SELECT g.user_id, g.review_status, g.model_attr, " .
                ' IF(g.model_price < 1, g.goods_number, IF(g.model_price < 2, wg.region_number, wag.region_number)) AS goods_number ' .
                " FROM " .$GLOBALS['ecs']->table('goods') ." as g ". 
                $leftJoin . 
                " WHERE g.goods_id = '" .$goods->goods_id. "'";
        $goodsInfo = $GLOBALS['db']->getRow($sql);
        
        $area_list = get_goods_link_area_list($goods->goods_id, $goodsInfo['user_id']);
       
        if($area_list['goods_area']){
            if(!in_array($area_id, $area_list['goods_area'])){
                $no_area  = 2;  
            }
        } else {
            $no_area  = 2;  
        }
        
        if($goodsInfo['model_attr'] == 1){
                $table_products = "products_warehouse";
                $type_files = " and warehouse_id = '$warehouse_id'";
        }elseif($goodsInfo['model_attr'] == 2){
                $table_products = "products_area";
                $type_files = " and area_id = '$area_id'";
        }else{
                $table_products = "products";
                $type_files = "";
        }

        $sql = "SELECT * FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '" .$goods->goods_id. "'" .$type_files. " LIMIT 0, 1";
        $prod = $GLOBALS['db']->getRow($sql);

        if(empty($prod)){ //当商品没有属性库存时
            $prod = 1; 
        }else{
            $prod = 0;
        }
        
        if($no_area == 2){
            $result['error'] = 1;
            $result['message'] = $_LANG['shiping_prompt'];
            
            die($json->encode($result));
        }elseif($goodsInfo['review_status'] <= 2){
            $result['error'] = 1;
            $result['message'] = $_LANG['shelves_goods']; 

            die($json->encode($result));
        }
        
    }

    /* 检查：如果商品有规格，而post的数据没有规格，把商品的规格属性通过JSON传到前台 */
    if (empty($goods->spec) AND empty($goods->quick))
    {
        //ecmoban模板堂 --zhuo start
        $groupBy = " group by ga.goods_attr_id ";
        $leftJoin = '';	

        $shop_price = "wap.attr_price, wa.attr_price, g.model_attr, ";

        $leftJoin .= " left join " .$GLOBALS['ecs']->table('goods'). " as g on g.goods_id = ga.goods_id";
        $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_attr'). " as wap on ga.goods_id = wap.goods_id and wap.warehouse_id = '$warehouse_id' and ga.goods_attr_id = wap.goods_attr_id ";
        $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_area_attr'). " as wa on ga.goods_id = wa.goods_id and wa.area_id = '$area_id' and ga.goods_attr_id = wa.goods_attr_id ";
        //ecmoban模板堂 --zhuo end
		
        $sql = "SELECT a.attr_id, a.attr_name, a.attr_type, ".
            "ga.goods_attr_id, ga.attr_value, IF(g.model_attr < 1, ga.attr_price, IF(g.model_attr < 2, wap.attr_price, wa.attr_price)) as attr_price " .
        'FROM ' . $GLOBALS['ecs']->table('goods_attr') . ' AS ga ' .
        'LEFT JOIN ' . $GLOBALS['ecs']->table('attribute') . ' AS a ON a.attr_id = ga.attr_id ' .$leftJoin.
        "WHERE a.attr_type != 0 AND ga.goods_id = '" . $goods->goods_id . "' "  . $groupBy .
        'ORDER BY a.sort_order, a.attr_id, ga.goods_attr_id';

        $res = $GLOBALS['db']->getAll($sql);
        if (!empty($res))
        {
            $spe_arr = array();
            foreach ($res AS $row) 
            {
                $spe_arr[$row['attr_id']]['attr_type'] = $row['attr_type'];
                $spe_arr[$row['attr_id']]['name']     = $row['attr_name'];
                $spe_arr[$row['attr_id']]['attr_id']     = $row['attr_id'];
                $spe_arr[$row['attr_id']]['values'][] = array(
                                                            'label'        => $row['attr_value'],
                                                            'price'        => $row['attr_price'],
                                                            'format_price' => price_format($row['attr_price'], false),
                                                            'id'           => $row['goods_attr_id']);
            }
            $i = 0;
            $spe_array = array();
            foreach ($spe_arr AS $row)
            {
                $spe_array[]=$row;
            }
            $result['error']   = ERR_NEED_SELECT_ATTR;
            $result['goods_id'] = $goods->goods_id;
            $result['warehouse_id'] = $warehouse_id;
            $result['area_id'] = $area_id;
            $result['parent'] = $goods->parent;
            $smarty->assign('spe_array',$spe_array);

            $result['message'] = $smarty->fetch("library/goods_attr.lbi");
            
            $result['active_id'] = $goods->active_id;

            die($json->encode($result));
        }
    }

    /* 检查：商品数量是否合法 */
    if (!is_numeric($goods->number) || intval($goods->number) <= 0)
    {
        $result['error']   = 1;
        $result['message'] = $_LANG['invalid_number'];
    }
    
    /* 更新：进货单 */
    else
    {
        // 限购
        $xiangouInfo = get_purchasing_goods_info($goods->goods_id);
        if($xiangouInfo['is_xiangou'] == 1){
                $user_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

                $sql= "SELECT goods_number FROM ". $ecs->table('cart') ."WHERE goods_id = ".$goods->goods_id . " and " . $sess_id;
                $cartGoodsNumInfo=$db->getRow($sql);//获取进货单数量

                $start_date = $xiangouInfo['xiangou_start_date'];
                $end_date = $xiangouInfo['xiangou_end_date'];
                $orderGoods = get_for_purchasing_goods($start_date, $end_date, $goods->goods_id, $user_id);

                $nowTime = gmtime();	
                if($nowTime > $start_date && $nowTime < $end_date){
                        if($orderGoods['goods_number'] >= $xiangouInfo['xiangou_num']){
                                $result['error']   = 1;
                                $max_num = $xiangouInfo['xiangou_num'] - $orderGoods['goods_number'];
                                $result['message'] =$_LANG['purchasing_prompt'];
                                die($json->encode($result));
                        }else{
                                if($xiangouInfo['xiangou_num'] > 0){
                                        if($cartGoodsNumInfo['goods_number'] + $orderGoods['goods_number'] + $goods->number > $xiangouInfo['xiangou_num']){
                                                $result['error']   = 1;
                                                $result['message'] =$_LANG['purchasing_prompt_two'];
                                                die($json->encode($result));
                                        }
                                }
                        }
                }
        }
        //ecmoban模板堂 --zhuo end 限购
		
        // 更新：添加到进货单
        if (addto_cart($goods->goods_id, $goods->number, $goods->spec, $goods->parent, $warehouse_id, $area_id)) //ecmoban模板堂 --zhuo $goods->warehouse_id
        {
            if ($_CFG['cart_confirm'] > 2)
            {
                $result['message'] = '';
            }
            else
            {
                $result['message'] = $_CFG['cart_confirm'] == 1 ? $_LANG['addto_cart_success_1'] : $_LANG['addto_cart_success_2'];
            }

            //凑单活动类型 满减-满换-打折
            $smarty->assign('act_type_txt', get_act_type($_SESSION['user_rank'], $goods->active_id));
            $smarty->assign('favourable_id', $goods->active_id);
            
            $cart_fav_goods = cart_favourable_goods($goods->active_id);
            $smarty->assign('cart_favourable_goods', $cart_fav_goods);
            
            $cart_fav_num = 0;
            $cart_fav_total = 0;
            foreach ($cart_fav_goods as $key => $row)
            {
                $cart_fav_num += $row['goods_number'];
                $cart_fav_total += $row['shop_price']*$row['goods_number'];
            }
            $smarty->assign('cart_fav_num', $cart_fav_num);
            $smarty->assign('cart_fav_total', price_format($cart_fav_total));
            
            $result['content'] = $smarty->fetch("library/coudan_top_list.lbi");
            
            $result['one_step_buy'] = $_CFG['one_step_buy'];
        }
        else
        {
            $result['message']  = $err->last_message();
            $result['error']    = $err->error_no;
            $result['goods_id'] = stripslashes($goods->goods_id);
            if (is_array($goods->spec))
            {
                $result['product_spec'] = implode(',', $goods->spec);
            }
            else
            {
                $result['product_spec'] = $goods->spec;
            }
        }
    }
    
    $result['confirm_type'] = !empty($_CFG['cart_confirm']) ? $_CFG['cart_confirm'] : 2;
    die($json->encode($result));
}

// 删除进货单里面的优惠活动凑单商品
elseif ($_REQUEST['act'] == 'delete_cart_fav_goods')
{
    include_once(ROOT_PATH .'includes/cls_json.php');
    $json = new JSON();
    $result = array('error'=>0, 'content'=>'', 'message'=>'');
    
    $rec_id = !empty($_POST['rec_id']) ? intval($_POST['rec_id']) : 0;
    $active_id = !empty($_POST['favourable_id']) ? intval($_POST['favourable_id']) : 0;
    if ($rec_id == 0)
    {
        $result['error'] = 1;
        $result['message'] = $_LANG['cart_no_goods'];
        die($json->encode($result));
    }
    
    // 删除进货单商品
    $GLOBALS['db']->query("DELETE FROM ".$GLOBALS['ecs']->table('cart')." WHERE rec_id = '$rec_id' ");
    
    // 当优惠活动商品不满足最低金额时-删除赠品
    $favourable = favourable_info($active_id);
    $favourable_available = favourable_available($favourable);

    if (!$favourable_available)
    {
        $sql = "DELETE FROM " .$GLOBALS['ecs']->table('cart') . " WHERE "  .$sess_id. " AND is_gift <> 0";
        $GLOBALS['db']->query($sql);
    }

    //凑单活动类型 满减-满换-打折
    $smarty->assign('act_type_txt', get_act_type($_SESSION['user_rank'], $active_id));

    $smarty->assign('favourable_id', $active_id);
    
    $cart_fav_goods = cart_favourable_goods($active_id);
    
    $smarty->assign('cart_favourable_goods', $cart_fav_goods);

    $cart_fav_num = 0;
    $cart_fav_total = 0;
    foreach ($cart_fav_goods as $key => $row)
    {
        $cart_fav_num += $row['goods_number'];
        $cart_fav_total += $row['shop_price']*$row['goods_number'];
    }
    $smarty->assign('cart_fav_num', $cart_fav_num);
    $smarty->assign('cart_fav_total', price_format($cart_fav_total));


    $result['content'] = $smarty->fetch("library/coudan_top_list.lbi");
    die($json->encode($result));
}
/**
 * 取得某用户等级当前时间可以享受的优惠活动
 * @param   int     $user_rank      用户等级id，0表示非会员
 * @param int $user_id 商家id
 * @return  array
 */
function favourable_goods_list($user_rank, $favourable_id, $sort='', $order='', $size, $page, $warehouse_id = 0, $area_id = 0)
{
    if ($sort)
    {
        $sort = " ORDER BY g.$sort ";
    }
    /* 当前用户可享受的优惠活动 */
    $favourable_list = array();
    $user_rank = ',' . $user_rank . ',';
    $now = gmtime();
    $sql = "SELECT act_range_ext, act_range, userFav_type, user_id " .
            "FROM " . $GLOBALS['ecs']->table('favourable_activity') .
            " WHERE CONCAT(',', user_rank, ',') LIKE '%" . $user_rank . "%'" .
            " AND review_status = 3 AND start_time <= '$now' AND end_time >= '$now' AND act_id = '$favourable_id' ";
    $favourable = $GLOBALS['db']->getRow($sql);
    
    if ($favourable['act_range'] == FAR_ALL)
    {
        // 全部商品
    }
    if ($favourable['act_range'] == FAR_CATEGORY)
    {
        // 按分类
        $id_list = array();
        $cat_list = explode(',', $favourable['act_range_ext']);
        foreach ($cat_list as $id) {
            
            /**
             * 当前分类下的所有子分类
             * 返回一维数组
             */
            $cat_keys = get_array_keys_cat(intval($id));

            $id_list = array_merge($id_list, $cat_keys);
        }
        $where .= " AND g.cat_id " . db_create_in($id_list);
    }
    if ($favourable['act_range'] == FAR_BRAND)
    {
        // 按品牌
        $id_list = explode(',', $favourable['act_range_ext']);

        $where .= " AND g.brand_id " . db_create_in($id_list);
    }
    if ($favourable['act_range'] == FAR_GOODS)
    {
        // 按商品分类
        $id_list = explode(',', $favourable['act_range_ext']);

        $where .= " AND g.goods_id " . db_create_in($id_list);
    }
    
    if($favourable['userFav_type'] == 0){
        $where .= " AND g.user_id = '" .$favourable['user_id']. "'";
    }
    
    $sql_goods = "SELECT g.goods_id, "
            . "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price,"
            . " g.goods_name, g.goods_thumb FROM " . $GLOBALS['ecs']->table('goods') . "AS g" .
        " LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp ".
                "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' ".
        " LEFT JOIN " .$GLOBALS['ecs']->table('warehouse_goods'). " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' " .
        " LEFT JOIN " .$GLOBALS['ecs']->table('warehouse_area_goods'). " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' " .
        " WHERE g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 $where $sort $order";
    
    $res = $GLOBALS['db']->selectLimit($sql_goods, $size, ($page - 1) * $size);
    $arr = array();
    $key = 0;
    while ($row= $GLOBALS['db']->fetchRow($res))
    {
        $arr[$key]['goods_id'] = $row['goods_id'];
        $arr[$key]['goods_name'] = $row['goods_name'];
        $arr[$key]['goods_thumb'] = $row['goods_thumb'];
        $arr[$key]['format_shop_price'] = price_format($row['shop_price']);
        $arr[$key]['url'] = build_uri('goods', array('gid'=>$row['goods_id']), $row['goods_name']);
        
        $key++;
    }
    return $arr;
}
function get_favourable_goods_count($user_rank, $favourable_id)
{
    /* 当前用户可享受的优惠活动 */
    $user_rank = ',' . $user_rank . ',';
    $now = gmtime();
    $sql = "SELECT act_range_ext, act_range, userFav_type, user_id " .
            "FROM " . $GLOBALS['ecs']->table('favourable_activity') .
            " WHERE CONCAT(',', user_rank, ',') LIKE '%" . $user_rank . "%'" .
            " AND review_status = 3 AND start_time <= '$now' AND end_time >= '$now' AND act_id = '$favourable_id' ";
    $favourable = $GLOBALS['db']->getRow($sql);
//    print_arr($favourable);
    if ($favourable['act_range'] == FAR_ALL)
    {
        // 全部商品
    }
    if ($favourable['act_range'] == FAR_CATEGORY)
    {
        // 按分类
        $id_list = array();
        $cat_list = explode(',', $favourable['act_range_ext']);
        foreach ($cat_list as $id)
        {

            /**
             * 当前分类下的所有子分类
             * 返回一维数组
             */
            $cat_keys = get_array_keys_cat(intval($id));
            
            $id_list = array_merge($id_list, $cat_keys);
        }
        $where .= " AND cat_id " . db_create_in($id_list);
    }
    if ($favourable['act_range'] == FAR_BRAND)
    {
        // 按品牌
        $id_list = explode(',', $favourable['act_range_ext']);

        $where .= " AND brand_id " . db_create_in($id_list);
    }
    if ($favourable['act_range'] == FAR_GOODS)
    {
        // 按商品分类
        $id_list = explode(',', $favourable['act_range_ext']);

        $where .= " AND goods_id " . db_create_in($id_list);
    }
    
    if($favourable['userFav_type'] == 0){
        $where .= " AND user_id = '" .$favourable['user_id']. "'";
    }
    
    $sql_goods = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('goods') .
        " WHERE is_on_sale = 1 AND is_alone_sale = 1 AND is_delete = 0 $where";
//    echo $favourable['user_id'];die;
    return $GLOBALS['db']->getOne($sql_goods);
}

// 取得当前活动 已经加入进货单的商品
function cart_favourable_goods($favourable_id, $warehouse_id = 0, $area_id = 0)
{
    $favourable = $GLOBALS['db']->getRow("SELECT * FROM " . $GLOBALS['ecs']->table('favourable_activity') . " WHERE review_status = 3 AND act_id = '$favourable_id' ");
    
    // 增加查询条件
    if(!empty($_SESSION['user_id'])){
            $c_sess = " c.user_id = '" . $_SESSION['user_id'] . "' ";
    }else{
            $c_sess = " c.session_id = '" . real_cart_mac_ip() . "' ";
    }
    $fav_where = "";
    if($favourable['userFav_type'] == 0){
        $fav_where = " AND g.user_id = '" .$favourable['user_id']. "' ";
    }
    
    /* 查询优惠范围内进货单的商品 */
    $sql = "SELECT c.rec_id, c.goods_number, g.goods_id, g.goods_thumb, g.goods_name, c.goods_price AS shop_price" . 
            " FROM " . $GLOBALS['ecs']->table('cart') . " AS c JOIN " . $GLOBALS['ecs']->table('goods') . " AS g " .
            " ON c.goods_id = g.goods_id ".
            " LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp ".
                "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' ".
            " LEFT JOIN " .$GLOBALS['ecs']->table('warehouse_goods'). " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' " .
            " LEFT JOIN " .$GLOBALS['ecs']->table('warehouse_area_goods'). " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' " .
            "WHERE " .$c_sess. " AND c.rec_type = '" . CART_GENERAL_GOODS . "' " .
            "AND c.is_gift = 0 " .
            "AND c.goods_id > 0 " . $fav_where; //ecmoban模板堂 --zhuo

    /* 根据优惠范围修正sql */
    if ($favourable['act_range'] == FAR_ALL)
    {
        // sql do not change
    }
    elseif ($favourable['act_range'] == FAR_CATEGORY)
    {
        /* 取得优惠范围分类的所有下级分类 */
        $id_list = array();
        $cat_list = explode(',', $favourable['act_range_ext']);
        foreach ($cat_list as $id)
        {
            
            /**
             * 当前分类下的所有子分类
             * 返回一维数组
             */
            $cat_keys = get_array_keys_cat(intval($id));
            
            $id_list = array_merge($id_list, $cat_keys);
        }

        $sql .= "AND g.cat_id " . db_create_in($id_list);
    }
    elseif ($favourable['act_range'] == FAR_BRAND)
    {
        $id_list = explode(',', $favourable['act_range_ext']);

        $sql .= "AND g.brand_id " . db_create_in($id_list);
    }
    else
    {
        $id_list = explode(',', $favourable['act_range_ext']);

        $sql .= "AND g.goods_id " . db_create_in($id_list);
    }

    /* 优惠范围内的商品总额 */
    $res =  $GLOBALS['db']->query($sql);
    
    $cart_favourable_goods = array();
    $key = 0;
    while ($row = $GLOBALS['db']->fetchRow($res))
    {
        $cart_favourable_goods[$key]['rec_id'] = $row['rec_id'];
        $cart_favourable_goods[$key]['goods_id'] = $row['goods_id'];
        $cart_favourable_goods[$key]['goods_name'] = $row['goods_name'];
        $cart_favourable_goods[$key]['goods_thumb'] = $row['goods_thumb'];
        $cart_favourable_goods[$key]['shop_price'] = number_format($row['shop_price'], 2, '.', '');
        $cart_favourable_goods[$key]['goods_number'] = $row['goods_number'];
        $cart_favourable_goods[$key]['goods_url'] = build_uri('goods', array('gid'=>$row['goods_id']), $row['goods_name']);
        $key++;
    }
//    print_arr($cart_favourable_goods);
    return $cart_favourable_goods;
}

// 获取优惠活动类型 满赠-满减-打折
function get_act_type($user_rank, $favourable_id)
{
    $user_rank = ',' . $user_rank . ',';
    $now = gmtime();
	if(defined('THEME_EXTENSION')){
		$select = 'act_name, act_type, min_amount, act_type_ext, gift';
	}else{
		$select = 'act_type';		
	}

    $sql = "SELECT $select " .
            "FROM " . $GLOBALS['ecs']->table('favourable_activity') .
            " WHERE CONCAT(',', user_rank, ',') LIKE '%" . $user_rank . "%'" .
            " AND review_status = 3 AND start_time <= '$now' AND end_time >= '$now' AND act_id = '$favourable_id' ";

	if(defined('THEME_EXTENSION')){
		$selected = $GLOBALS['db']->getRow($sql);		
		$act_type_txt = '';
		switch ($selected['act_type'])
		{
			case 0:
		$act_type_txt = $GLOBALS['_LANG']['With_a_gift']."| 满 " .$selected['min_amount']. " 元可换购赠品";
				break;
			case 1:
				$act_type_txt = $GLOBALS['_LANG']['Full_reduction']."| 满 " .$selected['min_amount']. " 元可享受减免 ".$selected['act_type_ext']." 元 ";
				break;
			case 2:
				$act_type_txt = $GLOBALS['_LANG']['discount']."| 满 " .$selected['min_amount']. " 元可享受折扣 ";
				break;

			default:
				break;
		}	
		return $act_type_txt;	
	}else{
		$selected = $GLOBALS['db']->getOne($sql);
		$act_type_txt = '';
		
		switch ($selected)
		{
			case 0:
				$act_type_txt = $GLOBALS['_LANG']['With_a_gift'];
				break;
			case 1:
				$act_type_txt = $GLOBALS['_LANG']['Full_reduction'];
				break;
			case 2:
				$act_type_txt = $GLOBALS['_LANG']['discount'];
				break;

			default:
				break;
		}
		return $act_type_txt;		
	}
}

?>