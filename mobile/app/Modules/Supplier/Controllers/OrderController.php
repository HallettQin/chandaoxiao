<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/9
 * Time: 23:36
 */
namespace App\Modules\Supplier\Controllers;

use Think\Image;
use App\Modules\Base\Controllers\FrontendController;

class OrderController extends FrontendController
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        $this->user_id = $_SESSION['user_id'];
        L(require(LANG_PATH . C('shop.lang') . '/supplier.php'));
        L(require(LANG_PATH . C('shop.lang') . '/order.php'));
        $this->actionchecklogin();
        $this->check_supplier();

        $file = [
            'order'
        ];
        $this->load_helper($file);

        $this->assign_total();

        $user_action_list = get_user_action_list($_SESSION['seller_id']);
        //商家单个权限 ecmoban模板堂 start
        $order_back_apply = get_merchants_permissions($user_action_list, 'order_back_apply');
        $this->assign('order_back_apply', $order_back_apply); //退换货权限

        //定时24小时未支付订单
        timing_cancel_order();
    }

    /**
     * 订单列表页
     */
    public function actionIndex()
    {
        if (IS_AJAX || $_GET['is_ajax'] == 1) {
            $order_list = seller_order_list();
            exit(json_encode(['order_list' => $order_list['orders'], 'totalPage' => $order_list['page_count']]));
        }
        $this->assign('page_title', '订单列表');
        $this->display();
    }

    /*
     * 订单详情页
     */
    public function actionDetail() {
        global $ecs, $db, $_CFG;
        $order_id = I('order_id', 0, 'intval');
        $order = seller_order_info($order_id);

        /* 处理确认收货时间 start */
        if($order['shipping_status'] == 2 && empty($order['confirm_take_time'])){
            $sql = "SELECT MAX(log_time) AS log_time FROM " .$GLOBALS['ecs']->table('order_action'). " WHERE order_id = '" .$order['order_id']. "' AND shipping_status = '" .SS_RECEIVED. "'";
            $log_time = $GLOBALS['db']->getOne($sql, true);

            $log_other = array(
                'confirm_take_time' => $log_time
            );

            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_info'), $log_other, 'UPDATE', "order_id = '" .$order['order_id']. "'");

            $order['confirm_take_time'] = $log_time;
        }
        /* 处理确认收货时间 end */



        /* 查询更新支付状态 start */
        if (($order['order_status'] == OS_UNCONFIRMED || $order['order_status'] == OS_CONFIRMED || $order['order_status'] == OS_SPLITED) && $order['pay_status'] == PS_UNPAYED) {

            $pay_log = get_pay_log($order['order_id'], 1);
            if ($pay_log && $pay_log['is_paid'] == 0) {
                $payment = payment_info($order['pay_id']);

                $file_pay = ROOT_PATH . 'includes/modules/payment/' . $payment['pay_code'] . '.php';
                if ($payment && file_exists($file_pay)) {
                    /* 调用相应的支付方式文件 */
                    include_once($file_pay);

                    /* 取得在线支付方式的支付按钮 */
                    if (class_exists($payment['pay_code'])) {

                        $pay_obj = new $payment['pay_code'];
                        $is_callable = array($pay_obj, 'query');

                        /* 判断类对象方法是否存在 */
                        if (is_callable($is_callable)) {

                            $order_other = array(
                                'order_sn' => $order['order_sn'],
                                'log_id' => $pay_log['log_id']
                            );

                            $pay_obj->query($order_other);

                            $sql = "SELECT order_status, shipping_status, pay_status, pay_time FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '" . $order['order_id'] . "' LIMIT 1";
                            $order_info = $GLOBALS['db']->getRow($sql);
                            if ($order_info) {
                                $order['order_status'] = $order_info['order_status'];
                                $order['shipping_status'] = $order_info['shipping_status'];
                                $order['pay_status'] = $order_info['pay_status'];
                                $order['pay_time'] = $order_info['pay_time'];
                            }
                        }
                    }
                }
            }
        }

        /* 查询更新支付状态 end */
        if ($order['ru_id'] != $_SESSION['user_id']) {
           $this->error('参数错误');
        }

        //获取支付方式code
        $sql = "SELECT pay_code FROM " .$GLOBALS['ecs']->table('payment'). " WHERE pay_id = '" .$order['pay_id']. "'";
        $pay_code = $GLOBALS['db']->getOne($sql, true);

        if($pay_code == "cod" || $pay_code == "bank"){
            $this->assign('pay_code', 1);
        }else{
            $this->assign('pay_code', 0);
        }

        /*判断订单状态 by kong*/
        if ($order['order_status'] == OS_INVALID || $order['order_status'] == OS_CANCELED)
        {
            $order['can_remove'] = 1;
        }
        else
        {
            $order['can_remove'] = 0;
        }

        $order['delivery_id'] = $GLOBALS['db']->getOne("SELECT delivery_id FROM " . $ecs->table('delivery_order') . " WHERE order_sn = '" .$order['order_sn']. "'", true);


        //ecmoban模板堂 --zhuo start
        if ($_CFG['open_delivery_time'] == 1) {

            /* 查询订单信息，检查状态 */
            $sql = "SELECT order_id, user_id, order_sn , order_status, shipping_status, pay_status, auto_delivery_time, add_time, pay_time, " .
                "order_amount, goods_amount, tax, invoice_type, shipping_fee, insure_fee, pay_fee, pack_fee, card_fee, shipping_time, " .
                "bonus, integral_money, coupons, discount, money_paid, surplus, confirm_take_time, tax_id " .
                "FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '" .$order['order_id']. "' LIMIT 1";

            $orderInfo = $GLOBALS['db']->GetRow($sql);

            $confirm_take_time = gmtime();
            if (($orderInfo['order_status'] == OS_CONFIRMED || $orderInfo['order_status'] == OS_SPLITED) && $orderInfo['shipping_status'] == SS_SHIPPED && $orderInfo['pay_status'] == PS_PAYED) { //发货状态
                $delivery_time = $orderInfo['shipping_time'] + 24 * 3600 * $orderInfo['auto_delivery_time'];

                if ($confirm_take_time > $delivery_time) { //自动确认发货操作

                    $sql = "UPDATE " . $GLOBALS['ecs']->table('order_info') . " SET order_status = '" . OS_SPLITED . "', ".
                        "shipping_status = '" . SS_RECEIVED . "', pay_status = '" . PS_PAYED . "', confirm_take_time = '$confirm_take_time' WHERE order_id = '" .$order['order_id']. "'";
                    if ($GLOBALS['db']->query($sql))
                    {
                        /* 记录日志 */
                        order_action($orderInfo['order_sn'], $orderInfo['order_status'], SS_RECEIVED, $orderInfo['pay_status'], '', $GLOBALS['_LANG']['buyer'], 0, $confirm_take_time);

                        $seller_id = $GLOBALS['db']->getOne("SELECT ru_id FROM " .$GLOBALS['ecs']->table('order_goods'). " WHERE order_id = '" .$order['order_id']. "'", true);
                        $value_card = $GLOBALS['db']->getOne("SELECT use_val FROM " .$GLOBALS['ecs']->table('value_card_record'). " WHERE order_id = '" .$order['order_id']. "'", true);

                        $return_amount = get_order_return_amount($order['order_id']);

                        $other = array(
                            'user_id'               => $orderInfo['user_id'],
                            'seller_id'             => $seller_id,
                            'order_id'              => $orderInfo['order_id'],
                            'order_sn'              => $orderInfo['order_sn'],
                            'order_status'          => $orderInfo['order_status'],
                            'shipping_status'       => SS_RECEIVED,
                            'pay_status'            => $orderInfo['pay_status'],
                            'order_amount'          => $orderInfo['order_amount'],
                            'return_amount'         => $return_amount,
                            'goods_amount'          => $orderInfo['goods_amount'],
                            'tax'                   => $orderInfo['tax'],
                            'tax_id'                => $orderInfo['tax_id'],
                            'invoice_type'          => $orderInfo['invoice_type'],
                            'shipping_fee'          => $orderInfo['shipping_fee'],
                            'insure_fee'            => $orderInfo['insure_fee'],
                            'pay_fee'               => $orderInfo['pay_fee'],
                            'pack_fee'              => $orderInfo['pack_fee'],
                            'card_fee'              => $orderInfo['card_fee'],
                            'bonus'                 => $orderInfo['bonus'],
                            'integral_money'        => $orderInfo['integral_money'],
                            'coupons'               => $orderInfo['coupons'],
                            'discount'              => $orderInfo['discount'],
                            'value_card'            => $value_card,
                            'money_paid'            => $orderInfo['money_paid'],
                            'surplus'               => $orderInfo['surplus'],
                            'confirm_take_time'     => $confirm_take_time
                        );

                        if($seller_id){
                            $insert_id = get_order_bill_log($other);
                            /* by zxk jisuan*/
                            //确认收货 商家结算到可用金额
                            order_confirm_change($orderInfo['order_id'], $insert_id);
                            /* by zxk jisuan*/
                        }
                    }
                }
            }
        }
        //ecmoban模板堂 --zhuo end

        /* 如果订单不存在，退出 */
        if (empty($order))
        {
            die('order does not exist');
        }

        /* 根据订单是否完成检查权限 */
//        if (order_finished($order))
//        {
//            admin_priv('order_view_finished');
//        }
//        else
//        {
//            admin_priv('order_view');
//        }

        /* 如果管理员属于某个办事处，检查该订单是否也属于这个办事处 */
        $sql = "SELECT agency_id FROM " . $ecs->table('admin_user') . " WHERE user_id = '$_SESSION[seller_id]'";
        $agency_id = $db->getOne($sql);
        if ($agency_id > 0)
        {
            if ($order['agency_id'] != $agency_id)
            {
                $this->error(L('priv_error'));
                sys_msg(L('priv_error'));
            }
        }

        /* 取得上一个、下一个订单号 */
        if (!empty($_COOKIE['ECSCP']['lastfilter']))
        {
            $filter = unserialize(urldecode($_COOKIE['ECSCP']['lastfilter']));
            if (!empty($filter['composite_status']))
            {
                $where = '';
                //综合状态
                switch($filter['composite_status'])
                {
                    case CS_AWAIT_PAY :
                        $where .= order_query_sql('await_pay');
                        break;

                    case CS_AWAIT_SHIP :
                        $where .= order_query_sql('await_ship');
                        break;

                    case CS_FINISHED :
                        $where .= order_query_sql('finished');
                        break;

                    default:
                        if ($filter['composite_status'] != -1)
                        {
                            $where .= " AND o.order_status = '$filter[composite_status]' ";
                        }
                }
            }
        }
        $sql = "SELECT MAX(order_id) FROM " . $ecs->table('order_info') . " as o WHERE order_id < '$order[order_id]'";
        if ($agency_id > 0)
        {
            $sql .= " AND agency_id = '$agency_id'";
        }
        if (!empty($where))
        {
            $sql .= $where;
        }
        $this->assign('prev_id', $db->getOne($sql));
        $sql = "SELECT MIN(order_id) FROM " . $ecs->table('order_info') . " as o WHERE order_id > '$order[order_id]'";
        if ($agency_id > 0)
        {
            $sql .= " AND agency_id = '$agency_id'";
        }
        if (!empty($where))
        {
            $sql .= $where;
        }
        $this->assign('next_id', $db->getOne($sql));

        /* 取得所有办事处 */
        $sql = "SELECT agency_id, agency_name FROM " . $ecs->table('agency');
        $this->assign('agency_list', $db->getAll($sql));


        /* 取得区域名 */
        $order['region'] = get_user_region_address($order['order_id']);

        /* 格式化金额 */
        if ($order['order_amount'] < 0)
        {
            $order['money_refund']          = abs($order['order_amount']);
            $order['formated_money_refund'] = price_format(abs($order['order_amount']));
        }

        /* 其他处理 */
        $order['order_time']    = local_date($_CFG['time_format'], $order['add_time']);
        $order['pay_time']      = $order['pay_time'] > 0 ?
            local_date($_CFG['time_format'], $order['pay_time']) : L('ps')[PS_UNPAYED];
        $order['shipping_time'] = $order['shipping_time'] > 0 ?
            local_date($_CFG['time_format'], $order['shipping_time']) :  L('ss')[SS_UNSHIPPED];
        $order['confirm_take_time'] = $order['confirm_take_time'] > 0 ?
            local_date($_CFG['time_format'], $order['confirm_take_time']) :  L('ss')[SS_UNSHIPPED];
        $order['status']        = L('os')[$order['order_status']] . ',' .  L('ps')[$order['pay_status']] . ',' . L('ss')[$order['shipping_status']];
        $order['invoice_no']    = $order['shipping_status'] == SS_UNSHIPPED || $order['shipping_status'] == SS_PREPARING ? L('ss')[SS_UNSHIPPED] : $order['invoice_no'];


        /* 取得订单的来源 */
        if ($order['from_ad'] == 0)
        {
            $order['referer'] = empty($order['referer']) ?  L('from_self_site') : $order['referer'];
        }
        elseif ($order['from_ad'] == -1)
        {
            $order['referer'] = L('from_goods_js') . ' ('. L('from') . $order['referer'].')';
        }
        else
        {
            /* 查询广告的名称 */
            $ad_name = $db->getOne("SELECT ad_name FROM " .$ecs->table('ad'). " WHERE ad_id='$order[from_ad]'");
            $order['referer'] = L('from_ad_js') . $ad_name . ' ('. L('from') . $order['referer'].')';
        }

        /* 此订单的发货备注(此订单的最后一条操作记录) */
        $sql = "SELECT action_note FROM " . $ecs->table('order_action').
            " WHERE order_id = '$order[order_id]' AND shipping_status = 1 ORDER BY log_time DESC";
        $order['invoice_note'] = $db->getOne($sql);

        /* 自提点信息 */
        $sql = "SELECT shipping_code FROM ". $ecs->table('shipping') ." WHERE shipping_id = '$order[shipping_id]'";
        if($db->getOne($sql) == 'cac'){
            $sql = "SELECT * FROM ".$ecs->table('shipping_point')." WHERE id IN (SELECT point_id FROM ".$ecs->table('order_info')." WHERE order_id='" .$order['order_id']. "')";
            $order['point']= $db->getRow($sql);
        }

        /* 判断当前订单是否是白条分期付订单 bylu */
        $sql="SELECT stages_total,stages_one_price,is_stages FROM " .$ecs->table('baitiao_log'). " WHERE order_id = '$order_id'";
        $baitiao_info=$db->getRow($sql);
        if($baitiao_info['is_stages']==1){
            $order['is_stages']=1;
            $order['stages_total']=$baitiao_info['stages_total'];
            $order['stages_one_price']=$baitiao_info['stages_one_price'];
        }


        /*增值发票 start*/
        if($order['invoice_type'] == 1){
            $user_id = $order['user_id'];
            $sql = " SELECT * FROM " . $ecs->table('users_vat_invoices_info') . " WHERE user_id = '$user_id' LIMIT 1";
            $res = $db->getRow($sql);
            $region = array('province'=>$res['province'],'city'=>$res['city'],'district'=>$res['district']);
            $res['region'] = get_area_region_info($region);
            $this->assign('vat_info',$res);
        }
        /*增值发票 end*/

        /* 取得订单商品总重量 */
        $weight_price = order_weight_price($order['order_id']);
        $order['total_weight'] = $weight_price['formated_weight'];


        /*判断是否评论 by kong*/
        $order['is_comment'] = 0;
        $sql=" SELECT comment_id , add_time FROM".$ecs->table('comment')." WHERE order_id = '".$order['order_id']."' AND user_id = '".$order['user_id']."'";
        $comment=$db->getRow($sql);
        if($comment){
            $order['is_comment'] = 1;
            $order['comment_time'] =  $comment['add_time'] > 0 ?
                local_date($_CFG['time_format'], $order['add_time']) : "尚未评论";;
        }
        /* 参数赋值：订单 */
        $this->assign('order', $order);

        if ($order['user_id'] > 0)
        {
            $user = user_info($order['user_id']);

            /* 用户等级 */
            if ($user['user_rank'] > 0)
            {
                $where = " WHERE rank_id = '$user[user_rank]' ";
            }
            else
            {
                $where = " WHERE min_points <= " . intval($user['rank_points']) . " ORDER BY min_points DESC ";
            }
            $sql = "SELECT rank_name FROM " . $ecs->table('user_rank') . $where;
            $user['rank_name'] = $db->getOne($sql);

            // 用户红包数量
            $day    = getdate();
            $today  = local_mktime(23, 59, 59, $day['mon'], $day['mday'], $day['year']);
            $sql = "SELECT COUNT(*) " .
                "FROM " . $ecs->table('bonus_type') . " AS bt, " . $ecs->table('user_bonus') . " AS ub " .
                "WHERE bt.type_id = ub.bonus_type_id " .
                "AND ub.user_id = '$order[user_id]' " .
                "AND ub.order_id = 0 " .
                "AND bt.use_start_date <= '$today' " .
                "AND bt.use_end_date >= '$today'";
            $user['bonus_count'] = $db->getOne($sql);
            $this->assign('user', $user);

            // 地址信息
            $sql = "SELECT * FROM " . $ecs->table('user_address') . " WHERE user_id = '$order[user_id]'";
            $this->assign('address_list', $db->getAll($sql));
        }


        /* 取得用户名 */
        if ($order['user_id'] > 0)
        {
            $user = user_info($order['user_id']);
            if (!empty($user))
            {
                $order['user_name'] = $user['user_name'];
            }
        }


        /* 取得订单商品及货品 */
        $goods_list = array();
        $goods_attr = array();
        $sql = "SELECT o.*, c.measure_unit, g.goods_number AS storage, g.model_inventory, g.model_attr as model_attr, o.goods_attr, g.suppliers_id, p.product_sn,g.goods_thumb,
            g.user_id AS ru_id, g.brand_id, g.bar_code, IF(oi.extension_code != '', oi.extension_code, o.extension_code), oi.extension_id , o.extension_code as o_extension_code, oi.extension_code as oi_extension_code
            FROM " . $ecs->table('order_goods') . " AS o
                LEFT JOIN " . $ecs->table('products') . " AS p
                    ON p.product_id = o.product_id
                LEFT JOIN " . $ecs->table('goods') . " AS g
                    ON o.goods_id = g.goods_id
                LEFT JOIN " . $ecs->table('category') . " AS c
                    ON g.cat_id = c.cat_id
				LEFT JOIN " . $ecs->table('order_info') . " AS oi
					ON o.order_id = oi.order_id
            WHERE o.order_id = '$order[order_id]'";
        $res = $db->query($sql);

        foreach ($res as $key => $row)
        {
            /* 虚拟商品支持 */
            if ($row['is_real'] == 0)
            {
                /* 取得语言项 */
                $filename = ROOT_PATH . 'plugins/' . $row['extension_code'] . '/languages/common_' . $_CFG['lang'] . '.php';
                if (file_exists($filename))
                {
                    include_once($filename);
                    $extension_code_lang = L($row['extension_code'].'_link');
                    if (!empty($extension_code_lang))
                    {
                        $row['goods_name'] = $row['goods_name'] . sprintf(L($row['extension_code'].'_link'), $row['goods_id'], $order['order_sn']);
                    }
                }
            }

            if($row['model_inventory'] == 1){
                $row['storage'] = get_warehouse_area_goods($row['warehouse_id'], $row['goods_id'], 'warehouse_goods');
            }elseif($row['model_inventory'] == 2){
                $row['storage'] = get_warehouse_area_goods($row['area_id'], $row['goods_id'], 'warehouse_area_goods');
            }

            //ecmoban模板堂 --zhuo start 商品金额促销
            $row['goods_amount'] = $row['goods_price'] * $row['goods_number'];
            $goods_con = get_con_goods_amount($row['goods_amount'], $row['goods_id'], 0, 0, $row['parent_id']);

            $goods_con['amount'] = explode(',', $goods_con['amount']);
            $row['amount'] = min($goods_con['amount']);

            $row['dis_amount'] = $row['goods_amount'] - $row['amount'];
            $row['discount_amount'] = price_format($row['dis_amount'], false);
            //ecmoban模板堂 --zhuo end 商品金额促销

            //ecmoban模板堂 --zhuo start //库存查询
            $products = get_warehouse_id_attr_number($row['goods_id'], $row['goods_attr_id'], $row['ru_id'], $row['warehouse_id'], $row['area_id'], $row['model_attr']);
            $row['goods_storage'] = $products['product_number'];

            if($row['product_id']){
                $row['bar_code'] = $products['bar_code'];
            }

            if($row['model_attr'] == 1){
                $table_products = "products_warehouse";
                $type_files = " and warehouse_id = '" .$row['warehouse_id']. "'";
            }elseif($row['model_attr'] == 2){
                $table_products = "products_area";
                $type_files = " and area_id = '" .$row['area_id']. "'";
            }else{
                $table_products = "products";
                $type_files = "";
            }

            $sql = "SELECT * FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '" .$row['goods_id']. "'" .$type_files. " LIMIT 0, 1";
            $prod = $GLOBALS['db']->getRow($sql);

            if(empty($prod)){ //当商品没有属性库存时
                $row['goods_storage'] = $row['storage'];
            }

            $row['goods_storage'] = !empty($row['goods_storage']) ? $row['goods_storage'] : 0;
            $row['storage'] = $row['goods_storage'];
            $row['product_sn'] = $products['product_sn'];
            //ecmoban模板堂 --zhuo end //库存查询

            $brand = get_goods_brand_info($row['brand_id']);
            $row['brand_name'] = $brand['brand_name'];

            $row['formated_subtotal']       = price_format($row['amount']);
            $row['formated_goods_price']    = price_format($row['goods_price']);

            $row['warehouse_name']    = $db->getOne("select region_name from " .$ecs->table('region_warehouse'). " where region_id = '" .$row['warehouse_id']. "'");

            $goods_attr[] = explode(' ', trim($row['goods_attr'])); //将商品属性拆分为一个数组

            if ($row['extension_code'] == 'package_buy')
            {
                $row['storage'] = '';
                $row['brand_name'] = '';
                $row['package_goods_list'] = get_package_goods($row['goods_id']);
            }

            //图片显示
            $row['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);

            $goods_list[] = $row;
        }

        $attr = array();
        $arr  = array();
        foreach ($goods_attr AS $index => $array_val)
        {
            foreach ($array_val AS $value)
            {
                $arr = explode(':', $value);//以 : 号将属性拆开
                $attr[$index][] =  @array('name' => $arr[0], 'value' => $arr[1]);
            }
        }
        $this->assign('goods_attr', $attr);
        $this->assign('goods_list', $goods_list);


        /* 取得能执行的操作列表 */
        $operable_list = seller_operable_list($order);
        $this->assign('operable_list', $operable_list);

        /* 判断退换货订单申请是否通过 strat */
        $sql = "SELECT agree_apply FROM " . $ecs->table('order_return') . " WHERE order_id = '$order[order_id]'";
        $is_apply = $db->getOne($sql);
        $this->assign('is_apply', $is_apply);
        /* 判断退换货订单申请是否通过 end */


        /**
         * 取得用户收货时间 以快物流信息显示为准，目前先用用户收货时间为准，后期修改TODO by Leah S
         */
        $sql = "SELECT log_time  FROM " . $ecs->table('order_action') . " WHERE order_id = '$order[order_id]' ";
        $res_time = local_date($_CFG['time_format'], $db->getOne($sql));
        $this->assign('res_time', $res_time);
        /**
         * by Leah E
         */

        /* 取得订单操作记录 */
        $act_list = array();
        $sql = "SELECT * FROM " . $ecs->table('order_action') . " WHERE order_id = '$order[order_id]' ORDER BY log_time DESC,action_id DESC";
        $res = $db->query($sql);
        foreach ($res as $key => $row)
        {
            $row['order_status']    = L('os')[$row['order_status']];
            $row['pay_status']      =  L('ps')[$row['pay_status']];
            $row['shipping_status'] =  L('ss')[$row['shipping_status']];
            $row['action_time']     = local_date($_CFG['time_format'], $row['log_time']);
            $act_list[] = $row;
        }

        $this->assign('action_list', $act_list);

        /* 取得是否存在实体商品 */
        $this->assign('exist_real_goods', exist_real_goods($order['order_id']));


        /* 返回门店列表 */
        if($order['pay_status'] == 2 && $order['shipping_status'] == 0)
        {
            $sql = " SELECT COUNT(*) FROM ".$GLOBALS['ecs']->table('store_order')." WHERE order_id = '$order[order_id]' AND store_id > 0 ";
            $have_store_order = $GLOBALS['db']->getOne($sql);
            if($have_store_order == 0)
            {
                $this->assign('can_set_grab_order', 1);
            }
        }


        $this->assign('order', $order);
        $this->assign('page_title', '订单详情');
        $this->display();
    }

    public function actionOperate (){
        $this->assign('page_title', '订单操作');
        /* 检查权限 */
        admin_priv('order_os_edit');

        $adminru['ru_id'] = $_SESSION['user_id'];

        global $ecs, $db, $_CFG;

        $order_id = isset($_REQUEST['order_id']) && !empty($_REQUEST['order_id']) ? intval($_REQUEST['order_id']) : 0;
        $rec_id = isset($_REQUEST['rec_id']) && !empty($_REQUEST['rec_id']) ? intval($_REQUEST['rec_id']) : 0;
        $ret_id = isset($_REQUEST['ret_id']) && !empty($_REQUEST['ret_id']) ? intval($_REQUEST['ret_id']) : 0;

        /* 取得订单id（可能是多个，多个sn）和操作备注（可能没有） */
        $batch = isset($_REQUEST['batch']); // 是否批处理
        $action_note = isset($_REQUEST['action_note']) ? trim($_REQUEST['action_note']) : '';

        /* 确认 */
        if (isset($_POST['confirm'])) {
            $require_note = false;
            $action = L('op_confirm');
            $operation = 'confirm';
        }

        /* ------------------------------------------------------ */
        //-- start 一键发货
        /* ------------------------------------------------------ */
        elseif (isset($_POST['to_shipping'])) {
            /* 定义当前时间 */
            $invoice_no = empty($_REQUEST['invoice_no']) ? '' : trim($_REQUEST['invoice_no']);  //快递单号

            if (empty($invoice_no)) {
                /* 操作失败 */
                $href = url('order/detail', ['order_id'=>$order_id]);
                $links[] = array('text' =>L('invoice_no_null'), 'href' => $href);
                sys_msg(L('act_false'), 0, $links);
            }

            /* 定义当前时间 */
            define('GMTIME_UTC', gmtime()); // 获取 UTC 时间戳

            $delivery_info = get_delivery_info($order_id);

            if (!empty($invoice_no) && !$delivery_info) {
                $order_id = intval(trim($order_id));

                /* 查询：根据订单id查询订单信息 */
                if (!empty($order_id)) {
                    $order = seller_order_info($order_id);
                } else {
                    die('order does not exist');
                }
                /* 查询：根据订单是否完成 检查权限 */
                if (order_finished($order)) {
                    admin_priv('order_view_finished');
                } else {
                    admin_priv('order_view');
                }

                /* 查询：如果管理员属于某个办事处，检查该订单是否也属于这个办事处 */
                $sql = "SELECT agency_id FROM " . $ecs->table('admin_user') . " WHERE user_id = '$_SESSION[seller_id]'";
                $agency_id = $db->getOne($sql);
                if ($agency_id > 0) {
                    if ($order['agency_id'] != $agency_id) {
                        sys_msg(L('priv_error'), 0);
                    }
                }
                /* 查询：取得用户名 */
                if ($order['user_id'] > 0) {
                    $user = user_info($order['user_id']);
                    if (!empty($user)) {
                        $order['user_name'] = $user['user_name'];
                    }
                }
                /* 查询：取得区域名 */

                $order['region'] = $db->getOne($sql);

                /* 查询：其他处理 */
                $order['order_time'] = local_date($_CFG['time_format'], $order['add_time']);
                $order['invoice_no'] = $order['shipping_status'] == SS_UNSHIPPED || $order['shipping_status'] == SS_PREPARING ? L('ss')[SS_UNSHIPPED] : $order['invoice_no'];

                /* 查询：是否保价 */
                $order['insure_yn'] = empty($order['insure_fee']) ? 0 : 1;
                /* 查询：是否存在实体商品 */
                $exist_real_goods = exist_real_goods($order_id);


                /* 查询：取得订单商品 */
                $_goods = get_order_goods(array('order_id' => $order['order_id'], 'order_sn' => $order['order_sn']));

                $attr = $_goods['attr'];
                $goods_list = $_goods['goods_list'];
                unset($_goods);

                /* 查询：商品已发货数量 此单可发货数量 */
                if ($goods_list) {
                    foreach ($goods_list as $key => $goods_value) {
                        if (!$goods_value['goods_id']) {
                            continue;
                        }

                        /* 超级礼包 */
                        if (($goods_value['extension_code'] == 'package_buy') && (count($goods_value['package_goods_list']) > 0)) {
                            $goods_list[$key]['package_goods_list'] = package_goods($goods_value['package_goods_list'], $goods_value['goods_number'], $goods_value['order_id'], $goods_value['extension_code'], $goods_value['goods_id']);

                            foreach ($goods_list[$key]['package_goods_list'] as $pg_key => $pg_value) {
                                $goods_list[$key]['package_goods_list'][$pg_key]['readonly'] = '';
                                /* 使用库存 是否缺货 */
                                if ($pg_value['storage'] <= 0 && $_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP) {
                                    $goods_list[$key]['package_goods_list'][$pg_key]['send'] = L('act_good_vacancy');
                                    $goods_list[$key]['package_goods_list'][$pg_key]['readonly'] = 'readonly="readonly"';
                                } /* 将已经全部发货的商品设置为只读 */
                                elseif ($pg_value['send'] <= 0) {
                                    $goods_list[$key]['package_goods_list'][$pg_key]['send'] = L('act_good_delivery');
                                    $goods_list[$key]['package_goods_list'][$pg_key]['readonly'] = 'readonly="readonly"';
                                }
                            }
                        } else {
                            $goods_list[$key]['sended'] = $goods_value['send_number'];
                            $goods_list[$key]['sended'] = $goods_value['goods_number'];
                            $goods_list[$key]['send'] = $goods_value['goods_number'] - $goods_value['send_number'];
                            $goods_list[$key]['readonly'] = '';
                            /* 是否缺货 */
                            if ($goods_value['storage'] <= 0 && $_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP) {
                                $goods_list[$key]['send'] = L('act_good_vacancy');
                                $goods_list[$key]['readonly'] = 'readonly="readonly"';
                            } elseif ($goods_list[$key]['send'] <= 0) {
                                $goods_list[$key]['send'] = L('act_good_delivery');
                                $goods_list[$key]['readonly'] = 'readonly="readonly"';
                            }
                        }
                    }
                }

                $suppliers_id = 0;

                $delivery['order_sn'] = trim($order['order_sn']);
                $delivery['add_time'] = trim($order['order_time']);
                $delivery['user_id'] = intval(trim($order['user_id']));
                $delivery['how_oos'] = trim($order['how_oos']);
                $delivery['shipping_id'] = trim($order['shipping_id']);
                $delivery['shipping_fee'] = trim($order['shipping_fee']);
                $delivery['consignee'] = trim($order['consignee']);
                $delivery['address'] = trim($order['address']);
                $delivery['country'] = intval(trim($order['country']));
                $delivery['province'] = intval(trim($order['province']));
                $delivery['city'] = intval(trim($order['city']));
                $delivery['district'] = intval(trim($order['district']));
                $delivery['sign_building'] = trim($order['sign_building']);
                $delivery['email'] = trim($order['email']);
                $delivery['zipcode'] = trim($order['zipcode']);
                $delivery['tel'] = trim($order['tel']);
                $delivery['mobile'] = trim($order['mobile']);
                $delivery['best_time'] = trim($order['best_time']);
                $delivery['postscript'] = trim($order['postscript']);
                $delivery['how_oos'] = trim($order['how_oos']);
                $delivery['insure_fee'] = floatval(trim($order['insure_fee']));
                $delivery['shipping_fee'] = floatval(trim($order['shipping_fee']));
                $delivery['agency_id'] = intval(trim($order['agency_id']));
                $delivery['shipping_name'] = trim($order['shipping_name']);

                /* 检查能否操作 */
                $operable_list = operable_list($order);

                /* 初始化提示信息 */
                $msg = '';

                /* 取得订单商品 */
                $_goods = get_order_goods(array('order_id' => $order_id, 'order_sn' => $delivery['order_sn']));
                $goods_list = $_goods['goods_list'];


                /* 检查此单发货商品库存缺货情况 */
                /* $goods_list已经过处理 超值礼包中商品库存已取得 */
                $virtual_goods = array();
                $package_virtual_goods = array();
                /* 生成发货单 */
                /* 获取发货单号和流水号 */
                $delivery['delivery_sn'] = get_delivery_sn();
                $delivery_sn = $delivery['delivery_sn'];

                /* 获取当前操作员 */
                $delivery['action_user'] = $_SESSION['admin_name'];

                /* 获取发货单生成时间 */
                $delivery['update_time'] = GMTIME_UTC;
                $delivery_time = $delivery['update_time'];
                $sql = "select add_time from " . $GLOBALS['ecs']->table('order_info') . " WHERE order_sn = '" . $delivery['order_sn'] . "'";
                $delivery['add_time'] = $GLOBALS['db']->GetOne($sql);
                /* 获取发货单所属供应商 */
                $delivery['suppliers_id'] = $suppliers_id;

                /* 设置默认值 */
                $delivery['status'] = 2; // 正常
                $delivery['order_id'] = $order_id;

                /* 过滤字段项 */
                $filter_fileds = array(
                    'order_sn', 'add_time', 'user_id', 'how_oos', 'shipping_id', 'shipping_fee',
                    'consignee', 'address', 'country', 'province', 'city', 'district', 'sign_building',
                    'email', 'zipcode', 'tel', 'mobile', 'best_time', 'postscript', 'insure_fee',
                    'agency_id', 'delivery_sn', 'action_user', 'update_time',
                    'suppliers_id', 'status', 'order_id', 'shipping_name'
                );
                $_delivery = array();
                foreach ($filter_fileds as $value) {
                    $_delivery[$value] = $delivery[$value];
                }

                /* 发货单入库 */
                //修改pc 端 插入
                $delivery_id = dao('delivery_order')->data($_delivery)->add();

                if ($delivery_id) {

                    $delivery_goods = array();

                    //发货单商品入库
                    if (!empty($goods_list)) {
                        foreach ($goods_list as $value) {
                            // 商品（实货）（虚货）
                            if (empty($value['extension_code']) || $value['extension_code'] == 'virtual_card') {
                                $delivery_goods = array('delivery_id' => $delivery_id,
                                    'goods_id' => $value['goods_id'],
                                    'product_id' => $value['product_id'],
                                    'product_sn' => $value['product_sn'],
                                    'goods_id' => $value['goods_id'],
                                    'goods_name' => $value['goods_name'],
                                    'brand_name' => $value['brand_name'],
                                    'goods_sn' => $value['goods_sn'],
                                    'send_number' => $value['goods_number'],
                                    'parent_id' => 0,
                                    'is_real' => $value['is_real'],
                                    'goods_attr' => $value['goods_attr']
                                );
                                /* 如果是货品 */
                                if (!empty($value['product_id'])) {
                                    $delivery_goods['product_id'] = $value['product_id'];
                                }
                                $query = $db->autoExecute($ecs->table('delivery_goods'), $delivery_goods, 'INSERT', '', 'SILENT');
                                $sql = "UPDATE " . $GLOBALS['ecs']->table('order_goods') . "
                    SET send_number = " . $value['goods_number'] . "
                    WHERE order_id = '" . $value['order_id'] . "'
                    AND goods_id = '" . $value['goods_id'] . "' ";
                                $GLOBALS['db']->query($sql, 'SILENT');
                            } // 商品（超值礼包）
                            elseif ($value['extension_code'] == 'package_buy') {
                                foreach ($value['package_goods_list'] as $pg_key => $pg_value) {
                                    $delivery_pg_goods = array('delivery_id' => $delivery_id,
                                        'goods_id' => $pg_value['goods_id'],
                                        'product_id' => $pg_value['product_id'],
                                        'product_sn' => $pg_value['product_sn'],
                                        'goods_name' => $pg_value['goods_name'],
                                        'brand_name' => '',
                                        'goods_sn' => $pg_value['goods_sn'],
                                        'send_number' => $value['goods_number'],
                                        'parent_id' => $value['goods_id'], // 礼包ID
                                        'extension_code' => $value['extension_code'], // 礼包
                                        'is_real' => $pg_value['is_real']
                                    );
                                    $query = $db->autoExecute($ecs->table('delivery_goods'), $delivery_pg_goods, 'INSERT', '', 'SILENT');
                                    $sql = "UPDATE " . $GLOBALS['ecs']->table('order_goods') . "
                                            SET send_number = " . $value['goods_number'] . "
                                            WHERE order_id = '" . $value['order_id'] . "'
                                            AND goods_id = '" . $pg_value['goods_id'] . "' ";
                                    $GLOBALS['db']->query($sql, 'SILENT');
                                }
                            }
                        }
                    }
                } else {
                    /* 操作失败 */
                    $links[] = array('text' => L('order_info'), 'href' => 'order.php?act=info&order_id=' . $order_id);
                    sys_msg(L('act_false'), 1, $links);
                }
                unset($filter_fileds, $delivery, $_delivery, $order_finish);

                /* 定单信息更新处理 */
                if (true) {

                    /* 标记订单为已确认 “发货中” */
                    /* 更新发货时间 */
                    $order_finish = get_order_finish($order_id);
                    $shipping_status = SS_SHIPPED_ING;
                    if ($order['order_status'] != OS_CONFIRMED && $order['order_status'] != OS_SPLITED && $order['order_status'] != OS_SPLITING_PART) {
                        $arr['order_status'] = OS_CONFIRMED;
                        $arr['confirm_time'] = GMTIME_UTC;
                    }
                    $arr['order_status'] = $order_finish ? OS_SPLITED : OS_SPLITING_PART; // 全部分单、部分分单
                    $arr['shipping_status'] = $shipping_status;
                    update_order($order_id, $arr);
                }

                /* 记录log */
                order_action($order['order_sn'], $arr['order_status'], $shipping_status, $order['pay_status'], $action_note, $_SESSION['seller_name']);

                /* 清除缓存 */
                clear_cache_files();

                /* 根据发货单id查询发货单信息 */
                if (!empty($delivery_id)) {
                    $delivery_order = delivery_order_info($delivery_id);
                } elseif (!empty($order_sn)) {

                    $delivery_id = $GLOBALS['db']->getOne("SELECT delivery_id FROM " . $ecs->table('delivery_order') . " WHERE order_sn = '$order_sn'");
                    $delivery_order = delivery_order_info($delivery_id);
                } else {
                    die('order does not exist');
                }

                /* 如果管理员属于某个办事处，检查该订单是否也属于这个办事处 */
                $sql = "SELECT agency_id FROM " . $ecs->table('admin_user') . " WHERE user_id = '" . $_SESSION['seller_id'] . "'";
                $agency_id = $db->getOne($sql);
                if ($agency_id > 0) {
                    if ($delivery_order['agency_id'] != $agency_id) {
                        sys_msg(L('priv_error'));
                    }

                    /* 取当前办事处信息 */
                    $sql = "SELECT agency_name FROM " . $ecs->table('agency') . " WHERE agency_id = '$agency_id' LIMIT 0, 1";
                    $agency_name = $db->getOne($sql);
                    $delivery_order['agency_name'] = $agency_name;
                }

                /* 取得用户名 */
                if ($delivery_order['user_id'] > 0) {
                    $user = user_info($delivery_order['user_id']);
                    if (!empty($user)) {
                        $delivery_order['user_name'] = $user['user_name'];
                    }
                }

                /* 取得区域名 */
                $sql = "SELECT concat(IFNULL(c.region_name, ''), '  ', IFNULL(p.region_name, ''), " .
                    "'  ', IFNULL(t.region_name, ''), '  ', IFNULL(d.region_name, '')) AS region " .
                    "FROM " . $ecs->table('order_info') . " AS o " .
                    "LEFT JOIN " . $ecs->table('region') . " AS c ON o.country = c.region_id " .
                    "LEFT JOIN " . $ecs->table('region') . " AS p ON o.province = p.region_id " .
                    "LEFT JOIN " . $ecs->table('region') . " AS t ON o.city = t.region_id " .
                    "LEFT JOIN " . $ecs->table('region') . " AS d ON o.district = d.region_id " .
                    "WHERE o.order_id = '" . $delivery_order['order_id'] . "'";
                $delivery_order['region'] = $db->getOne($sql);

                /* 是否保价 */
                $order['insure_yn'] = empty($order['insure_fee']) ? 0 : 1;

                /* 取得发货单商品 */
                $goods_sql = "SELECT *
                      FROM " . $ecs->table('delivery_goods') . "
                      WHERE delivery_id = " . $delivery_order['delivery_id'];
                $goods_list = $GLOBALS['db']->getAll($goods_sql);

                /* 是否存在实体商品 */
                $exist_real_goods = 0;
                if ($goods_list) {
                    foreach ($goods_list as $value) {
                        if ($value['is_real']) {
                            $exist_real_goods++;
                        }
                    }
                }

                /* 取得订单操作记录 */
                $act_list = array();
                $sql = "SELECT * FROM " . $ecs->table('order_action') . " WHERE order_id = '" . $delivery_order['order_id'] . "' AND action_place = 1 ORDER BY log_time DESC,action_id DESC";
                $res = $db->query($sql);
//                while ($row = $db->fetchRow($res)) {
                foreach ($res as $key => $row) {
                    $row['order_status'] = L('os')[$row['order_status']];
                    $row['pay_status'] = L('ps')[$row['pay_status']];
                    $row['shipping_status'] = ($row['shipping_status'] == SS_SHIPPED_ING) ? L('ss_admin')[SS_SHIPPED_ING] : L('ss')[$row['shipping_status']];
                    $row['action_time'] = local_date($_CFG['time_format'], $row['log_time']);
                    $act_list[] = $row;
                }

                /* 同步发货 */
                /* 判断支付方式是否支付宝 */
                $alipay = false;
                $order = seller_order_info($delivery_order['order_id']);  //根据订单ID查询订单信息，返回数组$order
                $payment = payment_info($order['pay_id']);           //取得支付方式信息

                /* 根据发货单id查询发货单信息 */
                if (!empty($delivery_id)) {
                    $delivery_order = delivery_order_info($delivery_id);
                } else {
                    die('order does not exist');
                }

                /* 检查此单发货商品库存缺货情况  ecmoban模板堂 --zhuo start 下单减库存*/
                $delivery_stock_sql = "SELECT DG.rec_id AS dg_rec_id, OG.rec_id AS og_rec_id, G.model_attr, G.model_inventory, DG.goods_id, DG.delivery_id, DG.is_real, DG.send_number AS sums, G.goods_number AS storage, G.goods_name, DG.send_number," .
                    " OG.goods_attr_id, OG.warehouse_id, OG.area_id, OG.ru_id, OG.order_id, OG.product_id FROM " . $GLOBALS['ecs']->table('delivery_goods') . " AS DG, " .
                    $GLOBALS['ecs']->table('goods') . " AS G, " .
                    $GLOBALS['ecs']->table('delivery_order') . " AS D, " .
                    $GLOBALS['ecs']->table('order_goods') . " AS OG " .
                    " WHERE DG.goods_id = G.goods_id AND DG.delivery_id = D.delivery_id AND D.order_id = OG.order_id AND DG.goods_sn = OG.goods_sn AND DG.product_id = OG.product_id AND DG.delivery_id = '$delivery_id' GROUP BY OG.rec_id ";

                $delivery_stock_result = $GLOBALS['db']->getAll($delivery_stock_sql);

                $virtual_goods = array();
                for ($i = 0; $i < count($delivery_stock_result); $i++) {
                    if ($delivery_stock_result[$i]['model_attr'] == 1) {
                        $table_products = "products_warehouse";
                        $type_files = " and warehouse_id = '" . $delivery_stock_result[$i]['warehouse_id'] . "'";
                    } elseif ($delivery_stock_result[$i]['model_attr'] == 2) {
                        $table_products = "products_area";
                        $type_files = " and area_id = '" . $delivery_stock_result[$i]['area_id'] . "'";
                    } else {
                        $table_products = "products";
                        $type_files = "";
                    }

                    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table($table_products) . " WHERE goods_id = '" . $delivery_stock_result[$i]['goods_id'] . "'" . $type_files . " LIMIT 0, 1";
                    $prod = $GLOBALS['db']->getRow($sql);

                    /* 如果商品存在规格就查询规格，如果不存在规格按商品库存查询 */
                    if (empty($prod)) {
                        if ($delivery_stock_result[$i]['model_inventory'] == 1) {
                            $delivery_stock_result[$i]['storage'] = get_warehouse_area_goods($delivery_stock_result[$i]['warehouse_id'], $delivery_stock_result[$i]['goods_id'], 'warehouse_goods');
                        } elseif ($delivery_stock_result[$i]['model_inventory'] == 2) {
                            $delivery_stock_result[$i]['storage'] = get_warehouse_area_goods($delivery_stock_result[$i]['area_id'], $delivery_stock_result[$i]['goods_id'], 'warehouse_area_goods');
                        }
                    } else {
                        $products = get_warehouse_id_attr_number($delivery_stock_result[$i]['goods_id'], $delivery_stock_result[$i]['goods_attr_id'], $delivery_stock_result[$i]['ru_id'], $delivery_stock_result[$i]['warehouse_id'], $delivery_stock_result[$i]['area_id'], $delivery_stock_result[$i]['model_attr']);
                        $delivery_stock_result[$i]['storage'] = $products['product_number'];
                    }

                    if (($delivery_stock_result[$i]['sums'] > $delivery_stock_result[$i]['storage'] || $delivery_stock_result[$i]['storage'] <= 0) && (($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP) || ($_CFG['use_storage'] == '0' && $delivery_stock_result[$i]['is_real'] == 0))) {
                        /* 操作失败 */
                        $links[] = array('text' => L('order_info'), 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
                        sys_msg(sprintf(L('act_good_vacancy'), $value['goods_name']), 1, $links);
                        break;
                    }

                    /* 虚拟商品列表 virtual_card*/
                    if ($delivery_stock_result[$i]['is_real'] == 0) {
                        $virtual_goods[] = array(
                            'goods_id' => $delivery_stock_result[$i]['goods_id'],
                            'goods_name' => $delivery_stock_result[$i]['goods_name'],
                            'num' => $delivery_stock_result[$i]['send_number']
                        );
                    }
                }
                //ecmoban模板堂 --zhuo end 下单减库存

                /* 发货 */
                /* 处理虚拟卡 商品（虚货） */
                if ($virtual_goods && is_array($virtual_goods) && count($virtual_goods) > 0) {
                    foreach ($virtual_goods as $virtual_value) {
                        virtual_card_shipping($virtual_value, $order['order_sn'], $msg, 'split');
                    }

                    //虚拟卡缺货
                    if (!empty($msg)) {
                        $links[] = array('text' => L('delivery_sn') . L('detail'), 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
                        sys_msg($msg, 1, $links);
                    }
                }

                /* 如果使用库存，且发货时减库存，则修改库存 */
                if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP) {

                    foreach ($delivery_stock_result as $value) {

                        /* 商品（实货）、超级礼包（实货） ecmoban模板堂 --zhuo */
                        if ($value['is_real'] != 0) {
                            //（货品）
                            if (!empty($value['product_id'])) {
                                if ($value['model_attr'] == 1) {
                                    $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products_warehouse') . "
                                                SET product_number = product_number - " . $value['sums'] . "
                                                WHERE product_id = " . $value['product_id'];
                                } elseif ($value['model_attr'] == 2) {
                                    $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products_area') . "
                                                SET product_number = product_number - " . $value['sums'] . "
                                                WHERE product_id = " . $value['product_id'];
                                } else {
                                    $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products') . "
                                                SET product_number = product_number - " . $value['sums'] . "
                                                WHERE product_id = " . $value['product_id'];
                                }
                            } else {
                                if ($value['model_inventory'] == 1) {
                                    $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('warehouse_goods') . "
                                                SET region_number = region_number - " . $value['sums'] . "
                                                WHERE goods_id = " . $value['goods_id'] . " AND region_id = " . $value['warehouse_id'];
                                } elseif ($value['model_inventory'] == 2) {
                                    $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('warehouse_area_goods') . "
                                                SET region_number = region_number - " . $value['sums'] . "
                                                WHERE goods_id = " . $value['goods_id'] . " AND region_id = " . $value['area_id'];
                                } else {
                                    $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('goods') . "
                                                SET goods_number = goods_number - " . $value['sums'] . "
                                                WHERE goods_id = " . $value['goods_id'];
                                }
                            }

                            $GLOBALS['db']->query($minus_stock_sql, 'SILENT');

                            //库存日志
                            $logs_other = array(
                                'goods_id' => $value['goods_id'],
                                'order_id' => $value['order_id'],
                                'use_storage' => $_CFG['stock_dec_time'],
                                'admin_id' => $_SESSION['seller_id'],
                                'number' => "- " . $value['sums'],
                                'model_inventory' => $value['model_inventory'],
                                'model_attr' => $value['model_attr'],
                                'product_id' => $value['product_id'],
                                'warehouse_id' => $value['warehouse_id'],
                                'area_id' => $value['area_id'],
                                'add_time' => gmtime()
                            );

                            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('goods_inventory_logs'), $logs_other, 'INSERT');
                        }
                    }
                }

                /* 修改发货单信息 */
                $invoice_no = trim($invoice_no);
                $_delivery['invoice_no'] = $invoice_no;
                $_delivery['status'] = 0; // 0，为已发货
                $query = $db->autoExecute($ecs->table('delivery_order'), $_delivery, 'UPDATE', "delivery_id = $delivery_id", 'SILENT');
                if (!$query) {
                    /* 操作失败 */
                    $links[] = array('text' => L('delivery_sn') . L('detail'), 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
                    sys_msg(L('act_false'), 1, $links);
                }

                /* 标记订单为已确认 “已发货” */
                /* 更新发货时间 */
                $order_finish = get_all_delivery_finish($order_id);
                $shipping_status = ($order_finish == 1) ? SS_SHIPPED : SS_SHIPPED_PART;
                $arr['shipping_status'] = $shipping_status;
                $arr['shipping_time'] = GMTIME_UTC; // 发货时间
                $arr['invoice_no'] = trim($order['invoice_no'] . '<br>' . $invoice_no, '<br>');

                if (empty($order['pay_time'])) {
                    $arr['pay_time'] = gmtime();
                }


                /* 发货单发货记录log */
                order_action($order['order_sn'], OS_CONFIRMED, $shipping_status, $order['pay_status'], $action_note, $_SESSION['seller_name'], 1);

                /* 如果当前订单已经全部发货 */
                if ($order_finish) {
                    /* 如果订单用户不为空，计算积分，并发给用户；发红包 */
                    if ($order['user_id'] > 0) {
                        /* 取得用户信息 */
                        $user = user_info($order['user_id']);

                        /* 计算并发放积分 */
                        $integral = integral_to_give($order);
                        /* 如果已配送子订单的赠送积分大于0   减去已配送子订单积分 */
                        if (!empty($child_order)) {
                            $integral['custom_points'] = $integral['custom_points'] - $child_order['custom_points'];
                            $integral['rank_points'] = $integral['rank_points'] - $child_order['rank_points'];
                        }
                        log_account_change($order['user_id'], 0, 0, intval($integral['rank_points']), intval($integral['custom_points']), sprintf(L('order_gift_integral'), $order['order_sn']));

                        /* 发放红包 */
                        send_order_bonus($order_id);

                        /* 发放优惠券 bylu */
                        send_order_coupons($order_id);
                    }

                    /* 发送邮件 */
                    $cfg = $_CFG['send_ship_email'];
                    if ($cfg == '1') {
                        $order['invoice_no'] = $invoice_no;
                        $tpl = get_mail_template('deliver_notice');
                        $this->assign('order', $order);
                        $this->assign('send_time', local_date($_CFG['time_format']));
                        $this->assign('shop_name', $_CFG['shop_name']);
                        $this->assign('send_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
                        $this->assign('sent_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
                        $this->assign('confirm_url', $ecs->url() . 'user.php?act=order_detail&order_id=' . $order['order_id']); //by wu
                        $this->assign('send_msg_url', $ecs->url() . 'user.php?act=message_list&order_id=' . $order['order_id']);
                        $content = $this->fetch("", 'str:' . $tpl['template_content']);
                        if (!send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html'])) {
                            $msg = L('send_mail_fail');
                        }
                    }

                    /* 如果需要，发短信 */
                    if ($GLOBALS['_CFG']['sms_order_shipped'] == '1' && $order['mobile'] != '') {

                        //阿里大鱼短信接口参数
                        if ($order['ru_id']) {
                            $shop_name = get_shop_name($order['ru_id'], 1);
                        } else {
                            $shop_name = "";
                        }

                        $user_info = get_admin_user_info($order['user_id']);

                        $smsParams = array(
                            'shop_name' => $shop_name,
                            'shopname' => $shop_name,
                            'user_name' => $user_info['user_name'],
                            'username' => $user_info['user_name'],
                            'consignee' => $order['consignee'],
                            'order_sn' => $order['order_sn'],
                            'ordersn' => $order['order_sn'],
                            'mobile_phone' => $order['mobile'],
                            'mobilephone' => $order['mobile']
                        );

                        $send_result = send_sms($order['mobile'], 'sms_order_shipped', $smsParams);

//                        if ($GLOBALS['_CFG']['sms_type'] == 0) {
//
//                            huyi_sms($smsParams, 'sms_order_shipped');
//
//                        } elseif ($GLOBALS['_CFG']['sms_type'] >= 1) {
//
//                            $result = sms_ali($smsParams, 'sms_order_shipped'); //阿里大鱼短信变量传值，发送时机传值
//
//                            if ($result) {
//                                $resp = $GLOBALS['ecs']->ali_yu($result);
//                            } else {
//                                sys_msg('阿里大鱼短信配置异常', 1);
//                            }
//                        }
                    }

                    /* 更新商品销量 */
                    get_goods_sale($order_id);

                }

                update_order($order_id, $arr);

                /* 清除缓存 */
                clear_cache_files();

                /* 操作成功 */
                $links[] = array('text' => L('09_delivery_order'), 'href' => 'order.php?act=delivery_list');
                $links[] = array('text' => L('delivery_sn') . L('detail'), 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
                sys_msg(L('act_ok'), 0, $links);
            }
        }
        /* ------------------------------------------------------ */
        //-- end一键发货
        /* ------------------------------------------------------ */

        /* 付款 */
        elseif (isset($_POST['pay'])) {
            /* 检查权限 */
            admin_priv('order_ps_edit');
            $require_note = $_CFG['order_pay_note'] == 1;
            $action = L('09_op_pay');
            $operation = 'pay';
        } /* 配货 */
        elseif (isset($_POST['prepare'])) {
            $require_note = false;
            $action = L('op_prepare');
            $operation = 'prepare';
        } /* 分单 */
        elseif (isset($_POST['ship'])) {
            /* 查询：检查权限 */
            admin_priv('order_ss_edit');

            $order_id = intval(trim($order_id));
            $action_note = trim($action_note);

            /* 查询：根据订单id查询订单信息 */
            if (!empty($order_id)) {
                $order = seller_order_info($order_id);
            } else {
                die('order does not exist');
            }

            /* 查询：根据订单是否完成 检查权限 */
            if (order_finished($order)) {
                admin_priv('order_view_finished');
            } else {
                admin_priv('order_view');
            }

            /* 查询：如果管理员属于某个办事处，检查该订单是否也属于这个办事处 */
            $sql = "SELECT agency_id FROM " . $ecs->table('admin_user') . " WHERE user_id = '$_SESSION[seller_id]'";
            $agency_id = $db->getOne($sql);
            if ($agency_id > 0) {
                if ($order['agency_id'] != $agency_id) {
                    sys_msg(L('priv_error'), 0);
                }
            }

            /* 查询：取得用户名 */
            if ($order['user_id'] > 0) {
                $user = user_info($order['user_id']);
                if (!empty($user)) {
                    $order['user_name'] = $user['user_name'];
                }
            }

            /* 查询：取得区域名 */
            $order['region'] = get_user_region_address($order['order_id']);

            /* 查询：其他处理 */
            $order['order_time'] = local_date($_CFG['time_format'], $order['add_time']);
            $order['invoice_no'] = $order['shipping_status'] == SS_UNSHIPPED || $order['shipping_status'] == SS_PREPARING ? L('ss')[SS_UNSHIPPED] : $order['invoice_no'];
            $order['pay_time'] = $order['pay_time'] > 0 ?
                local_date($_CFG['time_format'], $order['pay_time']) : L('ps')[PS_UNPAYED];
            $order['shipping_time'] = $order['shipping_time'] > 0 ?
                local_date($_CFG['time_format'], $order['shipping_time']) : L('ss')[SS_UNSHIPPED];
            $order['confirm_time'] = local_date($_CFG['time_format'], $order['confirm_time']);
            /* 查询：是否保价 */
            $order['insure_yn'] = empty($order['insure_fee']) ? 0 : 1;

            /* 查询：是否存在实体商品 */
            $exist_real_goods = exist_real_goods($order_id);

            /* 查询：取得订单商品 */
            $_goods = get_order_goods(array('order_id' => $order['order_id'], 'order_sn' => $order['order_sn']));

            $attr = $_goods['attr'];
            $goods_list = $_goods['goods_list'];
            unset($_goods);

            /* 查询：商品已发货数量 此单可发货数量 */
            if ($goods_list) {
                foreach ($goods_list as $key => $goods_value) {
                    if (!$goods_value['goods_id']) {
                        continue;
                    }

                    /* 超级礼包 */
                    if (($goods_value['extension_code'] == 'package_buy') && (count($goods_value['package_goods_list']) > 0)) {
                        $goods_list[$key]['package_goods_list'] = package_goods($goods_value['package_goods_list'], $goods_value['goods_number'], $goods_value['order_id'], $goods_value['extension_code'], $goods_value['goods_id']);

                        foreach ($goods_list[$key]['package_goods_list'] as $pg_key => $pg_value) {
                            $goods_list[$key]['package_goods_list'][$pg_key]['readonly'] = '';
                            /* 使用库存 是否缺货 */
                            if ($pg_value['storage'] <= 0 && $_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP) {
                                //$goods_list[$key]['package_goods_list'][$pg_key]['send'] = L('act_good_vacancy');
                                $goods_list[$key]['package_goods_list'][$pg_key]['readonly'] = 'readonly="readonly"';
                            } /* 将已经全部发货的商品设置为只读 */
                            elseif ($pg_value['send'] <= 0) {
                                //$goods_list[$key]['package_goods_list'][$pg_key]['send'] = L('act_good_delivery');
                                $goods_list[$key]['package_goods_list'][$pg_key]['readonly'] = 'readonly="readonly"';
                            }
                        }
                    } else {
                        $goods_list[$key]['sended'] = $goods_value['send_number'];
                        $goods_list[$key]['send'] = $goods_value['goods_number'] - $goods_value['send_number'];

                        $goods_list[$key]['readonly'] = '';
                        /* 是否缺货 */
                        if ($goods_value['storage'] <= 0 && $_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP) {
                            $goods_list[$key]['send'] = L('act_good_vacancy');
                            $goods_list[$key]['readonly'] = 'readonly="readonly"';
                        } elseif ($goods_list[$key]['send'] <= 0) {
                            $goods_list[$key]['send'] = L('act_good_delivery');
                            $goods_list[$key]['readonly'] = 'readonly="readonly"';
                        }
                    }
                }
            }
    //        var_dump($order);die;
            /* 模板赋值 */
            $this->assign('order', $order);
            $this->assign('exist_real_goods', $exist_real_goods);
            $this->assign('goods_attr', $attr);
            $this->assign('goods_list', $goods_list);
            $this->assign('order_id', $order_id); // 订单id
            $this->assign('operation', 'split'); // 订单id
            $this->assign('action_note', $action_note); // 发货操作信息

            $suppliers_list = get_suppliers_list();
            $suppliers_list_count = count($suppliers_list);
            $this->assign('suppliers_name', suppliers_list_name()); // 取供货商名
            $this->assign('suppliers_list', ($suppliers_list_count == 0 ? 0 : $suppliers_list)); // 取供货商列表
            $this->assign('menu_select', array('action' => '04_order', 'current' => '09_delivery_order'));
            /* 显示模板 */
            $this->assign('ur_here', L('order_operate') . L('op_split'));
            /* 取得订单操作记录 */
            $act_list = array();
            $sql = "SELECT * FROM " . $ecs->table('order_action') . " WHERE order_id = '$order_id' ORDER BY log_time DESC,action_id DESC";
            $res = $db->query($sql);
            foreach ($res as $key => $row) {
                $row['order_status'] = L('os')[$row['order_status']];
                $row['pay_status'] = L('ps')[$row['pay_status']];
                $row['shipping_status'] = L('ss')[$row['shipping_status']];
                $row['action_time'] = local_date($_CFG['time_format'], $row['log_time']);
                $act_list[] = $row;
            }
            $this->assign('action_list', $act_list);
            assign_query_info();
            $this->display('order.delivery_info');
            exit;
        } /* 未发货 */
        elseif (isset($_POST['unship'])) {
            /* 检查权限 */
            admin_priv('order_ss_edit');

            $require_note = $_CFG['order_unship_note'] == 1;
            $action = L('op_unship');
            $operation = 'unship';
        } /* 收货确认 */
        elseif (isset($_POST['receive'])) {
            $require_note = $_CFG['order_receive_note'] == 1;
            $action = L('op_receive');
            $operation = 'receive';
        } /* 取消 */
        elseif (isset($_POST['cancel'])) {
            $require_note = $_CFG['order_cancel_note'] == 1;
            $action = L('op_cancel');
            $operation = 'cancel';
            $show_cancel_note = true;
            $order = seller_order_info($order_id);
            if ($order['pay_status'] > 0) {
                $show_refund = true;
            }
            $anonymous = $order['user_id'] == 0;
        } /* 无效 */
        elseif (isset($_POST['invalid'])) {
            $require_note = $_CFG['order_invalid_note'] == 1;
            $action = L('op_invalid');
            $operation = 'invalid';
        } /* 售后 */
        elseif (isset($_POST['after_service'])) {
            $require_note = true;
            $action = L('op_after_service');
            $operation = 'after_service';
        } /* 退货 */
        elseif (isset($_POST['return'])) {
            $sql = "SELECT ret_id FROM " . $ecs->table('order_return') . " WHERE order_id = '" . $order_id . "'";
            $ret_id = $db->getOne($sql);
            if ($ret_id > 0) {
                $links[] = array('text' =>L('go_back'), 'href' => 'order.php?act=info&order_id=' . $order_id);
                sys_msg("该订单存在退换货商品，不能退货", 0, $links);
            } else {
                $require_note = $_CFG['order_return_note'] == 1;
                $order = seller_order_info($order_id);
                if ($order['pay_status'] > 0) {
                    $show_refund = true;
                }
                $anonymous = $order['user_id'] == 0;
                $action = L('op_return');
                $operation = 'return';
            }

            $sql = "SELECT vc_id, use_val FROM " . $GLOBALS['ecs']->table('value_card_record') . " WHERE order_id = '" . $order['order_id'] . "' LIMIT 1";
            $value_card = $GLOBALS['db']->getRow($sql);

            $paid_amount = $order['money_paid'] + $order['surplus'];
            if ($paid_amount > 0 && $order['shipping_fee'] > 0 && $paid_amount >= $order['shipping_fee']) {
                $refound_amount = $paid_amount - $order['shipping_fee'];
            } else {
                $refound_amount = $paid_amount;
            }

            $this->assign('refound_amount', $refound_amount);
            $this->assign('shipping_fee', $order['shipping_fee']);
            $this->assign('value_card', $value_card);
            $this->assign('is_whole', 1);
        } /**
         * 同意申请
         * by ecmoban模板堂 --zhuo
         */ elseif (isset($_POST['agree_apply'])) {
            $require_note = false;
            $action = L('op_confirm');
            $operation = 'agree_apply';
        } /* 退款
         * by Leah
         */
        elseif (isset($_POST['refound'])) {
            $require_note = $_CFG['order_return_note'] == 1;
            $order = seller_order_info($order_id);
            $refound_amount = empty($_REQUEST['refound_amount']) ? 0 : floatval($_REQUEST['refound_amount']);
            $return_shipping_fee = empty($_REQUEST['return_shipping_fee']) ? 0 : floatval($_REQUEST['return_shipping_fee']);

            //判断运费退款是否大于实际运费退款金额
            $is_refound_shippfee = order_refound_shipping_fee($order_id, $ret_id);
            $is_refound_shippfee_amount = $is_refound_shippfee + $return_shipping_fee;

            if (($is_refound_shippfee_amount > $order['shipping_fee']) || ($return_shipping_fee == 0 && $is_refound_shippfee > 0)) {
                $return_shipping_fee = $order['shipping_fee'] - $is_refound_shippfee;
            } elseif ($return_shipping_fee == 0 && $is_refound_shippfee == 0) {
                $return_shipping_fee = $order['shipping_fee'];
            }

            // 判断退货单订单中是否只有一个商品   如果只有一个则退订单的全部积分   如果多个则按商品积分的比例来退  by kong
            $count_goods = $db->getAll(" SELECT rec_id ,goods_id FROM " . $ecs->table("order_goods") . " WHERE order_id = '$order_id'");
            if (count($count_goods) > 1) {

                foreach ($count_goods as $k => $v) {
                    $all_goods_id[] = $v['goods_id'];
                }
                $count_integral = $db->getOne(" SELECT sum(integral) FROM" . $ecs->table("goods") . " WHERE  goods_id" . db_create_in($all_goods_id)); //获取该订单的全部可用积分
                $return_integral = $db->getOne(' SELECT g.integral FROM' . $ecs->table("goods") . " as g LEFT JOIN " . $ecs->table("order_return") . " as o on o.goods_id = g.goods_id  WHERE o.ret_id = '$ret_id'"); //退货商品的可用积分
                $count_integral = !empty($count_integral) ? $count_integral : 1;
                $return_ratio = $return_integral / $count_integral; //退还积分比例
                $return_price = (empty($order['pay_points']) ? '' : $order['pay_points']) * $return_ratio; //那比例最多返还的积分
            } else {
                $return_price = empty($order['pay_points']) ? '' : $order['pay_points']; //by kong 赋值支付积分
            }
            $goods_number = $GLOBALS['db']->getOne(" SELECT goods_number FROM " . $GLOBALS['ecs']->table("order_goods") . " WHERE rec_id = '$rec_id'"); //获取该商品的订单数量
            $return_number = $GLOBALS['db']->getOne(" SELECT return_number FROM " . $GLOBALS['ecs']->table("order_return_extend") . " WHERE ret_id = '$ret_id'"); //获取退货数量
            //*如果退货数量小于订单商品数量   则按比例返还*/
            if ($return_number < $goods_number) {
                $refound_pay_points = intval($return_price * ($return_number / $goods_number));
            } else {
                $refound_pay_points = intval($return_price);
            }
            if ($order['pay_status'] > 0) {
                $show_refund1 = true;
            }
            $anonymous = $order['user_id'] == 0;
            $action = L('op_return');
            $operation = 'refound';

            $sql = "SELECT vc_id, use_val FROM " . $GLOBALS['ecs']->table('value_card_record') . " WHERE order_id = '" . $order['order_id'] . "' LIMIT 1";
            $value_card = $GLOBALS['db']->getRow($sql);

            $return_order = return_order_info($ret_id);

            $should_return = $return_order['should_return'] - $return_order['discount_amount'];
            if ($value_card) {
                if ($value_card['use_val'] > $should_return) {
                    $value_card['use_val'] = $should_return;
                }
            }

            $paid_amount = $order['money_paid'] + $order['surplus'];
            if ($paid_amount > 0 && $paid_amount >= $order['shipping_fee']) {
                $paid_amount = $paid_amount - $order['shipping_fee'];
            }

            if ($refound_amount > $paid_amount) {
                $refound_amount = $paid_amount;
            }

            $this->assign('refound_pay_points', $refound_pay_points); // by kong  页面赋值
            $this->assign('refound_amount', $refound_amount);
            $this->assign('shipping_fee', $return_shipping_fee);
            $this->assign('value_card', $value_card);

            /* 检测订单是否只有一个退货商品的订单 start */
            $is_whole = 0;
            $is_diff = get_order_return_rec($order['order_id']);
            if ($is_diff) {
                //整单退换货
                $return_count = return_order_info_byId($order['order_id'], 0);
                if ($return_count == 1) {
                    $is_whole = 1;
                }
            }

            $this->assign('is_whole', $is_whole);
            /* 检测订单是否只有一个退货商品的订单 end */
        } /**
         * 收到退换货商品
         * by Leah
         */ elseif (isset($_POST['receive_goods'])) {
            $require_note = false;
            $action = L('op_confirm');
            $operation = 'receive_goods';
        } /**
         * 换出商品 --  快递信息
         * by Leah
         */ elseif (isset($_POST['send_submit'])) {

            $shipping_id = $_POST['shipping_name'];
            $invoice_no = $_POST['invoice_no'];
            $action_note = $_POST['action_note'];
            $sql = "SELECT shipping_name FROM " . $ecs->table('shipping') . " WHERE shipping_id =" . $shipping_id;
            $shipping_name = $db->getOne($sql);
            $require_note = false;
            $action = L('op_confirm');
            $operation = 'receive_goods';
            $db->query("UPDATE " . $ecs->table('order_return') . " SET out_shipping_name = '$shipping_id' ,out_invoice_no ='$invoice_no'" .
                "WHERE rec_id = '$rec_id'");
        } /**
         * 商品分单寄出
         * by Leah
         */ elseif (isset($_POST['swapped_out'])) {

            $require_note = false;
            $action = L('op_confirm');
            $operation = 'swapped_out';
        } /**
         * 商品分单寄出  分单
         * by Leah
         */ elseif (isset($_POST['swapped_out_single'])) {

            $require_note = false;
            $action = L('op_confirm');
            $operation = 'swapped_out_single';
        } /**
         * 完成退换货
         * by Leah
         */ elseif (isset($_POST['complete'])) {

            $require_note = false;
            $action = L('op_confirm');
            $operation = 'complete';
        } /**
         * 拒绝申请
         * by Leah
         */ elseif (isset($_POST['refuse_apply'])) {

            $require_note = true;
            $action = L('refuse_apply');
            $operation = 'refuse_apply';
        } /* 指派 */
        elseif (isset($_POST['assign'])) {
            /* 取得参数 */
            $new_agency_id = isset($_POST['agency_id']) ? intval($_POST['agency_id']) : 0;
            if ($new_agency_id == 0) {
                sys_msg(L('js_languages')['pls_select_agency']);
            }

            /* 查询订单信息 */
            $order = seller_order_info($order_id);

            /* 如果管理员属于某个办事处，检查该订单是否也属于这个办事处 */
            $sql = "SELECT agency_id FROM " . $ecs->table('admin_user') . " WHERE user_id = '$_SESSION[seller_id]'";
            $admin_agency_id = $db->getOne($sql);
            if ($admin_agency_id > 0) {
                if ($order['agency_id'] != $admin_agency_id) {
                    sys_msg(L('priv_error'));
                }
            }

            /* 修改订单相关所属的办事处 */
            if ($new_agency_id != $order['agency_id']) {
                $query_array = array('order_info', // 更改订单表的供货商ID
                    'delivery_order', // 更改订单的发货单供货商ID
                    'back_order'// 更改订单的退货单供货商ID
                );
                foreach ($query_array as $value) {
                    $db->query("UPDATE " . $ecs->table($value) . " SET agency_id = '$new_agency_id' " .
                        "WHERE order_id = '$order_id'");

                }
            }

            /* 操作成功 */
            $links[] = array('href' => 'order.php?act=list&' . list_link_postfix(), 'text' => L('02_order_list'));
            sys_msg(L('act_ok'), 0, $links);
        } /* 订单删除 */
        elseif (isset($_POST['remove'])) {
            $require_note = false;
            $operation = 'remove';
            if (!$batch) {
                /* 检查能否操作 */
                $order = seller_order_info($order_id);

                if ($order['ru_id'] != $adminru['ru_id']) {
                    sys_msg(L('order_removed'), 0, array(array('href' => 'order.php?act=list&' . list_link_postfix(), 'text' => L('return_list'))));
                    exit;
                }

                $operable_list = operable_list($order);
                if (!isset($operable_list['remove'])) {
                    die('Hacking attempt');
                }

                $return_order = return_order_info(0, '', $order['order_id']);
                if ($return_order) {
                    sys_msg(sprintf(L('order_remove_failure'), $order['order_sn']), 0, array(array('href' => 'order.php?act=list&' . list_link_postfix(), 'text' => L('return_list'))));
                    exit;
                }

                /* 删除订单 */
                $db->query("DELETE FROM " . $ecs->table('order_info') . " WHERE order_id = '$order_id'");
                $db->query("DELETE FROM " . $ecs->table('order_goods') . " WHERE order_id = '$order_id'");
                $db->query("DELETE FROM " . $ecs->table('order_action') . " WHERE order_id = '$order_id'");
                $action_array = array('delivery', 'back');
                del_delivery($order_id, $action_array);

                /* todo 记录日志 */
                admin_log($order['order_sn'], 'remove', 'order');

                /* 返回 */
                sys_msg(L('order_removed'), 0, array(array('href' => 'order.php?act=list&' . list_link_postfix(), 'text' => L('return_list'))));
            }
        } /* 发货单删除 */
        elseif (isset($_REQUEST['remove_invoice'])) {
            // 删除发货单
            $delivery_id = isset($_REQUEST['delivery_id']) ? $_REQUEST['delivery_id'] : $_REQUEST['checkboxes'];
            $delivery_id = is_array($delivery_id) ? $delivery_id : array($delivery_id);

            foreach ($delivery_id as $value_is) {
                $value_is = intval(trim($value_is));

                // 查询：发货单信息
                $delivery_order = delivery_order_info($value_is);

                // 如果status不是退货
                if ($delivery_order['status'] != 1) {
                    /* 处理退货 */
                    delivery_return_goods($value_is, $delivery_order);
                }

                // 如果status是已发货并且发货单号不为空
                if ($delivery_order['status'] == 0 && $delivery_order['invoice_no'] != '') {
                    /* 更新：删除订单中的发货单号 */
                    del_order_invoice_no($delivery_order['order_id'], $delivery_order['invoice_no']);
                }

                // 更新：删除发货单
                $sql = "DELETE FROM " . $ecs->table('delivery_order') . " WHERE delivery_id = '$value_is'";
                $db->query($sql);
            }

            /* 返回 */
            sys_msg(L('tips_delivery_del'), 0, array(array('href' => 'order.php?act=delivery_list', 'text' => L('return_list'))));
        } /* 退货单删除 */
        elseif (isset($_REQUEST['remove_back'])) {
            $back_id = isset($_REQUEST['back_id']) ? $_REQUEST['back_id'] : $_POST['checkboxes'];
            /* 删除退货单 */
            if (is_array($back_id)) {
                foreach ($back_id as $value_is) {
                    $sql = "DELETE FROM " . $ecs->table('back_order') . " WHERE back_id = '$value_is'";
                    $db->query($sql);
                }
            } else {
                $sql = "DELETE FROM " . $ecs->table('back_order') . " WHERE back_id = '$back_id'";
                $db->query($sql);
            }
            /* 返回 */
            sys_msg(L('tips_back_del'), 0, array(array('href' => 'order.php?act=back_list', 'text' => L('return_list'))));
        } /* 批量打印订单 */
        elseif (isset($_POST['print'])) {
            if (empty($_POST['order_id'])) {
                sys_msg(L('pls_select_order'));
            }

            //快递鸟、电子面单 start
            $url = 'tp_api.php?act=order_print&order_sn=' . $_POST['order_id'];
            ecs_header("Location: $url\n");
            exit;
            //快递鸟、电子面单 end

            /* 赋值公用信息 */
            $this->assign('print_time', local_date($_CFG['time_format']));
            $this->assign('action_user', $_SESSION['seller_name']);

            $html = '';
            $order_sn_list = explode(',', $_POST['order_id']);
            foreach ($order_sn_list as $order_sn) {
                if ($order_sn) {

                    /* 取得订单信息 */
                    $order = seller_order_info(0, $order_sn);
                    if (empty($order)) {
                        continue;
                    }

                    /* 根据订单是否完成检查权限 */
                    if (order_finished($order)) {
                        if (!admin_priv('order_view_finished', '', false)) {
                            continue;
                        }
                    } else {
                        if (!admin_priv('order_view', '', false)) {
                            continue;
                        }
                    }

                    /* 如果管理员属于某个办事处，检查该订单是否也属于这个办事处 */
                    $sql = "SELECT agency_id FROM " . $ecs->table('admin_user') . " WHERE user_id = '$_SESSION[seller_id]'";
                    $agency_id = $db->getOne($sql);
                    if ($agency_id > 0) {
                        if ($order['agency_id'] != $agency_id) {
                            continue;
                        }
                    }

                    /* 取得用户名 */
                    if ($order['user_id'] > 0) {
                        $user = user_info($order['user_id']);
                        if (!empty($user)) {
                            $order['user_name'] = $user['user_name'];
                        }
                    }

                    /* 取得区域名 */
                    $order['region'] = get_user_region_address($order['order_id']);

                    /* 其他处理 */
                    $order['order_time'] = local_date($_CFG['time_format'], $order['add_time']);
                    $order['pay_time'] = $order['pay_time'] > 0 ?
                        local_date($_CFG['time_format'], $order['pay_time']) : L('ps')[PS_UNPAYED];
                    $order['shipping_time'] = $order['shipping_time'] > 0 ?
                        local_date($_CFG['time_format'], $order['shipping_time']) : L('ss')[SS_UNSHIPPED];
                    $order['status'] = L('os')[$order['order_status']] . ',' . L('ps')[$order['pay_status']] . ',' . L('ss')[$order['shipping_status']];
                    $order['invoice_no'] = $order['shipping_status'] == SS_UNSHIPPED || $order['shipping_status'] == SS_PREPARING ? L('ss')[SS_UNSHIPPED] : $order['invoice_no'];

                    /* 此订单的发货备注(此订单的最后一条操作记录) */
                    $sql = "SELECT action_note FROM " . $ecs->table('order_action') .
                        " WHERE order_id = '$order[order_id]' AND shipping_status = 1 ORDER BY log_time DESC";
                    $order['invoice_note'] = $db->getOne($sql);

                    /* 参数赋值：订单 */
                    $this->assign('order', $order);

                    /* 取得订单商品 */
                    $goods_list = array();
                    $goods_attr = array();
                    $sql = "SELECT o.*, c.measure_unit, g.goods_number AS storage, o.goods_attr, IFNULL(b.brand_name, '') AS brand_name, g.bar_code " .
                        "FROM " . $ecs->table('order_goods') . " AS o " .
                        "LEFT JOIN " . $ecs->table('goods') . " AS g ON o.goods_id = g.goods_id " .
                        "LEFT JOIN " . $ecs->table('brand') . " AS b ON g.brand_id = b.brand_id " .
                        'LEFT JOIN ' . $GLOBALS['ecs']->table('category') . ' AS c ON g.cat_id = c.cat_id ' .
                        "WHERE o.order_id = '$order[order_id]' ";
                    $res = $db->query($sql);
//                    while ($row = $db->fetchRow($res)) {
                    foreach ($res as $key => $row) {
                        $products = get_warehouse_id_attr_number($row['goods_id'], $row['goods_attr_id'], $row['ru_id'], $row['warehouse_id'], $row['area_id'], $row['model_attr']);
                        if ($row['product_id']) {
                            $row['bar_code'] = $products['bar_code'];
                        }

                        /* 虚拟商品支持 */
                        if ($row['is_real'] == 0) {
                            /* 取得语言项 */
                            $filename = ROOT_PATH . 'plugins/' . $row['extension_code'] . '/languages/common_' . $_CFG['lang'] . '.php';
                            if (file_exists($filename)) {
                                include_once($filename);

                                $lang_extension_code = L($row['extension_code'] . '_link');
                                if (!empty($lang_extension_code)) {
                                    $row['goods_name'] = $row['goods_name'] . sprintf($lang_extension_code, $row['goods_id'], $order['order_sn']);
                                }
                            }
                        }

                        $row['formated_subtotal'] = price_format($row['goods_price'] * $row['goods_number']);
                        $row['formated_goods_price'] = price_format($row['goods_price']);

                        $goods_attr[] = explode(' ', trim($row['goods_attr'])); //将商品属性拆分为一个数组
                        $goods_list[] = $row;
                    }

                    $attr = array();
                    $arr = array();
                    foreach ($goods_attr AS $index => $array_val) {
                        foreach ($array_val AS $value) {
                            $arr = explode(':', $value); //以 : 号将属性拆开
                            $attr[$index][] = @array('name' => $arr[0], 'value' => $arr[1]);
                        }
                    }

                    /* 取得商家信息 by  kong */
                    $sql = "select shop_name,country,province,city,shop_address,kf_tel from " . $ecs->table('seller_shopinfo') . " where ru_id='" . $order['ru_id'] . "'";
                    $store = $db->getRow($sql);

                    $store['shop_name'] = get_shop_name($order['ru_id'], 1);

                    $sql = "SELECT domain_name FROM " . $ecs->table("seller_domain") . " WHERE ru_id = '" . $order['ru_id'] . "' AND  is_enable = 1"; //获取商家域名
                    $domain_name = $db->getOne($sql);
                    $this->assign('domain_name', $domain_name);

                    $this->assign('shop_name', $store['shop_name']);
                    $this->assign('shop_url', $ecs->seller_url());
                    $this->assign('shop_address', $store['shop_address']);
                    $this->assign('service_phone', $store['kf_tel']);

                    $this->assign('goods_attr', $attr);
                    $this->assign('goods_list', $goods_list);
                    $this->template_dir = '../' . DATA_DIR;
                    $html .= $this->fetch('order_print.html') .
                        '<div style="PAGE-BREAK-AFTER:always"></div>';
                }
            }
            echo $html;
            exit;
        } /* 去发货 */
        elseif (isset($_POST['to_delivery'])) {
            $url = url('order/delivery_list', ['order_sn'=>$_REQUEST['order_sn']]);;
            ecs_header("Location: $url\n");
            exit;
        } /* 批量发货 by wu */
        elseif (isset($_REQUEST['batch_delivery'])) {
            /* 检查权限 */
            admin_priv('delivery_view');
            /* 定义当前时间 */
            define('GMTIME_UTC', gmtime()); // 获取 UTC 时间戳

            $delivery_id = isset($_REQUEST['delivery_id']) ? $_REQUEST['delivery_id'] : $_REQUEST['checkboxes'];
            $delivery_id = is_array($delivery_id) ? $delivery_id : array($delivery_id);
            $invoice_nos = isset($_REQUEST['invoice_no']) ? $_REQUEST['invoice_no'] : array();
            $action_note = isset($_REQUEST['action_note']) ? trim($_REQUEST['action_note']) : '';

            foreach ($delivery_id as $value_is) {
                $msg = '';
                $value_is = intval(trim($value_is));
                $delivery_info = get_table_date('delivery_order', "delivery_id='$value_is'", array('order_id', 'status'));

                //跳过已发货、退货订单
                if ($delivery_info['status'] != 2 || !isset($invoice_nos[$value_is])) {
                    continue;
                }

                /* 取得参数 */
                $delivery = array();
                $order_id = $delivery_info['order_id'];        // 订单id
                $delivery_id = $value_is;        // 发货单id
                $delivery['invoice_no'] = $invoice_nos[$value_is];
                $action_note = $action_note;

                /* 根据发货单id查询发货单信息 */
                if (!empty($delivery_id)) {
                    $delivery_order = delivery_order_info($delivery_id);
                } else {
                    die('order does not exist');
                }

                /* 查询订单信息 */
                $order = seller_order_info($order_id);
                /* 检查此单发货商品库存缺货情况  ecmoban模板堂 --zhuo start 下单减库存 */
                $delivery_stock_sql = "SELECT DG.rec_id AS dg_rec_id, OG.rec_id AS og_rec_id, G.model_attr, G.model_inventory, DG.goods_id, DG.delivery_id, DG.is_real, DG.send_number AS sums, G.goods_number AS storage, G.goods_name, DG.send_number," .
                    " OG.goods_attr_id, OG.warehouse_id, OG.area_id, OG.ru_id, OG.order_id, OG.product_id FROM " . $GLOBALS['ecs']->table('delivery_goods') . " AS DG, " .
                    $GLOBALS['ecs']->table('goods') . " AS G, " .
                    $GLOBALS['ecs']->table('delivery_order') . " AS D, " .
                    $GLOBALS['ecs']->table('order_goods') . " AS OG " .
                    " WHERE DG.goods_id = G.goods_id AND DG.delivery_id = D.delivery_id AND D.order_id = OG.order_id AND DG.goods_sn = OG.goods_sn AND DG.product_id = OG.product_id AND DG.delivery_id = '$delivery_id' GROUP BY OG.rec_id ";

                $delivery_stock_result = $GLOBALS['db']->getAll($delivery_stock_sql);

                $virtual_goods = array();
                for ($i = 0; $i < count($delivery_stock_result); $i++) {
                    if ($delivery_stock_result[$i]['model_attr'] == 1) {
                        $table_products = "products_warehouse";
                        $type_files = " and warehouse_id = '" . $delivery_stock_result[$i]['warehouse_id'] . "'";
                    } elseif ($delivery_stock_result[$i]['model_attr'] == 2) {
                        $table_products = "products_area";
                        $type_files = " and area_id = '" . $delivery_stock_result[$i]['area_id'] . "'";
                    } else {
                        $table_products = "products";
                        $type_files = "";
                    }

                    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table($table_products) . " WHERE goods_id = '" . $delivery_stock_result[$i]['goods_id'] . "'" . $type_files . " LIMIT 0, 1";
                    $prod = $GLOBALS['db']->getRow($sql);

                    /* 如果商品存在规格就查询规格，如果不存在规格按商品库存查询 */
                    if (empty($prod)) {
                        if ($delivery_stock_result[$i]['model_inventory'] == 1) {
                            $delivery_stock_result[$i]['storage'] = get_warehouse_area_goods($delivery_stock_result[$i]['warehouse_id'], $delivery_stock_result[$i]['goods_id'], 'warehouse_goods');
                        } elseif ($delivery_stock_result[$i]['model_inventory'] == 2) {
                            $delivery_stock_result[$i]['storage'] = get_warehouse_area_goods($delivery_stock_result[$i]['area_id'], $delivery_stock_result[$i]['goods_id'], 'warehouse_area_goods');
                        }
                    } else {
                        $products = get_warehouse_id_attr_number($delivery_stock_result[$i]['goods_id'], $delivery_stock_result[$i]['goods_attr_id'], $delivery_stock_result[$i]['ru_id'], $delivery_stock_result[$i]['warehouse_id'], $delivery_stock_result[$i]['area_id'], $delivery_stock_result[$i]['model_attr']);
                        $delivery_stock_result[$i]['storage'] = $products['product_number'];
                    }

                    if (($delivery_stock_result[$i]['sums'] > $delivery_stock_result[$i]['storage'] || $delivery_stock_result[$i]['storage'] <= 0) && (($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP) || ($_CFG['use_storage'] == '0' && $delivery_stock_result[$i]['is_real'] == 0))) {
                        /* 操作失败 */
                        $links[] = array('text' => L('order_info'), 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
                        //sys_msg(sprintf(L('act_good_vacancy'), $value['goods_name']), 1, $links);
                        break;
                    }

                    /* 虚拟商品列表 virtual_card */
                    if ($delivery_stock_result[$i]['is_real'] == 0) {
                        $virtual_goods[] = array(
                            'goods_id' => $delivery_stock_result[$i]['goods_id'],
                            'goods_name' => $delivery_stock_result[$i]['goods_name'],
                            'num' => $delivery_stock_result[$i]['send_number']
                        );
                    }
                }
                //ecmoban模板堂 --zhuo end 下单减库存

                /* 发货 */
                /* 处理虚拟卡 商品（虚货） */
                if ($virtual_goods && is_array($virtual_goods) && count($virtual_goods) > 0) {
                    foreach ($virtual_goods as $virtual_value) {
                        virtual_card_shipping($virtual_value, $order['order_sn'], $msg, 'split');
                    }

                    //虚拟卡缺货
                    if (!empty($msg)) {
                        continue;
                    }
                }

                /* 如果使用库存，且发货时减库存，则修改库存 */
                if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP) {

                    foreach ($delivery_stock_result as $value) {

                        /* 商品（实货）、超级礼包（实货） ecmoban模板堂 --zhuo */
                        if ($value['is_real'] != 0) {
                            //（货品）
                            if (!empty($value['product_id'])) {
                                if ($value['model_attr'] == 1) {
                                    $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products_warehouse') . "
                                                        SET product_number = product_number - " . $value['sums'] . "
                                                        WHERE product_id = " . $value['product_id'];
                                } elseif ($value['model_attr'] == 2) {
                                    $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products_area') . "
                                                        SET product_number = product_number - " . $value['sums'] . "
                                                        WHERE product_id = " . $value['product_id'];
                                } else {
                                    $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products') . "
                                                        SET product_number = product_number - " . $value['sums'] . "
                                                        WHERE product_id = " . $value['product_id'];
                                }
                            } else {
                                if ($value['model_inventory'] == 1) {
                                    $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('warehouse_goods') . "
                                                        SET region_number = region_number - " . $value['sums'] . "
                                                        WHERE goods_id = " . $value['goods_id'] . " AND region_id = " . $value['warehouse_id'];
                                } elseif ($value['model_inventory'] == 2) {
                                    $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('warehouse_area_goods') . "
                                                        SET region_number = region_number - " . $value['sums'] . "
                                                        WHERE goods_id = " . $value['goods_id'] . " AND region_id = " . $value['area_id'];
                                } else {
                                    $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('goods') . "
                                                        SET goods_number = goods_number - " . $value['sums'] . "
                                                        WHERE goods_id = " . $value['goods_id'];
                                }
                            }

                            $GLOBALS['db']->query($minus_stock_sql, 'SILENT');

                            //库存日志
                            $logs_other = array(
                                'goods_id' => $value['goods_id'],
                                'order_id' => $value['order_id'],
                                'use_storage' => $_CFG['stock_dec_time'],
                                'admin_id' => $_SESSION['admin_id'],
                                'number' => "- " . $value['sums'],
                                'model_inventory' => $value['model_inventory'],
                                'model_attr' => $value['model_attr'],
                                'product_id' => $value['product_id'],
                                'warehouse_id' => $value['warehouse_id'],
                                'area_id' => $value['area_id'],
                                'add_time' => gmtime()
                            );

                            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('goods_inventory_logs'), $logs_other, 'INSERT');
                        }
                    }
                }

                /* 修改发货单信息 */
                $invoice_no = str_replace(',', '<br>', $delivery['invoice_no']);
                $invoice_no = trim($invoice_no, '<br>');
                $_delivery['invoice_no'] = $invoice_no;
                $_delivery['status'] = 0; // 0，为已发货
                $query = $db->autoExecute($ecs->table('delivery_order'), $_delivery, 'UPDATE', "delivery_id = $delivery_id", 'SILENT');
                if (!$query) {
                    /* 操作失败 */
                    $links[] = array('text' => L('delivery_sn') . L('detail'), 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
                    //sys_msg(L('act_false'), 1, $links);
                    continue;
                }

                /* 标记订单为已确认 “已发货” */
                /* 更新发货时间 */
                $order_finish = get_all_delivery_finish($order_id);
                $shipping_status = ($order_finish == 1) ? SS_SHIPPED : SS_SHIPPED_PART;
                $arr['shipping_status'] = $shipping_status;
                $arr['shipping_time'] = GMTIME_UTC; // 发货时间
                $arr['invoice_no'] = trim($order['invoice_no'] . '<br>' . $invoice_no, '<br>');
                update_order($order_id, $arr);

                /* 发货单发货记录log */
                order_action($order['order_sn'], OS_CONFIRMED, $shipping_status, $order['pay_status'], $action_note, $_SESSION['admin_name'], 1);

                /* 如果当前订单已经全部发货 */
                if ($order_finish) {
                    /* 如果订单用户不为空，计算积分，并发给用户；发红包 */
                    if ($order['user_id'] > 0) {
                        /* 取得用户信息 */
                        $user = user_info($order['user_id']);

                        /* 计算并发放积分 */
                        $integral = integral_to_give($order);
                        /* 如果已配送子订单的赠送积分大于0   减去已配送子订单积分 */
                        if (!empty($child_order)) {
                            $integral['custom_points'] = $integral['custom_points'] - $child_order['custom_points'];
                            $integral['rank_points'] = $integral['rank_points'] - $child_order['rank_points'];
                        }
                        log_account_change($order['user_id'], 0, 0, intval($integral['rank_points']), intval($integral['custom_points']), sprintf(L('order_gift_integral'), $order['order_sn']));

                        /* 发放红包 */
                        send_order_bonus($order_id);

                        /* 发放优惠券 bylu */
                        send_order_coupons($order_id);
                    }

                    /* 发送邮件 */
                    $cfg = $_CFG['send_ship_email'];
                    if ($cfg == '1') {
                        $order['invoice_no'] = $invoice_no;
                        $tpl = get_mail_template('deliver_notice');
                        $this->assign('order', $order);
                        $this->assign('send_time', local_date($_CFG['time_format']));
                        $this->assign('shop_name', $_CFG['shop_name']);
                        $this->assign('send_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
                        $this->assign('sent_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
                        //$this->assign('confirm_url', $ecs->url() . 'receive.php?id=' . $order['order_id'] . '&con=' . rawurlencode($order['consignee']));
                        $this->assign('confirm_url', $ecs->url() . 'user.php?act=order_detail&order_id=' . $order['order_id']); //by wu
                        $this->assign('send_msg_url', $ecs->url() . 'user.php?act=message_list&order_id=' . $order['order_id']);
                        $content = $this->fetch('str:' . $tpl['template_content']);
                        if (!send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html'])) {
                            $msg = L('send_mail_fail');
                        }
                    }

                    /* 如果需要，发短信 */
                    if ($GLOBALS['_CFG']['sms_order_shipped'] == '1' && $order['mobile'] != '') {

                        //短信接口参数
                        if ($order['ru_id']) {
                            $shop_name = get_shop_name($order['ru_id'], 1);
                        } else {
                            $shop_name = "";
                        }

                        $user_info = get_admin_user_info($order['user_id']);

                        $smsParams = array(
                            'shop_name' => $shop_name,
                            'shopname' => $shop_name,
                            'user_name' => $user_info['user_name'],
                            'username' => $user_info['user_name'],
                            'consignee' => $order['consignee'],
                            'order_sn' => $order['order_sn'],
                            'ordersn' => $order['order_sn'],
                            'mobile_phone' => $order['mobile'],
                            'mobilephone' => $order['mobile']
                        );

                        $send_result = send_sms($order['mobile'], 'sms_order_shipped', $smsParams);

//                        if ($GLOBALS['_CFG']['sms_type'] == 0) {
//
//                            huyi_sms($smsParams, 'sms_order_shipped');
//                        } elseif ($GLOBALS['_CFG']['sms_type'] >= 1) {
//
//                            $result = sms_ali($smsParams, 'sms_order_shipped'); //阿里大鱼短信变量传值，发送时机传值
//
//                            if ($result) {
//                                $resp = $GLOBALS['ecs']->ali_yu($result);
//                            } else {
//                                //sys_msg('阿里大鱼短信配置异常', 1);
//                                continue;
//                            }
//                        }
                    }

                    /* 更新商品销量 */
                    get_goods_sale($order_id);
                }

                /* 清除缓存 */
                clear_cache_files();

                /* 操作成功 */
                $links[] = array('text' => L('09_delivery_order'), 'href' => 'order.php?act=delivery_list');
                $links[] = array('text' => L('delivery_sn') . L('detail'), 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
                //sys_msg(L('act_ok'), 0, $links);
                continue;
            }

            /* 返回 */
            sys_msg('批量发货成功', 0, array(array('href' => 'order.php?act=delivery_list', 'text' => L('return_list'))));
        }

        /*  @bylu 判断当前退款订单是否为白条支付订单(白条支付订单退款只能退到白条额度) start */
        $sql = "select log_id from {$ecs->table('baitiao_log')} where order_id" . db_create_in(explode(',', $order_id));
        $baitiao = $db->getOne($sql);
        if ($baitiao) {
            $this->assign('is_baitiao', $baitiao); // 是否要求填写备注
        }
        /*  @bylu  end */


        /* 直接处理还是跳到详细页面 ecmoban模板堂 --zhuo ($require_note && $action_note == '')*/
        if ($require_note || isset($show_invoice_no) || isset($show_refund)) {

            /* 模板赋值 */
            $this->assign('require_note', $require_note); // 是否要求填写备注
            $this->assign('action_note', $action_note);   // 备注
            $this->assign('show_cancel_note', isset($show_cancel_note)); // 是否显示取消原因
            $this->assign('show_invoice_no', isset($show_invoice_no)); // 是否显示发货单号
            $this->assign('show_refund', isset($show_refund)); // 是否显示退款
            $this->assign('show_refund1', isset($show_refund1)); // 是否显示退款 // by Leah
            $this->assign('anonymous', isset($anonymous) ? $anonymous : true); // 是否匿名
            $this->assign('order_id', $order_id); // 订单id
            $this->assign('rec_id', $rec_id); // 订单商品id    //by Leah
            $this->assign('ret_id', $ret_id); // 订单商品id   // by Leah
            $this->assign('batch', $batch);   // 是否批处理
            $this->assign('operation', $operation); // 操作
            $this->assign('menu_select', array('action' => '04_order', 'current' => '12_back_apply'));
            /* 显示模板 */
            $this->assign('ur_here', L('order_operate') . $action);
            assign_query_info();
            $this->display('order_operate');
        } else {
            /* 直接处理 */
            if (!$batch) {
                // by　Leah S
                if ($_REQUEST['ret_id']) {
                    $param = [];
                    $param['order_id'] = $order_id;
                    $param['operation'] = $operation;
                    $param['action_note'] = urlencode($action_note);
                    $param['rec_id'] = urlencode($rec_id);
                    $param['ret_id'] = urlencode($ret_id);
                    $this->redirect(url('order/operate_post', $param));
                    exit;
                } else {

                    $param = [];
                    $param['order_id'] = $order_id;
                    $param['operation'] = $operation;
                    $param['action_note'] = urlencode($action_note);

                    /* 一个订单 */
                    $this->redirect(url('order/operate_post', $param));
//                    ecs_header("Location: order.php?act=operate_post&order_id=" . $order_id .
//                        "&operation=" . $operation . "&action_note=" . urlencode($action_note) . "\n");
                    exit;
                }
                //by Leah E
            } else {
                $param = [];
                $param['order_id'] = $order_id;
                $param['operation'] = $operation;
                $param['action_note'] = urlencode($action_note);
                $this->redirect(url('order/batch_operate_post', $param));
                exit;
            }
        }
    }

    public function actionBatchOperatePost() {
        global $ecs, $db, $_CFG;
            /* 检查权限 */
        admin_priv('order_os_edit');

        $this->assign('menu_select',array('action' => '04_order', 'current' => '02_order_list'));

            /* 取得参数 */
        $order_id   = $_REQUEST['order_id'];        // 订单id（逗号格开的多个订单id）
        $operation  = $_REQUEST['operation'];       // 订单操作
        $action_note= $_REQUEST['action_note'];     // 操作备注

        $order_id_list = explode(',', $order_id);

            /* 初始化处理的订单sn */
        $sn_list = array();
        $sn_not_list = array();

           /* 确认 */
        if ('confirm' == $operation)
        {
            foreach($order_id_list as $id_order)
            {
            $sql = "SELECT * FROM " . $ecs->table('order_info') .
            " WHERE order_sn = '$id_order'" .
            " AND order_status = '" . OS_UNCONFIRMED . "'";
            $order = $db->getRow($sql);

            if($order)
            {
                    /* 检查能否操作 */
                $operable_list = operable_list($order);
                if (!isset($operable_list[$operation]))
                {
                    $sn_not_list[] = $id_order;
                    continue;
                }

                if($order['order_status'] == OS_RETURNED || $order['order_status'] == OS_RETURNED_PART){
                    continue;
                }

                $order_id = $order['order_id'];

                /* 标记订单为已确认 */
                update_order($order_id, array('order_status' => OS_CONFIRMED, 'confirm_time' => gmtime()));
                update_order_amount($order_id);

                /* 记录log */
                order_action($order['order_sn'], OS_CONFIRMED, SS_UNSHIPPED, PS_UNPAYED, $action_note,$_SESSION['seller_name']);

                /* 发送邮件 */
                if ($_CFG['send_confirm_email'] == '1')
                {
                    $tpl = get_mail_template('order_confirm');
                    $order['formated_add_time'] = local_date($GLOBALS['_CFG']['time_format'], $order['add_time']);
                    $this->assign('order', $order);
                    $this->assign('shop_name', $_CFG['shop_name']);
                    $this->assign('send_date', local_date($_CFG['date_format']));
                    $this->assign('sent_date', local_date($_CFG['date_format']));
                    $content = $this->fetch('str:' . $tpl['template_content']);
                    send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html']);
                }

                $sn_list[] = $order['order_sn'];
                }
                else
                    {
                        $sn_not_list[] = $id_order;
                    }
                }

                $sn_str = L('confirm_order');
                $this->assign('ur_here',  L('order_operate') . L('op_confirm'));
            }
            /* 无效 */
            elseif ('invalid' == $operation)
            {
                foreach($order_id_list as $id_order)
                {
                    $sql = "SELECT * FROM " . $ecs->table('order_info') .
                        " WHERE order_sn = $id_order" . order_query_sql('unpay_unship');

                    $order = $db->getRow($sql);

                    /*判断门店订单，获取门店id by kong */
                    $store_order_id = get_store_id($order['order_id']);
                    $store_id = ($store_order_id > 0) ? $store_order_id : 0;

                    if($order)
                    {
                        /* 检查能否操作 */
                        $operable_list = operable_list($order);
                        if (!isset($operable_list[$operation]))
                        {
                            $sn_not_list[] = $id_order;
                            continue;
                        }

                        $order_id = $order['order_id'];

                        /* 标记订单为“无效” */
                        update_order($order_id, array('order_status' => OS_INVALID));

                        /* 记录log */
                        order_action($order['order_sn'], OS_INVALID, SS_UNSHIPPED, PS_UNPAYED, $action_note,$_SESSION['seller_name']);

                        /* 如果使用库存，且下订单时减库存，则增加库存 */
                        if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_PLACE)
                        {
                            change_order_goods_storage($order_id, false, SDT_PLACE, 2, $_SESSION['seller_id'],$store_id);
                        }

                        /* 发送邮件 */
                        if ($_CFG['send_invalid_email'] == '1')
                        {
                            $tpl = get_mail_template('order_invalid');
                            $this->assign('order', $order);
                            $this->assign('shop_name', $_CFG['shop_name']);
                            $this->assign('send_date', local_date($_CFG['date_format']));
                            $this->assign('sent_date', local_date($_CFG['date_format']));
                            $content = $this->fetch('str:' . $tpl['template_content']);
                            send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html']);
                        }

                        /* 退还用户余额、积分、红包 */
                        return_user_surplus_integral_bonus($order);

                        $sn_list[] = $order['order_sn'];
                    }
                    else
                    {
                        $sn_not_list[] = $id_order;
                    }
                }

                $sn_str = L('invalid_order');
            }
            elseif ('cancel' == $operation)
            {
                foreach($order_id_list as $id_order)
                {
                    $sql = "SELECT * FROM " . $ecs->table('order_info') .
                        " WHERE order_sn = $id_order" . order_query_sql('unpay_unship');

                    $order = $db->getRow($sql);

                    /*判断门店订单，获取门店id by kong */

                    $store_order_id = get_store_id($order['order_id']);
                    $store_id = ($store_order_id > 0) ? $store_order_id : 0;
                    if($order)
                    {
                        /* 检查能否操作 */
                        $operable_list = operable_list($order);
                        if (!isset($operable_list[$operation]))
                        {
                            $sn_not_list[] = $id_order;
                            continue;
                        }

                        $order_id = $order['order_id'];

                        /* 标记订单为“取消”，记录取消原因 */
                        $cancel_note = trim($_REQUEST['cancel_note']);
                        update_order($order_id, array('order_status' => OS_CANCELED, 'to_buyer' => $cancel_note));

                        /* 记录log */
                        order_action($order['order_sn'], OS_CANCELED, $order['shipping_status'], PS_UNPAYED, $action_note,$_SESSION['seller_name']);

                        /* 如果使用库存，且下订单时减库存，则增加库存 */
                        if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_PLACE)
                        {
                            change_order_goods_storage($order_id, false, SDT_PLACE, 3, $_SESSION['seller_id'],$store_id);
                        }

                        /* 发送邮件 */
                        if ($_CFG['send_cancel_email'] == '1')
                        {
                            $tpl = get_mail_template('order_cancel');
                            $this->assign('order', $order);
                            $this->assign('shop_name', $_CFG['shop_name']);
                            $this->assign('send_date', local_date($_CFG['date_format']));
                            $this->assign('sent_date', local_date($_CFG['date_format']));
                            $content = $this->fetch('str:' . $tpl['template_content']);
                            send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html']);
                        }

                        /* 退还用户余额、积分、红包 */
                        return_user_surplus_integral_bonus($order);

                        $sn_list[] = $order['order_sn'];
                    }
                    else
                    {
                        $sn_not_list[] = $id_order;
                    }
                }

                $sn_str = L('cancel_order');
            }
            elseif ('remove' == $operation)
            {
                foreach ($order_id_list as $id_order)
                {
                    /* 检查能否操作 */
                    $order = seller_order_info('', $id_order);
                    $operable_list = operable_list($order);
                    if (!isset($operable_list['remove']))
                    {
                        $sn_not_list[] = $id_order;
                        continue;
                    }

                    $return_order = return_order_info(0, '', $order['order_id']);
                    if($return_order){
                        sys_msg(sprintf(L('order_remove_failure'), $order['order_sn']), 0, array(array('href'=>'order.php?act=list&' . list_link_postfix(), 'text' => L('return_list'))));
                        exit;
                    }

                    /* 删除订单 */
                    $db->query("DELETE FROM ".$ecs->table('order_info'). " WHERE order_id = '$order[order_id]'");
                    $db->query("DELETE FROM ".$ecs->table('order_goods'). " WHERE order_id = '$order[order_id]'");
                    $db->query("DELETE FROM ".$ecs->table('order_action'). " WHERE order_id = '$order[order_id]'");
                    $action_array = array('delivery', 'back');
                    del_delivery($order['order_id'], $action_array);

                    /* todo 记录日志 */
                    admin_log($order['order_sn'], 'remove', 'order');

                    $sn_list[] = $order['order_sn'];
                }

                $sn_str = L('remove_order');
                $this->assign('ur_here', L('order_operate') . L('remove'));
            }
            else
            {
                die('invalid params');
            }

            /* 取得备注信息 */
        //    $action_note = $_REQUEST['action_note'];

            if(empty($sn_not_list))
            {
                $sn_list = empty($sn_list) ? '' : L('updated_order') . join($sn_list, ',');
                $msg = $sn_list;
                $links[] = array('text' => L('return_list'), 'href' => 'order.php?act=list&' . list_link_postfix());
                sys_msg($msg, 0, $links);
            }
            else
            {
                $order_list_no_fail = array();
                $sql = "SELECT * FROM " . $ecs->table('order_info') .
                    " WHERE order_sn " . db_create_in($sn_not_list);
                $res = $db->query($sql);
//                while($row = $db->fetchRow($res))
                foreach ($res as $key => $row)
                {
                    $order_list_no_fail[$row['order_id']]['order_id'] = $row['order_id'];
                    $order_list_no_fail[$row['order_id']]['order_sn'] = $row['order_sn'];
                    $order_list_no_fail[$row['order_id']]['order_status'] = $row['order_status'];
                    $order_list_no_fail[$row['order_id']]['shipping_status'] = $row['shipping_status'];
                    $order_list_no_fail[$row['order_id']]['pay_status'] = $row['pay_status'];

                    $order_list_fail = '';
                    foreach(operable_list($row) as $key => $value)
                    {
                        if($key != $operation)
                        {
                            $order_list_fail .= L('op_' . $key) . ',';
                        }
                    }
                    $order_list_no_fail[$row['order_id']]['operable'] = $order_list_fail;
                }

            /* 模板赋值 */
            $this->assign('order_info', $sn_str);
            $this->assign('action_link', array('href' => 'order.php?act=list', 'text' => L('02_order_list'), 'class' => 'icon-reply'));
            $this->assign('order_list',   $order_list_no_fail);

            /* 显示模板 */
            assign_query_info();
            $this->display('order_operate_info.dwt');
        }
    }

    public function actionOperatePost() {
        /* 检查权限 */
        admin_priv('order_os_edit');

        global $ecs, $db, $_CFG;

        /* 取得参数 */
        $order_id   = intval(trim($_REQUEST['order_id']));        // 订单id
        $rec_id = empty($_REQUEST['rec_id']) ? 0 : $_REQUEST['rec_id'];     //by　Leah
        $ret_id = empty($_REQUEST['ret_id']) ? 0 : $_REQUEST['ret_id'];  //by Leah
        $return = '';   //by leah
        //by Leah S
        if ($ret_id) {

            $return = 1;
        }
        //by Leah E
        $operation = $_REQUEST['operation'];                 // 订单操作

        /* 查询订单信息 */
        $order = seller_order_info($order_id);

        /*判断门店订单，获取门店id by kong */
        $store_order_id = get_store_id($order_id);
        $store_id = ($store_order_id > 0) ? $store_order_id : 0;

        /* 检查能否操作 */
        $operable_list = operable_list($order);

        if (!isset($operable_list[$operation]))
        {
            die('Hacking attempt');
        }

        /* 取得备注信息 */
        $action_note = $_REQUEST['action_note'];

        /* 初始化提示信息 */
        $msg = '';

        /* 确认 */
        if ('confirm' == $operation)
        {
            /* 标记订单为已确认 */
            update_order($order_id, array('order_status' => OS_CONFIRMED, 'confirm_time' => gmtime()));
            update_order_amount($order_id);

            /* 记录log */
            order_action($order['order_sn'], OS_CONFIRMED, SS_UNSHIPPED, PS_UNPAYED, $action_note,$_SESSION['seller_name']);


            //by zxk 减库存
            /* 如果原来状态不是“未确认”，且使用库存，且下订单时减库存，则减少库存 */
            if ($order['order_status'] != OS_UNCONFIRMED && $_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_PLACE)
            {
                change_order_goods_storage($order_id, true, SDT_PLACE, 4, $_SESSION['seller_id'],$store_id);
            }

            /* 发送邮件 */
            $cfg = $_CFG['send_confirm_email'];
            if ($cfg == '1')
            {
                $tpl = get_mail_template('order_confirm');
                $this->assign('order', $order);
                $this->assign('shop_name', $_CFG['shop_name']);
                $this->assign('send_date', local_date($_CFG['date_format']));
                $this->assign('sent_date', local_date($_CFG['date_format']));
                $content = $this->fetch('', 'str:' . $tpl['template_content']);

                if ($order['email'] && !send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html']))
                {
                    $msg = L('send_mail_fail');
                }
            }
        }
        /* 付款 */
        elseif ('pay' == $operation)
        {
            /* 检查权限 */
            admin_priv('order_ps_edit');

            /* 标记订单为已确认、已付款，更新付款时间和已支付金额，如果是货到付款，同时修改订单为“收货确认” */
            if ($order['order_status'] != OS_CONFIRMED)
            {
                $arr['order_status']    = OS_CONFIRMED;
                $arr['confirm_time']    = gmtime();
                if($_CFG['sales_volume_time'] == SALES_PAY){
                    $arr['is_update_sale'] = 1;
                }
            }
            $arr['pay_time']    = gmtime();
            //预售定金处理
            if($order['extension_code'] == 'presale' && $order['pay_status'] == 0){
                $arr['pay_status']  = PS_PAYED_PART;
                $arr['money_paid']  = $order['money_paid'] + $order['order_amount'];
                $arr['order_amount']= $order['goods_amount'] + $order['shipping_fee'] + $order['insure_fee'] + $order['pay_fee'] + $order['tax'] + $order['pack_fee'] + $order['card_fee'] -
                    $order['surplus'] - $order['money_paid'] - $order['integral_money'] - $order['bonus'] - $order['order_amount'] - $order['discount'] ;
            }else{
                $arr['pay_status']  = PS_PAYED;
                $arr['money_paid']  = $order['money_paid'] + $order['order_amount'];
                $arr['order_amount']= 0;
            }
            $payment = payment_info($order['pay_id']);
            if ($payment['is_cod'])
            {
                $arr['shipping_status'] = SS_RECEIVED;
                $order['shipping_status'] = SS_RECEIVED;
            }

            update_order($order_id, $arr);

            //付款成功创建快照
            create_snapshot($order_id);

            /* 更新商品销量 ecmoban模板堂 --zhuo */
            get_goods_sale($order_id);

            //门店处理 付款成功发送短信
            $sql = 'SELECT store_id,pick_code,order_id FROM '.$GLOBALS['ecs']->table("store_order")." WHERE order_id = '$order_id' LIMIT 1";
            $stores_order = $GLOBALS['db']->getRow($sql);
            $user_mobile_phone = '';
            $sql = "SELECT mobile_phone,user_name FROM " . $GLOBALS['ecs']->table('users') . " WHERE user_id = '" . $order['user_id'] . "' LIMIT 1";
            $orderUsers = $GLOBALS['db']->getRow($sql);
            if ($stores_order['store_id'] > 0) {
                if ($order['mobile']) {
                    $user_mobile_phone = $order['mobile'];
                } else {
                    $user_mobile_phone = $orderUsers['mobile_phone'];
                }
            }
            if ($user_mobile_phone != '') {
                //门店短信处理
                $store_smsParams = '';
                $sql = "SELECT id, country, province, city, district, stores_address, stores_name, stores_tel FROM " . $GLOBALS['ecs']->table('offline_store') . " WHERE id = '" . $stores_order['store_id'] . "' LIMIT 1";
                $stores_info = $GLOBALS['db']->getRow($sql);
                $store_address = get_area_region_info($stores_info) . $stores_info['stores_address'];
                $user_name = !empty($orderUsers['user_name']) ? $orderUsers['user_name'] : '';
                //门店订单->短信接口参数
                $store_smsParams = array(
                    'user_name' => $user_name,
                    'username' => $user_name,
                    'order_sn' => $order['order_sn'],
                    'ordersn' => $order['order_sn'],
                    'code' => $stores_order['pick_code'],
                    'store_address' => $store_address,
                    'storeaddress' => $store_address,
                    'mobile_phone' => $user_mobile_phone,
                    'mobilephone' => $user_mobile_phone
                );
                $send_result = send_sms($user_mobile_phone, 'store_order_code', $store_smsParams);

//                if ($GLOBALS['_CFG']['sms_type'] == 0) {
//                    if ($stores_order['store_id'] > 0 && !empty($store_smsParams)) {
//                        huyi_sms($store_smsParams, 'store_order_code');
//                    }
//                } elseif ($GLOBALS['_CFG']['sms_type'] >=1) {
//                    if ($stores_order['store_id'] > 0 && !empty($store_smsParams)) {
//                        $store_result = sms_ali($store_smsParams, 'store_order_code'); //阿里大鱼短信变量传值，发送时机传值
//                        $GLOBALS['ecs']->ali_yu($store_result);
//                    }
//                }
            }

            $confirm_take_time = gmtime();
            if(($arr['order_status'] == OS_CONFIRMED || $arr['order_status'] == OS_SPLITED) && $arr['pay_status'] == PS_PAYED && $arr['shipping_status'] == SS_RECEIVED){

                /* 查询订单信息，检查状态 */
                $sql = "SELECT order_id, user_id, order_sn , order_status, shipping_status, pay_status, " .
                    "order_amount, goods_amount, tax, shipping_fee, insure_fee, pay_fee, pack_fee, card_fee, " .
                    "bonus, integral_money, coupons, discount, money_paid, surplus, confirm_take_time " .
                    "FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '$order_id'";

                $bill_order = $GLOBALS['db']->GetRow($sql);

                $seller_id = $GLOBALS['db']->getOne("SELECT ru_id FROM " .$GLOBALS['ecs']->table('order_goods'). " WHERE order_id = '$order_id'", true);
                $value_card = $GLOBALS['db']->getOne("SELECT use_val FROM " .$GLOBALS['ecs']->table('value_card_record'). " WHERE order_id = '$order_id'", true);

                $return_amount = get_order_return_amount($order_id);

                $other = array(
                    'user_id'               => $bill_order['user_id'],
                    'seller_id'             => $seller_id,
                    'order_id'              => $bill_order['order_id'],
                    'order_sn'              => $bill_order['order_sn'],
                    'order_status'          => $bill_order['order_status'],
                    'shipping_status'       => SS_RECEIVED,
                    'pay_status'            => $bill_order['pay_status'],
                    'order_amount'          => $bill_order['order_amount'],
                    'return_amount'         => $return_amount,
                    'goods_amount'          => $bill_order['goods_amount'],
                    'tax'                   => $bill_order['tax'],
                    'shipping_fee'          => $bill_order['shipping_fee'],
                    'insure_fee'            => $bill_order['insure_fee'],
                    'pay_fee'               => $bill_order['pay_fee'],
                    'pack_fee'              => $bill_order['pack_fee'],
                    'card_fee'              => $bill_order['card_fee'],
                    'bonus'                 => $bill_order['bonus'],
                    'integral_money'        => $bill_order['integral_money'],
                    'coupons'               => $bill_order['coupons'],
                    'discount'               => $bill_order['discount'],
                    'value_card'            => $value_card,
                    'money_paid'            => $bill_order['money_paid'],
                    'surplus'               => $bill_order['surplus'],
                    'confirm_take_time'     => $confirm_take_time
                );

                if($seller_id){
                    $insert_id = get_order_bill_log($other);

                    /* by zxk jisuan*/
                    //确认收货 商家结算到可用金额
                    order_confirm_change($bill_order['order_id'], $insert_id);
                    /* by zxk jisuan*/
                }
            }

            /* 记录log */
            if($order['extension_code'] == 'presale' && $order['pay_status'] == 0){
                order_action($order['order_sn'], OS_CONFIRMED, $order['shipping_status'], PS_PAYED_PART, $action_note,$_SESSION['seller_name']);
                /* 更新 pay_log */
                update_pay_log($order_id);
            }else{
                order_action($order['order_sn'], OS_CONFIRMED, $order['shipping_status'], PS_PAYED, $action_note,$_SESSION['seller_name'], 0, $confirm_take_time);
            }
        }
        /* 配货 */
        elseif ('prepare' == $operation)
        {
            /* 标记订单为已确认，配货中 */
            if ($order['order_status'] != OS_CONFIRMED)
            {
                $arr['order_status']    = OS_CONFIRMED;
                $arr['confirm_time']    = gmtime();
            }
            $arr['shipping_status']     = SS_PREPARING;
            update_order($order_id, $arr);

            /* 记录log */
            order_action($order['order_sn'], OS_CONFIRMED, SS_PREPARING, $order['pay_status'], $action_note,$_SESSION['seller_name']);

            /* 清除缓存 */
            clear_cache_files();
        }
        /* 分单确认 */
        elseif ('split' == $operation)
        {
            /* 检查权限 */
            admin_priv('order_ss_edit');

            /* 定义当前时间 */
            define('GMTIME_UTC', gmtime()); // 获取 UTC 时间戳
            $delivery_info = get_delivery_info($order_id);
            if(true){
                /* 获取表单提交数据 */
                $suppliers_id = isset($_REQUEST['suppliers_id']) ? intval(trim($_REQUEST['suppliers_id'])) : '0';
                array_walk($_REQUEST['delivery'], 'trim_array_walk');
                $delivery = $_REQUEST['delivery'];
                array_walk($_REQUEST['send_number'], 'trim_array_walk');
                array_walk($_REQUEST['send_number'], 'intval_array_walk');
                $send_number = $_REQUEST['send_number'];
                $action_note = isset($_REQUEST['action_note']) ? trim($_REQUEST['action_note']) : '';
                $delivery['user_id']  = intval($delivery['user_id']);
                $delivery['country']  = intval($delivery['country']);
                $delivery['province'] = intval($delivery['province']);
                $delivery['city']     = intval($delivery['city']);
                $delivery['district'] = intval($delivery['district']);
                $delivery['agency_id']    = intval($delivery['agency_id']);
                $delivery['insure_fee']   = floatval($delivery['insure_fee']);
                $delivery['shipping_fee'] = floatval($delivery['shipping_fee']);

                /* 订单是否已全部分单检查 */
                if ($order['order_status'] == OS_SPLITED)
                {
                    /* 操作失败 */
                    $links[] = array('text' => L('order_info'), 'href' => 'order.php?act=info&order_id=' . $order_id);
                    sys_msg(sprintf( L('order_splited_sms'), $order['order_sn'],
                        L('os')[OS_SPLITED], L('ss')[SS_SHIPPED_ING], $GLOBALS['_CFG']['shop_name']), 1, $links);
                }

                /* 取得订单商品 */
                $_goods = get_order_goods(array('order_id' => $order_id, 'order_sn' => $delivery['order_sn']));
                $goods_list = $_goods['goods_list'];

                /* 检查此单发货数量填写是否正确 合并计算相同商品和货品 */
                if (!empty($send_number) && !empty($goods_list))
                {
                    $goods_no_package = array();
                    foreach ($goods_list as $key => $value)
                    {
                        /* 去除 此单发货数量 等于 0 的商品 */
                        if (!isset($value['package_goods_list']) || !is_array($value['package_goods_list']))
                        {
                            // 如果是货品则键值为商品ID与货品ID的组合
                            $_key = empty($value['product_id']) ? $value['goods_id'] : ($value['goods_id'] . '_' . $value['product_id']);

                            // 统计此单商品总发货数 合并计算相同ID商品或货品的发货数
                            if (empty($goods_no_package[$_key]))
                            {
                                $goods_no_package[$_key] = $send_number[$value['rec_id']];
                            }
                            else
                            {
                                $goods_no_package[$_key] += $send_number[$value['rec_id']];
                            }

                            //去除
                            if ($send_number[$value['rec_id']] <= 0)
                            {
                                unset($send_number[$value['rec_id']], $goods_list[$key]);
                                continue;
                            }
                        }
                        else
                        {
                            /* 组合超值礼包信息 */
                            $goods_list[$key]['package_goods_list'] = package_goods($value['package_goods_list'], $value['goods_number'], $value['order_id'], $value['extension_code'], $value['goods_id']);

                            /* 超值礼包 */
                            foreach ($value['package_goods_list'] as $pg_key => $pg_value)
                            {
                                // 如果是货品则键值为商品ID与货品ID的组合
                                $_key = empty($pg_value['product_id']) ? $pg_value['goods_id'] : ($pg_value['goods_id'] . '_' . $pg_value['product_id']);

                                //统计此单商品总发货数 合并计算相同ID产品的发货数
                                if (empty($goods_no_package[$_key]))
                                {
                                    $goods_no_package[$_key] = $send_number[$value['rec_id']][$pg_value['g_p']];
                                }
                                //否则已经存在此键值
                                else
                                {
                                    $goods_no_package[$_key] += $send_number[$value['rec_id']][$pg_value['g_p']];
                                }

                                //去除
                                if ($send_number[$value['rec_id']][$pg_value['g_p']] <= 0)
                                {
                                    unset($send_number[$value['rec_id']][$pg_value['g_p']], $goods_list[$key]['package_goods_list'][$pg_key]);
                                }
                            }

                            if (count($goods_list[$key]['package_goods_list']) <= 0)
                            {
                                unset($send_number[$value['rec_id']], $goods_list[$key]);
                                continue;
                            }
                        }

                        /* 发货数量与总量不符 */
                        if (!isset($value['package_goods_list']) || !is_array($value['package_goods_list']))
                        {
                            $sended = order_delivery_num($order_id, $value['goods_id'], $value['product_id']);
                            if (($value['goods_number'] - $sended - $send_number[$value['rec_id']]) < 0)
                            {
                                /* 操作失败 */
                                $links[] = array('text' => L('order_info'), 'href' => 'order.php?act=info&order_id=' . $order_id);
                                sys_msg(L('act_ship_num'), 1, $links);
                            }
                        }
                        else
                        {
                            /* 超值礼包 */
                            foreach ($goods_list[$key]['package_goods_list'] as $pg_key => $pg_value)
                            {
                                if (($pg_value['order_send_number'] - $pg_value['sended'] - $send_number[$value['rec_id']][$pg_value['g_p']]) < 0)
                                {
                                    /* 操作失败 */
                                    $links[] = array('text' => L('order_info'), 'href' => 'order.php?act=info&order_id=' . $order_id);
                                    sys_msg(L('act_ship_num'), 1, $links);
                                }
                            }
                        }
                    }
                }

                /* 对上一步处理结果进行判断 兼容 上一步判断为假情况的处理 */
                if (empty($send_number) || empty($goods_list))
                {
                    $href = url('order/detail', ['order_id'=>$order_id]);
                    /* 操作失败 */
                    $links[] = array('text' => L('order_info'), 'href' => $href);
                    sys_msg(L('act_false'), 1, $links);
                }

                /* 检查此单发货商品库存缺货情况 */
                /* $goods_list已经过处理 超值礼包中商品库存已取得 */
                $virtual_goods = array();
                $package_virtual_goods = array();

                foreach ($goods_list as $key => $value)
                {
                    // 商品（超值礼包）
                    if ($value['extension_code'] == 'package_buy')
                    {
                        foreach ($value['package_goods_list'] as $pg_key => $pg_value)
                        {
                            if ($pg_value['goods_number'] < $goods_no_package[$pg_value['g_p']] && (($_CFG['use_storage'] == '1'  && $_CFG['stock_dec_time'] == SDT_SHIP) || ($_CFG['use_storage'] == '0' && $pg_value['is_real'] == 0)))
                            {
                                /* 操作失败 */
                                $links[] = array('text' => L('order_info'), 'href' => 'order.php?act=info&order_id=' . $order_id);
                                sys_msg(sprintf(L('act_good_vacancy'), $pg_value['goods_name']), 1, $links);
                            }

                            /* 商品（超值礼包） 虚拟商品列表 package_virtual_goods*/
                            if ($pg_value['is_real'] == 0)
                            {
                                $package_virtual_goods[] = array(
                                    'goods_id' => $pg_value['goods_id'],
                                    'goods_name' => $pg_value['goods_name'],
                                    'num' => $send_number[$value['rec_id']][$pg_value['g_p']]
                                );
                            }
                        }
                    }
                    // 商品（虚货）
                    elseif ($value['extension_code'] == 'virtual_card' || $value['is_real'] == 0)
                    {
                        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('virtual_card') . " WHERE goods_id = '" . $value['goods_id'] . "' AND is_saled = 0 ";
                        $num = $GLOBALS['db']->GetOne($sql);
                        if (($num < $goods_no_package[$value['goods_id']]) && !($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_PLACE))
                        {
                            /* 操作失败 */
                            $links[] = array('text' => L('order_info'), 'href' => 'order.php?act=info&order_id=' . $order_id);
                            sys_msg(sprintf($GLOBALS['_LANG']['virtual_card_oos'] . '【' . $value['goods_name'] . '】'), 1, $links);
                        }

                        /* 虚拟商品列表 virtual_card*/
                        if ($value['extension_code'] == 'virtual_card')
                        {
                            $virtual_goods[$value['extension_code']][] = array('goods_id' => $value['goods_id'], 'goods_name' => $value['goods_name'], 'num' => $send_number[$value['rec_id']]);
                        }
                    }
                    // 商品（实货）、（货品）
                    else
                    {
                        //如果是货品则键值为商品ID与货品ID的组合
                        $_key = empty($value['product_id']) ? $value['goods_id'] : ($value['goods_id'] . '_' . $value['product_id']);
                        $num = $value['storage']; //ecmoban模板堂 --zhuo

                        if (($num < $goods_no_package[$_key]) && $_CFG['use_storage'] == '1'  && $_CFG['stock_dec_time'] == SDT_SHIP)
                        {
                            /* 操作失败 */
                            $links[] = array('text' => L('order_info'), 'href' => 'order.php?act=info&order_id=' . $order_id);
                            sys_msg(sprintf(L('act_good_vacancy'), $value['goods_name']), 1, $links);
                        }
                    }
                }

                /* 生成发货单 */
                /* 获取发货单号和流水号 */
                $delivery['delivery_sn'] = get_delivery_sn();
                $delivery_sn = $delivery['delivery_sn'];
                /* 获取当前操作员 */
                $delivery['action_user'] = $_SESSION['seller_name'];
                /* 获取发货单生成时间 */
                $delivery['update_time'] = GMTIME_UTC;
                $delivery_time = $delivery['update_time'];
                $sql ="select add_time from ". $GLOBALS['ecs']->table('order_info') ." WHERE order_sn = '" . $delivery['order_sn'] . "'";
                $delivery['add_time'] =  $GLOBALS['db']->GetOne($sql);
                /* 获取发货单所属供应商 */
                $delivery['suppliers_id'] = $suppliers_id;
                /* 设置默认值 */
                $delivery['status'] = 2; // 正常
                $delivery['order_id'] = $order_id;
                /* 过滤字段项 */
                $filter_fileds = array(
                    'order_sn', 'add_time', 'user_id', 'how_oos', 'shipping_id', 'shipping_fee',
                    'consignee', 'address', 'country', 'province', 'city', 'district', 'sign_building',
                    'email', 'zipcode', 'tel', 'mobile', 'best_time', 'postscript', 'insure_fee',
                    'agency_id', 'delivery_sn', 'action_user', 'update_time',
                    'suppliers_id', 'status', 'order_id', 'shipping_name'
                );
                $_delivery = array();
                foreach ($filter_fileds as $value)
                {
                    $_delivery[$value] = $delivery[$value];
                }

                /* 发货单入库 */
                $delivery_id = dao('delivery_order')->data($_delivery)->add();
//                $query = $db->autoExecute($ecs->table('delivery_order'), $_delivery, 'INSERT', '', 'SILENT');
//                $delivery_id = $db->insert_id();
                if ($delivery_id)
                {
                    $delivery_goods = array();

                    //发货单商品入库
                    if (!empty($goods_list))
                    {
                        //分单操作
                        $split_action_note = "";

                        foreach ($goods_list as $value)
                        {
                            // 商品（实货）（虚货）
                            if (empty($value['extension_code']) || $value['extension_code'] == 'virtual_card')
                            {
                                $delivery_goods = array('delivery_id' => $delivery_id,
                                    'goods_id' => $value['goods_id'],
                                    'product_id' => $value['product_id'],
                                    'product_sn' => $value['product_sn'],
                                    'goods_id' => $value['goods_id'],
                                    'goods_name' => addslashes($value['goods_name']),
                                    'brand_name' => addslashes($value['brand_name']),
                                    'goods_sn' => $value['goods_sn'],
                                    'send_number' => $send_number[$value['rec_id']],
                                    'parent_id' => 0,
                                    'is_real' => $value['is_real'],
                                    'goods_attr' => addslashes($value['goods_attr'])
                                );

                                /* 如果是货品 */
                                if (!empty($value['product_id']))
                                {
                                    $delivery_goods['product_id'] = $value['product_id'];
                                }

                                $query = $db->autoExecute($ecs->table('delivery_goods'), $delivery_goods, 'INSERT', '', 'SILENT');

                                //分单操作
                                $split_action_note .= sprintf(L('split_action_note'), $value['goods_sn'], $send_number[$value['rec_id']]) . "<br/>";
                            }
                            // 商品（超值礼包）
                            elseif ($value['extension_code'] == 'package_buy')
                            {
                                foreach ($value['package_goods_list'] as $pg_key => $pg_value)
                                {
                                    $delivery_pg_goods = array('delivery_id' => $delivery_id,
                                        'goods_id' => $pg_value['goods_id'],
                                        'product_id' => $pg_value['product_id'],
                                        'product_sn' => $pg_value['product_sn'],
                                        'goods_name' => $pg_value['goods_name'],
                                        'brand_name' => '',
                                        'goods_sn' => $pg_value['goods_sn'],
                                        'send_number' => $send_number[$value['rec_id']][$pg_value['g_p']],
                                        'parent_id' => $value['goods_id'], // 礼包ID
                                        'extension_code' => $value['extension_code'], // 礼包
                                        'is_real' => $pg_value['is_real']
                                    );
                                    $query = $db->autoExecute($ecs->table('delivery_goods'), $delivery_pg_goods, 'INSERT', '', 'SILENT');
                                }

                                //分单操作
                                $split_action_note .= sprintf(L('split_action_note'), L('14_package_list'), 1) . "<br/>";
                            }
                        }
                    }
                }
                else
                {
                    /* 操作失败 */
                    $links[] = array('text' => L('order_info'), 'href' => url('order/detail', ['order_id'=>$order_id]));
                    sys_msg(L('act_false'), 1, $links);
                }
                unset($filter_fileds, $delivery, $_delivery, $order_finish);

                /* 定单信息更新处理 */
                if (true)
                {
                    /* 定单信息 */
                    $_sended = & $send_number;
                    foreach ($_goods['goods_list'] as $key => $value)
                    {
                        if ($value['extension_code'] != 'package_buy')
                        {
                            unset($_goods['goods_list'][$key]);
                        }
                    }
                    foreach ($goods_list as $key => $value)
                    {
                        if ($value['extension_code'] == 'package_buy')
                        {
                            unset($goods_list[$key]);
                        }
                    }
                    $_goods['goods_list'] = $goods_list + $_goods['goods_list'];
                    unset($goods_list);

                    /* 更新订单的虚拟卡 商品（虚货） */
                    $_virtual_goods = isset($virtual_goods['virtual_card']) ? $virtual_goods['virtual_card'] : '';
                    update_order_virtual_goods($order_id, $_sended, $_virtual_goods);

                    /* 更新订单的非虚拟商品信息 即：商品（实货）（货品）、商品（超值礼包）*/
                    update_order_goods($order_id, $_sended, $_goods['goods_list']);

                    /* 标记订单为已确认 “发货中” */
                    /* 更新发货时间 */
                    $order_finish = get_order_finish($order_id);
                    $shipping_status = SS_SHIPPED_ING;
                    if ($order['order_status'] != OS_CONFIRMED && $order['order_status'] != OS_SPLITED && $order['order_status'] != OS_SPLITING_PART)
                    {
                        $arr['order_status']    = OS_CONFIRMED;
                        $arr['confirm_time']    = GMTIME_UTC;
                    }
                    $arr['order_status'] = $order_finish ? OS_SPLITED : OS_SPLITING_PART; // 全部分单、部分分单
                    $arr['shipping_status']     = $shipping_status;
                    update_order($order_id, $arr);
                }

                /* 分单操作 */
                $action_note = $split_action_note . $action_note;

                /* 记录log */
                order_action($order['order_sn'], $arr['order_status'], $shipping_status, $order['pay_status'], $action_note,$_SESSION['seller_name']);

                /* 清除缓存 */
                clear_cache_files();
            }
        }
        /* 设为未发货 */
        elseif ('unship' == $operation)
        {
            /* 检查权限 */
            admin_priv('order_ss_edit');

            /* 标记订单为“未发货”，更新发货时间, 订单状态为“确认” */
            update_order($order_id, array('shipping_status' => SS_UNSHIPPED, 'shipping_time' => 0, 'invoice_no' => '', 'order_status' => OS_CONFIRMED));

            /* 记录log */
            order_action($order['order_sn'], $order['order_status'], SS_UNSHIPPED, $order['pay_status'], $action_note,$_SESSION['seller_name']);
            /* 如果订单用户不为空，计算积分，并退回 */
            if ($order['user_id'] > 0)
            {
                /* 取得用户信息 */
                $user = user_info($order['user_id']);

                /* 计算并退回积分 */
                $integral = integral_to_give($order);
                log_account_change($order['user_id'], 0, 0, (-1) * intval($integral['rank_points']), (-1) * intval($integral['custom_points']), sprintf(L('return_order_gift_integral'), $order['order_sn']));

                /* todo 计算并退回红包 */
                return_order_bonus($order_id);
            }

            /* 如果使用库存，则增加库存 */
            if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP)
            {
                change_order_goods_storage($order['order_id'], false, SDT_SHIP, 5, $_SESSION['seller_id'],$store_id);
            }

            /* 删除发货单 */
            del_order_delivery($order_id);

            /* 将订单的商品发货数量更新为 0 */
            $sql = "UPDATE " . $GLOBALS['ecs']->table('order_goods') . "
                        SET send_number = 0
                        WHERE order_id = '$order_id'";
            $GLOBALS['db']->query($sql, 'SILENT');

            /* 清除缓存 */
            clear_cache_files();
        }
        /* 收货确认 */
        elseif ('receive' == $operation)
        {

            $confirm_take_time = gmtime();

            /* 标记订单为“收货确认”，如果是货到付款，同时修改订单为已付款 */
            $arr = array('shipping_status' => SS_RECEIVED, 'confirm_take_time' => $confirm_take_time);
            $payment = payment_info($order['pay_id']);
            if ($payment['is_cod'])
            {
                $arr['pay_status'] = PS_PAYED;
                $order['pay_status'] = PS_PAYED;
            }
            update_order($order_id, $arr);

            /* 更新商品销量 ecmoban模板堂 --zhuo */
            // $sql = 'SELECT goods_id,goods_number FROM ' . $GLOBALS['ecs']->table('order_goods') . ' WHERE order_id =' . $order_id;
            // $order_res = $GLOBALS['db']->getAll($sql);
            // foreach ($order_res as $idx => $val) {
            // $sql = 'SELECT SUM(og.goods_number) as goods_number ' .
            // 'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g, ' .
            // $GLOBALS['ecs']->table('order_info') . ' AS o, ' .
            // $GLOBALS['ecs']->table('order_goods') . ' AS og ' .
            // "WHERE g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 AND og.order_id = o.order_id AND og.goods_id = g.goods_id " .
            // "AND (o.order_status = '" . OS_CONFIRMED . "' OR o.order_status = '" . OS_SPLITED . "') " .
            // "AND (o.pay_status = '" . PS_PAYED . "') " .
            // "AND (o.shipping_status = '" . SS_RECEIVED . "') AND g.goods_id=" . $val['goods_id'];

            // $sales_volume = $GLOBALS['db']->getOne($sql);
            // $sql = "update " . $ecs->table('goods') . " set sales_volume='$sales_volume' WHERE goods_id =" . $val['goods_id'];

            // $db->query($sql);
            // }

            /* 记录log */
            order_action($order['order_sn'], $order['order_status'], SS_RECEIVED, $order['pay_status'], $action_note, $_SESSION['seller_name'], 0, $confirm_take_time);

            $bill = array(
                'order_id' => $order['order_id']
            );
            $bill_order = get_bill_order($bill);

            if(!$bill_order){

                $seller_id = $GLOBALS['db']->getOne("SELECT ru_id FROM " .$GLOBALS['ecs']->table('order_goods'). " WHERE order_id = '" .$order['order_id']. "'", true);
                $value_card = $GLOBALS['db']->getOne("SELECT use_val FROM " .$GLOBALS['ecs']->table('value_card_record'). " WHERE order_id = '" .$order['order_id']. "'", true);

                $return_amount = get_order_return_amount($order['order_id']);

                $other = array(
                    'user_id'               => $order['user_id'],
                    'seller_id'             => $seller_id,
                    'order_id'              => $order['order_id'],
                    'order_sn'              => $order['order_sn'],
                    'order_status'          => $order['order_status'],
                    'shipping_status'       => SS_RECEIVED,
                    'pay_status'            => $order['pay_status'],
                    'order_amount'          => $order['order_amount'],
                    'return_amount'         => $return_amount,
                    'goods_amount'          => $order['goods_amount'],
                    'tax'                   => $order['tax'],
                    'shipping_fee'          => $order['shipping_fee'],
                    'insure_fee'            => $order['insure_fee'],
                    'pay_fee'               => $order['pay_fee'],
                    'pack_fee'              => $order['pack_fee'],
                    'card_fee'              => $order['card_fee'],
                    'bonus'                 => $order['bonus'],
                    'integral_money'        => $order['integral_money'],
                    'coupons'               => $order['coupons'],
                    'discount'               => $order['discount'],
                    'value_card'            => $value_card,
                    'money_paid'            => $order['money_paid'],
                    'surplus'               => $order['surplus'],
                    'confirm_take_time'     => $confirm_take_time
                );

                if($seller_id){
                    $insert_id = get_order_bill_log($other);

                    /* by zxk jisuan*/
                    //确认收货 商家结算到可用金额
                    order_confirm_change($order['order_id'], $insert_id);
                    /* by zxk jisuan*/
                }
            }
        }

        /*
         * 收到退换货商品
         * by　ecmoban模板堂 --zhuo
         */
        elseif ('agree_apply' == $operation) {

            $arr = array('agree_apply' => 1); //收到用户退回商品
            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_return'), $arr, 'UPDATE', "rec_id = '$rec_id'");

            /* 记录log TODO_LOG */
            return_action($ret_id, RF_AGREE_APPLY, '', $action_note);
        }

        /*
         * 收到退换货商品
         * by　Leah
         */
        elseif ('receive_goods' == $operation) {

            $arr = array('return_status' => 1); //收到用户退回商品


            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_return'), $arr, 'UPDATE', "rec_id = '$rec_id'");
            $arr['pay_status'] = PS_PAYED;
            $order['pay_status'] = PS_PAYED;

            /* 记录log TODO_LOG */
            return_action($ret_id, RF_RECEIVE, '', $action_note);
        }
        /**
         * 换出商品寄出 ---- 分单
         * by Leah
         */ elseif ('swapped_out_single' == $operation) {

            $arr = array('return_status' => 2); //换出商品寄出

            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_return'), $arr, 'UPDATE', "rec_id = '$rec_id'");
            return_action($ret_id, RF_SWAPPED_OUT_SINGLE, '', $action_note);
        }
        /**
         * 换出商品寄出
         * by leah
         */
        elseif ('swapped_out' == $operation) {

            $arr = array('return_status' => 3); //换出商品寄出

            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_return'), $arr, 'UPDATE', "rec_id = '$rec_id'");
            return_action($ret_id, RF_SWAPPED_OUT, '', $action_note);
        }

        /**
         * 拒绝申请
         * by leah
         */
        elseif ('refuse_apply' == $operation) {

            $arr = array('return_status' => 6); //换出商品寄出

            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_return'), $arr, 'UPDATE', "rec_id = '$rec_id'");
            return_action($ret_id, REFUSE_APPLY, '', $action_note);
        }

        /**
         * 完成退换货
         * by Leah
         */
        elseif ('complete' == $operation) {

            $arr = array('return_status' => 4); //完成退换货

            $sql = "SELECT return_type FROM " .$ecs->table('order_return'). " WHERE rec_id = '$rec_id'";
            $return_type = $db->getOne($sql);

            if($return_type == 0){
                $return_note = FF_MAINTENANCE;
            }else if($return_type == 1){
                $return_note = FF_REFOUND;
            }else if($return_type == 2){
                $return_note = FF_EXCHANGE;
            }

            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_return'), $arr, 'UPDATE', "rec_id = '$rec_id'");
            return_action($ret_id, RF_COMPLETE, $return_note, $action_note);
        }

        /* 取消 */
        elseif ('cancel' == $operation)
        {
            /* 标记订单为“取消”，记录取消原因 */
            $cancel_note = isset($_REQUEST['cancel_note']) ? trim($_REQUEST['cancel_note']) : '';
            $arr = array(
                'order_status'  => OS_CANCELED,
                'to_buyer'      => $cancel_note,
                'pay_status'    => PS_UNPAYED,
                'pay_time'      => 0,
                'money_paid'    => 0,
                'order_amount'  => $order['money_paid']
            );
            update_order($order_id, $arr);

            /* todo 处理退款 */
            if ($order['money_paid'] > 0)
            {
                $refund_type = isset($_REQUEST['refund']) && !empty($_REQUEST['refund']) ? tirm($_REQUEST['refund']) : '';
                $refund_note = isset($_REQUEST['refund']) && !empty($_REQUEST['refund_note']) ? tirm($_REQUEST['refund_note']) : '';

                if ($refund_note) {
                    $refund_note = "【" . L('setorder_cancel') . "】【" . $order['order_sn'] . "】" . $refund_note;
                }

                order_refund($order, $refund_type, $refund_note);
            }

            /* 记录log */
            order_action($order['order_sn'], OS_CANCELED, $order['shipping_status'], PS_UNPAYED, $action_note,$_SESSION['seller_name']);

            /* 如果使用库存，且下订单时减库存，则增加库存 */
            if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_PLACE)
            {
                change_order_goods_storage($order_id, false, SDT_PLACE, 3, $_SESSION['seller_id'],$store_id);
            }

            /* 退还用户余额、积分、红包 */
            return_user_surplus_integral_bonus($order);

            /* 发送邮件 */
            $cfg = $_CFG['send_cancel_email'];
            if ($cfg == '1')
            {
                $tpl = get_mail_template('order_cancel');
                $this->assign('order', $order);
                $this->assign('shop_name', $_CFG['shop_name']);
                $this->assign('send_date', local_date($_CFG['date_format']));
                $this->assign('sent_date', local_date($_CFG['date_format']));
                $content = $this->fetch('str:' . $tpl['template_content']);
                if (!send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html']))
                {
                    $msg = L('send_mail_fail');
                }
            }
        }
        /* 设为无效 */
        elseif ('invalid' == $operation)
        {
            /* 标记订单为“无效”、“未付款” */
            update_order($order_id, array('order_status' => OS_INVALID));

            /* 记录log */
            order_action($order['order_sn'], OS_INVALID, $order['shipping_status'], PS_UNPAYED, $action_note,$_SESSION['seller_name']);

            /* 如果使用库存，且下订单时减库存，则增加库存 */
            if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_PLACE)
            {
                change_order_goods_storage($order_id, false, SDT_PLACE, 2, $_SESSION['seller_id'],$store_id);
            }

            /* 发送邮件 */
            $cfg = $_CFG['send_invalid_email'];
            if ($cfg == '1')
            {
                $tpl = get_mail_template('order_invalid');
                $this->assign('order', $order);
                $this->assign('shop_name', $_CFG['shop_name']);
                $this->assign('send_date', local_date($_CFG['date_format']));
                $this->assign('sent_date', local_date($_CFG['date_format']));
                $content = $this->fetch('str:' . $tpl['template_content']);
                if (!send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html']))
                {
                    $msg = L('send_mail_fail');
                }
            }

            /* 退货用户余额、积分、红包 */
            return_user_surplus_integral_bonus($order);
        }

        /**
         * 退款
         * by  Leah
         */
        elseif ('refound' == $operation) {
            exit;
            include_once(ROOT_PATH . 'includes/lib_transaction.php');
            //TODO
            /* 定义当前时间 */
            define('GMTIME_UTC', gmtime()); // 获取 UTC 时间戳

            $is_whole = 0;
            $is_diff = get_order_return_rec($order_id);
            if ($is_diff) {
                //整单退换货
                $return_count = return_order_info_byId($order_id, 0);
                if ($return_count == 1) {

                    //退还红包
                    $bonus = $order['bonus'];
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('user_bonus') . " SET used_time = '' , order_id = '' WHERE order_id = " . $order_id;
                    $GLOBALS['db']->query($sql);

                    /*  @author-bylu 退还优惠券 start  */
                    unuse_coupons($order_id);

                    $is_whole = 1;
                }
            }

            /* 过滤数据 */
            $_REQUEST['refund'] = isset($_REQUEST['refund']) ? $_REQUEST['refund'] : ''; // 退款类型
            $_REQUEST['refund_amount'] = isset($_REQUEST['refund_amount']) ? $_REQUEST['refund_amount'] :
                $_REQUEST['action_note'] = isset($_REQUEST['action_note']) ? $_REQUEST['action_note'] : ''; //退款说明
            $_REQUEST['refound_pay_points'] = isset($_REQUEST['refound_pay_points']) ? $_REQUEST['refound_pay_points']:0;//退回积分  by kong

            $return_amount = isset($_REQUEST['refound_amount']) && !empty($_REQUEST['refound_amount']) ? floatval($_REQUEST['refound_amount']) : 0; //退款金额
            $is_shipping = isset($_REQUEST['is_shipping']) && !empty($_REQUEST['is_shipping']) ? intval($_REQUEST['is_shipping']) : 0; //是否退运费
            $shippingFee = !empty($is_shipping) ? floatval($_REQUEST['shipping']) : 0; //退款运费金额

            $refound_vcard = isset($_REQUEST['refound_vcard']) && !empty($_REQUEST['refound_vcard']) ? floatval($_REQUEST['refound_vcard']) : 0; //储值卡金额
            $vc_id = isset($_REQUEST['vc_id']) && !empty($_REQUEST['vc_id']) ? intval($_REQUEST['vc_id']) : 0; //储值卡金额

            $return_goods = get_return_order_goods1($rec_id); //退换货商品
            $return_info = return_order_info($ret_id);        //退换货订单

            /* todo 处理退款 */
            if ($order['pay_status'] != PS_UNPAYED) {
                $order_goods = get_order_goods($order);             //订单商品
                $refund_type = $_REQUEST['refund'];

                //判断商品退款是否大于实际商品退款金额
                $refound_fee = order_refound_fee($order_id, $ret_id); //已退金额
                $paid_amount = $order['money_paid'] + $order['surplus'] - $refound_fee;
                if ($return_amount > $paid_amount) {
                    $return_amount = $paid_amount - $order['shipping_fee'];
                }

                //判断运费退款是否大于实际运费退款金额
                $is_refound_shippfee = order_refound_shipping_fee($order_id, $ret_id);
                $is_refound_shippfee_amount = $is_refound_shippfee + $shippingFee;

                if ($is_refound_shippfee_amount > $order['shipping_fee']) {
                    $shippingFee = $order['shipping_fee'] - $is_refound_shippfee;
                }

                $refund_amount = $return_amount + $shippingFee;
                $get_order_arr = get_order_arr($return_info['return_number'], $return_info['rec_id'], $order_goods['goods_list'], $order);
                $refund_note = addslashes(trim($_REQUEST['refund_note']));

                //退款
                if (!empty($_REQUEST['action_note'])) {

                    update_order($order_id, $get_order_arr);

                    $order['should_return'] = $return_info['should_return'];
                    order_refound($order, $refund_type, $refund_note, $refund_amount, $operation);
                    //标记order_return 表
                    $return_status = array('refound_status' => 1, 'actual_return' => $refund_amount, 'return_shipping_fee' => $shippingFee);
                    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_return'), $return_status, 'UPDATE', "ret_id = '$ret_id'");

                    //退款更新账单
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('seller_bill_order') . " SET return_amount = return_amount + '$return_amount', " .
                        "return_shippingfee = '$shippingFee' WHERE order_id = '$order_id'";
                    $GLOBALS['db']->query($sql);
                }
            }
        //         /*判断是否需要退还积分  如果需要 则跟新退还日志   by kong*/
            if ($_REQUEST['refound_pay_points'] > 0) {
                log_account_change($order['user_id'], 0, 0, 0, $_REQUEST['refound_pay_points'], " 订单退款，退回订单 " . $order['order_sn'] . " 购买的积分");
            }

            /* 退回订单赠送的积分 */
            return_integral_rank($ret_id, $order['user_id'], $order['order_sn'], $rec_id, $_REQUEST['refound_pay_points']);

            if($is_whole == 1){
                return_card_money($order_id, $ret_id, $return_info['return_sn']);
            }else{
                /* 退回订单消费储值卡金额 */
                get_return_vcard($order_id, $vc_id, $refound_vcard, $return_info['return_sn'], $ret_id);
            }

            /* 如果使用库存，则增加库存（不论何时减库存都需要） */
            if ($_CFG['use_storage'] == '1') {
                if ($_CFG['stock_dec_time'] == SDT_SHIP) {
                    change_order_goods_storage($order_id, false, SDT_SHIP, 6, $_SESSION['seller_id'], $store_id);
                } elseif ($_CFG['stock_dec_time'] == SDT_PLACE) {
                    change_order_goods_storage($order_id, false, SDT_PLACE, 6, $_SESSION['seller_id'], $store_id);
                }
            }

            /* 记录log */
            return_action($ret_id, '', FF_REFOUND, $action_note);
        }

        /* 退货 by Leah */
        elseif ('return' == $operation) {
            //TODO
            /* 定义当前时间 */
            define('GMTIME_UTC', gmtime()); // 获取 UTC 时间戳

            /* 过滤数据 */
            $_REQUEST['refund'] = isset($_REQUEST['refund']) ? $_REQUEST['refund'] : '';
            $_REQUEST['refund_note'] = isset($_REQUEST['refund_note']) ? $_REQUEST['refund'] : '';

            /* 手动修改退款金额 start */
            $return_amount = isset($_REQUEST['refound_amount']) && !empty($_REQUEST['refound_amount']) ? floatval($_REQUEST['refound_amount']) : 0; //退款金额
            $is_shipping = isset($_REQUEST['is_shipping']) && !empty($_REQUEST['is_shipping']) ? intval($_REQUEST['is_shipping']) : 0; //是否退运费
            $shipping_fee = !empty($is_shipping) ? floatval($_REQUEST['shipping']) : 0; //退款运费金额
            /* 手动修改退款金额 end */

            $refound_vcard = isset($_REQUEST['refound_vcard']) && !empty($_REQUEST['refound_vcard']) ? floatval($_REQUEST['refound_vcard']) : 0; //储值卡金额
            $vc_id = isset($_REQUEST['vc_id']) && !empty($_REQUEST['vc_id']) ? intval($_REQUEST['vc_id']) : 0; //储值卡金额

            $order_return_amount = $return_amount + $shipping_fee;

            /* 标记订单为“退货”、“未付款”、“未发货” */
            $arr = array('order_status' => OS_RETURNED,
                'pay_status' => PS_UNPAYED,
                'shipping_status' => SS_UNSHIPPED,
                'money_paid' => 0,
                'invoice_no' => '',
                'return_amount' => $return_amount,
                'order_amount' => $order_amount
            );
            update_order($order_id, $arr);

            /* todo 处理退款 */
            if ($order['pay_status'] != PS_UNPAYED) {

                $order['order_status'] = OS_RETURNED;
                $order['pay_status'] = PS_UNPAYED;
                $order['shipping_status'] = SS_UNSHIPPED;

                $refund_type = $_REQUEST['refund'];
                $refund_note = $_REQUEST['refund'];
                $refund_note = "【" .L('refund'). "】" . "【" .$order['order_sn']. "】" . $refund_note;
                order_refund($order, $refund_type, $refund_note, $return_amount, $shipping_fee);

                /* 余额已放入冻结资金 */
                $order['surplus'] = 0;
            }

            /* 记录log */
            order_action($order['order_sn'], OS_RETURNED, SS_UNSHIPPED, PS_UNPAYED, $action_note,$_SESSION['seller_name']);

            /* 如果订单用户不为空，计算积分，并退回 */
            if ($order['user_id'] > 0) {
                /* 取得用户信息 */
                $user = user_info($order['user_id']);

                $sql = "SELECT  goods_number, send_number FROM" . $GLOBALS['ecs']->table('order_goods') . "
                        WHERE order_id = '" . $order['order_id'] . "'";

                $goods_num = $db->query($sql);

                //获取第一个
                $goods_num = $goods_num[0];

//                $goods_num = $db->fetchRow($goods_num);

                if ($goods_num['goods_number'] == $goods_num['send_number']) {
                    /* 计算并退回积分 */
                    $integral = integral_to_give($order);
                    log_account_change($order['user_id'], 0, 0, (-1) * intval($integral['rank_points']), (-1) * intval($integral['custom_points']), sprintf(L('return_order_gift_integral'), $order['order_sn']));
                }
                /* todo 计算并退回红包 */
                return_order_bonus($order_id);
            }

            /* 如果使用库存，则增加库存（不论何时减库存都需要） */
            if ($_CFG['use_storage'] == '1') {
                if ($_CFG['stock_dec_time'] == SDT_SHIP) {
                    change_order_goods_storage($order['order_id'], false, SDT_SHIP, 6, $_SESSION['seller_id'],$store_id);
                } elseif ($_CFG['stock_dec_time'] == SDT_PLACE) {
                    change_order_goods_storage($order['order_id'], false, SDT_PLACE, 6, $_SESSION['seller_id'],$store_id);
                }
            }

            /* 退回订单消费储值卡金额 */
            return_card_money($order_id);

            /* 退货用户余额、积分、红包 */
            return_user_surplus_integral_bonus($order);

            /* 获取当前操作员 */
            $delivery['action_user'] = $_SESSION['seller_name'];
            /* 添加退货记录 */
            $delivery_list = array();
            $sql_delivery = "SELECT *
                                 FROM " . $ecs->table('delivery_order') . "
                                 WHERE status IN (0, 2)
                                 AND order_id = " . $order['order_id'];
            $delivery_list = $GLOBALS['db']->getAll($sql_delivery);
            if ($delivery_list) {
                foreach ($delivery_list as $list) {
                    $sql_back = "INSERT INTO " . $ecs->table('back_order') . " (delivery_sn, order_sn, order_id, add_time, shipping_id, user_id, action_user, consignee, address, Country, province, City, district, sign_building, Email,Zipcode, Tel, Mobile, best_time, postscript, how_oos, insure_fee, shipping_fee, update_time, suppliers_id, return_time, agency_id, invoice_no) VALUES ";

                    $sql_back .= " ( '" . $list['delivery_sn'] . "', '" . $list['order_sn'] . "',
                                      '" . $list['order_id'] . "', '" . $list['add_time'] . "',
                                      '" . $list['shipping_id'] . "', '" . $list['user_id'] . "',
                                      '" . $delivery['action_user'] . "', '" . $list['consignee'] . "',
                                      '" . $list['address'] . "', '" . $list['country'] . "', '" . $list['province'] . "',
                                      '" . $list['city'] . "', '" . $list['district'] . "', '" . $list['sign_building'] . "',
                                      '" . $list['email'] . "', '" . $list['zipcode'] . "', '" . $list['tel'] . "',
                                      '" . $list['mobile'] . "', '" . $list['best_time'] . "', '" . $list['postscript'] . "',
                                      '" . $list['how_oos'] . "', '" . $list['insure_fee'] . "',
                                      '" . $list['shipping_fee'] . "', '" . $list['update_time'] . "',
                                      '" . $list['suppliers_id'] . "', '" . GMTIME_UTC . "',
                                      '" . $list['agency_id'] . "', '" . $list['invoice_no'] . "'
                                      )";
//                    $GLOBALS['db']->query($sql_back, 'SILENT');
                    $data = [];
                    $data['delivery_sn'] = $list['delivery_sn'];
                    $data['order_sn'] = $list['order_sn'];
                    $data['order_id'] = $list['order_id'];
                    $data['add_time'] = $list['add_time'];
                    $data['shipping_id'] = $list['shipping_id'];
                    $data['user_id'] = $list['user_id'];
                    $data['action_user'] = $delivery['action_user'];
                    $data['consignee'] = $list['consignee'];

                    $data['address'] = $list['address'];
                    $data['country'] = $list['country'];
                    $data['province'] = $list['province'];
                    $data['city'] = $list['city'];
                    $data['district'] = $list['district'];
                    $data['sign_building'] = $list['sign_building'];

                    $data['email'] = $list['email'];
                    $data['zipcode'] = $list['zipcode'];
                    $data['tel'] = $list['tel'];
                    $data['mobile'] = $list['mobile'];
                    $data['best_time'] = $list['best_time'];
                    $data['postscript'] = $list['postscript'];

                    $data['how_oos'] = $list['how_oos'];
                    $data['insure_fee'] = $list['insure_fee'];
                    $data['shipping_fee'] = $list['shipping_fee'];
                    $data['update_time'] = $list['update_time'];


                    $data['suppliers_id'] = $list['suppliers_id'];
                    $data['return_time'] = GMTIME_UTC;
                    $data['agency_id'] = $list['agency_id'];
                    $data['invoice_no'] = $list['invoice_no'];

                    $back_id = dao('back_order')->data($list)->add();
//                    $back_id = $GLOBALS['db']->insert_id$data;

                    $sql_back_goods = "INSERT INTO " . $ecs->table('back_goods') . " (back_id, goods_id, product_id, product_sn, goods_name,goods_sn, is_real, send_number, goods_attr)
                                           SELECT '$back_id', goods_id, product_id, product_sn, goods_name, goods_sn, is_real, send_number, goods_attr
                                           FROM " . $ecs->table('delivery_goods') . "
                                           WHERE delivery_id = " . $list['delivery_id'];
                    $GLOBALS['db']->query($sql_back_goods, 'SILENT');
                }
            }

            /* 修改订单的发货单状态为退货 */
            $sql_delivery = "UPDATE " . $ecs->table('delivery_order') . "
                                 SET status = 1
                                 WHERE status IN (0, 2)
                                 AND order_id = " . $order['order_id'];
            $GLOBALS['db']->query($sql_delivery, 'SILENT');

            /* 将订单的商品发货数量更新为 0 */
            $sql = "UPDATE " . $GLOBALS['ecs']->table('order_goods') . "
                        SET send_number = 0
                        WHERE order_id = '$order_id'";
            $GLOBALS['db']->query($sql, 'SILENT');

            /* 清除缓存 */
            clear_cache_files();
        } elseif ('after_service' == $operation) {
            /* 记录log */
            order_action($order['order_sn'], $order['order_status'], $order['shipping_status'], $order['pay_status'], '[' . L('op_after_service') . '] ' . $action_note,$_SESSION['seller_name']);
        } else {
            die('invalid params');
        }


        /**
         * by Leah s
         */
        if ($return) {

            $href = url('order/return_info', ['ret_id'=>$ret_id, 'rec_id'=>$rec_id]);
            $links = array('text' => L('order_info'), 'href' => $href); //by Leah
            $this->success(L('act_ok') . $msg, $links['href']);
        } else {
            /* 操作成功 */
            $href = url('order/detail', ['order_id'=>$order_id]);
            $links = array('text' => L('order_info'), 'href' => $href);
            $this->success(L('act_ok') . $msg, $links['href']);
        }
        /**
         * by Leah e
         */
    }

    //去发货
    public function actionDeliveryList() {
        /* 检查权限 */
        admin_priv('delivery_view');

        /* 查询 */
        $result = delivery_list();


        /* 模板赋值 */
        $this->assign('page_title', '发货单列表');

        $this->assign('os_unconfirmed',   OS_UNCONFIRMED);
        $this->assign('cs_await_pay',     CS_AWAIT_PAY);
        $this->assign('cs_await_ship',    CS_AWAIT_SHIP);
        $this->assign('full_page',        1);
        $page_count_arr = seller_page($result,$_REQUEST['page']);

        $this->assign('page_count_arr',   $page_count_arr);
        $this->assign('delivery_list',   $result['delivery']);
        $this->assign('filter',       $result['filter']);
        $this->assign('record_count', $result['record_count']);
        $this->assign('page_count',   $result['page_count']);
        $this->assign('sort_update_time', '<img src="images/sort_desc.gif">');

        /* 显示模板 */
        assign_query_info();
        $this->display();
    }

    public function actionDeliveryInfo() {
        //公共
        global $ecs, $db, $_CFG;

        /* 检查权限 */
        admin_priv('delivery_view');

        $this->assign('menu_select',array('action' => '04_order', 'current' => '09_delivery_order'));
        $delivery_id = intval(trim($_REQUEST['delivery_id']));

        /* 根据发货单id查询发货单信息 */
        if (!empty($delivery_id))
        {
            $delivery_order = delivery_order_info($delivery_id);
        }
        else
        {
            die('order does not exist');
        }

        /* 如果管理员属于某个办事处，检查该订单是否也属于这个办事处 */
        $sql = "SELECT agency_id FROM " . $ecs->table('admin_user') . " WHERE user_id = '" . $_SESSION['seller_id'] . "'";
        $agency_id = $db->getOne($sql);
        if ($agency_id > 0)
        {
            if ($delivery_order['agency_id'] != $agency_id)
            {
                sys_msg(L(['priv_error']));
            }

            /* 取当前办事处信息 */
            $sql = "SELECT agency_name FROM " . $ecs->table('agency') . " WHERE agency_id = '$agency_id' LIMIT 0, 1";
            $agency_name = $db->getOne($sql);
            $delivery_order['agency_name'] = $agency_name;
        }

        /* 取得用户名 */
        if ($delivery_order['user_id'] > 0)
        {
            $user = user_info($delivery_order['user_id']);
            if (!empty($user))
            {
                $delivery_order['user_name'] = $user['user_name'];
            }
        }

        /* 取得区域名 */
        $delivery_order['region'] = get_user_region_address($delivery_order['order_id']);

        /* 是否保价 */
        $order['insure_yn'] = empty($order['insure_fee']) ? 0 : 1;

        /* 取得发货单商品 */
        $goods_sql = "SELECT dg.*, g.brand_id FROM " . $ecs->table('delivery_goods') ." AS dg ".
            "LEFT JOIN ". $GLOBALS['ecs']->table('goods'). " AS g ON g.goods_id = dg.goods_id ".
            "WHERE dg.delivery_id = '" . $delivery_order['delivery_id'] . "'";
        $goods_list = $GLOBALS['db']->getAll($goods_sql);

        foreach($goods_list AS $key=>$row)
        {
            $brand = get_goods_brand_info($row['brand_id']);
            $goods_list[$key]['brand_name'] = $brand['brand_name'];

            //图片显示
            $row['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);

            $goods_list[$key]['goods_thumb'] = $row['goods_thumb'];
        }

        /* 是否存在实体商品 */
        $exist_real_goods = 0;
        if ($goods_list)
        {
            foreach ($goods_list as $value)
            {
                if ($value['is_real'])
                {
                    $exist_real_goods++;
                }
            }
        }

        /* 取得订单操作记录 */
        $act_list = array();
        $sql = "SELECT * FROM " . $ecs->table('order_action') . " WHERE order_id = '" . $delivery_order['order_id'] . "' AND action_place = 1 ORDER BY log_time DESC,action_id DESC";
        $res = $db->query($sql);
//        while ($row = $db->fetchRow($res))
        foreach ($res as $key => $row)
        {
            $row['order_status']    = L('os')[$row['order_status']];
            $row['pay_status']      = L('ps')[$row['pay_status']];
            $row['shipping_status'] = ($row['shipping_status'] == SS_SHIPPED_ING) ? L('ss_admin')[SS_SHIPPED_ING] : L('ss')[$row['shipping_status']];
            $row['action_time']     = local_date($_CFG['time_format'], $row['log_time']);
            $act_list[] = $row;
        }
        $this->assign('action_list', $act_list);

        /* 模板赋值 */
        $this->assign('delivery_order', $delivery_order);
        $this->assign('exist_real_goods', $exist_real_goods);
        $this->assign('goods_list', $goods_list);
        $this->assign('delivery_id', $delivery_id); // 发货单id

        /* 显示模板 */
        $this->assign('action_act', ($delivery_order['status'] == 2) ? 'delivery_ship' : 'delivery_cancel_ship');
        assign_query_info();

        $this->assign('page_title', '发货单操作');

        $this->display();
    }

    //取消订单
    public function actionDeliveryCancelShip() {
        //公共
        global $ecs, $db, $_CFG;

        /* 检查权限 */
        admin_priv('delivery_view');

        /* 取得参数 */
        $delivery = '';
        $order_id   = intval(trim($_REQUEST['order_id']));        // 订单id
        $delivery_id   = intval(trim($_REQUEST['delivery_id']));        // 发货单id
        $delivery['invoice_no'] = isset($_REQUEST['invoice_no']) ? trim($_REQUEST['invoice_no']) : '';
        $action_note = isset($_REQUEST['action_note']) ? trim($_REQUEST['action_note']) : '';

        /* 根据发货单id查询发货单信息 */
        if (!empty($delivery_id))
        {
            $delivery_order = delivery_order_info($delivery_id);
        }
        else
        {
            die('order does not exist');
        }

        /* 查询订单信息 */
        $order = seller_order_info($order_id);

        /* 取消当前发货单物流单号 */
        $_delivery['invoice_no'] = '';
        $_delivery['status'] = 2;
        $query = $db->autoExecute($ecs->table('delivery_order'), $_delivery, 'UPDATE', "delivery_id = $delivery_id", 'SILENT');
        if (!$query)
        {
            /* 操作失败 */
            $href = url('order/delivery_info', ['delivery_id'=> $delivery_id]);
            $links[] = array('text' => L('delivery_sn'). L('detail'), 'href' => $href);
            sys_msg(L('act_false'), $links);
            exit;
        }

        /* 修改定单发货单号 */
        $invoice_no_order = explode('<br>', $order['invoice_no']);
        $invoice_no_delivery = explode('<br>', $delivery_order['invoice_no']);
        foreach ($invoice_no_order as $key => $value)
        {
            $delivery_key = array_search($value, $invoice_no_delivery);
            if ($delivery_key !== false)
            {
                unset($invoice_no_order[$key], $invoice_no_delivery[$delivery_key]);
                if (count($invoice_no_delivery) == 0)
                {
                    break;
                }
            }
        }
        $_order['invoice_no'] = implode('<br>', $invoice_no_order);

        /* 更新配送状态 */
        $order_finish = get_all_delivery_finish($order_id);
        $shipping_status = ($order_finish == -1) ? SS_SHIPPED_PART : SS_SHIPPED_ING;
        $arr['shipping_status']     = $shipping_status;
        if ($shipping_status == SS_SHIPPED_ING)
        {
            $arr['shipping_time']   = ''; // 发货时间
        }
        $arr['invoice_no']          = $_order['invoice_no'];
        update_order($order_id, $arr);

        /* 发货单取消发货记录log */
        order_action($order['order_sn'], $order['order_status'], $shipping_status, $order['pay_status'], $action_note, $_SESSION['seller_name'], 1);

        /* 如果使用库存，则增加库存 */
        if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP)
        {
            // 检查此单发货商品数量
            $virtual_goods = array();
            $delivery_stock_sql = "SELECT DG.goods_id, DG.product_id, DG.is_real, SUM(DG.send_number) AS sums
            FROM " . $GLOBALS['ecs']->table('delivery_goods') . " AS DG
            WHERE DG.delivery_id = '$delivery_id'
            GROUP BY DG.goods_id ";
            $delivery_stock_result = $GLOBALS['db']->getAll($delivery_stock_sql);
            foreach ($delivery_stock_result as $key => $value)
            {
                /* 虚拟商品 */
                if ($value['is_real'] == 0)
                {
                    continue;
                }

                //（货品）
                if (!empty($value['product_id']))
                {
                    $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products') . "
                                    SET product_number = product_number + " . $value['sums'] . "
                                    WHERE product_id = " . $value['product_id'];
                    $GLOBALS['db']->query($minus_stock_sql, 'SILENT');
                }

                $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('goods') . "
                                SET goods_number = goods_number + " . $value['sums'] . "
                                WHERE goods_id = " . $value['goods_id'];
                $GLOBALS['db']->query($minus_stock_sql, 'SILENT');
            }
        }

        /* 发货单全退回时，退回其它 */
        if ($order['order_status'] == SS_SHIPPED_ING)
        {
            /* 如果订单用户不为空，计算积分，并退回 */
            if ($order['user_id'] > 0)
            {
                /* 取得用户信息 */
                $user = user_info($order['user_id']);

                /* 计算并退回积分 */
                $integral = integral_to_give($order);
                log_account_change($order['user_id'], 0, 0, (-1) * intval($integral['rank_points']), (-1) * intval($integral['custom_points']), sprintf(L('return_order_gift_integral'), $order['order_sn']));

                /* todo 计算并退回红包 */
                return_order_bonus($order_id);
            }
        }

        /* 清除缓存 */
        clear_cache_files();

        /* 操作成功 */
        $href = url('order/delivery_info', ['delivery_id'=>$delivery_id]);
        $links[] = array('text' => L('delivery_sn') . L('detail'), 'href' => $href);
        sys_msg(L('act_ok'), $links);
    }

    //发货处理
    public function actionDeliveryShip() {
        global $ecs, $db, $_CFG;

        /* 检查权限 */
        admin_priv('delivery_view');


        /* 定义当前时间 */
        define('GMTIME_UTC', gmtime()); // 获取 UTC 时间戳

        /* 取得参数 */
        $delivery   = array();
        $order_id   = intval(trim($_REQUEST['order_id']));        // 订单id
        $delivery_id   = intval(trim($_REQUEST['delivery_id']));        // 发货单id
        $delivery['invoice_no'] = isset($_REQUEST['invoice_no']) ? trim($_REQUEST['invoice_no']) : '';
        $action_note    = isset($_REQUEST['action_note']) ? trim($_REQUEST['action_note']) : '';

        /* 根据发货单id查询发货单信息 */
        if (!empty($delivery_id))
        {
            $delivery_order = delivery_order_info($delivery_id);
        }
        else
        {
            die('order does not exist');
        }

        /* 查询订单信息 */
        $order = seller_order_info($order_id);
        /* 检查此单发货商品库存缺货情况  ecmoban模板堂 --zhuo start 下单减库存*/
        $delivery_stock_sql = "SELECT G.model_attr, G.model_inventory, DG.goods_id, DG.delivery_id, DG.is_real, DG.send_number AS sums, G.goods_number AS storage, G.goods_name, DG.send_number," .
            " OG.goods_attr_id, OG.warehouse_id, OG.area_id, OG.ru_id, OG.order_id, OG.product_id FROM " . $GLOBALS['ecs']->table('delivery_goods') . " AS DG, " .
            $GLOBALS['ecs']->table('goods') . " AS G, " .
            $GLOBALS['ecs']->table('delivery_order') . " AS D, " .
            $GLOBALS['ecs']->table('order_goods') . " AS OG " .
            " WHERE DG.goods_id = G.goods_id AND DG.delivery_id = D.delivery_id AND D.order_id = OG.order_id AND DG.delivery_id = '$delivery_id' GROUP BY OG.rec_id ";
        $delivery_stock_result = $GLOBALS['db']->getAll($delivery_stock_sql);

        $virtual_goods = array();
        for($i=0; $i<count($delivery_stock_result); $i++){
            if($delivery_stock_result[$i]['model_attr'] == 1){
                $table_products = "products_warehouse";
                $type_files = " and warehouse_id = '" .$delivery_stock_result[$i]['warehouse_id']. "'";
            }elseif($delivery_stock_result[$i]['model_attr'] == 2){
                $table_products = "products_area";
                $type_files = " and area_id = '" .$delivery_stock_result[$i]['area_id']. "'";
            }else{
                $table_products = "products";
                $type_files = "";
            }

            $sql = "SELECT * FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '" .$delivery_stock_result[$i]['goods_id']. "'" .$type_files. " LIMIT 0, 1";
            $prod = $GLOBALS['db']->getRow($sql);
            /* 如果商品存在规格就查询规格，如果不存在规格按商品库存查询 */
            if(empty($prod)){
                if($delivery_stock_result[$i]['model_inventory'] == 1){
                    $delivery_stock_result[$i]['storage'] = get_warehouse_area_goods($delivery_stock_result[$i]['warehouse_id'], $delivery_stock_result[$i]['goods_id'], 'warehouse_goods');
                }elseif($delivery_stock_result[$i]['model_inventory'] == 2){
                    $delivery_stock_result[$i]['storage'] = get_warehouse_area_goods($delivery_stock_result[$i]['area_id'], $delivery_stock_result[$i]['goods_id'], 'warehouse_area_goods');
                }
            }else{
                $products = get_warehouse_id_attr_number($delivery_stock_result[$i]['goods_id'], $delivery_stock_result[$i]['goods_attr_id'], $delivery_stock_result[$i]['ru_id'], $delivery_stock_result[$i]['warehouse_id'], $delivery_stock_result[$i]['area_id'], $delivery_stock_result[$i]['model_attr']);
                $delivery_stock_result[$i]['storage'] = $products['product_number'];
            }

            if (($delivery_stock_result[$i]['sums'] > $delivery_stock_result[$i]['storage'] || $delivery_stock_result[$i]['storage'] <= 0) && (($_CFG['use_storage'] == '1'  && $_CFG['stock_dec_time'] == SDT_SHIP) || ($_CFG['use_storage'] == '0' && $delivery_stock_result[$i]['is_real'] == 0)))
            {

                $href = url('order/delivery_info', ['delivery_id'=>$delivery_id]);
                /* 操作失败 */
                $links[] = array('text' => L('order_info'), 'href' => $href);
                sys_msg(sprintf(L('act_good_vacancy'), $value['goods_name']), 1, $links);
                break;
            }

            /* 虚拟商品列表 virtual_card*/
            if ($delivery_stock_result[$i]['is_real'] == 0)
            {
                $virtual_goods[] = array(
                    'goods_id' => $delivery_stock_result[$i]['goods_id'],
                    'goods_name' => $delivery_stock_result[$i]['goods_name'],
                    'num' => $delivery_stock_result[$i]['send_number']
                );
            }
        }
        //ecmoban模板堂 --zhuo end 下单减库存

        /* 发货 */
        /* 处理虚拟卡 商品（虚货） */
        if ($virtual_goods && is_array($virtual_goods) && count($virtual_goods) > 0)
        {
            foreach ($virtual_goods as $virtual_value)
            {
                virtual_card_shipping($virtual_value,$order['order_sn'], $msg, 'split');
            }

            //虚拟卡缺货
            if(!empty($msg)){
                $links[] = array('text' =>L('delivery_sn') . L('detail'), 'href' => 'order.php?act=delivery_info&delivery_id=' . $delivery_id);
                sys_msg($msg, 1, $links);
            }
        }

        /* 如果使用库存，且发货时减库存，则修改库存 */
        if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP)
        {

            foreach ($delivery_stock_result as $value)
            {

                /* 商品（实货）、超级礼包（实货） ecmoban模板堂 --zhuo */
                if ($value['is_real'] != 0)
                {
                    //（货品）
                    if (!empty($value['product_id']))
                    {
                        if($value['model_attr'] == 1){
                            $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products_warehouse') . "
                                            SET product_number = product_number - " . $value['sums'] . "
                                            WHERE product_id = " . $value['product_id'];
                        }elseif($value['model_attr'] == 2){
                            $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products_area') . "
                                            SET product_number = product_number - " . $value['sums'] . "
                                            WHERE product_id = " . $value['product_id'];
                        }else{
                            $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products') . "
                                            SET product_number = product_number - " . $value['sums'] . "
                                            WHERE product_id = " . $value['product_id'];
                        }

                    }else{
                        if($value['model_inventory'] == 1){
                            $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('warehouse_goods') . "
                                            SET region_number = region_number - " . $value['sums'] . "
                                            WHERE goods_id = " . $value['goods_id'] . " AND region_id = " . $value['warehouse_id'];
                        }elseif($value['model_inventory'] == 2){
                            $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('warehouse_area_goods') . "
                                            SET region_number = region_number - " . $value['sums'] . "
                                            WHERE goods_id = " . $value['goods_id'] . " AND region_id = " . $value['area_id'];
                        }else{
                            $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('goods') . "
                                            SET goods_number = goods_number - " . $value['sums'] . "
                                            WHERE goods_id = " . $value['goods_id'];
                        }
                    }

                    $GLOBALS['db']->query($minus_stock_sql, 'SILENT');

                    //库存日志
                    $logs_other = array(
                        'goods_id' =>$value['goods_id'],
                        'order_id' => $value['order_id'],
                        'use_storage' =>$_CFG['stock_dec_time'],
                        'admin_id' =>$_SESSION['seller_id'],
                        'number' => "- " . $value['sums'],
                        'model_inventory' =>$value['model_inventory'],
                        'model_attr' =>$value['model_attr'],
                        'product_id' =>$value['product_id'],
                        'warehouse_id' =>$value['warehouse_id'],
                        'area_id' =>$value['area_id'],
                        'add_time' => gmtime()
                    );

                    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('goods_inventory_logs'), $logs_other, 'INSERT');
                }
            }
        }

        /* 修改发货单信息 */
        $invoice_no = str_replace(',', '<br>', $delivery['invoice_no']);
        $invoice_no = trim($invoice_no, '<br>');
        $_delivery['invoice_no'] = $invoice_no;
        $_delivery['status'] = 0; // 0，为已发货
        $query = $db->autoExecute($ecs->table('delivery_order'), $_delivery, 'UPDATE', "delivery_id = $delivery_id", 'SILENT');
        if (!$query)
        {
            /* 操作失败 */
            $href = url('order/delivery_info', ['delivery_id'=>$delivery_id]);
            $links[] = array('text' =>L('delivery_sn') . L('detail'), 'href' => $href);
            sys_msg(L('act_false'), 1, $links);
        }

        /* 标记订单为已确认 “已发货” */
        /* 更新发货时间 */
        $order_finish = get_all_delivery_finish($order_id);
        $shipping_status = ($order_finish == 1) ? SS_SHIPPED : SS_SHIPPED_PART;
        $arr['shipping_status']     = $shipping_status;
        $arr['shipping_time']       = GMTIME_UTC; // 发货时间
        $arr['invoice_no'] = !empty($invoice_no) ? $invoice_no : $order['invoice_no'];
        update_order($order_id, $arr);

        /* 发货单发货记录log */
        order_action($order['order_sn'], OS_CONFIRMED, $shipping_status, $order['pay_status'], $action_note, $_SESSION['seller_name'], 1);

        /* 如果当前订单已经全部发货 */
        if ($order_finish)
        {
            /* 如果订单用户不为空，计算积分，并发给用户；发红包 */
            if ($order['user_id'] > 0)
            {
                /* 取得用户信息 */
                $user = user_info($order['user_id']);

                /* 计算并发放积分 */
                $integral = integral_to_give($order);
                /*如果已配送子订单的赠送积分大于0   减去已配送子订单积分*/
                if(!empty($child_order)){
                    $integral['custom_points']=$integral['custom_points']-$child_order['custom_points'];
                    $integral['rank_points']=$integral['rank_points']-$child_order['rank_points'];
                }
                log_account_change($order['user_id'], 0, 0, intval($integral['rank_points']), intval($integral['custom_points']), sprintf(L('order_gift_integral'), $order['order_sn']));

                /* 发放红包 */
                send_order_bonus($order_id);

                /* 发放优惠券 bylu */
                send_order_coupons($order_id);
            }

            /* 发送邮件 */
            $cfg = $_CFG['send_ship_email'];
            if ($cfg == '1')
            {
                $order['invoice_no'] = $invoice_no;
                $tpl = get_mail_template('deliver_notice');
                $this->assign('order', $order);
                $this->assign('send_time', local_date($_CFG['time_format']));
                $this->assign('shop_name', $_CFG['shop_name']);
                $this->assign('send_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
                $this->assign('sent_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
                $this->assign('confirm_url', $ecs->url() . 'user.php?act=order_detail&order_id=' . $order['order_id']); //by wu
                $this->assign('send_msg_url',$ecs->url() . 'user.php?act=message_list&order_id=' . $order['order_id']);
                $content = $this->fetch("", 'str:' . $tpl['template_content']);
                if (!send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html']))
                {
                    $msg = L('send_mail_fail');
                }
            }

            /* 如果需要，发短信 */
            if ($GLOBALS['_CFG']['sms_order_shipped'] == '1' && $order['mobile'] != '') {

                //短信接口参数
                if ($order['ru_id']) {
                    $shop_name = get_shop_name($order['ru_id'], 1);
                } else {
                    $shop_name = "";
                }

                $user_info = get_admin_user_info($order['user_id']);

                $smsParams = array(
                    'shop_name' => $shop_name,
                    'shopname' => $shop_name,
                    'user_name' => $user_info['user_name'],
                    'username' => $user_info['user_name'],
                    'consignee' => $order['consignee'],
                    'order_sn' => $order['order_sn'],
                    'ordersn' => $order['order_sn'],
                    'mobile_phone' => $order['mobile'],
                    'mobilephone' => $order['mobile']
                );

                $send_result = send_sms($order['mobile'], 'sms_order_shipped', $smsParams);
//                if ($GLOBALS['_CFG']['sms_type'] == 0) {
//
//                    huyi_sms($smsParams, 'sms_order_shipped');
//
//                } elseif ($GLOBALS['_CFG']['sms_type'] >=1) {
//
//                    $result = sms_ali($smsParams, 'sms_order_shipped'); //阿里大鱼短信变量传值，发送时机传值
//
//                    if ($result) {
//                        $resp = $GLOBALS['ecs']->ali_yu($result);
//                    } else {
//                        sys_msg('阿里大鱼短信配置异常', 1);
//                    }
//                }
            }

            /* 更新商品销量 */
            get_goods_sale($order_id);
        }

        // 微信通模板消息 发货通知
        $file = ROOT_PATH .'mobile/app/Http/Wechat/Controllers/Index.php';
        if(file_exists($file) && $order['user_id'] > 0){
            $pushUrl = str_replace('/seller', '', $GLOBALS['ecs']->url());
            $pushData = array(
                'first' => array('value' => '您的订单已发货'),
                'keyword1' => array('value' => $order['order_sn']), //订单
                'keyword2' => array('value' => $order['shipping_name']), //物流服务
                'keyword3' => array('value' => $order['invoice_no']),  //快递单号
                'keyword4' => array('value' => $order['consignee']),  // 收货信息
                'remark' => array('value' => '订单正在配送中，请您耐心等待')
            );
            $code = 'OPENTM202243318';
            $order_url = $pushUrl . 'mobile/index.php?r=user/order/detail&order_id='.$order_id;
            $order_url = urlencode(base64_encode($order_url));
            //以json格式传输
            $data = urlencode(serialize($pushData));
            $url = $pushUrl . 'mobile/?r=wechat/api&user_id='.$order['user_id'].'&code='.urlencode($code).'&pushData='.$data.'&url='.$order_url;
            curlGet($url);
        }

        /* 清除缓存 */
        clear_cache_files();

        /* 操作成功 */
        $href = url('order/delivery_list');
        $links[] = array('text' => L('09_delivery_order'), 'href' => $href);
        sys_msg(L('act_ok'), 0, $links);
    }

    public function actionEdit() {
        global $ecs, $db, $_CFG;
        $adminru['ru_id'] = $_SESSION['user_id'];

        $this->assign('page_title', '编辑订单');

        /* 检查权限 */
        admin_priv('order_edit');

        /* 取得参数 order_id */
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        $this->assign('order_id', $order_id);

        /* 取得参数 step */
        $step_list = array('user', 'goods', 'consignee', 'shipping', 'payment', 'other', 'money');
        $step = isset($_GET['step']) && in_array($_GET['step'], $step_list) ? $_GET['step'] : 'user';
        $this->assign('step', $step);

        $warehouse_list = get_warehouse_list_goods();
        $this->assign('warehouse_list',			$warehouse_list); //仓库列表

        /* 取得参数 act */
        $act = $_GET['act'];
        $this->assign('ur_here',L('add_order'));
        $this->assign('step_act', $act);

        /* 取得订单信息 */
        if ($order_id > 0)
        {
            $order = seller_order_info($order_id);

            $sql = "SELECT COUNT(*) FROM " .$GLOBALS['ecs']->table('order_goods'). " WHERE order_id = '$order_id'";
            $goods_count = $GLOBALS['db']->getOne($sql);


            if ($goods_count > 0) {
                if ($order['ru_id'] != $adminru['ru_id']) {
                    $Loaction = url('supplier/order/index');
                    ecs_header("Location: $Loaction\n");
                    exit;
                }
            }

            if($order['invoice_type'] == 1){
                $user_id = $order['user_id'];
                $sql = " SELECT * FROM " . $ecs->table('users_vat_invoices_info') . " WHERE user_id = '$user_id' LIMIT 1";
                $res = $db->getRow($sql);
                $this->assign('vat_info',$res);
            }

            /* 发货单格式化 */
            $order['invoice_no'] = str_replace('<br>', ',', $order['invoice_no']);

            /* 如果已发货，就不能修改订单了（配送方式和发货单号除外） */
            if ($order['shipping_status'] == SS_SHIPPED || $order['shipping_status'] == SS_RECEIVED)
            {
                if ($step != 'shipping')
                {
                    sys_msg(L('cannot_edit_order_shipped'));
                }
                else
                {
                    $step = 'invoice';
                    $this->assign('step', $step);
                }
            }

            $this->assign('order', $order);
        }
        else
        {
            if ($act != 'add' || $step != 'user')
            {
                die('invalid params');
            }
        }

        /* 选择会员 */
        if ('user' == $step)
        {
            // 无操作
        }

        /* 增删改商品 */
        elseif ('goods' == $step)
        {
            /* 取得订单商品 */
            $goods_list = order_goods($order_id);
            if (!empty($goods_list))
            {
                foreach ($goods_list AS $key => $goods)
                {
                    /* 计算属性数 */
                    $attr = $goods['goods_attr'];
                    if ($attr == '')
                    {
                        $goods_list[$key]['rows'] = 1;
                    }
                    else
                    {
                        $goods_list[$key]['rows'] = count(explode(chr(13), $attr));
                    }
                }
            }

            $this->assign('goods_list', $goods_list);

            /* 取得商品总金额 */
            $this->assign('goods_amount', order_amount($order_id));
        }

        // 设置收货人
        elseif ('consignee' == $step)
        {
            $this->assign('menu_select',array('action' => '04_order', 'current' => '02_order_list'));
            /* 查询是否存在实体商品 */
            $exist_real_goods = exist_real_goods($order_id);
            $this->assign('exist_real_goods', $exist_real_goods);

            /* 取得收货地址列表 */
            if ($order['user_id'] > 0)
            {
                $this->assign('address_list', address_list($order['user_id']));

                $address_id = isset($_REQUEST['address_id']) ? intval($_REQUEST['address_id']) : 0;
                if ($address_id > 0)
                {
                    $address = address_info($address_id);
                    var_dump($address);
                    if ($address)
                    {
                        $order['consignee']     = $address['consignee'];
                        $order['country']       = $address['country'];
                        $order['province']      = $address['province'];
                        $order['city']          = $address['city'];
                        $order['district']      = $address['district'];
                        $order['street']        = $address['street'];

                        $order['consignee_name']     = $address['consignee'];
                        $order['country_name']       = $address['country'];
                        $order['province_name']      = $address['province'];
                        $order['city_name']          = $address['city'];
                        $order['district_name']      = $address['district'];
                        $order['street_name']        = $address['street'];

                        $order['email']         = $address['email'];
                        $order['address']       = $address['address'];
                        $order['zipcode']       = $address['zipcode'];
                        $order['tel']           = $address['tel'];
                        $order['mobile']        = $address['mobile'];
                        $order['sign_building'] = $address['sign_building'];
                        $order['best_time']     = $address['best_time'];
                        $this->assign('order', $order);
                    }
                }
            }

            if ($exist_real_goods) {
                /* 取得国家 */
                $this->assign('country_list', get_regions());
                $this->assign('province_list', get_region_name($order['province'])['region_name']);
                $this->assign('city_list', get_region_name($order['city'])['region_name']);
                $this->assign('district_list', get_region_name($order['district'])['region_name']);
                $this->assign('street_list', get_region_name($order['street'])['region_name']);

            }
        }

        // 选择配送方式
        elseif ('shipping' == $step)
        {
            /* 如果不存在实体商品 */
            if (!exist_real_goods($order_id))
            {
                die ('Hacking Attemp');
            }

            /* 取得可用的配送方式列表 */
            $region_id_list = array(
                $order['country'], $order['province'], $order['city'], $order['district'], $order['street']
            );
            $shipping_list = available_shipping_list($region_id_list, $order['ru_id']);

            $consignee = array(
                'country'       => $order['country'],
                'province'      => $order['province'],
                'city'          => $order['city'],
                'district'      => $order['district']
            );

            $goods_list = order_goods($order_id);
            $cart_goods = $goods_list;

            $shipping_fee = 0;
            /* 取得配送费用 */
            foreach ($shipping_list AS $key => $val)
            {
                if (substr($val['shipping_code'], 0, 5) != 'ship_') {
                    if ($GLOBALS['_CFG']['freight_model'] == 0) {

                        /* 商品单独设置运费价格 start */
                        if ($cart_goods) {
                            if (count($cart_goods) == 1) {

                                $cart_goods = array_values($cart_goods);

                                if (!empty($cart_goods[0]['freight']) && $cart_goods[0]['is_shipping'] == 0) {

                                    if ($cart_goods[0]['freight'] == 1) {
                                        $configure_value = $cart_goods[0]['shipping_fee'] * $cart_goods[0]['goods_number'];
                                    } else {

                                        $trow = get_goods_transport($cart_goods[0]['tid']);

                                        if ($trow['freight_type']) {

                                            $cart_goods[0]['user_id'] = $cart_goods[0]['ru_id'];
                                            $transport_tpl = get_goods_transport_tpl($cart_goods[0], $region, $val, $cart_goods[0]['goods_number']);

                                            $configure_value = isset($transport_tpl['shippingFee']) ? $transport_tpl['shippingFee'] : 0;
                                        } else {

                                            /**
                                             * 商品运费模板
                                             * 自定义
                                             */
                                            $custom_shipping = get_goods_custom_shipping($cart_goods);

                                            $transport = array('top_area_id', 'area_id', 'tid', 'ru_id', 'sprice');
                                            $transport_where = " AND ru_id = '" . $cart_goods[0]['ru_id'] . "' AND tid = '" . $cart_goods[0]['tid'] . "'";
                                            $goods_transport = $GLOBALS['ecs']->get_select_find_in_set(2, $consignee['city'], $transport, $transport_where, 'goods_transport_extend', 'area_id');

                                            $ship_transport = array('tid', 'ru_id', 'shipping_fee');
                                            $ship_transport_where = " AND ru_id = '" . $cart_goods[0]['ru_id'] . "' AND tid = '" . $cart_goods[0]['tid'] . "'";
                                            $goods_ship_transport = $GLOBALS['ecs']->get_select_find_in_set(2, $val['shipping_id'], $ship_transport, $ship_transport_where, 'goods_transport_express', 'shipping_id');

                                            $goods_transport['sprice'] = isset($goods_transport['sprice']) ? $goods_transport['sprice'] : 0;
                                            $goods_ship_transport['shipping_fee'] = isset($goods_ship_transport['shipping_fee']) ? $goods_ship_transport['shipping_fee'] : 0;

                                            /* 是否免运费 start */
                                            if ($custom_shipping && $custom_shipping[$cart_goods[0]['tid']]['amount'] >= $trow['free_money'] && $trow['free_money'] > 0) {
                                                $is_shipping = 1; /* 免运费 */
                                            } else {
                                                $is_shipping = 0; /* 有运费 */
                                            }
                                            /* 是否免运费 end */

                                            if ($is_shipping == 0) {
                                                if ($trow['type'] == 1) {
                                                    $configure_value = $goods_transport['sprice'] * $cart_goods[0]['goods_number'] + $goods_ship_transport['shipping_fee'] * $cart_goods[0]['goods_number'];
                                                } else {
                                                    $configure_value = $goods_transport['sprice'] + $goods_ship_transport['shipping_fee'];
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    /* 有配送按配送区域计算运费 */
                                    $configure_type = 1;
                                }
                            } else {
                                $order_transpor = get_order_transport($cart_goods, $consignee, $val['shipping_id'], $val['shipping_code']);

                                if ($order_transpor['freight']) {
                                    /* 有配送按配送区域计算运费 */
                                    $configure_type = 1;
                                }

                                $configure_value = isset($order_transpor['sprice']) ? $order_transpor['sprice'] : 0;
                            }
                        }
                        /* 商品单独设置运费价格 end */

                        $shipping_fee = $configure_value;
                    }

                    $shipping_cfg = unserialize_config($val['configure']);

                    $shipping_list[$key]['shipping_id'] = $val['shipping_id'];
                    $shipping_list[$key]['shipping_name'] = $val['shipping_name'];
                    $shipping_list[$key]['shipping_code'] = $val['shipping_code'];
                    $shipping_list[$key]['format_shipping_fee'] = price_format($shipping_fee, false);
                    $shipping_list[$key]['shipping_fee'] = $shipping_fee;
                    $shipping_list[$key]['insure_formated'] = strpos($val['insure'], '%') === false ? price_format($val['insure'], false) : $val['insure'];
                    $shipping_list[$key]['format_free_money'] = price_format($shipping_cfg['free_money'], false);
                    $shipping_list[$key]['free_money'] = $shipping_cfg['free_money'];

                    /* 当前的配送方式是否支持保价 */
                    if ($val['shipping_id'] == $order['shipping_id']) {
                        $insure_disabled = ($val['insure'] == 0);
                        $cod_disabled = ($val['support_cod'] == 0);
                    }

                    $shipping_list[$key]['insure_disabled'] = $insure_disabled;
                    $shipping_list[$key]['cod_disabled'] = $cod_disabled;
                }

                // 兼容过滤ecjia配送方式
                if (substr($val['shipping_code'], 0, 5) == 'ship_') {
                    unset($shipping_list[$key]);
                }
            }


            $this->assign('shipping_list', $shipping_list);
        }

        // 选择支付方式
        elseif ('payment' == $step)
        {
            $this->assign('menu_select',array('action' => '04_order', 'current' => '02_order_list'));
            /* 取得可用的支付方式列表 */
            if (exist_real_goods($order_id))
            {
                /* 存在实体商品 */
                $region_id_list = array(
                    $order['country'], $order['province'], $order['city'], $order['district'], $order['street']
                );
                $shipping_area = shipping_info($order['shipping_id']);
                $pay_fee = ($shipping_area['support_cod'] == 1) ? $shipping_area['pay_fee'] : 0;

                $payment_list = available_payment_list($shipping_area['support_cod'], $pay_fee);
            }
            else
            {
                /* 不存在实体商品 */
                $payment_list = available_payment_list(false);
            }

            /* 过滤掉使用余额支付 */
            foreach ($payment_list as $key => $payment)
            {
                if ($payment['pay_code'] == 'balance')
                {
                    unset($payment_list[$key]);
                }
            }
            $this->assign('payment_list', $payment_list);
        }

        // 选择包装、贺卡
        elseif ('other' == $step)
        {
            $this->assign('menu_select',array('action' => '04_order', 'current' => '02_order_list'));
            /* 查询是否存在实体商品 */
            $exist_real_goods = exist_real_goods($order_id);
            $this->assign('exist_real_goods', $exist_real_goods);

            if ($exist_real_goods)
            {
                /* 取得包装列表 */
                $this->assign('pack_list', pack_list());

                /* 取得贺卡列表 */
                $this->assign('card_list', card_list());
            }
        }

        // 费用
        elseif ('money' == $step)
        {
            /* 查询是否存在实体商品 */
            $exist_real_goods = exist_real_goods($order_id);
            $this->assign('exist_real_goods', $exist_real_goods);
            $this->assign('page_title', '编辑订单');

            /* 取得用户信息 */
            if ($order['user_id'] > 0)
            {
                $user = user_info($order['user_id']);

                /* 计算可用余额 */
                $this->assign('available_user_money', $order['surplus'] + $user['user_money']);

                /* 计算可用积分 */
                $this->assign('available_pay_points', $order['integral'] + $user['pay_points']);

                /* 取得用户可用红包 */
                $user_bonus = user_bonus($order['user_id'], $order['goods_amount']);

                $arr = array();
                foreach($user_bonus AS $key=>$row){
                    $sql = "SELECT order_id FROM " .$ecs->table('order_info'). " WHERE bonus_id = '" .$row['bonus_id']. "'";
                    if(!$db->getOne($sql)){
                        $arr[] = $row;
                    }
                }

                $this->assign('available_bonus', $arr);
            }
        }

        // 发货后修改配送方式和发货单号
        elseif ('invoice' == $step)
        {
            $this->assign('menu_select',array('action' => '04_order', 'current' => '02_order_list'));
            /* 如果不存在实体商品 */
            if (!exist_real_goods($order_id))
            {
                die ('Hacking Attemp');
            }

            /* 取得可用的配送方式列表 */
            $region_id_list = array(
                $order['country'], $order['province'], $order['city'], $order['district']
            );

            $shipping_list = available_shipping_list($region_id_list, $order['ru_id']);
            $this->assign('shipping_list', $shipping_list);
        }

        /* 显示模板 */
        assign_query_info();
        $this->display('order_step');
    }

    public function actionStepPost() {
        global $ecs, $db, $_CFG;
        /* 检查权限 */
        admin_priv('order_edit');

        /* 取得参数 step */
        $step_list = array('user', 'edit_goods', 'add_goods', 'goods', 'consignee', 'shipping', 'payment', 'other', 'money', 'invoice');
        $step = isset($_REQUEST['step']) && in_array($_REQUEST['step'], $step_list) ? $_REQUEST['step'] : 'user';

        /* 取得参数 order_id */
        $order_id = isset($_REQUEST['order_id']) ? intval($_REQUEST['order_id']) : 0;
        if ($order_id > 0)
        {
        $old_order =  seller_order_info($order_id);
        }

        /* 取得参数 step_act 添加还是编辑 */
        $step_act = isset($_REQUEST['step_act']) ? $_REQUEST['step_act'] : 'add';

        /* 插入订单信息 */
        if ('user' == $step)
        {
            /* 取得参数：user_id */
            $user_id = ($_POST['anonymous'] == 1) ? 0 : intval($_POST['user']);

            /* 插入新订单，状态为无效 */
            $order = array(
                'user_id'           => $user_id,
                'add_time'          => gmtime(),
                'order_status'      => OS_INVALID,
                'shipping_status'   => SS_UNSHIPPED,
                'pay_status'        => PS_UNPAYED,
                'from_ad'           => 0,
                'referer'           => L('admin')
            );

            do
            {
                $order['order_sn'] = get_order_sn();
                if ($db->autoExecute($ecs->table('order_info'), $order, 'INSERT', '', 'SILENT'))
                {
                    break;
                }
                else
                {
                    if ($db->errno() != 1062)
                    {
                        die($db->error());
                    }
                }
            }
            while (true); // 防止订单号重复

            $order_id = $db->insert_id();

            /* todo 记录日志 */
            admin_log($order['order_sn'], 'add', 'order');

            /* 记录log */
            $action_note = sprintf(L('add_order_info'), $_SESSION['seller_name']);
            order_action($order['order_sn'], $order['order_status'], $order['shipping_status'], $order['pay_status'], $action_note, $_SESSION['admin_name']);

            /* 插入 pay_log */
            $sql = 'INSERT INTO ' . $ecs->table('pay_log') . " (order_id, order_amount, order_type, is_paid)" .
                " VALUES ('$order_id', 0, '" . PAY_ORDER . "', 0)";
            $db->query($sql);

            /* 下一步 */
            ecs_header("Location: " . url('supplier/order/'.$step_act, ['order_id'=>$order_id, 'step'=>'goods']) . "\n");
            exit;
        }
        /* 编辑商品信息 */
        elseif ('edit_goods' == $step)
        {
            if (isset($_POST['rec_id']))
            {
                foreach ($_POST['rec_id'] AS $key => $rec_id)
                {
                    $sql = "SELECT warehouse_id, area_id FROM " .$GLOBALS['ecs']->table('order_goods'). " WHERE rec_id = '$rec_id' LIMIT 1";
                    $order_goods = $GLOBALS['db']->getRow($sql);

                    $sql = "SELECT goods_number ".
                        'FROM ' . $GLOBALS['ecs']->table('goods') .
                        "WHERE goods_id =".$_POST['goods_id'][$key];
                    /* 取得参数 */
                    $goods_price = floatval($_POST['goods_price'][$key]);
                    $goods_number = intval($_POST['goods_number'][$key]);
                    $goods_attr = $_POST['goods_attr'][$key];
                    $product_id = intval($_POST['product_id'][$key]);
                    if($product_id)
                    {

                        $sql = "SELECT product_number ".
                            'FROM ' . $GLOBALS['ecs']->table('products') .
                            " WHERE product_id =".$_POST['product_id'][$key];
                    }
                    $goods_number_all = $db->getOne($sql);
                    if($goods_number_all>=$goods_number)
                    {
                        /* 修改 */
                        $sql = "UPDATE " . $ecs->table('order_goods') .
                            " SET goods_price = '$goods_price', " .
                            "goods_number = '$goods_number', " .
                            "goods_attr = '$goods_attr', " .
                            "warehouse_id = '" .$order_goods['warehouse_id']. "', " .
                            "area_id = '" .$order_goods['area_id']. "' " .
                            "WHERE rec_id = '$rec_id' LIMIT 1";
                        $db->query($sql);
                    }
                    else
                    {
                        sys_msg(L('goods_num_err'));
                    }
                }

                /* 更新商品总金额和订单总金额 */
                $goods_amount = order_amount($order_id);
                update_order($order_id, array('goods_amount' => $goods_amount));
                update_order_amount($order_id);

                /* 更新 pay_log */
                update_pay_log($order_id);

                /* todo 记录日志 */
                $sn = $old_order['order_sn'];
                $new_order =  seller_order_info($order_id);
                if ($old_order['total_fee'] != $new_order['total_fee'])
                {
                    $sn .= ',' . sprintf(L('order_amount_change'), $old_order['total_fee'], $new_order['total_fee']);
                }
                admin_log($sn, 'edit', 'order');
            }

            /* 跳回订单商品 */
            ecs_header("Location: " . url('supplier/order/'.$step_act, ['order_id'=>$order_id, 'step'=>'goods']) . "\n");
            exit;
        }
        /* 添加商品 */
        elseif ('add_goods' == $step)
        {
            /* 取得参数 */
            $goods_id = intval($_POST['goodslist']);
            $warehouse_id = intval($_POST['warehouse_id']);
            $area_id = intval($_POST['area_id']);
            $model_attr = intval($_POST['model_attr']);
            $attr_price = $_POST['attr_price'];

            $goods_price = $_POST['add_price'] != 'user_input' ? floatval($_POST['add_price']) : floatval($_POST['input_price']);
            $goods_price = $goods_price + $attr_price;

            $sql = "SELECT user_id FROM " .$GLOBALS['ecs']->table('goods'). " WHERE goods_id = '$goods_id' LIMIT 0, 1";
            $goods_info = $GLOBALS['db']->getRow($sql);

            $goods_attr = '0';
            for ($i = 0; $i < $_POST['spec_count']; $i++)
            {
                if (is_array($_POST['spec_' . $i]))
                {
                    $temp_array = $_POST['spec_' . $i];
                    $temp_array_count = count($_POST['spec_' . $i]);
                    for ($j = 0; $j < $temp_array_count; $j++)
                    {
                        if($temp_array[$j]!==NULL)
                        {
                            $goods_attr .= ',' . $temp_array[$j];
                        }
                    }
                }
                else
                {
                    if($_POST['spec_' . $i]!==NULL)
                    {
                        $goods_attr .= ',' . $_POST['spec_' . $i];
                    }
                }
            }
            $goods_number = $_POST['add_number'];
            $attr_list = $goods_attr;

            $goods_attr = explode(',',$goods_attr);
            $k   =   array_search(0,$goods_attr);
            unset($goods_attr[$k]);

            //ecmoban模板堂 --zhuo start
            $attr_leftJoin = '';
            $select = '';
            if ($model_attr == 1) {
                $select = " wap.attr_price as warehouse_attr_price, ";
                $attr_leftJoin = 'LEFT JOIN ' . $GLOBALS['ecs']->table('warehouse_attr') . " AS wap ON g.goods_attr_id = wap.goods_attr_id AND wap.warehouse_id = '$warehouse_id' ";
            } elseif ($model_attr == 2) {
                $select = " waa.attr_price as area_attr_price, ";
                $attr_leftJoin = 'LEFT JOIN ' . $GLOBALS['ecs']->table('warehouse_area_attr') . " AS waa ON g.goods_attr_id = waa.goods_attr_id AND area_id = '$area_id' ";
            }

            $attr_value = "";
            if ($attr_list) {
                $where = "g.goods_attr_id in($attr_list)";

                $sql = "SELECT g.attr_value, " . $select . " g.attr_price " .
                    'FROM ' . $GLOBALS['ecs']->table('goods_attr') . " AS g " .
                    "LEFT JOIN" . $ecs->table('attribute') . " AS a ON g.attr_id = a.attr_id " .
                    $attr_leftJoin .
                    "WHERE $where ORDER BY a.sort_order, a.attr_id, g.goods_attr_id";

                $res = $db->query($sql);
                while ($row = $db->fetchRow($res)) {
                    if ($model_attr == 1) {
                        $row['attr_price'] = $row['warehouse_attr_price'];
                    } elseif ($model_attr == 2) {
                        $row['attr_price'] = $row['area_attr_price'];
                    } else {
                        $row['attr_price'] = $row['attr_price'];
                    }

                    $attr_price = '';
                    if ($row['attr_price'] > 0) {
                        $attr_price = ":[" . price_format($row['attr_price']) . "]";
                    }
                    $attr_value[] = $row['attr_value'] . $attr_price;
                }
                //ecmoban模板堂 --zhuo end

                if ($attr_value) {
                    $attr_value = implode(",", $attr_value);
                }
            }

            //ecmoban模板堂 --zhuo start
            if($model_attr == 1){
                $table_products = "products_warehouse";
                $type_files = " and warehouse_id = '$warehouse_id'";
            }elseif($model_attr == 2){
                $table_products = "products_area";
                $type_files = " and area_id = '$area_id'";
            }else{
                $table_products = "products";
                $type_files = "";
            }

            $sql = "SELECT * FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '$goods_id'" .$type_files. " LIMIT 0, 1";
            $prod = $GLOBALS['db']->getRow($sql);
            //ecmoban模板堂 --zhuo end

            if (is_spec($goods_attr) && !empty($prod))
            {
                $product_info = get_products_info($goods_id, $goods_attr, $warehouse_id, $area_id); //ecmoban模板堂 --zhuo
            }

            //商品存在规格 是货品 检查该货品库存
            if (is_spec($goods_attr) && !empty($prod))
            {
                if (!empty($goods_attr))
                {
                    /* 取规格的货品库存 */
                    if ($goods_number > $product_info['product_number'])
                    {
                        $url = "order.php?act=" . $step_act . "&order_id=" . $order_id . "&step=goods";

                        echo '<a href="'.$url.'">'.L('goods_num_err') .'</a>';
                        exit;

                        return false;
                    }
                }
            }

            if(is_spec($goods_attr) && !empty($prod))
            {
                /* 插入订单商品 */
                $sql = "INSERT INTO " . $ecs->table('order_goods') .
                    "(order_id, goods_id, goods_name, goods_sn, product_id, goods_number, market_price, " .
                    "goods_price, goods_attr, is_real, extension_code, parent_id, is_gift, goods_attr_id, model_attr, warehouse_id, area_id, ru_id) " .
                    "SELECT '$order_id', goods_id, goods_name, goods_sn, " .$product_info['product_id'].", ".
                    "'$goods_number', market_price, '$goods_price', '" .$attr_value . "', " .
                    "is_real, extension_code, 0, 0 , '".implode(',',$goods_attr)."', '$model_attr', '$warehouse_id', '$area_id', '" .$goods_info['user_id']. "' " .
                    "FROM " . $ecs->table('goods') .
                    " WHERE goods_id = '$goods_id' LIMIT 1";
            }
            else
            {
                $sql = "INSERT INTO " . $ecs->table('order_goods') .
                    " (order_id, goods_id, goods_name, goods_sn, " .
                    "goods_number, market_price, goods_price, goods_attr, " .
                    "is_real, extension_code, parent_id, is_gift, model_attr, warehouse_id, area_id, ru_id) " .
                    "SELECT '$order_id', goods_id, goods_name, goods_sn, " .
                    "'$goods_number', market_price, '$goods_price', '" . $attr_value. "', " .
                    "is_real, extension_code, 0, 0, '$model_attr', '$warehouse_id', '$area_id', '" .$goods_info['user_id']. "' " .
                    "FROM " . $ecs->table('goods') .
                    " WHERE goods_id = '$goods_id' LIMIT 1";
            }
            $db->query($sql);

            /* 如果使用库存，且下订单时减库存，则修改库存 */
            if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_PLACE)
            {
                //ecmoban模板堂 --zhuo start
                $model_inventory = get_table_date("goods", "goods_id = '$goods_id'", array('model_inventory'), 2);

                //（货品）
                if (!empty($product_info['product_id']))
                {
                    if($model_attr == 1){
                        $sql = "UPDATE " . $GLOBALS['ecs']->table('products_warehouse') . "
                                    SET product_number = product_number - " . $goods_number . "
                                    WHERE product_id = " . $product_info['product_id'];
                    }elseif($model_attr == 2){
                        $sql = "UPDATE " . $GLOBALS['ecs']->table('products_area') . "
                                    SET product_number = product_number - " . $goods_number . "
                                    WHERE product_id = " . $product_info['product_id'];
                    }else{
                        $sql = "UPDATE " . $GLOBALS['ecs']->table('products') . "
                                    SET product_number = product_number - " . $goods_number . "
                                    WHERE product_id = " . $product_info['product_id'];
                    }

                }else{
                    if($model_inventory == 1){
                        $sql = "UPDATE " . $GLOBALS['ecs']->table('warehouse_goods') . "
                                    SET region_number = region_number - " . $goods_number . "
                                    WHERE goods_id = '$goods_id' AND region_id = '$warehouse_id'";
                    }elseif($model_inventory == 2){
                        $sql = "UPDATE " . $GLOBALS['ecs']->table('warehouse_area_goods') . "
                                    SET region_number = region_number - " . $goods_number . "
                                    WHERE goods_id = '$goods_id' AND region_id = '$area_id'";
                    }else{
                        $sql = "UPDATE " . $GLOBALS['ecs']->table('goods') . "
                                    SET goods_number = goods_number - " . $goods_number . "
                                    WHERE goods_id = '$goods_id'";
                    }
                }
                //ecmoban模板堂 --zhuo end

                $db->query($sql);
            }

            /* 更新商品总金额和订单总金额 */
            update_order($order_id, array('goods_amount' => order_amount($order_id)));
            update_order_amount($order_id);

            /* 更新 pay_log */
            update_pay_log($order_id);

            /* todo 记录日志 */
            $sn = $old_order['order_sn'];
            $new_order =  seller_order_info($order_id);
            if ($old_order['total_fee'] != $new_order['total_fee'])
            {
                $sn .= ',' . sprintf(L('order_amount_change'), $old_order['total_fee'], $new_order['total_fee']);
            }
            admin_log($sn, 'edit', 'order');

            /* 跳回订单商品 */
            ecs_header("Location: " . url('supplier/order/'.$step_act, ['order_id'=>$order_id, 'step'=>'goods']) . "\n");
            exit;
        }
        /* 商品 */
        elseif ('goods' == $step)
        {
            /* 下一步 */
            if (isset($_POST['next']))
            {
                ecs_header("Location: " . url('supplier/order/'.$step_act, ['order_id'=>$order_id, 'step'=>'consignee']) . "\n");
                exit;
            }
            /* 完成 */
            elseif (isset($_POST['finish']))
            {
                /* 初始化提示信息和链接 */
                $msgs   = array();
                $links  = array();

                /* 如果已付款，检查金额是否变动，并执行相应操作 */
                $order =  seller_order_info($order_id);
                handle_order_money_change($order, $msgs, $links);

                /* 显示提示信息 */
                if (!empty($msgs))
                {
                    sys_msg(join(chr(13), $msgs), 0, $links);
                }
                else
                {
                    /* 跳转到订单详情 */
                    ecs_header("Location: " . url('supplier/order/detail', ['order_id'=>$order_id]) . "\n");
                    exit;
                }
            }
        }
        /* 保存收货人信息 */
        elseif ('consignee' == $step)
        {
            /* 保存订单 */
            $order = $_POST;
            $order['agency_id'] = get_agency_by_regions(array($order['country'], $order['province'], $order['city'], $order['district']));
            update_order($order_id, $order);

            /* 该订单所属办事处是否变化 */
            $agency_changed = $old_order['agency_id'] != $order['agency_id'];

            /* todo 记录日志 */
            $sn = $old_order['order_sn'];
            admin_log($sn, 'edit', 'order');

            if (isset($_POST['next']))
            {
                /* 下一步 */
                if (exist_real_goods($order_id))
                {
                    /* 存在实体商品，去配送方式 */
                    ecs_header("Location: " . url('supplier/order/'.$step_act, ['order_id'=>$order_id, 'step'=>'shipping']) . "\n");
                    exit;
                }
                else
                {
                    /* 不存在实体商品，去支付方式 */
                    ecs_header("Location: " . url('supplier/order/'.$step_act, ['order_id'=>$order_id, 'step'=>'payment']) . "\n");
                    exit;
                }
            }
            elseif (isset($_POST['finish']))
            {
                /* 如果是编辑且存在实体商品，检查收货人地区的改变是否影响原来选的配送 */
                if ('edit' == $step_act && exist_real_goods($order_id))
                {
                    $order =  seller_order_info($order_id);

                    /* 取得可用配送方式 */
                    $region_id_list = array(
                        $order['country'], $order['province'], $order['city'], $order['district']
                    );
                    $shipping_list = available_shipping_list($region_id_list, $order['ru_id']);

                    /* 判断订单的配送是否在可用配送之内 */
                    $exist = false;
                    foreach ($shipping_list AS $shipping)
                    {
                        if ($shipping['shipping_id'] == $order['shipping_id'])
                        {
                            $exist = true;
                            break;
                        }
                    }

                    /* 如果不在可用配送之内，提示用户去修改配送 */
                    if (!$exist)
                    {
                        // 修改配送为空，配送费和保价费为0
                        update_order($order_id, array('shipping_id' => 0, 'shipping_name' => ''));
                        $links[] = array('text' => L('step')['shipping'], 'href' => 'order.php?act=edit&order_id=' . $order_id . '&step=shipping');
                        sys_msg(L('continue_shipping'), 1, $links);
                    }
                }

                /* 完成 */
                if ($agency_changed)
                {
                    $href = url('supplier/order/index');
                    ecs_header("Location: ".$href."\n");
                }
                else
                {
                    $href = url('supplier/order/detail', ['order_id'=>$order_id]);
                    ecs_header("Location: " . $href . "\n");
                }
                exit;
            }
        }
        /* 保存配送信息 */
        elseif ('shipping' == $step)
        {
            /* 如果不存在实体商品，退出 */
            if (!exist_real_goods($order_id))
            {
                die ('Hacking Attemp');
            }

            /* 取得订单信息 */
            $order_info =  seller_order_info($order_id);
            $region_id_list = array($order_info['country'], $order_info['province'], $order_info['city'], $order_info['district']);

            /* 保存订单 */
            $shipping_id = intval($_POST['shipping']);
            $shipping = shipping_info($shipping_id);
            $shipping_name = $shipping['shipping_name'];

            $consignee = array(
                'country'       => $order_info['country'],
                'province'      => $order_info['province'],
                'city'          => $order_info['city'],
                'district'      => $order_info['district']
            );

            $goods_list = order_goods($order_id);
            $cart_goods = $goods_list;

            $shipping_fee = 0;
            if ($GLOBALS['_CFG']['freight_model'] == 0) {

                /* 商品单独设置运费价格 start */
                if ($cart_goods) {
                    if (count($cart_goods) == 1) {

                        $cart_goods = array_values($cart_goods);

                        if (!empty($cart_goods[0]['freight']) && $cart_goods[0]['is_shipping'] == 0) {

                            if ($cart_goods[0]['freight'] == 1) {
                                $configure_value = $cart_goods[0]['shipping_fee'] * $cart_goods[0]['goods_number'];
                            } else {

                                $trow = get_goods_transport($cart_goods[0]['tid']);

                                if ($trow['freight_type']) {

                                    $cart_goods[0]['user_id'] = $cart_goods[0]['ru_id'];
                                    $transport_tpl = get_goods_transport_tpl($cart_goods[0], $region, $val, $cart_goods[0]['goods_number']);

                                    $configure_value = isset($transport_tpl['shippingFee']) ? $transport_tpl['shippingFee'] : 0;
                                } else {

                                    /**
                                     * 商品运费模板
                                     * 自定义
                                     */
                                    $custom_shipping = get_goods_custom_shipping($cart_goods);

                                    $transport = array('top_area_id', 'area_id', 'tid', 'ru_id', 'sprice');
                                    $transport_where = " AND ru_id = '" . $cart_goods[0]['ru_id'] . "' AND tid = '" . $cart_goods[0]['tid'] . "'";
                                    $goods_transport = $GLOBALS['ecs']->get_select_find_in_set(2, $consignee['city'], $transport, $transport_where, 'goods_transport_extend', 'area_id');

                                    $ship_transport = array('tid', 'ru_id', 'shipping_fee');
                                    $ship_transport_where = " AND ru_id = '" . $cart_goods[0]['ru_id'] . "' AND tid = '" . $cart_goods[0]['tid'] . "'";
                                    $goods_ship_transport = $GLOBALS['ecs']->get_select_find_in_set(2, $shipping_id, $ship_transport, $ship_transport_where, 'goods_transport_express', 'shipping_id');

                                    $goods_transport['sprice'] = isset($goods_transport['sprice']) ? $goods_transport['sprice'] : 0;
                                    $goods_ship_transport['shipping_fee'] = isset($goods_ship_transport['shipping_fee']) ? $goods_ship_transport['shipping_fee'] : 0;

                                    /* 是否免运费 start */
                                    if ($custom_shipping && $custom_shipping[$cart_goods[0]['tid']]['amount'] >= $trow['free_money'] && $trow['free_money'] > 0) {
                                        $is_shipping = 1; /* 免运费 */
                                    } else {
                                        $is_shipping = 0; /* 有运费 */
                                    }
                                    /* 是否免运费 end */

                                    if ($is_shipping == 0) {
                                        if ($trow['type'] == 1) {
                                            $configure_value = $goods_transport['sprice'] * $cart_goods[0]['goods_number'] + $goods_ship_transport['shipping_fee'] * $cart_goods[0]['goods_number'];
                                        } else {
                                            $configure_value = $goods_transport['sprice'] + $goods_ship_transport['shipping_fee'];
                                        }
                                    }
                                }
                            }
                        } else {
                            /* 有配送按配送区域计算运费 */
                            $configure_type = 1;
                        }
                    } else {
                        $order_transpor = get_order_transport($cart_goods, $consignee, $shipping_id, $val['shipping_code']);

                        if ($order_transpor['freight']) {
                            /* 有配送按配送区域计算运费 */
                            $configure_type = 1;
                        }

                        $configure_value = isset($order_transpor['sprice']) ? $order_transpor['sprice'] : 0;
                    }
                }
                /* 商品单独设置运费价格 end */

                $shipping_fee = $configure_value;
            }

            $order = array(
                'shipping_id' => $shipping_id,
                'shipping_name' => addslashes($shipping_name),
                'shipping_fee' => $shipping_fee
            );

            if (isset($_POST['insure']))
            {
                /* 计算保价费 */
                $order['insure_fee'] = shipping_insure_fee($shipping['shipping_code'], order_amount($order_id), $shipping['insure']);
            }
            else
            {
                $order['insure_fee'] = 0;
            }
            update_order($order_id, $order);
            update_order_amount($order_id);

            /* 更新 pay_log */
            update_pay_log($order_id);

            /* 清除首页缓存：发货单查询 */
            clear_cache_files('index.dwt');

            /* todo 记录日志 */
            $sn = $old_order['order_sn'];
            $new_order =  seller_order_info($order_id);
            if ($old_order['total_fee'] != $new_order['total_fee'])
            {
                $sn .= ',' . sprintf(L('order_amount_change'), $old_order['total_fee'], $new_order['total_fee']);
            }
            admin_log($sn, 'edit', 'order');

            if (isset($_POST['next']))
            {
                /* 下一步 */
                $href = url('supplier/order/'.$step_act, ['order_id'=>$order_id, 'step'=>'payment']);
                ecs_header("Location: $href\n");
                exit;
            }
            elseif (isset($_POST['finish']))
            {
                /* 初始化提示信息和链接 */
                $msgs   = array();
                $links  = array();

                /* 如果已付款，检查金额是否变动，并执行相应操作 */
                $order =  seller_order_info($order_id);
                handle_order_money_change($order, $msgs, $links);

                /* 如果是编辑且配送不支持货到付款且原支付方式是货到付款 */
                if ('edit' == $step_act && $shipping['support_cod'] == 0)
                {
                    $payment = payment_info($order['pay_id']);
                    if ($payment['is_cod'] == 1)
                    {
                        /* 修改支付为空 */
                        update_order($order_id, array('pay_id' => 0, 'pay_name' => ''));
                        $msgs[]     = L('continue_payment');
                        $links[]    = array('text' => L('step')['payment'], 'href' => 'order.php?act=' . $step_act . '&order_id=' . $order_id . '&step=payment');
                    }
                }

                /* 显示提示信息 */
                if (!empty($msgs))
                {
                    sys_msg(join(chr(13), $msgs), 0, $links);
                }
                else
                {
                    /* 完成 */
                    ecs_header("Location: " . url('supplier/order/detail', ['order_id'=>$order_id]) . "\n");
                    exit;
                }
            }
        }
        /* 保存支付信息 */
        elseif ('payment' == $step)
        {
            /* 取得支付信息 */
            $pay_id = $_POST['payment'];
            $payment = payment_info($pay_id);

            /* 计算支付费用 */
            $order_amount = order_amount($order_id);
            if ($payment['is_cod'] == 1)
            {
                $order =  seller_order_info($order_id);
                $region_id_list = array(
                    $order['country'], $order['province'], $order['city'], $order['district']
                );
                $shipping = shipping_info($order['shipping_id']);
                $pay_fee = pay_fee($pay_id, $order_amount, $shipping['pay_fee']);
            }
            else
            {
                $pay_fee = pay_fee($pay_id, $order_amount);
            }

            /* 保存订单 */
            $order = array(
                'pay_id' => $pay_id,
                'pay_name' => addslashes($payment['pay_name']),
                'pay_fee' => $pay_fee
            );
            update_order($order_id, $order);
            update_order_amount($order_id);

            /* 更新 pay_log */
            update_pay_log($order_id);

            /* todo 记录日志 */
            $sn = $old_order['order_sn'];
            $new_order =  seller_order_info($order_id);
            if ($old_order['total_fee'] != $new_order['total_fee'])
            {
                $sn .= ',' . sprintf(L('order_amount_change'), $old_order['total_fee'], $new_order['total_fee']);
            }
            admin_log($sn, 'edit', 'order');

            if (isset($_POST['next']))
            {
                $href = url('supplier/order/'.$step_act, ['order_id'=>$order_id, 'step'=>'other']);
                /* 下一步 */
                ecs_header("Location: $href\n");
                exit;
            }
            elseif (isset($_POST['finish']))
            {
                /* 初始化提示信息和链接 */
                $msgs   = array();
                $links  = array();

                /* 如果已付款，检查金额是否变动，并执行相应操作 */
                $order =  seller_order_info($order_id);
                handle_order_money_change($order, $msgs, $links);

                /* 显示提示信息 */
                if (!empty($msgs))
                {
                    sys_msg(join(chr(13), $msgs), 0, $links);
                }
                else
                {
                    /* 完成 */
                    $href = url('supplier/order/detail', ['order_id'=>$order_id]);
                    ecs_header("Location: " . $href . "\n");
                    exit;
                }
            }
        }
        elseif ('other' == $step)
        {
            /* 保存订单 */
            $order = array();
            if (isset($_POST['pack']) && $_POST['pack'] > 0)
            {
                $pack               = pack_info($_POST['pack']);
                $order['pack_id']   = $pack['pack_id'];
                $order['pack_name'] = addslashes($pack['pack_name']);
                $order['pack_fee']  = $pack['pack_fee'];
            }
            else
            {
                $order['pack_id']   = 0;
                $order['pack_name'] = '';
                $order['pack_fee']  = 0;
            }
            if (isset($_POST['card']) && $_POST['card'] > 0)
            {
                $card               = card_info($_POST['card']);
                $order['card_id']   = $card['card_id'];
                $order['card_name'] = addslashes($card['card_name']);
                $order['card_fee']  = $card['card_fee'];
                $order['card_message'] = $_POST['card_message'];
            }
            else
            {
                $order['card_id']   = 0;
                $order['card_name'] = '';
                $order['card_fee']  = 0;
                $order['card_message'] = '';
            }
            $order['inv_type']      = $_POST['inv_type'];
            $order['inv_payee']     = $_POST['inv_payee'];
            $order['inv_content']   = $_POST['inv_content'];
            $order['how_oos']       = $_POST['how_oos'];
            $order['postscript']    = $_POST['postscript'];
            $order['to_buyer']      = $_POST['to_buyer'];
            update_order($order_id, $order);
            update_order_amount($order_id);

            /* 更新 pay_log */
            update_pay_log($order_id);

            /* todo 记录日志 */
            $sn = $old_order['order_sn'];
            admin_log($sn, 'edit', 'order');

            if (isset($_POST['next']))
            {
                /* 下一步 */
                $href = url('supplier/order/'.$step_act, ['order_id'=>$order_id, 'step'=>'money']);
                /* 下一步 */
                ecs_header("Location: $href\n");
                exit;
            }
            elseif (isset($_POST['finish']))
            {
                /* 完成 */
                ecs_header("Location: " . url('supplier/order/detail', ['order_id'=>$order_id]) . "\n");
                exit;
            }
        }
        elseif ('money' == $step)
        {
            /* 取得订单信息 */
            $old_order =  seller_order_info($order_id);
            if ($old_order['user_id'] > 0)
            {
                /* 取得用户信息 */
                $user = user_info($old_order['user_id']);
            }

            /* 保存信息 */
            $order['goods_amount']  = $old_order['goods_amount'];
            $order['discount']      = isset($_POST['discount']) && floatval($_POST['discount']) >= 0 ? round(floatval($_POST['discount']), 2) : 0;
            $order['tax']           = round(floatval($_POST['tax']), 2);
            $order['shipping_fee']  = isset($_POST['shipping_fee']) && floatval($_POST['shipping_fee']) >= 0 ? round(floatval($_POST['shipping_fee']), 2) : 0;
            $order['insure_fee']    = isset($_POST['insure_fee']) && floatval($_POST['insure_fee']) >= 0 ? round(floatval($_POST['insure_fee']), 2) : 0;
            $order['pay_fee']       = floatval($_POST['pay_fee']) >= 0 ? round(floatval($_POST['pay_fee']), 2) : 0;
            $order['pack_fee']      = isset($_POST['pack_fee']) && floatval($_POST['pack_fee']) >= 0 ? round(floatval($_POST['pack_fee']), 2) : 0;
            $order['card_fee']      = isset($_POST['card_fee']) && floatval($_POST['card_fee']) >= 0 ? round(floatval($_POST['card_fee']), 2) : 0;
            $order['coupons']      = isset($_POST['coupons']) && floatval($_POST['coupons']) >= 0 ? round(floatval($_POST['coupons']), 2) : 0;

            $order['money_paid']    = $old_order['money_paid'];
            $order['surplus']       = 0;
            //$order['integral']      = 0;
            $order['integral']=intval($_POST['integral']) >= 0 ? intval($_POST['integral']) : 0;
            $order['integral_money']= 0;
            $order['bonus_id']      = 0;
            $order['bonus']         = isset($_POST['bonus']) && floatval($_POST['bonus']) >= 0 ? round(floatval($_POST['bonus']), 2) : 0;
            $_POST['bonus_id'] = isset($_POST['bonus_id']) && !empty($_POST['bonus_id']) ? intval($_POST['bonus_id']) : 0;

            /* 计算待付款金额 */
            $order['order_amount']  = $order['goods_amount'] - $order['discount']
                + $order['tax']
                + $order['shipping_fee']
                + $order['insure_fee']
                + $order['pay_fee']
                + $order['pack_fee']
                + $order['card_fee']
                - $order['coupons']
                - $old_order['use_val']
                - $order['money_paid'];

            if ($order['order_amount'] > 0)
            {
                if ($old_order['user_id'] > 0)
                {
                    /* 如果选择了红包，先使用红包支付 */
                    if ($_POST['bonus_id'] > 0 && !isset($_POST['bonus']))
                    {
                        /* todo 检查红包是否可用 */
                        $order['bonus_id']      = $_POST['bonus_id'];
                        $bonus                  = bonus_info($_POST['bonus_id']);
                        $order['bonus']         = $bonus['type_money'];

                        $order['order_amount']  -= $order['bonus'];
                    }

                    /* 使用红包之后待付款金额仍大于0 */
                    if ($order['order_amount'] > 0)
                    {
                        if($old_order['extension_code']!='exchange_goods')
                        {
                            /* 如果设置了积分，再使用积分支付 */
                            if (isset($_POST['integral']) && intval($_POST['integral']) > 0)
                            {
                                /* 检查积分是否足够 */
                                $order['integral']          = intval($_POST['integral']);
                                $order['integral_money']    = value_of_integral(intval($_POST['integral']));
                                if ($old_order['integral'] + $user['pay_points'] < $order['integral'])
                                {
                                    sys_msg(L('pay_points_not_enough'));
                                }

                                $order['order_amount'] -= $order['integral_money'];
                            }
                        }
                        else
                        {
                            if (intval($_POST['integral']) > $user['pay_points']+$old_order['integral'])
                            {
                                sys_msg(L('pay_points_not_enough'));
                            }

                        }
                        if ($order['order_amount'] > 0)
                        {
                            /* 如果设置了余额，再使用余额支付 */
                            if (isset($_POST['surplus']) && floatval($_POST['surplus']) >= 0)
                            {
                                /* 检查余额是否足够 */
                                $order['surplus'] = round(floatval($_POST['surplus']), 2);
                                if ($old_order['surplus'] + $user['user_money'] + $user['credit_line'] < $order['surplus'])
                                {
                                    sys_msg(L('user_money_not_enough'));
                                }

                                /* 如果红包和积分和余额足以支付，把待付款金额改为0，退回部分积分余额 */
                                $order['order_amount'] -= $order['surplus'];
                                if ($order['order_amount'] < 0)
                                {
                                    $order['surplus']       += $order['order_amount'];
                                    $order['order_amount']  = 0;
                                }
                            }
                        }
                        else
                        {
                            /* 如果红包和积分足以支付，把待付款金额改为0，退回部分积分 */
                            $order['integral_money']    += $order['order_amount'];
                            $order['integral']          = integral_of_value($order['integral_money']);
                            $order['order_amount']      = 0;
                        }
                    }
                    else
                    {
                        /* 如果红包足以支付，把待付款金额设为0 */
                        $order['order_amount'] = 0;
                    }
                }
            }

            update_order($order_id, $order);

            /* 更新 pay_log */
            update_pay_log($order_id);

            /* todo 记录日志 */
            $sn = $old_order['order_sn'];
            $new_order =  seller_order_info($order_id);
            if ($old_order['total_fee'] != $new_order['total_fee'])
            {
                //如果是编辑订单，且金额发生变化时，重新生成订单编号,防止微信支付失败
                if ($step_act == 'edit'){
                    $new_order_sn = correct_order_sn($old_order['order_sn']);
                    $sn = $new_order_sn;
                    $old_order['order_sn'] = $new_order_sn;
                }
                $sn .= ',' . sprintf(L('order_amount_change'), $old_order['total_fee'], $new_order['total_fee']);
            }
            admin_log($sn, 'edit', 'order');

            /* 如果余额、积分、红包有变化，做相应更新 */
            if ($old_order['user_id'] > 0)
            {
                $user_money_change = $old_order['surplus'] - $order['surplus'];
                if ($user_money_change != 0)
                {
                    log_account_change($user['user_id'], $user_money_change, 0, 0, 0, sprintf(L('change_use_surplus'), $old_order['order_sn']));
                }

                $pay_points_change = $old_order['integral'] - $order['integral'];
                if ($pay_points_change != 0)
                {
                    log_account_change($user['user_id'], 0, 0, 0, $pay_points_change, sprintf(L('change_use_integral'), $old_order['order_sn']));
                }

                if ($old_order['bonus_id'] != $order['bonus_id'])
                {
                    if ($old_order['bonus_id'] > 0)
                    {
                        $sql = "UPDATE " . $ecs->table('user_bonus') .
                            " SET used_time = 0, order_id = 0 " .
                            "WHERE bonus_id = '$old_order[bonus_id]' LIMIT 1";
                        $db->query($sql);
                    }

                    if ($order['bonus_id'] > 0)
                    {
                        $sql = "UPDATE " . $ecs->table('user_bonus') .
                            " SET used_time = '" . gmtime() . "', order_id = '$order_id' " .
                            "WHERE bonus_id = '$order[bonus_id]' LIMIT 1";
                        $db->query($sql);
                    }
                }
            }

            if (isset($_POST['finish']))
            {
                /* 完成 */
                if ($step_act == 'add')
                {
                    /* 订单改为已确认，（已付款） */
                    $arr['order_status'] = OS_CONFIRMED;
                    $arr['confirm_time'] = gmtime();
                    if ($order['order_amount'] <= 0)
                    {
                        $arr['pay_status']  = PS_PAYED;
                        $arr['pay_time']    = gmtime();
                    }
                    update_order($order_id, $arr);
                }

                /* 初始化提示信息和链接 */
                $msgs   = array();
                $links  = array();

                /* 如果已付款，检查金额是否变动，并执行相应操作 */
                $order =  seller_order_info($order_id);
                handle_order_money_change($order, $msgs, $links);

                if ($step_act == 'add') {
                    /* 记录log */
                    $action_note = sprintf(L('add_order_info'), $_SESSION['seller_name']);
                    order_action($order['order_sn'], $order['order_status'], $order['shipping_status'], $order['pay_status'], $action_note, $_SESSION['seller_name']);
                }

                /* 显示提示信息 */
                if (!empty($msgs))
                {
                    sys_msg(join(chr(13), $msgs), 0, $links);
                }
                else
                {
                    $href = url('supplier/order/detail', ['order_id'=>$order_id]);
                    ecs_header("Location: ".$href. "\n");
                    exit;
                }
            }
        }
        /* 保存发货后的配送方式和发货单号 */
        elseif ('invoice' == $step)
        {
            /* 如果不存在实体商品，退出 */
            if (!exist_real_goods($order_id))
            {
                die ('Hacking Attemp');
            }

            /* 保存订单 */
            $shipping_id    = intval($_POST['shipping']);
            $shipping       = shipping_info($shipping_id);
            $invoice_no     = trim($_POST['invoice_no']);
            $invoice_no     = str_replace(',', '<br>', $invoice_no);
            $order = array(
                'shipping_id'   => $shipping_id,
                'shipping_name' => addslashes($shipping['shipping_name']),
                'invoice_no'    => $invoice_no
            );
            update_order($order_id, $order);

            /* todo 记录日志 */
            $sn = $old_order['order_sn'];
            admin_log($sn, 'edit', 'order');

            if (isset($_POST['finish']))
            {
                $href = url('supplier/order/detail', ['order_id'=>$order_id]);
                ecs_header("Location: ".$href. "\n");
                exit;
            }
        }
    }
}
