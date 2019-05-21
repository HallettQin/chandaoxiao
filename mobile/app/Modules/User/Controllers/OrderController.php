<?php

namespace App\Modules\User\Controllers;

use App\Modules\Base\Controllers\FrontendController;

class OrderController extends FrontendController
{
    public $user_id;

    // 用户id

    /**
     * 构造，加载文件语言包和helper文件
     */
    public function __construct()
    {
        parent::__construct();
        $this->user_id = $_SESSION['user_id'];
        $this->actionchecklogin();
        L(require(LANG_PATH . C('shop.lang') . '/user.php'));
        L(require(LANG_PATH . C('shop.lang') . '/flow.php'));

        $files = [
            'order',
            'clips',
            'payment',
            'transaction'
        ];
        $this->load_helper($files);
        //是否显示拼团信息
        $this->assign('team', is_dir(APP_TEAM_PATH) ? 1 : 0);

        //取消24小时订单
        timing_cancel_order();
    }

    /**
     * 订单列表
     */
    public function actionIndex()
    {
        $size = 10;
        $page = I('page', 1, 'intval');
        $status = I('status', 0, 'intval');
        $mode = I('mode', '', 'trim');
        //添加模式筛选
        if (!$mode || !in_array($mode, ['group_buy', 'presale', 'sample', 'wholesale'])) {
            $mode = '';
        }

        if (IS_POST || $_GET['is_ajax']) {
            $order_list = get_user_orders($this->user_id, $size, $page, $status, $mode);

            exit(json_encode(['order_list' => $order_list['list'], 'totalPage' => $order_list['totalpage']]));
        }

        $where = $mode ? " and  oi.extension_code = '".$mode ."'" : '';

        // 所有订单
        $all_order = get_order_where_count($this->user_id, 0, $where);
        // 待付款
        $where_pay = ' AND oi.pay_status = ' . PS_UNPAYED . ' AND oi.order_status not in(' . OS_CANCELED . ',' . OS_INVALID . ',' . OS_RETURNED . ')';
        $pay_count = get_order_where_count($this->user_id, 0, $where . $where_pay);
        // 待发货
        $where_splited = " AND oi.pay_status = " . PS_PAYED . " AND oi.order_status in (" . OS_CONFIRMED . ", " . OS_SPLITED . ", " . OS_SPLITING_PART .  ") AND (oi.shipping_status in (" . SS_UNSHIPPED .  ',' . SS_PREPARING.  ',' . SS_SHIPPED_ING ."))";
        $splite_count = get_order_where_count($this->user_id, 0, $where . $where_splited);

        // 待收货
        $where_confirmed = " AND oi.pay_status = " . PS_PAYED . " AND oi.order_status in (" . OS_CONFIRMED . ", " . OS_SPLITED . ", " . OS_SPLITING_PART . ") AND (oi.shipping_status in (" . SS_SHIPPED .  ',' . OS_SHIPPED_PART. "))";
        $confirmed_count = get_order_where_count($this->user_id, 0, $where . $where_confirmed);

        //已完成
        $where_completed =  " AND oi.order_status " . db_create_in(array(OS_CONFIRMED, OS_SPLITED)) . "  AND oi.shipping_status = '" . SS_RECEIVED . "' AND oi.pay_status " . db_create_in(array(PS_PAYED, PS_PAYING)) ;
        $completed_count = get_order_where_count($this->user_id, 0, $where . $where_completed);

        $order_num = [
            'all_order' => $all_order,
            'pay_count' => $pay_count,
            'splite_count' => $splite_count,
            'confirmed_count' => $confirmed_count,
            'completed_count' => $completed_count
        ];
        $this->assign('order_num', $order_num);
        $this->assign('status', $status);
        $this->assign('mode', $mode);
        $this->assign('page_title', L('order_list_lnk'));
        $this->display();
    }

