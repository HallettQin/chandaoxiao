<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019-1-6
 * Time: 17:54
 */
define('IN_ECS', true);
require '../init.php';
require_once ROOT_PATH . 'includes/cls_json.php';
$json = new JSON();

$action = (isset($_POST['action']) ? $_POST['action'] : '');
$_params = (isset($_POST['params']) ? $_POST['params'] : '');
if (!$action || !$_params) {
    resultArray('数据来源不合法，请返回', 'error');
}
$params = aesDecrypt($_params);
$params = json_decode($params, true);
unset($_REQUEST);
if ($action !=  'signin' && $action != 'minibind') {
    $token = isset($params['token']) ? $params['token'] : '';
    if (!$token) {
        resultArray('数据来源不合法，请返回', 'error');
    }

    $kuaidi =  $GLOBALS['db']->getRow('SELECT * FROM ' . $GLOBALS['ecs']->table('kuaidi') . ' WHERE `token`="' .$token . '"', true);
    if (!$kuaidi) {
        resultArray('数据来源不合法，请返回', 'error');
    }
}
if ('signin' == $action) {
    //登录
    require './wechat.php';

    $code = isset($params['code']) ? $params['code'] : '';
    $token = isset($params['token']) ? $params['token'] : '';
    if (!$code && !$token) {
        resultArray('数据来源不合法，请返回', 'error');
    }

    if (!$token) {
        $wetchat_config = [
            'appid' => 'wx87a822690f2bf30c',
            'appsecret' => '83d40e0409d69eaca863a0203a48fc4a'
        ];
        $wechat = new Wechat($wetchat_config);
        $data = $wechat->jscode2session($code);
    } else {
        $data = $GLOBALS['db']->getRow('SELECT openid FROM ' . $GLOBALS['ecs']->table('kuaidi') . ' WHERE `token`="' .$token . '"', true);
    }

    if ($data && $data['openid']) {
        $kuaidi = $GLOBALS['db']->getRow('SELECT * FROM ' . $GLOBALS['ecs']->table('kuaidi') . ' WHERE `openid`= "' . $data['openid'] .'"', true);
        if (!$kuaidi) {
            resultArray(['openid' => $data['openid']], 'error');
        }

        //唯一token
        $dsc_token = get_dsc_token();

        $kuaidi['mobile'] = preg_replace('/(\d{3})\d{4}(\d{4})/', '$1****$2', $kuaidi['mobile']);

        resultArray(['token'=>$kuaidi['token'], 'id'=>$kuaidi['id'], 'login_name'=>$kuaidi['login_name'], 'mobile'=>$kuaidi['mobile']], 'success');
    } else {
        resultArray('登录失败', 'error');
    }
} elseif ('minibind' == $action) {
    //绑定
    $login_name = trim($params['login_name']);
    $password = trim($params['password']);
    $openid = trim($params['openid']);

    if (!$login_name) {
        resultArray('数据来源不合法，请返回', 'error');
    }

    if (!$password) {
        resultArray('数据来源不合法，请返回', 'error');
    }

    if (!$openid) {
        resultArray('数据来源不合法，请返回', 'error');
    }

    $result = $GLOBALS['db']->getRow("SELECT id, login_name, mobile FROM " . $GLOBALS['ecs']->table("kuaidi") . " WHERE login_name='$login_name' AND status = 1");
    if (!$result) {
        resultArray('账号或者密码错误', 'error');
    }

    if (md5($password) == $result['password']) {
        resultArray('账号或者密码错误', 'error');
    }

    if ($result['openid']) {
        resultArray('该账号已绑定微信', 'error');
    }

    //唯一token
    $dsc_token = get_dsc_token();

    //绑定
    $GLOBALS['db']->query('UPDATE ' . $GLOBALS['ecs']->table("kuaidi") . "SET `openid`='" . $openid . "', token ='" . $dsc_token . "' WHERE id = '" . $result['id'] . "'");
    $result['mobile'] = preg_replace('/(\d{3})\d{4}(\d{4})/', '$1****$2', $result['mobile']);
    resultArray(['token'=>$dsc_token, 'id'=>$result['id'], 'login_name'=>$result['login_name'], 'mobile'=>$result['mobile']], 'success');

} elseif ('wallet' == $action) {
    //可提现金额
    $result = ['available_money'=>$kuaidi['available_money']];
    if ($kuaidi['real_name'] && $kuaidi['bank_mobile'] && $kuaidi['bank_name'] && $kuaidi['bank_card']) {
        $result['has_bind'] = 1;
    } else {
        $result['has_bind'] = 0;
    }
    resultArray($result, 'success');

} elseif ('invoice_list' == $action) {
    //发货单列表
    //账单列表
    $date = $params['date'];
    $type = $params['type'];
    if (!$date || !in_array($type, ['all', 'settled', 'unsettled'])) {
        resultArray('数据来源不合法，请返回', 'error');
    }

    $bet_time = mFristAndLast(date('Y', strtotime($date)),  date('m', strtotime($date)));
    $_REQUEST['start_time'] = $bet_time['firstday'];
    $_REQUEST['end_time'] = $bet_time['lastday'];
    $_REQUEST['id'] = $kuaidi['id'];
    $_REQUEST['type'] = $type;
    $_REQUEST['page'] = $params['page'];

    $result = bill_order_list();

    resultArray($result, 'success');

} elseif ('invoice_detail' == $action) {

} elseif ('bill_list' == $action) {
    //账单列表
    $date = $params['date'];
    if (!$date) {
        resultArray('数据来源不合法，请返回', 'error');
    }


    $bet_time = mFristAndLast(date('Y', strtotime($date)),  date('m', strtotime($date)));
    $_REQUEST['start_time'] = $bet_time['firstday'];
    $_REQUEST['end_time'] = $bet_time['lastday'];
    $_REQUEST['id'] = $kuaidi['id'];
    $result = log_list();
    resultArray($result, 'success');

} elseif ('bill_detail' == $action) {
    //账单详情
} elseif ('withdrawal_apply' == $action) {
    //提现申请
    $deposit = floatval($params['deposit']);
    $password = isset($params['password']) ? trim($params['password']) : '';
    if (!$deposit) {
        resultArray('数据来源不合法，请返回', 'error');
    }

    if (!$password || md5($password) != $kuaidi['password']) {
        resultArray('支付密码错误', 'error');
    }

    if ($deposit > $kuaidi['available_money']) {
        resultArray('提现金额不够', 'error');
    }

    //修改成功
    $save = log_kuaidi_account_change($kuaidi['id'], -$deposit, $deposit);
    if ($save) {
        withdrawal_apply_log($kuaidi['id'], $deposit);
        kuaidi_account_log($kuaidi['id'], -$deposit, $deposit, '提现', 1);

        $available_money =  $kuaidi =  $GLOBALS['db']->getOne('SELECT available_money FROM ' . $GLOBALS['ecs']->table('kuaidi') . ' WHERE `token`="' .$token . '"', true);
        $result = ['available_money'=>$available_money];
        resultArray($result, 'success');
    } else {
        resultArray('申请失败', 'error');
    }
} elseif ('getcode' == $action) {
    $need = $params['need'];
    //获取验证码
    if ($need) {
        $mobile = $params['mobile'];
    } else {
        $mobile = $kuaidi['mobile'];
    }

    if (empty($mobile)) {
        resultArray('手机号码不能为空', 'error');
    }

    $code = random(6, 1);
    if (!saveSmscode($mobile, $code, 'sms_kuaidi_signin')) {
        resultArray('获取验证码太过频繁，一分钟之内只能获取一次。。', 'error');
    }

    $smsParams = array(
        'code' => $code,
        'mobile_phone' => $mobile,
        'mobilephone' => $mobile
    );

    if ($GLOBALS['_CFG']['sms_type'] == 0) {
        $sms = huyi_sms($smsParams, 'sms_kuaidi_signin');

    } elseif ($GLOBALS['_CFG']['sms_type'] >=1) {
        $result = sms_ali($smsParams, 'sms_kuaidi_signin'); //阿里大鱼短信变量传值，发送时机传值

        if ($result) {
            $resp = $GLOBALS['ecs']->ali_yu($result);
        } else {
            resultArray('数据来源不合法，请返回。', 'error');
        }
    }
    resultArray('发送成功。', 'success');
} elseif ('setpassword' == $action) {
    //验证手机验证码
    $mobile = $kuaidi['mobile'];
    $code = $params['smscode'];
    $password = $params['password'];
    if (!$code) {
        resultArray('验证码不能为空', 'error');
    }

    if (!$password) {
        resultArray('密码不能为空', 'error');
    }

    if (!checkSmscode($mobile, $code, 'sms_kuaidi_signin')) {
        resultArray('验证码错误', 'error');
    }

    //修改密码
    $GLOBALS['db']->query('UPDATE ' . $GLOBALS['ecs']->table("kuaidi") . 'SET `password`="' . md5($password) . '" WHERE id = ' . $kuaidi['id']);

    resultArray('修改成功', 'success');
} elseif ('logout' == $action) {
    //退出
    $data = [
        'openid' => '',
        'token'  => ''
    ];

    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('kuaidi'), $data, 'UPDATE', 'id = ' . $kuaidi['id']);
    resultArray('退出成功', 'success');
} elseif ('change_phone'  == $action) {
    //更换手机
    $step = $params['step'] ? $params['step'] : 'one';

    switch ($step) {
        case 'one':
            $code = $params['smscode'];
            if (!$code) {
                resultArray('验证码不能为空', 'error');
            }

            //验证手机验证码
            $mobile = $kuaidi['mobile'];

            if (!checkSmscode($mobile, $code, 'sms_kuaidi_signin')) {
                resultArray('验证码错误', 'error');
            }

            resultArray('验证成功。', 'success');
            break;
        case 'two':
            $code = $params['smscode'];
            if (!$code) {
                resultArray('验证码不能为空', 'error');
            }
            $mobile = $params['mobile'];
            if (!$mobile) {
                resultArray('手机号不能为空', 'error');
            }

            if ($mobile == $kuaidi['mobile']) {
                resultArray('手机号与原手机号一样', 'error');
            }

            $find =  $GLOBALS['db']->getRow('SELECT * FROM ' . $GLOBALS['ecs']->table('kuaidi') . ' WHERE `mobile`="' .$mobile . '"', true);
            if ($find) {
                resultArray('该手机号已绑定其他账号', 'error');
            }

            if (!checkSmscode($mobile, $code, 'sms_kuaidi_signin')) {
                resultArray('验证码错误', 'error');
            }

            //退出
            $data = [
                'mobile' => $mobile,
            ];

            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('kuaidi'), $data, 'UPDATE', 'id = ' . $kuaidi['id']);
            resultArray('更换成功。', 'success');
            break;
    }
} elseif ('set_paypassword' ==  $action) {
    //验证手机验证码
    $mobile = $kuaidi['mobile'];
    $code = $params['smscode'];
    $password = $params['password'];
    if (!$code) {
        resultArray('验证码不能为空', 'error');
    }

    if (!$password) {
        resultArray('密码不能为空', 'error');
    }

    if (!checkSmscode($mobile, $code, 'sms_kuaidi_signin')) {
        resultArray('验证码错误', 'error');
    }

    //修改密码
    $GLOBALS['db']->query('UPDATE ' . $GLOBALS['ecs']->table("kuaidi") . 'SET `kouling`="' . md5($password) . '" WHERE id = ' . $kuaidi['id']);

    resultArray('修改成功', 'success');
} elseif ('bank' == $action) {
    if ($kuaidi['real_name'] && $kuaidi['bank_mobile'] && $kuaidi['bank_name'] && $kuaidi['bank_card']) {
        $bankCardNo = $kuaidi['bank_card'];
        $kuaidi['bank_card'] = substr($bankCardNo,0,4) . " **** **** **** " . substr($bankCardNo,-4,4);
        resultArray(['bank_name' => $kuaidi['bank_name'], 'bank_card' => $kuaidi['bank_card']], 'success');
    } else {
        resultArray('未绑定', 'error');
    }
} elseif ('bind_bank' == $action) {
    //绑定银行卡
    $real_name = isset($params['real_name']) ? trim($params['real_name']) : '';
    $bank_mobile = isset($params['bank_mobile']) ? trim($params['bank_mobile']) : '';
    $bank_name = isset($params['bank_name']) ? trim($params['bank_name']) : '';
    $bank_card = isset($params['bank_card']) ? trim($params['bank_card']) : '';
    $code = isset($params['smscode']) ? trim($params['smscode']) : '';
    $password = isset($params['password']) ? trim($params['password']) : '';
    if (!$real_name || !$bank_name || !$bank_name || !$bank_card) {
        resultArray('数据来源不合法，请返回1', 'error');
    }
    if (!$code) {
        resultArray('验证码不能为空', 'error');
    }
    if (!$password || md5($password) != $kuaidi['password']) {
        resultArray('支付密码错误', 'error');
    }

    if (!checkSmscode($bank_mobile, $code, 'sms_kuaidi_signin')) {
        resultArray('验证码错误', 'error');
    }

    $data = [
        'real_name' => $real_name,
        'bank_mobile' => $bank_mobile,
        'bank_name' => $bank_name,
        'bank_card' => $bank_card,
    ];

    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('kuaidi'), $data, 'UPDATE', 'id = ' . $kuaidi['id']);
    resultArray('绑定成功', 'success');
}

