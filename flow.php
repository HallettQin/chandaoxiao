<?php

/**
 * DSC 购物流程
 * ============================================================================
 * 版权所有 2005-2016 上海商创网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecmoban.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: Zhuo $
 * $Id: common.php 2016-01-04 Zhuo $
 */

define('IN_ECS', true);
require(dirname(__FILE__) . '/includes/init.php');
require(ROOT_PATH . 'includes/lib_area.php');  //ecmoban模板堂 --zhuo
require(ROOT_PATH . 'includes/lib_order.php');

/* 载入语言文件 */
require_once(ROOT_PATH . 'languages/' .$_CFG['lang']. '/user.php');
require_once(ROOT_PATH . 'languages/' .$_CFG['lang']. '/shopping_flow.php');

//ecmoban模板堂 --zhuo start
$area_info = get_area_info($province_id);
$area_id = $area_info['region_id'];

$where = "regionId = '$province_id'";
$date = array('parent_id');
$region_id = get_table_date('region_warehouse', $where, $date, 2);

if(isset($_COOKIE['region_id']) && !empty($_COOKIE['region_id'])){
    $region_id = $_COOKIE['region_id'];
}
//ecmoban模板堂 --zhuo end

$smarty->assign('keywords',        htmlspecialchars($_CFG['shop_keywords']));
$smarty->assign('description',     htmlspecialchars($_CFG['shop_desc']));

$user_id = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

/*------------------------------------------------------ */
//-- INPUT
/*------------------------------------------------------ */

if (!isset($_REQUEST['step']))
{
    $_REQUEST['step'] = "cart";
}

//ecmoban模板堂 --zhuo start
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
//ecmoban模板堂 --zhuo end

/*------------------------------------------------------ */
//-- PROCESSOR
/*------------------------------------------------------ */

assign_template();
$position = assign_ur_here(0, $_LANG['shopping_flow']);
$smarty->assign('page_title',       $position['title']);    // 页面标题
$smarty->assign('ur_here',          $position['ur_here']);  // 当前位置
$smarty->assign('helps',            get_shop_help());       // 网店帮助
$smarty->assign('lang',             $_LANG);
$smarty->assign('show_marketprice', $_CFG['show_marketprice']);
$smarty->assign('data_dir',    DATA_DIR);       // 数据目录

$smarty->assign('user_id',   $_SESSION['user_id']);

/*------------------------------------------------------ */
//-- 添加商品到进货单
/*------------------------------------------------------ */
if ($_REQUEST['step'] == 'add_to_cart')
{
}

/**
 * 加入进货单
 */
 elseif ($_REQUEST['step'] == 'add_to_cart_showDiv') {
    include_once('includes/cls_json.php');
    $_POST['goods'] = strip_tags(urldecode($_POST['goods']));
    $_POST['goods'] = json_str_iconv($_POST['goods']);


    if (!empty($_REQUEST['goods_id']) && empty($_POST['goods'])) {
        if (!is_numeric($_REQUEST['goods_id']) || intval($_REQUEST['goods_id']) <= 0) {
            ecs_header("Location:./\n");
        }
        $goods_id = intval($_REQUEST['goods_id']);
        exit;
    }

    $result = array('error' => 0, 'message' => '', 'content' => '', 'goods_id' => '', 'goods_number' => '', 'subtotal' => '', 'script_name' => '', 'goods_recommend' => '');
    $json = new JSON;

    if (empty($_POST['goods'])) {
        $result['error'] = 1;
        die($json->encode($result));
    }

    $goods = $json->decode($_POST['goods']);

    $goods->stages_qishu = isset($goods->stages_qishu) && !empty($goods->stages_qishu) ? intval($goods->stages_qishu) : -1;

    //@author-bylu 检测当前用户白条相关权限(是否逾期,逾期不能下单);
    bt_auth_check($goods->stages_qishu);

    if (!empty($goods->script_name)) {

        $result['script_name'] = $goods->script_name;
    } else {
        $result['script_name'] = 0;
    }

    if (!empty($goods->goods_recommend)) {

        $result['goods_recommend'] = $goods->goods_recommend;
    } else {
        $result['goods_recommend'] = '';
    }

    /* 检查：如果商品有规格，而post的数据没有规格，把商品的规格属性通过JSON传到前台 */
    if (empty($goods->spec) AND empty($goods->quick)) {
        //ecmoban模板堂 --zhuo start
        $groupBy = " group by ga.goods_attr_id ";
        $leftJoin = '';

        $shop_price = "wap.attr_price, wa.attr_price, g.model_attr, ";

        $leftJoin .= " left join " . $GLOBALS['ecs']->table('goods') . " as g on g.goods_id = ga.goods_id";
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_attr') . " as wap on ga.goods_id = wap.goods_id and wap.warehouse_id = '" . $goods->warehouse_id . "' and ga.goods_attr_id = wap.goods_attr_id ";
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_attr') . " as wa on ga.goods_id = wa.goods_id and wa.area_id = '" . $goods->area_id . "' and ga.goods_attr_id = wa.goods_attr_id ";
        //ecmoban模板堂 --zhuo end

        $sql = "SELECT a.attr_id, a.attr_name, a.attr_type, " .
                "ga.goods_attr_id, ga.attr_value, IF(g.model_attr < 1, ga.attr_price, IF(g.model_attr < 2, wap.attr_price, wa.attr_price)) as attr_price " .
                'FROM ' . $GLOBALS['ecs']->table('goods_attr') . ' AS ga ' .
                'LEFT JOIN ' . $GLOBALS['ecs']->table('attribute') . ' AS a ON a.attr_id = ga.attr_id ' . $leftJoin .
                "WHERE a.attr_type != 0 AND ga.goods_id = '" . $goods->goods_id . "' " . $groupBy .
                'ORDER BY a.sort_order, a.attr_id, ga.goods_attr_id';

        $res = $GLOBALS['db']->getAll($sql);

        if (!empty($res)) {
            $spe_arr = array();
            foreach ($res AS $row) {
                $spe_arr[$row['attr_id']]['attr_type'] = $row['attr_type'];
                $spe_arr[$row['attr_id']]['name'] = $row['attr_name'];
                $spe_arr[$row['attr_id']]['attr_id'] = $row['attr_id'];
                $spe_arr[$row['attr_id']]['values'][] = array(
                    'label' => $row['attr_value'],
                    'price' => $row['attr_price'],
                    'format_price' => price_format($row['attr_price'], false),
                    'id' => $row['goods_attr_id']);
            }
            $i = 0;
            $spe_array = array();
            foreach ($spe_arr AS $row) {
                $spe_array[] = $row;
            }
            $result['error'] = ERR_NEED_SELECT_ATTR;
            $result['goods_id'] = $goods->goods_id;
            $result['parent'] = $goods->parent;
            $result['message'] = $spe_array;
            if (!empty($goods->script_name)) {
                $result['script_name'] = $goods->script_name;
            } else {
                $result['script_name'] = 0;
            }
            die($json->encode($result));
        }
    }

    /* 更新：如果是一步购物，先清空进货单 */
    if ($_CFG['one_step_buy'] == '1') {
        clear_cart();
    }

    /* 检查：商品数量是否合法 */
    if (!is_numeric($goods->number) || intval($goods->number) <= 0) {
        $result['error'] = 1;
        $result['message'] = $_LANG['invalid_number'];
    }
    else /* 更新：进货单 */
    {
        //ecmoban模板堂 --zhuo start 限购
        $nowTime = gmtime();
        $xiangouInfo = get_purchasing_goods_info($goods->goods_id);
        $start_date = $xiangouInfo['xiangou_start_date'];
        $end_date = $xiangouInfo['xiangou_end_date'];

        if ($xiangouInfo['is_xiangou'] == 1 && $nowTime > $start_date && $nowTime < $end_date) {

            $user_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
            $sql = "SELECT goods_number FROM " . $ecs->table('cart') . "WHERE goods_id = " . $goods->goods_id . " and " . $sess_id;
            $cartGoodsNumInfo = $db->getRow($sql); //获取进货单数量

            $orderGoods = get_for_purchasing_goods($start_date, $end_date, $goods->goods_id, $user_id);
            if ($orderGoods['goods_number'] >= $xiangouInfo['xiangou_num']) {
                $result['error'] = 1;
                $max_num = $xiangouInfo['xiangou_num'] - $orderGoods['goods_number'];
                $result['message'] = sprintf($_LANG['purchasing_prompt'], get_table_date('goods', "goods_id='".$goods->goods_id."'", array('goods_name'), 2));
                $result['show_info'] = '';
                die($json->encode($result));
            } else {
                if ($xiangouInfo['xiangou_num'] > 0) {
                    if ($cartGoodsNumInfo['goods_number'] + $orderGoods['goods_number'] + $goods->number > $xiangouInfo['xiangou_num']) {
                        $result['error'] = 1;
                        $result['message'] = $_LANG['purchasing_prompt_two'];
                        $result['show_info'] = '';
                        die($json->encode($result));
                    }
                }
            }
        }

        //ecmoban模板堂 --zhuo end 限购
        // 更新：添加到进货单
        if (addto_cart($goods->goods_id, $goods->number, $goods->spec, $goods->parent, $goods->warehouse_id, $goods->area_id, $goods->stages_qishu)) {
            if ($_CFG['cart_confirm'] > 2) {
                $result['message'] = '';
            } else {
                $result['message'] = $_CFG['cart_confirm'] == 1 ? $_LANG['addto_cart_success_1'] : $_LANG['addto_cart_success_2'];
            }

            $result['content'] = insert_cart_info(4);
            $result['one_step_buy'] = $_CFG['one_step_buy'];
        } else {
            $result['message'] = $err->last_message();
            $result['error'] = $err->error_no;
            $result['goods_id'] = stripslashes($goods->goods_id);
            if (is_array($goods->spec)) {
                $result['product_spec'] = implode(',', $goods->spec);
            } else {
                $result['product_spec'] = $goods->spec;
            }
        }
    }

    $result['confirm_type'] = !empty($_CFG['cart_confirm']) ? $_CFG['cart_confirm'] : 2;

    /* 取得购物类型 */
    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    $result['goods_id'] = $goods->goods_id;

    /* 计算合计 */
    $cart_goods = get_cart_goods();

    $result['goods_number'] = 0;
    foreach ($cart_goods['goods_list'] as $val) {
        $result['goods_number'] += $val['goods_number'];
    }

    $result['show_info'] = insert_show_div_info($result['goods_number'], $result['script_name'], $result['goods_id'], $result['goods_recommend'], $cart_goods['total']['goods_amount'], $cart_goods['total']['real_goods_count']);

    $result['cart_num'] = $result['goods_number'];
    $cart_info = array('goods_list' => $cart_goods['goods_list'], 'number' => $result['goods_number'], 'amount' => $cart_goods['total']['goods_amount']);
    $GLOBALS['smarty']->assign('cart_info', $cart_info);

    $result['cart_content'] = $GLOBALS['smarty']->fetch('library/cart_menu_info.lbi');

    /*  @author-bylu 如果是点击"分期购"进来的就获取到分期购商品在进货单中的ID start  */
    if(!empty($goods->stages_qishu)) {
        //判断 有无商品属性传入,如果有商品属性就将商品属性加入条件;
        if (!empty($goods->spec)) {
            $goods_attr_ids = implode(',', $goods->spec);
        } else {
            $goods_attr_ids = '';
        }

        $goods_attr_id_in=' AND '.db_create_in($goods_attr_ids,'goods_attr_id');

        $cart_value = $db->getOne("SELECT rec_id FROM ". $ecs->table('cart')." WHERE goods_id='$goods->goods_id' AND user_id='".$_SESSION['user_id']."' $goods_attr_id_in ");
        $result['cart_value'] = $cart_value;
    }
    /*  @author-bylu  end  */

    die($json->encode($result));
}

//ecmoban模板堂 --zhuo 组合购买 start
elseif ($_REQUEST['step'] == 'add_to_cart_combo') //by mike
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

    /* 更新：如果是一步购物，先清空进货单 */
    if ($_CFG['one_step_buy'] == '1')
    {
        clear_cart();
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
        //ecmoban模板堂 --zhuo start 限购
        $nowTime = gmtime();
        $xiangouInfo = get_purchasing_goods_info($goods->goods_id);
        $start_date = $xiangouInfo['xiangou_start_date'];
        $end_date = $xiangouInfo['xiangou_end_date'];

        if ($xiangouInfo['is_xiangou'] == 1 && $nowTime > $start_date && $nowTime < $end_date) {
            $user_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

            $sql = "SELECT goods_number FROM " . $ecs->table('cart') . "WHERE goods_id = " . $goods->goods_id . " and " . $sess_id;
            $cartGoodsNumInfo = $db->getRow($sql); //获取进货单数量

            $orderGoods = get_for_purchasing_goods($start_date, $end_date, $goods->goods_id, $user_id);
            if ($orderGoods['goods_number'] >= $xiangouInfo['xiangou_num']) {
                $result['error'] = 1;
                $max_num = $xiangouInfo['xiangou_num'] - $orderGoods['goods_number'];
                $result['message'] = $_LANG['purchasing_prompt'];
                die($json->encode($result));
            } else {
                if ($xiangouInfo['xiangou_num'] > 0) {
                    if ($cartGoodsNumInfo['goods_number'] + $orderGoods['goods_number'] + $goods->number > $xiangouInfo['xiangou_num']) {
                        $result['error'] = 1;
                        $result['message'] = $_LANG['purchasing_prompt_two'];
                        die($json->encode($result));
                    }
                }
            }
        }
        //ecmoban模板堂 --zhuo end 限购

        // 更新：添加到进货单
        if (addto_cart_combo($goods->goods_id, $goods->number, $goods->spec, $goods->parent, $goods->group, $goods->warehouse_id, $goods->area_id, $goods->goods_attr))
        {
            if ($_CFG['cart_confirm'] > 2)
            {
                $result['message'] = '';
            }
            else
            {
                $result['message'] = $_CFG['cart_confirm'] == 1 ? $_LANG['addto_cart_success_1'] : $_LANG['addto_cart_success_2'];
            }

            $result['group']    = $goods->group;
            $result['goods_id'] = stripslashes($goods->goods_id);
            $result['content'] = "";
            $result['one_step_buy'] = $_CFG['one_step_buy'];

            //返回 原价，配件价，库存信息

            $warehouse_area['warehouse_id'] = $goods->warehouse_id;
            $warehouse_area['area_id'] = $goods->area_id;

            $combo_goods_info = get_combo_goods_info($goods->goods_id, $goods->number, $goods->spec, $goods->parent, $warehouse_area);
            $result['fittings_price'] = $combo_goods_info['fittings_price'];
            $result['spec_price']   = $combo_goods_info['spec_price'];
            $result['goods_price'] = $combo_goods_info['goods_price'];
            $result['stock'] = $combo_goods_info['stock'];
            $result['parent'] = $goods->parent;
        }
        else
        {
            $result['message']  = $err->last_message();
            $result['error']    = $err->error_no;
            $result['group']    = $goods->group;
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

    $result['warehouse_id']     = $goods->warehouse_id;
    $result['area_id']          = $goods->area_id;
    $result['goods_attr'] = $goods->goods_attr;
    $result['goods_group'] = str_replace("_" . $goods->parent, '', $goods->group);

    $combo_goods = get_cart_combo_goods_list($goods->goods_id, $goods->parent, $goods->group);

    $result['combo_amount'] = $combo_goods['combo_amount'];
    $result['combo_number'] = $combo_goods['combo_number'];

    $result['add_group'] = $goods->add_group;

    //查询组合购买商品区间价格 start
    $parent_id = $goods->parent;
    $warehouse_id = $goods->warehouse_id;
    $area_id = $goods->area_id;
    $rev = $goods->group;
	$fitt_goods = isset($goods->fitt_goods) ? $goods->fitt_goods : array();

	if(!in_array($goods->goods_id, $fitt_goods)){
		array_unshift($fitt_goods,  $goods->goods_id);
	}

    $goods_info = get_goods_fittings_info($parent_id, $warehouse_id, $area_id, $rev);
    $fittings = get_goods_fittings(array($parent_id), $warehouse_id, $area_id, $rev, 1);

    $fittings = array_merge($goods_info, $fittings);
    $fittings = array_values($fittings);

    $fittings_interval = get_choose_goods_combo_cart($fittings);

    if($fittings_interval['return_attr'] < 1){ //配件商品没有属性时
            $result['fittings_minMax'] = price_format($fittings_interval['all_price_ori']);
            $result['market_minMax'] = price_format($fittings_interval['all_market_price']);
            $result['save_minMaxPrice'] = price_format($fittings_interval['save_price_amount']);
    }else{
            $result['fittings_minMax'] = price_format($fittings_interval['fittings_min']) ."-". number_format($fittings_interval['fittings_max'], 2, '.', '');
    $result['market_minMax'] = price_format($fittings_interval['market_min']) ."-". number_format($fittings_interval['market_max'], 2, '.', '');

            if($fittings_interval['save_minPrice'] == $fittings_interval['save_maxPrice']){
                    $result['save_minMaxPrice'] = price_format($fittings_interval['save_minPrice']);
            }else{
                    $result['save_minMaxPrice'] = price_format($fittings_interval['save_minPrice']) ."-". number_format($fittings_interval['save_maxPrice'], 2, '.', '');
            }
    }

    $goodsGroup = explode('_', $goods->group);
    $result['groupId'] = $goodsGroup[2];
    //查询组合购买商品区间价格 end

	$result['fitt_goods'] = $fitt_goods;

    $result['confirm_type'] = !empty($_CFG['cart_confirm']) ? $_CFG['cart_confirm'] : 2;
    die($json->encode($result));
}
elseif ($_REQUEST['step'] == 'del_in_cart_combo') //删除进货单项目 by mike
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

    $result = array('error' => 0, 'message' => '');
    $json  = new JSON;

    if (empty($_POST['goods']))
    {
        $result['error'] = 1;
        die($json->encode($result));
    }

    $goods = $json->decode($_POST['goods']);

    //更新临时进货单（删除配件）
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart_combo') . " WHERE ".$sess_id.
            " AND goods_id = '" . $goods->goods_id . "' AND group_id = '" . $goods->group . "'";
    $GLOBALS['db']->query($sql);

    $sql = "select count(*) from " .$GLOBALS['ecs']->table('cart_combo'). " where " .$sess_id. " and parent_id = '" . $goods->parent . "' AND group_id = '" .$goods->group. "'";
    $rec_count = $GLOBALS['db']->getOne($sql);

    if($rec_count < 1){
        //更新临时进货单（删除主件）
        $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart_combo') . " WHERE ".$sess_id. " AND goods_id = '" . $goods->parent . "' AND parent_id = 0  AND group_id = '" .$goods->group. "'";
        $GLOBALS['db']->query($sql);
    }

    $result['error'] = 0;
    $result['group'] = substr($goods->group, 0, strrpos($goods->group, "_"));
    $result['parent'] = $goods->parent;

    $combo_goods = get_cart_combo_goods_list($goods->goods_id, $goods->parent, $goods->group);

    if(empty($combo_goods['shop_price'])){
        $shop_price = get_final_price($goods->parent, 1, true, $goods->goods_attr, $goods->warehouse_id, $goods->area_id);
        $combo_goods['combo_amount'] = price_format($shop_price, false);
    }

    $result['combo_amount'] = $combo_goods['combo_amount'];
    $result['combo_number'] = $combo_goods['combo_number'];

    //查询组合购买商品区间价格 start
    $parent_id = $goods->parent;
    $warehouse_id = $goods->warehouse_id;
    $area_id = $goods->area_id;
    $rev = $goods->group;

    if($combo_goods['combo_number'] > 0){
        $goods_info = get_goods_fittings_info($parent_id, $warehouse_id, $area_id, $rev);
        $fittings = get_goods_fittings(array($parent_id), $warehouse_id, $area_id, $rev, 1);
    }else{
        $goods_info = get_goods_fittings_info($parent_id, $warehouse_id, $area_id, '', 1);
        $fittings = get_goods_fittings(array($parent_id), $warehouse_id, $area_id);
    }

    $fittings = array_merge($goods_info, $fittings);
    $fittings = array_values($fittings);

    $fittings_interval = get_choose_goods_combo_cart($fittings);

    if($combo_goods['combo_number'] > 0){
        if($fittings_interval['return_attr'] < 1){ //配件商品没有属性时
                $result['fittings_minMax'] = price_format($fittings_interval['all_price_ori']);
                $result['market_minMax'] = price_format($fittings_interval['all_market_price']);
                $result['save_minMaxPrice'] = price_format($fittings_interval['save_price_amount']);
        }else{
                $result['fittings_minMax'] = price_format($fittings_interval['fittings_min']) ."-". number_format($fittings_interval['fittings_max'], 2, '.', '');
                $result['market_minMax'] = price_format($fittings_interval['market_min']) ."-". number_format($fittings_interval['market_max'], 2, '.', '');

                if($fittings_interval['save_minPrice'] == $fittings_interval['save_maxPrice']){
                        $result['save_minMaxPrice'] = price_format($fittings_interval['save_minPrice']);
                }else{
                        $result['save_minMaxPrice'] = price_format($fittings_interval['save_minPrice']) ."-". number_format($fittings_interval['save_maxPrice'], 2, '.', '');
                }
        }
    }else{
        $result['fittings_minMax'] = price_format($fittings_interval['fittings_min']) ."-". number_format($fittings_interval['fittings_max'], 2, '.', '');
        $result['market_minMax'] = price_format($fittings_interval['market_min']) ."-". number_format($fittings_interval['market_max'], 2, '.', '');

        if($fittings_interval['save_minPrice'] == $fittings_interval['save_maxPrice']){
                $result['save_minMaxPrice'] = price_format($fittings_interval['save_minPrice']);
        }else{
                $result['save_minMaxPrice'] = price_format($fittings_interval['save_minPrice']) ."-". number_format($fittings_interval['save_maxPrice'], 2, '.', '');
        }
    }

    $goodsGroup = explode('_', $goods->group);
    $result['groupId'] = $goodsGroup[2];
    //查询组合购买商品区间价格 end

    die($json->encode($result));
}

/**
 * 套餐添加到进货单
 * by mike
 */
 elseif ($_REQUEST['step'] == 'add_to_cart_group') {
    include_once('includes/cls_json.php');
    $_POST['goods'] = strip_tags(urldecode($_POST['goods']));
    $_POST['goods'] = json_str_iconv($_POST['goods']);

    $result = array('error' => 0, 'message' => '');
    $json = new JSON;

    if (empty($_POST['goods'])) {
        $result['error'] = 1;
        $result['message'] = $_LANG['system_error'];
        die($json->encode($result));
    }

    $goods = $json->decode($_POST['goods']);
    $group = $goods->group . "_" . $goods->goods_id; //套餐组
    //批量加入进货单
    $sql = "SELECT rec_id FROM " . $GLOBALS['ecs']->table('cart_combo') . " WHERE " . $sess_id .
            " AND group_id = '" . $group . "' ORDER BY parent_id limit 1";
    $res = $GLOBALS['db']->query($sql);

    if ($res) {
        //清空进货单中的原有数据
        $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart') . " WHERE " . $sess_id . " AND group_id = '" . $group . "'";
        $GLOBALS['db']->query($sql);
        //插入新的数据
        $sql = "INSERT INTO " . $GLOBALS['ecs']->table('cart') . "(" .
                "user_id, session_id, goods_id, goods_sn, product_id, group_id, goods_name, market_price, goods_price, goods_number, goods_attr, is_real, " .
                "extension_code, parent_id, rec_type, is_gift, is_shipping, can_handsel, model_attr, goods_attr_id, warehouse_id, area_id, add_time" .
                ")" . " SELECT " .
                "user_id, session_id, goods_id, goods_sn, product_id, group_id, goods_name, market_price, goods_price, goods_number, goods_attr, is_real, " .
                "extension_code, parent_id, rec_type, is_gift, is_shipping, can_handsel, model_attr, goods_attr_id, warehouse_id, area_id, add_time" .
                " FROM " . $GLOBALS['ecs']->table('cart_combo') . " WHERE " . $sess_id . " AND group_id = '" . $group . "'";
        $GLOBALS['db']->query($sql);

        //查询ru_id
        $sql = " SELECT user_id FROM " . $GLOBALS['ecs']->table('goods') . " WHERE goods_id = '" . $goods->goods_id . "' ";
        $ru_id = $GLOBALS['db']->getOne($sql, true);

        //插入更新进货单商品数量
        $sql = "UPDATE " . $GLOBALS['ecs']->table('cart') . " SET goods_number = '$goods->number', ru_id = '$ru_id' WHERE " . $sess_id . " AND group_id = '" . $group . "'";
        $GLOBALS['db']->query($sql);

        //清空套餐临时数据
        $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart_combo') . " WHERE " . $sess_id . " AND group_id = '" . $group . "'";
        $GLOBALS['db']->query($sql);
    } else {
        $result['error'] = 1;
        $result['message'] = $_LANG['data_null'];
        die($json->encode($result));
    }

    $result['error'] = 0;
    die($json->encode($result));
}

//获取组合购买商品选择数据列表
elseif ($_REQUEST['step'] == 'add_cart_combo_list') { //by zhuo
    include_once('includes/cls_json.php');
    $_POST['group'] = strip_tags(urldecode($_POST['group']));
    $_POST['group'] = json_str_iconv($_POST['group']);

    $result = array('error' => 0, 'message' => '', 'content' => '', 'goods_id' => '');
    $json = new JSON;

    if (empty($_POST['group'])) {
        $result['error'] = 1;
        die($json->encode($result));
    }

    $group = $json->decode($_POST['group']);
    $number = $group->number;
    $goods = explode('_', $group->rev);

    $goodSEqual = isset($group->fitt_goods) ? $group->fitt_goods : array();
    $goods_id = $goods[3];
    $warehouse_id = $goods[4];
    $area_id = $goods[5];
    $rev = $goods[0] . "_" . $goods[1] . "_" . $goods[2] . "_" . $goods[3];
    $group = $goods[0] . "_" . $goods[1] . "_" . $goods[2];

    $result['groupId'] = $goods[2];
    $result['number'] = $number;

    if (!empty($number)) {
        $smarty->assign('number', $number); //套餐数量
    }
    $result['group'] = $group;
    $result['goods_id'] = $goods_id;
    $result['warehouse_id'] = $warehouse_id;
    $result['area_id'] = $area_id;
    $smarty->assign('group', $group); //组名称
    $smarty->assign('warehouse_id', $warehouse_id); //仓库
    $smarty->assign('area_id', $area_id); //地区
    $smarty->assign('goods_id', $goods_id); //主件商品ID
    //判断商品是否有属性，并且已经全选
    $list_select = get_combo_goods_list_select(0, $goods[3], $rev);

    //获取组合购买商品的总数量
    $combo_goods = get_cart_combo_goods_list(0, $goods[3], $rev);

    $result['group_rev'] = $goods[0] . "_" . $goods[1] . "_" . $goods[2] . "_" . $goods[3] . "_" . $goods[4] . "_" . $goods[5];
    $smarty->assign('group_rev', $result['group_rev']); //主件商品ID

    $fittings_top = get_goods_fittings(array($goods_id), $warehouse_id, $area_id, $goods[2], 2);
    $fittings_top = array_values($fittings_top);
    $smarty->assign('fittings_top', $fittings_top); // 配件列表
    $smarty->assign('list_select', $list_select); //是否全选

    if ($goodSEqual) { //弹框中点击添加配件商品
        $goods_info = get_goods_fittings_info($goods_id, $warehouse_id, $area_id, $rev);
        $fittings = get_goods_fittings(array($goods_id), $warehouse_id, $area_id, $rev, 1, $goodSEqual);

        $fittings = array_merge($goods_info, $fittings);
        $fittings = array_values($fittings);

        $fittings_interval = get_choose_goods_combo_cart($fittings, $number);

        $result['amount'] = !empty($fittings_interval['fittings_price']) ? $fittings_interval['fittings_price'] : 0;
        if ($list_select == 1) {
            $result['goods_amount'] = !empty($fittings_interval['fittings_price']) ? price_format($fittings_interval['fittings_price']) : 0;
            $result['goods_market_amount'] = !empty($fittings_interval['all_market_price']) ? price_format($fittings_interval['all_market_price']) : 0;
            $result['save_amount'] = price_format($fittings_interval['save_price_amount']);
        } else {
            $result['goods_amount'] = price_format($fittings_interval['fittings_min']) . "-" . number_format($fittings_interval['fittings_max'], 2, '.', '');

            if ($fittings_interval['save_minPrice'] == $fittings_interval['save_maxPrice']) {
                $result['save_amount'] = price_format($fittings_interval['save_minPrice']);
            } else {
                $result['save_amount'] = price_format($fittings_interval['save_minPrice']) . "-" . number_format($fittings_interval['save_maxPrice'], 2, '.', '');
            }

            $result['goods_market_amount'] = price_format($fittings_interval['market_min']) . "-" . number_format($fittings_interval['market_max'], 2, '.', '');
        }

        $result['fittings_minMax'] = $result['goods_amount'];
        $result['market_minMax'] = $result['goods_market_amount'];
        $result['save_minMaxPrice'] = $result['save_amount'];
    } else { //商品详情页中点击组合购买
        if ($combo_goods['combo_number'] > 0) {
            $goods_info = get_goods_fittings_info($goods_id, $warehouse_id, $area_id, $rev);
            $fittings = get_goods_fittings(array($goods_id), $warehouse_id, $area_id, $rev, 1);
        } else {
            $goods_info = get_goods_fittings_info($goods_id, $warehouse_id, $area_id, '', 1);
            $fittings = get_goods_fittings(array($goods_id), $warehouse_id, $area_id);
        }

        $fittings = array_merge($goods_info, $fittings);
        $fittings = array_values($fittings);

        $fittings_interval = get_choose_goods_combo_cart($fittings);

        if ($combo_goods['combo_number'] > 0) {
            if ($list_select == 1) {
                $result['fittings_minMax'] = price_format($fittings_interval['all_price_ori']);
                $result['market_minMax'] = price_format($fittings_interval['all_market_price']);
                $result['save_minMaxPrice'] = price_format($fittings_interval['save_price_amount']);
            } else {
                if ($fittings_interval['return_attr'] < 1) { //配件商品没有属性时
                    $result['fittings_minMax'] = price_format($fittings_interval['all_price_ori']);
                    $result['save_minMaxPrice'] = price_format($fittings_interval['save_price_amount']);
                } else {
                    $result['fittings_minMax'] = price_format($fittings_interval['fittings_min']) . "-" . number_format($fittings_interval['fittings_max'], 2, '.', '');
                    if ($fittings_interval['save_minPrice'] == $fittings_interval['save_maxPrice']) {
                        $result['save_minMaxPrice'] = price_format($fittings_interval['save_minPrice']);
                    } else {
                        $result['save_minMaxPrice'] = price_format($fittings_interval['save_minPrice']) . "-" . number_format($fittings_interval['save_maxPrice'], 2, '.', '');
                    }
                }

                if ($fittings_interval['return_attr'] < 1) { //配件商品没有属性时
                    $result['market_minMax'] = price_format($fittings_interval['all_market_price']);
                } else {
                    $result['market_minMax'] = price_format($fittings_interval['market_min']) . "-" . number_format($fittings_interval['market_max'], 2, '.', '');
                }
            }
        } else {
            $result['fittings_minMax'] = price_format($fittings_interval['fittings_min']) . "-" . number_format($fittings_interval['fittings_max'], 2, '.', '');

            if ($fittings_interval['save_minPrice'] == $fittings_interval['save_maxPrice']) {
                $result['save_minMaxPrice'] = price_format($fittings_interval['save_minPrice']);
            } else {
                $result['save_minMaxPrice'] = price_format($fittings_interval['save_minPrice']) . "-" . number_format($fittings_interval['save_maxPrice'], 2, '.', '');
            }

            $result['market_minMax'] = price_format($fittings_interval['market_min']) . "-" . number_format($fittings_interval['market_max'], 2, '.', '');
        }
    }

    $result['list_select'] = $list_select;
    $result['null_money'] = price_format(0);
    $result['collocation_number'] = $fittings_interval['collocation_number'];

    if ($combo_goods['combo_number'] < 1) {
        $fittings = array();
    }
    $result['spe_conut'] = 0;
    if($fittings){
        foreach($fittings as $k=>$v){
            if($v['properties']['spe']){
                $result['spe_conut']++;
            }
        }
    }

    $smarty->assign('fittings', $fittings); // 配件
    $smarty->assign('fittings_minMax', $result['fittings_minMax']); // 搭配区间价
    $smarty->assign('market_minMax', $result['market_minMax']); // 参考区间价
    $smarty->assign('save_minMaxPrice', $result['save_minMaxPrice']); // 节省区间价
    $smarty->assign('collocation_number', $result['collocation_number']); // 已搭配

    $sql = "SELECT group_number FROM " . $GLOBALS['ecs']->table('goods') . " WHERE goods_id = '$goods_id'";
    $group_number = $GLOBALS['db']->getOne($sql, true);
    $smarty->assign('group_number', $group_number); // 搭配区间价
    $smarty->assign('null_money', price_format(0));

    $smarty->assign('goods_id', $goods_id);

    $result['content'] = $smarty->fetch("library/goods_fittings_result.lbi");
    $result['content_type'] = $smarty->fetch("library/goods_fittings_result_type.lbi");

    die($json->encode($result));
}

//获取组合购买商品选择属性
if ($_REQUEST['step'] == 'add_cart_combo_goodsAttr') {
    include_once('includes/cls_json.php');
    $_POST['group'] = strip_tags(urldecode($_POST['group']));
    $_POST['group'] = json_str_iconv($_POST['group']);

    $result = array('error' => 0, 'message' => '', 'content' => '', 'goods_id' => '', 'goods_amount' => 0);
    $json = new JSON;

    if (empty($_POST['group'])) {
        $result['error'] = 1;
        die($json->encode($result));
    }

    $group = $json->decode($_POST['group']);
    $goodsRow = explode('_', $group->group_rev);

    $goodSEqual = $group->fitt_goods;
    $type = $group->type;
    $tImg = $group->tImg;
    $attr_id = $group->attr;
    $number = 1;
    $goods_id = $group->goods_id;
    $fittings_goods = $group->fittings_goods;  //配件主商品ID
    $fittings_attr = $group->fittings_attr;  //配件主商品属性组ID
    $warehouse_id = $goodsRow[4];
    $area_id = $goodsRow[5];
    $group_id = $goodsRow[0] . "_" . $goodsRow[1] . "_" . $goodsRow[2] . "_" . $goodsRow[3];

    $goods = get_goods_info($goods_id, $warehouse_id, $area_id);

    if ($goods_id == 0) {
        $result['message'] = $_LANG['err_change_attr'];
        $result['error'] = 1;
    } else {
        if ($number == 0) {
            $result['qty'] = $number = 1;
        } else {
            $result['qty'] = $number;
        }

        $group_attr = implode('|', $group->attr);
        $products = get_warehouse_id_attr_number($goods_id, $group_attr, $goods['user_id'], $warehouse_id, $area_id);
        $attr_number = $products['product_number'];

        if ($goods['model_attr'] == 1) {
            $table_products = "products_warehouse";
            $type_files = " and warehouse_id = '$warehouse_id'";
        } elseif ($goods['model_attr'] == 2) {
            $table_products = "products_area";
            $type_files = " and area_id = '$area_id'";
        } else {
            $table_products = "products";
            $type_files = "";
        }

        $sql = "SELECT * FROM " . $GLOBALS['ecs']->table($table_products) . " WHERE goods_id = '$goods_id'" . $type_files . " LIMIT 0, 1";
        $prod = $GLOBALS['db']->getRow($sql);

        if (empty($prod)) { //当商品没有属性库存时
            $attr_number = $goods['goods_number'];
        }

        $attr_number = !empty($attr_number) ? $attr_number : 0;
        $result['attr_number'] = $attr_number;

        if($GLOBALS['_CFG']['add_shop_price'] == 1){
            $add_tocart = 1;
        }else{
            $add_tocart = 0;
        }

        $shop_price = get_final_price($goods_id, $number, true, $attr_id, $warehouse_id, $area_id, 0, 0, $add_tocart);

        $prod_attr = array();
        if (!empty($prod['goods_attr'])) {
            $prod_attr = explode('|', $prod['goods_attr']);
        }

        if (count($prod_attr) <= 1) {
            if (empty($result['attr_number'])) {
                $result['message'] = $_LANG['Stock_goods_null'];
            }
        } elseif (count($prod_attr) > 1) {
            if (count($prod_attr) == count($attr_id)) {
                if (empty($result['attr_number'])) {
                    $result['message'] = $_LANG['Stock_goods_null'];
                }
            } else {
                unset($result['attr_number']);
            }
        }

        if (is_spec($prod_attr) && !empty($prod)) {
            $product_info = get_products_info($goods_id, $prod_attr, $warehouse_id, $area_id);
        }

        $warehouse_area = array(
            'warehouse_id' => $warehouse_id,
            'area_id' => $area_id,
        );

        $spec_price = spec_price($attr_id, $goods_id, $warehouse_area);
        $goods_attr = get_goods_attr_info($attr_id, 'pice', $warehouse_id, $area_id);

        $parent = array(
            'goods_attr_id' => implode(',', $attr_id),
            'product_id' => $product_info['product_id'],
            'goods_attr' => addslashes($goods_attr)
        );

        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('cart_combo'), $parent, 'UPDATE', "group_id = '$group_id' AND goods_id = '$goods_id' AND " . $sess_id);

        if ($type == 1) {
            $goods_price = $shop_price;
        } else {
            $sql = "select goods_price from " . $GLOBALS['ecs']->table('group_goods') . " where parent_id = '" . $goodsRow[3] . "' and goods_id = '$goods_id' and group_id = '" . $goodsRow[2] . "'";
            $goods_price = $GLOBALS['db']->getOne($sql);

            if($GLOBALS['_CFG']['add_shop_price'] == 1){
                $goods_price = $goods_price + $spec_price;
            }
        }

        $img_flie = '';
        if (!empty($tImg)) {
            $img_flie = ", img_flie = '$tImg'";
        }

        $sql = "update " . $GLOBALS['ecs']->table('cart_combo') . " set goods_price = '$goods_price' " . $img_flie .
                " where group_id = '$group_id' AND goods_id = '$goods_id' AND " . $sess_id;
        $GLOBALS['db']->query($sql);

        $result['goods_id'] = $goods_id;
        $result['shop_price'] = price_format($shop_price);
        $result['market_price'] = $goods['market_price'];
        $result['result'] = price_format($shop_price * $number);
        $result['groupId'] = $goodsRow[2];

        //商品判断属性是否选完
        $attr_type_list = get_goods_attr_type_list($goods_id, 1);
        if ($attr_type_list == count($attr_id)) {
            $result['attr_equal'] = 1;
        } else {
            $result['attr_equal'] = 0;
        }

        $goods_info = get_goods_fittings_info($goodsRow[3], $warehouse_id, $area_id, $group_id, 0, $fittings_goods, $fittings_attr);
        $fittings = get_goods_fittings(array($goodsRow[3]), $warehouse_id, $area_id, $group_id, 1, $goodSEqual);

        $fittings = array_merge($goods_info, $fittings);
        $fittings = array_values($fittings);

        $fittings_interval = get_choose_goods_combo_cart($fittings);

        if ($fittings_interval['return_attr'] < 1) { //配件商品没有属性时
            $result['amount'] = !empty($fittings_interval['all_price_ori']) ? $fittings_interval['all_price_ori'] : 0;
            $result['goods_amount'] = !empty($fittings_interval['all_price_ori']) ? price_format($fittings_interval['all_price_ori']) : 0;
        } else {
            $result['amount'] = !empty($fittings_interval['fittings_price']) ? $fittings_interval['fittings_price'] : 0;
            $result['goods_amount'] = !empty($fittings_interval['fittings_price']) ? price_format($fittings_interval['fittings_price']) : 0;
        }
        $result['goods_market_amount'] = !empty($fittings_interval['all_market_price']) ? price_format($fittings_interval['all_market_price']) : 0;
        $result['save_amount'] = price_format($fittings_interval['save_price_amount']);
    }

    //判断商品是否有属性，并且已经全选
    $list_select = get_combo_goods_list_select(0, $goodsRow[3], $group_id);
    $result['list_select'] = $list_select;

    die($json->encode($result));
}

//获取组合购买商品选择数据列表
elseif ($_REQUEST['step'] == 'add_del_cart_combo_list') //by zhuo
{
    include_once('includes/cls_json.php');
    $_POST['group']=strip_tags(urldecode($_POST['group']));
    $_POST['group'] = json_str_iconv($_POST['group']);

    $result = array('error' => 0, 'message' => '', 'content' => '', 'goods_id' => '');
    $json  = new JSON;

    if (empty($_POST['group']))
    {
        $result['error'] = 1;
        die($json->encode($result));
    }

    $group = $json->decode($_POST['group']);
	$goodsRow = explode('|', $group->group_rev);
	$goods_id = $goodsRow[0];
	$group_id = str_replace('=', '_', $goodsRow[3]);

	$goodsRow2 = explode('=', $goodsRow[3]);
	$parent_id = $goodsRow2[1];

	$goodSEqual = $group->fitt_goods;

	$sql = "delete from " .$GLOBALS['ecs']->table('cart_combo'). " where goods_id = '$goods_id' and group_id = '$group_id' and " . $sess_id;
	$GLOBALS['db']->query($sql);

	$sql = "select count(*) from " .$GLOBALS['ecs']->table('cart_combo'). " where " .$sess_id. " and parent_id = '$parent_id' AND group_id = '$group_id'";
    $rec_count = $GLOBALS['db']->getOne($sql);

    if($rec_count < 1){
        //更新临时进货单（删除主件）
        $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart_combo') . " WHERE ".$sess_id. " AND goods_id = '$parent_id' AND parent_id = 0  AND group_id = '$group_id'";
        $GLOBALS['db']->query($sql);

		$result['fitt_goods'] = '';
    }else{
		$arr = array();
		foreach($goodSEqual as $key=>$row){
			if($row != $goods_id){
				$arr[$key] = $row;
			}
		}
	}

	$result['fitt_goods'] = $arr;
	$result['add_group'] = $group->add_group;

	die($json->encode($result));
}
//ecmoban模板堂 --zhuo 组合购买 end

elseif ($_REQUEST['step'] == 'link_buy')
{
    $goods_id = intval($_GET['goods_id']);

    if (!cart_goods_exists($goods_id,array()))
    {
        addto_cart($goods_id);
    }
    ecs_header("Location:./flow.php\n");
    exit;
}
elseif ($_REQUEST['step'] == 'login')
{
    include_once('languages/'. $_CFG['lang']. '/user.php');

	//第三方登录判断
    if($_SESSION['user_id'] > 0){
        ecs_header("Location:./flow.php?step=consignee\n");
        exit;
    }

    /*
     * 用户登录注册
     */
    if ($_SERVER['REQUEST_METHOD'] == 'GET')
    {
        $smarty->assign('anonymous_buy', $_CFG['anonymous_buy']);

        /* 检查是否有赠品，如果有提示登录后重新选择赠品 */
        $sql = "SELECT COUNT(*) FROM " . $ecs->table('cart') .
                " WHERE " .$sess_id. " AND is_gift > 0";
        if ($db->getOne($sql) > 0)
        {
            $smarty->assign('need_rechoose_gift', 1);
        }

        /* 检查是否需要注册码 */
        $captcha = intval($_CFG['captcha']);
        if (($captcha & CAPTCHA_LOGIN) && (!($captcha & CAPTCHA_LOGIN_FAIL) || (($captcha & CAPTCHA_LOGIN_FAIL) && $_SESSION['login_fail'] > 2)) && gd_version() > 0)
        {
            $smarty->assign('enabled_login_captcha', 1);
            $smarty->assign('rand', mt_rand());
        }
        if ($captcha & CAPTCHA_REGISTER)
        {
            $smarty->assign('enabled_register_captcha', 1);
            $smarty->assign('rand', mt_rand());
        }
    }
    else
    {
        include_once('includes/lib_passport.php');
        if (!empty($_POST['act']) && $_POST['act'] == 'signin')
        {
            $captcha = intval($_CFG['captcha']);
            if (($captcha & CAPTCHA_LOGIN) && (!($captcha & CAPTCHA_LOGIN_FAIL) || (($captcha & CAPTCHA_LOGIN_FAIL) && $_SESSION['login_fail'] > 2)) && gd_version() > 0)
            {
                if (empty($_POST['captcha']))
                {
                    show_message($_LANG['invalid_captcha']);
                }

                /* 检查验证码 */
                include_once('includes/cls_captcha.php');

                $validator = new captcha();
                $validator->session_word = 'captcha_login';
                if (!$validator->check_word($_POST['captcha']))
                {
                    show_message($_LANG['invalid_captcha']);
                }
            }

            if ($user->login($_POST['username'], $_POST['password'],isset($_POST['remember'])))
            {
                update_user_info();  //更新用户信息
                recalculate_price(); // 重新计算进货单中的商品价格

                if(!empty($_SESSION['user_id'])){
                        $login_sess = " user_id = '" . $_SESSION['user_id'] . "' ";
                }else{
                        $login_sess = " session_id = '" . real_cart_mac_ip() . "' ";
                }

                /* 检查进货单中是否有商品 没有商品则跳转到首页 */
                $sql = "SELECT COUNT(*) FROM " . $ecs->table('cart') . " WHERE " .$login_sess;
                if ($db->getOne($sql) > 0)
                {
                    ecs_header("Location: flow.php\n");
                }
                else
                {
                    ecs_header("Location:index.php\n");
                }

                exit;
            }
            else
            {
                $_SESSION['login_fail']++;
                show_message($_LANG['signin_failed'], '', 'user.php');
            }
        }
        elseif (!empty($_POST['act']) && $_POST['act'] == 'signup')
        {
            if ((intval($_CFG['captcha']) & CAPTCHA_REGISTER) && gd_version() > 0)
            {
                if (empty($_POST['captcha']))
                {
                    show_message($_LANG['invalid_captcha']);
                }

                /* 检查验证码 */
                include_once('includes/cls_captcha.php');

                $validator = new captcha();
                if (!$validator->check_word($_POST['captcha']))
                {
                    show_message($_LANG['invalid_captcha']);
                }
            }

            if (register(trim($_POST['username']), trim($_POST['password']), trim($_POST['email'])))
            {
                /* 用户注册成功 */
                ecs_header("Location: flow.php?step=consignee\n");
                exit;
            }
            else
            {
                $err->show();
            }
        }
        else
        {
            // TODO: 非法访问的处理
        }
    }
}
elseif ($_REQUEST['step'] == 'consignee')
{
    /*------------------------------------------------------ */
    //-- 收货人信息
    /*------------------------------------------------------ */
    include_once('includes/lib_transaction.php');

    if ($_SERVER['REQUEST_METHOD'] == 'GET')
    {
		require(ROOT_PATH . '/includes/lib_area.php');  //ecmoban模板堂 --zhuo

		/*
         * 收货人信息填写界面
         */

        if (isset($_REQUEST['direct_shopping']))
        {
            $_SESSION['direct_shopping'] = 1;
        }

        /* 取得购物类型 */
        $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

        /* 取得国家列表、商店所在国家、商店所在国家的省列表 */
        $smarty->assign('country_list',       get_regions());
        $smarty->assign('shop_country',       $_CFG['shop_country']);
        $smarty->assign('shop_province_list', get_regions(1, $_CFG['shop_country']));

        /* 获得用户所有的收货人信息 */
        if ($_SESSION['user_id'] > 0)
        {
            $consignee_list = get_consignee_list($_SESSION['user_id']);

            if (count($consignee_list) < 5)
            {
                /* 如果用户收货人信息的总数小于 5 则增加一个新的收货人信息 */
                $consignee_list[] = array('country' => $_CFG['shop_country'], 'email' => isset($_SESSION['email']) ? $_SESSION['email'] : '');
            }
        }
        else
        {
            if (isset($_SESSION['flow_consignee'])){
                $consignee_list = array($_SESSION['flow_consignee']);
            }
            else
            {
                $consignee_list[] = array(
                    'country' => $_CFG['shop_country'],
                    'province' => $province_id,
                    'city' => $city_id,
                    'district' => $district_id
                );
            }
        }
        $smarty->assign('name_of_region',   array($_CFG['name_of_region_1'], $_CFG['name_of_region_2'], $_CFG['name_of_region_3'], $_CFG['name_of_region_4']));
        $smarty->assign('consignee_list', $consignee_list);

        /* 取得每个收货地址的省市区列表 */
        $province_list = array();
        $city_list = array();
        $district_list = array();
        foreach ($consignee_list as $region_id => $consignee)
        {
            $consignee['country']  = isset($consignee['country'])  ? intval($consignee['country'])  : 1;
            $consignee['province'] = isset($consignee['province']) ? intval($consignee['province']) : $province_id;
            $consignee['city']     = isset($consignee['city'])     ? intval($consignee['city'])     : $city_id;

            $province_list[$region_id] = get_regions(1, $consignee['country']);
            $city_list[$region_id]     = get_regions(2, $consignee['province']);
            $district_list[$region_id] = get_regions(3, $consignee['city']);
        }
        $smarty->assign('province_list', $province_list);
        $smarty->assign('city_list',     $city_list);
        $smarty->assign('district_list', $district_list);

        /* 返回收货人页面代码 */
        $smarty->assign('real_goods_count', exist_real_goods(0, $flow_type) ? 1 : 0);
    }
    else
    {
        /*
         * 保存收货人信息
         */
        $consignee = array(
            'address_id'    => !isset($_POST['address_id']) && empty($_POST['address_id'])          ?    0  :   intval($_POST['address_id']),
            'consignee'     => !isset($_POST['consignee']) && empty($_POST['consignee'])            ?    '' :   compile_str(trim($_POST['consignee'])),
            'country'       => !isset($_POST['country']) && empty($_POST['country'])                ?    0  :   intval($_POST['country']),
            'province'      => !isset($_POST['province']) && empty($_POST['province'])              ?    0  :   intval($_POST['province']),
            'city'          => !isset($_POST['city']) && empty($_POST['city'])                      ?    0  :   intval($_POST['city']),
            'district'      => !isset($_POST['district']) && empty($_POST['district'])              ?    0  :   intval($_POST['district']),
            'street'        => !isset($_POST['street']) && empty($_POST['street'])                  ?    0  :   intval($_POST['street']),
            'email'         => !isset($_POST['email']) && empty($_POST['email'])                    ?    '' :   compile_str($_POST['email']),
            'address'       => !isset($_POST['address']) && empty($_POST['address'])                ?    '' :   compile_str($_POST['address']),
            'zipcode'       => !isset($_POST['zipcode']) && empty($_POST['zipcode'])                ?    '' :   compile_str(make_semiangle(trim($_POST['zipcode']))),
            'tel'           => !isset($_POST['tel']) && empty($_POST['tel'])                        ?    '' :   compile_str(make_semiangle(trim($_POST['tel']))),
            'mobile'        => !isset($_POST['mobile']) && empty($_POST['mobile'])                  ?    '' :   compile_str(make_semiangle(trim($_POST['mobile']))),
            'sign_building' => !isset($_POST['sign_building']) && empty($_POST['sign_building'])    ?    '' :   compile_str($_POST['sign_building']),
            'best_time'     => !isset($_POST['best_time']) && empty($_POST['best_time'])            ?    '' :   compile_str($_POST['best_time']),
        );

        if ($_SESSION['user_id'] > 0)
        {
            include_once(ROOT_PATH . 'includes/lib_transaction.php');

            /* 如果用户已经登录，则保存收货人信息 */
            $consignee['user_id'] = $_SESSION['user_id'];

            save_consignee($consignee, true);
        }

        /* 保存到session */
        $_SESSION['flow_consignee'] = stripslashes_deep($consignee);

        ecs_header("Location: flow.php?step=checkout&direct_shopping=1\n");
        exit;
    }
}
elseif ($_REQUEST['step'] == 'drop_consignee')
{
    /*------------------------------------------------------ */
    //-- 删除收货人信息
    /*------------------------------------------------------ */
    include_once('includes/lib_transaction.php');

    $consignee_id = intval($_GET['id']);

    if (drop_consignee($consignee_id))
    {
        ecs_header("Location: flow.php?step=consignee\n");
        exit;
    }
    else
    {
        show_message($_LANG['not_fount_consignee']);
    }
}
elseif ($_REQUEST['step'] == 'checkout2')
{

    /**
     * 初始化红包、优惠券、储值卡
     */
    unset($_SESSION['flow_order']['bonus_id']);
    unset($_SESSION['flow_order']['uc_id']);
    unset($_SESSION['flow_order']['vc_id']);

    $sc_rand = rand(1000, 9999);
    $sc_guid = sc_guid();

    $account_cookie = MD5($sc_guid . "-" . $sc_rand);
    setcookie('done_cookie', $account_cookie, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

    $smarty->assign('sc_guid', $sc_guid);
    $smarty->assign('sc_rand', $sc_rand);

    //@author-bylu 检测当前用户白条相关权限(是否逾期,逾期不能下单);
    //这里主要是为了防止用户在逾期前进货单中已存在商品,之后逾期通过进货单"结算"入口下单;
    bt_auth_check($stges_qishu=null,$is_jiesuan=true);

    /* 取得购物类型 */
    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    //配送方式--自提点标识
    $_SESSION['merchants_shipping'] = array();
    //ecmoban模板堂 --zhuo
    $direct_shopping = isset($_REQUEST['direct_shopping']) ? $_REQUEST['direct_shopping'] : 0;
    $cart_value = isset($_REQUEST['rec_ids']) ? dsc_addslashes($_REQUEST['rec_ids']) : '';
    $store_seller = isset($_REQUEST['store_seller']) ? dsc_addslashes($_REQUEST['store_seller']) : '';// by kong 20160721 门店标识
    $store_id = isset($_REQUEST['store_id'])  ? intval($_REQUEST['store_id']) : 0;  // by kong 20160721 门店id

    $cart_value = implode(',', $cart_value);

    if(empty($cart_value)){
        $cart_value = get_cart_value($flow_type);
    }else{
        if(count(explode(",", $cart_value)) == 1){
            $cart_value = intval($cart_value);
        }
    }

    $_SESSION['cart_value'] = $cart_value;
    $smarty->assign('cart_value', $cart_value);
    /*------------------------------------------------------ */
    //-- 订单确认
    /*------------------------------------------------------ */

    /* 团购标志 */
    if ($flow_type == CART_GROUP_BUY_GOODS)
    {
        $smarty->assign('is_group_buy', 1);
    }
    /* 积分兑换商品 */
    elseif ($flow_type == CART_EXCHANGE_GOODS)
    {
        //ecmoban模板堂 --zhuo
        $smarty->assign('is_exchange_goods', 1);
    }
    /* 预售商品 */
    elseif($flow_type == CART_PRESALE_GOODS){
        $smarty->assign('is_presale_goods', 1);
    }
    else
    {
        //正常购物流程  清空其他购物流程情况
        $_SESSION['flow_order']['extension_code'] = '';
    }

    /* 检查进货单中是否有商品 */
    $sql = "SELECT COUNT(*) FROM " . $ecs->table('cart') .
        " WHERE " .$sess_id.
        "AND parent_id = 0 AND is_gift = 0";

    if ($db->getOne($sql) == 0)
    {
        show_message($_LANG['no_goods_in_cart'], '', '', 'warning');
    }

    /*
     * 检查用户是否已经登录
     * 如果用户已经登录了则检查是否有默认的收货地址
     * 如果没有登录则跳转到登录和注册页面
     */
    if (empty($direct_shopping) && $_SESSION['user_id'] == 0)
    {
        /* 用户没有登录且没有选定匿名购物，转向到登录页面 */
        ecs_header("Location: user.php\n");
        exit;
    }

    $consignee = get_consignee($_SESSION['user_id']);

    if($consignee){
        setcookie('province', $consignee['province'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('city', $consignee['city'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('district', $consignee['district'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('street', $consignee['street'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('street_area', '', gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

        $flow_warehouse = get_warehouse_goods_region($consignee['province']);
        setcookie('area_region', $flow_warehouse['region_id'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('flow_region', $flow_warehouse['region_id'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
    }

    $region_id = get_province_id_warehouse($consignee['province']);
    $area_info = get_area_info($consignee['province']);

    $smarty->assign('warehouse_id', $region_id);
    $smarty->assign('area_id', $area_info['region_id']);

    //ecmoban模板堂 --zhuo start 审核收货人地址
    $user_address = get_order_user_address_list($_SESSION['user_id']);

    if($direct_shopping != 1 && !empty($_SESSION['user_id'])){
        $_SESSION['browse_trace'] = "flow.php";
    }else{
        $_SESSION['browse_trace'] = "flow.php?step=checkout";
    }

    if(!$user_address && $consignee){
        $consignee['province_name'] = get_goods_region_name($consignee['province']);
        $consignee['city_name'] = get_goods_region_name($consignee['city']);
        $consignee['district_name'] = get_goods_region_name($consignee['district']);
        $consignee['street_name'] = get_goods_region_name($consignee['street']);
        $consignee['region'] = $consignee['province_name'] ."&nbsp;". $consignee['city_name'] ."&nbsp;". $consignee['district_name'] ."&nbsp;". $consignee['street_name'];

        $user_address = array($consignee);
    }

    $smarty->assign('user_address', $user_address);
    $smarty->assign('auditStatus', $_CFG['auditStatus']);

    //有存在虚拟和实体商品 start
    get_goods_flow_type($cart_value);
    //有存在虚拟和实体商品 end

    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $smarty->assign('user_id', $user_id);
    //ecmoban模板堂 --zhuo end 审核收货人地址

    /* 初始化地区ID */
    $consignee['country']       = !isset($consignee['country']) && empty($consignee['country'])                ?    0 :   intval($consignee['country']);
    $consignee['province']      = !isset($consignee['province']) && empty($consignee['province'])              ?    0 :   intval($consignee['province']);
    $consignee['city']          = !isset($consignee['city']) && empty($consignee['city'])                      ?    0 :   intval($consignee['city']);
    $consignee['district']      = !isset($consignee['district']) && empty($consignee['district'])              ?    0 :   intval($consignee['district']);
    $consignee['street']        = !isset($consignee['street']) && empty($consignee['street'])                  ?    0 :   intval($consignee['street']);

    $_SESSION['flow_consignee'] = $consignee;

    $consignee['province_name'] = get_goods_region_name($consignee['province']);
    $consignee['city_name'] = get_goods_region_name($consignee['city']);
    $consignee['district_name'] = get_goods_region_name($consignee['district']);
    $consignee['street_name'] = get_goods_region_name($consignee['street']);
    $consignee['consignee_address'] = $consignee['province_name'] . $consignee['city_name'] . $consignee['district_name'] . $consignee['street_name'] . $consignee['address'];
    $smarty->assign('consignee', $consignee);


    /* 对商品信息赋值 */
    $cart_goods_list = cart_goods($flow_type, $cart_value, 1, $region_id, $area_info['region_id'], $consignee, $store_id); // 取得商品列表，计算合计
    $cart_goods_list_new = cart_by_favourable($cart_goods_list);

    $smarty->assign('goods_list', $cart_goods_list_new);

    $smarty->assign('seckill_id',$_SESSION['extension_id']);//输出秒杀id

    /*获取门店信息  by kong 20160721 start*/
    $smarty->assign('provinces', get_regions(1, 1));
    if($store_id > 0){
        /*获取该商品有货门店*/
        $sql = "SELECT c.goods_id,o.id,o.stores_name,o.stores_address,p.region_name as province,ci.region_name as city ,d.region_name as district "
            . "FROM".$ecs->table("cart")." AS c "
            . "LEFT JOIN ".$ecs->table("offline_store")." AS o ON c.store_id=o.id "
            . "LEFT JOIN".$ecs->table("store_goods")." AS s ON s.store_id=o.id "
            . "LEFT JOIN ".$ecs->table("region")." AS p ON p.region_id = o.province "
            . "LEFT JOIN ".$ecs->table('region')." AS ci ON ci.region_id = o.city "
            . "LEFT JOIN ".$ecs->table('region')." AS d ON d.region_id = o.district WHERE c.rec_id='".$cart_value."' LIMIT 1";
        $seller_store = $db->getRow($sql);
        $smarty->assign("seller_store",$seller_store);
        $sql = "SELECT store_mobile,take_time FROM".$ecs->table("cart")."WHERE rec_id in (".$cart_value.") LIMIT 1";
        $store_info = $db->getRow($sql);
        if(!$store_info['store_mobile']){
            $sql = "SELECT mobile_phone FROM ".$ecs->table('users')." WHERE user_id = '".$_SESSION['user_id']."'";
            $store_info['store_mobile'] = $db->getOne($sql, true);
        }
        if(!$store_info['take_time']){
            $store_info['take_time'] = date("Y-m-d H:i:s",strtotime("+1 day"));
        }
        $now_time = date("Y-m-d H:i:s",gmtime());
        $smarty->assign("now_time",$now_time);
        $smarty->assign('store_info',$store_info);
    }

    $smarty->assign('store_id',$store_id);
    $smarty->assign('cart_value',$cart_value);
    $smarty->assign('store_seller',$store_seller);
    $smarty->assign('is_address',$is_address);
    /*获取门店信息  by kong 20160721 end*/

    $cart_goods_number = get_buy_cart_goods_number($flow_type, $cart_value);
    $smarty->assign('cart_goods_number', $cart_goods_number);

    // 取得商品列表，计算合计
    $cart_goods = get_new_group_cart_goods($cart_goods_list_new);

    /* 对是否允许修改进货单赋值 */
    if ($flow_type != CART_GENERAL_GOODS || $_CFG['one_step_buy'] == '1')
    {
        $smarty->assign('allow_edit_cart', 0);
    }
    else
    {
        $smarty->assign('allow_edit_cart', 1);
    }

    /*
     * 取得购物流程设置
     */
    $smarty->assign('config', $_CFG);

    /*
     * 取得订单信息
     */
    $order = flow_order_info();

    $smarty->assign('order', $order);

    /* 如果能开发票，取得发票内容列表 */
    if ((!isset($_CFG['can_invoice']) || $_CFG['can_invoice'] == '1')
        && isset($_CFG['invoice_content'])
        && trim($_CFG['invoice_content']) != '' && $flow_type != CART_EXCHANGE_GOODS)
    {
        $inv_content_list = explode("\n", str_replace("\r", '', $_CFG['invoice_content']));
        $smarty->assign('inv_content', $inv_content_list[0]);
        //默认发票计算
        $order['need_inv']    = 1;
        $order['inv_type']    = $_CFG['invoice_type']['type'][0];
        $order['inv_payee']   = '个人';
        $order['inv_content'] = $inv_content_list[0];
    }

    /* 计算折扣 */
    if ($flow_type != CART_EXCHANGE_GOODS && $flow_type != CART_GROUP_BUY_GOODS)
    {
        $discount = compute_discount(3, $cart_value);
        $smarty->assign('discount', $discount['discount']);
        $favour_name = empty($discount['name']) ? '' : join(',', $discount['name']);
        $smarty->assign('your_discount', sprintf($_LANG['your_discount'], $favour_name, price_format($discount['discount'])));
    }

    if(!$user_address){
        $consignee = array(
            'province' => 0,
            'city' => 0
        );
        // 取得国家列表、商店所在国家、商店所在国家的省列表
        $smarty->assign('country_list',       get_regions());
        $smarty->assign('please_select',       $_LANG['please_select']);

        $province_list = get_regions_log(1,1);
        $city_list     = get_regions_log(2, $consignee['province']);
        $district_list = get_regions_log(3, $consignee['city']);

        $smarty->assign('province_list', $province_list);
        $smarty->assign('city_list',     $city_list);
        $smarty->assign('district_list', $district_list);
        $smarty->assign('consignee', $consignee);
    }

    /*
     * 计算订单的费用
     */
    $total = order_fee($order, $cart_goods, $consignee, 0, $cart_value, 0, $cart_goods_list,0,0,$store_id,$store_seller);

    $smarty->assign('total', $total);
    $smarty->assign('shopping_money', sprintf($_LANG['shopping_money'], $total['formated_goods_price']));
    $smarty->assign('market_price_desc', sprintf($_LANG['than_market_price'], $total['formated_market_price'], $total['formated_saving'], $total['save_rate']));

    /* 取得支付列表 */
    if ($order['shipping_id'] == 0)
    {
        $cod        = true;
        $cod_fee    = 0;
    }
    else
    {
        $shipping = shipping_info($order['shipping_id']);
        $cod = $shipping['support_cod'];

        if ($cod)
        {
            /* 如果是团购，且保证金大于0，不能使用货到付款 */
            if ($flow_type == CART_GROUP_BUY_GOODS)
            {
                $group_buy_id = $_SESSION['extension_id'];
                if ($group_buy_id <= 0)
                {
                    show_message('error group_buy_id');
                }
                $group_buy = group_buy_info($group_buy_id);
                if (empty($group_buy))
                {
                    show_message('group buy not exists: ' . $group_buy_id);
                }

                if ($group_buy['deposit'] > 0)
                {
                    $cod = false;
                    $cod_fee = 0;

                    /* 赋值保证金 */
                    $smarty->assign('gb_deposit', $group_buy['deposit']);
                }
            }

            if ($cod)
            {
                $shipping_area_info = shipping_info($order['shipping_id']);
                $cod_fee            = isset($shipping_area_info['pay_fee']) ? $shipping_area_info['pay_fee'] : 0;
            }
        }
        else
        {
            $cod_fee = 0;
        }
    }

    // 给货到付款的手续费加<span id>，以便改变配送的时候动态显示
    $payment_list = available_payment_list(1, $cod_fee);

    if(isset($payment_list))
    {
        foreach ($payment_list as $key => $payment)
        {
            //ecmoban模板堂 --will start
            //pc端去除ecjia的支付方式
            if (substr($payment['pay_code'], 0 , 4) == 'pay_') {
                unset($payment_list[$key]);
                continue;
            }
            //ecmoban模板堂 --will end

            if ($payment['is_cod'] == '1')
            {
                $payment_list[$key]['format_pay_fee'] = '<span id="ECS_CODFEE">' . $payment['format_pay_fee'] . '</span>';
            }
            /* 如果有易宝神州行支付 如果订单金额大于300 则不显示 */
            if ($payment['pay_code'] == 'yeepayszx' && $total['amount'] > 300)
            {
                unset($payment_list[$key]);
            }

            if ($payment['pay_code'] == 'alipay_wap') {
                unset($payment_list[$key]);
            }

            /* 如果有余额支付 */
            if ($payment['pay_code'] == 'balance')
            {
                /* 如果未登录，不显示 */
                if ($_SESSION['user_id'] == 0)
                {
                    unset($payment_list[$key]);
                }
                else
                {
                    if ($_SESSION['flow_order']['pay_id'] == $payment['pay_id'])
                    {
                        $smarty->assign('disable_surplus', 1);
                    }
                }
            }
        }
    }

    //@模板堂-bylu 过滤掉在线支付的方法(余额支付,支付宝等等),因为订单结算页只允许显示一个在线支付按钮 start
    foreach ($payment_list as $k => $v) {
        if ($v['is_online'] == 1) {
            unset($payment_list[$k]);
        }
    }

    //@模板堂-bylu  end
    $smarty->assign('payment_list', $payment_list);

    /* 取得包装与贺卡 */
    if ($total['real_goods_count'] > 0)
    {
        /* 只有有实体商品,才要判断包装和贺卡 */
        if (!isset($_CFG['use_package']) || $_CFG['use_package'] == '1')
        {
            /* 如果使用包装，取得包装列表及用户选择的包装 */
            $smarty->assign('pack_list', pack_list());
        }

        /* 如果使用贺卡，取得贺卡列表及用户选择的贺卡 */
        if (!isset($_CFG['use_card']) || $_CFG['use_card'] == '1')
        {
            $smarty->assign('card_list', card_list());
        }
    }

    $user_info = user_info($_SESSION['user_id']);

    // 如果开启在线支付  qin
    $sql_pay = "SELECT pay_online, ec_salt, pay_password, user_surplus FROM ".$ecs->table('users_paypwd')." WHERE user_id = '" .$_SESSION['user_id']. "' LIMIT 1";
    $pay_online = $db->getRow($sql_pay);
    if($pay_online['pay_online'] || ($pay_online['user_surplus'] && $user_info['user_money'] > 0)) { //安装了"在线支付",才显示余额支付输入框 bylu;
        $smarty->assign('open_pay_password', 1);
        $smarty->assign('pay_pwd_error',       1);
    }

    /* 如果使用余额，取得用户余额 */
    if ((!isset($_CFG['use_surplus']) || $_CFG['use_surplus'] == '1')
        && $_SESSION['user_id'] > 0
        && $user_info['user_money'] > 0)
    {
        if($db->getOne("SELECT enabled FROM ".$ecs->table('payment')." WHERE pay_code = 'balance'")) { // 安装了"余额支付",才显示余额支付输入框 bylu;
            // 能使用余额
            $smarty->assign('allow_use_surplus', 1);
            $smarty->assign('your_surplus', $user_info['user_money']);
        }
    }

    /* 如果使用积分，取得用户可用积分及本订单最多可以使用的积分 */
    if ((!isset($_CFG['use_integral']) || $_CFG['use_integral'] == '1')
        && $_SESSION['user_id'] > 0
        && $user_info['pay_points'] > 0
        && ($flow_type != CART_GROUP_BUY_GOODS && $flow_type != CART_EXCHANGE_GOODS))
    {
        // 能使用积分
        $smarty->assign('allow_use_integral', 1);
        $smarty->assign('order_max_integral', flow_available_points($cart_value, $region_id, $area_id));  // 可用积分  by kong  改
        $smarty->assign('your_integral',      $user_info['pay_points']); // 用户积分
    }

    $cart_ru_id = '';
    if($cart_value){
        $cart_ru_id = get_cart_seller($cart_value);
    }

    /* 如果使用红包，取得用户可以使用的红包及用户选择的红包 */
    if ((!isset($_CFG['use_bonus']) || $_CFG['use_bonus'] == '1')
        && ($flow_type != CART_GROUP_BUY_GOODS && $flow_type != CART_EXCHANGE_GOODS))
    {
        // 取得用户可用红包
        $user_bonus = user_bonus($_SESSION['user_id'], $total['goods_price'], $cart_value, $total['seller_amount'], $cart_ru_id);

        if (!empty($user_bonus)) {
            foreach ($user_bonus AS $key => $val) {
                $user_bonus[$key]['bonus_money_formated'] = price_format($val['type_money'], false);
                if (defined('THEME_EXTENSION')) {
                    $bonus_ids[] = $val['bonus_id'];
                    $user_bonus[$key]['use_end_date'] = local_date('Y-m-d', $val['use_end_date']);
                }
            }

            $smarty->assign('bonus_list', $user_bonus);
        }

        if (defined('THEME_EXTENSION')) {
            $bonus = get_user_bouns_new_list($_SESSION['user_id'], 1, 0, 'bouns_available_gotoPage', 0, 20, $cart_ru_id); //获取用户全部的红包列表，显示20
            //获取不能使用的红包数组
            if (!empty($bonus['available_list'])) {
                foreach ($bonus['available_list'] as $k => $v) {
                    foreach($user_bonus as $bk => $br){
                        if($br['bonus_id'] == $v['bonus_id']){
                            unset($bonus['available_list'][$k]);
                            continue;
                        }
                    }
                }

                $no_bonuslist = !empty($bonus['available_list']) ? $bonus['available_list'] : array();
                $smarty->assign('no_bonuslist', $no_bonuslist);
            }
        }

        // 能使用红包
        $smarty->assign('allow_use_bonus', 1);
    }

    /* 储值卡 begin */
    if (($_CFG['use_value_card'] == '1') && $flow_type != CART_EXCHANGE_GOODS)
    {
        // 取得用户可用储值卡
        $value_card = get_user_value_card($_SESSION['user_id'], $cart_goods, $cart_value);

        if (!empty($value_card))
        {
            foreach ($value_card AS $key => $val)
            {
                $value_card[$key]['card_money_formated'] = price_format($val['card_money'], false);
            }

            $smarty->assign('value_card_list', $value_card);
        }

        if ($value_card && isset($value_card['is_value_cart'])) {
            $value_card = array();
            $smarty->assign('is_value_cart', 0);
        } else {
            $smarty->assign('is_value_cart', 1);
        }

        // 能使用储值卡
        $smarty->assign('allow_use_value_card', 1);
    }else{
        $smarty->assign('allow_use_value_card', 0);
    }

    /*  @author-bylu 优惠券 start  */
    if ($_CFG['use_coupons'] == 1 && $flow_type == CART_GENERAL_GOODS)
    {
        // 取得用户可用优惠券
        $user_coupons = get_user_coupons_list($_SESSION['user_id'], true, $total['goods_price'], $cart_goods, true, $cart_ru_id);

        if (defined('THEME_EXTENSION')) {
            $coupons_list = get_user_coupons_list($_SESSION['user_id'], true, '', false, true, $cart_ru_id, 'cart'); //获得当前登陆用户所有的优惠券

            //获取不能使用的优惠券数组
            if (!empty($coupons_list)) {
                foreach ($coupons_list as $k => $v) {
                    $coupons_list[$k]['cou_type_name'] = $v['cou_type'];
                    $coupons_list[$k]['cou_end_time'] = local_date('Y-m-d', $v['cou_end_time']);
                    $coupons_list[$k]['cou_type'] = $v['cou_type'] == 3 ? $_LANG['lang_goods_coupons']['all_pay'] : ($v['cou_type'] == 4 ? $_LANG['lang_goods_coupons']['user_pay'] : ($v['cou_type'] == 2 ? $_LANG['lang_goods_coupons']['goods_pay'] : ($v['cou_type'] == 1 ? $_LANG['lang_goods_coupons']['reg_pay'] : $_LANG['lang_goods_coupons']['not_pay'])));

                    if ($v['spec_cat']) {
                        $coupons_list[$k]['cou_goods_name'] = $_LANG['lang_goods_coupons']['is_cate'];
                    } elseif ($v['cou_goods']) {
                        $coupons_list[$k]['cou_goods_name'] = $_LANG['lang_goods_coupons']['is_goods'];
                    } else {
                        $coupons_list[$k]['cou_goods_name'] = $_LANG['lang_goods_coupons']['is_all'];
                    }

                    if (!empty($user_coupons)) {
                        foreach ($user_coupons AS $uk => $ur) {
                            if ($v['cou_id'] == $ur['cou_id']) {
                                unset($coupons_list[$k]);
                                continue;
                            }
                        }
                    }
                }
            }
            //没有满足条件的优惠券数组
            $smarty->assign('coupons_list', $coupons_list);
        }

        foreach ($user_coupons as $k => $v) {
            $user_coupons[$k]['cou_type_name'] = $v['cou_type'];
            $user_coupons[$k]['cou_end_time'] = local_date('Y-m-d', $v['cou_end_time']);
            $user_coupons[$k]['cou_type'] = $v['cou_type'] == 3 ? $_LANG['lang_goods_coupons']['all_pay'] : ($v['cou_type'] == 4 ? $_LANG['lang_goods_coupons']['user_pay'] : ($v['cou_type'] == 2 ? $_LANG['lang_goods_coupons']['goods_pay'] : ($v['cou_type'] == 1 ? $_LANG['lang_goods_coupons']['reg_pay'] : $_LANG['lang_goods_coupons']['not_pay'])));
            $user_coupons[$k]['cou_goods_name'] = $v['cou_goods'] ? $_LANG['lang_goods_coupons']['is_goods'] : $_LANG['lang_goods_coupons']['is_all'];
            if ($v['spec_cat']) {
                $user_coupons[$k]['cou_goods_name'] = $_LANG['lang_goods_coupons']['is_cate'];
            } elseif ($v['cou_goods']) {
                $user_coupons[$k]['cou_goods_name'] = $_LANG['lang_goods_coupons']['is_goods'];
            } else {
                $user_coupons[$k]['cou_goods_name'] = $_LANG['lang_goods_coupons']['is_all'];
            }
        }
        //优惠券列表
        $smarty->assign('user_coupons', $user_coupons);
    }
    /*  @author-bylu  end  */

    /* 如果使用缺货处理，取得缺货处理列表 */
    if (!isset($_CFG['use_how_oos']) || $_CFG['use_how_oos'] == '1')
    {
        if (is_array($GLOBALS['_LANG']['oos']) && !empty($GLOBALS['_LANG']['oos']))
        {
            $smarty->assign('how_oos_list', $GLOBALS['_LANG']['oos']);
        }
    }

    /* 保存 session */
    $_SESSION['flow_order'] = $order;

}
elseif ($_REQUEST['step'] == 'checkout')
{

    /**
     * 初始化红包、优惠券、储值卡
     */
    unset($_SESSION['flow_order']['bonus_id']);
    unset($_SESSION['flow_order']['uc_id']);
    unset($_SESSION['flow_order']['vc_id']);

    $sc_rand = rand(1000, 9999);
    $sc_guid = sc_guid();

    $account_cookie = MD5($sc_guid . "-" . $sc_rand);
    setcookie('done_cookie', $account_cookie, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

    $smarty->assign('sc_guid', $sc_guid);
    $smarty->assign('sc_rand', $sc_rand);

    //@author-bylu 检测当前用户白条相关权限(是否逾期,逾期不能下单);
    //这里主要是为了防止用户在逾期前进货单中已存在商品,之后逾期通过进货单"结算"入口下单;
    bt_auth_check($stges_qishu=null,$is_jiesuan=true);

    /* 取得购物类型 */
    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    //配送方式--自提点标识
    $_SESSION['merchants_shipping'] = array();
    //ecmoban模板堂 --zhuo
    $direct_shopping = isset($_REQUEST['direct_shopping']) ? $_REQUEST['direct_shopping'] : 0;
    $cart_value = isset($_REQUEST['cart_value']) ? dsc_addslashes($_REQUEST['cart_value']) : '';
    $store_seller = isset($_REQUEST['store_seller']) ? dsc_addslashes($_REQUEST['store_seller']) : '';// by kong 20160721 门店标识
    $store_id = isset($_REQUEST['store_id'])  ? intval($_REQUEST['store_id']) : 0;  // by kong 20160721 门店id

    if(empty($cart_value)){
        $cart_value = get_cart_value($flow_type);
    }else{
        if(count(explode(",", $cart_value)) == 1){
            $cart_value = intval($cart_value);
        }
    }

    $_SESSION['cart_value'] = $cart_value;
    $smarty->assign('cart_value', $cart_value);
    /*------------------------------------------------------ */
    //-- 订单确认
    /*------------------------------------------------------ */

    /* 团购标志 */
    if ($flow_type == CART_GROUP_BUY_GOODS)
    {
        $smarty->assign('is_group_buy', 1);
    }
    /* 积分兑换商品 */
    elseif ($flow_type == CART_EXCHANGE_GOODS)
    {
        //ecmoban模板堂 --zhuo
        $smarty->assign('is_exchange_goods', 1);
    }
    /* 预售商品 */
    elseif($flow_type == CART_PRESALE_GOODS){
        $smarty->assign('is_presale_goods', 1);
    }
    else
    {
        //正常购物流程  清空其他购物流程情况
        $_SESSION['flow_order']['extension_code'] = '';
    }

    /* 检查进货单中是否有商品 */
    $sql = "SELECT COUNT(*) FROM " . $ecs->table('cart') .
        " WHERE " .$sess_id.
        "AND parent_id = 0 AND is_gift = 0 AND rec_type = '$flow_type'";

    if ($db->getOne($sql) == 0)
    {
        show_message($_LANG['no_goods_in_cart'], '', '', 'warning');
    }

    /*
     * 检查用户是否已经登录
     * 如果用户已经登录了则检查是否有默认的收货地址
     * 如果没有登录则跳转到登录和注册页面
     */
    if (empty($direct_shopping) && $_SESSION['user_id'] == 0)
    {
        /* 用户没有登录且没有选定匿名购物，转向到登录页面 */
        ecs_header("Location: user.php\n");
        exit;
    }

    $consignee = get_consignee($_SESSION['user_id']);

    if($consignee){
        setcookie('province', $consignee['province'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('city', $consignee['city'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('district', $consignee['district'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('street', $consignee['street'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('street_area', '', gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

        $flow_warehouse = get_warehouse_goods_region($consignee['province']);
        setcookie('area_region', $flow_warehouse['region_id'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('flow_region', $flow_warehouse['region_id'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
    }

    $region_id = get_province_id_warehouse($consignee['province']);
    $area_info = get_area_info($consignee['province']);

    $smarty->assign('warehouse_id', $region_id);
    $smarty->assign('area_id', $area_info['region_id']);

    //ecmoban模板堂 --zhuo start 审核收货人地址
    $user_address = get_order_user_address_list($_SESSION['user_id']);

    if($direct_shopping != 1 && !empty($_SESSION['user_id'])){
        $_SESSION['browse_trace'] = "flow.php";
    }else{
        $_SESSION['browse_trace'] = "flow.php?step=checkout";
    }

    if(!$user_address && $consignee){
        $consignee['province_name'] = get_goods_region_name($consignee['province']);
        $consignee['city_name'] = get_goods_region_name($consignee['city']);
        $consignee['district_name'] = get_goods_region_name($consignee['district']);
        $consignee['street_name'] = get_goods_region_name($consignee['street']);
        $consignee['region'] = $consignee['province_name'] ."&nbsp;". $consignee['city_name'] ."&nbsp;". $consignee['district_name'] ."&nbsp;". $consignee['street_name'];

        $user_address = array($consignee);
    }

    $smarty->assign('user_address', $user_address);
    $smarty->assign('auditStatus', $_CFG['auditStatus']);

    //有存在虚拟和实体商品 start
    get_goods_flow_type($cart_value);
    //有存在虚拟和实体商品 end

    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $smarty->assign('user_id', $user_id);
    //ecmoban模板堂 --zhuo end 审核收货人地址

    /* 初始化地区ID */
    $consignee['country']       = !isset($consignee['country']) && empty($consignee['country'])                ?    0 :   intval($consignee['country']);
    $consignee['province']      = !isset($consignee['province']) && empty($consignee['province'])              ?    0 :   intval($consignee['province']);
    $consignee['city']          = !isset($consignee['city']) && empty($consignee['city'])                      ?    0 :   intval($consignee['city']);
    $consignee['district']      = !isset($consignee['district']) && empty($consignee['district'])              ?    0 :   intval($consignee['district']);
    $consignee['street']        = !isset($consignee['street']) && empty($consignee['street'])                  ?    0 :   intval($consignee['street']);

    $_SESSION['flow_consignee'] = $consignee;

    $consignee['province_name'] = get_goods_region_name($consignee['province']);
    $consignee['city_name'] = get_goods_region_name($consignee['city']);
    $consignee['district_name'] = get_goods_region_name($consignee['district']);
    $consignee['street_name'] = get_goods_region_name($consignee['street']);
    $consignee['consignee_address'] = $consignee['province_name'] . $consignee['city_name'] . $consignee['district_name'] . $consignee['street_name'] . $consignee['address'];
    $smarty->assign('consignee', $consignee);



    /* 对商品信息赋值 */
    $cart_goods_list = cart_goods($flow_type, $cart_value, 1, $region_id, $area_info['region_id'], $consignee, $store_id); // 取得商品列表，计算合计
    $cart_goods_list_new = cart_by_favourable($cart_goods_list);
    $smarty->assign('goods_list', $cart_goods_list_new);

    $smarty->assign('seckill_id',$_SESSION['extension_id']);//输出秒杀id

     /*获取门店信息  by kong 20160721 start*/
    $smarty->assign('provinces', get_regions(1, 1));
    if($store_id > 0){
        /*获取该商品有货门店*/
        $sql = "SELECT c.goods_id,o.id,o.stores_name,o.stores_address,p.region_name as province,ci.region_name as city ,d.region_name as district "
                . "FROM".$ecs->table("cart")." AS c "
                . "LEFT JOIN ".$ecs->table("offline_store")." AS o ON c.store_id=o.id "
                . "LEFT JOIN".$ecs->table("store_goods")." AS s ON s.store_id=o.id "
                . "LEFT JOIN ".$ecs->table("region")." AS p ON p.region_id = o.province "
                . "LEFT JOIN ".$ecs->table('region')." AS ci ON ci.region_id = o.city "
                . "LEFT JOIN ".$ecs->table('region')." AS d ON d.region_id = o.district WHERE c.rec_id='".$cart_value."' LIMIT 1";
        $seller_store = $db->getRow($sql);
        $smarty->assign("seller_store",$seller_store);
        $sql = "SELECT store_mobile,take_time FROM".$ecs->table("cart")."WHERE rec_id in (".$cart_value.") LIMIT 1";
        $store_info = $db->getRow($sql);
        if(!$store_info['store_mobile']){
            $sql = "SELECT mobile_phone FROM ".$ecs->table('users')." WHERE user_id = '".$_SESSION['user_id']."'";
            $store_info['store_mobile'] = $db->getOne($sql, true);
        }
        if(!$store_info['take_time']){
            $store_info['take_time'] = date("Y-m-d H:i:s",strtotime("+1 day"));
        }
        $now_time = date("Y-m-d H:i:s",gmtime());
        $smarty->assign("now_time",$now_time);
        $smarty->assign('store_info',$store_info);
    }

    $smarty->assign('store_id',$store_id);
    $smarty->assign('cart_value',$cart_value);
    $smarty->assign('store_seller',$store_seller);
    $smarty->assign('is_address',$is_address);
    /*获取门店信息  by kong 20160721 end*/

    $cart_goods_number = get_buy_cart_goods_number($flow_type, $cart_value);
    $smarty->assign('cart_goods_number', $cart_goods_number);

    // 取得商品列表，计算合计
    $cart_goods = get_new_group_cart_goods($cart_goods_list_new);

    /* 对是否允许修改进货单赋值 */
    if ($flow_type != CART_GENERAL_GOODS || $_CFG['one_step_buy'] == '1')
    {
        $smarty->assign('allow_edit_cart', 0);
    }
    else
    {
        $smarty->assign('allow_edit_cart', 1);
    }

    /*
     * 取得购物流程设置
     */
    $smarty->assign('config', $_CFG);

    /*
     * 取得订单信息
     */
    $order = flow_order_info();

    $smarty->assign('order', $order);

    /* 如果能开发票，取得发票内容列表 */
    if ((!isset($_CFG['can_invoice']) || $_CFG['can_invoice'] == '1')
        && isset($_CFG['invoice_content'])
        && trim($_CFG['invoice_content']) != '' && $flow_type != CART_EXCHANGE_GOODS)
    {
        $inv_content_list = explode("\n", str_replace("\r", '', $_CFG['invoice_content']));
        $smarty->assign('inv_content', $inv_content_list[0]);
        //默认发票计算
        $order['need_inv']    = 1;
        $order['inv_type']    = $_CFG['invoice_type']['type'][0];
        $order['inv_payee']   = '个人';
        $order['inv_content'] = $inv_content_list[0];
    }

    /* 计算折扣 */
    if ($flow_type != CART_EXCHANGE_GOODS && $flow_type != CART_GROUP_BUY_GOODS)
    {
        $discount = compute_discount(3, $cart_value);
        $smarty->assign('discount', $discount['discount']);
        $favour_name = empty($discount['name']) ? '' : join(',', $discount['name']);
        $smarty->assign('your_discount', sprintf($_LANG['your_discount'], $favour_name, price_format($discount['discount'])));
    }

    if(!$user_address){
        $consignee = array(
            'province' => 0,
            'city' => 0
        );
        // 取得国家列表、商店所在国家、商店所在国家的省列表
        $smarty->assign('country_list',       get_regions());
        $smarty->assign('please_select',       $_LANG['please_select']);

        $province_list = get_regions_log(1,1);
        $city_list     = get_regions_log(2, $consignee['province']);
        $district_list = get_regions_log(3, $consignee['city']);

        $smarty->assign('province_list', $province_list);
        $smarty->assign('city_list',     $city_list);
        $smarty->assign('district_list', $district_list);
        $smarty->assign('consignee', $consignee);
    }

    /*
     * 计算订单的费用
     */
    $total = order_fee($order, $cart_goods, $consignee, 0, $cart_value, 0, $cart_goods_list,0,0,$store_id,$store_seller);

    $smarty->assign('total', $total);
    $smarty->assign('shopping_money', sprintf($_LANG['shopping_money'], $total['formated_goods_price']));
    $smarty->assign('market_price_desc', sprintf($_LANG['than_market_price'], $total['formated_market_price'], $total['formated_saving'], $total['save_rate']));

    /* 取得支付列表 */
    if ($order['shipping_id'] == 0)
    {
        $cod        = true;
        $cod_fee    = 0;
    }
    else
    {
        $shipping = shipping_info($order['shipping_id']);
        $cod = $shipping['support_cod'];

        if ($cod)
        {
            /* 如果是团购，且保证金大于0，不能使用货到付款 */
            if ($flow_type == CART_GROUP_BUY_GOODS)
            {
                $group_buy_id = $_SESSION['extension_id'];
                if ($group_buy_id <= 0)
                {
                    show_message('error group_buy_id');
                }
                $group_buy = group_buy_info($group_buy_id);
                if (empty($group_buy))
                {
                    show_message('group buy not exists: ' . $group_buy_id);
                }

                if ($group_buy['deposit'] > 0)
                {
                    $cod = false;
                    $cod_fee = 0;

                    /* 赋值保证金 */
                    $smarty->assign('gb_deposit', $group_buy['deposit']);
                }
            }

            if ($cod)
            {
                $shipping_area_info = shipping_info($order['shipping_id']);
                $cod_fee            = isset($shipping_area_info['pay_fee']) ? $shipping_area_info['pay_fee'] : 0;
            }
        }
        else
        {
            $cod_fee = 0;
        }
    }

    // 给货到付款的手续费加<span id>，以便改变配送的时候动态显示
    $payment_list = available_payment_list(1, $cod_fee);

    if(isset($payment_list))
    {
        foreach ($payment_list as $key => $payment)
        {
            //ecmoban模板堂 --will start
            //pc端去除ecjia的支付方式
            if (substr($payment['pay_code'], 0 , 4) == 'pay_') {
                    unset($payment_list[$key]);
                    continue;
            }
            //ecmoban模板堂 --will end

            if ($payment['is_cod'] == '1')
            {
                $payment_list[$key]['format_pay_fee'] = '<span id="ECS_CODFEE">' . $payment['format_pay_fee'] . '</span>';
            }
            /* 如果有易宝神州行支付 如果订单金额大于300 则不显示 */
            if ($payment['pay_code'] == 'yeepayszx' && $total['amount'] > 300)
            {
                unset($payment_list[$key]);
            }

            if ($payment['pay_code'] == 'alipay_wap') {
                unset($payment_list[$key]);
            }

            /* 如果有余额支付 */
            if ($payment['pay_code'] == 'balance')
            {
                /* 如果未登录，不显示 */
                if ($_SESSION['user_id'] == 0)
                {
                    unset($payment_list[$key]);
                }
                else
                {
                    if ($_SESSION['flow_order']['pay_id'] == $payment['pay_id'])
                    {
                        $smarty->assign('disable_surplus', 1);
                    }
                }
            }
        }
    }

    //@模板堂-bylu 过滤掉在线支付的方法(余额支付,支付宝等等),因为订单结算页只允许显示一个在线支付按钮 start
    foreach ($payment_list as $k => $v) {
        if ($v['is_online'] == 1) {
            unset($payment_list[$k]);
        }
    }

    //@模板堂-bylu  end
    $smarty->assign('payment_list', $payment_list);

    /* 取得包装与贺卡 */
    if ($total['real_goods_count'] > 0)
    {
        /* 只有有实体商品,才要判断包装和贺卡 */
        if (!isset($_CFG['use_package']) || $_CFG['use_package'] == '1')
        {
            /* 如果使用包装，取得包装列表及用户选择的包装 */
            $smarty->assign('pack_list', pack_list());
        }

        /* 如果使用贺卡，取得贺卡列表及用户选择的贺卡 */
        if (!isset($_CFG['use_card']) || $_CFG['use_card'] == '1')
        {
            $smarty->assign('card_list', card_list());
        }
    }

    $user_info = user_info($_SESSION['user_id']);

    // 如果开启在线支付  qin
    $sql_pay = "SELECT pay_online, ec_salt, pay_password, user_surplus FROM ".$ecs->table('users_paypwd')." WHERE user_id = '" .$_SESSION['user_id']. "' LIMIT 1";
    $pay_online = $db->getRow($sql_pay);
    if($pay_online['pay_online'] || ($pay_online['user_surplus'] && $user_info['user_money'] > 0)) { //安装了"在线支付",才显示余额支付输入框 bylu;
        $smarty->assign('open_pay_password', 1);
        $smarty->assign('pay_pwd_error',       1);
    }

    /* 如果使用余额，取得用户余额 */
    if ((!isset($_CFG['use_surplus']) || $_CFG['use_surplus'] == '1')
        && $_SESSION['user_id'] > 0
        && $user_info['user_money'] > 0)
    {
        if($db->getOne("SELECT enabled FROM ".$ecs->table('payment')." WHERE pay_code = 'balance'")) { // 安装了"余额支付",才显示余额支付输入框 bylu;
            // 能使用余额
            $smarty->assign('allow_use_surplus', 1);
            $smarty->assign('your_surplus', $user_info['user_money']);
        }
    }

    /* 如果使用积分，取得用户可用积分及本订单最多可以使用的积分 */
    if ((!isset($_CFG['use_integral']) || $_CFG['use_integral'] == '1')
        && $_SESSION['user_id'] > 0
        && $user_info['pay_points'] > 0
        && ($flow_type != CART_GROUP_BUY_GOODS && $flow_type != CART_EXCHANGE_GOODS))
    {
        // 能使用积分
        $smarty->assign('allow_use_integral', 1);
        $smarty->assign('order_max_integral', flow_available_points($cart_value, $region_id, $area_id));  // 可用积分  by kong  改
        $smarty->assign('your_integral',      $user_info['pay_points']); // 用户积分
    }

    $cart_ru_id = '';
    if($cart_value){
        $cart_ru_id = get_cart_seller($cart_value);
    }

    /* 如果使用红包，取得用户可以使用的红包及用户选择的红包 */
    if ((!isset($_CFG['use_bonus']) || $_CFG['use_bonus'] == '1')
        && ($flow_type != CART_GROUP_BUY_GOODS && $flow_type != CART_EXCHANGE_GOODS))
    {
        // 取得用户可用红包
        $user_bonus = user_bonus($_SESSION['user_id'], $total['goods_price'], $cart_value, $total['seller_amount'], $cart_ru_id);

        if (!empty($user_bonus)) {
            foreach ($user_bonus AS $key => $val) {
                $user_bonus[$key]['bonus_money_formated'] = price_format($val['type_money'], false);
                if (defined('THEME_EXTENSION')) {
                    $bonus_ids[] = $val['bonus_id'];
                    $user_bonus[$key]['use_end_date'] = local_date('Y-m-d', $val['use_end_date']);
                }
            }

            $smarty->assign('bonus_list', $user_bonus);
        }

        if (defined('THEME_EXTENSION')) {
            $bonus = get_user_bouns_new_list($_SESSION['user_id'], 1, 0, 'bouns_available_gotoPage', 0, 20, $cart_ru_id); //获取用户全部的红包列表，显示20
            //获取不能使用的红包数组
            if (!empty($bonus['available_list'])) {
                foreach ($bonus['available_list'] as $k => $v) {
                    foreach($user_bonus as $bk => $br){
                        if($br['bonus_id'] == $v['bonus_id']){
                            unset($bonus['available_list'][$k]);
                            continue;
                        }
                    }
                }

                $no_bonuslist = !empty($bonus['available_list']) ? $bonus['available_list'] : array();
                $smarty->assign('no_bonuslist', $no_bonuslist);
            }
        }

        // 能使用红包
        $smarty->assign('allow_use_bonus', 1);
    }

    /* 储值卡 begin */
    if (($_CFG['use_value_card'] == '1') && $flow_type != CART_EXCHANGE_GOODS)
    {
        // 取得用户可用储值卡
        $value_card = get_user_value_card($_SESSION['user_id'], $cart_goods, $cart_value);

        if (!empty($value_card))
        {
            foreach ($value_card AS $key => $val)
            {
                $value_card[$key]['card_money_formated'] = price_format($val['card_money'], false);
            }

            $smarty->assign('value_card_list', $value_card);
        }

        if ($value_card && isset($value_card['is_value_cart'])) {
            $value_card = array();
            $smarty->assign('is_value_cart', 0);
        } else {
            $smarty->assign('is_value_cart', 1);
        }

        // 能使用储值卡
        $smarty->assign('allow_use_value_card', 1);
    }else{
        $smarty->assign('allow_use_value_card', 0);
    }

    /*  @author-bylu 优惠券 start  */
    if ($_CFG['use_coupons'] == 1 && $flow_type == CART_GENERAL_GOODS)
    {
        // 取得用户可用优惠券
        $user_coupons = get_user_coupons_list($_SESSION['user_id'], true, $total['goods_price'], $cart_goods, true, $cart_ru_id);

        if (defined('THEME_EXTENSION')) {
            $coupons_list = get_user_coupons_list($_SESSION['user_id'], true, '', false, true, $cart_ru_id, 'cart'); //获得当前登陆用户所有的优惠券

            //获取不能使用的优惠券数组
            if (!empty($coupons_list)) {
                foreach ($coupons_list as $k => $v) {
                    $coupons_list[$k]['cou_type_name'] = $v['cou_type'];
                    $coupons_list[$k]['cou_end_time'] = local_date('Y-m-d', $v['cou_end_time']);
                    $coupons_list[$k]['cou_type'] = $v['cou_type'] == 3 ? $_LANG['lang_goods_coupons']['all_pay'] : ($v['cou_type'] == 4 ? $_LANG['lang_goods_coupons']['user_pay'] : ($v['cou_type'] == 2 ? $_LANG['lang_goods_coupons']['goods_pay'] : ($v['cou_type'] == 1 ? $_LANG['lang_goods_coupons']['reg_pay'] : $_LANG['lang_goods_coupons']['not_pay'])));

                    if ($v['spec_cat']) {
                        $coupons_list[$k]['cou_goods_name'] = $_LANG['lang_goods_coupons']['is_cate'];
                    } elseif ($v['cou_goods']) {
                        $coupons_list[$k]['cou_goods_name'] = $_LANG['lang_goods_coupons']['is_goods'];
                    } else {
                        $coupons_list[$k]['cou_goods_name'] = $_LANG['lang_goods_coupons']['is_all'];
                    }

                    if (!empty($user_coupons)) {
                        foreach ($user_coupons AS $uk => $ur) {
                            if ($v['cou_id'] == $ur['cou_id']) {
                                unset($coupons_list[$k]);
                                continue;
                            }
                        }
                    }
                }
            }
            //没有满足条件的优惠券数组
            $smarty->assign('coupons_list', $coupons_list);
        }

        foreach ($user_coupons as $k => $v) {
            $user_coupons[$k]['cou_type_name'] = $v['cou_type'];
            $user_coupons[$k]['cou_end_time'] = local_date('Y-m-d', $v['cou_end_time']);
            $user_coupons[$k]['cou_type'] = $v['cou_type'] == 3 ? $_LANG['lang_goods_coupons']['all_pay'] : ($v['cou_type'] == 4 ? $_LANG['lang_goods_coupons']['user_pay'] : ($v['cou_type'] == 2 ? $_LANG['lang_goods_coupons']['goods_pay'] : ($v['cou_type'] == 1 ? $_LANG['lang_goods_coupons']['reg_pay'] : $_LANG['lang_goods_coupons']['not_pay'])));
            $user_coupons[$k]['cou_goods_name'] = $v['cou_goods'] ? $_LANG['lang_goods_coupons']['is_goods'] : $_LANG['lang_goods_coupons']['is_all'];
            if ($v['spec_cat']) {
                $user_coupons[$k]['cou_goods_name'] = $_LANG['lang_goods_coupons']['is_cate'];
            } elseif ($v['cou_goods']) {
                $user_coupons[$k]['cou_goods_name'] = $_LANG['lang_goods_coupons']['is_goods'];
            } else {
                $user_coupons[$k]['cou_goods_name'] = $_LANG['lang_goods_coupons']['is_all'];
            }
        }
        //优惠券列表
        $smarty->assign('user_coupons', $user_coupons);
    }
    /*  @author-bylu  end  */

    /* 如果使用缺货处理，取得缺货处理列表 */
    if (!isset($_CFG['use_how_oos']) || $_CFG['use_how_oos'] == '1')
    {
        if (is_array($GLOBALS['_LANG']['oos']) && !empty($GLOBALS['_LANG']['oos']))
        {
            $smarty->assign('how_oos_list', $GLOBALS['_LANG']['oos']);
        }
    }

    /* 保存 session */
    $_SESSION['flow_order'] = $order;

}

//选择配送方式
elseif ($_REQUEST['step'] == 'select_shipping')
{
    /*------------------------------------------------------ */
    //-- 改变配送方式
    /*------------------------------------------------------ */
    include_once('includes/cls_json.php');
    $json = new JSON;
    $result = array('error' => '', 'content' => '', 'need_insure' => 0);

    $warehouse_id = isset($_REQUEST['warehouse_id'])  ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id = isset($_REQUEST['area_id'])  ? intval($_REQUEST['area_id']) : 0;

    $smarty->assign('warehouse_id', $warehouse_id);
    $smarty->assign('area_id', $area_id);

    /* 取得购物类型 */
    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    /* 获得收货人信息 */
    $consignee = get_consignee($_SESSION['user_id']);

    /* 对商品信息赋值 */
    $cart_goods = cart_goods($flow_type, $_SESSION['cart_value']); // 取得商品列表，计算合计

    if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
    {
        $result['error'] = $_LANG['no_goods_in_cart'];
    }
    else
    {
        /* 取得购物流程设置 */
        $smarty->assign('config', $_CFG);

        /* 取得订单信息 */
        $order = flow_order_info();

        $order['shipping_id'] = intval($_REQUEST['shipping']);
        $regions = array($consignee['country'], $consignee['province'], $consignee['city'], $consignee['district']);
        $shipping_info = shipping_info($order['shipping_id'], $regions);

        /* 计算订单的费用 */
        $total = order_fee($order, $cart_goods, $consignee);
        $smarty->assign('total', $total);

        /* 取得可以得到的积分和红包 */
        $smarty->assign('total_integral', cart_amount(false, $flow_type) - $total['bonus'] - $total['integral_money']);
        $smarty->assign('total_bonus',    price_format(get_total_bonus(), false));

        /* 团购标志 */
        if ($flow_type == CART_GROUP_BUY_GOODS)
        {
            $smarty->assign('is_group_buy', 1);
        }
        elseif ($flow_type == CART_EXCHANGE_GOODS)
        {
            // 积分兑换 qin
            $smarty->assign('is_exchange_goods', 1);
        }

        $result['cod_fee']     = $shipping_info['pay_fee'];
        if (strpos($result['cod_fee'], '%') === false)
        {
            $result['cod_fee'] = price_format($result['cod_fee'], false);
        }

        //ecmoban模板堂 --zhuo start
        $ru_list = get_ru_info_list($total['ru_list']); //商家运费详细信息
        $smarty->assign('warehouse_fee', $ru_list);
        $smarty->assign('freight_model',   $GLOBALS['_CFG']['freight_model']); //ecmoban模板堂 --zhuo 运费模式
        //ecmoban模板堂 --zhuo end

        $result['need_insure'] = ($shipping_info['insure'] > 0 && !empty($order['need_insure'])) ? 1 : 0;
        $result['content']     = $smarty->fetch('library/order_total.lbi');
    }

    echo $json->encode($result);
    exit;
}
elseif ($_REQUEST['step'] == 'select_insure')
{
    /*------------------------------------------------------ */
    //-- 选定/取消配送的保价
    /*------------------------------------------------------ */

    include_once('includes/cls_json.php');
    $json = new JSON;
    $result = array('error' => '', 'content' => '', 'need_insure' => 0);

    $warehouse_id = isset($_REQUEST['warehouse_id'])  ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id = isset($_REQUEST['area_id'])  ? intval($_REQUEST['area_id']) : 0;

    $smarty->assign('warehouse_id', $warehouse_id);
    $smarty->assign('area_id', $area_id);

    /* 取得购物类型 */
    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    /* 获得收货人信息 */
    $consignee = get_consignee($_SESSION['user_id']);

    /* 对商品信息赋值 */
    $cart_goods = cart_goods($flow_type, $_SESSION['cart_value']); // 取得商品列表，计算合计

    if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
    {
        $result['error'] = $_LANG['no_goods_in_cart'];
    }
    else
    {
        /* 取得购物流程设置 */
        $smarty->assign('config', $_CFG);

        /* 取得订单信息 */
        $order = flow_order_info();

        $order['need_insure'] = intval($_REQUEST['insure']);

        /* 保存 session */
        $_SESSION['flow_order'] = $order;

        //ecmoban模板堂 --zhuo start
        $cart_goods_number = get_buy_cart_goods_number($flow_type, $_SESSION['cart_value']);
        $smarty->assign('cart_goods_number', $cart_goods_number);

        $consignee['province_name'] = get_goods_region_name($consignee['province']);
        $consignee['city_name'] = get_goods_region_name($consignee['city']);
        $consignee['district_name'] = get_goods_region_name($consignee['district']);
        $consignee['street_name'] = get_goods_region_name($consignee['street']);
        $consignee['consignee_address'] = $consignee['province_name'] . $consignee['city_name'] . $consignee['district_name'] . $consignee['street_name'] . $consignee['address'];
        $smarty->assign('consignee', $consignee);

        $cart_goods_list = cart_goods($flow_type, $_SESSION['cart_value'], 1); // 取得商品列表，计算合计

        $smarty->assign('goods_list', $cart_goods_list);

        /* 计算订单的费用 */
        $total = order_fee($order, $cart_goods, $consignee, 0, $_SESSION['cart_value'], 0, $cart_goods_list);
        $smarty->assign('total', $total);
        //ecmoban模板堂 --zhuo end

        /* 取得可以得到的积分和红包 */
        $smarty->assign('total_integral', cart_amount(false, $flow_type) - $total['bonus'] - $total['integral_money']);
        $smarty->assign('total_bonus',    price_format(get_total_bonus(), false));

        /* 团购标志 */
        if ($flow_type == CART_GROUP_BUY_GOODS)
        {
            $smarty->assign('is_group_buy', 1);
        }
        elseif ($flow_type == CART_EXCHANGE_GOODS)
        {
            // 积分兑换 qin
            $smarty->assign('is_exchange_goods', 1);
        }

        $result['content'] = $smarty->fetch('library/order_total.lbi');
    }

    echo $json->encode($result);
    exit;
}
/*------------------------------------------------------ */
//-- 上门自取弹框
/*------------------------------------------------------ */
 elseif ($_REQUEST['step'] == 'pickSite') {
    /* 自提点弹窗口 */
    include('includes/cls_json.php');
    $json = new JSON;
    $res = array('err_msg' => '', 'result' => '');

    $mark = isset($_REQUEST['mark']) ? intval($_REQUEST['mark']) : 0;

    if($mark == 1){
        $days = array ();

        for($i=0; $i<=6; $i++)
        {
                    $days[$i]['shipping_date'] = date("Y-m-d", strtotime(' +'. $i . 'day'));
                    $days[$i]['date_year'] = $days[$i]['shipping_date'];
                    $days[$i]['week'] = $_LANG['unit']['week'] . transition_date($days[$i]['shipping_date']);
                    $days[$i]['date'] = substr($days[$i]['shipping_date'], 5);
        }

        $shipping_date_list = $db->getAll("SELECT * FROM " . $ecs->table('shipping_date'));
        $select = array();
        foreach($shipping_date_list as $key => $val)
        {
            $m = 0;
            for($s=0; $s<7; $s++)
            {
                    if($s < $val['select_day'])
                    {
                            $select[$m]['day'] = 0;
                            $select[$m]['date'] = $days[$m]['date'];
                            $select[$m]['week'] = $days[$m]['week'];
                            $select[$m]['shipping_date'] = $days[$m]['shipping_date'];
                    }
                    else
                    {
                            $strtime = $days[$m]['date_year']." ".$val['end_date'];
                            $strtime = strtotime($strtime);
                            $select[$m]['day'] = 1;
                            if($strtime < gmtime()+8*3600){
                                $select[$m]['day'] = 0;
                            }
                            $select[$m]['date'] = $days[$m]['date'];
                            $select[$m]['week'] = $days[$m]['week'];
                            $select[$m]['shipping_date'] = $days[$m]['shipping_date'];
                    }
                    $m++;
            }
            $shipping_date_list[$key]['select_day'] = $select;
            $select = array();
        }

        $smarty->assign('years', local_date('Y', gmtime()));
        $smarty->assign('days', $days);
        $smarty->assign('shipping_date_list', $shipping_date_list);

        $res['result'] = $GLOBALS['smarty']->fetch('library/picksite_date.lbi');
    }else{
        $district = $_SESSION['flow_consignee']['district'];
        $city = $_SESSION['flow_consignee']['city'];

        //全部区域
        $sql = "SELECT * FROM ". $ecs->table('region') ." WHERE parent_id = '$city'";
        $district_list = $db->getAll($sql);

        $picksite_list = get_self_point($district);

        $smarty->assign('picksite_list',   $picksite_list);
        $smarty->assign('district_list',   $district_list);
        $smarty->assign('district',   $district);
        $smarty->assign('city',   $city);

        $res['result'] = $GLOBALS['smarty']->fetch('library/picksite.lbi');
    }

    die($json->encode($res));
}
/*------------------------------------------------------ */
//-- 按区域选择自提点
/*------------------------------------------------------ */
elseif($_REQUEST['step'] == 'getPickSiteList'){
    include_once('includes/cls_json.php');
    $district = !empty($_POST['id']) ? intval($_POST['id']) : 0;

    $result = array('error' => 0, 'message' => '', 'content' => '');
    $json = new JSON;

    if ($district == 0) {
        $sql = "SELECT a.region_id ,a.shipping_area_id,b.region_name,b.parent_id as city,c.name,c.user_name,c.id as point_id,c.address,c.mobile,c.img_url,c.anchor,c.line FROM " . $GLOBALS['ecs']->table('area_region') . " AS a
                LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS b ON a.region_id=b.region_id  LEFT JOIN " .
                $GLOBALS['ecs']->table('shipping_point') . " AS c ON c.shipping_area_id=a.shipping_area_id " .
                "WHERE c.name != '' AND a.region_id IN (SELECT region_id FROM " . $ecs->table('region') . " WHERE parent_id='{$_SESSION[flow_consignee][city]}')";
        $self_point = $db->getAll($sql);
    } else {
        $self_point = get_self_point($district);
    }

    if (empty($self_point)) {
        $result['error'] = 1;
    }

    die($json->encode($self_point));
}
/*------------------------------------------------------ */
//-- 选择上门自取点
/*------------------------------------------------------ */
elseif ($_REQUEST['step'] == 'select_picksite')
{
    include('includes/cls_json.php');
    $json = new JSON;
    $res = array('error' => 0, 'err_msg' => '', 'content' => '');

    $picksite_id = isset($_REQUEST['picksite_id']) ? intval($_REQUEST['picksite_id']) : 0;
    $district = isset($_REQUEST['district']) ? intval($_REQUEST['district']) : 0;
    $shipping_date = isset($_REQUEST['shipping_date']) ? htmlspecialchars($_REQUEST['shipping_date']) : '';
    $time_range = isset($_REQUEST['time_range']) ? htmlspecialchars($_REQUEST['time_range']) : '';
    $mark = isset($_REQUEST['mark']) ? intval($_REQUEST['mark']) : 0;

    if($mark == 0){
        $_SESSION['flow_consignee']['point_id'] = $picksite_id;
    }else{
        if($shipping_date){
            $week = $_LANG['unit']['week'] . transition_date($shipping_date);
        }
        $shipping_dateStr = date("m",strtotime($shipping_date))."月".date("d",strtotime($shipping_date))."日【". $week ."】".$time_range;
        $_SESSION['flow_consignee']['shipping_dateStr'] = $shipping_dateStr;
    }
    /* 取得购物类型 */
    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    /* 获得收货人信息 */
    $consignee = get_consignee($_SESSION['user_id']);

    /* 对商品信息赋值 */
    $cart_goods_list = cart_goods($flow_type, $_SESSION['cart_value'], 1, $region_id, $area_info['region_id']); // 取得商品列表，计算合计
    $cart_goods_list_new = cart_by_favourable($cart_goods_list);

    $cart_goods = get_new_group_cart_goods($cart_goods_list_new);
    if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
    {
        if(empty($cart_goods)){
            $result['error'] = 1;
            $result['err_msg'] = $_LANG['no_goods_in_cart'];
        }elseif(!check_consignee_info($consignee, $flow_type)){
            $result['error'] = 2;
            $result['err_msg'] = $_LANG['au_buy_after_login'];
        }
    }

    $smarty->assign('goods_list', $cart_goods_list_new);
    $smarty->assign('shipping_code', 'cac');
    //有存在虚拟和实体商品 start
    get_goods_flow_type($_SESSION['cart_value']);
    //有存在虚拟和实体商品 end

    $res['content'] = $GLOBALS['smarty']->fetch('library/flow_cart_goods.lbi');

    die($json->encode($res));
}

/*------------------------------------------------------ */
//-- 验证支付密码
/*------------------------------------------------------ */
elseif ($_REQUEST['step'] == 'pay_pwd')
{
    include('includes/cls_json.php');
    $json = new JSON;
    $res = array('error' => 0, 'err_msg' => '', 'content' => '');

    $pay_pwd = isset($_POST['pay_pwd']) && !empty($_POST['pay_pwd']) ? addslashes(trim($_POST['pay_pwd'])) : '';

    $sql = "SELECT pay_online, ec_salt, pay_password FROM ".$ecs->table('users_paypwd')." WHERE user_id = '" .$_SESSION['user_id']. "' LIMIT 1";
    $pay = $db->getRow($sql);

    // 加密
    $ec_salt = $pay['ec_salt'];
    $new_password = md5(md5($pay_pwd) . $ec_salt);

    if(empty($pay_pwd)){
        $res['error'] = 1;
    }
    else if ($new_password != $pay['pay_password']) {
        $res['error'] = 2;
    }

    die($json->encode($res));
}
/*
 * 微信支付改变状态
 */
elseif($_REQUEST['step'] == 'checkorder'){
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    $sql = "SELECT pay_status, pay_id FROM ".$ecs->table('order_info')." WHERE order_id = '$order_id' LIMIT 1";
    $order_info = $db->getRow($sql);

    $sql = "SELECT pay_name, pay_code FROM " .$ecs->table('payment'). " WHERE pay_id = '" .$order_info['pay_id']. "' LIMIT 1";
    $pay = $db->getRow($sql);

    //已付款
    if ($order_info && $order_info['pay_status'] == PS_PAYED) {
        $json = array('code' => 1, 'pay_name' => $pay['pay_name'], 'pay_code' => $pay['pay_code']);
        exit(json_encode($json));
    } else {
        $json = array('code' => 0, 'pay_name' => $pay['pay_name'], 'pay_code' => $pay['pay_code']);
        exit(json_encode($json));
    }
}

elseif ($_REQUEST['step'] == 'select_payment')
{
    /*------------------------------------------------------ */
    //-- 改变支付方式
    /*------------------------------------------------------ */

    include_once('includes/cls_json.php');
    $json = new JSON;
    $result = array('error' => 0, 'massage' => '', 'content' => '', 'need_insure' => 0, 'payment' => 1);

    /* 取得购物类型 */
    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;
    $store_id = isset($_REQUEST['store_id'])  ? intval($_REQUEST['store_id']) : 0;
    $store_seller = isset($_REQUEST['store_seller'])  ? dsc_addslashes($_REQUEST['store_seller']) : '';
    $store_seller = ($store_id > 0)  ?  'store_seller' : $store_seller;
    $_POST['shipping_id'] = strip_tags(urldecode($_REQUEST['shipping_id']));
    $tmp_shipping_id_arr = $json->decode($_POST['shipping_id']);

    $smarty->assign('store_id', $store_id);
    $smarty->assign('store_seller', $store_seller);

    $warehouse_id = isset($_REQUEST['warehouse_id'])  ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id = isset($_REQUEST['area_id'])  ? intval($_REQUEST['area_id']) : 0;

    $smarty->assign('warehouse_id', $warehouse_id);
    $smarty->assign('area_id', $area_id);

    if ($store_id > 0) {

        /* 对商品信息赋值 */
        $cart_goods_list = cart_goods($flow_type, $_SESSION['cart_value'], 1, $region_id, $area_info['region_id'], '', $store_id); // 取得商品列表，计算合计
        $cart_goods_list_new = cart_by_favourable($cart_goods_list);

        $cart_goods = get_new_group_cart_goods($cart_goods_list_new);
        if (empty($cart_goods)) {
            //ecmoban模板堂 --zhuo start
            if (empty($cart_goods)) {
                $result['error'] = 1;
            }
            //ecmoban模板堂 --zhuo end
        } else {
            /* 取得购物流程设置 */
            $smarty->assign('config', $_CFG);

            /* 取得订单信息 */
            $order = flow_order_info();

            $order['pay_id'] = intval($_REQUEST['payment']);
            $payment_info = payment_info($order['pay_id']);
            $result['pay_code'] = $payment_info['pay_code'];

            /* 保存 session */
            $_SESSION['flow_order'] = $order;

            //ecmoban模板堂 --zhuo start
            $cart_goods_number = get_buy_cart_goods_number($flow_type, $_SESSION['cart_value']);
            $smarty->assign('cart_goods_number', $cart_goods_number);

            $smarty->assign('goods_list', $cart_goods_list_new);

            //切换配送方式 by kong
            $cart_goods_list = get_flowdone_goods_list($cart_goods_list, $tmp_shipping_id_arr);

            /* 计算订单的费用 */
            $total = order_fee($order, $cart_goods, '', 0, $_SESSION['cart_value'], 0, $cart_goods_list, 0, 0, $store_id, $store_seller);
            $smarty->assign('total', $total);
            //ecmoban模板堂 --zhuo end

            /* 取得可以得到的积分和红包 */
            $smarty->assign('total_integral', cart_amount(false, $flow_type) - $total['bonus'] - $total['integral_money']);
            $smarty->assign('total_bonus', price_format(get_total_bonus(), false));

            //有存在虚拟和实体商品 start
            get_goods_flow_type($_SESSION['cart_value']);
            //有存在虚拟和实体商品 end

            $result['content'] = $smarty->fetch('library/order_total.lbi');
        }
    } else {

        /* 获得收货人信息 */
        $consignee = get_consignee($_SESSION['user_id']);

        /* 对商品信息赋值 */
        $cart_goods_list = cart_goods($flow_type, $_SESSION['cart_value'], 1, $region_id, $area_info['region_id']); // 取得商品列表，计算合计
        $cart_goods_list_new = cart_by_favourable($cart_goods_list);

        $cart_goods = get_new_group_cart_goods($cart_goods_list_new);
        if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type)) {
            //ecmoban模板堂 --zhuo start
            if (empty($cart_goods)) {
                $result['error'] = 1;
            } elseif (!check_consignee_info($consignee, $flow_type)) {
                $result['error'] = 2;
            }
            //ecmoban模板堂 --zhuo end
        } else {
            /* 取得购物流程设置 */
            $smarty->assign('config', $_CFG);

            /* 取得订单信息 */
            $order = flow_order_info();
            $order['surplus'] = 0;

            $order['pay_id'] = intval($_REQUEST['payment']);
            $payment_info = payment_info($order['pay_id']);
            $result['pay_code'] = $payment_info['pay_code'];

            /* 保存 session */
            $_SESSION['flow_order'] = $order;

            //ecmoban模板堂 --zhuo start
            $cart_goods_number = get_buy_cart_goods_number($flow_type, $_SESSION['cart_value']);
            $smarty->assign('cart_goods_number', $cart_goods_number);

            $consignee['province_name'] = get_goods_region_name($consignee['province']);
            $consignee['city_name'] = get_goods_region_name($consignee['city']);
            $consignee['district_name'] = get_goods_region_name($consignee['district']);
            $consignee['street_name'] = get_goods_region_name($consignee['street']);
            $consignee['consignee_address'] = $consignee['province_name'] . $consignee['city_name'] . $consignee['district_name'] . $consignee['street_name'] . $consignee['address'];
            $smarty->assign('consignee', $consignee);

            $smarty->assign('goods_list', $cart_goods_list_new);

            //切换配送方式 by kong
            $cart_goods_list = get_flowdone_goods_list($cart_goods_list, $tmp_shipping_id_arr);

            /* 计算订单的费用 */
            $total = order_fee($order, $cart_goods, $consignee, 0, $_SESSION['cart_value'], 0, $cart_goods_list, 0, 0, $store_id, $store_seller);
            $smarty->assign('total', $total);
            //ecmoban模板堂 --zhuo end

            /* 取得可以得到的积分和红包 */
            $smarty->assign('total_integral', cart_amount(false, $flow_type) - $total['bonus'] - $total['integral_money']);
            $smarty->assign('total_bonus', price_format(get_total_bonus(), false));

            /* 团购标志 */
            if ($flow_type == CART_GROUP_BUY_GOODS) {
                $smarty->assign('is_group_buy', 1);
            } elseif ($flow_type == CART_EXCHANGE_GOODS) {
                // 积分兑换 qin
                $smarty->assign('is_exchange_goods', 1);
            }

            //有存在虚拟和实体商品 start
            get_goods_flow_type($_SESSION['cart_value']);
            //有存在虚拟和实体商品 end

            $result['content'] = $smarty->fetch('library/order_total.lbi');
        }
    }


    echo $json->encode($result);
    exit;
}
elseif ($_REQUEST['step'] == 'select_pack')
{
    /*------------------------------------------------------ */
    //-- 改变商品包装
    /*------------------------------------------------------ */

    include_once('includes/cls_json.php');
    $json = new JSON;
    $result = array('error' => '', 'content' => '', 'need_insure' => 0);

    $warehouse_id = isset($_REQUEST['warehouse_id'])  ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id = isset($_REQUEST['area_id'])  ? intval($_REQUEST['area_id']) : 0;

    $smarty->assign('warehouse_id', $warehouse_id);
    $smarty->assign('area_id', $area_id);

    /* 取得购物类型 */
    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    /* 获得收货人信息 */
    $consignee = get_consignee($_SESSION['user_id']);

    /* 对商品信息赋值 */
    $cart_goods = cart_goods($flow_type, $_SESSION['cart_value']); // 取得商品列表，计算合计

    if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
    {
        $result['error'] = $_LANG['no_goods_in_cart'];
    }
    else
    {
        /* 取得购物流程设置 */
        $smarty->assign('config', $_CFG);

        /* 取得订单信息 */
        $order = flow_order_info();

        $order['pack_id'] = intval($_REQUEST['pack']);

        /* 保存 session */
        $_SESSION['flow_order'] = $order;

        //ecmoban模板堂 --zhuo start
        $cart_goods_number = get_buy_cart_goods_number($flow_type, $_SESSION['cart_value']);
        $smarty->assign('cart_goods_number', $cart_goods_number);

        $consignee['province_name'] = get_goods_region_name($consignee['province']);
        $consignee['city_name'] = get_goods_region_name($consignee['city']);
        $consignee['district_name'] = get_goods_region_name($consignee['district']);
        $consignee['street_name'] = get_goods_region_name($consignee['street']);
        $consignee['consignee_address'] = $consignee['province_name'] . $consignee['city_name'] . $consignee['district_name'] . $consignee['street_name'] . $consignee['address'];
        $smarty->assign('consignee', $consignee);

        $cart_goods_list = cart_goods($flow_type, $_SESSION['cart_value'], 1); // 取得商品列表，计算合计
        $smarty->assign('goods_list', $cart_goods_list);

        /* 计算订单的费用 */
        $total = order_fee($order, $cart_goods, $consignee, 0, $_SESSION['cart_value'], 0, $cart_goods_list);
        $smarty->assign('total', $total);
        //ecmoban模板堂 --zhuo end

        /* 取得可以得到的积分和红包 */
        $smarty->assign('total_integral', cart_amount(false, $flow_type) - $total['bonus'] - $total['integral_money']);
        $smarty->assign('total_bonus',    price_format(get_total_bonus(), false));

        /* 团购标志 */
        if ($flow_type == CART_GROUP_BUY_GOODS)
        {
            $smarty->assign('is_group_buy', 1);
        }
        elseif ($flow_type == CART_EXCHANGE_GOODS)
        {
            // 积分兑换 qin
            $smarty->assign('is_exchange_goods', 1);
        }

        $result['content'] = $smarty->fetch('library/order_total.lbi');
    }

    echo $json->encode($result);
    exit;
}
elseif ($_REQUEST['step'] == 'select_card')
{
    /*------------------------------------------------------ */
    //-- 改变贺卡
    /*------------------------------------------------------ */

    include_once('includes/cls_json.php');
    $json = new JSON;
    $result = array('error' => '', 'content' => '', 'need_insure' => 0);

    $warehouse_id = isset($_REQUEST['warehouse_id'])  ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id = isset($_REQUEST['area_id'])  ? intval($_REQUEST['area_id']) : 0;

    $smarty->assign('warehouse_id', $warehouse_id);
    $smarty->assign('area_id', $area_id);

    /* 取得购物类型 */
    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    /* 获得收货人信息 */
    $consignee = get_consignee($_SESSION['user_id']);

    /* 对商品信息赋值 */
    $cart_goods = cart_goods($flow_type, $_SESSION['cart_value']); // 取得商品列表，计算合计

    if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
    {
        $result['error'] = $_LANG['no_goods_in_cart'];
    }
    else
    {
        /* 取得购物流程设置 */
        $smarty->assign('config', $_CFG);

        /* 取得订单信息 */
        $order = flow_order_info();

        $order['card_id'] = intval($_REQUEST['card']);

        /* 保存 session */
        $_SESSION['flow_order'] = $order;

        //ecmoban模板堂 --zhuo start
        $cart_goods_number = get_buy_cart_goods_number($flow_type, $_SESSION['cart_value']);
        $smarty->assign('cart_goods_number', $cart_goods_number);

        $consignee['province_name'] = get_goods_region_name($consignee['province']);
        $consignee['city_name'] = get_goods_region_name($consignee['city']);
        $consignee['district_name'] = get_goods_region_name($consignee['district']);
        $consignee['street_name'] = get_goods_region_name($consignee['street']);
        $consignee['consignee_address'] = $consignee['province_name'] . $consignee['city_name'] . $consignee['district_name'] . $consignee['street_name'] . $consignee['address'];
        $smarty->assign('consignee', $consignee);

        $cart_goods_list = cart_goods($flow_type, $_SESSION['cart_value'], 1); // 取得商品列表，计算合计
        $smarty->assign('goods_list', $cart_goods_list);

        /* 计算订单的费用 */
        $total = order_fee($order, $cart_goods, $consignee, 0, $_SESSION['cart_value'], 0, $cart_goods_list);
        $smarty->assign('total', $total);
        //ecmoban模板堂 --zhuo end

        /* 取得可以得到的积分和红包 */
        $smarty->assign('total_integral', cart_amount(false, $flow_type) - $order['bonus'] - $total['integral_money']);
        $smarty->assign('total_bonus',    price_format(get_total_bonus(), false));

        /* 团购标志 */
        if ($flow_type == CART_GROUP_BUY_GOODS)
        {
            $smarty->assign('is_group_buy', 1);
        }
        elseif ($flow_type == CART_EXCHANGE_GOODS)
        {
            // 积分兑换 qin
            $smarty->assign('is_exchange_goods', 1);
        }

        $result['content'] = $smarty->fetch('library/order_total.lbi');
    }

    echo $json->encode($result);
    exit;
}
elseif ($_REQUEST['step'] == 'change_surplus')
{
    /*------------------------------------------------------ */
    //-- 改变余额
    /*------------------------------------------------------ */
    include_once('includes/cls_json.php');
    $json = new JSON();
    $surplus   = floatval($_GET['surplus']);
    $user_info = user_info($_SESSION['user_id']);
    $_POST['shipping_id']=strip_tags(urldecode($_REQUEST['shipping_id']));
    $tmp_shipping_id_arr = $json->decode($_POST['shipping_id']);

    $warehouse_id = isset($_REQUEST['warehouse_id'])  ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id = isset($_REQUEST['area_id'])  ? intval($_REQUEST['area_id']) : 0;

    $smarty->assign('warehouse_id', $warehouse_id);
    $smarty->assign('area_id', $area_id);

    if ($user_info['user_money'] + $user_info['credit_line'] < $surplus)
    {
        $result['error'] = $_LANG['surplus_not_enough'];
    }
    else
    {
        /* 取得购物类型 */
        $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

        /* 取得购物流程设置 */
        $smarty->assign('config', $_CFG);

        /* 获得收货人信息 */
        $consignee = get_consignee($_SESSION['user_id']);

        /* 对商品信息赋值 */
        $cart_goods = cart_goods($flow_type, $_SESSION['cart_value']); // 取得商品列表，计算合计

        if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
        {
            $result['error'] = $_LANG['no_goods_in_cart'];
        }
        else
        {
            /* 取得订单信息 */
            $order = flow_order_info();
            $order['surplus'] = $surplus;

            //ecmoban模板堂 --zhuo start
            $cart_goods_number = get_buy_cart_goods_number($flow_type, $_SESSION['cart_value']);
            $smarty->assign('cart_goods_number', $cart_goods_number);

            $consignee['province_name'] = get_goods_region_name($consignee['province']);
            $consignee['city_name'] = get_goods_region_name($consignee['city']);
            $consignee['district_name'] = get_goods_region_name($consignee['district']);
            $consignee['street_name'] = get_goods_region_name($consignee['street']);
            $consignee['consignee_address'] = $consignee['province_name'] . $consignee['city_name'] . $consignee['district_name'] . $consignee['street_name'] . $consignee['address'];
            $smarty->assign('consignee', $consignee);

            $cart_goods_list = cart_goods($flow_type, $_SESSION['cart_value'], 1); // 取得商品列表，计算合计
            $smarty->assign('goods_list', $cart_goods_list);

            //切换配送方式 by kong
            $cart_goods_list = get_flowdone_goods_list($cart_goods_list, $tmp_shipping_id_arr);

            /* 计算订单的费用 */
            $total = order_fee($order, $cart_goods, $consignee, 0, $_SESSION['cart_value'], 0, $cart_goods_list);

            $smarty->assign('total', $total);
            //ecmoban模板堂 --zhuo end

            /* 团购标志 */
            if ($flow_type == CART_GROUP_BUY_GOODS)
            {
                $smarty->assign('is_group_buy', 1);
            }
            elseif ($flow_type == CART_EXCHANGE_GOODS)
            {
                // 积分兑换 qin
                $smarty->assign('is_exchange_goods', 1);
            }

            $result['content'] = $smarty->fetch('library/order_total.lbi');
        }
    }


    die($json->encode($result));
}
elseif ($_REQUEST['step'] == 'change_integral')
{
    /*------------------------------------------------------ */
    //-- 改变积分
    /*------------------------------------------------------ */
    include_once('includes/cls_json.php');
    $json = new JSON();
    $points    = floatval($_GET['points']);
    $user_info = user_info($_SESSION['user_id']);
    $_POST['shipping_id']=strip_tags(urldecode($_REQUEST['shipping_id']));
    $tmp_shipping_id_arr = $json->decode($_POST['shipping_id']);

    /* 取得订单信息 */
    $order = flow_order_info();
    $flow_points = flow_available_points($_SESSION['cart_value'], $region_id, $area_id);  // 该订单允许使用的积分
    $user_points = $user_info['pay_points']; // 用户的积分总数

    $warehouse_id = isset($_REQUEST['warehouse_id'])  ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id = isset($_REQUEST['area_id'])  ? intval($_REQUEST['area_id']) : 0;

    $smarty->assign('warehouse_id', $warehouse_id);
    $smarty->assign('area_id', $area_id);

    if ($points > $user_points)
    {
        $result['error'] = $_LANG['integral_not_enough'];
    }
    elseif ($points > $flow_points)
    {
        $result['error'] = sprintf($_LANG['integral_too_much'], $flow_points);
    }
    else
    {
        /* 取得购物类型 */
        $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

        $order['integral'] = $points;

        /* 获得收货人信息 */
        $consignee = get_consignee($_SESSION['user_id']);

        /* 对商品信息赋值 */
        $cart_goods = cart_goods($flow_type, $_SESSION['cart_value']); // 取得商品列表，计算合计

        if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
        {
            $result['error'] = $_LANG['no_goods_in_cart'];
        }
        else
        {
            //ecmoban模板堂 --zhuo start
            $cart_goods_number = get_buy_cart_goods_number($flow_type, $_SESSION['cart_value']);
            $smarty->assign('cart_goods_number', $cart_goods_number);

            $consignee['province_name'] = get_goods_region_name($consignee['province']);
            $consignee['city_name'] = get_goods_region_name($consignee['city']);
            $consignee['district_name'] = get_goods_region_name($consignee['district']);
            $consignee['street_name'] = get_goods_region_name($consignee['street']);
            $consignee['consignee_address'] = $consignee['province_name'] . $consignee['city_name'] . $consignee['district_name'] . $consignee['street_name'] . $consignee['address'];
            $smarty->assign('consignee', $consignee);

            $cart_goods_list = cart_goods($flow_type, $_SESSION['cart_value'], 1); // 取得商品列表，计算合计
            $smarty->assign('goods_list', $cart_goods_list);

            //切换配送方式 by kong
            $cart_goods_list = get_flowdone_goods_list($cart_goods_list, $tmp_shipping_id_arr);

            /* 计算订单的费用 */
            $total = order_fee($order, $cart_goods, $consignee, 0, $_SESSION['cart_value'], 0, $cart_goods_list);
            $smarty->assign('total', $total);
            //ecmoban模板堂 --zhuo end
            $smarty->assign('config', $_CFG);

            /* 团购标志 */
            if ($flow_type == CART_GROUP_BUY_GOODS)
            {
                $smarty->assign('is_group_buy', 1);
            }
            elseif ($flow_type == CART_EXCHANGE_GOODS)
            {
                // 积分兑换 qin
                $smarty->assign('is_exchange_goods', 1);
            }

            $result['content'] = $smarty->fetch('library/order_total.lbi');
            $result['error'] = '';
        }
    }


    die($json->encode($result));
}
elseif ($_REQUEST['step'] == 'change_bonus')
{

    /*------------------------------------------------------ */
    //-- 改变红包
    /*------------------------------------------------------ */
    include_once('includes/cls_json.php');
    $result = array('error' => '', 'content' => '');
    $json = new JSON();
    /* 取得购物类型 */
    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    $_POST['shipping_id']=strip_tags(urldecode($_REQUEST['shipping_id']));
    $tmp_shipping_id_arr = $json->decode($_POST['shipping_id']);

    $warehouse_id = isset($_REQUEST['warehouse_id'])  ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id = isset($_REQUEST['area_id'])  ? intval($_REQUEST['area_id']) : 0;

    $smarty->assign('warehouse_id', $warehouse_id);
    $smarty->assign('area_id', $area_id);

    /* 获得收货人信息 */
    $consignee = get_consignee($_SESSION['user_id']);

    /* 对商品信息赋值 */
    $cart_goods = cart_goods($flow_type, $_SESSION['cart_value']); // 取得商品列表，计算合计

    if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
    {
        if (empty($cart_goods)) {
            $result['error'] = 1;
        } else {
            $result['error'] = 2;
        }
    }
    else
    {
        /* 取得购物流程设置 */
        $smarty->assign('config', $_CFG);

        /* 取得订单信息 */
        $order = flow_order_info();

        $bonus = bonus_info(intval($_GET['bonus']));

        if ((!empty($bonus) && $bonus['user_id'] == $_SESSION['user_id']) || $_GET['bonus'] == 0)
        {
            $order['bonus_id'] = intval($_GET['bonus']);
        }
        else
        {
            $order['bonus_id'] = 0;
            $result['error'] = $_LANG['invalid_bonus'];
        }

        //ecmoban模板堂 --zhuo start
        $cart_goods_number = get_buy_cart_goods_number($flow_type, $_SESSION['cart_value']);
        $smarty->assign('cart_goods_number', $cart_goods_number);

        $consignee['province_name'] = get_goods_region_name($consignee['province']);
        $consignee['city_name'] = get_goods_region_name($consignee['city']);
        $consignee['district_name'] = get_goods_region_name($consignee['district']);
        $consignee['street_name'] = get_goods_region_name($consignee['street']);
        $consignee['consignee_address'] = $consignee['province_name'] . $consignee['city_name'] . $consignee['district_name'] . $consignee['street_name'] . $consignee['address'];
        $smarty->assign('consignee', $consignee);

        $cart_goods_list = cart_goods($flow_type, $_SESSION['cart_value'], 1); // 取得商品列表，计算合计
        $smarty->assign('goods_list', $cart_goods_list);

        //切换配送方式 by kong
        $cart_goods_list = get_flowdone_goods_list($cart_goods_list, $tmp_shipping_id_arr);

        /* 计算订单的费用 */
        $total = order_fee($order, $cart_goods, $consignee, 0, $_SESSION['cart_value'], 0, $cart_goods_list);
        $smarty->assign('total', $total);
        //ecmoban模板堂 --zhuo end

        /* 团购标志 */
        if ($flow_type == CART_GROUP_BUY_GOODS)
        {
            $smarty->assign('is_group_buy', 1);
        }
        elseif ($flow_type == CART_EXCHANGE_GOODS)
        {
            // 积分兑换 qin
            $smarty->assign('is_exchange_goods', 1);
        }

        $result['content'] = $smarty->fetch('library/order_total.lbi');
    }


    die($json->encode($result));
}

/*------------------------------------------------------ */
//-- 改变储值卡
/*------------------------------------------------------ */
elseif ($_REQUEST['step'] == 'change_value_card')
{
    include_once('includes/cls_json.php');
    $result = array('error' => '', 'content' => '');
    $json = new JSON();
    /* 取得购物类型 */
    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    $_POST['shipping_id']=strip_tags(urldecode($_REQUEST['shipping_id']));
    $tmp_shipping_id_arr = $json->decode($_POST['shipping_id']);

    $warehouse_id = isset($_REQUEST['warehouse_id']) ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id = isset($_REQUEST['area_id']) ? intval($_REQUEST['area_id']) : 0;
    $store_id = isset($_REQUEST['store_id']) ? intval($_REQUEST['store_id']) : 0;

    $smarty->assign('warehouse_id', $warehouse_id);
    $smarty->assign('area_id', $area_id);
    $smarty->assign('store_id', $store_id);

    /* 获得收货人信息 */
    $consignee = get_consignee($_SESSION['user_id']);

    /* 对商品信息赋值 */
    $cart_goods = cart_goods($flow_type, $_SESSION['cart_value'],0,0,0,'',$store_id); // 取得商品列表，计算合计
    if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
    {
        if (empty($cart_goods)) {
            $result['error'] = 1;
        } else {
            $result['error'] = 2;
        }
    }
    else
    {
        /* 取得购物流程设置 */
        $smarty->assign('config', $_CFG);

        /* 取得订单信息 */
        $order = flow_order_info();

	$vc_id = intval($_GET['value_card']);
        $value_card = value_card_info($vc_id);
        if ((!empty($value_card) && $value_card['user_id'] == $_SESSION['user_id']) || $_GET['bonus'] == 0)
        {
            $order['vc_id'] = intval($vc_id);
        }
        else
        {
            $order['vc_id'] = 0;
            $result['error'] = $_LANG['invalid_value_card'];
        }

        //ecmoban模板堂 --zhuo start
        $cart_goods_number = get_buy_cart_goods_number($flow_type, $_SESSION['cart_value']);
        $smarty->assign('cart_goods_number', $cart_goods_number);

        $consignee['province_name'] = get_goods_region_name($consignee['province']);
        $consignee['city_name'] = get_goods_region_name($consignee['city']);
        $consignee['district_name'] = get_goods_region_name($consignee['district']);
        $consignee['street_name'] = get_goods_region_name($consignee['street']);
        $consignee['consignee_address'] = $consignee['province_name'] . $consignee['city_name'] . $consignee['district_name'] . $consignee['street_name'] . $consignee['address'];
        $smarty->assign('consignee', $consignee);

        $cart_goods_list = cart_goods($flow_type, $_SESSION['cart_value'], 1,0,0,'',$store_id); // 取得商品列表，计算合计
        $smarty->assign('goods_list', $cart_goods_list);

        //切换配送方式 by kong
        $cart_goods_list = get_flowdone_goods_list($cart_goods_list, $tmp_shipping_id_arr);

        /* 计算订单的费用 */
        $total = order_fee($order, $cart_goods, $consignee, 0, $_SESSION['cart_value'], 0, $cart_goods_list, 0, 0, $store_id);
        $smarty->assign('total', $total);
        //ecmoban模板堂 --zhuo end

        /* 团购标志 */
        if ($flow_type == CART_GROUP_BUY_GOODS)
        {
            $smarty->assign('is_group_buy', 1);
        }
        elseif ($flow_type == CART_EXCHANGE_GOODS)
        {
            // 积分兑换 qin
            $smarty->assign('is_exchange_goods', 1);
        }

        $result['content'] = $smarty->fetch('library/order_total.lbi');
    }
    die($json->encode($result));
}

/*------------------------------------------------------ */
//-- 改变优惠券 bylu
/*------------------------------------------------------ */
elseif ($_REQUEST['step'] == 'change_coupons')
{
    include_once('includes/cls_json.php');
    $result = array('error' => 0, 'content' => '');
    $json = new JSON();

    $warehouse_id = isset($_REQUEST['warehouse_id'])  ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id = isset($_REQUEST['area_id'])  ? intval($_REQUEST['area_id']) : 0;
    $_POST['shipping_id'] = strip_tags(urldecode($_REQUEST['shipping_id']));
    $tmp_shipping_id_arr = $json->decode($_POST['shipping_id']);

    /* 优惠券ID */
    $uc_id = isset($_GET['uc_id']) && !empty($_GET['uc_id']) ? intval($_GET['uc_id']) : 0;

    $smarty->assign('warehouse_id', $warehouse_id);
    $smarty->assign('area_id', $area_id);

    /* 取得购物类型 */
    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    /* 获得收货人信息 */
    $consignee = get_consignee($_SESSION['user_id']);

    /* 对商品信息赋值 */
    $cart_goods = cart_goods($flow_type, $_SESSION['cart_value']); // 取得商品列表，计算合计

    if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
    {
        if (empty($cart_goods)) {
            $result['error'] = 1;
        } else {
            $result['error'] = 2;
        }
    }
    else
    {
        /* 取得购物流程设置 */
        $smarty->assign('config', $_CFG);

        /* 取得订单信息 */
        $order = flow_order_info();
        $_SESSION['flow_order']=null;

        /* 获取优惠券信息 */
        $coupons_info = get_coupons($uc_id, array('c.cou_id', 'c.cou_man', 'c.cou_type', 'c.ru_id', 'c.cou_money', 'cu.uc_id', 'cu.user_id'));
        if ((!empty($coupons_info) && $coupons_info['user_id'] == $user_id) || $uc_id == 0)
        {
            $order['uc_id'] = $uc_id;
        }
        else
        {
            $order['uc_id'] = 0;
            $result['error'] = $_LANG['Coupon_null'];
        }

        //ecmoban模板堂 --zhuo start
        $cart_goods_number = get_buy_cart_goods_number($flow_type, $_SESSION['cart_value']);
        $smarty->assign('cart_goods_number', $cart_goods_number);

        $consignee['province_name'] = get_goods_region_name($consignee['province']);
        $consignee['city_name'] = get_goods_region_name($consignee['city']);
        $consignee['district_name'] = get_goods_region_name($consignee['district']);
        $consignee['street_name'] = get_goods_region_name($consignee['street']);
        $consignee['consignee_address'] = $consignee['province_name'] . $consignee['city_name'] . $consignee['district_name'] . $consignee['street_name'] . $consignee['address'];
        $smarty->assign('consignee', $consignee);

        $cart_goods_list = cart_goods($flow_type, $_SESSION['cart_value'], 1); // 取得商品列表，计算合计
        $smarty->assign('goods_list', $cart_goods_list);


        /* 优惠券 免邮 start */
        $not_freightfree = 0;
        if (!empty($coupons_info) && $cart_goods_list) {

            if ($coupons_info['cou_type'] == 5) {
                $cou_goods = array();
                $goods_amount = 0;
                foreach ($cart_goods_list as $key => $row) {
                    if ($row['ru_id'] == $coupons_info['ru_id']) {
                        $cou_goods = $row['goods_list'];
                        foreach ($row['goods_list'] as $gkey => $grow) {
                            $goods_amount += $grow['goods_price'] * $grow['goods_number'];
                        }
                    }
                }

                if ($goods_amount >= $coupons_info['cou_man'] || $coupons_info['cou_man'] == 0) {

                    $cou_region = get_coupons_region($coupons_info['cou_id']);
                    $cou_region = !empty($cou_region) ? explode(",", $cou_region) : array();

                    /* 是否含有不支持免邮的地区 */
                    if ($cou_region && in_array($consignee['province'], $cou_region)) {
                        $not_freightfree = 1;
                    }
                }
            }
        }
        /* 优惠券 免邮 start */

        $result['not_freightfree'] = $not_freightfree;

        //切换配送方式 by kong
        $cart_goods_list = get_flowdone_goods_list($cart_goods_list, $tmp_shipping_id_arr);

        /* 计算订单的费用 */
        $total = order_fee($order, $cart_goods, $consignee, 0, $_SESSION['cart_value'], 0, $cart_goods_list);
        $smarty->assign('total', $total);
        //ecmoban模板堂 --zhuo end

        /* 团购标志 */
        if ($flow_type == CART_GROUP_BUY_GOODS)
        {
            $smarty->assign('is_group_buy', 1);
        }
        elseif ($flow_type == CART_EXCHANGE_GOODS)
        {
            // 积分兑换 qin
            $smarty->assign('is_exchange_goods', 1);
        }

        $result['content'] = $smarty->fetch('library/order_total.lbi');

    }

    die($json->encode($result));
}
elseif ($_REQUEST['step'] == 'change_needinv')
{
    /*------------------------------------------------------ */
    //-- 改变发票的设置
    /*------------------------------------------------------ */
    include_once('includes/cls_json.php');
    $result = array('error' => '', 'content' => '');
    $json = new JSON();
    $_GET['inv_type'] = !empty($_GET['inv_type']) ? json_str_iconv(urldecode($_GET['inv_type'])) : '';
    $_GET['invPayee'] = !empty($_GET['invPayee']) ? json_str_iconv(urldecode($_GET['invPayee'])) : '';
    $_GET['inv_content'] = !empty($_GET['inv_content']) ? json_str_iconv(urldecode($_GET['inv_content'])) : '';

    $warehouse_id = isset($_REQUEST['warehouse_id'])  ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id = isset($_REQUEST['area_id'])  ? intval($_REQUEST['area_id']) : 0;

    $smarty->assign('warehouse_id', $warehouse_id);
    $smarty->assign('area_id', $area_id);

    /* 取得购物类型 */
    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    /* 获得收货人信息 */
    $consignee = get_consignee($_SESSION['user_id']);

    /* 对商品信息赋值 */
    $cart_goods = cart_goods($flow_type, $_SESSION['cart_value']); // 取得商品列表，计算合计

    if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
    {
        $result['error'] = $_LANG['no_goods_in_cart'];
        die($json->encode($result));
    }
    else
    {
        /* 取得购物流程设置 */
        $smarty->assign('config', $_CFG);

        /* 取得订单信息 */
        $order = flow_order_info();

        if (isset($_GET['need_inv']) && intval($_GET['need_inv']) == 1)
        {
            $order['need_inv']    = 1;
            $order['inv_type']    = trim(stripslashes($_GET['inv_type']));
            $order['inv_payee']   = trim(stripslashes($_GET['inv_payee']));
            $order['inv_content'] = trim(stripslashes($_GET['inv_content']));
			$order['tax_id'] = trim(stripslashes($_GET['tax_id']));
        }
        else
        {
            $order['need_inv']    = 0;
            $order['inv_type']    = '';
            $order['inv_payee']   = '';
            $order['inv_content'] = '';
			$order['tax_id']   = '';
        }

        //ecmoban模板堂 --zhuo start
        $cart_goods_number = get_buy_cart_goods_number($flow_type, $_SESSION['cart_value']);
        $smarty->assign('cart_goods_number', $cart_goods_number);

        $consignee['province_name'] = get_goods_region_name($consignee['province']);
        $consignee['city_name'] = get_goods_region_name($consignee['city']);
        $consignee['district_name'] = get_goods_region_name($consignee['district']);
        $consignee['street_name'] = get_goods_region_name($consignee['street']);
        $consignee['consignee_address'] = $consignee['province_name'] . $consignee['city_name'] . $consignee['district_name'] . $consignee['street_name'] . $consignee['address'];
        $smarty->assign('consignee', $consignee);

        $cart_goods_list = cart_goods($flow_type, $_SESSION['cart_value'], 1); // 取得商品列表，计算合计
        $smarty->assign('goods_list', $cart_goods_list);

        /* 计算订单的费用 */
        $total = order_fee($order, $cart_goods, $consignee, 0, $_SESSION['cart_value'], 0, $cart_goods_list);
        $smarty->assign('total', $total);
        //ecmoban模板堂 --zhuo end

        /* 团购标志 */
        if ($flow_type == CART_GROUP_BUY_GOODS)
        {
            $smarty->assign('is_group_buy', 1);
        }
        elseif ($flow_type == CART_EXCHANGE_GOODS)
        {
            // 积分兑换 qin
            $smarty->assign('is_exchange_goods', 1);
        }

        die($smarty->fetch('library/order_total.lbi'));
    }
}

elseif ($_REQUEST['step'] == 'change_oos')
{
    /*------------------------------------------------------ */
    //-- 改变缺货处理时的方式
    /*------------------------------------------------------ */

    /* 取得订单信息 */
    $order = flow_order_info();

    $order['how_oos'] = intval($_GET['oos']);

    /* 保存 session */
    $_SESSION['flow_order'] = $order;
}
elseif ($_REQUEST['step'] == 'check_surplus')
{
    /*------------------------------------------------------ */
    //-- 检查用户输入的余额
    /*------------------------------------------------------ */
    $surplus   = floatval($_GET['surplus']);
    $user_info = user_info($_SESSION['user_id']);

    if (($user_info['user_money'] + $user_info['credit_line'] < $surplus))
    {
        die($_LANG['surplus_not_enough']);
    }

    exit;
}
elseif ($_REQUEST['step'] == 'check_integral')
{
    /*------------------------------------------------------ */
    //-- 检查用户输入的余额
    /*------------------------------------------------------ */
    $points      = floatval($_GET['integral']);
    $user_info   = user_info($_SESSION['user_id']);
    $_SESSION['cart_value']=$cart_value;
    $flow_points = flow_available_points($cart_value, $region_id, $area_id);  // 该订单允许使用的积分
    $user_points = $user_info['pay_points']; // 用户的积分总数

    if ($points > $user_points)
    {
        die($_LANG['integral_not_enough']);
    }

    if ($points > $flow_points)
    {
        die(sprintf($_LANG['integral_too_much'], $flow_points));
    }

    exit;
}
/*------------------------------------------------------ */
//-- 完成所有订单操作，提交到数据库
/*------------------------------------------------------ */
elseif ($_REQUEST['step'] == 'done')
{
    include_once('includes/lib_clips.php');
    include_once('includes/lib_payment.php');
    $store_id = !empty($_REQUEST['store_id'])   ?   intval($_REQUEST['store_id'])  : 0;//门店id

    $warehouse_id = isset($_REQUEST['warehouse_id'])  ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id = isset($_REQUEST['area_id'])  ? intval($_REQUEST['area_id']) : 0;

    /* 防止重复提交 start */
    $sc_rand = isset($_POST['sc_rand']) && !empty($_POST['sc_rand']) ? dsc_addslashes(trim($_POST['sc_rand'])) : '';
    $sc_guid = isset($_POST['sc_guid']) && !empty($_POST['sc_guid']) ? dsc_addslashes(trim($_POST['sc_guid'])) : '';

    $done_cookie = MD5($sc_guid . "-" . $sc_rand);
    $is_done = 0;

    if (!empty($sc_guid) && !empty($sc_rand) && isset($_COOKIE['done_cookie'])) {

        if (!empty($_COOKIE['done_cookie'])) {
            if (!($_COOKIE['done_cookie'] == $done_cookie)) {
                $is_done = 1;
            }
        } else {
            $is_done = 1;
        }
    }

    /* 防止重复提交 end */
    //@模板堂-bylu 余额支付,白条支付 start
    /* 余额支付 bylu */
    if ($_REQUEST['act'] == 'balance') {

        //取出"余额支付"的支付信息;
        $balance_info=$db->getRow("SELECT pay_name,pay_id, pay_code FROM ".$ecs->table('payment')." WHERE pay_code='balance'");

        $order_sn = addslashes_deep($_REQUEST['order_sn']);
        //通过订单号查询出该订单的总价;
        $sql="SELECT * FROM ".$ecs->table('order_info')." WHERE order_sn='$order_sn'";
        $order_info = $db->getRow($sql);
        $order_amount=floatval($order_info['order_amount']);

        //查询出当前用户的剩余余额;
        $user_money=$db->getOne("SELECT user_money FROM ".$ecs->table('users')." WHERE user_id='".$_SESSION['user_id']."'");
        //如果用户余额足够支付订单;
        if ($user_money >= $order_amount) {
            //判断该订单是否拥有子订单;
            $child_info = $db->getRow("SELECT GROUP_CONCAT(order_id) AS order_id, GROUP_CONCAT(order_sn) AS order_sn FROM " . $ecs->table('order_info') . " WHERE main_order_id='" . $order_info['order_id'] . "'");
            $child_ids = isset($child_info['order_id']) && !empty($child_info['order_id']) ? $child_info['order_id'] : '';
            $child_sn = isset($child_info['order_sn']) && !empty($child_info['order_sn']) ? explode(",", $child_info['order_sn']) : '';

            if (!empty($child_ids)) {
                $order_ids = $order_info['order_id'] . ',' . $child_ids;
            } else {
                $order_ids = $order_info['order_id'];
            }
            /* 扣除余额(记录到"账户日志"表中) */
            if ($order_info['user_id'] > 0) {

                log_account_change($order_info['user_id'], $order_amount * (-1), 0, 0, 0, sprintf($_LANG['pay_order'], $order_info['order_sn']));

                //扣款成功,修改订单为,已确认,已支付;
                $order['order_status'] = OS_CONFIRMED;
                $order['confirm_time'] = gmtime();
                if ($order_info['extension_code'] == 'presale') {//liu
                    $order['pay_status'] = PS_PAYED_PART; //部分付款
                    $order['surplus'] = $order_info['order_amount'];
                    $order['order_amount'] = $order_info['goods_amount'] + $order_info['shipping_fee'] + $order_info['insure_fee'] + $order_info['tax'] - $order_info['discount'] - $order['surplus'];
                } else {
                    $order['pay_status'] = PS_PAYED;
                    $order['surplus'] = $order_amount + $order_info['surplus']; //该字段记录当前订单使用了多少余额支付的;
                    $order['order_amount'] = 0;
                }

                if ($_CFG['sales_volume_time'] == SALES_PAY) {
                    $order['is_update_sale'] = 1;
                }

                $order['pay_time'] = gmtime();
                $order['pay_name'] = $balance_info['pay_name'];
                $order['pay_id'] = $balance_info['pay_id'];
				$order['pay_code'] = $balance_info['pay_code'];

                /* 记录订单操作记录 */
                order_action($order_sn, OS_CONFIRMED, SS_UNSHIPPED, PS_PAYED, $GLOBALS['_LANG']['flow_surplus_pay'], $GLOBALS['_LANG']['buyer']);

                if($child_sn){
                    /* 记录订单操作记录 */
                    foreach($child_sn as $key=>$row){
                        order_action($row, OS_CONFIRMED, SS_UNSHIPPED, PS_PAYED, $GLOBALS['_LANG']['flow_surplus_pay'], $GLOBALS['_LANG']['buyer']);
                    }
                }

                $order['money_paid'] = 0;
                //如果有子订单,处理子订单付款金额;
                if (!empty($child_ids)) {
                    $child_order_amounts = $db->getAll("SELECT order_id,order_amount FROM " . $ecs->table('order_info') . " WHERE order_id IN($order_ids)");
                    foreach ($child_order_amounts as $k => $v) {
                        $order['surplus'] = $v['order_amount'] + $v['surplus'];
                        $re = $db->autoExecute($ecs->table('order_info'), $order, 'UPDATE', "order_id = '" .$v['order_id']. "'");
                    }
                } else {
                    $re = $db->autoExecute($ecs->table('order_info'), $order, 'UPDATE', "order_id in($order_ids)");
                }
                /* 修改"支付日志"中该订单为已支付 */
                $db->query("UPDATE " . $ecs->table('pay_log') . " SET is_paid=1 WHERE order_id IN($order_ids)");

                /* 如果订单金额为0 处理虚拟卡 by wu start */
                $order_arr = explode(',', $order_ids);
                foreach ($order_arr as $order_one) {
                    if ($order['order_amount'] <= 0) {
                        $orderInfo = $GLOBALS['db']->getRow(" SELECT * FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '$order_one' ");
                        $sql = "SELECT goods_id, goods_name, goods_number AS num FROM " .
                                $GLOBALS['ecs']->table('order_goods') .
                                " WHERE is_real = 0 AND extension_code = 'virtual_card'" .
                                " AND order_id = '$orderInfo[order_id]'";

                        $res = $GLOBALS['db']->getAll($sql);

                        $virtual_goods = array();
                        foreach ($res AS $row) {
                            $virtual_goods['virtual_card'][] = array('goods_id' => $row['goods_id'], 'goods_name' => $row['goods_name'], 'num' => $row['num']);
                        }

                        if ($virtual_goods AND $flow_type != CART_GROUP_BUY_GOODS) {
                            /* 虚拟卡发货 */
                            if (virtual_goods_ship($virtual_goods, $msg, $orderInfo['order_sn'], true)) {
                                /* 如果没有实体商品，修改发货状态，送积分和红包 */
                                $sql = "SELECT COUNT(*)" .
                                        " FROM " . $ecs->table('order_goods') .
                                        " WHERE order_id = '$orderInfo[order_id]' " .
                                        " AND is_real = 1";
                                if ($db->getOne($sql) <= 0) {
                                    /* 修改订单状态 */
                                    update_order($orderInfo['order_id'], array('shipping_status' => SS_SHIPPED, 'shipping_time' => gmtime()));

                                    /* 如果订单用户不为空，计算积分，并发给用户；发红包 */
                                    if ($orderInfo['user_id'] > 0) {
                                        /* 取得用户信息 */
                                        $user = user_info($orderInfo['user_id']);

                                        /* 计算并发放积分 */
                                        $integral = integral_to_give($orderInfo);
                                        $gave_custom_points = integral_of_value($integral['custom_points']) - $orderInfo['integral']; // by kong 计算赠送积分
                                        if ($gave_custom_points < 0) {
                                            $gave_custom_points = 0;
                                        }
                                        log_account_change($orderInfo['user_id'], 0, 0, intval($integral['rank_points']), intval($integral['custom_points']), sprintf($_LANG['order_gift_integral'], $orderInfo['order_sn']));

                                        /* 发放红包 */
                                        send_order_bonus($orderInfo['order_id']);

                                        /* 发放优惠券 bylu */
                                        send_order_coupons($orderInfo['order_id']);
                                    }
                                }
                            }
                        }
                    }
                }
                /* 如果订单金额为0 处理虚拟卡 by wu end */

                /* 众筹状态的更改 by wu */
                update_zc_project($order_info['order_id']);

                //付款成功创建快照
                create_snapshot($order_info['order_id']);

                if($order_info['extension_code'] == 'presale'){
                    //付款成功后增加预售人数
                    get_presale_num($order_info['order_id']);
                }

                /* 更新商品销量 */
                get_goods_sale($order_info['order_id']);

                /* 如果需要，发短信 */
                $sql = "SELECT ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '" . $order_info['order_id'] . "'";
                $ru_id = $GLOBALS['db']->getOne($sql, true);

                $shop_name = get_shop_name($ru_id, 1);

                if ($ru_id == 0) {
                    $sms_shop_mobile = $GLOBALS['_CFG']['sms_shop_mobile'];
                } else {
                    $sql = "SELECT mobile FROM " . $GLOBALS['ecs']->table('seller_shopinfo') . " WHERE ru_id = '$ru_id'";
                    $sms_shop_mobile = $GLOBALS['db']->getOne($sql, true);
                }

                $order_result = array();
                if ($GLOBALS['_CFG']['sms_order_payed'] == '1' && $sms_shop_mobile) {

                    $order_region = get_flow_user_region($order_info['order_id']);
                    //阿里大鱼短信接口参数
                    $smsParams = array(
                        'shop_name' => $shop_name,
                        'shopname' => $shop_name,
                        'order_sn' => $order_info['order_sn'],
                        'ordersn' => $order_info['order_sn'],
                        'consignee' => $order_info['consignee'],
                        'order_region' => $order_region,
                        'orderregion' => $order_region,
                        'address' => $order_info['address'],
                        'order_mobile' => $order_info['mobile'],
                        'ordermobile' => $order_info['mobile'],
                        'mobile_phone' => $sms_shop_mobile,
                        'mobilephone' => $sms_shop_mobile
                    );

                    if ($GLOBALS['_CFG']['sms_type'] == 0) {
                        huyi_sms($smsParams, 'sms_order_payed');
                    } elseif ($GLOBALS['_CFG']['sms_type'] >= 1) {
                        $order_result = sms_ali($smsParams, 'sms_order_payed'); //阿里大鱼短信变量传值，发送时机传值
                    }
                }

                //门店处理
                $sql = 'SELECT id, store_id, order_id FROM '.$GLOBALS['ecs']->table("store_order")." WHERE order_id = '" . $order_info['order_id'] . "' LIMIT 1";
                $stores_order = $GLOBALS['db']->getRow($sql);
                $store_result = array();
                if ($stores_order && $stores_order['store_id'] > 0) {
                    if ($order_info['mobile']) {
                        $user_mobile_phone = $order_info['mobile'];
                    } else {
                        $sql = "SELECT mobile_phone FROM " . $GLOBALS['ecs']->table('users') . " WHERE user_id = '" . $_SESSION['user_id'] . "'";
                        $user_mobile_phone = $GLOBALS['db']->getOne($sql, true);
                    }

                    if ($user_mobile_phone) {

                        $pick_code = substr($order['order_sn'], -3) . rand(0, 9) . rand(0, 9) . rand(0, 9);

                        $sql = "UPDATE " . $GLOBALS['ecs']->table('store_order') . " SET pick_code = '$pick_code' WHERE id = '" . $stores_order['id'] . "'";
                        $db->query($sql);

                        //门店短信处理
                        $sql = "SELECT id, country, province, city, district, stores_address, stores_name, stores_tel FROM " . $GLOBALS['ecs']->table('offline_store') . " WHERE id = '" . $stores_order['store_id'] . "' LIMIT 1";
                        $stores_info = $GLOBALS['db']->getRow($sql);
                        $store_address = get_area_region_info($stores_info) . $stores_info['stores_address'];
                        $user_name = isset($_SESSION['user_name']) && !empty($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
                        //门店订单->短信接口参数
                        $store_smsParams = array(
                            'user_name' => $user_name,
                            'username' => $user_name,
                            'order_sn' => $order_info['order_sn'],
                            'ordersn' => $order_info['order_sn'],
                            'code' => $pick_code,
                            'store_address' => $store_address,
                            'storeaddress' => $store_address,
                            'mobile_phone' => $user_mobile_phone,
                            'mobilephone' => $user_mobile_phone
                        );
                        if ($GLOBALS['_CFG']['sms_type'] == 0) {
                            if ($stores_order['store_id'] > 0 && !empty($store_smsParams)) {
                                huyi_sms($store_smsParams, 'store_order_code');
                            }
                        } elseif ($GLOBALS['_CFG']['sms_type'] >= 1) {
                            if ($stores_order['store_id'] > 0 && !empty($store_smsParams)) {
                                $store_result = sms_ali($store_smsParams, 'store_order_code'); //阿里大鱼短信变量传值，发送时机传值
                            }
                        }
                    }
                }

                if ($GLOBALS['_CFG']['sms_type'] >= 1) {

                    $sms_send = array($store_result, $order_result);
                    $resp = $GLOBALS['ecs']->ali_yu($sms_send, 1);
                }

                //付款成功,跳转到 支付成功页;
                header("location:flow.php?step=pay_success&order_id=" . $order_info['order_id']."&store_id=$store_id");
                die;
            }
        } else {
            //余额不足;
            show_message($GLOBALS["_LANG"]['balance_not_enough'], $GLOBALS["_LANG"]['go_pay'], '');
            die;
        }

        //白条支付 bylu;
    }else if($_REQUEST['act'] == 'chunsejinrong'){

        $_REQUEST['order_sn'] = trim($_REQUEST['order_sn']);
        $order_sn = dsc_addslashes($_REQUEST['order_sn']);

        //取出"白条支付"的支付信息;
        $bt_payment_info = $db->getRow("SELECT pay_name,pay_id,pay_code FROM " . $ecs->table('payment') . " WHERE pay_code='chunsejinrong' LIMIT 1");

        //通过订单号查询出该订单的总价;
        $sql = "SELECT * FROM " . $ecs->table('order_info') . " WHERE order_sn = '$order_sn' LIMIT 1";
        $order_info = $db->getRow($sql);

        //检查会员白条余额是否足够
        $bt_other = array(
            'user_id' => $user_id
        );
        $bt_info = get_baitiao_info($bt_other);

        $sql = "SELECT SUM(b.stages_one_price * (b.stages_total - b.yes_num)) AS total_amount FROM " . $ecs->table('baitiao_log') . " AS b " .
                " WHERE b.user_id = '$user_id' AND b.is_repay = 0 AND b.is_refund = 0 LIMIT 1";
        $repay_bt = $db->getOne($sql);

        if (!$bt_info) {
            show_message( $_LANG['Ious_error_one'], $_LANG['back_up_page'], '');
        }

        $remain_amount = floatval($bt_info['amount']) - floatval($repay_bt);

        //如果当前订单价格,大于可用白条余额;
        if ($order_info['order_amount'] > $remain_amount) {
            show_message($_LANG['Ious_error_two'], $_LANG['back_up_page'], '');
        } else {
            //判断该订单是否拥有子订单;
            $child_ids = $db->getOne("SELECT GROUP_CONCAT(order_id) order_id FROM " . $ecs->table('order_info') . " WHERE main_order_id = '" . $order_info['order_id'] . "'");
            if (!empty($child_ids)) {
                $order_ids = $order_info['order_id'] . ',' . $child_ids;
            } else {
                $order_ids = $order_info['order_id'];
            }

            //先取出当前用户的白条日志信息 bylu;
            $user_baitiao_info = $db->getAll("SELECT * FROM " . $ecs->table('baitiao_log') . " WHERE is_repay = 0 AND user_id = '$user_id'");

            foreach ($user_baitiao_info as $k => $v) {
                if ($user_baitiao_info[$k]['is_stages'] == 1) {
                    $repay_date = unserialize($user_baitiao_info[$k]['repay_date']);
                    $over_date[] = strtotime($repay_date[$user_baitiao_info[$k]['yes_num'] + 1]);
                } else {
                    $over_date[] = $user_baitiao_info[$k]['repay_date'];
                }
                if (gmtime() >= $over_date[$k]) {
                    show_message($_LANG['Ious_error_Three'], $_LANG['back_up_page'], '');
                }
            }

            //更新订单状态为已支付;
            $order['order_status'] = OS_CONFIRMED;
            $order['pay_time'] = gmtime();
            $order['pay_name'] = $bt_payment_info['pay_name'];
            $order['pay_code'] = $bt_payment_info['pay_code'];
            $order['pay_id'] = $bt_payment_info['pay_id'];
            $order['money_paid'] = floatval($order_info['order_amount']);
            $order['confirm_time'] = gmtime();
            $order['pay_status'] = PS_PAYED;
            $order['order_amount'] = 0;
            $db->autoExecute($ecs->table('order_info'), $order, 'update', "order_id in($order_ids)");

            //处理子订单的已付款金额;
            if (!empty($child_ids)) {
                $child_order_amounts = $db->getAll("SELECT order_id,order_amount FROM " . $ecs->table('order_info') . " WHERE order_id IN($child_ids)");
                foreach ($child_order_amounts as $k => $v) {

                    $child_order_other['order_status'] = OS_CONFIRMED;
                    $child_order_other['pay_time'] = gmtime();
                    $child_order_other['pay_name'] = $bt_payment_info['pay_name'];
                    $child_order_other['pay_code'] = $bt_payment_info['pay_code'];
                    $child_order_other['pay_id'] = $bt_payment_info['pay_id'];
                    $child_order_other['money_paid'] = $v['order_amount'];
                    $child_order_other['confirm_time'] = gmtime();
                    $child_order_other['pay_status'] = PS_PAYED;
                    $child_order_other['order_amount'] = 0;

                    $db->autoExecute($ecs->table('order_info'), $order, 'UPDATE', "order_id = '" .$v['order_id']. "'");
                }
            }

            /* 修改"支付日志"中该订单为已支付 */
            $db->query("UPDATE ".$ecs->table('pay_log')." SET is_paid=1 WHERE order_id IN($order_ids)");

            $stages_total = 0;
            $stages_one_price = 0;

            //如果是白条分期商品;
            $stages_other = array(
                'order_sn' => $order_sn
            );

            $stages_info = get_stages_info($stages_other);

            if ($stages_info) {
                $is_stages = 1; //是否分期;
                $stages_total = $stages_info['stages_total']; //分期总期数;
                $stages_one_price = $stages_info['stages_one_price']; //每期还款额;
                $repay_date = $stages_info['repay_date']; //白条分期的还款日期;
            } else {
                $repcheckorderay_date = gmtime() + ($bt_info['repay_term'] * 24 * 3600); //还款日期;
            }

            //将白条消费记录插入白条日志表;
            $bt_insert_other = array(
                'baitiao_id' => $bt_info['baitiao_id'],
                'user_id' => $user_id,
                'use_date' => gmtime(),
                'repay_date' => $repay_date,
                'order_id' => $order_info['order_id'],
                'is_repay' => 0,
                'is_stages' => $is_stages,
                'stages_total' => $stages_total,
                'stages_one_price' => $stages_one_price,
                'yes_num' => 0
            );
            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('baitiao_log'), $bt_insert_other, 'INSERT');
            $bt_log_id = $GLOBALS['db']->insert_id();

            if ($stages_total > 0 && $is_stages == 1) {
                for ($i = 1; $i <= $stages_total; $i++) {
                    $sql = "SELECT id FROM " . $GLOBALS['ecs']->table('baitiao_pay_log') . " WHERE log_id = '$bt_log_id' AND baitiao_id = '" . $bt_info['baitiao_id'] . "' AND stages_num = '" . $i . "'";
                    if (!$GLOBALS['db']->getOne($sql, true)) {
                        $pay_log_other = array(
                            'log_id' => $bt_log_id,
                            'baitiao_id' => $bt_info['baitiao_id'],
                            'stages_num' => $i,
                            'stages_price' => $stages_one_price,
                            'add_time' => gmtime()
                        );
                        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('baitiao_pay_log'), $pay_log_other, 'INSERT');
                    }
                }

                $sql = "SELECT COUNT(*) FROM " . $ecs->table('baitiao_pay_log') . " WHERE log_id = '$bt_log_id'";
                $bt_pay_count = $db->getOne($sql);
                if ($stages_total == $bt_pay_count) {
                    $baitiao_log_other['pay_num'] = 1;
                    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('baitiao_log'), $baitiao_log_other, 'UPDATE', "log_id = '$bt_log_id'");
                }
            }

            //付款成功创建快照
            create_snapshot($order_info['order_id']);

            if ($order_info['extension_code'] == 'presale') {
                //付款成功后增加预售人数
                get_presale_num($order_info['order_id']);
            }

            //付款成功,跳转到 支付成功页;
            header("location:flow.php?step=pay_success&order_id=" . $order_info['order_id'] . "&store_id=$store_id");
        }
        //@模板堂-bylu 余额支付,白条支付 end

    }else {
        $where_flow = '';
        $pay_type = isset($_POST['pay_type']) ? intval($_POST['pay_type']) : 0; //取得支付类型
        $done_cart_value = isset($_POST['done_cart_value']) ? htmlspecialchars($_POST['done_cart_value']) : 0; //取得购买进货单商品ID

        if(empty($done_cart_value)){
            header("Location:" .$GLOBALS['ecs']->url(). "\n");
            exit;
        }

        /* 取得购物类型 */
        $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

        /* 检查进货单中是否有商品 */
        $sql = " SELECT COUNT(*) FROM " . $ecs->table('cart') .
            " WHERE " . $sess_id . " AND rec_id " . db_create_in($done_cart_value);
        if (!$db->getOne($sql)) {
            //@author-bylu 这里跳转到一个新的页面,目的是当订单结算页刷新页面订单还在;
            header("Location:flow.php?step=order_reload\n");
            exit;
        }

        if ($is_done == 1) {
            header("Location:flow.php?step=cart\n");
            exit;
        }

        /* 检查商品、货品库存 start */
        /* 如果使用库存，且下订单时减库存，则减少库存 */  //--库存管理use_storage 1为开启 0为未启用-------  SDT_PLACE：0为发货时 1为下订单时
        if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_PLACE) {
            $cart_goods_stock = get_cart_goods($done_cart_value);
            $_cart_goods_stock = array();
            foreach ($cart_goods_stock['goods_list'] as $value) {
                $_cart_goods_stock[$value['rec_id']] = $value['goods_number'];
            }


            flow_cart_stock($_cart_goods_stock, $store_id, $warehouse_id, $area_id);
            unset($cart_goods_stock, $_cart_goods_stock);
        }
        /* 检查商品库存 end */


        //活动是否存在
        $cart_goods_stock = get_cart_activity($done_cart_value);
        foreach ($cart_goods_stock as $cart) {
            activity_is_done($cart);
        }

        /*
         * 检查用户是否已经登录
         * 如果用户已经登录了则检查是否有默认的收货地址
         * 如果没有登录则跳转到登录和注册页面
         */
        if (empty($_SESSION['direct_shopping']) && $_SESSION['user_id'] == 0) {
            /* 用户没有登录且没有选定匿名购物，转向到登录页面 */
            ecs_header("Location: user.php\n");
            exit;
        }

        $consignee = get_consignee($_SESSION['user_id']);
        /* 检查收货人信息是否完整 */
        if (!check_consignee_info($consignee, $flow_type) && $store_id == 0) {
            /* 如果不完整则转向到收货人信息填写界面 */
            ecs_header("Location: user.php?act=address_list\n"); //by wu
            exit;
        }

        $_POST['how_oos'] = isset($_POST['how_oos']) ? intval($_POST['how_oos']) : 0;
        $_POST['card_message'] = isset($_POST['card_message']) ? compile_str($_POST['card_message']) : '';
        $_POST['inv_type'] = !empty($_POST['inv_type']) ? compile_str($_POST['inv_type']) : '';
        $_POST['inv_payee'] = isset($_POST['inv_payee']) ? compile_str($_POST['inv_payee']) : '';
		$_POST['tax_id'] = isset($_POST['tax_id']) ? compile_str($_POST['tax_id']) : '';
        $_POST['inv_content'] = isset($_POST['inv_content']) ? compile_str($_POST['inv_content']) : '';
        $_POST['postscript'] = isset($_POST['postscript']) ? compile_str($_POST['postscript']) : '';

        //插入订单信息 ecmoban模板堂 --zhuo

        if (count($_POST['shipping']) == 1) {
            $shipping['shipping_id'] = $_POST['shipping'][0];
        } else {
            $shipping = get_order_post_shipping($_POST['shipping'],$_POST['shipping_code'],$_POST['shipping_type'], $_POST['ru_id']);
        }

        $order = array(
            'shipping_id' => $shipping['shipping_id'], //ecmoban模板堂 --zhuo
            'shipping_type' => $shipping['shipping_type'], //ecmoban模板堂 --zhuo
            'pay_id' => isset($_POST['payment']) ? intval($_POST['payment']) : 0,
            //'pay_code' => $pay_code,
            'pay_code' => isset($_POST['pay_code']) ? addslashes(trim($_POST['pay_code'])) : '',
            'pack_id' => isset($_POST['pack']) ? intval($_POST['pack']) : 0,
            'card_id' => isset($_POST['card']) ? intval($_POST['card']) : 0,
            'card_message' => trim($_POST['card_message']),
            'surplus' => isset($_POST['surplus']) ? floatval($_POST['surplus']) : 0.00,
            'integral' => isset($_POST['integral']) ? intval($_POST['integral']) : 0,
            'bonus_id' => isset($_POST['bonus']) ? intval($_POST['bonus']) : 0,
            'uc_id' => isset($_POST['uc_id']) ? intval($_POST['uc_id']) : 0, //优惠券id bylu
            'not_freightfree' => isset($_POST['not_freightfree']) ? intval($_POST['not_freightfree']) : 0, //优惠券是否含有地区免邮条件  0：支持 1：不支持
            'vc_id' => isset($_POST['vc_id']) ? intval($_POST['vc_id']) : 0, //储值卡ID
            'need_inv' => isset($_POST['inv_payee']) ? 1 : 0,
            'inv_type' => isset($_CFG['invoice_type']['type'][0]) ? $_CFG['invoice_type']['type'][0] : '',
            'inv_payee' => isset($_POST['inv_payee']) ? trim($_POST['inv_payee']) : '',
            'tax_id' => isset($_POST['tax_id']) ? trim($_POST['tax_id']) : '', //纳税人识别号
            'inv_content' => isset($_POST['inv_content']) ? trim($_POST['inv_content']) : '',
            'postscript' => trim($_POST['postscript']),
            'how_oos' => isset($_LANG['oos'][$_POST['how_oos']]) ? addslashes($_LANG['oos'][$_POST['how_oos']]) : '',
            'need_insure' => isset($_POST['need_insure']) ? intval($_POST['need_insure']) : 0,
            'user_id' => $_SESSION['user_id'],
            'add_time' => gmtime(),
            //'order_status' => $order_status,
            'order_status' => OS_UNCONFIRMED,
            'shipping_status' => SS_UNSHIPPED,
            'mobile' => isset($_POST['store_mobile']) && !empty($_POST['store_mobile']) ? addslashes(trim($_POST['store_mobile'])) : '',
            'pay_status' => PS_UNPAYED,
            'agency_id' => get_agency_by_regions(array($consignee['country'], $consignee['province'], $consignee['city'], $consignee['district'])),
            'point_id' => isset($_POST['point_id']) ? intval($_POST['point_id']) : 0,
            'shipping_dateStr' => isset($_POST['shipping_dateStr']) ? trim($_POST['shipping_dateStr']) : '',
            'invoice_type' => isset($_POST['invoice_type']) ? intval($_POST['invoice_type']) : 0,
        );


        /* 扩展信息 */
        if (isset($_SESSION['flow_type']) && intval($_SESSION['flow_type']) != CART_GENERAL_GOODS) {
            $order['extension_code'] = $_SESSION['extension_code'];
            $order['extension_id'] = $_SESSION['extension_id'];
        } else {
            $order['extension_code'] = '';
            $order['extension_id'] = 0;
        }

        $user_id = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

        // 检查有没有开启线上支付 qin
        $sql_pay = "SELECT pay_online, ec_salt, pay_password FROM " . $ecs->table('users_paypwd') . " WHERE user_id = '" . $_SESSION['user_id'] . "' LIMIT 1";
        $pay_online = $db->getRow($sql_pay);

        if ($pay_online['pay_online']) {

            $pay_surplus = isset($_REQUEST['surplus']) ? addslashes(trim($_REQUEST['surplus'])) : ''; //余额
            $pay_integral = isset($_REQUEST['integral']) ? addslashes(trim($_REQUEST['integral'])) : ''; //消费积分
            $pay_pwd = isset($_REQUEST['pay_pwd']) ? addslashes(trim($_REQUEST['pay_pwd'])) : '';
            $pay_pwd_error = isset($_REQUEST['pay_pwd_error']) ? intval($_REQUEST['pay_pwd_error']) : 0;

            if ($order['pay_code'] == 'onlinepay' || $pay_surplus > 0 || $pay_integral > 0) {
                if ($pay_pwd_error == 0 && !empty($pay_pwd)) {
                    $ec_salt = $pay_online['ec_salt'];
                    $new_password = md5(md5($pay_pwd) . $ec_salt);
                    if (!empty($pay_pwd) && ($new_password != $pay_online['pay_password'])) {
                        show_message($_LANG['pay_password_packup_error'], $_LANG['back'], '', 'error');
                    }
                }
            }
        }

        /* 检查积分余额是否合法 */
        if ($user_id > 0) {
            $user_info = user_info($user_id);

            $order['surplus'] = min($order['surplus'], $user_info['user_money'] + $user_info['credit_line']);
            if ($order['surplus'] < 0) {
                $order['surplus'] = 0;
            }

            // 查询用户有多少积分
            $flow_points = flow_available_points($done_cart_value, $region_id, $area_id);  // 该订单允许使用的积分
            $user_points = $user_info['pay_points']; // 用户的积分总数

            $order['integral'] = min($order['integral'], $user_points, $flow_points);
            if ($order['integral'] < 0) {
                $order['integral'] = 0;
            }
        } else {
            $order['surplus'] = 0;
            $order['integral'] = 0;
        }

        /* 检查红包是否存在 */
        if ($order['bonus_id'] > 0) {
            $bonus = bonus_info($order['bonus_id']);

            if (empty($bonus) || $bonus['user_id'] != $user_id || $bonus['order_id'] > 0 || $bonus['min_goods_amount'] > cart_amount(true, $flow_type)) {
                $order['bonus_id'] = 0;
            }
        } elseif (isset($_POST['bonus_psd'])) {
            $bonus_psd = trim($_POST['bonus_psd']);
            $bonus = bonus_info(0, $bonus_psd);
            $now = gmtime();
            if (empty($bonus) || $bonus['user_id'] > 0 || $bonus['order_id'] > 0 || $bonus['min_goods_amount'] > cart_amount(true, $flow_type) || $now > $bonus['use_end_date']) {
            } else {
                if ($user_id > 0) {
                    $sql = "UPDATE " . $ecs->table('user_bonus') . " SET user_id = '$user_id' WHERE bonus_id = '$bonus[bonus_id]' LIMIT 1";
                    $db->query($sql);
                }
                $order['bonus_id'] = $bonus['bonus_id'];
                $order['bonus_psd'] = $bonus_psd;
            }
        }

        /* 检查储值卡ID是否存在 */
        if ($order['vc_id'] > 0) {
            $value_card = value_card_info($order['vc_id']);

            if (empty($value_card) || $value_card['user_id'] != $user_id) {
                $order['vc_id'] = 0;
            }
        } elseif (isset($_POST['value_card_psd'])) {
            $value_card_psd = trim($_POST['value_card_psd']);
            $value_card = value_card_info(0, $value_card_psd);
            $now = gmtime();
            if (!(empty($value_card) || $value_card['user_id'] > 0)) {
                if ($user_id > 0 && empty($value_card['end_time'])) {
                    $end_time = ", end_time = '" . local_strtotime("+" . $value_card['vc_indate'] . " months ") . "' ";
                    $sql = " UPDATE " . $ecs->table('value_card') . " SET user_id = '$user_id', bind_time = '" . gmtime() . "'" . $end_time . " WHERE vid = '$value_card[vid]' ";
                    $db->query($sql);
                    $order['vc_id'] = $value_card['vid'];
                    $order['vc_psd'] = $value_card_psd;
                } elseif ($now > $value_card['end_time']) {
                    $order['vc_id'] = 0;
                }
            }
        }

        /* 检查优惠券是否存在 bylu */
        if ($order['uc_id'] > 0) {
            $coupons = get_coupons($order['uc_id']);

            if (empty($coupons) || $coupons['user_id'] != $user_id || $coupons['is_use'] == 1 || $coupons['cou_man'] > cart_amount(true, $flow_type)) {
                $order['uc_id'] = 0;
            }
        }

        $cart_goods_list = cart_goods($flow_type, $done_cart_value, 1, $region_id, $area_info['region_id']); // 取得商品列表，计算合计
        /* 订单中的商品 */
        $cart_goods = cart_goods($flow_type, $done_cart_value);
        if (empty($cart_goods)) {
            show_message($_LANG['no_goods_in_cart'], $_LANG['back_home'], './', 'warning');
        }

        /* 检查商品总额是否达到最低限购金额 */
        if ($flow_type == CART_GENERAL_GOODS && cart_amount(true, CART_GENERAL_GOODS) < $_CFG['min_goods_amount']) {
            show_message(sprintf($_LANG['goods_amount_not_enough'], price_format($_CFG['min_goods_amount'], false)));
        }

        /* 收货人信息 */
        foreach ($consignee as $key => $value) {
            if($key == 'mobile' && !empty($order['mobile'])){
                $order[$key] = $order['mobile'];  //门店取货手机号
            }else{
                $order[$key] = addslashes($value);
            }
        }

        /* 判断是不是实体商品 */
        foreach ($cart_goods AS $val) {
            /* 统计实体商品的个数 */
            if ($val['is_real']) {
                $is_real_good = 1;
            }
        }

        //切换配送方式
        foreach($cart_goods_list as $key=>$val){
            foreach($_POST['ru_id'] as $kk=>$vv)
            {
                if($val['ru_id'] == $vv)
                {
                    $cart_goods_list[$key]['tmp_shipping_id'] = $_POST['shipping'][$kk];
                    continue;
                }
            }
        }

        /* 订单中的总额 */
        $total = order_fee($order, $cart_goods, $consignee, 1, $done_cart_value, $pay_type, $cart_goods_list, $region_id, $area_id,$store_id);

        $order['bonus'] = $total['bonus'];
        $order['coupons'] = $total['coupons']; //优惠券金额 bylu
        $order['use_value_card'] = $total['use_value_card']; //储值卡使用金额
        $order['goods_amount'] = $total['goods_price'];
        $order['total_goods_amount'] = $total['total_goods_amount'];
        $order['cost_amount'] = $total['cost_price'];
        $order['discount'] = $total['discount'];
        $order['surplus'] = $total['surplus'];
        $order['tax'] = $total['tax'];

        // 进货单中的商品能享受红包支付的总额
        $discount_amout = compute_discount_amount($done_cart_value);
        // 红包和积分最多能支付的金额为商品总额
        $temp_amout = $order['goods_amount'] - $discount_amout;
        if ($temp_amout <= 0) {
            $order['bonus_id'] = 0;
        }

        /* 配送方式 ecmoban模板堂 --zhuo */
        if (!empty($order['shipping_id'])) {

            $order['shipping_code'] = addslashes($shipping['shipping_code']);

            if (count($_POST['shipping']) == 1) {
                $shipping = shipping_info($order['shipping_id']);
            }

            $order['shipping_isarr'] = 0;
            $order['shipping_name'] = addslashes($shipping['shipping_name']);
            $shipping_name = !empty($order['shipping_name']) ? explode(",", $order['shipping_name']) : '';
            if($shipping_name && count($shipping_name) > 1){
                $order['shipping_isarr'] = 1;
            }
        }

        $order['shipping_fee'] = $total['shipping_fee'];
        $order['insure_fee'] = $total['shipping_insure'];

        if ($order['pay_id'] > 0) {
            $payment = payment_info($order['pay_id']);
            $order['pay_name'] = addslashes($payment['pay_name']);
			$order['pay_code'] = addslashes($payment['pay_code']);
        }

        $order['pay_fee'] = $total['pay_fee'];
        $order['cod_fee'] = $total['cod_fee'];

        /* 商品包装 */
        if ($order['pack_id'] > 0) {
            $pack = pack_info($order['pack_id']);
            $order['pack_name'] = addslashes($pack['pack_name']);
        }
        $order['pack_fee'] = $total['pack_fee'];

        /* 祝福贺卡 */
        if ($order['card_id'] > 0) {
            $card = card_info($order['card_id']);
            $order['card_name'] = addslashes($card['card_name']);
        }
        $order['card_fee'] = $total['card_fee'];

        $order['order_amount'] = number_format($total['amount'], 2, '.', '');

        //ecmoban模板堂 --zhuo
        if (isset($_SESSION['direct_shopping']) && !empty($_SESSION['direct_shopping'])) {
            $where_flow = "?step=checkout&direct_shopping=" . $_SESSION['direct_shopping'];
        }

        /* 如果全部使用余额支付，检查余额是否足够 */
        if ($payment['pay_code'] == 'balance' && $order['order_amount'] > 0) {
            if ($order['surplus'] > 0) //余额支付里如果输入了一个金额
            {
                $order['order_amount'] = $order['order_amount'] + $order['surplus'];
                $order['surplus'] = 0;
            }

            if ($order['order_amount'] > ($user_info['user_money'] + $user_info['credit_line'])) {
                //ecmoban模板堂 --zhuo
                $location = "flow.php";
                if ($_SESSION['flow_type'] == CART_PRESALE_GOODS) {

                    $location = "presale.php";
                    $where_flow = "?id=" . $order['extension_id'] . "&act=view";
                } elseif ($_SESSION['flow_type'] == CART_GROUP_BUY_GOODS) {
                    $location = "group_buy.php";
                    $where_flow = "?act=view&id=" . $order['extension_id'];
                }
                show_message($_LANG['balance_not_enough'], $_LANG['back_up_page'], $location . $where_flow);
            } else {
                $order['surplus'] = $order['order_amount'];
                $has_pay = true;
//                $order['order_amount'] = 0;
//                $snapshot = true;
                //by zxk预订付款
//                if ($_SESSION['flow_type'] == CART_PRESALE_GOODS || $_SESSION['flow_type'] == CART_GROUP_BUY_GOODS) {//预售--首次付定金
//                    $order['surplus'] = $order['order_amount'];
//                    $order['pay_status'] = PS_PAYED_PART; //部分付款
//                    $order['order_status'] = OS_CONFIRMED; //已确认
//                    $order['order_amount'] = $order['goods_amount'] + $order['shipping_fee'] + $order['insure_fee'] + $order['tax'] - $order['discount'] - $order['surplus'];
//                } else {
//                    $order['surplus'] = $order['order_amount'];
//                    $order['order_amount'] = 0;
//                    $snapshot = true;
//                }
            }
        }

        $stores_sms = 0;//门店提货码是否发送信息 0不发送  1发送
        /* 如果订单金额为0（使用余额或积分或红包支付），修改订单状态为已确认、已付款 */
//        if ($order['order_amount'] <= 0) {
//            $order['order_status'] = OS_CONFIRMED;
//            $order['confirm_time'] = gmtime();
//            $order['pay_status'] = PS_PAYED;
//            $order['pay_time'] = gmtime();
//            $order['order_amount'] = 0;
//            $stores_sms = 1;
//        }

        $order['integral_money'] = $total['integral_money'];
        $order['integral'] = $total['integral'];

        if ($order['extension_code'] == 'exchange_goods') {
            $order['integral_money'] = value_of_integral($total['exchange_integral']);
            $order['integral'] = $total['exchange_integral'];
            $order['goods_amount'] = value_of_integral($total['exchange_integral']);
        }

        $order['from_ad'] = !empty($_SESSION['from_ad']) ? $_SESSION['from_ad'] : '0';
        $order['referer'] = !empty($_SESSION['referer']) ? addslashes($_SESSION['referer']) : '';

        /* 记录扩展信息 */
        if ($flow_type != CART_GENERAL_GOODS) {
            $order['extension_code'] = $_SESSION['extension_code'];
            $order['extension_id'] = $_SESSION['extension_id'];
        }

        $affiliate = unserialize($_CFG['affiliate']);
        if (isset($affiliate['on']) && $affiliate['on'] == 1 && $affiliate['config']['separate_by'] == 1) {
            //推荐订单分成
            $parent_id = get_affiliate();
            if ($user_id == $parent_id) {
                $parent_id = 0;
            }
        } elseif (isset($affiliate['on']) && $affiliate['on'] == 1 && $affiliate['config']['separate_by'] == 0) {
            //推荐注册分成
            $parent_id = 0;
        } else {
            //分成功能关闭
            $parent_id = 0;
        }
        $order['parent_id'] = $parent_id;

        $goodsIn = '';
        $cartValue = !empty($done_cart_value) ? $done_cart_value : '';
        $is_cart = get_is_cart_goods($cartValue);

        $new_order_id = 0;

        if ($is_cart && $is_done == 0) {
            /* 插入订单表 */
            $error_no = 0;
            do {
                /* 不支持免邮时设置优惠券为不使用 */
                if($order['not_freightfree'] == 1){
                    $order['uc_id'] = 0;
                }

                $order['order_sn'] = get_order_sn(); //获取新订单号
                $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_info'), $order, 'INSERT');

                $error_no = $GLOBALS['db']->errno();
                if ($error_no > 0 && $error_no != 1062) {
                    die($GLOBALS['db']->errorMsg());
                }
            } while ($error_no == 1062); //如果是订单号重复则重新提交数据

            $new_order_id = $db->insert_id();
        }

        $order['order_id'] = $new_order_id;

        /* 插入支付日志 */
        $order['log_id'] = insert_pay_log($new_order_id, $order['order_amount'], PAY_ORDER);

        if ($is_cart && $is_done == 0) {
            //微分销start
            if (file_exists(MOBILE_DRP)) {

                $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('drp_config') . " WHERE code = 'drp_affiliate' LIMIT 1";
                $drp_affiliate = $GLOBALS['db']->getRow($sql);
                $drp_affiliate = unserialize($drp_affiliate['value']);
                empty($drp_affiliate) && $drp_affiliate = array();
                if (isset($drp_affiliate['on']) && $drp_affiliate['on'] == 1) {
                    $parent_id = get_affiliate();
                    if ($parent_id) {
                        $is_distribution = 1;
                    } else {
                        $is_distribution = 0;
                    }
                }

                if (!empty($cartValue)) {
                    $goodsIn = " and ca.rec_id in($cartValue)";
                }

                //by zxk  进货单进去不筛选订单类型
                $flow_type_str = " AND ca.rec_type = '$flow_type'";
                if ($flow_type == -1) {
                    $flow_type_str = '';
                }

                $sql = "INSERT INTO " . $ecs->table('order_goods') . "( " .
                        "order_id, goods_id, goods_name, goods_sn, product_id, goods_number, market_price, goods_price," .
                        "goods_attr, is_real, extension_code, extension_id, parent_id, is_gift, model_attr, goods_attr_id, ru_id, shopping_fee, warehouse_id, area_id, freight, tid, shipping_fee, is_distribution, drp_money, commission_rate) " .
                        " SELECT '$new_order_id', ca.goods_id, ca.goods_name, ca.goods_sn, ca.product_id, ca.goods_number, ca.market_price, ca.goods_price, ca.goods_attr, " .
                        "ca.is_real, ca.extension_code, ca.extension_id, ca.parent_id, ca.is_gift, ca.model_attr, ca.goods_attr_id, ca.ru_id, ca.shopping_fee, ca.warehouse_id, ca.area_id, ca.freight, ca.tid, ca.shipping_fee," .
                        "g.is_distribution*'$is_distribution' AS is_distribution, " .
                        "g.dis_commission * g.is_distribution * ca.goods_price * ca.goods_number / 100 * '$is_distribution' AS drp_money, ca.commission_rate" .
                        " FROM " . $ecs->table('cart') . " ca" .
                        " LEFT JOIN  " . $ecs->table('goods') . " as g ON ca.goods_id = g.goods_id" .
                        " WHERE ca." . $sess_id . " AND ca.rec_type = '$flow_type'" . $goodsIn;
                $db->query($sql);
                //微分销end
            } else {

                if (!empty($cartValue)) {
                    $goodsIn = " and rec_id in($cartValue)";
                }

                /* 插入订单商品 */
                $sql = "INSERT INTO " . $ecs->table('order_goods') . "( " .
                        "order_id, goods_id, goods_name, goods_sn, product_id, goods_number, market_price, commission_rate, " .
                        "goods_price, goods_attr, is_real, extension_code, parent_id, is_gift, model_attr, goods_attr_id, ru_id, shopping_fee, warehouse_id, area_id, freight, tid, shipping_fee) " .
                        " SELECT '$new_order_id', goods_id, goods_name, goods_sn, product_id, goods_number, market_price, commission_rate, " .
                        "goods_price, goods_attr, is_real, extension_code, parent_id, is_gift, model_attr, goods_attr_id, ru_id, shopping_fee, warehouse_id, area_id, freight, tid, shipping_fee" .
                        " FROM " . $ecs->table('cart') .
                        " WHERE " . $sess_id . " AND rec_type = '$flow_type'" . $goodsIn;
                $db->query($sql);
            }
        }
        /*插入订单拓展表 by kong  20160725*/

        //防止重复提交
        setcookie('done_cookie', '', gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

        if (!$is_cart) {
            ecs_header("Location: " .$GLOBALS['ecs']->url(). "\n");
            exit;
        }

        $good_ru_id = !empty($_REQUEST['ru_id']) ? dsc_addslashes($_REQUEST['ru_id'], 1) : 0;

        if($store_id > 0 && $good_ru_id){

            $take_time = isset($_POST['take_time']) && !empty($_POST['take_time']) ? addslashes(trim($_POST['take_time'])) : '';

            foreach($good_ru_id as $v){
                $pick_code = substr($order['order_sn'],-3).rand(0, 9) . rand(0, 9) . rand(0, 9);

                if($stores_sms != 1){
                    $pick_code = '';
                }

                $sql = "INSERT INTO".$ecs->table("store_order")." (`order_id`,`store_id`,`ru_id`,`pick_code`,`take_time`) VALUES ('$new_order_id','$store_id','$v','$pick_code','$take_time')";
                $db->query($sql);
            }
        }

        /* 修改拍卖活动状态 */
        if ($order['extension_code'] == 'auction') {
            $sql = "UPDATE " . $ecs->table('goods_activity') . " SET is_finished='2' WHERE act_id=" . $order['extension_id'];
            $db->query($sql);
        }

        /* 处理余额、积分、红包 */
        if ($order['user_id'] > 0 && $order['surplus'] > 0) {
            log_account_change($order['user_id'], $order['surplus'] * (-1), 0, 0, 0, sprintf($_LANG['pay_order'], $order['order_sn']));
        }
        if ($order['user_id'] > 0 && $order['integral'] > 0) {
            log_account_change($order['user_id'], 0, 0, 0, $order['integral'] * (-1), sprintf($_LANG['pay_order'], $order['order_sn']));
        }

        if ($order['bonus_id'] > 0 && $temp_amout > 0) {
            use_bonus($order['bonus_id'], $new_order_id);
        }

        /* 处理储值卡 */
        if ($order['vc_id'] > 0) {
            use_value_card($order['vc_id'], $new_order_id, $order['use_value_card']);
        }

        /* 记录优惠券使用 bylu */
        if ($order['uc_id'] > 0) {
            use_coupons($order['uc_id'], $new_order_id);
        }

        /* 如果使用库存，且下订单时减库存，则减少库存 */
        if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_PLACE) {
            change_order_goods_storage($order['order_id'], true, SDT_PLACE, $_CFG['stock_dec_time'],0,$store_id);
        }

        /* 如果订单金额为0 处理虚拟卡 */
        if ($order['order_amount'] <= 0) {
            $sql = "SELECT goods_id, goods_name, goods_number AS num FROM " .
                $GLOBALS['ecs']->table('cart') .
                " WHERE is_real = 0 AND extension_code = 'virtual_card'" .
                " AND " . $sess_id . " AND rec_type = '$flow_type'";

            $res = $GLOBALS['db']->getAll($sql);

            $virtual_goods = array();
            foreach ($res AS $row) {
                $virtual_goods['virtual_card'][] = array('goods_id' => $row['goods_id'], 'goods_name' => $row['goods_name'], 'num' => $row['num']);
            }

            if ($virtual_goods AND $flow_type != CART_GROUP_BUY_GOODS) {
                /* 虚拟卡发货 */
                if (virtual_goods_ship($virtual_goods, $msg, $order['order_sn'], true)) {
                    /* 如果没有实体商品，修改发货状态，送积分和红包 */
                    $sql = "SELECT COUNT(*)" .
                        " FROM " . $ecs->table('order_goods') .
                        " WHERE order_id = '$order[order_id]' " .
                        " AND is_real = 1";
                    if ($db->getOne($sql) <= 0) {
                        /* 修改订单状态 */
                        update_order($order['order_id'], array('shipping_status' => SS_SHIPPED, 'shipping_time' => gmtime()));

                        /* 如果订单用户不为空，计算积分，并发给用户；发红包 */
                        if ($order['user_id'] > 0) {
                            /* 取得用户信息 */
                            $user = user_info($order['user_id']);

                            /* 计算并发放积分 */
                            $integral = integral_to_give($order);
                            $gave_custom_points=integral_of_value($integral['custom_points'])-$order['integral'];// by kong 计算赠送积分
                            if($gave_custom_points < 0 ){
                                $gave_custom_points=0;
                            }
                            log_account_change($order['user_id'], 0, 0, intval($integral['rank_points']), intval($integral['custom_points']), sprintf($_LANG['order_gift_integral'], $order['order_sn']));

                            /* 发放红包 */
                            send_order_bonus($order['order_id']);

                            /* 发放优惠券 bylu */
                            send_order_coupons($order['order_id']);
                        }
                    }
                }
            }

        }

        $stages_intro = array();
        /* 取得支付信息，生成支付代码 */
        if ($order['order_amount'] > 0) {

            //查询"在线支付"的pay_id;
            $onlinepay_pay_id = $db->getOne("SELECT pay_id FROM " . $ecs->table('payment') . " WHERE pay_code='onlinepay'");
            //@模板堂-bylu 如果选择的是在线支付,循环出商家启用的所有在线支付方法 start
            if ($order['pay_id'] == $onlinepay_pay_id) {

                //用于判断当前用户是否拥有白条支付授权(有的话才显示"白条支付按钮");
                $baitiao_balance = get_baitiao_balance($user_id);

                $payment_list = available_payment_list(0, $cod_fee, false, $order['order_amount']);

                //取出所有在线支付方法(含按钮);
                foreach ($payment_list as $K => $v) {
                    if ($v['is_online'] == 1) {

                        $payment_file = 'includes/modules/payment/' . $v['pay_code'] . '.php';
                        if (file_exists($payment_file)) {
                            include_once($payment_file);
                            $pay_obj = new $v['pay_code'];
                            $payment = payment_info($v['pay_id']);

                            $par_order = $order;
                            $par_order['order_amount'] = $par_order['order_amount'] + $v['pay_fee_amount'];

                            $pay_online_button[$v['pay_code']] = <<<HTML
      {$pay_obj->get_code($par_order, unserialize_config($v['pay_config']))}
HTML;
                            //判断已安装的支付方法中是否有"支付宝网银直连"方法;
                            if ($v['pay_code'] == 'alipay_bank') {
                                //重新赋值支付宝网银直连的支付按钮,将支付按钮列表中的删除;
                                $smarty->assign('is_alipay_bank', $pay_online_button['alipay_bank']);
                                unset($pay_online_button['alipay_bank']);
                            }
                            if ($v['pay_code'] == 'balance') {
                                $pay_online_button['balance'] = <<<HTML
                    <a href="flow.php?step=done&act=balance&order_sn={$order['order_sn']}&store_id={$store_id}" id="balance" style="float: left;" order_sn="{$order['order_sn']}" flag="balance" >{$_LANG['balance_pay']}</a>
HTML;
                            }
                            //判断当前用户是否拥有白条支付授权(有的话才显示"白条支付按钮");
                            if ($baitiao_balance && $baitiao_balance['balance'] > 0) {
                                $smarty->assign('is_chunsejinrong', true);
                                if ($v['pay_code'] == 'chunsejinrong') {
                                    $pay_online_button['chunsejinrong'] = <<<HTML
                            <a href="flow.php?step=done&act=chunsejinrong&order_sn={$order['order_sn']}&store_id={$store_id}" id="chunsejinrong" style="height:36px; line-height:36px; float: left;" order_sn="{$order['order_sn']}" flag="chunsejinrong" >{$_LANG['ious_pay']}</a>
HTML;
                                }
                            }
                        }
                    }
                }

                $smarty->assign('pay_online_button', $pay_online_button); //在线支付按钮数组;
                $smarty->assign('is_onlinepay', true); //在线支付标记 by lu;
                //@模板堂-bylu  end
            } else {
                $payment = payment_info($order['pay_id']);

                $payment_file = 'includes/modules/payment/' . $payment['pay_code'] . '.php';
                if (file_exists($payment_file)) {
                    include_once($payment_file);
                    $pay_obj = new $payment['pay_code'];
                    $pay_online = $pay_obj->get_code($order, unserialize_config($payment['pay_config']));
                } else {
                    $pay_online = '';
                }

                $order['pay_desc'] = $payment['pay_desc'];
            }

            if ($_SESSION['flow_type'] == 5) {//预售商品标记 by liu
                $smarty->assign('is_presale_goods', true);
            }

            $smarty->assign('pay_online', $pay_online);

            /*  @author-bylu 白条分期信息 start  */
            /* 分期数据插入白条分期表 by lu */
            $cart_info = cart_goods($flow_type, $_REQUEST['done_cart_value']);

            //如果当前商品是白条分期商品; -1表示普通商品;
            if ($cart_info[0]['stages_qishu'] > 0) {

                //获取到当前商品的费率;
                $stages_rate = $db->getOne("SELECT stages_rate FROM ".$ecs->table('goods')." WHERE goods_id = '".$cart_info[0]['goods_id']."'", true);

                $order_sn = $order['order_sn']; //订单编号;
                $stages_qishu = $cart_info[0]['stages_qishu']; //选择的期数;
                $goods_number = $cart_info[0]['goods_number']; //购买的数量;

                //获取该白条分期订单的总价;
                $shop_price_total = $order['order_amount'];
                //计算每期价格(每期价格=总价*费率+总价/期数);
                $stages_one_price = round(($shop_price_total * ($stages_rate / 100)) + ($shop_price_total / $stages_qishu), 2);

                //计算还款日期;
                if ($stages_qishu == 1) {
                    //这里是30天免息,还款日期直接就是下个月的今天,且不计算费率;
                    $repay_datee[1] = local_date('Y-m-d', local_strtotime("+1 month"));
                    $stages_one_price = round($shop_price_total, 2); //30天免息,还款金额直接为应付总价;
                } else {
                    for ($i = 1; $i <= $stages_qishu; $i++) {
                        $repay_datee[$i] = local_date('Y-m-d', local_strtotime("+$i month"));
                    }
                }

                $repay_date = serialize($repay_datee);//还款日期;

                //将数据插入 白条分期表;
                $stages_other = array(
                    'order_sn' => $order_sn,
                    'stages_total' => $stages_qishu,
                    'stages_one_price' => $stages_one_price,
                    'yes_num' => '0',
                    'create_date' => gmtime(),
                    'repay_date' => $repay_date,
                );

                $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('stages'), $stages_other, 'INSERT');

                //将白条分期付款信息注入到前台显示;
                $stages_intro = array(
                    'stages_qishu' => $stages_qishu,
                    'goods_number' => $goods_number,
                    'stages_one_price' => $stages_one_price,
                    'repay_date' => $repay_datee[1],
                    'baitiao' => isset($baitiao_balance['balance']) && !empty($baitiao_balance['balance']) ? $baitiao_balance['balance'] : 0
                );
            }
            /*  @author-bylu 白条分期信息 end  */
        }

        $smarty->assign('stages_info', $stages_intro);
        /*如果是门店商品*/
        if($store_id > 0){
            $sql = "SELECT id, country, province, city, district, stores_address, stores_name, stores_tel FROM ".$ecs->table('offline_store')." WHERE id = '$store_id' LIMIT 1";
            $stores_info = $db->getRow($sql);
            $smarty->assign('stores_info', $stores_info);
        }

        //@author-bylu start 将当前用户的当前订单的订单信息存入session,用于订单页刷新处理;
        $order_reload = $stages_intro; //白条分期信息;
        $order_reload['order_id'] = $new_order_id;
        $_SESSION['order_reload'][$user_id] = $order_reload;
        //@author-bylu end;

        /* 清空进货单 */
        clear_cart($flow_type, $done_cart_value);
        /* 清除缓存，否则买了商品，但是前台页面读取缓存，商品数量不减少 */
        clear_all_files();

        if (!empty($order['shipping_name'])) {
            $order['shipping_name'] = trim(stripcslashes($order['shipping_name']));
        }

        if (isset($_SESSION['direct_shopping'])) {
            $smarty->assign('direct_shopping', $_SESSION['direct_shopping']);
        }

        /* 更新商品销量 */
        get_goods_sale($new_order_id);

        //付款成功创建快照
        if ($snapshot) {
            create_snapshot($new_order_id);
        }

        //处理价格显示
        $order['format_shipping_fee'] = price_format($order['shipping_fee']);
        $order['format_order_amount'] = price_format($order['order_amount']);

        /* 订单信息 */
        $smarty->assign('order', $order);
        $smarty->assign('total', $total);
        $smarty->assign('order_submit_back', sprintf($_LANG['order_submit_back'], $_LANG['back_home'], $_LANG['goto_user_center'])); // 返回提示

        user_uc_call('add_feed', array($order['order_id'], BUY_GOODS)); //推送feed到uc
        unset($_SESSION['flow_consignee']); // 清除session中保存的收货人信息
        unset($_SESSION['flow_order']);
        unset($_SESSION['direct_shopping']);

        //订单分子订单 start
        $order_id = $order['order_id'];

        $row = get_main_order_info($order_id);
        $order_info = get_main_order_info($order_id, 1);
        $ru_id = explode(",", $order_info['all_ruId']['ru_id']);


        //获取订单是否存在商家无白条支付权限 staert
//        $seller_grade = 1;
//        if (count($ru_id) > 1) {
//            $is_payment = get_payment_code(); //是否安装白条支付
//            if ($is_payment) {
//                $sg_ru_id = get_array_flip(0, $ru_id);
//                $seller_grade = get_seller_grade($sg_ru_id, 1);
//            }
//
//            $smarty->assign('seller_grade', $seller_grade);
//        } else {
//            if ($ru_id[0] == 0) {
//                $smarty->assign('seller_grade', $seller_grade);
//            } else {
//                $is_payment = get_payment_code(); //是否安装白条支付
//                if ($is_payment) {
//                    $seller_grade = get_seller_grade($ru_id, 1);
//                }
//
//                $smarty->assign('seller_grade', $seller_grade);
//            }
//        }
        //获取订单是否存在商家无白条支付权限 end

        $ru_number = count($ru_id);
        if ($order_info['order_goods_goods'] > 1) {
            get_insert_order_goods_single($order_info, $row, $order_id, $ru_number);
        } else {
            //by zxk
            //只有一个商品的时候
            //修改的订单类型
            $order_goods = get_main_order_goods_info($order_id);
            $first = array_first($order_goods);
            $extension_code = $first['extension_code'];
            $extension_id = $first['extension_id'];
            $extension = [
               'extension_code' => $extension_code,
                'extension_id' => $extension_id
            ];

            //单个商品订单
            if ($has_pay) {
               //已支付
               if ($extension_code == 'presale') {
                   //预定
                   $extension['surplus'] = $order['order_amount'];
                   $extension['pay_status'] = PS_PAYED_PART; //部分付款
                   $extension['order_status'] = OS_CONFIRMED; //已确认
                   $extension['order_amount'] = $order['total_goods_amount'] + $order['shipping_fee'] + $order['insure_fee'] + $order['tax'] - $order['discount'] - $order['surplus'];
                   $order['order_amount'] = $extension['order_amount'];
               } else {
                   $extension['surplus'] = $order['order_amount'];
                   $extension['order_status'] = OS_CONFIRMED;
                   $extension['confirm_time'] = gmtime();
                   $extension['pay_status'] = PS_PAYED;
                   $extension['pay_time'] = gmtime();
                   $extension['order_amount'] = 0;
                   $order['order_amount'] = 0;
                   $stores_sms = 1;
               }
            }
            $order['format_order_amount'] = price_format($order['order_amount']);
            $smarty->assign('order', $order);

            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_info'), $extension, 'UPDATE', "order_id = '$order_id'");
        }

        /* jisuan by zxk  */
        /* jisuan by zxk  */

        $sql = "SELECT count(*) FROM " . $ecs->table('order_info') . " WHERE main_order_id = '" . $order['order_id'] ."'";
        $child_order = $db->getOne($sql, true);
        if ($child_order > 1) {
            $child_order_info = get_child_order_info($order['order_id']);
            $smarty->assign('child_order_info', $child_order_info);
        }

        $smarty->assign('pay_type', $pay_type);
        $smarty->assign('child_order', $child_order);
        //end

        $goods_buy_list = get_order_goods_buy_list($region_id, $area_id);
        $smarty->assign('goods_buy_list', $goods_buy_list);
        //门店发送短信
        if($stores_sms == 1 && $store_id > 0){
            $user_name = isset($_SESSION['user_name']) && !empty($_SESSION['user_name']) ? $_SESSION['user_name'] : '';

            /*门店下单时未填写手机号码 则用会员绑定号码*/
            if ($store_id) {
                if ($order['mobile']) {
                    $user_mobile_phone = $order['mobile'];
                } else {
                    $sql = "SELECT mobile_phone FROM " . $ecs->table('users') . " WHERE user_id = '" . $_SESSION['user_id'] . "'";
                    $user_mobile_phone = $db->getOne($sql, true);
                }

                $store_address = get_area_region_info($stores_info) . $stores_info['stores_address'];

                //门店订单->短信接口参数
                $store_smsParams = array(
                    'user_name' => $user_name,
                    'username' => $user_name,
                    'order_sn' => $order['order_sn'],
                    'ordersn' => $order['order_sn'],
                    'code' => $pick_code,
                    'store_address' => $store_address,
                    'storeaddress' => $store_address,
                    'mobile_phone' => $user_mobile_phone,
                    'mobilephone' => $user_mobile_phone
                );
                if ($GLOBALS['_CFG']['sms_type'] == 0 && $user_mobile_phone) {
                     huyi_sms($store_smsParams, 'store_order_code');
                }elseif ($GLOBALS['_CFG']['sms_type'] >=1 && $user_mobile_phone) {
                     $store_result = sms_ali($store_smsParams, 'store_order_code'); //阿里大鱼短信变量传值，发送时机传值
                     $resp = $GLOBALS['ecs']->ali_yu($store_result);
                }
            }
        }

        //对单商家下单
        if (count($ru_id) == 1) {
            /* 如果需要，发短信 */
            $sellerId = $ru_id[0];
            $shop_name = get_shop_name($sellerId, 1);

            if ($sellerId == 0) {
                $sms_shop_mobile = $_CFG['sms_shop_mobile'];
                $service_email = $_CFG['service_email'];
            } else {
                $sql = "SELECT mobile, seller_email FROM " . $ecs->table('seller_shopinfo') . " WHERE ru_id = '$sellerId'";
                $seller_shopinfo = $db->getOne($sql, true);

                $sms_shop_mobile = isset($seller_shopinfo['mobile']) && !empty($seller_shopinfo['mobile']) ? $seller_shopinfo['mobile'] : '';
                $service_email = isset($seller_shopinfo['seller_email']) && !empty($seller_shopinfo['seller_email']) ? $seller_shopinfo['seller_email'] : '';
            }

            //是否开启下单自动发短信、邮件 by wu start
            $sql = " select * from " . $GLOBALS['ecs']->table('crons') . " where cron_code='auto_sms' and enable=1 LIMIT 1";
            $auto_sms = $GLOBALS['db']->getRow($sql);

            if ($_CFG['sms_order_placed'] == '1' && $sms_shop_mobile) {
                if (!empty($auto_sms)) {
                    $sql = " insert into " . $GLOBALS['ecs']->table('auto_sms') . " (item_id,item_type,user_id,ru_id,order_id,add_time) " .
                            " VALUES " .
                            "(NULL,1,'" . $order['user_id'] . "','" . $sellerId . "','" . $order['order_id'] . "','" . gmtime() . "')";
                    $GLOBALS['db']->query($sql);
                } else {
                    $order_region = get_flow_user_region($order_id);

                    //普通订单->短信接口参数
                    $pt_smsParams = array(
                        'shop_name' => $shop_name,
                        'shopname' => $shop_name,
                        'order_sn' => $order['order_sn'],
                        'ordersn' => $order['order_sn'],
                        'consignee' => $order['consignee'],
                        'order_region' => $order_region,
                        'orderregion' => $order_region,
                        'address' => $order['address'],
                        'order_mobile' => $order['mobile'],
                        'ordermobile' => $order['mobile'],
                        'mobile_phone' => $sms_shop_mobile,
                        'mobilephone' => $sms_shop_mobile
                    );

                    if ($GLOBALS['_CFG']['sms_type'] == 0) {

                        huyi_sms($pt_smsParams, 'sms_order_placed');

                    } elseif ($GLOBALS['_CFG']['sms_type'] >=1) {

                        $pt_result = sms_ali($pt_smsParams, 'sms_order_placed'); //阿里大鱼短信变量传值，发送时机传值

                        $resp = $GLOBALS['ecs']->ali_yu($pt_result);
                    }
                }
            }

            /* 增加是否给客服发送邮件选项 */
            if ((($sellerId == 0 && $_CFG['send_service_email'] == '1') || ($sellerId > 0 && $_CFG['seller_email'] == '1')) && $service_email) {
                $smarty->assign('done_cart_value', $done_cart_value);
                $smarty->assign('order_id', $order_id);
                $smarty->assign("ajax_send_mail", true);
                $smarty->assign("send_time", 'send_service_email');
            }
            //是否开启下单自动发短信、邮件 by wu end
        }
    }

    if (defined('THEME_EXTENSION')) {
        $region = array(
            'province' => $order['province'],
            'city' => $order['city'],
            'district' => $order['district'],
            'street' => $order['street']
        );
        $address_info = get_area_region_info($region);
        $smarty->assign('address_info', $address_info); //收货地址
    }
}

/*------------------------------------------------------ */
//-- 更新进货单
/*------------------------------------------------------ */
/*------------------------------------------------------*/
//-- Ajax更新进货单add 20120118
/*------------------------------------------------------*/

 elseif ($_REQUEST['step'] == 'ajax_update_cart') {
    require_once(ROOT_PATH . 'includes/cls_json.php');
    $json = new JSON();
    $result = array('error' => 0, 'message' => '');

    if (isset($_POST['rec_id']) && isset($_POST['goods_number'])) {
        $key = !empty($_POST['rec_id']) ? intval($_POST['rec_id']) : 0;
        $val = !empty($_POST['goods_number']) ? intval($_POST['goods_number']) : 0;

        $warehouse_id = !empty($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0;
        $area_id = !empty($_POST['area_id']) ? intval($_POST['area_id']) : 0;

        $val = intval(make_semiangle($val));
        if ($val <= 0 && !is_numeric($key)) {
            $result['error'] = 99;
            $result['message'] = '';
            die($json->encode($result));
        }

        $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('warehouse_goods') . " AS wg ON g.goods_id = wg.goods_id AND wg.region_id = '$warehouse_id' ";
        $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('warehouse_area_goods') . " AS wag ON g.goods_id = wag.goods_id AND wag.region_id = '$area_id' ";

        $sql = "SELECT g.goods_name, wg.region_number as wg_number, wag.region_number as wag_number, g.model_price, g.model_inventory, g.model_attr, g.goods_number, g.group_number, " .
                "c.goods_id, c.goods_attr_id, c.product_id, c.extension_code, c.warehouse_id, c.area_id, c.ru_id, " .
                "c.group_id, c.extension_code, c.goods_name AS act_name, g.freight, g.tid, g.shipping_fee " .
                "FROM " . $GLOBALS['ecs']->table('goods') . " AS g " .
                " LEFT JOIN " .$GLOBALS['ecs']->table('cart') . " AS c on g.goods_id = c.goods_id " .
                $leftJoin .
                "WHERE c.rec_id = '$key' LIMIT 1";
        $row = $GLOBALS['db']->getRow($sql);

        $result['ru_id'] = $row['ru_id'];

        //ecmoban模板堂 --zhuo start 限购
        $nowTime = gmtime();
        $xiangouInfo = get_purchasing_goods_info($row['goods_id']);
        $start_date = $xiangouInfo['xiangou_start_date'];
        $end_date = $xiangouInfo['xiangou_end_date'];

        if ($xiangouInfo['is_xiangou'] == 1 && $nowTime > $start_date && $nowTime < $end_date) {
            $user_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
            $orderGoods = get_for_purchasing_goods($start_date, $end_date, $row['goods_id'], $user_id);

            if ($orderGoods['goods_number'] >= $xiangouInfo['xiangou_num']) {
                $max_num = $xiangouInfo['xiangou_num'] - $orderGoods['goods_number'];
                $result['message'] = sprintf($_LANG['purchase_Prompt'], $row['goods_name']);

                //更新进货单中的商品数量
                $sql = "UPDATE " . $GLOBALS['ecs']->table('cart') .
                        " SET goods_number = 0 WHERE rec_id='$key'";
                $GLOBALS['db']->query($sql);

                $result['error'] = 1;

                die($json->encode($result));
            } else {
                if ($xiangouInfo['xiangou_num'] > 0) {
                    if ($xiangouInfo['is_xiangou'] == 1 && $orderGoods['goods_number'] + $val > $xiangouInfo['xiangou_num']) {
                        $max_num = $xiangouInfo['xiangou_num'] - $orderGoods['goods_number'];
                        $result['message'] = sprintf($_LANG['purchasing_prompt'], $row['goods_name']);

                        //更新进货单中的商品数量
                        $cart_Num = $xiangouInfo['xiangou_num'] - $orderGoods['goods_number'];
                        $sql = "UPDATE " . $GLOBALS['ecs']->table('cart') .
                                " SET goods_number = '$cart_Num' WHERE rec_id='$key'";
                        $GLOBALS['db']->query($sql);

                        $result['error'] = 1;
                        $result['cart_Num'] = $cart_Num;
                        $result['rec_id'] = $key;

                        die($json->encode($result));
                    }
                }
            }
        }
        //ecmoban模板堂 --zhuo end 限购
        //查询：系统启用了库存，检查输入的商品数量是否有效
        if (intval($GLOBALS['_CFG']['use_storage']) > 0 && $row['extension_code'] != 'package_buy') {
            //ecmoban模板堂 --zhuo start
            if ($row['model_inventory'] == 1) {
                $row['goods_number'] = $row['wg_number'];
            } elseif ($row['model_inventory'] == 2) {
                $row['goods_number'] = $row['wag_number'];
            }
            //ecmoban模板堂 --zhuo end

            /* 是货品 */
            if (!empty($row['product_id'])) {
                //ecmoban模板堂 --zhuo start
                if ($row['model_attr'] == 1) {
                    $table_products = "products_warehouse";
                } elseif ($row['model_attr'] == 2) {
                    $table_products = "products_area";
                } else {
                    $table_products = "products";
                }
                //ecmoban模板堂 --zhuo end

                $sql = "SELECT product_number FROM " . $GLOBALS['ecs']->table($table_products) . " WHERE goods_id = '" . $row['goods_id'] . "' and product_id = '" . $row['product_id'] . "' LIMIT 1";
                $product_number = $GLOBALS['db']->getOne($sql);

                if ($product_number < $val) {
                    $result['error'] = 2;
                    $result['message'] = sprintf($GLOBALS['_LANG']['stock_insufficiency'], $row['goods_name'], $product_number, $product_number);
                    die($json->encode($result));
                }
            } else {
                if ($row['goods_number'] < $val) {
                    $result['error'] = 1;
                    $result['message'] = sprintf($GLOBALS['_LANG']['stock_insufficiency'], $row['goods_name'], $row['goods_number'], $row['goods_number']);
                    die($json->encode($result));
                }
            }
        } elseif (intval($GLOBALS['_CFG']['use_storage']) > 0 && $row['extension_code'] == 'package_buy') {
            if (judge_package_stock($row['goods_id'], $val)) {
                $result['error'] = 3;
                $result['message'] = $GLOBALS['_LANG']['package_stock_insufficiency'];
                die($json->encode($result));
            }
        }

        /* 查询：检查该项是否为基本件 以及是否存在配件 */
        /* 此处配件是指添加商品时附加的并且是设置了优惠价格的配件 此类配件都有parent_idgoods_number为1 */
        $sql = "SELECT b.goods_number,b.rec_id
                FROM " . $GLOBALS['ecs']->table('cart') . " a, " . $GLOBALS['ecs']->table('cart') . " b
                WHERE a.rec_id = '$key'
                AND " . $a_sess . "
                AND a.extension_code <>'package_buy'
                AND b.parent_id = a.goods_id
                AND " . $b_sess;

        $offers_accessories_res = $GLOBALS['db']->getAll($sql);

        //订货数量大于0
        if ($val > 0) {
            if ($row['group_number'] > 0 && $val > $row['group_number'] && !empty($row['group_id'])) {
                $result['error'] = 1;
                $result['message'] = sprintf($GLOBALS['_LANG']['group_stock_insufficiency'], $row['goods_name'], $row['group_number'], $row['group_number']);
                die($json->encode($result));
            }

            //主配件更新数量时，子配件也跟着加数量
            for ($i = 0; $i < count($offers_accessories_res); $i++) {
                $sql = "UPDATE " . $GLOBALS['ecs']->table('cart') . " SET goods_number = '$val'" . " WHERE rec_id ='" . $offers_accessories_res[$i]['rec_id'] . "'";
                $GLOBALS['db']->query($sql);
            }

            /* 处理超值礼包 */
            if ($row['extension_code'] == 'package_buy')
            {
                //更新进货单中的商品数量
                $sql = "UPDATE " . $GLOBALS['ecs']->table('cart') ." SET goods_number= '$val' WHERE rec_id = '$key'";
                $GLOBALS['db']->query($sql);
            }
            /* 处理普通商品或非优惠的配件 */
            else
            {
                if($GLOBALS['_CFG']['add_shop_price'] == 1){
                    $add_tocart = 1;
                }else{
                    $add_tocart = 0;
                }

                $attr_id = empty($row['goods_attr_id']) ? array() : explode(',', $row['goods_attr_id']);
                $goods_price = get_final_price($row['goods_id'], $val, true, $attr_id, $_POST['warehouse_id'], $_POST['area_id'], 0, 0, $add_tocart); //ecmoban模板堂 --zhuo
                //更新进货单中的商品数量
                $set = "";
                if($goods_price > 0){
                    $set = "goods_price = '$goods_price', ";
                }
                $sql = "UPDATE " . $GLOBALS['ecs']->table('cart') ." SET goods_number = '$val', " .$set. " freight = '" .$row['freight']. "', tid = '" .$row['tid']. "', shipping_fee = '" .$row['shipping_fee']. "' WHERE rec_id = '$key'";
                $GLOBALS['db']->query($sql);
            }
        }
        //订货数量等于0
        else {
            //主配件商品为零时，该底下所有子配件都将删除
            for ($i = 0; $i < count($offers_accessories_res); $i++) {
                $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart') . " WHERE rec_id ='" . $offers_accessories_res[$i]['rec_id'] . "'";
                $GLOBALS['db']->query($sql);
            }

            //删除主配件
            $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart') . " WHERE rec_id = '$key'";
            $GLOBALS['db']->query($sql);
        }

        $result['rec_id'] = $key;
        $result['goods_number'] = $val;
        $result['total_desc'] = '';

        //查询礼品包价格 ecmoban模板堂 --zhuo
        if ($row['extension_code'] == 'package_buy') {
            $result['ext_info'] = $GLOBALS['db']->getOne("SELECT ext_info FROM " . $GLOBALS['ecs']->table('goods_activity') . " WHERE review_status = 3 AND act_name = '" . $row['act_name'] . "'");
            $ext_arr = unserialize($result['ext_info']);
            unset($result['ext_info']);

            $goods_price = $ext_arr['package_price'];
        }

        $result['goods_price'] = price_format($goods_price);
        $result['cart_info'] = insert_cart_info(4);

        /* 计算合计 */
        $cValue = htmlspecialchars($_POST['cValue']);
        $cart_goods = get_cart_goods($cValue, 0, $warehouse_id, $area_id);

        foreach ($cart_goods['goods_list'] as $goods)
        {
            if ($goods['rec_id'] == $key)
            {
                if($goods['dis_amount'] > 0){
                    $result['goods_subtotal'] = price_format($goods['subtotal']);
                    $result['discount_amount'] = $goods['discount_amount'];
                }else{
                    $result['goods_subtotal'] = price_format($goods['subtotal']);
                    $result['discount_amount'] = price_format(0);
                }

                $result['rec_goods'] =$goods['goods_id'];

                break;
            }
        }

        /* 计算折扣 */
        $discount = compute_discount(3, $cValue);
        $goods_discount_amount = get_cart_check_goods($cart_goods['goods_list'], $key, 1);

        /* 商品阶梯优惠 + 优惠活动金额 ($goods_discount_amount['save_amount'] + ) */
        $fav_amount = $discount['discount'];
        $result['save_total_amount'] = price_format($fav_amount + $goods_discount_amount['save_amount']);

        //商品阶梯优惠
        $result['dis_amount'] =  $goods_discount_amount['save_amount'];

        $result['group'] = array();
        $subtotal_number = 0;
        foreach ($cart_goods['goods_list'] as $goods) {
            $subtotal_number += $goods['goods_number'];

            if (isset($result['rec_goods']) && $goods['parent_id'] > 0 && $result['rec_goods'] == $goods['parent_id']) {
                if ($goods['rec_id'] != $key) {
                    $result['group'][$goods['rec_id']]['rec_group'] = $goods['group_id'] . "_" . $goods['rec_id'];
                    $result['group'][$goods['rec_id']]['rec_group_number'] = $goods['goods_number'];

                    $result['group'][$goods['rec_id']]['rec_group_talId'] = $goods['group_id'] . "_" . $goods['rec_id'] . "_subtotal";
                    $result['group'][$goods['rec_id']]['rec_group_subtotal'] = price_format($goods['goods_amount'], false);
                }
            }
        }

        $result['subtotal_number'] = $subtotal_number;

        if ($result['group']) {
            $result['group'] = array_values($result['group']);
        }

        $goods_amount = $cart_goods['total']['goods_amount'] - $fav_amount;
        $total_goods_price = price_format($goods_amount, false);

        /* 返回进货单信息 */
        $result['flow_info'] = insert_flow_info($total_goods_price, $cart_goods['total']['market_price'], $cart_goods['total']['saving'], $cart_goods['total']['save_rate'], $goods_amount, $cart_goods['total']['real_goods_count']);

        $act_id = isset($_POST['favourable_id']) ? intval($_POST['favourable_id']) : 0;

        $act_sel_id = !empty($_REQUEST['sel_id']) ? addslashes($_REQUEST['sel_id']) : ''; // 被选中的优惠活动商品（这里是全部，点击进货单 加减号）
        $sel_flag = !empty($_REQUEST['sel_flag']) ? addslashes($_REQUEST['sel_flag']) : ''; // 标志flag
        $act_sel = array('act_sel_id' => $act_sel_id, 'act_sel' => $sel_flag);

        if ($act_id > 0) {
            // 当优惠活动商品不满足最低金额时-删除赠品
            $favourable = favourable_info($act_id);
            $favourable_available = favourable_available($favourable, $act_sel);

            if (!$favourable_available) {
                $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart') . " WHERE " . $sess_id . " AND is_gift = '$act_id' AND ru_id = '" .$row['ru_id']. "'";
                $GLOBALS['db']->query($sql);
            }

            // 局部更新优惠活动
            $cart_fav_box = cart_favourable_box($act_id, $act_sel);
            $smarty->assign('activity', $cart_fav_box);
            $smarty->assign('ru_id', $row['ru_id']);
            $result['favourable_box_content'] = $smarty->fetch("library/cart_favourable_box.lbi");
            $result['act_id'] = $act_id;
        }

        die($json->encode($result));
    } else {
        $result['error'] = 100;
        $result['message'] = '';
        die($json->encode($result));
    }
}

elseif ($_REQUEST['step']== 'ajax_cart_goods_amount')
{
    require_once(ROOT_PATH .'includes/cls_json.php');
    $json = new JSON();
    $result = array('error' => 0, 'message'=> '');

    get_request_filter();

    $rec_id = !empty($_REQUEST['rec_id']) ? dsc_addslashes($_REQUEST['rec_id']) : ''; //字符串
    $act_sel_id = !empty($_REQUEST['sel_id']) ? dsc_addslashes($_REQUEST['sel_id']) : ''; // 被选中的优惠活动商品
    $sel_flag = !empty($_REQUEST['sel_flag']) ? dsc_addslashes($_REQUEST['sel_flag']) : ''; // 标志flag
    $act_sel = array('act_sel_id'=>$act_sel_id, 'act_sel'=>$sel_flag);

    /* 计算折扣 */
    $discount = compute_discount(3, $rec_id);
    $favour_name = empty($discount['name']) ? '' : implode(',', $discount['name']);

    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;
    $cart_goods = cart_goods($flow_type, $rec_id, 0, $region_id, $area_id); // 取得商品列表
    $goods_amount = get_cart_check_goods($cart_goods, $rec_id);

    /* 商品阶梯优惠 + 优惠活动金额 ($goods_amount['save_amount'] + ) */
    $fav_amount = $discount['discount'];

    //节省
    $save_total_amount = price_format($fav_amount + $goods_amount['save_amount']);
    $result['save_total_amount'] = $save_total_amount;

    //商品阶梯优惠
    $result['dis_amount'] = $goods_amount['save_amount'];

    $result['error'] = 0;
    $result['message'] = '';

    if($goods_amount['subtotal_amount']){
        $goods_amount['subtotal_amount'] = $goods_amount['subtotal_amount'] - $fav_amount;
    }else{
        $result['save_total_amount'] = 0;
    }

    $result['goods_amount'] = price_format($goods_amount['subtotal_amount'], false);
    $result['subtotal_number'] = $goods_amount['subtotal_number'];

    $result['discount'] = $discount['discount'];

    $is_gift = 0;
    $act_id = isset($_POST['favourable_id']) ? intval($_POST['favourable_id']) : 0;

    if($act_id)
    {
        $sql = "SELECT COUNT(*) FROM " .$GLOBALS['ecs']->table('cart') . " WHERE "  .$sess_id. " AND is_gift = '$act_id' LIMIT 1";
        $is_gift = $GLOBALS['db']->getOne($sql);

        //删除赠品
        $sql = "DELETE FROM " .$GLOBALS['ecs']->table('cart') . " WHERE "  .$sess_id. " AND is_gift = '$act_id'";
        $GLOBALS['db']->query($sql);

        // 局部更新优惠活动
        $cart_fav_box = cart_favourable_box($act_id, $act_sel);
        $smarty->assign('activity', $cart_fav_box);
        $result['favourable_box_content'] = $smarty->fetch("library/cart_favourable_box.lbi");
    }
    $result['act_id'] = $act_id;
    $result['is_gift'] = $is_gift;

    die($json->encode($result));
}

elseif ($_REQUEST['step'] == 'update_cart')
{
    if (isset($_POST['goods_number']) && is_array($_POST['goods_number']))
    {
        flow_update_cart($_POST['goods_number']);
    }

    show_message($_LANG['update_cart_notice'], $_LANG['back_to_cart'], 'flow.php');
    exit;
}

/*------------------------------------------------------ */
//-- 打印并下载订单 ecmoban模板堂 --zhuo
/*------------------------------------------------------ */
elseif($_REQUEST['step'] == 'pdf'){

    $order_id = isset($_REQUEST['order']) && !empty($_REQUEST['order']) ? $_REQUEST['order'] : 0;

    /* 跳转首页 会员未登录 */
    get_go_index();

    /* 跳转首页 订单ID为0 */
    get_go_index(1, $order_id);

    $consignee = get_consignee($_SESSION['user_id']);
    $userinfo = get_user_info($consignee['user_id']);
    $consignee['user_name'] = $userinfo['user_name'];

    /* 初始化地区ID */
    $consignee['country']       = !isset($consignee['country']) && empty($consignee['country'])                ?    0 :   intval($consignee['country']);
    $consignee['province']      = !isset($consignee['province']) && empty($consignee['province'])              ?    0 :   intval($consignee['province']);
    $consignee['city']          = !isset($consignee['city']) && empty($consignee['city'])                      ?    0 :   intval($consignee['city']);
    $consignee['district']      = !isset($consignee['district']) && empty($consignee['district'])              ?    0 :   intval($consignee['district']);
    $consignee['street']        = !isset($consignee['street']) && empty($consignee['street'])                  ?    0 :   intval($consignee['street']);

    $_SESSION['flow_consignee'] = $consignee;
    $smarty->assign('consignee', $consignee);

    $order_info = order_info($order_id);

    if(empty($order_info['order_id'])){
        /* 跳转首页 订单信息为空 */
        get_go_index(1, false);
    }elseif($order_info['user_id'] != $user_id){
        /* 跳转首页 订单会员不等于当前登录会员 */
        get_go_index(1, false);
    }

    $order_info['add_time'] = local_date("Y-m-d H:i:s", $order_info['add_time']);

    $order_goods = get_order_pdf_goods($order_id); /* 订单中的商品 */
    $shop_info = get_seller_shopinfo($order_info['ru_id']); //商家信息
    ob_start();
    include('./html/order_info.php');
    $content = ob_get_clean();

    require_once('./html/html2pdf.class.php');
    ob_clean();
    try
    {
        $html2pdf = new HTML2PDF('P', 'A3', 'fr');
        $html2pdf->setDefaultFont('stsongstdlight');
        $html2pdf->writeHTML($content, isset($_GET['vuehtml']));

        $html2pdf->Output('orderDdf_' .$order_id. '.pdf');
    }
    catch(HTML2PDF_exception $e) {
        echo $e;
        exit;
    }
}

/*------------------------------------------------------ */
//-- 删除进货单中的商品
/*------------------------------------------------------ */

elseif ($_REQUEST['step'] == 'drop_goods')
{
    //ecmoban模板堂 --zhuo start
    if(!empty($_GET['sig'])){
        $n_id = explode('@',$_GET['id']);
        foreach($n_id as $val){
            flow_drop_cart_goods($val);
        }
    }else{
        $rec_id = !empty($_GET['id']) ? intval($_GET['id']) : 0;
        flow_drop_cart_goods($rec_id);
    }
    //ecmoban模板堂 --zhuo end

    ecs_header("Location: flow.php\n");
    exit;
}
elseif ($_REQUEST['step'] == 'remove')
{
    //进货单删除
    require_once ROOT_PATH . 'includes/cls_json.php';
    $json = new JSON();
    $result = array('error' => 0, 'message' => '', 'content' => '');
    $extension_code = (empty($_REQUEST['extension_code']) ? '' : trim($_REQUEST['extension_code']));
    $extension_id = (empty($_REQUEST['extension_id']) ? 0 : intval($_REQUEST['extension_id']));
    $rec_id = (empty($_REQUEST['rec_id']) ? 0 : intval($_REQUEST['rec_id']));
    if (!(empty($extension_code)) && !(empty($extension_id)))
    {
        if ($rec_id) {
            $sess_id .= ' AND rec_id = \'' . $rec_id . '\' ';
        }
        $sess_id .= ' AND extension_code = \'' . $extension_code . '\' ';
        $sess_id .= ' AND extension_id = \'' . $extension_id . '\' ';
        $sql = ' DELETE FROM ' . $GLOBALS['ecs']->table('cart') . ' WHERE ' . $sess_id . ' ';
        $GLOBALS['db']->query($sql);
    }
    exit($json->encode($result));
}
/* 把优惠活动加入进货单 */
elseif ($_REQUEST['step'] == 'add_favourable')
{
    include_once('includes/cls_json.php');
    $json = new JSON();

    $ru_id = !empty($_REQUEST['ru_id']) ? intval($_REQUEST['ru_id']) : 0; // 被选中的优惠活动商品
    $act_sel_id = !empty($_REQUEST['sel_id']) ? addslashes($_REQUEST['sel_id']) : ''; // 被选中的优惠活动商品
    $sel_flag = !empty($_REQUEST['sel_flag']) ? addslashes($_REQUEST['sel_flag']) : ''; // 标志flag
    $act_sel = array('act_sel_id'=>$act_sel_id, 'act_sel'=>$sel_flag);

    // 获取要添加到进货单的赠品
    $select_gift = explode(',', $_POST['select_gift']);

    /* 取得优惠活动信息 */
    $act_id = intval($_POST['act_id']);
    $favourable = favourable_info($act_id);
    if (empty($favourable))
    {
        $result['error'] = 1;
        $result['message'] = $_LANG['favourable_not_exist'];
        die($json->encode($result));
    }

    /* 判断用户能否享受该优惠 */
    if (!favourable_available($favourable))
    {
        $result['error'] = 2;
        $result['message'] = $_LANG['favourable_not_available'];
        die($json->encode($result));
    }

    /* 检查进货单中是否已有该优惠 */
    $cart_favourable = cart_favourable($ru_id);
    if (favourable_used($favourable, $cart_favourable))
    {
        $result['error'] = 3;
        $result['message'] = $_LANG['favourable_used'];
        die($json->encode($result));
    }

    /* 赠品（特惠品）优惠 */
    if ($favourable['act_type'] == FAT_GOODS)
    {
        /* 检查是否选择了赠品 */
        if (empty($select_gift))
        {
            $result['error'] = 4;
            $result['message'] = $_LANG['pls_select_gift'];
            die($json->encode($result));
        }

        /* 检查是否已在进货单 */
        $sql = "SELECT goods_name" .
                " FROM " . $ecs->table('cart') .
                " WHERE " . $sess_id .
                " AND rec_type = '" . CART_GENERAL_GOODS . "'" .
                " AND is_gift = '$act_id'" .
                " AND goods_id " . db_create_in($select_gift);
        $gift_name = $db->getCol($sql);
        if (!empty($gift_name))
        {
            $result['error'] = 5;
            $result['message'] = sprintf($_LANG['gift_in_cart'], join(',', $gift_name));
            die($json->encode($result));
        }

        /* 检查数量是否超过上限 */
        $count = isset($cart_favourable[$act_id]) ? $cart_favourable[$act_id] : 0;
        if ($favourable['act_type_ext'] > 0 && $count + count($select_gift) > $favourable['act_type_ext'])
        {
            $result['error'] = 6;
            $result['message'] = $_LANG['gift_count_exceed'];
            die($json->encode($result));
        }

        /* 添加赠品到进货单 */
        foreach ($favourable['gift'] as $gift)
        {
            if (in_array($gift['id'], $select_gift))
            {
                add_gift_to_cart($act_id, $gift['id'], $gift['price'], $ru_id);
            }
        }

        // 返回优惠活动
        $favourable_box = cart_add_favourable_box($act_id, $act_sel, $ru_id);
        $result['goods_amount'] = $favourable_box['goods_amount'];
        unset($favourable_box['goods_amount']);
        $smarty->assign('activity', $favourable_box);
        $smarty->assign('ru_id', $ru_id);
        $result['content'] = $smarty->fetch("library/cart_favourable_box.lbi");
        $result['act_id'] = $act_id;

    }
    elseif ($favourable['act_type'] == FAT_DISCOUNT)
    {
        add_favourable_to_cart($act_id, $favourable['act_name'], cart_favourable_amount($favourable) * (100 - $favourable['act_type_ext']) / 100);
    }
    elseif ($favourable['act_type'] == FAT_PRICE)
    {
        add_favourable_to_cart($act_id, $favourable['act_name'], $favourable['act_type_ext']);
    }

    $result['ru_id'] = $ru_id;
    die($json->encode($result));
}
elseif ($_REQUEST['step'] == 'clear')
{
    $sql = "DELETE FROM " . $ecs->table('cart') . " WHERE " . $sess_id;
    $db->query($sql);

    ecs_header("Location:./\n");
}
elseif ($_REQUEST['step'] == 'drop_to_collect')
{
    $rec_id = intval($_GET['id']);
    if ($_SESSION['user_id'] > 0)
    {
        $goods_id = $db->getOne("SELECT  goods_id FROM " .$ecs->table('cart'). " WHERE rec_id = '$rec_id' AND " . $sess_id);
        $count = $db->getOne("SELECT goods_id FROM " . $ecs->table('collect_goods') . " WHERE user_id = '$_SESSION[user_id]' AND goods_id = '$goods_id'");
        if (empty($count))
        {
            $time = gmtime();
            $sql = "INSERT INTO " .$GLOBALS['ecs']->table('collect_goods'). " (user_id, goods_id, add_time)" .
                    "VALUES ('$_SESSION[user_id]', '$goods_id', '$time')";
            $db->query($sql);
        }
    }

    flow_drop_cart_goods($rec_id, $_REQUEST['step']);
    ecs_header("Location: flow.php\n");
    exit;
}

/* 验证红包序列号 */
elseif ($_REQUEST['step'] == 'validate_bonus')
{
    $bonus_psd = dsc_addslashes(trim($_REQUEST['bonus_psd']));
    if (!empty($bonus_psd))
    {
        $bonus = bonus_info(0, $bonus_psd, $_SESSION['cart_value']);
    }
    else
    {
        $bonus = array();
    }

    $bonus_kill = price_format($bonus['type_money'], false);

    include_once('includes/cls_json.php');
    $result = array('error' => '', 'content' => '');
    $json = new JSON();

    /* 取得购物类型 */
    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;
    $_POST['shipping_id'] = strip_tags(urldecode($_REQUEST['shipping_id']));
    $tmp_shipping_id_arr = $json->decode($_POST['shipping_id']);

    $warehouse_id = isset($_REQUEST['warehouse_id'])  ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id = isset($_REQUEST['area_id'])  ? intval($_REQUEST['area_id']) : 0;

    $smarty->assign('warehouse_id', $warehouse_id);
    $smarty->assign('area_id', $area_id);

    /* 获得收货人信息 */
    $consignee = get_consignee($_SESSION['user_id']);

    /* 对商品信息赋值 */
    $cart_goods = cart_goods($flow_type, $_SESSION['cart_value']); // 取得商品列表，计算合计

    if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
    {
        $result['error'] = $_LANG['no_goods_in_cart'];
    }
    else
    {
        /* 取得购物流程设置 */
        $smarty->assign('config', $_CFG);

        /* 取得订单信息 */
        $order = flow_order_info();


        if (((!empty($bonus) && $bonus['user_id'] == $_SESSION['user_id']) || ($bonus['type_money'] > 0 && empty($bonus['user_id']))) && $bonus['order_id'] <= 0)
        {
            //$order['bonus_kill'] = $bonus['type_money'];
            $now = gmtime();
            if ($now > $bonus['use_end_date'])
            {
                $order['bonus_id'] = '';
                $result['error']=$_LANG['bonus_use_expire'];
            }
            else
            {
                $order['bonus_id'] = $bonus['bonus_id'];
                $order['bonus_psd'] = $bonus_psd;
            }
        }
        else
        {
            //$order['bonus_kill'] = 0;
            $order['bonus_id'] = '';
            $result['error'] = $_LANG['invalid_bonus'];
        }

        //ecmoban模板堂 --zhuo start
        $cart_goods_number = get_buy_cart_goods_number($flow_type, $_SESSION['cart_value']);
        $smarty->assign('cart_goods_number', $cart_goods_number);

        $consignee['province_name'] = get_goods_region_name($consignee['province']);
        $consignee['city_name'] = get_goods_region_name($consignee['city']);
        $consignee['district_name'] = get_goods_region_name($consignee['district']);
        $consignee['street_name'] = get_goods_region_name($consignee['street']);
        $consignee['consignee_address'] = $consignee['province_name'] . $consignee['city_name'] . $consignee['district_name'] . $consignee['street_name'] . $consignee['address'];
        $smarty->assign('consignee', $consignee);

        $cart_goods_list = cart_goods($flow_type, $_SESSION['cart_value'], 1); // 取得商品列表，计算合计
        $smarty->assign('goods_list', $cart_goods_list);

        //切换配送方式 by kong
        $cart_goods_list = get_flowdone_goods_list($cart_goods_list, $tmp_shipping_id_arr);

        /* 计算订单的费用 */
        $total = order_fee($order, $cart_goods, $consignee, 0, $_SESSION['cart_value'], 0, $cart_goods_list);
        $smarty->assign('total', $total);
        //ecmoban模板堂 --zhuo end

        if ($total['goods_price'] < $bonus['min_goods_amount']) {
            $order['bonus_id'] = '';
            /* 重新计算订单 */
            $result['error'] = sprintf($_LANG['bonus_min_amount_error'], $bonus['min_goods_amount']);
        }

        /* 团购标志 */
        if ($flow_type == CART_GROUP_BUY_GOODS)
        {
            $smarty->assign('is_group_buy', 1);
        }
        elseif ($flow_type == CART_EXCHANGE_GOODS)
        {
            // 积分兑换 qin
            $smarty->assign('is_exchange_goods', 1);
        }

        $result['content'] = $smarty->fetch('library/order_total.lbi');
    }

    die($json->encode($result));
}

/* 验证储值卡密码 */
elseif ($_REQUEST['step'] == 'validate_value_card')
{
    $vc_psd = dsc_addslashes(trim($_REQUEST['vc_psd']));

    if (!empty($vc_psd))
    {
        $value_card = value_card_info(0, $vc_psd);
    }
    else
    {
        $value_card = array();
    }

    include_once('includes/cls_json.php');
    $result = array('error' => '', 'content' => '');

    $json = new JSON();

    /* 取得购物类型 */
    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    $warehouse_id = isset($_REQUEST['warehouse_id'])  ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id = isset($_REQUEST['area_id'])  ? intval($_REQUEST['area_id']) : 0;
    $_POST['shipping_id'] = strip_tags(urldecode($_REQUEST['shipping_id']));
    $tmp_shipping_id_arr = $json->decode($_POST['shipping_id']);

    $smarty->assign('warehouse_id', $warehouse_id);
    $smarty->assign('area_id', $area_id);

    /* 获得收货人信息 */
    $consignee = get_consignee($_SESSION['user_id']);

    /* 对商品信息赋值 */
    $cart_goods = cart_goods($flow_type, $_SESSION['cart_value']); // 取得商品列表，计算合计

    if (!empty($value_card)) {
        if ($value_card['use_condition'] == 1 && !comparison_cat($cart_goods, $value_card['spec_cat'])) {
            $value_card['error'] = true;
        }
        if ($value_card['use_condition'] == 2 && !comparison_goods($cart_goods, $value_card['spec_goods'])) {
            $value_card['error'] = true;
        };
    }

    if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
    {
        $result['error'] = $_LANG['no_goods_in_cart'];
    }
    else
    {
        /* 取得购物流程设置 */
        $smarty->assign('config', $_CFG);

        /* 取得订单信息 */
        $order = flow_order_info();

        if (!empty($value_card) && ($value_card['card_money'] > 0 && $value_card['user_id'] == 0) && empty($value_card['error'])) {
            $now = gmtime();
            if ($now > $value_card['end_time'] && $value_card['end_time'] > 0) {
                $order['vc_id'] = '';
                $result['error'] = $_LANG['vc_use_expire'];
            } else {
                $sql = " SELECT COUNT(*) FROM " . $ecs->table('value_card') . " WHERE user_id = '$_SESSION[user_id]' AND tid = '$value_card[tid]' ";
                $count = $db->getOne($sql);
                if ($count >= $value_card['vc_limit']) {
                    $order['vc_id'] = '';
                    $result['error'] = $_LANG['over_bind_limit'];
                } else {
                    $order['vc_id'] = $value_card['vid'];
                    $order['vc_psd'] = $vc_psd;
                }
            }
        } elseif ($value_card['error']) {
            $result['error'] = $_LANG['vc_no_use_order'];
            $order['vid'] = '';
        } else {
            $order['vid'] = '';
            $result['error'] = $_LANG['vc_not_exist'];
        }

        //ecmoban模板堂 --zhuo start
        $cart_goods_number = get_buy_cart_goods_number($flow_type, $_SESSION['cart_value']);
        $smarty->assign('cart_goods_number', $cart_goods_number);

        $consignee['province_name'] = get_goods_region_name($consignee['province']);
        $consignee['city_name'] = get_goods_region_name($consignee['city']);
        $consignee['district_name'] = get_goods_region_name($consignee['district']);
        $consignee['street_name'] = get_goods_region_name($consignee['street']);
        $consignee['consignee_address'] = $consignee['province_name'] . $consignee['city_name'] . $consignee['district_name'] . $consignee['street_name'] . $consignee['address'];
        $smarty->assign('consignee', $consignee);

        $cart_goods_list = cart_goods($flow_type, $_SESSION['cart_value'], 1); // 取得商品列表，计算合计
        $smarty->assign('goods_list', $cart_goods_list);

        //切换配送方式 by kong
        $cart_goods_list = get_flowdone_goods_list($cart_goods_list, $tmp_shipping_id_arr);

        /* 计算订单的费用 */
        $total = order_fee($order, $cart_goods, $consignee, 0, $_SESSION['cart_value'], 0, $cart_goods_list);
        $smarty->assign('total', $total);
        //ecmoban模板堂 --zhuo end

        /* 团购标志 */
        if ($flow_type == CART_GROUP_BUY_GOODS) {
            $smarty->assign('is_group_buy', 1);
        } elseif ($flow_type == CART_EXCHANGE_GOODS) {
            // 积分兑换 qin
            $smarty->assign('is_exchange_goods', 1);
        }

        $result['content'] = $smarty->fetch('library/order_total.lbi');
    }

    die($json->encode($result));
}

/*------------------------------------------------------ */
//-- 添加礼包到进货单
/*------------------------------------------------------ */
elseif ($_REQUEST['step'] == 'add_package_to_cart')
{
    include_once('includes/cls_json.php');
    $_POST['package_info'] = json_str_iconv($_POST['package_info']);

    $result = array('error' => 0, 'message' => '', 'content' => '', 'package_id' => '');
    $json  = new JSON;

    if (empty($_POST['package_info']))
    {
        $result['error'] = 1;
        die($json->encode($result));
    }

    $package = $json->decode($_POST['package_info']);

    $package->type = isset($package->type) && !empty($package->type) ? $package->type : 0;

    /* 如果是一步购物，先清空进货单 */
    if ($_CFG['one_step_buy'] == '1')
    {
        clear_cart();
    }

    /* 商品数量是否合法 */
    if (!is_numeric($package->number) || intval($package->number) <= 0)
    {
        $result['error']   = 1;
        $result['message'] = $_LANG['invalid_number'];
    }
    else
    {
        /* 添加到进货单 */
        if (add_package_to_cart($package->package_id, $package->number, $package->warehouse_id, $package->area_id, $package->type))
        {
            if ($_CFG['cart_confirm'] > 2)
            {
                $result['message'] = '';
            }
            else
            {
                $result['message'] = $_CFG['cart_confirm'] == 1 ? $_LANG['addto_cart_success_1'] : $_LANG['addto_cart_success_2'];
            }

            $result['content'] = insert_cart_info(4);
            $result['one_step_buy'] = $_CFG['one_step_buy'];
        }
        else
        {
            $result['message']    = $err->last_message();
            $result['error']      = $err->error_no;
            $result['package_id'] = stripslashes($package->package_id);
        }
    }

    $confirm_type = isset($package->confirm_type) ? $package->confirm_type : 0;

    if($confirm_type > 0){
        $result['confirm_type'] = $confirm_type;
    }else{
        $result['confirm_type'] = !empty($_CFG['cart_confirm']) ? $_CFG['cart_confirm'] : 2;
    }

    die($json->encode($result));
}
/*------------------------------------------------------ */
//-- 显示赠品商品
/*------------------------------------------------------ */
elseif ($_REQUEST['step'] == 'show_gift_div')
{
    include_once('includes/cls_json.php');
    $json  = new JSON;
    $favourable_id = $_POST['favourable_id'];

    $ru_id = !empty($_REQUEST['ru_id']) ? intval($_REQUEST['ru_id']) : 0; // 被选中的商品商家ID
    $act_sel_id = !empty($_REQUEST['sel_id']) ? addslashes($_REQUEST['sel_id']) : ''; // 被选中的优惠活动商品
    $sel_flag = !empty($_REQUEST['sel_flag']) ? addslashes($_REQUEST['sel_flag']) : ''; // 标志flag
    $act_sel = array('act_sel_id'=>$act_sel_id, 'act_sel'=>$sel_flag);

    $favourable = favourable_list($_SESSION['user_rank'], -1, $favourable_id, $act_sel, $ru_id);
    $activity = $favourable[0];

    $activity['act_type_ext'] = intval($activity['act_type_ext']); // 允许最大数量
    // 已加入进货单的活动赠品数量
    $cart_favourable_num = cart_favourable($ru_id);
    $activity['cart_favourable_gift_num'] = !empty($cart_favourable_num[$favourable_id])? intval($cart_favourable_num[$favourable_id]): 0;
    $activity['favourable_used'] = favourable_used($activity, $cart_favourable_num);
    $activity['left_gift_num'] = intval($activity['act_type_ext']) - (empty($cart_favourable_num[$activity['act_id']])? 0: intval($cart_favourable_num[$activity['act_id']]));

    foreach ($activity['gift'] as $key => $row)
    {
        $activity['act_gift_list'][$key] = $row;
        $activity['act_gift_list'][$key]['url'] = build_uri('goods', array('gid'=>$row['id']), $row['name']);
    }

    $smarty->assign('activity', $activity);
    $smarty->assign('ru_id', $ru_id);
    $smarty->assign('gift_div', 1);
    $smarty->assign('act_id', $favourable_id);
    $result['content'] = $smarty->fetch("library/cart_gift_box.lbi");
    $result['act_id'] = $favourable_id;
    $result['ru_id'] = $ru_id;

    die($json->encode($result));
}

//  结算页面收货地址编辑
else if($_REQUEST['step'] == 'edit_Consignee')
{
    include('includes/cls_json.php');

    $json   = new JSON;
    $res    = array('message' => '', 'result' => '', 'qty' => 1);
    $address_id = isset($_REQUEST['address_id']) ? intval($_REQUEST['address_id']) : 0;

    if($address_id == 0){
        $consignee['country'] = 1;
        $consignee['province'] = 0;
        $consignee['city'] = 0;
    }

    $consignee = get_update_flow_Consignee($address_id);
    $smarty->assign('consignee', $consignee);

    /* 取得国家列表、商店所在国家、商店所在国家的省列表 */
    $smarty->assign('country_list',       get_regions());

    $smarty->assign('please_select',       '请选择');

    $province_list = get_regions_log(1,$consignee['country']);
    $city_list     = get_regions_log(2,$consignee['province']);
    $district_list = get_regions_log(3,$consignee['city']);
    $street_list = get_regions_log(4,$consignee['district']);

    $smarty->assign('province_list', $province_list);
    $smarty->assign('city_list',     $city_list);
    $smarty->assign('district_list', $district_list);
    $smarty->assign('street_list', $street_list);

    //有存在虚拟和实体商品 start
    get_goods_flow_type($_SESSION['cart_value']);
    //有存在虚拟和实体商品 end

    if($_SESSION['user_id'] <= 0){
            $result['error']  = 2;
            $result['message']  = $_LANG['lang_crowd_not_login'];
    }else{
            $result['error']  = 0;
            $result['content'] = $smarty->fetch("library/consignee_new.lbi");
    }

    die($json->encode($result));

}

/**
 * 添加收货地址
 */
 else if ($_REQUEST['step'] == 'insert_Consignee') {
    include('includes/cls_json.php');

    $time = gmtime();
    $json = new JSON;
    $result = array('message' => '', 'result' => '', 'error' => 0);

    $uc_id = isset($_REQUEST['uc_id']) ? intval($_REQUEST['uc_id']) : 0;

    $_REQUEST['csg'] = isset($_REQUEST['csg']) ? json_str_iconv($_REQUEST['csg']) : '';
    $csg = $json->decode($_REQUEST['csg']);

    $_POST['shipping_id'] = strip_tags(urldecode($_REQUEST['shipping_id']));
    $tmp_shipping_id_arr = $json->decode($_POST['shipping_id']);

    $consignee = array(
        'address_id' => empty($csg->address_id) ? 0 : intval($csg->address_id),
        'consignee' => empty($csg->consignee) ? '' : compile_str(trim($csg->consignee)),
        'country' => empty($csg->country) ? 0 : intval($csg->country),
        'province' => empty($csg->province) ? 0 : intval($csg->province),
        'city' => empty($csg->city) ? 0 : intval($csg->city),
        'district' => empty($csg->district) ? 0 : intval($csg->district),
        'street' => empty($csg->street) ? 0 : intval($csg->street),
        'email' => empty($csg->email) ? '' : compile_str($csg->email),
        'address' => empty($csg->address) ? '' : compile_str($csg->address),
        'zipcode' => empty($csg->zipcode) ? '' : compile_str(make_semiangle(trim($csg->zipcode))),
        'tel' => empty($csg->tel) ? '' : compile_str(make_semiangle(trim($csg->tel))),
        'mobile' => empty($csg->mobile) ? '' : compile_str(make_semiangle(trim($csg->mobile))),
        'sign_building' => empty($csg->sign_building) ? '' : compile_str($csg->sign_building),
        'userUp_time' => $time,
        'best_time' => empty($csg->best_time) ? '' : compile_str($csg->best_time),
    );

    if ($consignee) {
        setcookie('province', $consignee['province'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('city', $consignee['city'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('district', $consignee['district'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('street', $consignee['street'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('street_area', '', gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

        $flow_warehouse = get_warehouse_goods_region($consignee['province']);
        setcookie('area_region', $flow_warehouse['region_id'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('flow_region', $flow_warehouse['region_id'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
    }

    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    if ($result['error'] == 0) {

        if ($_SESSION['user_id'] > 0) {

            $sql = "SELECT COUNT(*) FROM" . $ecs->table('region') . "WHERE parent_id = '" . $consignee['city'] . "'";
            $district_count = $db->getOne($sql);

            $sql = "SELECT COUNT(*) FROM" . $ecs->table('region') . "WHERE parent_id = '" . $consignee['district'] . "'";
            $street_count = $db->getOne($sql);

            include_once(ROOT_PATH . 'includes/lib_transaction.php');
             //验证传入数据
            if ($consignee['district'] == '' && $district_count && $csg->goods_flow_type == 101) {
                $result['error'] = 4;
                $result['message'] = $_LANG['district_null'];
            } elseif ($consignee['street'] == '' && $street_count && $csg->goods_flow_type == 101) {
                $result['error'] = 4;
                $result['message'] = $_LANG['street_null'];
            }
            if($result['error'] == 0){
                if ($consignee['address_id'] > 0) {
                    $addressId = " and address_id <> '" . $consignee['address_id'] . "' ";
                }

                $sql = "SELECT COUNT(*) FROM " . $ecs->table('user_address') . " WHERE consignee = '" . $consignee['consignee'] . "'" .
                        " AND country = '" . $consignee['country'] . "'" .
                        " AND province = '" . $consignee['province'] . "'" .
                        " AND city = '" . $consignee['city'] . "'" .
                        " AND district = '" . $consignee['district'] . "'" .
                        " AND user_id = '" . $_SESSION['user_id'] . "'" . $addressId;
                $row = $db->getOne($sql);

                if ($row > 0) {
                    $result['error'] = 4;
                    $result['message'] = $_LANG['Distribution_exists'];
                } else {
                    $result['error'] = 0;

                    /* 如果用户已经登录，则保存收货人信息 */
                    $consignee['user_id'] = $_SESSION['user_id'];
                    $saveConsignee = save_consignee($consignee, true);

                    $sql = "select address_id from " . $GLOBALS['ecs']->table('users') . " where user_id = '" . $_SESSION['user_id'] . "'";
                    $user_address_id = $GLOBALS['db']->getOne($sql);

                    if ($user_address_id > 0) {
                        $consignee['address_id'] = $user_address_id;
                    }

                    $sql = "select count(*) from " . $GLOBALS['ecs']->table('user_address') . " where user_id = '" . $_SESSION['user_id'] . "'";
                    $count = $GLOBALS['db']->getOne($sql);

                    if ($_CFG['auditStatus'] == 1) {
                        if ($count <= $_CFG['auditCount']) {
                            $result['message'] = '';
                        } else {
                            if ($saveConsignee['update'] == false) {
                                if ($consignee['address_id'] > 0) {
                                    $result['message'] = $_LANG['edit_success_one'];
                                } else {
                                    $result['message'] = $_LANG['add_success_one'];
                                }
                            } else {
                                $result['message'] = '';
                            }
                        }
                    } else {
                        if ($consignee['address_id'] > 0) {

                            $sql = "UPDATE " . $GLOBALS['ecs']->table('users') . " SET address_id = '" . $consignee['address_id'] . "' " . " WHERE user_id = '" . $consignee['user_id'] . "'";
                            $GLOBALS['db']->query($sql);
                            $_SESSION['flow_consignee'] = $consignee;

                            $result['message'] = $_LANG['edit_success_two'];
                        } else {
                            $result['message'] = $_LANG['add_success_two'];
                        }
                    }
                }
            }
            $user_address = get_order_user_address_list($_SESSION['user_id']);
            $smarty->assign('user_address', $user_address);
            $consignee['province_name'] = get_goods_region_name($consignee['province']);
            $consignee['city_name'] = get_goods_region_name($consignee['city']);
            $consignee['district_name'] = get_goods_region_name($consignee['district']);
            $consignee['street_name'] = get_goods_region_name($consignee['street']);
            $consignee['consignee_address'] = $consignee['province_name'] . $consignee['city_name'] . $consignee['district_name'] . $consignee['street_name'] . $consignee['address'];

            $smarty->assign('consignee', $consignee);

            $result['content'] = $smarty->fetch("library/consignee_flow.lbi");

            $region_id = get_province_id_warehouse($consignee['province']);
            $area_info = get_area_info($consignee['province']);

            $smarty->assign('warehouse_id', $region_id);
            $smarty->assign('area_id', $area_info['region_id']);

            /* 对商品信息赋值 */
            $cart_goods_list = cart_goods($flow_type, $_SESSION['cart_value'], 1, $region_id, $area_info['region_id']); // 取得商品列表，计算合计
            $cart_goods_list_new = cart_by_favourable($cart_goods_list);

            $cart_goods = get_new_group_cart_goods($cart_goods_list_new);

            //有存在虚拟和实体商品 start
            get_goods_flow_type($_SESSION['cart_value']);
            //有存在虚拟和实体商品 end

            $smarty->assign('goods_list', $cart_goods_list_new);
            $result['goods_list'] = $smarty->fetch('library/flow_cart_goods.lbi'); //送货清单

            /* 取得订单信息 */
            $order = flow_order_info();
            $cart_goods_number = get_buy_cart_goods_number($flow_type, $_SESSION['cart_value']);
            $smarty->assign('cart_goods_number', $cart_goods_number);

            //切换配送方式 by kong
            $cart_goods_list = get_flowdone_goods_list($cart_goods_list, $tmp_shipping_id_arr);

            $coupons_info = get_coupons($uc_id, array('c.cou_id', 'c.cou_man', 'c.cou_type', 'c.ru_id', 'c.cou_money', 'cu.uc_id', 'cu.user_id'));

            /* 优惠券 免邮 start */
            $not_freightfree = 0;
            if (!empty($coupons_info) && $cart_goods_list) {

                if ($coupons_info['cou_type'] == 5) {
                    $cou_goods = array();
                    $goods_amount = 0;
                    foreach ($cart_goods_list as $key => $row) {
                        if ($row['ru_id'] == $coupons_info['ru_id']) {
                            $cou_goods = $row['goods_list'];
                            foreach ($row['goods_list'] as $gkey => $grow) {
                                $goods_amount += $grow['goods_price'] * $grow['goods_number'];
                            }
                        }
                    }

                    if ($goods_amount >= $coupons_info['cou_man'] || $coupons_info['cou_man'] == 0) {

                        $cou_region = get_coupons_region($coupons_info['cou_id']);
                        $cou_region = !empty($cou_region) ? explode(",", $cou_region) : array();

                        /* 是否含有不支持免邮的地区 */
                        if ($cou_region && in_array($consignee['province'], $cou_region)) {
                            $not_freightfree = 1;
                        }
                    }
                }
            }
            /* 优惠券 免邮 start */

            $result['not_freightfree'] = $not_freightfree;

            $type = array(
                'type' => 0,
                'shipping_list' => $tmp_shipping_id_arr,
                'step' => $_REQUEST['step']
            );

            /* 计算订单的费用 */
            $total = order_fee($order, $cart_goods, $consignee, $type, $_SESSION['cart_value'], 0, $cart_goods_list);
            $smarty->assign('total', $total);
            $result['order_total'] = $smarty->fetch('library/order_total.lbi'); //费用汇总
        } else {
            $result['error'] = 2;
            $result['message'] = $_LANG['lang_crowd_not_login'];
        }
    }

    die($json->encode($result));
}

/**
 * 删除收货地址
 */
else if ($_REQUEST['step'] == 'delete_Consignee') {
    include('includes/cls_json.php');

    $json = new JSON;
    $res = array('message' => '', 'result' => '', 'qty' => 1);

    $result['error'] = 0;

    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    $address_id = isset($_REQUEST['address_id']) ? intval($_REQUEST['address_id']) : 0;
	$type = isset($_REQUEST['type']) ? intval($_REQUEST['type']) : 0;

    $sql = "delete from " . $ecs->table('user_address') . " where address_id = '$address_id'";
    $db->query($sql);

    $consignee = $_SESSION['flow_consignee'];
    $smarty->assign('consignee', $consignee);

    if ($consignee) {
        setcookie('province', $consignee['province'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('city', $consignee['city'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('district', $consignee['district'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('street', $consignee['street'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('street_area', '', gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

        $flow_warehouse = get_warehouse_goods_region($consignee['province']);
        setcookie('area_region', $flow_warehouse['region_id'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('flow_region', $flow_warehouse['region_id'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
    }

    $region_id = get_province_id_warehouse($consignee['province']);
    $area_info = get_area_info($consignee['province']);

    $smarty->assign('warehouse_id', $region_id);
    $smarty->assign('area_id', $area_info['region_id']);

    $user_address = get_order_user_address_list($_SESSION['user_id']);
    $smarty->assign('user_address', $user_address);

    //有存在虚拟和实体商品 start
    get_goods_flow_type($_SESSION['cart_value']);
    //有存在虚拟和实体商品 end

    if (!$user_address) {
        $consignee = array(
            'province' => 0,
            'city' => 0
        );
        // 取得国家列表、商店所在国家、商店所在国家的省列表
        $smarty->assign('country_list', get_regions());
        $smarty->assign('please_select', $_LANG['please_select']);

        $province_list = get_regions_log(1, 1);
        $city_list = get_regions_log(2, $consignee['province']);
        $district_list = get_regions_log(3, $consignee['city']);

        $smarty->assign('province_list', $province_list);
        $smarty->assign('city_list', $city_list);
        $smarty->assign('district_list', $district_list);
        $smarty->assign('consignee', $consignee);

        $result['error'] = 2;
		if($type == 1){
			$result['content'] = $smarty->fetch("library/consignee_flow.lbi");
		}else{
			$result['content'] = $smarty->fetch("library/consignee_new.lbi");
		}

    } else {
        $result['content'] = $smarty->fetch("library/consignee_flow.lbi");
    }

    // 获取用户收货地址
    $consignee = get_consignee($_SESSION['user_id']);

    /* 初始化地区ID */
    $consignee['country'] = !isset($consignee['country']) && empty($consignee['country']) ? 0 : intval($consignee['country']);
    $consignee['province'] = !isset($consignee['province']) && empty($consignee['province']) ? 0 : intval($consignee['province']);
    $consignee['city'] = !isset($consignee['city']) && empty($consignee['city']) ? 0 : intval($consignee['city']);
    $consignee['district'] = !isset($consignee['district']) && empty($consignee['district']) ? 0 : intval($consignee['district']);
    $consignee['street'] = !isset($consignee['street']) && empty($consignee['street']) ? 0 : intval($consignee['street']);
    $consignee['address'] = !isset($consignee['address']) && empty($consignee['address']) ? '' : intval($consignee['address']);

    if (empty($consignee)) {
        $consignee = array('country' => 0, 'province' => 0, 'city' => 0, 'district' => 0, 'street' => 0, 'province_name' => '', 'city_name' => '', 'district_name' => '', 'street_name' => '', 'address' => '');
    }

    $consignee['province_name'] = get_goods_region_name($consignee['province']);
    $consignee['city_name'] = get_goods_region_name($consignee['city']);
    $consignee['district_name'] = get_goods_region_name($consignee['district']);
    $consignee['street_name'] = get_goods_region_name($consignee['street']);
    $consignee['region'] = $consignee['province_name'] . "&nbsp;" . $consignee['city_name'] . "&nbsp;" . $consignee['district_name'] . "&nbsp;" . $consignee['street_name'];
    $consignee['consignee_address'] = $consignee['province_name'] . $consignee['city_name'] . $consignee['district_name'] . $consignee['street_name'] . $consignee['address'];

    $_SESSION['flow_consignee'] = $consignee;
    $smarty->assign('consignee', $consignee);

    /* 对商品信息赋值 */
    $cart_goods_list = cart_goods($flow_type, $_SESSION['cart_value'], 1, $region_id, $area_info['region_id']); // 取得商品列表，计算合计
    $cart_goods_list_new = cart_by_favourable($cart_goods_list);
    $smarty->assign('goods_list', $cart_goods_list_new);

    $cart_goods = get_new_group_cart_goods($cart_goods_list_new);

    //有存在虚拟和实体商品 start
    get_goods_flow_type($_SESSION['cart_value']);
    //有存在虚拟和实体商品 end

    $result['goods_list'] = $smarty->fetch('library/flow_cart_goods.lbi'); //送货清单

    /* 取得订单信息 */
    $order = flow_order_info();

    $cart_goods_number = get_buy_cart_goods_number($flow_type, $_SESSION['cart_value']);
    $smarty->assign('cart_goods_number', $cart_goods_number);
    /* 计算订单的费用 */
    $total = order_fee($order, $cart_goods, $consignee, 0, $_SESSION['cart_value'], 0, $cart_goods_list);
    $smarty->assign('total', $total);
    $result['order_total'] = $smarty->fetch('library/order_total.lbi'); //费用汇总

    die($json->encode($result));
}

// 结算页面 切换收货地址
else if($_REQUEST['step'] == 'edit_consignee_checked')
{
    include('includes/cls_json.php');
    /* 取得购物类型 */
    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;
    $_SESSION['cart_value'] = isset($_SESSION['cart_value']) ?  $_SESSION['cart_value'] : '';

    $json = new JSON;
    $res = array('msg' => '', 'result' => '', 'qty' => 1);
    $result['error'] = 0;

    $_POST['shipping_id']=strip_tags(urldecode($_REQUEST['shipping_id']));
    $tmp_shipping_id_arr = $json->decode($_POST['shipping_id']);

    $uc_id = isset($_REQUEST['uc_id']) ? intval($_REQUEST['uc_id']) : 0;
    $address_id = isset($_REQUEST['address_id']) ? intval($_REQUEST['address_id']) : 0;
    $store_id = isset($_REQUEST['store_id']) ? intval($_REQUEST['store_id']) : 0;
    $store_seller = ($store_id > 0)  ?  'store_seller' : '';
    $smarty->assign('store_seller', $store_seller);
    $_SESSION['merchants_shipping'] = array(); //默认快递

    $consignee = get_update_flow_Consignee($address_id);

    /* 初始化地区ID */
    $consignee['country']       = !isset($consignee['country']) && empty($consignee['country'])                ?    0 :   intval($consignee['country']);
    $consignee['province']      = !isset($consignee['province']) && empty($consignee['province'])              ?    0 :   intval($consignee['province']);
    $consignee['city']          = !isset($consignee['city']) && empty($consignee['city'])                      ?    0 :   intval($consignee['city']);
    $consignee['district']      = !isset($consignee['district']) && empty($consignee['district'])              ?    0 :   intval($consignee['district']);
    $consignee['street']        = !isset($consignee['street']) && empty($consignee['street'])                  ?    0 :   intval($consignee['street']);

    $_SESSION['flow_consignee'] = $consignee;

    if($consignee){
        setcookie('province', $consignee['province'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('city', $consignee['city'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('district', $consignee['district'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('street', $consignee['street'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('street_area', '', gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

        $flow_warehouse = get_warehouse_goods_region($consignee['province']);
        setcookie('area_region', $flow_warehouse['region_id'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('flow_region', $flow_warehouse['region_id'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
    }

    $region_id = get_province_id_warehouse($consignee['province']);
    $area_info = get_area_info($consignee['province']);

    $smarty->assign('warehouse_id', $region_id);
    $smarty->assign('area_id', $area_info['region_id']);
     $smarty->assign('store_id', $store_id);

    $consignee['province_name'] = get_goods_region_name($consignee['province']);
    $consignee['city_name'] = get_goods_region_name($consignee['city']);
    $consignee['district_name'] = get_goods_region_name($consignee['district']);
    $consignee['street_name'] = get_goods_region_name($consignee['street']);
    $consignee['consignee_address'] = $consignee['province_name'] . $consignee['city_name'] . $consignee['district_name'] . $consignee['street_name'] . $consignee['address'];
    $smarty->assign('consignee', $consignee);

    $cart_goods_number = get_buy_cart_goods_number($flow_type, $_SESSION['cart_value']);
    $smarty->assign('cart_goods_number', $cart_goods_number);

    $user_address = get_order_user_address_list($_SESSION['user_id']);

    if(!$user_address && $consignee){
        $consignee['province_name'] = get_goods_region_name($consignee['province']);
        $consignee['city_name'] = get_goods_region_name($consignee['city']);
        $consignee['district_name'] = get_goods_region_name($consignee['district']);
        $consignee['street_name'] = get_goods_region_name($consignee['street']);
        $consignee['region'] = $consignee['province_name'] ."&nbsp;". $consignee['city_name'] ."&nbsp;". $consignee['district_name'] ."&nbsp;". $consignee['street_name'];

        $user_address = array($consignee);
    }

    $smarty->assign('user_address', $user_address);

    $result['content'] = $smarty->fetch("library/consignee_flow.lbi");

    /* 对商品信息赋值 */
    $cart_goods_list = cart_goods($flow_type, $_SESSION['cart_value'], 1, $region_id, $area_info['region_id'], '', $store_id); // 取得商品列表，计算合计
    $cart_goods_list_new = cart_by_favourable($cart_goods_list);
    $smarty->assign('goods_list', $cart_goods_list_new);

    $cart_goods = get_new_group_cart_goods($cart_goods_list_new);

    if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
    {
        //ecmoban模板堂 --zhuo start
        if(empty($cart_goods)){
            $result['error'] = 1;
            $result['msg'] = $_LANG['cart_or_login_not'] ;
        }elseif(!check_consignee_info($consignee, $flow_type)){
            $result['error'] = 2;
            $result['msg'] = $_LANG['address_Prompt'];
        }
        //ecmoban模板堂 --zhuo end
    }
    else
    {
        $region_id = get_province_id_warehouse($consignee['province']);
        $area_info = get_area_info($consignee['province']);

        /* 取得购物流程设置 */
        $smarty->assign('config', $_CFG);
        /* 取得订单信息 */
        $order = flow_order_info();

        //切换配送方式 by kong
        $cart_goods_list = get_flowdone_goods_list($cart_goods_list, $tmp_shipping_id_arr);

        $coupons_info = get_coupons($uc_id, array('c.cou_id', 'c.cou_man', 'c.cou_type', 'c.ru_id', 'c.cou_money', 'cu.uc_id', 'cu.user_id'));

        /* 优惠券 免邮 start */
        $not_freightfree = 0;
        if (!empty($coupons_info) && $cart_goods_list) {

            if ($coupons_info['cou_type'] == 5) {
                $cou_goods = array();
                $goods_amount = 0;
                foreach ($cart_goods_list as $key => $row) {
                    if ($row['ru_id'] == $coupons_info['ru_id']) {
                        $cou_goods = $row['goods_list'];
                        foreach ($row['goods_list'] as $gkey => $grow) {
                            $goods_amount += $grow['goods_price'] * $grow['goods_number'];
                        }
                    }
                }

                if ($goods_amount >= $coupons_info['cou_man'] || $coupons_info['cou_man'] == 0) {

                    $cou_region = get_coupons_region($coupons_info['cou_id']);
                    $cou_region = !empty($cou_region) ? explode(",", $cou_region) : array();

                    /* 是否含有不支持免邮的地区 */
                    if ($cou_region && in_array($consignee['province'], $cou_region)) {
                        $not_freightfree = 1;
                    }
                }
            }
        }
        /* 优惠券 免邮 start */

        $result['not_freightfree'] = $not_freightfree;

        /* 计算订单的费用 */
        $total = order_fee($order, $cart_goods, $consignee, 0, $_SESSION['cart_value'], 0, $cart_goods_list, 0, 0, $store_id);
        $smarty->assign('total', $total);
        /* 团购标志 */
        if ($flow_type == CART_GROUP_BUY_GOODS)
        {
            $smarty->assign('is_group_buy', 1);
        }
        elseif ($flow_type == CART_EXCHANGE_GOODS)
        {
            // 积分兑换 qin
            $smarty->assign('is_exchange_goods', 1);
        }

        //有存在虚拟和实体商品 start
        get_goods_flow_type($_SESSION['cart_value']);
        //有存在虚拟和实体商品 end

        $result['goods_list'] = $smarty->fetch('library/flow_cart_goods.lbi');//送货清单
        $result['order_total'] = $smarty->fetch('library/order_total.lbi');//费用汇总
    }

    die($json->encode($result));
}

/*by kong 切换门店，处理点单页面刷新，检查商品库存 start 20160722*/
elseif($_REQUEST['step'] == 'edit_offline_store'){
    include('includes/cls_json.php');
    /* 取得购物类型 */
    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;
    $cart_value = isset($_REQUEST['cart_value']) && !empty($_REQUEST['cart_value']) ? addslashes($_REQUEST['cart_value']) : $_SESSION['cart_value'];

    $json = new JSON;
    $res = array('msg' => '', 'result' => '', 'qty' => 1);
    $result['error'] = 0;
    $store_id = isset($_REQUEST['store_id']) ? intval($_REQUEST['store_id']): 0;
    $store_seller = ($store_id > 0)  ?  'store_seller' : '';
    $smarty->assign('store_seller', $store_seller);
    $smarty->assign('store_id',$store_id);

    /* 对商品信息赋值 */
    $cart_goods_list = cart_goods($flow_type, $_SESSION['cart_value'], 1, $region_id, $area_info['region_id'], '', $store_id); // 取得商品列表，计算合计
    $cart_goods_list_new = cart_by_favourable($cart_goods_list);
    $smarty->assign('goods_list', $cart_goods_list_new);

    $cart_goods = get_new_group_cart_goods($cart_goods_list_new);

    $cart_goods_number = get_buy_cart_goods_number($flow_type, $cart_value);
    $smarty->assign('cart_goods_number', $cart_goods_number);
    if (empty($cart_goods))
    {
        if(empty($cart_goods)){
            $result['error'] = 1;
            $result['msg'] = $_LANG['cart_or_login_not'];
        }
    }else{
        /* 取得购物流程设置 */
        $smarty->assign('config', $_CFG);

        /* 取得订单信息 */
        $order = flow_order_info();

        /* 计算订单的费用 */
        $total = order_fee($order, $cart_goods, '', 0, $cart_value, 0, $cart_goods_list, 0, 0, $store_id);
        $smarty->assign('total', $total);

        /* 团购标志 */
        if ($flow_type == CART_GROUP_BUY_GOODS)
        {
            $smarty->assign('is_group_buy', 1);
        }
        elseif ($flow_type == CART_EXCHANGE_GOODS)
        {
            // 积分兑换 qin
            $smarty->assign('is_exchange_goods', 1);
        }

        //有存在虚拟和实体商品 start
        get_goods_flow_type($cart_value);
        //有存在虚拟和实体商品 end

        $result['goods_list'] = $smarty->fetch('library/flow_cart_goods.lbi');//送货清单
        $result['order_total'] = $smarty->fetch('library/order_total.lbi');//费用汇总
    }

    die($json->encode($result));
}
/*by kong 切换门店，处理点单页面刷新，检查商品库存 end 20160722*/
//@author-bylu 处理在订单页刷新,订单丢失问题 start;
else if($_REQUEST['step'] == 'order_reload'){

    include_once('includes/lib_clips.php');
    include_once('includes/lib_payment.php');

    //取出当前用户当前订单,的订单信息;
    $order_info = $_SESSION['order_reload'][$user_id];

    //取的订单信息;
    $order = $db->getRow("SELECT * FROM " . $ecs->table('order_info') . " WHERE order_id='" . $order_info['order_id'] . "' LIMIT 1");

    //获取log_id
    $order['log_id'] = $GLOBALS['db']->getOne(" SELECT log_id FROM " . $GLOBALS['ecs']->table('pay_log') . " WHERE order_id = '" . $order_info['order_id'] . "' LIMIT 1 ");

    /* 取得支付信息，生成支付代码 */
    if ($order['order_amount'] > 0) {

        //查询"在线支付"的pay_id;
        $onlinepay_pay_id=$db->getOne("SELECT pay_id FROM ".$ecs->table('payment')." WHERE pay_code='onlinepay'");
        //@模板堂-bylu 如果选择的是在线支付,循环出商家启用的所有在线支付方法 start
        if ($order['pay_id'] == $onlinepay_pay_id) {

            //用于判断当前用户是否拥有白条支付授权(有的话才显示"白条支付按钮");
            $baitiao_balance = get_baitiao_balance($user_id);

            $payment_list = available_payment_list(0, $cod_fee);

            //取出所有在线支付方法(含按钮);
            foreach ($payment_list as $k => $v) {
                if ($v['is_online'] == 1) {
                    include_once("includes/modules/payment/{$v['pay_code']}.php");
                    if (class_exists($v['pay_code'])) {
                        $pay_obj = new $v['pay_code'];
                        $payment = payment_info($v['pay_id']);
                        $pay_online_button[$v['pay_code']] = <<<HTML

      {$pay_obj->get_code($order, unserialize_config($v['pay_config']))}
HTML;
                        //判断已安装的支付方法中是否有"支付宝网银直连"方法;
                        if ($v['pay_code'] == 'alipay_bank') {
                            //重新赋值支付宝网银直连的支付按钮,将支付按钮列表中的删除;
                            $smarty->assign('is_alipay_bank', $pay_online_button['alipay_bank']);
                            unset($pay_online_button['alipay_bank']);
                        }
                        if ($v['pay_code'] == 'balance') {
                            $pay_online_button['balance'] = <<<HTML
                    <a href="flow.php?step=done&act=balance&order_sn={$order['order_sn']}" id="balance" order_sn="{$order['order_sn']}" flag="balance" >{$_LANG['balance_pay']}</a>
HTML;
                        }
                        //判断当前用户是否拥有白条支付授权(有的话才显示"白条支付按钮");
                        if ($baitiao_balance && $baitiao_balance['balance'] > 0) {
                            $smarty->assign('is_chunsejinrong', true);
                            if ($v['pay_code'] == 'chunsejinrong') {
                                $pay_online_button['chunsejinrong'] = <<<HTML
                            <a href="flow.php?step=done&act=chunsejinrong&order_sn={$order['order_sn']}" id="chunsejinrong" style="height:36px; line-height:36px; float: left;" order_sn="{$order['order_sn']}" flag="chunsejinrong" >{$_LANG['ious_pay']}</a>
HTML;
                            }
                        }
                    }
                }
            }

            $smarty->assign('pay_online_button', $pay_online_button); //在线支付按钮数组;
            $smarty->assign('is_onlinepay', true); //在线支付标记 by lu;
            if ($_SESSION['flow_type'] == 5) {//预售商品标记 by liu
                $smarty->assign('is_presale_goods', true);
            }
            //@模板堂-bylu  end
        } else {
            $payment = payment_info($order['pay_id']);

            include_once('includes/modules/payment/' . $payment['pay_code'] . '.php');

            $pay_obj = new $payment['pay_code'];

            $pay_online = $pay_obj->get_code($order, unserialize_config($payment['pay_config']));

            $order['pay_desc'] = $payment['pay_desc'];
        }
        $smarty->assign('pay_online', $pay_online);

    }else{
        $payment = payment_info($order['pay_id']);
        $order['pay_code'] = $payment['pay_code'];
    }

    //处理价格显示
    $order['format_shipping_fee'] = price_format($order['shipping_fee']);
    $order['format_order_amount'] = price_format($order['order_amount']);

    //判断当前订单是否是"白条分期"订单,是的话显示分期信息;
    if(isset($order_info['stages_qishu'])){
        $order_info['baitiao'] = isset($baitiao_balance['balance']) && !empty($baitiao_balance['balance']) ? $baitiao_balance['balance'] : 0;
        $smarty->assign('stages_info', $order_info);
    }

    //"买了又买";
    $goods_buy_list = get_order_goods_buy_list($region_id, $area_id);
    $smarty->assign('goods_buy_list', $goods_buy_list);

    //@author-bylu 处理在订单页刷新,订单丢失问题 end;
    if (defined('THEME_EXTENSION')) {
        $region = array(
            'province' => $order['province'],
            'city' => $order['city'],
            'district' => $order['district'],
            'street' => $order['street']
        );
        $address_info = get_area_region_info($region);
        $smarty->assign('address_info', $address_info); //收货地址
        $order['region'] = $address_info;
    }

    $smarty->assign('order', $order);

    $sql = "SELECT count(*) FROM " . $ecs->table('order_info') . " WHERE main_order_id = '" . $order['order_id'] . "'";
    $child_order = $db->getOne($sql, true);
    if ($child_order > 1) {
        $child_order_info = get_child_order_info($order['order_id']);
        $smarty->assign('child_order_info', $child_order_info);
    }

    $smarty->assign('child_order', $child_order);
}
/*  @author-bylu 支付成功页 start  */
else if($_REQUEST['step'] == 'pay_success'){

	/* 取得购物类型 */
    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    /* 团购标志 */
    if ($flow_type == CART_GROUP_BUY_GOODS)
    {
        $smarty->assign('is_group_buy', 1);
    }

    $order_id=intval(trim($_GET['order_id']));

    //判断该订单是否真的支付成功;
    $order_status=$db->getOne("SELECT pay_status FROM ".$ecs->table('order_info')." WHERE order_id='$order_id'");

    if($order_status != 2 && $order_status != 3){
        ecs_header("Location: index.php\n");
        exit;
    }
    /*门店id*/
    $store_id = !empty($_REQUEST['store_id']) ? intval($_REQUEST['store_id']) : 0;

    /*如果是门店商品*/
        if($store_id > 0){
            $sql = "SELECT stores_name,id FROM".$ecs->table('offline_store')." WHERE id = '$store_id'";
            $smarty->assign('stores_info',$db->getRow($sql));
        }
    //(单一);
    $order = get_main_order_info($order_id);
    $order['order_amount']=$order['money_paid']+$order['surplus'];

    //(多子订单);
    $sql = "SELECT COUNT(order_id) AS child_num,money_paid,surplus FROM " . $ecs->table('order_info') . " WHERE main_order_id ='$order_id'";
    $child_order = $db->getOne($sql);
    if ($child_order > 1) {
        $sql = "SELECT money_paid,consignee,mobile,surplus,order_sn,order_id,shipping_name,shipping_fee,order_amount FROM " . $ecs->table('order_info') . " WHERE main_order_id ='$order_id'";
        $child_order_info = $db->getAll($sql);
        foreach($child_order_info as $k=>$v){
            $child_order_info[$k]['order_amount']=price_format($v['money_paid']+$v['surplus']);
        }
        $smarty->assign('child_order_info', $child_order_info);//子订单信息;
    }
    if(defined('THEME_EXTENSION')){
        $region = array(
            'province' => $order['province'],
            'city' => $order['city'],
            'district' => $order['district'],
            'street' => $order['street']
        );
        $address_info = get_area_region_info($region);
        $smarty->assign('address_info', $address_info);//收货地址
        $region_id = get_province_id_warehouse($address_info['province']);
        $goods_buy_list = get_order_goods_buy_list($region_id, $area_id);
        $smarty->assign('goods_buy_list', $goods_buy_list);
    }

    $smarty->assign('child_order', $child_order);//子订单个数;
    $smarty->assign('order', $order);//主订单信息;
    $smarty->assign('is_zc_order', $order['is_zc_order']);//是否为众筹订单;
    $smarty->assign('pay_success',true);

/*  @author-bylu 支付成功页 end  */
}
else if ('calculate_info'  == $_REQUEST['step']) {
    require_once ROOT_PATH . 'includes/cls_json.php';
    $json = new JSON();
    $result = array('error' => 0, 'message' => '', 'content' => '');
    $rec_ids = (empty($_REQUEST['rec_ids']) ? array() : $_REQUEST['rec_ids']);
    $rec_ids = implode(',', $rec_ids);
    $cart_goods_activity = get_cart_activity($rec_ids);

    $err = [];
    foreach ($cart_goods_activity as $cart) {
        $extension_code = $cart['extension_code'];
        $extension_id = $cart['extension_id'];
        $total_number = $cart['total_number'];

        $rec_ids = explode(',',$cart['rec_ids']);

        if (in_array($extension_code, ['group_buy', 'presale'])) {
            if ($extension_code == 'group_buy') {
                $act = group_buy_info($extension_id, $total_number);
                $act_type = '拼单活动';
            } elseif ($extension_code == 'presale') {
                $act = presale_info($extension_id, $total_number);
                $act_type = '预定活动';
            }


            if ($act['status'] != GBS_UNDER_WAY)
            {

                foreach ($rec_ids as $k => $rec_id) {
                    $err[$rec_id] = sprintf($GLOBALS['_LANG']['gb_error_status_3']);
                }
                continue;
            }

            //最小起批量
            if ($act['moq'] && $total_number < $act['moq']) {
                foreach ($rec_ids as $k => $rec_id) {
                    $err[$rec_id] = sprintf($GLOBALS['_LANG']['moq_error'], "", $act['moq']);
                }
                continue;
            }

            $order_goods = get_for_purchasing_goods_new($act['act_id'], $extension_code);
            $restrict_amount = $total_number + $order_goods['goods_number'];

            /* 查询：判断数量是否足够 */
            if($act['restrict_amount'] > 0 && $restrict_amount > $act['restrict_amount'])
            {
                exit;
                foreach ($rec_ids as $k => $rec_id) {
                    $err[$rec_id] = sprintf($GLOBALS['_LANG']['gb_error_restrict_amount1'], "");
                }
                continue;
            }
        } elseif ($extension_code == 'sample') {
            //
            $sample = sample_info($extension_id, $total_number);

            $act_type = '样品';

            if ($sample['moq'] && $total_number < $sample['moq']) {
                foreach ($rec_ids as $k => $rec_id) {
                    $err[$rec_id] = sprintf($GLOBALS['_LANG']['moq_error'], "", $act['moq']);
                }
                continue;
            }

            $goods_id = $sample['goods_id'];
            $properties = get_goods_properties($goods_id);  // 获得商品的规格和属性
            $specscount = count($properties['spe']);

            $flag = false;
            foreach ($rec_ids as $k => $rec_id) {
                $_cart = get_table_date('cart', 'rec_id = '.$rec_id, array('*'), 0);

                if ($_cart['goods_number'] > 1) {
                    $err[$rec_id] = '样品每个规格最多只能选择一件';
                    continue;
                }

                if ($specscount > 0) {
                    $specs = $_cart['goods_attr_id'];

                    $innerJoin = 'inner join '.$ecs->table('order_goods') . " AS og on oi.order_id = og.order_id ";
                    //是否已经购买过
                    $sql = "SELECT count(*) " .
                        "FROM " . $ecs->table('order_info') . " AS oi " . $innerJoin.
                        "WHERE og.goods_id = ".$goods_id . " AND oi.extension_code = '".$_cart['extension_code']."' AND oi.extension_id = ".$_cart['extension_id'] .' AND order_status != 2 AND user_id = '.$_SESSION['user_id'] ." AND goods_attr_id = '$specs' ";
                    $count = $db->getOne($sql);

                    $goods_attr = $_cart['goods_attr'];

                    if ($count > 0) {
                        $err[$rec_id] = '样品商品已经购买过了,不能再次购买';
                        continue;
                    }
                } else {
                    $innerJoin = 'inner join '.$ecs->table('order_goods') . " AS og on oi.order_id = og.order_id ";

                    //是否已经购买过
                    $sql = "SELECT count(*) " .
                        "FROM " . $ecs->table('order_info') . " AS oi " . $innerJoin.
                        "WHERE og.goods_id = ".$goods_id . " AND oi.extension_code = '".$_cart['extension_code']."' AND oi.extension_id = ".$_cart['extension_id'] .' AND order_status != 2 AND user_id = '.$_SESSION['user_id'];
                    $count = $db->getOne($sql);
                    if ($count > 0) {
                        $err[$rec_id] = '样品商品已经购买过了,不能再次购买';
                        continue;
                    }
                }
            }
        } else {
            //批发
            $wholesale = wholesale_info($extension_id, $total_number);

            if ($wholesale['moq'] && $total_number < $wholesale['moq']) {
                foreach ($rec_ids as $k => $rec_id) {
                    $err[$rec_id] = $GLOBALS['_LANG']['dont_match_min_num'];
                }
                continue;
            }

            //库存
            foreach ($rec_ids as $k => $rec_id) {
                $cart_info = get_table_date('cart', 'rec_id=\'' . $rec_id . '\'', array('goods_number', 'goods_id', 'goods_attr_id'));
                if (empty($cart_info['goods_attr_id']))
                {
                    $goods_number = get_table_date('wholesale', 'goods_id=\'' . $cart_info['goods_id'] . '\'', array('goods_number'), 2);
                }
                else
                {
                    $set = get_find_in_set(explode(',', $cart_info['goods_attr_id']));
                    $goods_number = get_table_date('wholesale_products', 'goods_id=\'' . $cart_info['goods_id'] . '\' ' . $set, array('product_number'), 2);
                }

                if ($goods_number < $cart_info['goods_number']) {
                    $err[$rec_id] =$GLOBALS['_LANG']['error_goods_lacking'];
                }
            }
        }


    }
    if ($err) {
        $result['error'] = 1;
        $result['rec_ids'] = $err;
    }
    exit($json->encode($result));
}
else if ('ajax_update_cart2'  == $_REQUEST['step']) {
    require_once ROOT_PATH . 'includes/cls_json.php';
    $json = new JSON();
    $result = array('error' => 0, 'message' => '', 'content' => '');

    $rec_ids = (empty($_REQUEST['rec_ids']) ? array() : $_REQUEST['rec_ids']);
    $rec_ids = implode(',', $rec_ids);
    $cart_goods = cart_goods2(0, $rec_ids);
    $goods_list = array();

    foreach ($cart_goods as $key => $val )
    {
        foreach ($val['goods_list'] as $k => $g )
        {
            $smarty->assign('goods', $g);
            $g['volume_price_lbi'] = $smarty->fetch('library/cart_volume_price.lbi');
            $goods_list[$g['goods_id']] = $g;
        }
    }
    $result['goods_list'] = $goods_list;
    $cart_info = cart_info(0, $rec_ids);
    $result['cart_info'] = $cart_info;
    exit($json->encode($result));

} else if ('update_rec_num' == $_REQUEST['step']) {
    require_once ROOT_PATH . 'includes/cls_json.php';
    $json = new JSON();
    $result = array('error' => 0, 'message' => '', 'content' => '');

    if (isset($_REQUEST['rec_id']) && isset($_REQUEST['goods_number'])) {
        $rec_id = (empty($_REQUEST['rec_id']) ? 0 : intval($_REQUEST['rec_id']));
        $goods_number = (empty($_REQUEST['goods_number']) ? 0 : intval($_REQUEST['goods_number']));

        $cart = get_table_date('cart', 'rec_id = '.$rec_id, array('extension_code', 'extension_id', 'goods_number'), 0);
        $old_goods_number = $cart['goods_number'];

        if ('group_buy' == $cart['extension_code']) {
            $activity = group_buy_info($cart['extension_id']);
        } elseif('presale' == $cart['extension_code']) {
            $activity = presale_info($cart['extension_id']);
        } elseif('sample' == $cart['extension_code']) {
            $activity = sample_info($cart['extension_id']);
            if ($goods_number > 1) {
                $result['error'] = 1;
                $result['message'] = "样品每个规格最多只能选择一件";
                $goods_number = $old_goods_number;
                $result['goods_number'] = $old_goods_number;
            }
        } elseif ('wholesale' == $cart['extension_code']) {
            $activity = wholesale_info($cart['extension_id']);
        }

        $where = ' extension_code = "'.$cart['extension_code'] . '" AND '. ' extension_id = '.$cart['extension_id'];
        $sql = ' SELECT extension_code, extension_id, SUM(c.goods_number) total_number FROM ' . $GLOBALS['ecs']->table('cart') . ' AS c WHERE ' . $where . ' ';
        $row = $GLOBALS['db']->getRow($sql);
        $total_number = $row['total_number'];


        //限购
        $restrict_amount = $total_number - $old_goods_number + $goods_number + $activity['valid_goods'];

        /* 查询：判断数量是否足够 */
        if($activity['restrict_amount'] > 0 && $restrict_amount > $activity['restrict_amount'])
        {
            $result['error'] = 1;
            $result['message'] = "对不起，您购买的商品数量已达到限购数量: ".($activity['restrict_amount'] - $order_goods['valid_goods']) ."件";
            $goods_number = $old_goods_number;
            $result['goods_number'] = $old_goods_number;
        }

        $sql = ' UPDATE ' . $GLOBALS['ecs']->table('cart') . ' SET goods_number = \'' . $goods_number . '\' WHERE rec_id = \'' . $rec_id . '\' ';
        $GLOBALS['db']->query($sql);

        exit($json->encode($result));
    }
}
else
{

    priv();
    /* 标记购物流程为普通商品 */
    $_SESSION['flow_type'] = CART_GENERAL_GOODS;

    /* 如果是一步购物，跳到结算中心 */
    if ($_CFG['one_step_buy'] == '1')
    {
        ecs_header("Location: flow.php?step=checkout\n");
        exit;
    }

    $smarty->assign('area_id',                $area_id); //省下级市
    $smarty->assign('flow_region',                $_COOKIE['flow_region']); //省下级市


    /* 取得商品列表，计算合计 */
    $goods_data = get_cart_goods2('', 1, $_COOKIE['flow_region'], $area_id);

    $smarty->assign('goods_data', $goods_data);

    //进货单的描述的格式化
    $smarty->assign('shopping_money',         sprintf($_LANG['shopping_money'], $cart_goods['total']['goods_price']));
    $smarty->assign('market_price_desc',      sprintf($_LANG['than_market_price'],
    $cart_goods['total']['market_price'], $cart_goods['total']['saving'], $cart_goods['total']['save_rate']));

    /* 计算折扣 */
    $discount = compute_discount();
    $smarty->assign('discount', $discount['discount']);
    $favour_name = empty($discount['name']) ? '' : join(',', $discount['name']);
    $smarty->assign('your_discount', sprintf($_LANG['your_discount'], $favour_name, price_format($discount['discount'])));

    /* 增加是否在进货单里显示商品图 */
    $smarty->assign('show_goods_thumb', $GLOBALS['_CFG']['show_goods_in_cart']);

    /* 增加是否在进货单里显示商品属性 */
    $smarty->assign('show_goods_attribute', $GLOBALS['_CFG']['show_attr_in_cart']);

    /* 进货单中商品配件列表 */
    //取得进货单中基本件ID
    $sql = "SELECT goods_id " .
            "FROM " . $GLOBALS['ecs']->table('cart') .
            " WHERE " . $sess_id .
            "AND rec_type = '" . CART_GENERAL_GOODS . "' " .
            "AND is_gift = 0 " .
            "AND extension_code <> 'package_buy' " .
            "AND parent_id = 0 ";
    $parent_list = $GLOBALS['db']->getCol($sql);

    $fittings_list = get_goods_fittings($parent_list);

    $smarty->assign('fittings_list', $fittings_list);
    $guess_goods = get_guess_goods($_SESSION['usre_id'], 1, 1, 18,$region_id, $area_id); //猜你喜欢
    $best_goods = get_recommend_goods('best', '', $region_id, $area_id);    // 推荐商品
    $smarty->assign('guess_goods', $guess_goods);
    $smarty->assign('guessGoods_count', count($guess_goods));
    $smarty->assign('best_goods',      $best_goods);
    $smarty->assign('bestGoods_count',   count($best_goods));

    $smarty->assign('province_row',  get_region_info($province_id));
    $smarty->assign('city_row',  get_region_info($city_id));
    $smarty->assign('district_row',  get_region_info($district_id));

    $province_list = get_warehouse_province();
    $city_list = get_region_city_county($province_id);
    $district_list = get_region_city_county($city_id);

    foreach ($province_list as $k => $v) {
        $province_list[$k]['choosable'] = true;
    }
    foreach ($city_list as $k => $v) {
        $city_list[$k]['choosable'] = true;
    }
    foreach ($district_list as $k => $v) {
        $district_list[$k]['choosable'] = true;
    }

    $smarty->assign('province_list',                $province_list); //省、直辖市
    $smarty->assign('city_list',                $city_list); //省下级市
    $smarty->assign('district_list',                $district_list);//市下级县
}

$history_goods = get_history_goods(0, $region_id, $area_id);
$smarty->assign('history_goods', $history_goods);
$smarty->assign('historyGoods_count', count($history_goods));

$cart_info = insert_cart_info(1);
$cart_info['rec_count'] = $cart_info['number'];
$smarty->assign('cart_info', $cart_info);
$smarty->assign('currency_format', $_CFG['currency_format']);
$smarty->assign('integral_scale',  price_format($_CFG['integral_scale']));
$smarty->assign('step',            $_REQUEST['step']);
assign_dynamic('shopping_flow');


$smarty->display('flow2.dwt');

/* ajax修改在线支付的支付方式(如:支付宝,京东钱包) by lu */
if(@$_REQUEST['act']=='onlinepay_edit'){
    $sql="SELECT * FROM " . $ecs->table('payment') . " WHERE pay_code='" .$_GET['onlinepay_type']. "'";
    $res=$db->getRow($sql);
    $sql="UPDATE " .$ecs->table('order_info'). " set pay_id='" .$res['pay_id']. "',pay_name='" .$res['pay_name']. "' WHERE order_sn = '" .$_GET['order_sn']. "'";
    $db->query($sql);

}
/*------------------------------------------------------ */
//-- PRIVATE FUNCTION
/*------------------------------------------------------ */

/**
 * 获得用户的可用积分
 *
 * @access  private
 * @return  integral
 */
function flow_available_points($cart_value, $warehouse_id = 0, $area_id = 0) {
    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $c_sess = " c.user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $c_sess = " c.session_id = '" . real_cart_mac_ip() . "' ";
    }
    //ecmoban模板堂 --zhuo end

    /* 取得可用积分  by  kong */
    $where = "";
    if (!empty($cart_value)) {
        $where = " AND c.rec_id " . db_create_in($cart_value);
    }

    $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg ON g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag ON g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

    $sql = "SELECT SUM(IF(g.model_price < 1, g.integral, IF(g.model_price < 2, wg.pay_integral, wag.pay_integral)) * c.goods_number ) " .
            "FROM " . $GLOBALS['ecs']->table('cart') . " AS c " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON c.goods_id = g.goods_id " .
            $leftJoin .
            "WHERE IF(g.model_price < 1, g.integral, IF(g.model_price < 2, wg.pay_integral, wag.pay_integral)) > 0 AND " . $c_sess . " AND c.is_gift = 0 $where" .
            "AND c.rec_type = '" . CART_GENERAL_GOODS . "'";

    $val = intval($GLOBALS['db']->getOne($sql));

    return integral_of_value($val);
}

/**
 * 更新进货单中的商品数量
 *
 * @access  public
 * @param   array   $arr
 * @return  void
 */
function flow_update_cart($arr)
{
	//ecmoban模板堂 --zhuo start
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
	//ecmoban模板堂 --zhuo end

    /* 处理 */
    foreach ($arr AS $key => $val)
    {
        $val = intval(make_semiangle($val));
        if ($val <= 0 || !is_numeric($key))
        {
            continue;
        }

        //查询：
        $sql = "SELECT `goods_id`, `goods_attr_id`, `product_id`, `extension_code` FROM" .$GLOBALS['ecs']->table('cart').
               " WHERE rec_id='$key' AND " . $sess_id;
        $goods = $GLOBALS['db']->getRow($sql);

        $sql = "SELECT g.goods_name, g.goods_number ".
                "FROM " .$GLOBALS['ecs']->table('goods'). " AS g, ".
                    $GLOBALS['ecs']->table('cart'). " AS c ".
                "WHERE g.goods_id = c.goods_id AND c.rec_id = '$key'";
        $row = $GLOBALS['db']->getRow($sql);

        //ecmoban模板堂 --zhuo start 限购
        $nowTime = gmtime();
        $xiangouInfo = get_purchasing_goods_info($goods['goods_id']);
        $start_date = $xiangouInfo['xiangou_start_date'];
        $end_date = $xiangouInfo['xiangou_end_date'];

        if ($xiangouInfo['is_xiangou'] == 1 && $nowTime > $start_date && $nowTime < $end_date) {
            $user_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
            $orderGoods = get_for_purchasing_goods($start_date, $end_date, $goods['goods_id'], $user_id);

            if ($orderGoods['goods_number'] >= $xiangouInfo['xiangou_num']) {
                $max_num = $xiangouInfo['xiangou_num'] - $orderGoods['goods_number'];
                $result['message'] = sprintf($GLOBALS['_LANG']['purchase_Prompt'],$row['goods_name']);
                //更新进货单中的商品数量
                $sql = "UPDATE " . $GLOBALS['ecs']->table('cart') .
                        " SET goods_number = 0 WHERE rec_id='$key'";
                $GLOBALS['db']->query($sql);

                show_message($result['message'], $_LANG['back_to_cart'], 'flow.php');
                exit;
            } else {
                if ($xiangouInfo['xiangou_num'] > 0) {
                    if ($xiangouInfo['is_xiangou'] == 1 && $orderGoods['goods_number'] + $val > $xiangouInfo['xiangou_num']) {
                        $max_num = $xiangouInfo['xiangou_num'] - $orderGoods['goods_number'];
                        $result['message'] = sprintf($GLOBALS['_LANG']['purchasing_prompt'],$row['goods_name']);

                        //更新进货单中的商品数量
                        $cart_Num = $xiangouInfo['xiangou_num'] - $orderGoods['goods_number'];
                        $sql = "UPDATE " . $GLOBALS['ecs']->table('cart') .
                                " SET goods_number = '$cart_Num' WHERE rec_id='$key'";
                        $GLOBALS['db']->query($sql);

                        show_message($result['message'], $_LANG['back_to_cart'], 'flow.php');
                        exit;
                    }
                }
            }
        }
        //ecmoban模板堂 --zhuo end 限购

        //查询：系统启用了库存，检查输入的商品数量是否有效
        if (intval($GLOBALS['_CFG']['use_storage']) > 0 && $goods['extension_code'] != 'package_buy')
        {
            if ($row['goods_number'] < $val)
            {
                show_message(sprintf($GLOBALS['_LANG']['stock_insufficiency'], $row['goods_name'],
                $row['goods_number'], $row['goods_number']));
                exit;
            }
            /* 是货品 */
            $goods['product_id'] = trim($goods['product_id']);
            if (!empty($goods['product_id']))
            {
                $sql = "SELECT product_number FROM " .$GLOBALS['ecs']->table('products'). " WHERE goods_id = '" . $goods['goods_id'] . "' AND product_id = '" . $goods['product_id'] . "' LIMIT 1";
                $product_number = $GLOBALS['db']->getOne($sql);

                if ($product_number < $val)
                {
                    show_message(sprintf($GLOBALS['_LANG']['stock_insufficiency'], $row['goods_name'], $product_number, $product_number));
                    exit;
                }
            }
        }
        elseif (intval($GLOBALS['_CFG']['use_storage']) > 0 && $goods['extension_code'] == 'package_buy')
        {
            if (judge_package_stock($goods['goods_id'], $val))
            {
                show_message($GLOBALS['_LANG']['package_stock_insufficiency']);
                exit;
            }
        }

        /* 查询：检查该项是否为基本件 以及是否存在配件 */
        /* 此处配件是指添加商品时附加的并且是设置了优惠价格的配件 此类配件都有parent_id goods_number为1 */
        $sql = "SELECT b.goods_number, b.rec_id
                FROM " .$GLOBALS['ecs']->table('cart') . " a, " .$GLOBALS['ecs']->table('cart') . " b
                WHERE a.rec_id = '$key'
                AND " .$a_sess. "
                AND a.extension_code <> 'package_buy'
                AND b.parent_id = a.goods_id
                AND " . $b_sess;

        $offers_accessories_res = $GLOBALS['db']->query($sql);

        //订货数量大于0
        if ($val > 0)
        {
            /* 判断是否为超出数量的优惠价格的配件 删除*/
            $row_num = 1;
            while ($offers_accessories_row = $GLOBALS['db']->fetchRow($offers_accessories_res))
            {
                if ($row_num > $val)
                {
                    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart') .
                            " WHERE " . $sess_id ." AND rec_id = '" . $offers_accessories_row['rec_id'] ."' AND group_id='' LIMIT 1"; //by mike
                    $GLOBALS['db']->query($sql);
                }

                $row_num ++;
            }

            /* 处理超值礼包 */
            if ($goods['extension_code'] == 'package_buy')
            {
                //更新进货单中的商品数量
                $sql = "UPDATE " .$GLOBALS['ecs']->table('cart').
                        " SET goods_number = '$val' WHERE rec_id='$key' AND " . $sess_id . " AND group_id=''"; //by mike
            }
            /* 处理普通商品或非优惠的配件 */
            else
            {
                $attr_id    = empty($goods['goods_attr_id']) ? array() : explode(',', $goods['goods_attr_id']);
                $goods_price = get_final_price($goods['goods_id'], $val, true, $attr_id);

                //更新进货单中的商品数量
                $sql = "UPDATE " .$GLOBALS['ecs']->table('cart').
                        " SET goods_number = '$val', goods_price = '$goods_price' WHERE rec_id='$key' AND "  . $sess_id . " AND group_id=''"; //by mike
            }
        }
        //订货数量等于0
        else
        {
            /* 如果是基本件并且有优惠价格的配件则删除优惠价格的配件 */
            while ($offers_accessories_row = $GLOBALS['db']->fetchRow($offers_accessories_res))
            {
                $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart') .
                        " WHERE " . $sess_id . " AND rec_id = '" . $offers_accessories_row['rec_id'] ."' AND group_id='' LIMIT 1"; //by mike
                $GLOBALS['db']->query($sql);
            }

            $sql = "DELETE FROM " .$GLOBALS['ecs']->table('cart').
                " WHERE rec_id='$key' AND " . $sess_id . " AND group_id=''"; //by mike
        }

        $GLOBALS['db']->query($sql);
    }

    /* 删除所有赠品 */
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart') . " WHERE ". $sess_id ." AND is_gift <> 0";
    $GLOBALS['db']->query($sql);
}


/**
 * 检查订单中商品库存
 *
 * @access  public
 * @param   array   $arr
 *
 * @return  void
 */
function flow_cart_stock($arr, $store_id = 0, $warehouse_id = 0, $area_id = 0)
{
    //ecmoban模板堂 --zhuo start
    if(!empty($_SESSION['user_id'])){
            $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
    }else{
            $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
    }
    //ecmoban模板堂 --zhuo end

    foreach ($arr AS $key => $val)
    {
        $val = intval(make_semiangle($val));
        if ($val <= 0 || !is_numeric($key))
        {
            continue;
        }

        $sql = "SELECT `goods_id`, `goods_attr_id`, `extension_code`, `warehouse_id` FROM" .$GLOBALS['ecs']->table('cart').
               " WHERE rec_id='$key' AND ". $sess_id;
        $goods = $GLOBALS['db']->getRow($sql);

        $sql = "SELECT g.goods_name, g.goods_number, g.goods_id, c.product_id, g.model_attr,c.goods_attr_id ".
                "FROM " .$GLOBALS['ecs']->table('goods'). " AS g, ".
                    $GLOBALS['ecs']->table('cart'). " AS c ".
                "WHERE g.goods_id = c.goods_id AND c.rec_id = '$key'";
        $row = $GLOBALS['db']->getRow($sql);

        //ecmoban模板堂 --zhuo start
        if ($store_id > 0) {
            $sql = "SELECT  goods_number FROM" . $GLOBALS['ecs']->table("store_goods") . " WHERE goods_id = '$goods_id' AND store_id = '$store_id'";
        } else {

            $leftJoin = " LEFT JOIN " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
            $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

            $sql = "SELECT IF(g.model_inventory < 1, g.goods_number, IF(g.model_inventory < 2, wg.region_number, wag.region_number)) AS goods_number " .
                    " FROM " . $GLOBALS['ecs']->table('goods') . " AS g " .
                    $leftJoin .
                    " WHERE g.goods_id = '" . $row['goods_id'] . "'";
        }

        $goods_number = $GLOBALS['db']->getOne($sql);

        $row['goods_number'] = $goods_number;
        //ecmoban模板堂 --zhuo end

        //系统启用了库存，检查输入的商品数量是否有效
        if (intval($GLOBALS['_CFG']['use_storage']) > 0 && $goods['extension_code'] != 'package_buy'&& $store_id == 0)
        {
            //ecmoban模板堂 --zhuo start
            /* 是货品 */
            $row['product_id'] = trim($row['product_id']);
            if (!empty($row['product_id']))
            {
                //ecmoban模板堂 --zhuo start
                if($row['model_attr'] == 1){
                        $table_products = "products_warehouse";
                }elseif($row['model_attr'] == 2){
                        $table_products = "products_area";
                }else{
                        $table_products = "products";
                }
                //ecmoban模板堂 --zhuo end

                $sql = "SELECT product_number FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '" .$row['goods_id']. "' and product_id = '" . $row['product_id'] . "'";
                $product_number = $GLOBALS['db']->getOne($sql);

                if ($product_number < $val)
                {
                    show_message(sprintf($GLOBALS['_LANG']['stock_insufficiency'], $row['goods_name'],
                    $product_number, $product_number));
                    exit;
                }
            }else{
                if ($row['goods_number'] < $val)
                {
                    show_message(sprintf($GLOBALS['_LANG']['stock_insufficiency'], $row['goods_name'],
                    $row['goods_number'], $row['goods_number']));
                    exit;
                }
            }
            //ecmoban模板堂 --zhuo end
        }elseif(intval($GLOBALS['_CFG']['use_storage']) >0 && $store_id > 0){
                $sql = "SELECT goods_number,ru_id FROM".$GLOBALS['ecs']->table("store_goods")." WHERE store_id = '$store_id' AND goods_id = '".$row['goods_id']."' ";
                $goodsInfo = $GLOBALS['db']->getRow($sql);

                $products = get_warehouse_id_attr_number($row['goods_id'], $row['goods_attr_id'], $goodsInfo['ru_id'], 0, 0,'',$store_id);//获取属性库存
                $attr_number = $products['product_number'];
                if($row['goods_attr_id']){ //当商品没有属性库存时
                    $row['goods_number'] = $attr_number;
                }else{
                    $row['goods_number'] = $goodsInfo['goods_number'];
                }
                if ($row['goods_number'] < $val)
                {
                    show_message(sprintf($GLOBALS['_LANG']['stock_store_shortage'], $row['goods_name'],
                    $row['goods_number'], $row['goods_number']));
                    exit;
                }
        }
        elseif (intval($GLOBALS['_CFG']['use_storage']) > 0 && $goods['extension_code'] == 'package_buy')
        {
            if (judge_package_stock($goods['goods_id'], $val))
            {
                show_message($GLOBALS['_LANG']['package_stock_insufficiency']);
                exit;
            }
        }
    }

}

/**
 * 比较优惠活动的函数，用于排序（把可用的排在前面）
 * @param   array   $a      优惠活动a
 * @param   array   $b      优惠活动b
 * @return  int     相等返回0，小于返回-1，大于返回1
 */
function cmp_favourable($a, $b)
{
    if ($a['available'] == $b['available'])
    {
        if ($a['sort_order'] == $b['sort_order'])
        {
            return 0;
        }
        else
        {
            return $a['sort_order'] < $b['sort_order'] ? -1 : 1;
        }
    }
    else
    {
        return $a['available'] ? -1 : 1;
    }
}

/**
 * 添加优惠活动（赠品）到进货单
 * @param   int     $act_id     优惠活动id
 * @param   int     $id         赠品id
 * @param   float   $price      赠品价格
 */
function add_gift_to_cart($act_id, $id, $price, $ru_id)
{
	//ecmoban模板堂 --zhuo start
	if(!empty($_SESSION['user_id'])){
		$sess = "";
	}else{
		$sess = real_cart_mac_ip();
	}
	//ecmoban模板堂 --zhuo end
    $sql = "INSERT INTO " . $GLOBALS['ecs']->table('cart') . " (" .
                "user_id, session_id, goods_id, goods_sn, goods_name, market_price, goods_price, ".
                "goods_number, is_real, extension_code, parent_id, is_gift, rec_type, ru_id ) ".
            "SELECT '$_SESSION[user_id]', '" . $sess . "', goods_id, goods_sn, goods_name, market_price, ".
                "'$price', 1, is_real, extension_code, 0, '$act_id', '" . CART_GENERAL_GOODS . "', $ru_id " .
            "FROM " . $GLOBALS['ecs']->table('goods') .
            " WHERE goods_id = '$id'";
    $GLOBALS['db']->query($sql);
}

/**
 * 添加优惠活动（非赠品）到进货单
 * @param   int     $act_id     优惠活动id
 * @param   string  $act_name   优惠活动name
 * @param   float   $amount     优惠金额
 */
function add_favourable_to_cart($act_id, $act_name, $amount) {
    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $sess = "";
    } else {
        $sess = real_cart_mac_ip();
    }
    //ecmoban模板堂 --zhuo end

    $sql = "INSERT INTO " . $GLOBALS['ecs']->table('cart') . "(" .
            "user_id, session_id, goods_id, goods_sn, goods_name, market_price, goods_price, " .
            "goods_number, is_real, extension_code, parent_id, is_gift, rec_type ) " .
            "VALUES('$_SESSION[user_id]', '" . $sess . "', 0, '', '$act_name', 0, " .
            "'" . (-1) * $amount . "', 1, 0, '', 0, '$act_id', '" . CART_GENERAL_GOODS . "')";
    $GLOBALS['db']->query($sql);
}

//获取进货单信息
function get_cart_value() {
    if (!empty($_SESSION['user_id'])) {
        $c_sess = " c.user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        show_message('请登录', '', '', 'warning');
    }

    $sql = "SELECT c.rec_id FROM " . $GLOBALS['ecs']->table('cart') .
        " AS c LEFT JOIN " . $GLOBALS['ecs']->table('goods') .
        " AS g ON c.goods_id = g.goods_id WHERE " . $c_sess . "order by c.rec_id asc";


    $goods_list = $GLOBALS['db']->getAll($sql);

    $rec_id = '';
    if ($goods_list) {
        foreach ($goods_list as $key => $row) {
            $rec_id .= $row['rec_id'] . ',';
        }

        $rec_id = substr($rec_id, 0, -1);
    }

    return $rec_id;

}

/* 获取进货单中同一活动下的商品和赠品 -qin
 *
 * $favourable_id int 优惠活动id
 * $act_sel_id string 活动中选中的cart id
 * $ru_id 商家ID
 */
function cart_add_favourable_box($favourable_id, $act_sel_id = array(), $ru_id = 0) {
    $fav_res = favourable_list($_SESSION['user_rank'], -1, $favourable_id, $act_sel_id);
    $favourable_activity = $fav_res[0];

    $cart_goods = get_cart_goods('', 1);
    $merchant_goods = $cart_goods['goods_list'];

    $favourable_box = array();

    if ($cart_goods['total']['goods_price']) {
        $favourable_box['goods_amount'] = $cart_goods['total']['goods_price'];
    }

    $list_array = array();
    foreach ($merchant_goods as $key => $row) { // 第一层 遍历商家
        $user_cart_goods = $row['goods_list'];
        if ($row['ru_id'] == $ru_id) { //判断是否商家活动
            foreach ($user_cart_goods as $key1 => $row1) { // 第二层 遍历进货单中商家的商品
                $row1['original_price'] = $row1['goods_price'] * $row1['goods_number'];
                if (!empty($act_sel_id)) { // 用来判断同一个优惠活动前面是否全部不选
                    $row1['sel_checked'] = strstr(',' . $act_sel_id['act_sel_id'] . ',', ',' . $row1['rec_id'] . ',') ? 1 : 0; // 选中为1
                }
                // 活动-全部商品
                if ($favourable_activity['act_range'] == 0 && $row1['extension_code'] != 'package_buy') {
                    if ($row1['is_gift'] == FAR_ALL) { // 活动商品

                        if ($row1) {
                            $favourable_box['act_id'] = $favourable_activity['act_id'];
                            $favourable_box['act_name'] = $favourable_activity['act_name'];
                            $favourable_box['act_type'] = $favourable_activity['act_type'];
                            // 活动类型
                            switch ($favourable_activity['act_type']) {
                                case 0:
                                    $favourable_box['act_type_txt'] = $GLOBALS['_LANG']['With_a_gift'];
                                    $favourable_box['act_type_ext_format'] = intval($favourable_activity['act_type_ext']); // 可领取总件数
                                    break;
                                case 1:
                                    $favourable_box['act_type_txt'] = $GLOBALS['_LANG']['Full_reduction'];
                                    $favourable_box['act_type_ext_format'] = number_format($favourable_activity['act_type_ext'], 2); // 满减金额
                                    break;
                                case 2:
                                    $favourable_box['act_type_txt'] = $GLOBALS['_LANG']['discount'];
                                    $favourable_box['act_type_ext_format'] = floatval($favourable_activity['act_type_ext'] / 10); // 折扣百分比
                                    break;

                                default:
                                    break;
                            }
                            $favourable_box['min_amount'] = $favourable_activity['min_amount'];
                            $favourable_box['act_type_ext'] = intval($favourable_activity['act_type_ext']); // 可领取总件数
                            $favourable_box['cart_fav_amount'] = cart_favourable_amount($favourable_activity, $act_sel_id);
                            $favourable_box['available'] = favourable_available($favourable_activity, $act_sel_id); // 进货单满足活动最低金额
                            // 进货单中已选活动赠品数量
                            $cart_favourable = cart_favourable($row['ru_id']);
                            $favourable_box['cart_favourable_gift_num'] = empty($cart_favourable[$favourable_activity['act_id']]) ? 0 : intval($cart_favourable[$favourable_activity['act_id']]);
                            $favourable_box['favourable_used'] = favourable_used($favourable_activity, $cart_favourable);
                            $favourable_box['left_gift_num'] = intval($favourable_activity['act_type_ext']) - (empty($cart_favourable[$favourable_activity['act_id']]) ? 0 : intval($cart_favourable[$favourable_activity['act_id']]));

                            // 活动赠品
                            if ($favourable_activity['gift']) {
                                $favourable_box['act_gift_list'] = $favourable_activity['gift'];
                            }

                            // new_list->活动id->act_goods_list
                            $favourable_box['act_goods_list'][$row1['rec_id']] = $row1;
                            unset($row1);
                        }
                    } else { // 赠品
                        $favourable_box['act_cart_gift'][$row1['rec_id']] = $row1;
                    }
                    continue; // 如果活动包含全部商品，跳出循环体
                }

                // 活动-分类
                if ($favourable_activity['act_range'] == FAR_CATEGORY && $row1['extension_code'] != 'package_buy') {
                    // 优惠活动关联的 分类集合
                    $get_act_range_ext = get_act_range_ext($_SESSION['user_rank'], $row['ru_id'], 1); // 1表示优惠范围 按分类

                    $str_cat = '';
                    foreach ($get_act_range_ext as $id) {

                        /**
                         * 当前分类下的所有子分类
                         * 返回一维数组
                         */
                        $cat_keys = get_array_keys_cat(intval($id));

                        if ($cat_keys) {
                            $str_cat .= implode(",", $cat_keys);
                        }
                    }

                    if ($str_cat) {
                        $list_array = explode(",", $str_cat);
                    }

                    $list_array = !empty($list_array) ? array_merge($get_act_range_ext, $list_array) : $get_act_range_ext;
                    $id_list = arr_foreach($list_array);
                    $id_list = array_unique($id_list);
                    $cat_id = $row1['cat_id']; //进货单商品所属分类ID

                    // 判断商品或赠品 是否属于本优惠活动
                    if ((in_array(trim($cat_id), $id_list) && $row1['is_gift'] == 0) || ($row1['is_gift'] == $favourable_activity['act_id'])) {

                        if ($row1) {
                            //优惠活动关联分类集合
                            $fav_act_range_ext = !empty($favourable_activity['act_range_ext']) ? explode(',', $favourable_activity['act_range_ext']) : array();

                            // 此 优惠活动所有分类
                            foreach ($fav_act_range_ext as $id) {
                                /**
                                 * 当前分类下的所有子分类
                                 * 返回一维数组
                                 */
                                $cat_keys = get_array_keys_cat(intval($id));
                                $fav_act_range_ext = array_merge($fav_act_range_ext, $cat_keys);
                            }

                            if ($row1['is_gift'] == 0 && in_array($cat_id, $fav_act_range_ext)) { // 活动商品
                                $favourable_box['act_id'] = $favourable_activity['act_id'];
                                $favourable_box['act_name'] = $favourable_activity['act_name'];
                                $favourable_box['act_type'] = $favourable_activity['act_type'];
                                // 活动类型
                                switch ($favourable_activity['act_type']) {
                                    case 0:
                                        $favourable_box['act_type_txt'] = $GLOBALS['_LANG']['With_a_gift'];
                                        $favourable_box['act_type_ext_format'] = intval($favourable_activity['act_type_ext']); // 可领取总件数
                                        break;
                                    case 1:
                                        $favourable_box['act_type_txt'] = $GLOBALS['_LANG']['Full_reduction'];
                                        $favourable_box['act_type_ext_format'] = number_format($favourable_activity['act_type_ext'], 2); // 满减金额
                                        break;
                                    case 2:
                                        $favourable_box['act_type_txt'] = $GLOBALS['_LANG']['discount'];
                                        $favourable_box['act_type_ext_format'] = floatval($favourable_activity['act_type_ext'] / 10); // 折扣百分比
                                        break;

                                    default:
                                        break;
                                }
                                $favourable_box['min_amount'] = $favourable_activity['min_amount'];
                                $favourable_box['act_type_ext'] = intval($favourable_activity['act_type_ext']); // 可领取总件数
                                $favourable_box['cart_fav_amount'] = cart_favourable_amount($favourable_activity, $act_sel_id);
                                $favourable_box['available'] = favourable_available($favourable_activity, $act_sel_id); // 进货单满足活动最低金额
                                // 进货单中已选活动赠品数量
                                $cart_favourable = cart_favourable($row['ru_id']);
                                $favourable_box['cart_favourable_gift_num'] = empty($cart_favourable[$favourable_activity['act_id']]) ? 0 : intval($cart_favourable[$favourable_activity['act_id']]);
                                $favourable_box['favourable_used'] = favourable_used($favourable_activity, $cart_favourable);
                                $favourable_box['left_gift_num'] = intval($favourable_activity['act_type_ext']) - (empty($cart_favourable[$favourable_activity['act_id']]) ? 0 : intval($cart_favourable[$favourable_activity['act_id']]));

                                // 活动赠品
                                if ($favourable_activity['gift']) {
                                    $favourable_box['act_gift_list'] = $favourable_activity['gift'];
                                }

                                // new_list->活动id->act_goods_list
                                $favourable_box['act_goods_list'][$row1['rec_id']] = $row1;

                                if (defined('THEME_EXTENSION')) {
                                    $favourable_box['act_goods_list_num'] = count($favourable_box['act_goods_list']);
                                }
                            }

                            if ($row1['is_gift'] == $favourable_activity['act_id']) { // 赠品
                                $favourable_box['act_cart_gift'][$row1['rec_id']] = $row1;
                            }

                            unset($row1);
                        }

                        continue;
                    }
                }

                // 活动-品牌
                if ($favourable_activity['act_range'] == FAR_BRAND && $row1['extension_code'] != 'package_buy') {
                    // 优惠活动 品牌集合
                    $get_act_range_ext = get_act_range_ext($_SESSION['user_rank'], $row['ru_id'], 2); // 2表示优惠范围 按品牌
                    $brand_id = $row1['brand_id'];

                    // 是品牌活动的商品或者赠品
                    if ((in_array(trim($brand_id), $get_act_range_ext) && $row1['is_gift'] == 0) || ($row1['is_gift'] == $favourable_activity['act_id'])) {

                        if ($row1) {
                            $act_range_ext_str = ',' . $favourable_activity['act_range_ext'] . ',';
                            $brand_id_str = ',' . $brand_id . ',';
                            if ($row1['is_gift'] == 0 && strstr($act_range_ext_str, trim($brand_id_str))) { // 活动商品
                                $favourable_box['act_id'] = $favourable_activity['act_id'];
                                $favourable_box['act_name'] = $favourable_activity['act_name'];
                                $favourable_box['act_type'] = $favourable_activity['act_type'];
                                // 活动类型
                                switch ($favourable_activity['act_type']) {
                                    case 0:
                                        $favourable_box['act_type_txt'] = $GLOBALS['_LANG']['With_a_gift'];
                                        $favourable_box['act_type_ext_format'] = intval($favourable_activity['act_type_ext']); // 可领取总件数
                                        break;
                                    case 1:
                                        $favourable_box['act_type_txt'] = $GLOBALS['_LANG']['Full_reduction'];
                                        $favourable_box['act_type_ext_format'] = number_format($favourable_activity['act_type_ext'], 2); // 满减金额
                                        break;
                                    case 2:
                                        $favourable_box['act_type_txt'] = $GLOBALS['_LANG']['discount'];
                                        $favourable_box['act_type_ext_format'] = floatval($favourable_activity['act_type_ext'] / 10); // 折扣百分比
                                        break;

                                    default:
                                        break;
                                }
                                $favourable_box['min_amount'] = $favourable_activity['min_amount'];
                                $favourable_box['act_type_ext'] = intval($favourable_activity['act_type_ext']); // 可领取总件数
                                $favourable_box['cart_fav_amount'] = cart_favourable_amount($favourable_activity, $act_sel_id);
                                $favourable_box['available'] = favourable_available($favourable_activity, $act_sel_id); // 进货单满足活动最低金额
                                // 进货单中已选活动赠品数量
                                $cart_favourable = cart_favourable();
                                $favourable_box['cart_favourable_gift_num'] = empty($cart_favourable[$favourable_activity['act_id']]) ? 0 : intval($cart_favourable[$favourable_activity['act_id']]);
                                $favourable_box['favourable_used'] = favourable_used($favourable_activity, $cart_favourable);
                                $favourable_box['left_gift_num'] = intval($favourable_activity['act_type_ext']) - (empty($cart_favourable[$favourable_activity['act_id']]) ? 0 : intval($cart_favourable[$favourable_activity['act_id']]));

                                // 活动赠品
                                if ($favourable_activity['gift']) {
                                    $favourable_box['act_gift_list'] = $favourable_activity['gift'];
                                }

                                // new_list->活动id->act_goods_list
                                $favourable_box['act_goods_list'][$row1['rec_id']] = $row1;
                            }
                            if ($row1['is_gift'] == $favourable_activity['act_id']) { // 赠品
                                $favourable_box['act_cart_gift'][$row1['rec_id']] = $row1;
                            }

                            unset($row1);
                        }

                        continue;
                    }
                }

                // 活动-部分商品
                if ($favourable_activity['act_range'] == FAR_GOODS && $row1['extension_code'] != 'package_buy') {
                    $get_act_range_ext = get_act_range_ext($_SESSION['user_rank'], $row['ru_id'], 3); // 3表示优惠范围 按商品
                    // 判断购物商品是否参加了活动  或者  该商品是赠品
                    if (in_array($row1['goods_id'], $get_act_range_ext) || ($row1['is_gift'] == $favourable_activity['act_id'])) {

                        if ($row1) {
                            $act_range_ext_str = ',' . $favourable_activity['act_range_ext'] . ','; // 优惠活动中的优惠商品
                            $goods_id_str = ',' . $row1['goods_id'] . ',';
                            // 如果是活动商品
                            if (strstr($act_range_ext_str, trim($goods_id_str)) && ($row1['is_gift'] == 0)) {
                                $favourable_box['act_id'] = $favourable_activity['act_id'];
                                $favourable_box['act_name'] = $favourable_activity['act_name'];
                                $favourable_box['act_type'] = $favourable_activity['act_type'];
                                // 活动类型
                                switch ($favourable_activity['act_type']) {
                                    case 0:
                                        $favourable_box['act_type_txt'] = $GLOBALS['_LANG']['With_a_gift'];
                                        $favourable_box['act_type_ext_format'] = intval($favourable_activity['act_type_ext']); // 可领取总件数
                                        break;
                                    case 1:
                                        $favourable_box['act_type_txt'] = $GLOBALS['_LANG']['Full_reduction'];
                                        $favourable_box['act_type_ext_format'] = number_format($favourable_activity['act_type_ext'], 2); // 满减金额
                                        break;
                                    case 2:
                                        $favourable_box['act_type_txt'] = $GLOBALS['_LANG']['discount'];
                                        $favourable_box['act_type_ext_format'] = floatval($favourable_activity['act_type_ext'] / 10); // 折扣百分比
                                        break;

                                    default:
                                        break;
                                }
                                $favourable_box['min_amount'] = $favourable_activity['min_amount'];
                                $favourable_box['act_type_ext'] = intval($favourable_activity['act_type_ext']); // 可领取总件数
                                $favourable_box['cart_fav_amount'] = cart_favourable_amount($favourable_activity, $act_sel_id);
                                $favourable_box['available'] = favourable_available($favourable_activity, $act_sel_id); // 进货单满足活动最低金额
                                // 进货单中已选活动赠品数量
                                $cart_favourable = cart_favourable($row['ru_id']);
                                $favourable_box['cart_favourable_gift_num'] = empty($cart_favourable[$favourable_activity['act_id']]) ? 0 : intval($cart_favourable[$favourable_activity['act_id']]);
                                $favourable_box['favourable_used'] = favourable_used($favourable_box, $cart_favourable);
                                $favourable_box['left_gift_num'] = intval($favourable_activity['act_type_ext']) - (empty($cart_favourable[$favourable_activity['act_id']]) ? 0 : intval($cart_favourable[$favourable_activity['act_id']]));

                                // 活动赠品
                                if ($favourable_activity['gift']) {
                                    $favourable_box['act_gift_list'] = $favourable_activity['gift'];
                                }

                                // new_list->活动id->act_goods_list
                                $favourable_box['act_goods_list'][$row1['rec_id']] = $row1;
                            }
                            // 如果是赠品
                            if ($row1['is_gift'] == $favourable_activity['act_id']) {
                                $favourable_box['act_cart_gift'][$row1['rec_id']] = $row1;
                            }

                            unset($row1);
                        }
                    }
                } else {
                    // new_list->活动id->act_goods_list | 活动id的数组位置为0，表示次数组下面为没有参加活动的商品
                    $favourable_box[$row1['rec_id']] = $row1;
                }
            }
        }
    }

    return $favourable_box;
}

/**
 * 获得指定国家的所有省份
 *
 * @access      public
 * @param       int     country    国家的编号
 * @return      array
 */
function get_regions_log($type = 0, $parent = 0)
{
    $sql = 'SELECT region_id, region_name FROM ' . $GLOBALS['ecs']->table('region') .
            " WHERE region_type = '$type' AND parent_id = '$parent'";

    return $GLOBALS['db']->GetAll($sql);
}

/**
 * 重新组合购物流程商品数组
 */
function get_new_group_cart_goods($cart_goods_list_new){
    $car_goods = array();
    foreach($cart_goods_list_new as $key=>$goods){
        foreach($goods['goods_list'] as $k => $list){
            $car_goods[] = $list;
        }
    }

    return $car_goods;
}

/**
 * 判断进货单商品是否存在
 */
function get_is_cart_goods($cart_value){
    $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('cart') . " WHERE rec_id " . db_create_in($cart_value);
    return $GLOBALS['db']->getOne($sql);
}
?>