    /**
     * 查看订单详情
     */
    public function actionDetail()
    {
        $order_id = I('order_id', 0, 'intval');
        $noTime = gmtime();
        $date = [
            'order_status',
            'shipping_status',
            'pay_status',
            'shipping_time',
            'auto_delivery_time'
        ];
        $orderInfo = get_table_date('order_info', "order_id = '$order_id' and user_id = '$this->user_id'", $date);

        if ($GLOBALS['_CFG']['open_delivery_time'] == 1) {
            if ($orderInfo['order_status'] == 5 && $orderInfo['shipping_status'] == 1 && $orderInfo['pay_status'] == 2) { // 发货状态
                $delivery_time = $orderInfo['shipping_time'] + 24 * 3600 * $orderInfo['auto_delivery_time'];
                if ($noTime > $delivery_time) { // 自动确认发货操作
                    $sql = "update {pre}order_info set order_status = 5, shipping_status = 2, pay_status = 2 where order_id = '$order_id'";
                    $this->db->query($sql);
                }
            }
        }
        /* 订单详情 */
        $order = get_order_detail($order_id, $this->user_id);
        if ($order === false) {
            $this->err->show(L('back_home_lnk'), './');
            exit();
        }
        $order['is_pay'] = $order['pay_status'];
        /* 获取订单门店信息  start */
        $sql = "SELECT id, store_id,pick_code  FROM" . $this->ecs->table("store_order") . " WHERE order_id = '$order_id'";
        $stores = $this->db->getRow($sql);
        if (!empty($stores)) {
            $order['store_id'] = $stores['store_id'];
            $order['pick_code'] = $stores['pick_code'];
            $sql = "SELECT o.*,p.region_name as province,c.region_name as city,d.region_name as district FROM" . $this->ecs->table('offline_store') . " AS o "
                . "LEFT JOIN " . $this->ecs->table('region') . " AS p ON p.region_id = o.province "
                . "LEFT JOIN " . $this->ecs->table('region') . " AS c ON c.region_id = o.city "
                . "LEFT JOIN " . $this->ecs->table('region') . " AS d ON d.region_id = o.district WHERE o.id = '" . $order['store_id'] . "'";
            $offline_store = $this->db->getRow($sql);
            $this->assign('offline_store', $offline_store);
            $this->assign('store_id', $stores['id']);
        }
        //订单店铺
        $ru_id = $this->db->getRow("SELECT ru_id FROM " . $this->ecs->table('order_goods') . " WHERE order_id = " . $order['order_id']);
        if ($ru_id) {
            $order['shop_name'] = get_shop_name($ru_id['ru_id'], 1); //店铺名称
            $order['shopUrl'] = url('store/index/index', ['id' => $ru_id]);
        }

        /* 是否显示添加到订货单 */
        if ($order['extension_code'] != 'group_buy' && $order['extension_code'] != 'exchange_goods') {
            $this->assign('allow_to_cart', 1);
        }

        /* 订单商品 */
        $goods_list = order_goods($order_id);
        $goods_count = 0;
        $package_goods_count = 0;
        $package_list_total = 0;
        foreach ($goods_list as $key => $value) {
            $goods_list[$key]['market_price'] = price_format($value['market_price'], false);
            $goods_list[$key]['goods_price'] = price_format($value['goods_price'], false);
            $goods_list[$key]['subtotal'] = price_format($value['subtotal'], false);
            /* 虚拟商品卡密 by wu */
            if ($value['is_real'] == 0) {
                $goods_list[$key]['virtual_info'] = get_virtual_goods_info($value['rec_id']);
            }
            //礼包统计
            if ($value['extension_code'] == 'package_buy') {
                $package_goods_count++;
                foreach ($value['package_goods_list'] as $package_goods_val) {
                    $package_list_total += $package_goods_val['rank_price'] * $package_goods_val['goods_number'];
                }
                $goods_list[$key]['package_list_total'] = $package_list_total;
                $goods_list[$key]['package_list_saving'] = $value['subtotal'] - $package_list_total;
                $goods_list[$key]['format_package_list_total'] = price_format($goods_list[$key]['package_list_total']);
                $goods_list[$key]['format_package_list_saving'] = price_format($goods_list[$key]['package_list_saving']);
            } else {
                $goods_count++;
            }
        }
        /* 设置能否修改使用余额数 */
        if ($order['order_amount'] > 0) {
            if ($order['order_status'] == OS_UNCONFIRMED || $order['order_status'] == OS_CONFIRMED) {
                $user = user_info($order['user_id']);
                if ($user['user_money'] + $user['credit_line'] > 0) {
                    $this->assign('allow_edit_surplus', 1);
                    $this->assign('max_surplus', sprintf(L('max_surplus'), $user['user_money']));
                }
            }
        }
        /* 未发货，未付款时允许更换支付方式 */
        if ($order['order_amount'] > 0 && ($order['pay_status'] == PS_UNPAYED || $order['pay_status'] == PS_PAYED_PART) && $order['shipping_status'] == SS_UNSHIPPED) {
            $payment_list = available_payment_list(false, 0, true);
            /* 过滤掉当前支付方式和余额支付方式 */
            if (is_array($payment_list)) {
                foreach ($payment_list as $key => $payment) {
                    // 去除ecjia的支付方式
                    if (substr($payment['pay_code'], 0, 4) == 'pay_') {
                        unset($payment_list[$key]);
                        continue;
                    }
                    // 只保留mobile支付插件
                    if (!file_exists(ADDONS_PATH . 'payment/' . $payment['pay_code'] . '.php')) {
                        unset($payment_list[$key]);
                    }
                    if ($payment['pay_id'] == $order['pay_id'] || $payment['pay_code'] == 'balance') {
                        unset($payment_list[$key]);
                    }
                    if ($payment['pay_code'] == 'wxpay') {
                        if (!is_dir(APP_WECHAT_PATH)) {
                            unset($payment_list[$key]);
                        }
                        // 非微信浏览控制显示h5
                        if (is_wechat_browser() == false && is_wxh5() == 0) {
                            unset($payment_list[$key]);
                        }
                    }
                }
            }
            $this->assign('payment_list', $payment_list);
        }
        /* 订单 支付 配送 状态语言项 */
        $os = L('os');
        $ps = L('ps');
        $ss = L('ss');
        if ($order['order_status'] == OS_UNCONFIRMED) {
            $order['handler'] = "<span class=\"box-flex text-right\"></span><a class=\"btn-default box-flex cancel-order\" type=\"button\" href=\"javascript:;\"  data-item=\"" . $order['order_id'] . "\">" . L('cancel') . "</a>";
        } elseif ($order['order_status'] == OS_SPLITED) {
            /* 对配送状态的处理 */
            if ($order['shipping_status'] == SS_SHIPPED) {
                @$order['handler'] = "<span class=\"box-flex text-right\"></span><a class=\"btn-submit received-order\"  data-item=\"" . $order['order_id'] . "\">" . L('received') . "</a>";
            } elseif ($order['shipping_status'] == SS_RECEIVED) {
                @$order['handler'] = '<span class="order-checkout-text box">' . L('ss_received') . '</span>';
            } else {
                if ($order['pay_status'] == PS_UNPAYED) {
                    @$order['handler'] = "<span class=\"box-flex text-right\"></span><a class=\"btn-submit\" href=\"" . url('user/order/detail', ['order_id' => $order['order_id']]) . '" >' . L('pay_money') . '</a>';
                } else {
                    $order['handler'] = '<span class="order-checkout-text box">' . $ss[$order['shipping_status']] . '</span>';
                }
            }
        } else {
            if ($order['order_status'] == OS_CONFIRMED && $order['pay_status'] == PS_UNPAYED) {
                $order['handler'] = "<span class='box-flex'></span><a class='btn-default box-flex' type='button' >" . $ps[$order['pay_status']] . "</a>";
            } elseif ($order['pay_status'] == PS_PAYED_PART) {
                if ($order['extension_code'] == 'presale') {
                    $result = presale_settle_status($order['extension_id']);
                    if ($result['settle_status'] == 1) {
                        $order['msg'] = '尾款支付时间:';
                        $start_time = trim($result['start_time']);
                        $end_time = trim($result['end_time']);
                        @$order['handler'] = "<span class=box-flex text-right>" . $start_time . '至' . $end_time . '</span>';
                    }
                    if ($result['settle_status'] == 0) {
                        $order['msg'] = '尾款支付时间:';
                        $start_time = trim($result['start_time']);
                        $end_time = trim($result['end_time']);
                        $order['hidden_pay_button'] = 1;
                        @$order['handler'] = "<span class=box-flex text-right>" . $start_time . '至' . $end_time . '</span>';
                    }
                    if ($result['settle_status'] == -1) {
                        $order['hidden_pay_button'] = 1;
                        $order['msg'] = "超出尾款支付时间";
                        $end_time = trim($result['end_time']);
                        @$order['handler'] = "<span class='box-flex text-right'>" . $end_time . "</span>";
                    }
                }
            } else {
                if ($order['pay_status'] == PS_PAYED) {
                    $order['handler'] = $order['handler'] = '<span class="order-checkout-text box">' . $ps[$order['pay_status']] . '</span>';
                } else {
                    $order['handler'] = $order['handler'] = '<span class="order-checkout-text box">' . $os[$order['order_status']] . '</span>';
                }
            }
        }

        $order['order_status'] = $os[$order[order_status]];
        $order['pay_status'] = $ps[$order[pay_status]];
        $order['shipping_status'] = $ss[$order['shipping_status']];

        $order['c'] = get_region_name($order['country']);
        $order['detail_address'] .= $order['c']['region_name'];

        $order['p'] = get_region_name($order['province']);
        $order['detail_address'] .= $order['p']['region_name'];

        $order['cc'] = get_region_name($order['city']);
        $order['detail_address'] .= $order['cc']['region_name'];

        $order['dd'] = get_region_name($order['district']);
        $order['detail_address'] .= $order['dd']['region_name'];
        $order['detail_address'] .= $order['address'];

        /* 自提点信息 */
        $sql = "SELECT * FROM " . $this->ecs->table('shipping_point') . " WHERE id IN (SELECT point_id FROM " . $this->ecs->table('order_info') . " WHERE order_id='{$order_id}')";
        $order['point'] = $this->db->getRow($sql);
        if ($order['point']) {
            $order['point']['pickDate'] = $order['shipping_dateStr'];
        }
        //验证拼团订单是否失败
        if (is_dir(APP_TEAM_PATH)) {
            if ($order['team_id'] > 0) {
                $failure = get_team_info($order['team_id'], $order['order_id']);
                $order['failure'] = $failure;
            }
        }
        //验证拼团订单是否失败 end
        $this->assign('order', $order);
        $this->assign('goods_list', $goods_list);
        $this->assign('goods_count', $goods_count);
        $this->assign('package_goods_count', $package_goods_count);
        $this->assign('page_title', L('order_detail'));
        $this->display();
    }