function random($length = 6, $numeric = 0)
{
    (PHP_VERSION < '4.2.0') && mt_srand((double) microtime() * 1000000);

    if ($numeric) {
        $hash = sprintf('%0' . $length . 'd', mt_rand(0, pow(10, $length) - 1));
    }
    else {
        $hash = '';
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789abcdefghjkmnpqrstuvwxyz';
        $max = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $hash .= $chars[mt_rand(0, $max)];
        }
    }

    return $hash;
}

//提现申请记录
function withdrawal_apply_log($id, $deposit) {
    if ($id) {
        $log = array(
            'kuaidi_id' => $id,
            'deposit' => $deposit,
            'add_time' => gmtime()
        );
        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('kuaidi_withdrawal_apply'), $log, 'INSERT');
    }
}

/**
 * 查询资金流水
 */
function bill_order_list($page=0) {
    //发货账单
    $id = $_REQUEST['id'];
    $sql = $GLOBALS['ecs']->table("kuaidi_bill_order") . " where kuaidi_id = $id ";
    /* 分页大小 */
    $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);
    if($page > 0){
        $filter['page'] = $page;
    }

    if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0)
    {
        $filter['page_size'] = intval($_REQUEST['page_size']);
    }
    elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0)
    {
        $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
    }
    else
    {
        $filter['page_size'] = 5;
    }

    if (isset($_REQUEST['start_time'])) {
        $sql .= ' AND "' . $_REQUEST['start_time'] . '" <= shipping_time';
    }
    if (isset($_REQUEST['end_time'])) {
        $sql .= ' AND "' . $_REQUEST['end_time'] . '" >= shipping_time';
    }
    $sql .= ' AND is_kuaidi_update = 1';
    if (isset( $_REQUEST['type'])) {
        switch ($_REQUEST['type']) {
            case 'all':
                break;
            case 'settled':
                $sql .= ' AND is_jiesuan = 1';
                break;
            case 'unsettled':
                $sql .= ' AND is_jiesuan = 0';
                break;
        }
    }

    $sql .= ' order by invoice_id desc';

    //记录数
    $record_count = count($GLOBALS['db']->getAll("select * from " .$sql));

    //总金额
    $total = $GLOBALS['db']->getOne("select SUM(kuaidi_shipping_fee) total from " .$sql);

    $sql .= " LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ",$filter[page_size]";
    $lists =  $GLOBALS['db']->getAll("select * from " .$sql);

    $arr['lists'] = $lists;
    $arr['total'] = $total ? $total : 0;
    $arr['record_count'] = $record_count;
    //页数
    $arr['page_count'] = $record_count > 0 ? ceil($record_count / $filter['page_size']) : 0;
    return $arr;
}

