<?php

/**
 * ECSHOP 优惠券
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
require(ROOT_PATH . 'includes/cls_json.php');

/* 过滤 XSS 攻击和SQL注入 */
get_request_filter();

//ecmoban模板堂 --zhuo start
require(ROOT_PATH . '/includes/lib_area.php');  //ecmoban模板堂 --zhuo
$area_info = get_area_info($province_id);
$area_id = $area_info['region_id'];

$where = "regionId = '$province_id'";
$date = array('parent_id');
$region_id = get_table_date('region_warehouse', $where, $date, 2);
//ecmoban模板堂 --zhuo end

$user_id = !empty($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

/*  @author-bylu 优惠券 start  */

assign_template();
assign_ur_here();

$smarty->assign('helps',      get_shop_help());       // 网店帮助
$categories_pro = get_category_tree_leve_one();
$smarty->assign('categories_pro',  $categories_pro); // 分类树加强版
$smarty->assign('navigator_list',        get_navigator($ctype, $catlist));  //自定义导航栏
$time=gmtime();//当前时间

//领券中心-首页
if ($_REQUEST['act'] == 'coupons_index') {

    for ($i = 1; $i <= $_CFG['auction_ad']; $i++) {
        $coupons_index .= "'coupons_index" . $i . ","; //顶部广告轮播图
    }
    $smarty->assign('coupons_index', $coupons_index);
    //当前时间;
    $time = gmtime();

    //取出各条优惠券剩余总数(注册送、购物送除外)
    $sql = "SELECT c.cou_id,FLOOR((c.cou_total-COUNT(cu.cou_id))/c.cou_total*100) cou_surplus FROM " . $ecs->table('coupons_user') . " cu LEFT JOIN " . $ecs->table('coupons') . " c ON c.cou_id=cu.cou_id  WHERE c.review_status = 3 AND c.cou_type NOT IN(1,2) AND c.cou_end_time>$time GROUP BY c.cou_id ORDER BY c.cou_id DESC limit 6";
    $cou_surplus = $db->getAll($sql);

    //取出所有优惠券(注册送、购物送除外)
    $sql = "SELECT c.*,cu.user_id,cu.is_use FROM " . $ecs->table('coupons') . " c LEFT JOIN " . $ecs->table('coupons_user') . " cu ON c.cou_id=cu.cou_id WHERE c.review_status = 3 AND c.cou_type  NOT IN(1,2,5) AND c.cou_end_time>$time GROUP BY c.cou_id ORDER BY c.cou_id DESC limit 6";
    $cou_data = $db->getAll($sql);

    //格式化各优惠券剩余总数
    foreach ($cou_data as $k => $v) {
        foreach ($cou_surplus as $m => $n) {
            if ($v['cou_id'] == $n['cou_id'])
                $cou_data[$k]['cou_surplus'] = $n['cou_surplus'];
        }
    }

    $cou_data = fromat_coupons($cou_data);

    //秒杀券
    $seckill = $cou_data;
    foreach ($seckill as $k => $v) {
        if ($v['cou_goods']) {
            $sort_arr[] = $v['cou_order'];
        } else {
            $seckill[$k]['cou_goods_name'][0]['goods_thumb'] = "images/coupons_default.png"; //默认商品图片		   
        }
    }

    array_multisort($sort_arr, SORT_DESC, $seckill);
    $seckill = array_slice($seckill, 0, 4);

    //任务集市(限购物券(购物满额返券))
    $sql = "SELECT * FROM " . $ecs->table('coupons') . " where review_status = 3 AND cou_type  IN(2) AND cou_end_time>$time limit 4";
    $cou_goods = $db->getAll($sql);
    foreach ($cou_goods as $k => $v) {

        //商品图片(没有指定商品时为默认图片)
        if ($v['cou_ok_goods']) {
            $cou_goods[$k]['cou_ok_goods_name'] = $db->getAll("SELECT goods_id,goods_name,goods_thumb FROM " . $ecs->table('goods') . " WHERE goods_id IN(" . $v['cou_ok_goods'] . ")");
        } else {
            $cou_goods[$k]['cou_ok_goods_name'][0]['goods_thumb'] = "images/coupons_default.png";
        }
        //可使用的店铺;
        $cou_goods[$k]['store_name'] = sprintf($GLOBALS['_LANG']['use_limit'], get_shop_name($v['ru_id'], 1));
        $cou_goods[$k]['cou_end_time_format'] = local_date('Y-m-d H:i:s', $v['cou_end_time']);
    }
    
    

    //免邮神券
    $sql = "SELECT * FROM " . $ecs->table('coupons') . " where review_status = 3 AND cou_type  IN(5) AND cou_end_time>$time limit 4";
    $cou_shipping = $db->getAll($sql);
   //格式化各优惠券剩余总数
    foreach ($cou_shipping as $k => $v) {
        foreach ($cou_surplus as $m => $n) {
            if ($v['cou_id'] == $n['cou_id'])
                $cou_shipping[$k]['cou_surplus'] = $n['cou_surplus'];
        }
    }
    $cou_shipping = fromat_coupons($cou_shipping);
    
    //好券集市(用户登入了的话,重新获取用户优惠券的使用情况)
    if ($_SESSION['user_id']) {
        foreach ($cou_data as $k => $v) {
            $cou_data[$k]['is_use'] = $db->getOne("SELECT is_use FROM" . $ecs->table('coupons_user') . "WHERE cou_id='" . $v['cou_id'] . "' AND user_id='" . $_SESSION['user_id'] . "' ORDER BY uc_id DESC LIMIT 1");
        }
        foreach ($cou_shipping as $k => $v) {
            $cou_shipping[$k]['is_use'] = $db->getOne("SELECT is_use FROM" . $ecs->table('coupons_user') . "WHERE cou_id='" . $v['cou_id'] . "' AND user_id='" . $_SESSION['user_id'] . "' ORDER BY uc_id DESC LIMIT 1");
        }
    }
    
    $smarty->assign('cou_shipping', $cou_shipping);    // 免邮券
    $smarty->assign('seckill', $seckill);    // 秒杀券
    $smarty->assign('cou_goods', $cou_goods);    // 任务集市
    $smarty->assign('cou_data', $cou_data);    //   好券集市
    $smarty->assign('page_title', $_LANG['page_title_Coupon']);    // 页面标题
    $smarty->display('coupons_index.dwt');
}

//好券集市
elseif ($_REQUEST['act'] == 'coupons_list'){
    $field_arr = array('cou_end_time','cou_money');
    $order_field = !in_array($_REQUEST['field'], $field_arr) ? 'c.cou_id' : 'c.' . addslashes($_REQUEST['field']);
    if(!empty($_REQUEST['type'])){
        if($_REQUEST['type']=='all'){
            $where=" AND cou_type = 3 ";
        }elseif($_REQUEST['type']=='member'){
            $where=" AND cou_type = 4 ";
        }
        elseif($_REQUEST['type']=='shipping'){
            $where=" AND cou_type = 5 ";
        }
        else{
            $where=" ";
        }
    }else{
        $where=" ";
    }


    //当前时间;
    $time=gmtime();

    //取出各条优惠券剩余总数(注册送、购物送除外)
    $sql="SELECT c.cou_id,FLOOR((c.cou_total-COUNT(cu.cou_id))/c.cou_total*100) cou_surplus FROM ".$ecs->table('coupons_user')." cu LEFT JOIN ".$ecs->table('coupons')." c ON c.cou_id=cu.cou_id  WHERE c.review_status = 3 AND c.cou_type NOT IN(1,2) AND c.cou_end_time>$time GROUP BY c.cou_id limit 6";
    $cou_surplus=$db->getAll($sql);


    //优惠券总数;
    $sql="SELECT COUNT(c.cou_id) FROM ".$ecs->table('coupons')." c  WHERE c.review_status = 3 AND c.cou_type  NOT IN(1,2) AND c.cou_end_time>$time $where ";
    $cou_row_total=$db->getOne($sql);

    $row_num=12;
    $page_total=ceil($cou_row_total/$row_num);
    $page=empty($_REQUEST['p']) || $page_total<$_REQUEST['p']?1:$_REQUEST['p'];
    $offset=($page-1)*$row_num;

    //取出所有优惠券(注册送、购物送除外)
    $sql="SELECT c.*,cu.user_id,cu.is_use FROM ".$ecs->table('coupons')." c LEFT JOIN ".$ecs->table('coupons_user')." cu ON c.cou_id=cu.cou_id WHERE c.review_status = 3 AND c.cou_type  NOT IN(1,2) AND c.cou_end_time>$time $where  GROUP BY c.cou_id  ORDER BY $order_field DESC limit ".$offset." , ".$row_num."";
    $cou_data=$db->getAll($sql);

    //格式化各优惠券剩余总数
    foreach($cou_data as $k=>$v){
        foreach($cou_surplus as $m=>$n){
            if($v['cou_id'] == $n['cou_id'])
                $cou_data[$k]['cou_surplus']=$n['cou_surplus'];
        }
    }

    $cou_data=fromat_coupons($cou_data);

    //好券集市(用户登入了的话,重新获取用户优惠券的使用情况)
    if($_SESSION['user_id']){
        foreach ($cou_data as $k=>$v ){
            $cou_data[$k]['is_use']=$db->getOne("SELECT is_use FROM".$ecs->table('coupons_user')."WHERE cou_id='".$v['cou_id']."' AND user_id='".$_SESSION['user_id']."' ORDER BY uc_id DESC LIMIT 1");
        }
    }


    for($i=1;$i<=$page_total;$i++){
        $page_total2[]=$i;
    }
    $page_url=strstr($_SERVER['QUERY_STRING'],'&p',true)?strstr($_SERVER['QUERY_STRING'],'&p',true):$_SERVER['QUERY_STRING'];
    $smarty->assign('page_total2', $page_total2);
    $smarty->assign('page_total', $page_total);
    $smarty->assign('page', $page);
    $smarty->assign('prev_page', $page==1?1:$page-1);
    $smarty->assign('next_page', $page==$page_total?$page_total:$page+1);
    $smarty->assign('page_url',$page_url );
    $smarty->assign('cou_data', $cou_data);    //   好券集市
    $smarty->assign('page_title', '领券中心-好券集市');    // 页面标题
    $smarty->display('coupons_list.dwt');

}

//任务集市
elseif ($_REQUEST['act'] == 'coupons_goods'){

    //当前时间
    $time=gmtime();

    //任务集市数据总数(限购物券(购物满额后返的券))
    $sql="SELECT COUNT(*) FROM ".$ecs->table('coupons')." WHERE review_status = 3 AND cou_type IN(2) AND cou_end_time>$time";
    $cou_row_total=$db->getOne($sql);

    $row_num=10;
    $page_total=ceil($cou_row_total/$row_num);
    $page=empty($_REQUEST['p']) || $page_total<$_REQUEST['p']?1:$_REQUEST['p'];
    $offset=($page-1)*$row_num;

    //任务集市(限购物券(购物满额后返的券))
    $sql="SELECT * FROM ".$ecs->table('coupons')." WHERE review_status = 3 AND cou_type IN(2) limit ".$offset." , ".$row_num."";  //by yanxin 前台展示已过期标签在底下做了判断，所以删掉了“AND cou_end_time>$time ”
    $cou_goods=$db->getAll($sql);
    foreach($cou_goods as $k=>$v){

        //商品图片(没有指定商品时为默认图片)
        if($v['cou_ok_goods']){
            $cou_goods[$k]['cou_ok_goods_name']=$db->getAll("SELECT goods_id,goods_name,goods_thumb FROM ".$ecs->table('goods')." WHERE goods_id IN(".$v['cou_ok_goods'].")");
        }else{
            $cou_goods[$k]['cou_ok_goods_name'][0]['goods_thumb']="images/coupons_default.png";
        }
        $cou_goods[$k]['cou_end_time_format']=local_date('Y-m-d H:i:s',$v['cou_end_time']);
		
		//判断是否已过期,0过期，1未过期 by yanxin;
        if ($v['cou_end_time'] < $time) {
            $cou_goods[$k]['is_overtime'] = 0;
        } else {
            $cou_goods[$k]['is_overtime'] = 1;
        }
        //可使用的店铺;
        $cou_goods[$k]['store_name'] = sprintf($GLOBALS['_LANG']['use_limit'], get_shop_name($v['ru_id'], 1));
    }


    for($i=1;$i<=$page_total;$i++){
        $page_total2[]=$i;
    }
    $page_url=strstr($_SERVER['QUERY_STRING'],'&p',true)?strstr($_SERVER['QUERY_STRING'],'&p',true):$_SERVER['QUERY_STRING'];
    $smarty->assign('page_total2', $page_total2);
    $smarty->assign('page_total', $page_total);
    $smarty->assign('page', $page);
    $smarty->assign('prev_page', $page==1?1:$page-1);
    $smarty->assign('next_page', $page==$page_total?$page_total:$page+1);
    $smarty->assign('page_url',$page_url );
    $smarty->assign('cou_goods', $cou_goods);    // 任务集市
    $smarty->assign('page_title', $_LANG['Coupon_redemption_task']);    // 页面标题
    $smarty->display('coupons_goods.dwt');

}

//优惠券领取
elseif ($_REQUEST['act'] == 'coupons_receive') {

    $cou_id = !empty($_REQUEST['cou_id']) ? intval($_REQUEST['cou_id']) : 0;
    $result['is_over'] = 0;
    //取出当前优惠券信息(未过期,剩余总数大于0)
    $sql = "SELECT c.*,c.cou_total-COUNT(cu.cou_id) cou_surplus FROM " . $ecs->table('coupons') . " c LEFT JOIN " . $ecs->table('coupons_user') . " cu ON c.cou_id = cu.cou_id GROUP BY c.cou_id  HAVING cou_surplus > 0 AND  c.cou_id = '$cou_id' AND c.review_status = 3 AND c.cou_end_time > $time LIMIT 1";
    $cou_data = $db->getRow($sql);

    //判断券是不是被领取完了
    if (!$cou_data) {
        die(json_encode(array('status' => 'error', 'msg' => $_LANG['lang_coupons_receive_failure'])));
    }

    //判断是否已经领取了,并且还没有使用(根据创建优惠券时设定的每人可以领取的总张数为准,防止超额领取)
    $sql = "SELECT COUNT(*) FROM " . $ecs->table('coupons_user') . " WHERE user_id = '$user_id' AND cou_id = '$cou_id'";
    $cou_user_num = $db->getOne($sql);

    if ($cou_data['cou_user_num'] <= $cou_user_num) {
        die(json_encode(array('status' => 'error', 'msg' => sprintf($_LANG['lang_coupons_user_receive'], $cou_data['cou_user_num']))));
    }else{
        $result['is_over'] = 1;
    }

    //判断当前会员等级能不能领取
    if (strpos(',' . $cou_data['cou_ok_user'] . ',', ',' . $_SESSION['user_rank'] . ',') === false && $cou_data['cou_type'] != 3) {
        $rank_name = $db->getOne("SELECT GROUP_CONCAT(rank_name) FROM " . $ecs->table('user_rank') . " WHERE rank_id IN(" . $cou_data['cou_ok_user'] . ")");
        die(json_encode(array('status' => 'error', 'msg' => sprintf($_LANG['lang_coupons_user_rank'], $rank_name))));
    }

    //领券
    $uc_sn = $time . rand(10, 99);
    $sql = "INSERT INTO " . $ecs->table('coupons_user') . " (`user_id`,`cou_id`,`uc_sn`) VALUES ($user_id,$cou_id,'$uc_sn') ";
    if ($db->query($sql)) {
        //取出各条优惠券剩余总数(注册送、购物送除外)
        $sql = "SELECT c.cou_id,FLOOR((c.cou_total-COUNT(cu.cou_id))/c.cou_total*100) cou_surplus FROM " . $ecs->table('coupons_user') . " cu LEFT JOIN " . $ecs->table('coupons') . " c ON c.cou_id=cu.cou_id  WHERE c.cou_type NOT IN(1,2,5) AND c.cou_end_time>$time GROUP BY c.cou_id ORDER BY c.cou_id DESC limit 6";
        $cou_surplus = $db->getAll($sql);

        //取出所有优惠券(注册送、购物送除外)
        $sql = "SELECT c.*,cu.user_id,cu.is_use FROM " . $ecs->table('coupons') . " c LEFT JOIN " . $ecs->table('coupons_user') . " cu ON c.cou_id=cu.cou_id WHERE c.cou_type  NOT IN(1,2,5) AND c.cou_end_time>$time GROUP BY c.cou_id ORDER BY c.cou_id DESC limit 6";
        $cou_data = $db->getAll($sql);

        //格式化各优惠券剩余总数
        foreach ($cou_data as $k => $v) {
            foreach ($cou_surplus as $m => $n) {
                if ($v['cou_id'] == $n['cou_id'])
                    $cou_data[$k]['cou_surplus'] = $n['cou_surplus'];
            }
        }

        $cou_data = fromat_coupons($cou_data);

        //秒杀券
        $seckill = $cou_data;
        foreach ($seckill as $k => $v) {
            if ($v['cou_goods']) {
                $sort_arr[] = $v['cou_order'];
            } else {
                $seckill[$k]['cou_goods_name'][0]['goods_thumb'] = "images/coupons_default.png"; //默认商品图片		   
            }
        }

        array_multisort($sort_arr, SORT_DESC, $seckill);
        $seckill = array_slice($seckill, 0, 4);
        
         //免邮神券
        $sql = "SELECT * FROM " . $ecs->table('coupons') . " where review_status = 3 AND cou_type  IN(5) AND cou_end_time>$time limit 4";
        $cou_shipping = $db->getAll($sql);
       //格式化各优惠券剩余总数
        foreach ($cou_shipping as $k => $v) {
            foreach ($cou_surplus as $m => $n) {
                if ($v['cou_id'] == $n['cou_id'])
                    $cou_shipping[$k]['cou_surplus'] = $n['cou_surplus'];
            }
        }
        $cou_shipping = fromat_coupons($cou_shipping);

        //好券集市(用户登入了的话,重新获取用户优惠券的使用情况)
        if ($_SESSION['user_id']) {
            foreach ($cou_data as $k => $v) {
                $cou_data[$k]['is_use'] = $db->getOne("SELECT is_use FROM" . $ecs->table('coupons_user') . "WHERE cou_id='" . $v['cou_id'] . "' AND user_id='" . $_SESSION['user_id'] . "' ORDER BY uc_id DESC LIMIT 1");
            }
            foreach ($cou_shipping as $k => $v) {
                $cou_shipping[$k]['is_use'] = $db->getOne("SELECT is_use FROM" . $ecs->table('coupons_user') . "WHERE cou_id='" . $v['cou_id'] . "' AND user_id='" . $_SESSION['user_id'] . "' ORDER BY uc_id DESC LIMIT 1");
            }
        }
        
        $GLOBALS['smarty']->assign('seckill', $seckill);    // 秒杀券

        $result['content_kill'] = $GLOBALS['smarty']->fetch('library/coupons_seckill.lbi');

        $cou_data = fromat_coupons($cou_data);

        $GLOBALS['smarty']->assign('cou_data', $cou_data);

        $result['content'] = $GLOBALS['smarty']->fetch('library/coupons_data.lbi');
        $cou_data = $cou_shipping;
        $GLOBALS['smarty']->assign('cou_data', $cou_data);
        $result['content_shipping'] = $GLOBALS['smarty']->fetch('library/coupons_data.lbi');
        
        die(json_encode(array('status' => 'ok', 'msg' => $_LANG['lang_coupons_receive_succeed'], 'content' => $result['content'], 'content_kill' => $result['content_kill'])));
    }
}

/* ------------------------------------------------------ */
//-- 优惠券领取页
/* ------------------------------------------------------ */
if ($_REQUEST['act'] == 'coupons_info') {
    /* 模板赋值 */
    assign_template();
    $position = assign_ur_here();
    $smarty->assign('page_title', $position['title']);    // 页面标题
    $smarty->assign('ur_here', $position['ur_here']);  // 当前位置

    $smarty->assign('feed_url',        ($_CFG['rewrite'] == 1) ? 'feed.xml' : 'feed.php'); // RSS URL
    $smarty->assign('helps',           get_shop_help());       // 网店帮助     

    /* 获取数据 */
    $cou_id = !empty($_GET['id']) ? intval($_GET['id']) : 0;
    $cou_info = $db->getRow("SELECT * FROM " .$ecs->table('coupons'). " WHERE cou_id = '$cou_id' AND cou_type IN (3,4,5) ");
	if($cou_info){
		$cou_info['cou_start_date']   = local_date('Y-m-d H:i:s', $cou_info['cou_start_time']);
		$cou_info['cou_end_date']     = local_date('Y-m-d H:i:s', $cou_info['cou_end_time']);
		$cou_info['type_money_formatted']      = price_format($cou_info['cou_money']);
		$cou_info['min_goods_amount_formatted']      = price_format($cou_info['cou_man']);
		$cou_info['shop_name'] = get_shop_name($cou_info['ru_id'], 1); //店铺名称
                
                //获取免邮券不包邮地区
                if($cou_info['cou_type'] == 5){
                    $cou_region_list = get_cou_region_list($cou_info['cou_id']);
                    $cou_info['region_name'] = $cou_region_list['free_value_name'];
                }
                $smarty->assign('cou_info', $cou_info);
	}


    /* 是否领过 */
    if($_SESSION['user_id']){
        $sql = " SELECT COUNT(uc_id) AS user_num, cou_id FROM ".$GLOBALS['ecs']->table('coupons_user')." WHERE cou_id = '$cou_id' AND user_id = '$_SESSION[user_id]' LIMIT 1 ";
        $res = $GLOBALS['db']->getRow($sql);
		if($res['cou_id']){
			$sql = " SELECT cou_user_num FROM " .$GLOBALS['ecs']->table('coupons'). " WHERE cou_id = '$res[cou_id]' ";
			$num = $GLOBALS['db']->getOne($sql);
			if($res['user_num'] >= $num){
				$smarty->assign('exist', true);   
			}		
		}
     
    }

    /* 剩余数量 */
    $sql = " SELECT COUNT(cou_id) FROM ".$GLOBALS['ecs']->table('coupons').
			" WHERE cou_id = '$cou_id' AND (SELECT COUNT(uc_id) FROM ".$GLOBALS['ecs']->table('coupons_user')." WHERE cou_id = '$cou_id') < cou_total  LIMIT 1 ";
    $left = $GLOBALS['db']->getOne($sql);

    $smarty->assign('left', $left); 

    /* 显示模板 */
    $smarty->display('coupons.dwt');
}

/*------------------------------------------------------ */
//-- PRIVATE FUNCTION
/*------------------------------------------------------ */

/**格式化优惠券数据(注册送、购物送除外)
 * @param $cou_data
 * @return mixed
 */
function fromat_coupons($cou_data) {

    //当前时间;
    $time = gmtime();

    //优化数据;
    foreach ($cou_data as $k => $v) {

        //优惠券剩余量
        if (!isset($v['cou_surplus'])) {
            $cou_data[$k]['cou_surplus'] = 100;
        }

        //可使用优惠券的商品; bylu
        if (!empty($v['cou_goods'])) {
            $cou_data[$k]['cou_goods_name'] = $GLOBALS['db']->getAll("SELECT goods_id,goods_name,goods_thumb FROM " . $GLOBALS['ecs']->table('goods') . " WHERE goods_id IN(" . $v['cou_goods'] . ")");
        }

        //可领券的会员等级;
        if (!empty($v['cou_ok_user'])) {
            $cou_data[$k]['cou_ok_user_name'] = $GLOBALS['db']->getOne("SELECT group_concat(rank_name)  FROM " . $GLOBALS['ecs']->table('user_rank') . " WHERE rank_id IN(" . $v['cou_ok_user'] . ")");
        }

        //可使用的店铺;
        $cou_data[$k]['store_name'] = sprintf($GLOBALS['_LANG']['use_limit'], get_shop_name($v['ru_id'], 1));

        
        //时间戳转时间;
        $cou_data[$k]['cou_start_time_format'] = local_date('Y/m/d', $v['cou_start_time']);
        $cou_data[$k]['cou_end_time_format'] = local_date('Y/m/d', $v['cou_end_time']);

        //判断是否已过期;
        if ($v['cou_end_time'] < $time) {
            $cou_data[$k]['is_overdue'] = 1;
        } else {
            $cou_data[$k]['is_overdue'] = 0;
        }

        //优惠券种类;
        $cou_data[$k]['cou_type_name'] = $v['cou_type'] == 3 ? $GLOBALS['_LANG']['vouchers_all'] : ($v['cou_type'] == 4 ? $GLOBALS['_LANG']['vouchers_user'] : ($v['cou_type'] == 5 ? $GLOBALS['_LANG']['vouchers_shipping'] : $GLOBALS['_LANG']['unknown']));

        //是否已经领取过了
        if ($_SESSION['user_id']) {
            $r = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('coupons_user') . " WHERE cou_id='" . $v['cou_id'] . "' AND user_id ='" . $_SESSION['user_id'] . "'");
            if($v['cou_user_num'] <= $r){
                $cou_data[$k]['cou_is_receive'] = 1;
            }else{
                $cou_data[$k]['cou_is_receive'] = 0;
            }
        }
    }
    
    return $cou_data;
}

/*  @author-bylu  end  */
?>