    /**
     * 退换货申请列表
     */
    public function actionApplyReturnList()
    {
        //var_dump($_REQUEST);
        /* 根据订单id或订单号查询订单信息 */
        if (isset($_REQUEST['rec_id'])) {
            $recr_id = intval($_REQUEST['rec_id']);
        } else {
            /* 如果参数不存在，退出 */
            die('invalid parameter');
        }
        $_REQUEST['order_id'] = intval($_REQUEST['order_id']);
        /* 退货权限 */
        $sql = " SELECT order_id FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '" . $_REQUEST['order_id'] . "' AND shipping_status > 0 ";
        $return_allowable = $GLOBALS['db']->getOne($sql);
        $this->assign('return_allowable', $return_allowable);

        /* 订单商品 */
        $goods_info = rec_goods($recr_id);

        $this->assign('goods', $goods_info);

        $this->display();
    }

    /**
     * 订单跟踪
     */
    public function actionOrderTracking()
    {
        $order_id = I('order_id', 0, 'intval');
        $order = get_order_detail($order_id, $this->user_id);
        if ($order === false) {
            $this->err->show(L('back_home_lnk'), './');
            exit();
        }
        if ($order['invoice_no']) {
            preg_match("/^<a.*href=\"(.*?)\">/is", $order['invoice_no'], $url);
            if ($url[1]) {
                redirect($url[1]);
            }
        }
        show_message(L('msg_unfilled_or_receive'), L('user_center'), url('user/index/index'));
    }