/**
 * 查询资金流水
 */
function log_list() {
    $id = $_REQUEST['id'];

    $sql = "SELECT * FROM ". $GLOBALS['ecs']->table("kuaidi_account_log") . " where kuaidi_id = $id ";
    if (isset($_REQUEST['start_time'])) {
        $sql .= ' AND "' . $_REQUEST['start_time'] . '" <= change_time';
    }
    if (isset($_REQUEST['end_time'])) {
        $sql .= ' AND "' . $_REQUEST['end_time'] . '" >= change_time';
    }
    $sql .= ' AND change_type != 0';

    $sql .= ' order by change_time desc';
    $logs =  $GLOBALS['db']->getAll($sql);

    $total =  0;
    $expend = 0;
    foreach ($logs as $k => $log) {
        if (in_array($log['change_type'], [2])) {
            $total += $log['available_money'];
        }
        if (in_array($log['change_type'], [1])) {
            $expend += $log['available_money'];
        }
    }

    $arr['logs'] = $logs;
    $arr['total'] = $total;
    $arr['expend'] = -$expend;
    return $arr;
}

/**
 *  获取发货单列表信息
 *
 * @access  public
 * @param
 *
 * @return void
 */
function delivery_list() {
}

/**
 * 保存验收码
 * @param $mobile
 * @param $code
 * @param $send_time
 */