    /* 确认收货 */

    public function actionAffirmReceived()
    {
        $user_id = $this->user_id;
        $order_id = isset($_REQUEST['order_id']) ? intval($_REQUEST['order_id']) : 0;
        if (affirm_received($order_id, $user_id)) {
            die(json_encode(['y' => 1]));
        } else {
            show_message(L('msg_unfilled_or_receive'));
        }
    }

    /**
     * 删除订单
     */
    public function actionDelOrder()
    {
        $order_id = I('order_id');
        if (IS_AJAX) {
            $sql = "UPDATE {pre}order_info SET `is_delete`=1 where order_id=" . $order_id;
            $this->db->query($sql);
            die(json_encode(['y' => 1]));
        }
    }

    /**
     * 取消订单
     */
    public function actionCancel()
    {
        $order_id = I('order_id', 0, 'intval');
        if (IS_AJAX) {
            if (cancel_order($order_id, $this->user_id)) {
                exit(json_encode(['y' => 1]));
            } else {
                exit(json_encode(['n' => 1]));
            }
        }
    }

    /**
     * 验证是否登录
     */
    public function actionchecklogin()
    {
        if (!$this->user_id) {
            $url = urlencode(__HOST__ . $_SERVER['REQUEST_URI']);
            if (IS_POST) {
                $url = urlencode($_SERVER['HTTP_REFERER']);
            }
            ecs_header("Location: " . url('user/login/index', ['back_act' => $url]));
            exit;
        }
    }

    // 订单列表获取订单数量
    public function Get_Order_Where_Count($user_id = 0, $show_type = 0, $where = '')
    {
        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('order_info') . " as oi" . " WHERE oi.user_id = '$user_id' and oi.is_delete = '$show_type'" . " and (select count(*) from " . $GLOBALS['ecs']->table('order_info') . " as oi_2 where oi_2.main_order_id = oi.order_id) = 0 " . // 主订单下有子订单时，则主订单不显示 .
            $where;
        return $GLOBALS['db']->getOne($sql);
    }

    public function actionChangePayment()
    {
        // 检查支付方式 检查订单号
        $pay_id = intval($_POST['pay_id']);
        $order_id = intval($_POST['order_id']);

        if ($pay_id <= 0 || $order_id <= 0) {
            $this->redirect('index/index');
        }

        $payment_info = payment_info($pay_id);
        if (empty($payment_info)) {
            $this->redirect('index/index');
        }

        // 取得订单
        $order = order_info($order_id);
        if (empty($order) || ($_SESSION['user_id'] != $order['user_id'])) {
            $this->redirect('index/index');
        }
        // 检查订单是否未付款和未发货 以及订单金额是否为0 和支付id是否为改变
        if (($order['pay_status'] == PS_UNPAYED || $order['pay_status'] == PS_PAYED_PART) && $order['shipping_status'] == SS_UNSHIPPED && $order['goods_amount'] > 0 && $order['pay_id'] != $pay_id) {
            $order_amount = $order['order_amount'] - $order['pay_fee'];
            $pay_fee = pay_fee($pay_id, $order_amount);
            $order_amount += $pay_fee;

            $data['pay_id'] = $pay_id;
            $data['pay_name'] = $payment_info['pay_name'];
            $data['pay_fee'] = $pay_fee;
            $data['order_amount'] = $order_amount;
            $where['order_id'] = $order_id;
            $this->model->table('order_info')
                ->data($data)
                ->where($where)
                ->save();
        }
        $this->redirect('detail', ['order_id' => $order_id]);
    }