function saveSmscode($mobile, $code, $send_time) {
    $smscode =  $GLOBALS['db']->getRow('SELECT * FROM ' . $GLOBALS['ecs']->table('smscode') . ' WHERE `mobile`="' .$mobile . '" and  `send_time`="' . $send_time . '" order by id desc', true);

    $time = gmtime();
    if ($smscode) {
        if ($smscode['createtime'] + 60 >= $time && $smscode['status'] == 0) {
            return false;
        }

        $data = [
            'code' => $code,
            'send_time'  => $send_time,
            'createtime' => $time,
            'expiretime' => $time + 15 * 60,
            'status' => 0
        ];

        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('smscode'), $data, 'UPDATE', 'id = ' . $smscode['id']);
        return true;
    }

    $data = [
        'mobile' => $mobile,
        'code' => $code,
        'send_time'     => $send_time,
        'createtime' => $time,
        'expiretime' => $time + 15 * 60
    ];

    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('smscode'), $data, 'INSERT');
    return true;
}


/**
 * 检查验收码
 * @param $mobile
 * @param $code
 * @param $send_time
 * @return bool
 */
function checkSmscode($mobile, $code, $send_time) {
    $time = gmtime();
    $smscode =  $GLOBALS['db']->getRow('SELECT * FROM ' . $GLOBALS['ecs']->table('smscode') . ' WHERE `mobile`="' .$mobile . '" and  `send_time`="' . $send_time . '" order by id desc', true);
    if ($smscode && $code && $smscode['code'] == $code) {
        if ($smscode['expiretime'] >= $time && $smscode['status'] == 0) {
            $data = [
                'status' => 1
            ];

            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('smscode'), $data, 'UPDATE', 'id = ' . $smscode['id']);
            return true;
        }
    }

    return false;
}

function resultArray($data, $type='success') {
    $json = new JSON();

    $results = [];
    if ('success' == $type) {
        $results['code'] = 1;
    } else {
        $results['code'] = 0;
    }

    $results['data'] = $data;
    exit($json->encode($results));
}

function mFristAndLast($y = "", $m = ""){
    if ($y == "") $y = date("Y");
    if ($m == "") $m = date("m");
    $m = sprintf("%02d", intval($m));
    $y = str_pad(intval($y), 4, "0", STR_PAD_RIGHT);

    $m>12 || $m<1 ? $m=1 : $m=$m;
    $firstday = strtotime($y . $m . "01000000");
    $firstdaystr = date("Y-m-01", $firstday);
    $lastday = strtotime(date('Y-m-d 23:59:59', strtotime("$firstdaystr +1 month -1 day")));

    return array(
        "firstday" => $firstday,
        "lastday" => $lastday
    );
}