    /**
     * 余额支付
     */
    public function actionSurplusPay()
    {
        $order_id = I("post.order_id", '', 'intval');
        $type = I("get.type");
        if ($order_id <= 0) {
            ecs_header("Location: ./\n");
            exit;
        }
        $order = order_info($order_id);

        /* 是否预售，检测结算时间是否超出尾款结束时间 liu */
        if ($type == 'presale' && $order['pay_status'] == PS_PAYED_PART) {
            $result = presale_settle_status($order['extension_id']);
            if ($result['settle_status'] == 0 || $result['settle_status'] == -1) {
                ecs_header("Location: ./\n");
                exit;
            }
        }

        /* 检查订单用户跟当前用户是否一致 */
        if ($_SESSION['user_id'] != $order['user_id']) {
            ecs_header("Location: ./\n");
            exit;
        }

        /* 检查订单是否未付款，检查应付款金额是否大于0 */
        if ($order['pay_status'] != PS_UNPAYED || $order['order_amount'] <= 0) {
            if ($order['pay_status'] != PS_PAYED_PART) {
                $GLOBALS['err']->add(L('error_order_is_paid'));
                $GLOBALS['err']->show(L('order_detail'), url('user/order/detail', ['order_id' => $order_id]));
            }
        }

        /* 检查余额 */
        $surplus = floatval($_POST['surplus']);
        if ($surplus <= 0) {
            $GLOBALS['err']->add(L('error_surplus_invalid'));
            $GLOBALS['err']->show(L('order_detail'), url('user/order/detail', ['order_id' => $order_id]));
        }

        /* 检查用户余额是否足够 */
        $user_info = user_info($_SESSION['user_id']);
        if ($order['order_amount'] > $user_info['user_money']) {
            show_message(L('balance_not_enough'), L('back_up_page'), url('user/order/detail', ['order_id' => $order_id]));
        }

        /* 余额是否超过了应付款金额，改为应付款金额 */
        if ($surplus > $order['order_amount']) {
            $surplus = $order['order_amount'];
        }

        /* 修改订单，重新计算支付费用 */
        $order['surplus'] += $surplus;
        $order['order_amount'] -= $surplus;
        if ($order['order_amount'] > 0) {
            $cod_fee = 0;
            if ($order['shipping_id'] > 0) {
                $regions = [$order['country'], $order['province'], $order['city'], $order['district']];
                $shipping = shipping_area_info($order['shipping_id'], $regions);
                if ($shipping['support_cod'] == '1') {
                    $cod_fee = $shipping['pay_fee'];
                }
            }

            $pay_fee = 0;
            if ($order['pay_id'] > 0) {
                $pay_fee = pay_fee($order['pay_id'], $order['order_amount'], $cod_fee);
            }

            $order['pay_fee'] = $pay_fee;
            $order['order_amount'] += $pay_fee;
        }
        /* 如果全部支付，设为已确认、已付款 | 预售商品设为已确认、部分付款 */
        if ($order['order_amount'] == 0) {
            $amount = $order['goods_amount'] + $order['shipping_fee'];
            $paid = $order['money_paid'] + $order['surplus'];
            if ($_POST['pay_status'] == 'presale' && $amount > $paid) {//判断是否是预售订金支付 liu
                $order['pay_status'] = PS_PAYED_PART;
                $order['order_amount'] = $amount - $paid;
            } else {
                $order['pay_status'] = PS_PAYED;
            }
            if ($order['order_status'] == OS_UNCONFIRMED) {
                $order['order_status'] = OS_CONFIRMED;
                $order['confirm_time'] = gmtime();
            }
            $order['pay_time'] = gmtime();
        }
        $order = addslashes_deep($order);
        update_order($order_id, $order);

        /* 更新商品销量 */
        $is_update_sale = is_update_sale($order['order_id']);
        if (C('shop.sales_volume_time') == SALES_PAY && $is_update_sale == 0) {
            get_goods_sale($order['order_id']);
        }
        /* 更新用户余额 */
        $change_desc = sprintf(L('pay_order_by_surplus'), $order['order_sn']);
        log_account_change($order['user_id'], (-1) * $surplus, 0, 0, 0, $change_desc);

        // 微信通模板消息 余额变动提醒
        if (is_dir(APP_WECHAT_PATH)) {
            // 查询用户当前余额
            $users = pay_money($this->user_id);
            $pushData = [
                'keyword1' => ['value' => $order['pay_time'], 'color' => '#173177'], //变动时间
                'keyword2' => ['value' => '消费扣减', 'color' => '#173177'], //变动类型
                'keyword3' => ['value' => $surplus, 'color' => '#173177'], // 变动金额
                'keyword4' => ['value' => $users['user_money'], 'color' => '#173177'], // 当前余额
                'remark' => ['value' => '详情请点击进入会员中心-资金管理页面查询!', 'color' => '#173177'],
            ];
            $url = __HOST__ . url('user/account/index'); // 进入用户余额页面
            push_template('OPENTM401833445', $pushData, $url);
        }

        /* 跳转 */
        $this->redirect('user/order/detail', ['order_id' => $order_id]);
        exit;
    }

    /* 交易投诉 */

    public function actionComplaintList()
    {
        if (IS_AJAX) {
            $size = 10;
            $page = I('page', 1, 'intval');
            $status = I('status', '0', 'intval'); //获取到举报列表  1以举报，0未举报
            $time = gmtime();
            $dealy_time = 15 * 86400;
            $where_zc_order = " AND oi.is_zc_order = 0 "; //排除众筹订单 by wu
            $where_confirmed = '';
            //获取已确认，已分单，部分分单，已付款，已发货或者已确认收货15天内的订单
            if ($status == 0) {
                $where_confirmed = " AND oi.order_status " . db_create_in([OS_CONFIRMED, OS_SPLITED, OS_SPLITING_PART]) . "  "
                    . "AND IF(oi.pay_status = " . PS_PAYED . ", IF(oi.shipping_status = " . SS_RECEIVED . ", oi.shipping_status = '" . SS_RECEIVED . "' AND ('$time'- oi.confirm_take_time) < '$dealy_time', " . db_create_in([SS_RECEIVED], "oi.shipping_status", "NOT") . ") ";
                $where_confirmed .= "AND oi.pay_status " . db_create_in([PS_PAYED]) . ", IF(oi.shipping_status = " . SS_RECEIVED . ", " . db_create_in([SS_RECEIVED], "oi.shipping_status") . " AND ('$time'- oi.confirm_take_time) < '$dealy_time', " . db_create_in([SS_UNSHIPPED], "oi.shipping_status", "NOT") . "))" . $where_zc_order;
            }
            $complaint_list = get_complaint_list($size, $page, $where_confirmed, $status);
            exit(json_encode(['order_list' => $complaint_list['list'], 'totalPage' => $complaint_list['totalPage']]));
        }
        $this->assign('status', $status);
        $this->assign('page_title', '待申请列表');
        $this->display();
    }

    /* 交易投诉详情 */
    public function actionComplaintApply()
    {
        $complaint_id = I('complaint_id', 0, 'intval');
        $order_id = I('order_id', 0, 'intval');
        $this->assign('complaint_id', $complaint_id);
        $this->assign('order_id', $order_id);
        $where = '';
        if ($complaint_id > 0) {
            $complaint_info = get_complaint_info($complaint_id);

            $order_id = $complaint_info['order_id'];
            //获取谈话列表
            if ($complaint_info['complaint_state'] > 1) {
                $talk_list = checkTalkView($complaint_id, 'user');
                $this->assign("talk_list", $talk_list);
            }
            $where = " AND complaint_id = '$complaint_id'";

            $this->assign("complaint_info", $complaint_info);
        } else {
            $where = " AND complaint_id = 0";
            $complaint_title = get_complaint_title();
            $this->assign("complaint_title", $complaint_title);
        }
        //获取订单详情
        $orders = order_info($order_id);
        $orders['order_goods'] = get_order_goods_toInfo($order_id);
        $orders['shop_name'] = get_shop_name($orders['ru_id'], 1); //店铺名称
        $this->assign('orders_goods', $orders['order_goods']);
        //获取纠纷类型
        //获取投诉相册
        $sql = "SELECT img_id as id , order_id, complaint_id,user_id,img_file as comment_img FROM {pre}complaint_img WHERE user_id = '$_SESSION[user_id]]' AND order_id = '$order_id' $where ORDER BY  id DESC";
        $img_list = $this->db->getAll($sql);
        $img = [];
        foreach ($img_list as $key => $val) {
            $img[$key]['img_id'] = $val['id'];
            $img[$key]['pic'] = get_image_path($val['comment_img']);
        }
        $this->assign('img', $img);
        $this->assign('page_title', '交易投诉详情');
        $this->display();
    }

    public function actionComplaintSubmit()
    {
        $order_id = I('order_id', 0, 'intval');
        $title_id = I('title_id', 0, 'intval');
        $complaint_content = !empty($_REQUEST['complaint_content']) ? trim($_REQUEST['complaint_content']) : '';
        //判断该订单是否已经投诉 防止重复提交
        $sql = "SELECT COUNT(*) FROM {pre}complaint WHERE order_id = '$order_id'";
        $complaint_count = $this->db->getOne($sql);
        if ($complaint_count > 0) {
            show_message(L('complaint_reprat'));
        }
        if ($title_id == 0) {
            show_message(L('complaint_title_null'));
        } elseif ($complaint_content == '') {
            show_message(L('complaint_content_null'));
        } else {
            //获取订单信息
            $sql = "SELECT og.ru_id,oi.order_sn FROM {pre}order_info  AS oi LEFT JOIN {pre}order_goods AS og ON og.order_id = oi.order_id  WHERE oi.order_id = '$order_id' LIMIT 1";
            $order_info = $this->db->getRow($sql);
            $shop_name = get_shop_name($order_info['ru_id'], 1);
            $time = gmtime();
            //更新数据
            $other = [
                'user_id' => $_SESSION['user_id'],
                'user_name' => $_SESSION['user_name'],
                'order_id' => $order_id,
                'shop_name' => $shop_name,
                'order_sn' => $order_info['order_sn'],
                'ru_id' => $order_info['ru_id'],
                'title_id' => $title_id,
                'add_time' => $time,
                'complaint_content' => $complaint_content
            ];
            //入库处理

            $complaint_id = dao('complaint')->add($other);
            //更新图片
            if ($complaint_id > 0) {
                $sql = "UPDATE {pre}complaint_img SET complaint_id = '$complaint_id' WHERE user_id = '$_SESSION[user_id]' AND order_id = '$order_id' AND complaint_id = 0";
                $this->db->query($sql);
            }
            show_message(L('complaint_success'), L('back_complaint_list'), url('order/complaint_list'));
        }
    }

    /**
     * 上传图片
     */
    public function actionImgReturn()
    {
        $img = $_FILES['myfile']['tmp_name'];
        list($width, $height, $type) = getimagesize($img);
        if (empty($img)) {
            return;
        }
        //获取退货信息
        $user_id = $_SESSION['user_id'];
        $order_id = I('order_id');
        //判断文件类型
        if (empty($type)) {
            echo json_encode(['error' => 1, 'content' => '图片类型不正确']);
            return;
        }
        //上传图片并 获得路径
        $result = $this->upload('data/complaint_img', false, 2, [600, 600]);
        $path = $result['url']['myfile']['url'];
        $add_time = gmtime();
        $sql = "INSERT INTO {pre}complaint_img (order_id,user_id,img_file,complaint_id)values(" . $order_id . "," . $user_id . ",'" . $path . "',0)";
        $GLOBALS['db']->query($sql);
        $sql = "SELECT img_id, img_file FROM {pre}complaint_img WHERE user_id = " . $user_id . " and order_id = " . $order_id;
        $res = $GLOBALS['db']->query($sql);
        $img = [];
        foreach ($res as $key => $val) {
            $img[$key]['img_id'] = $val['img_id'];
            $img[$key]['pic'] = get_image_path($val['img_file']);
        }
        echo json_encode($img);
    }

    /**
     * 清空图片
     */
    public function actionClearPictures()
    {
        $id = I('id', 0, 'intval');
        $rec_id = I('order_id', 0, 'intval');
        $result = ['error' => 0, 'content' => ''];
        $sql = "select img_file from {pre}complaint_img where user_id = '" . $_SESSION['user_id'] . "' and order_id = '$rec_id'" . " and img_id=" . $id;
        $img_list = $GLOBALS['db']->getAll($sql);
        foreach ($img_list as $key => $row) {
            get_oss_del_file([$row['img_file']]);
            @unlink(get_image_path($row['img_file']));
        }
        $sql = "delete from {pre}complaint_img where user_id = '" . $_SESSION['user_id'] . "' and order_id = '$rec_id'" . " and img_id=" . $id;
        $GLOBALS['db']->query($sql);
        echo json_encode($result);
    }

    /**
     * 发布聊天
     */
    public function actionTalkRelease()
    {
        $talk_id = !empty($_REQUEST['talk_id']) ? intval($_REQUEST['talk_id']) : 0;
        $complaint_id = !empty($_REQUEST['complaint_id']) ? intval($_REQUEST['complaint_id']) : 0;
        $talk_content = !empty($_REQUEST['talk_content']) ? trim($_REQUEST['talk_content']) : '';
        $type = !empty($_REQUEST['type']) ? intval($_REQUEST['type']) : 0;

        //执行操作类型  1、刷新，0入库
        if ($type == 0) {
            $complaint_talk = [
                'complaint_id' => $complaint_id,
                'talk_member_id' => $_SESSION['user_id'],
                'talk_member_name' => $_SESSION['user_name'],
                'talk_member_type' => 1,
                'talk_content' => $talk_content,
                'talk_time' => gmtime(),
                'view_state' => 'user'
            ];
            $sql = "INSERT INTO" . $this->ecs->table("complaint_talk") . " (`complaint_id`,`talk_member_id`,`talk_member_name`,`talk_member_type`,`talk_content`,`talk_time`,`view_state`) VALUES"
                . " ('$complaint_id','$_SESSION[user_id]','$_SESSION[user_name]',1,'$talk_content','$complaint_talk[talk_time]','user')";
            $this->db->query($sql);
        }
        $talk_list = checkTalkView($complaint_id, 'user');
        $this->assign('talk_list', $talk_list);
        $result['content'] = $this->fetch("talklist");
        die(json_encode($result));
    }

    //删除订单投诉
    public function actionDelCompalint()
    {
        $complaint_id = I('compalint_id', 0, 'intval');
        if ($_SESSION['user_id'] > 0) {
            //删除相关图片
            del_complaint_img($complaint_id);
            del_complaint_img($complaint_id, 'appeal_img');
            //删除相关聊天
            del_complaint_talk($complaint_id);
            $sql = "DELETE FROM {pre}complaint WHERE complaint_id = '$complaint_id'";
            $this->db->query($sql);
            die(json_encode(['y' => 1]));
        }
    }

    /* 提交仲裁 */
    public function actionArbitration()
    {
        $complaint_id = !empty($_REQUEST['complaint_id']) ? intval($_REQUEST['complaint_id']) : 0;
        $complaint_state = !empty($_REQUEST['complaint_state']) ? intval($_REQUEST['complaint_state']) : 3;
        $set = '';
        if ($complaint_state == 4) {
            $set = ",end_handle_messg='买家自行关闭'";
        }
        $sql = "UPDATE {pre}complaint SET complaint_state = '$complaint_state' $set WHERE complaint_id = '$complaint_id'";
        $this->db->query($sql);
        show_message(L('apply_success'), '', url('user/order/complaint_list'));
    }
}
