<?php
/**
 * ECMOBAN 公用函数库
 * ============================================================================
 * * 版权所有 上海商创网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecmoban.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: zhuo $
 * $Id: lib_ecmobanFunc.php 17217 2014-05-01 09:00:00Z zhuo $
*/


//IP地区名称 start
function get_ip_area_name($ip = 0, $api = 0){

    $api = isset($GLOBALS['_CFG']['ip_type']) ? $GLOBALS['_CFG']['ip_type'] : 0;

    if(empty($ip)){
        $ip = real_ip(); //获取当前用户的ip real_ip()
    }

    /**
     * 淘宝、腾讯、新浪
     */
    if ($api == 1) {
        $arr = get_taobao_api($ip);
    } elseif ($api == 2) {
        $arr = get_qq_api($ip);
    } else {
        $arr = get_sina_api($ip);
    }

    //获取默认数据 by wu
    if (empty($arr['region'])) {
        $region_list = get_regions();
        if ($region_list) {
            $region = reset($region_list);
            $arr['region'] = $region['region_name'];
        } else {
            $arr['region'] = '';
        }
        $arr['county_level'] = 1;
    }

    $area_name = str_replace(array('省', '市', "'"), '', $arr['region']);

    if(strstr($area_name, '香港')){
        $area_name = "香港";
    }elseif(strstr($area_name, '澳门')){
        $area_name = "澳门";
    }elseif(strstr($area_name, '内蒙古')){
        $area_name = "内蒙古";
    }elseif(strstr($area_name, '宁夏')){
        $area_name = "宁夏";
    }elseif(strstr($area_name, '新疆')){
        $area_name = "新疆";
    }elseif(strstr($area_name, '西藏')){
        $area_name = "西藏";
    }elseif(strstr($area_name, '广西')){
        $area_name = "广西";
    }

    $arr['area_name'] = $area_name;

    return $arr;
}

/**
 * 淘宝API
 */
function get_taobao_api($ip = 0){

    $Http = new Http();
    $url = "http://ip.taobao.com/service/getIpInfo.php?ip=" . $ip;
    $data = $Http->doGet($url); //调用淘宝API接口获取信息
    $str = json_decode($data, true);

    if (!is_array($str) || $ip == '127.0.0.1') {
        if (!empty($GLOBALS['_CFG']['shop_city'])) {
            $ip_city = get_shop_address($GLOBALS['_CFG']['shop_city']);
            $str = array('data' => array('city' => $ip_city, 'county' => ''));
        } elseif (!empty($GLOBALS['_CFG']['shop_province'])) {
            $ip_province = get_shop_address($GLOBALS['_CFG']['shop_province']);
            $str = array('data' => array('city' => '', 'county' => '', 'region' => $ip_province));
        } else {
            $str = array('data' => array('region' => "上海", 'city' => '', 'county' => ''));
        }
    }

    $arr = array();
    if (!empty($str['data']['county'])) { //市级
        $arr['region'] = $str['data']['county'];
        $arr['county_level'] = 2;
    } else {
        if (!empty($str['data']['city'])) { //市级

            if(str_len($str['data']['city']) > 4){
                $str['data']['city'] = mb_substr($str['data']['city'], 0, 2, "utf-8");
            }

            $arr['region'] = $str['data']['city'];
            $arr['county_level'] = 2;
        } else { //省级或特别行政区
            $arr['region'] = $str['data']['region'];
            $arr['county_level'] = 1;
        }
    }

    return $arr;
}

/**
 * 腾讯API
 */
function get_qq_api($ip = 0) {

    $Http = new Http();
    $key = !empty($GLOBALS['_CFG']['tengxun_key']) ? $GLOBALS['_CFG']['tengxun_key'] : 0;
    $url = "http://apis.map.qq.com/ws/location/v1/ip?ip=" . $ip . "&key=" . $key;
    $data = $Http->doGet($url); //调用新浪API接口获取信息
    $str = json_decode($data, true);

    if (!is_array($str) || $ip == '127.0.0.1' || $str['status'] == 110 || $str['status'] == 311) {
        if (empty($str['result']['ad_info']['city']) && empty($str['result']['ad_info']['province'])) {
            if (!empty($GLOBALS['_CFG']['shop_city'])) {
                $ip_city = get_shop_address($GLOBALS['_CFG']['shop_city']);
                $str['result']['ad_info'] = array('city' => $ip_city, 'province' => '');
            } elseif (!empty($GLOBALS['_CFG']['shop_province'])) {
                $ip_province = get_shop_address($GLOBALS['_CFG']['shop_province']);
                $str['result']['ad_info'] = array('city' => '', 'province' => $ip_province);
            } else {
                $str['result']['ad_info'] = array('city' => "上海");
            }
        }
    }

    $arr = array();
    if (!empty($str['result']['ad_info']['city'])) { //市级

        if(str_len($str['result']['ad_info']['city']) > 4){
            $str['result']['ad_info']['city'] = mb_substr($str['result']['ad_info']['city'], 0, 2, "utf-8");
        }

        $arr['region'] = $str['result']['ad_info']['city'];
        $arr['county_level'] = 2;
    } elseif (!empty($str['result']['ad_info']['province'])) { //省级或特别行政区
        $arr['region'] = $str['result']['ad_info']['province'];
        $arr['county_level'] = 1;
    }

    return $arr;
}

/**
 * 新浪API
 */
function get_sina_api($ip = 0) {

    $Http = new Http();
    $url = "http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json&ip=" . $ip;
    $data = $Http->doGet($url); //调用新浪API接口获取信息
    $str = json_decode($data, true);

    if (!is_array($str) || $ip == '127.0.0.1') {
        if (!empty($GLOBALS['_CFG']['shop_city'])) {
            $ip_city = get_shop_address($GLOBALS['_CFG']['shop_city']);
            $str = array('city' => $ip_city, 'province' => '');
        } elseif (!empty($GLOBALS['_CFG']['shop_province'])) {
            $ip_province = get_shop_address($GLOBALS['_CFG']['shop_province']);
            $str = array('city' => '', 'province' => $ip_province);
        } else {
            $str = array('city' => "上海");
        }
    }

    $arr = array();
    if (!empty($str['city'])) { //市级

        if(str_len($str['city']) > 4){
            $str['city'] = mb_substr($str['city'], 0, 2, "utf-8");
        }

        $arr['region'] = $str['city'];
        $arr['county_level'] = 2;
    } elseif (!empty($str['province'])) { //省级或特别行政区
        $arr['region'] = $str['province'];
        $arr['county_level'] = 1;
    }

    return $arr;
}

//IP地区名称 end

/**
 * 处理序列化的支付、配送的配置参数
 * 返回一个以name为索引的数组
 *
 * @access  public
 * @param   string       $cfg
 * @return  void
 */
function sc_unserialize_config($cfg)
{
    if (is_string($cfg) && ($arr = unserialize($cfg)) !== false)
    {
        $config = array();

        foreach ($arr AS $key => $val)
        {
            $config[$val['name']] = $val['value'];
        }

        return $config;
    }
    else
    {
        return false;
    }
}

/**
 * 生成查询订单的sql
 * @param   string  $type   类型
 * @param   string  $alias  order表的别名（包括.例如 o.）
 * @return  string
 */
function get_order_query_sql($type = 'finished', $alias = '')
{
    /* 已完成订单：已确认订单、已付款、已发货（用户已确认收货） */
    if ($type == 'finished')
    {
        return " AND {$alias}order_status " . db_create_in(array(OS_CONFIRMED, OS_SPLITED)) .
               " AND {$alias}shipping_status " . db_create_in(array(SS_RECEIVED)) .
               " AND {$alias}pay_status " . db_create_in(array(PS_PAYED, PS_PAYING)) . " ";
    }
    /* 待发货订单 */
    elseif ($type == 'await_ship')
    {
        return " AND   {$alias}order_status " .
                 db_create_in(array(OS_CONFIRMED, OS_SPLITED, OS_SPLITING_PART)) .
               " AND   {$alias}shipping_status " .
                 db_create_in(array(SS_UNSHIPPED, SS_PREPARING, SS_SHIPPED_ING)) .
               " AND ( {$alias}pay_status " . db_create_in(array(PS_PAYED, PS_PAYING)) . " OR {$alias}pay_id " . db_create_in(get_payment_id_list(true)) . ") ";
    }
    /* 待付款订单 */
    elseif ($type == 'await_pay')
    {
        return " AND   {$alias}order_status " . db_create_in(array(OS_CONFIRMED, OS_SPLITED)) .
               " AND   {$alias}pay_status " . db_create_in(array(PS_UNPAYED, PS_PAYED_PART)) .
               " AND ( {$alias}shipping_status " . db_create_in(array(SS_SHIPPED, SS_RECEIVED)) . " OR {$alias}pay_id " . db_create_in(get_payment_id_list(false)) . ") ";
    }
    /* 未确认订单 */
    elseif ($type == 'unconfirmed')
    {
        return " AND {$alias}order_status = '" . OS_UNCONFIRMED . "' ";
    }
    /* 未处理订单：用户可操作 */
    elseif ($type == 'unprocessed')
    {
        return " AND {$alias}order_status " . db_create_in(array(OS_UNCONFIRMED, OS_CONFIRMED)) .
               " AND {$alias}shipping_status = '" . SS_UNSHIPPED . "'" .
               " AND {$alias}pay_status = '" . PS_UNPAYED . "' ";
    }
    /* 未付款未发货订单：管理员可操作 */
    elseif ($type == 'unpay_unship')
    {
        return " AND {$alias}order_status " . db_create_in(array(OS_UNCONFIRMED, OS_CONFIRMED)) .
               " AND {$alias}shipping_status " . db_create_in(array(SS_UNSHIPPED, SS_PREPARING)) .
               " AND {$alias}pay_status = '" . PS_UNPAYED . "' ";
    }
    /* 已发货订单：不论是否付款 */
    elseif ($type == 'shipped')
    {
        return " AND {$alias}order_status = '" . OS_CONFIRMED . "'" .
               " AND {$alias}shipping_status " . db_create_in(array(SS_SHIPPED, SS_RECEIVED)) . " ";
    }
    /* 待确认收货：已确认订单、已付款、已发货（待用户确认收货） */
    elseif ($type == 'to_confirm')
    {
        return " AND {$alias}order_status " . db_create_in(array(OS_CONFIRMED, OS_SPLITED, OS_SPLITING_PART)) . " " .
               " AND {$alias}shipping_status = '" .SS_SHIPPED. "'" .
               " AND {$alias}pay_status " . db_create_in(array(PS_PAYED, PS_PAYING));
    }
    else
    {
        die('函数 order_query_sql 参数错误');
    }
}

/*
 * 删除一条字符串里面的多个字符
 * $strCnt 字符串内容
 * $re_str 删除字符串内容
 */
function get_del_in_val($strCnt, $re_str){

    $strCnt = explode(',', $strCnt);
    $re_str = explode(',', $re_str);
	$newstrCnt = $strCnt;

    for($i=0; $i<count($re_str); $i++){
        for($j=0; $j<count($strCnt); $j++){
            if($re_str[$i] == $strCnt[$j]){
                unset($newstrCnt[$j]);
            }
        }
    }

    $strCnt = implode(',', $newstrCnt);
    return $strCnt;
}

/**
 * 取得支付方式id列表
 * @param   bool    $is_cod 是否货到付款
 * @return  array
 */
function get_payment_id_list($is_cod)
{
    $sql = "SELECT pay_id FROM " . $GLOBALS['ecs']->table('payment');
    if ($is_cod)
    {
        $sql .= " WHERE is_cod = 1";
    }
    else
    {
        $sql .= " WHERE is_cod = 0";
    }

    return $GLOBALS['db']->getCol($sql);
}

/**
 * 生成查询订单总金额的字段
 * @param   string  $alias  order表的别名（包括.例如 o.）
 * @return  string
 */
function get_order_amount_field($alias = '')
{
    return "   {$alias}goods_amount + {$alias}tax + {$alias}shipping_fee" .
           " + {$alias}insure_fee + {$alias}pay_fee + {$alias}pack_fee" .
           " + {$alias}card_fee ";
}

/*简化sql获取数据
 *$table 表名称
 *$where 查询条件 例子：$where = "goods_id = '$goods_id' and user_id = '$user_id'"
 *$date 传值数组方式
 *$sqlType 获取数据方式 0:取一维数组数据, 1:取二维数组数据 2:取单字段数据集
 */
function get_table_date($table = '', $where = 1, $date = array(), $sqlType = 0){

	$date = implode(',', $date);

	if(!empty($date)){

            if($sqlType != 1){
                $where .= " LIMIT 1";
            }

           $sql = "SELECT " .$date. " FROM " .$GLOBALS['ecs']->table($table). " WHERE " . $where;

            if($sqlType == 1){
                    return $GLOBALS['db']->getAll($sql);
            }elseif($sqlType == 2){
                    return $GLOBALS['db']->getOne($sql);
            }else{
                    return $GLOBALS['db']->getRow($sql);
            }
	}
}

/**
 * 获得商品分类的信息
 *
 * @param   integer $cat_id
 *
 * @return  void
 */
function get_store_cat_info($cat_id)
{
    $select = array('cat_name', 'cat_alias_name', 'keywords', 'cat_desc', 'style', 'grade', 'filter_attr', 'parent_id');
    return get_cat_info($cat_id, $select, 'merchants_category');
}

/**
 * 取得最近的上级分类的grade值
 *
 * @access  public
 * @param   int     $cat_id    //当前的cat_id
 *
 * @return int
 */
function get_store_parent_grade($cat_id)
{
    static $res = NULL;

    if ($res === NULL)
    {
        $data = read_static_cache('cat_parent_grade');
        if ($data === false)
        {
            $sql = "SELECT parent_id, cat_id, grade ".
                   " FROM " . $GLOBALS['ecs']->table('category');
            $res = $GLOBALS['db']->getAll($sql);
            write_static_cache('cat_parent_grade', $res);
        }
        else
        {
            $res = $data;
        }
    }

    if (!$res)
    {
        return 0;
    }

    $parent_arr = array();
    $grade_arr = array();

    foreach ($res as $val)
    {
        $parent_arr[$val['cat_id']] = $val['parent_id'];
        $grade_arr[$val['cat_id']] = $val['grade'];
    }

    while ($parent_arr[$cat_id] >0 && $grade_arr[$cat_id] == 0)
    {
        $cat_id = $parent_arr[$cat_id];
    }

    return $grade_arr[$cat_id];

}

//数据打印
function get_print_r($arr){
	echo "<pre>";
	print_r($arr);
	echo "</pre>";
}

/**
 * 计算运费
 * @param   string  $shipping_code      配送方式代码
 * @param   mix     $shipping_config    配送方式配置信息
 * @param   float   $goods_weight       商品重量
 * @param   float   $goods_amount       商品金额
 * @param   float   $goods_number       商品数量
 * @return  float   运费
 */
function goods_shipping_fee($shipping_code, $shipping_config, $goods_weight, $goods_amount, $goods_number='')
{
    if (!is_array($shipping_config))
    {
        $shipping_config = unserialize($shipping_config);
    }

    $filename = ROOT_PATH . 'includes/modules/shipping/' . $shipping_code . '.php';
    if (file_exists($filename))
    {
        include_once($filename);

        $obj = new $shipping_code($shipping_config);

        return $obj->calculate($goods_weight, $goods_amount, $goods_number);
    }
    else
    {
        return 0;
    }
}

/**
 * 获得指定国家的所有省份
 *
 * @access      public
 * @param       int     country    国家的编号
 * @return      array
 */
function get_regions_steps($type = 0, $parent = 0) {
    $sql = 'SELECT region_id, region_name FROM ' . $GLOBALS['ecs']->table('region') .
            " WHERE region_type = '$type' AND parent_id = '$parent'";

    return $GLOBALS['db']->GetAll($sql);
}

//后台程序代码-------------------------------------

//添加或删除字段函数

/**
 * 新增、修改表字段
 */
function get_Add_Drop_fields($date, $newDate = '', $table = '', $type = 'insert', $dateType = 'VARCHAR', $length = '', $IntType = 'NOT NULL', $comment = '') {

    $date = trim($date);
    $comment = trim($comment);

    if (empty($newDate)) { //修改字段名称
        $newDate = $date;
    }

    //修改字段类型
    if ($dateType == 'VARCHAR') { //长字符串
        $length = empty($length) ? 255 : $length;

        $dateType = "VARCHAR( " . $length . " )";
    } elseif ($dateType == 'CHAR') { //短字符串
        $length = empty($length) ? 60 : $length;

        $dateType = "CHAR( " . $length . " )";
    } elseif ($dateType == 'INT') { //数据类型
        $length = empty($length) ? 11 : $length;
        $codingType = '';
        $coding = '';

        $dateType = "INT( " . $length . " ) UNSIGNED";
    } elseif ($dateType == 'MEDIUMINT') { //数据类型
        $length = empty($length) ? 11 : $length;
        $codingType = '';
        $coding = '';

        $dateType = "MEDIUMINT( " . $length . " ) UNSIGNED";
    } elseif ($dateType == 'SMALLINT') { //数据类型
        $length = empty($length) ? 11 : $length;
        $codingType = '';
        $coding = '';

        $dateType = "SMALLINT( " . $length . " ) UNSIGNED";
    } elseif ($dateType == 'TINYINT') { //数据类型
        $length = empty($length) ? 1 : $length;
        $codingType = '';
        $coding = '';

        $dateType = "TINYINT( " . $length . " ) UNSIGNED";
    } elseif ($dateType == 'TEXT') { //文本类型
        $length = '';
        $dateType = "TEXT";
    } elseif ($dateType == 'DECIMAL') { //保留几位数类型
        $length = empty($length) ? '10,2' : $length;
        $codingType = '';
        $coding = '';

        $dateType = "DECIMAL( " . $length . " )";
    }

    //修改字段是否为空
    if ($IntType != 'NOT NULL') {
        $IntType = 'NULL';
    }

    if (!empty($comment)) {
        $comment = " COMMENT '" . $comment . "'";
    }

    if (!empty($table) && !empty($date)) {

        //字段操作 start
        if ($type == 'insert') {
            $sql = "ALTER TABLE " . $GLOBALS['ecs']->table($table) . " ADD `" . $date . "` " . $dateType . " " . $IntType . $comment;
        } elseif ($type == 'update') {
            $sql = "ALTER TABLE " . $GLOBALS['ecs']->table($table) . " CHANGE `" . $date . "` `" . $newDate . "` " . $dateType . " " . $codingType . " " . $IntType . " " . $comment;
        } elseif ($type == 'delete') {
            $sql = "ALTER TABLE " . $GLOBALS['ecs']->table($table) . " DROP `" . $date . "`";
        }
        //字段操作 end

        $res = $GLOBALS['db']->query($sql);

        if ($res == 1) {
            return 1;
        } else {
            return 3;
        }
    } else {
        return 2;
    }
}

/* 查询表字段是否存在 */
function get_table_file_name($table = '', $name = ''){
    if($table != '' && $name != ''){
        $field = $GLOBALS['db']->query("Describe $table $name");
        $field = $GLOBALS['db']->fetch_array($field);

        if($field){
            $bool = 1;
        }else{
            $bool = 0;
        }

        return array('field'=>$field, 'bool'=>$bool);
    }else{
        echo "表名称或表字段名称不能为空";
    }
}

/**
 * 循环表字段增加修改
 */
function get_array_fields($date, $newDate, $table, $type, $dateType, $length) {

    for ($i = 0; $i < count($date); $i++) {
        get_Add_Drop_fields($date[$i], $newDate[$i], $table, $type, $dateType[$i], $length[$i]);
    }
}

/******************文章函数 start************************/

//查找商家入驻文章列表
function get_merchants_article_menu($cat_id){
	$sql = "select article_id, title, file_url, open_type, article_type from " .$GLOBALS['ecs']->table('article'). " where cat_id = '$cat_id' order by article_id desc";
	$res = $GLOBALS['db']->getAll($sql);

	$arr = array();
	foreach($res as $key=>$row){
		$arr[$key]['article_id'] = $row['article_id'];
		$arr[$key]['article_type'] = $row['article_type'];
		$arr[$key]['title'] = $row['title'];
		if($row['open_type'] != 1){
			$arr[$key]['url'] = build_uri('merchants', array('mid'=>$row['article_id']), $row['title']);
		}else{
			$arr[$key]['url'] = $row['file_url'];
		}
	}

	return $arr;
}

//查找商家入驻文章内容
function get_merchants_article_info($article_id){
	$sql = "select content from " .$GLOBALS['ecs']->table('article'). " where article_id = '$article_id'";
	return $GLOBALS['db']->getRow($sql);
}

/******************文章函数 end************************/

/******************入驻流程函数 start************************/

function get_merchants_steps_fields_admin($table, $date, $dateType, $length, $notnull, $coding, $formName, $fields_sort, $tid) {
    $arr = array();
    for ($i = 0; $i < count($date); $i++) {
        if (!empty($date[$i])) {
            $arr[$i]['date'] = $date[$i];
            $arr[$i]['dateType'] = $dateType[$i];
            $arr[$i]['length'] = $length[$i];
            $arr[$i]['notnull'] = $notnull[$i];
            $arr[$i]['formName'] = $formName[$i];
            $arr[$i]['coding'] = $coding[$i];
            $arr[$i]['fields_sort'] = $fields_sort[$i];

            $arr['textFields'] .= $date[$i] . ',';
            $arr['fieldsDateType'] .= $dateType[$i] . ',';
            $arr['fieldsLength'] .= $length[$i] . ',';
            $arr['fieldsNotnull'] .= $notnull[$i] . ',';
            $arr['fieldsFormName'] .= $formName[$i] . ',';
            $arr['fieldsCoding'] .= $coding[$i] . ',';
            $arr['fields_sort'] .= $fields_sort[$i] . ',';
            $arr['will_choose'] .= $_POST['will_choose_' . $i] . ',';

            if ($dateType[$i] == 'INT' || $dateType[$i] == 'TINYINT' || $dateType[$i] == 'DECIMAL' || $dateType[$i] == 'MEDIUMINT' || $dateType[$i] == 'SMALLINT') {
                $arr[$i]['coding'] = '';
            }

            //判断数据库表的字段是否存在
            $test = $GLOBALS['db']->query('Describe ' . $GLOBALS['ecs']->table($table) . $date[$i]);
            $test = $GLOBALS['db']->fetch_array($test);

            if (is_array($test)) { //表字段存在
                $type = 'update';
                $newDate = ''; //修改表名称
            } else { //表字段不存在
                $type = 'insert';
            }

            $failure = get_Add_Drop_fields($arr[$i]['date'], $newDate, $table, $type, $arr[$i]['dateType'], $arr[$i]['length'], $arr[$i]['notnull'], $arr[$i]['formName'], $arr[$i]['coding']);

            if ($failure == 2) {
                $sql = "select fields_steps from " . $GLOBALS['ecs']->table('merchants_steps_title') . " where tid = '$tid'";
                $pid = $GLOBALS['db']->getOne($sql);

                $link[] = array('text' => '返回一页', 'href' => 'merchants_steps.php?act=title_list&id=' . $pid);
                sys_msg('表名称为空', 0, $link);
                break;
            }
        }
    }

    $arr['textFields'] = substr($arr['textFields'], 0, -1);
    $arr['fieldsDateType'] = substr($arr['fieldsDateType'], 0, -1);
    $arr['fieldsLength'] = substr($arr['fieldsLength'], 0, -1);
    $arr['fieldsNotnull'] = substr($arr['fieldsNotnull'], 0, -1);
    $arr['fieldsFormName'] = substr($arr['fieldsFormName'], 0, -1);
    $arr['fieldsCoding'] = substr($arr['fieldsCoding'], 0, -1);
    $arr['fields_sort'] = substr($arr['fields_sort'], 0, -1);
    $arr['will_choose'] = substr($arr['will_choose'], 0, -1);

    return $arr;
}

//选择表单类型
function get_steps_form_choose($form_array = array()) {

    $form = $form_array['form'];

    $arr = array();
    for ($i = 0; $i < count($form); $i++) {

        if (!empty($form_array['formName_special'][$i])) {
            $formName_special = '+' . $form_array['formName_special'][$i];
        } else {
            $formName_special = '+' . ' ';
        }

        if ($form[$i] == 'input') {
            $arr[$i]['form'] = $form[$i] . ':' . $form_array['formSize'][$i] . $formName_special;
        } elseif ($form[$i] == 'textarea') {
            $arr[$i]['form'] = $form[$i] . ':' . $form_array['rows'][$i] . ',' . $form_array['cols'][$i] . $formName_special;
        } elseif ($form[$i] == 'radio') {
            $arr[$i]['form'] = $form[$i] . ':' . implode(',', get_formType_arr($_POST['radio_checkbox_' . $i], $_POST['rc_sort_' . $i])) . $formName_special;
        } elseif ($form[$i] == 'checkbox') {
            $arr[$i]['form'] = $form[$i] . ':' . implode(',', get_formType_arr($_POST['radio_checkbox_' . $i], $_POST['rc_sort_' . $i])) . $formName_special;
        } elseif ($form[$i] == 'select') {
            $arr[$i]['form'] = $form[$i] . ':' . implode(',', get_formType_arr($_POST['select_' . $i], '', 1)) . $formName_special;
        } elseif ($form[$i] == 'other') {
            if ($form_array['formOther'][$i] == 'dateTime') {
                $dateTimeText = ',' . $form_array['formOtherSize'][$i];
            }
            $arr[$i]['form'] = $form[$i] . ':' . $form_array['formOther'][$i] . $dateTimeText . $formName_special;
        }

        if (!empty($form_array['date'][$i])) {
            $arr['chooseForm'] .= $arr[$i]['form'] . '|';
        }
    }

    $arr['chooseForm'] = substr($arr['chooseForm'], 0, -1);

    return $arr;
}

function get_formType_arr($formType, $rc_sort, $type = 0) {

    $arr = array();
    for ($i = 0; $i < count($formType); $i++) {
        if (!empty($formType[$i])) {
            if ($type == 0) {
                $arr[$i] = trim($formType[$i]) . '*' . trim($rc_sort[$i]);
            } else {
                $arr[$i] = trim($formType[$i]);
            }
        }
    }

    return $arr;
}

function get_merchants_steps_fields_centent_insert_update($textFields, $fieldsDateType, $fieldsLength, $fieldsNotnull, $fieldsFormName, $fieldsCoding, $fields_sort, $will_choose, $chooseForm, $tid) {

    $parent = array(
        'tid' => $tid,
        'textFields' => $textFields,
        'fieldsDateType' => $fieldsDateType,
        'fieldsLength' => $fieldsLength,
        'fieldsNotnull' => $fieldsNotnull,
        'fieldsFormName' => $fieldsFormName,
        'fieldsCoding' => $fieldsCoding,
        'fields_sort' => $fields_sort,
        'will_choose' => $will_choose,
        'fieldsForm' => $chooseForm
    );

    $sql = "select id from " . $GLOBALS['ecs']->table('merchants_steps_fields_centent') . " where tid = '$tid'";
    $res = $GLOBALS['db']->getOne($sql);

    if ($res > 0) {
        $handler_type = 'update';
    } else {
        $handler_type = 'insert';
    }

    if ($handler_type == 'update') {
        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_steps_fields_centent'), $parent, 'UPDATE', "tid = '$tid'");
    } else {
        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_steps_fields_centent'), $parent, 'INSERT');
    }

    return true;
}

//添加或更新流程信息
function get_merchants_steps_title_insert_update($fields_steps, $fields_titles, $titles_annotation, $steps_style, $fields_special, $special_type, $handler_type = 'insert', $tid = 0) {

    if ($handler_type == 'update') {
        $typeTid = ' and tid <> ' . $tid;
    }

    $sql = "select tid from " . $GLOBALS['ecs']->table('merchants_steps_title') . " where fields_titles = '$fields_titles'" . $typeTid;
    $res = $GLOBALS['db']->getOne($sql);

    if ($res > 0) {
        return false;
    } else {
        $parent = array(
            'fields_steps' => $fields_steps,
            'fields_titles' => $fields_titles,
            'titles_annotation' => $titles_annotation,
            'steps_style' => $steps_style,
            'fields_special' => $fields_special,
            'special_type' => $special_type
        );

        if ($handler_type == 'update') {
            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_steps_title'), $parent, 'UPDATE', "tid = '$tid'");

            return true;
        } else {
            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_steps_title'), $parent, 'INSERT');
            $tid = $GLOBALS['db']->insert_id();

            $res['tid'] = $tid;
            $res['true'] = true;

            return $res;
        }
    }
}

//字段循环生成数组
function get_fields_centent_info($id, $textFields, $fieldsDateType, $fieldsLength, $fieldsNotnull, $fieldsFormName, $fieldsCoding, $fieldsForm, $fields_sort, $will_choose, $webType = 'admin', $user_id = 0) {

    if (!empty($textFields)) {
        $textFields = explode(',', $textFields);
        $fieldsDateType = explode(',', $fieldsDateType);
        $fieldsLength = explode(',', $fieldsLength);
        $fieldsNotnull = explode(',', $fieldsNotnull);
        $fieldsFormName = explode(',', $fieldsFormName);
        $fieldsCoding = explode(',', $fieldsCoding);
        $choose = explode('|', $fieldsForm);
        $fields_sort = explode(',', $fields_sort);
        $will_choose = explode(',', $will_choose);

        $arr = array();
        for ($i = 0; $i < count($textFields); $i++) {
            $arr[$i + 1]['id'] = $id;
            $arr[$i + 1]['textFields'] = $textFields[$i];
            $arr[$i + 1]['fieldsDateType'] = $fieldsDateType[$i];
            $arr[$i + 1]['fieldsLength'] = $fieldsLength[$i];
            $arr[$i + 1]['fieldsNotnull'] = $fieldsNotnull[$i];
            $arr[$i + 1]['fieldsFormName'] = $fieldsFormName[$i];
            $arr[$i + 1]['fieldsCoding'] = $fieldsCoding[$i];
            $arr[$i + 1]['fields_sort'] = $fields_sort[$i];
            $arr[$i + 1]['will_choose'] = $will_choose[$i];


            if ($user_id > 0) {
                $sql = "select " . $textFields[$i] . " from " . $GLOBALS['ecs']->table('merchants_steps_fields') . " where user_id = '$user_id'";
                $arr[$i + 1]['titles_centents'] = $GLOBALS['db']->getOne($sql);
                if ($textFields[$i] == 'business_term') {
                    $sql = "select shopTime_term from " . $GLOBALS['ecs']->table('merchants_steps_fields') . " where user_id = '$user_id'";
                    $arr[$i + 1]['shopTime_term'] = $GLOBALS['db']->getOne($sql);
                }
            }

            $chooseForm = explode(':', $choose[$i]);
            $arr[$i + 1]['chooseForm'] = $chooseForm[0];
            $form_special = explode('+', $chooseForm[1]);
            $arr[$i + 1]['formSpecial'] = $form_special[1]; //表单注释


            if ($chooseForm[0] == 'input') {
                $arr[$i + 1]['inputForm'] = $form_special[0];
            } elseif ($chooseForm[0] == 'textarea') {
                $textareaForm = explode(',', $form_special[0]);
                $arr[$i + 1]['rows'] = $textareaForm[0];
                $arr[$i + 1]['cols'] = $textareaForm[1];
            } elseif ($chooseForm[0] == 'radio' || $chooseForm[0] == 'checkbox') {
                if (!empty($form_special[0])) {
                    $radioCheckbox_sort = get_radioCheckbox_sort(explode(',', $form_special[0]));

                    if ($webType == 'root') {
                        $radioCheckbox_sort = get_array_sort($radioCheckbox_sort, 'rc_sort');
                    }

                    $arr[$i + 1]['radioCheckboxForm'] = $radioCheckbox_sort;
                } else {
                    $arr[$i + 1]['radioCheckboxForm'] = array();
                }
            } elseif ($chooseForm[0] == 'select') {
                if (!empty($form_special[0])) {
                    $arr[$i + 1]['selectList'] = explode(',', $form_special[0]);
                } else {
                    $arr[$i + 1]['selectList'] = array();
                }
            } elseif ($chooseForm[0] == 'other') {
                $otherForm = explode(',', $form_special[0]);
                $arr[$i + 1]['otherForm'] = $otherForm[0];
                if ($otherForm[0] == 'dateTime') { //日期
                    if ($webType == 'root') {
                        $arr[$i + 1]['dateTimeForm'] = get_dateTimeForm_arr(explode('--', $otherForm[1]), explode(',', $arr[$i + 1]['titles_centents']));
                    } else {
                        $arr[$i + 1]['dateTimeForm'] = $otherForm[1];
                    }
                } elseif ($otherForm[0] == 'textArea') { //地区
                    if ($webType == 'root') {
                        $arr[$i + 1]['textAreaForm'] = get_textAreaForm_arr(explode(',', $arr[$i + 1]['titles_centents']));

                        $arr[$i + 1]['province_list'] = get_regions_steps(1, $arr[$i + 1]['textAreaForm']['country']);
                        $arr[$i + 1]['city_list'] = get_regions_steps(2, $arr[$i + 1]['textAreaForm']['province']);
                        $arr[$i + 1]['district_list'] = get_regions_steps(3, $arr[$i + 1]['textAreaForm']['city']);
                    }
                }
            }
        }

        return $arr;
    } else {
        return array();
    }
}

//单选或多选表单数据
function get_radioCheckbox_sort($radioCheckbox_sort) {
    $arr = array();
    for ($i = 0; $i < count($radioCheckbox_sort); $i++) {
        $rc_sort = explode('*', $radioCheckbox_sort[$i]);
        $arr[$i]['radioCheckbox'] = $rc_sort[0];
        $arr[$i]['rc_sort'] = $rc_sort[1];
    }

    return $arr;
}

//日期表单数据
function get_dateTimeForm_arr($dateTime, $date_centent) {
    $arr = array();
    for ($i = 0; $i < $dateTime[0]; $i++) {
        $arr[$i]['dateSize'] = $dateTime[1];
        $arr[$i]['dateCentent'] = $date_centent[$i];
    }

    return $arr;
}

//地区表单数据
function get_textAreaForm_arr($textArea) {

    $arr['country'] = $textArea[0];
    $arr['province'] = $textArea[1];
    $arr['city'] = $textArea[2];
    $arr['district'] = $textArea[3];

    return $arr;
}

//查找字段数据 start
function get_fields_date_title_remove($tid, $objName, $type = 0) {
    $sql = "select * from " . $GLOBALS['ecs']->table('merchants_steps_fields_centent') . " where tid = '$tid'";
    $row = $GLOBALS['db']->getRow($sql);

    $textFields = explode(',', $row['textFields']);
    $fieldsDateType = explode(',', $row['fieldsDateType']);
    $fieldsLength = explode(',', $row['fieldsLength']);
    $fieldsNotnull = explode(',', $row['fieldsNotnull']);
    $fieldsFormName = explode(',', $row['fieldsFormName']);
    $fieldsCoding = explode(',', $row['fieldsCoding']);
    $fieldsForm = explode('|', $row['fieldsForm']);

    $arr = array();
    for ($i = 0; $i < count($textFields); $i++) {
        if ($type == 1) {
            if ($textFields[$i] != $objName) {
                $arr[$i]['textFields'] = $textFields[$i];
                $arr[$i]['fieldsDateType'] = $fieldsDateType[$i];
                $arr[$i]['fieldsLength'] = $fieldsLength[$i];
                $arr[$i]['fieldsNotnull'] = $fieldsNotnull[$i];
                $arr[$i]['fieldsFormName'] = $fieldsFormName[$i];
                $arr[$i]['fieldsCoding'] = $fieldsCoding[$i];
                $arr[$i]['fieldsForm'] = $fieldsForm[$i];
            }
        } else {
            $arr[$i]['textFields'] = $textFields[$i];
        }
    }
    return $arr;
}

function get_title_remove($tid, $fields, $objName) { //$objName 删除字段
    $fields = array_values($fields);
    for ($i = 0; $i < count($fields); $i++) {
        $arr[$i] = $fields[$i];
        $arr['textFields'] .= $fields[$i]['textFields'] . ',';
        $arr['fieldsDateType'] .= $fields[$i]['fieldsDateType'] . ',';
        $arr['fieldsLength'] .= $fields[$i]['fieldsLength'] . ',';
        $arr['fieldsNotnull'] .= $fields[$i]['fieldsNotnull'] . ',';
        $arr['fieldsFormName'] .= $fields[$i]['fieldsFormName'] . ',';
        $arr['fieldsCoding'] .= $fields[$i]['fieldsCoding'] . ',';
        $arr['fieldsForm'] .= $fields[$i]['fieldsForm'] . '|';
    }

    $arr['textFields'] = substr($arr['textFields'], 0, -1);
    $arr['fieldsDateType'] = substr($arr['fieldsDateType'], 0, -1);
    $arr['fieldsLength'] = substr($arr['fieldsLength'], 0, -1);
    $arr['fieldsNotnull'] = substr($arr['fieldsNotnull'], 0, -1);
    $arr['fieldsFormName'] = substr($arr['fieldsFormName'], 0, -1);
    $arr['fieldsCoding'] = substr($arr['fieldsCoding'], 0, -1);
    $arr['fieldsForm'] = substr($arr['fieldsForm'], 0, -1);

    $parent = array(
        'textFields' => $arr['textFields'],
        'fieldsDateType' => $arr['fieldsDateType'],
        'fieldsLength' => $arr['fieldsLength'],
        'fieldsNotnull' => $arr['fieldsNotnull'],
        'fieldsFormName' => $arr['fieldsFormName'],
        'fieldsCoding' => $arr['fieldsCoding'],
        'fieldsForm' => $arr['fieldsForm'],
    );

    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_steps_fields_centent'), $parent, 'UPDATE', "tid = '$tid'");
    get_Add_Drop_fields($objName, '', 'merchants_steps_fields', 'delete');

    return $arr;
}

//查找字段数据 end
//添加类目证件标题
function get_documentTitle_insert_update($dt_list, $cat_id, $dt_id = array()) {

    if(!empty($dt_list)){
        //删除
        $sql = "delete from " . $GLOBALS['ecs']->table('merchants_documenttitle') . " where cat_id = '" . $cat_id . "' AND  dt_id NOT ".  db_create_in($dt_id)."";
        $GLOBALS['db']->query($sql); //删除二级类目表数据
         for ($i = 0; $i < count($dt_list); $i++) {

            $dt_list[$i] = trim($dt_list[$i]);

            $sql = "select cat_id from " . $GLOBALS['ecs']->table('merchants_documenttitle') . " where dt_id = '" . $dt_id[$i] . "'";
            $catId = $GLOBALS['db']->getOne($sql);

            if (!empty($dt_list[$i])) {
                $parent = array(
                    'cat_id' => $cat_id,
                    'dt_title' => $dt_list[$i]
                );

                if ($catId > 0) {
                    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_documenttitle'), $parent, 'UPDATE', "dt_id = '" . $dt_id[$i] . "'");
                } else {
                    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_documenttitle'), $parent, 'INSERT');
                }
            } else {
                if ($catId > 0) {
                    $sql = "delete from " . $GLOBALS['ecs']->table('merchants_documenttitle') . " where dt_id = '" . $dt_id[$i] . "'";
                    $GLOBALS['db']->query($sql); //删除二级类目表数据
                }
            }
        }
    }

}

/* * ****************入驻流程函数 end*********************** */

/**
 * 后台管理员ID
 */
function get_admin_id() {
    $self = explode("/", substr(PHP_SELF, 1));
    $count = count($self);

    $admin_id = 0;
    if ($count > 1) {
        $real_path = $self[$count - 2];
        if ($real_path == ADMIN_PATH) {
            $admin_id = $_SESSION['admin_id'];
        } elseif ($real_path == SELLER_PATH) {
            $admin_id = $_SESSION['seller_id'];
        }
    }
    return $admin_id;
}

//获取入驻商家的前台会员ID
function get_admin_ru_id() {

    $admin_id = get_admin_id();

    $sql = "SELECT ru_id FROM " . $GLOBALS['ecs']->table('admin_user') . " WHERE user_id = '$admin_id' LIMIT 1";
    return $GLOBALS['db']->getRow($sql);
}

function is_admin_seller_path(){
    $self = explode("/", substr(PHP_SELF, 1));
    $count = count($self);

    $return = 3;
    $admin_id = 0;
    if ($count > 1) {
        $real_path = $self[$count - 2];
        if ($real_path == ADMIN_PATH) {
            $return = 1;
        } elseif ($real_path == SELLER_PATH) {
            $return = 2;
        } elseif($real_path == STORES_PATH){
            $return = 0;
        }
    }

    return $return;
}

//获取入驻商家的可用分类权限 start
function get_user_category($options, $shopMain_category, $ru_id = 0, $admin_type = 0) {
    if ($ru_id > 0) {
        $shopMain_category = get_category_child_tree($shopMain_category);
        $arr = array();
        if (!empty($shopMain_category)) {
            $category = explode(',', $shopMain_category);
            foreach ($options as $key => $row) {
                if ($row['level'] < 3) {
                    for ($i = 0; $i < count($category); $i++) {
                        if ($key == $category[$i]) {
                            $arr[$key] = $row;
                        }
                    }
                } else {
                    $sql = "SELECT cat_id FROM " . $GLOBALS['ecs']->table('merchants_category') . " WHERE cat_id = '" . $row['cat_id'] . "' AND user_id = '$ru_id'";
                    $uc_id = $GLOBALS['db']->getOne($sql);

                    if ($admin_type == 0) {
                        if ($uc_id > 0) {
                            $arr[$key] = $row;
                        }
                    }
                }
            }
        }

        return $arr;
    } else {
        return $options;
    }
}

function get_category_child_tree($shopMain_category, $ru_id = 0, $type = 0) {

    $category = explode('-', $shopMain_category);

    for ($i = 0; $i < count($category); $i++) {
        $category[$i] = explode(':', $category[$i]);

        if ($category[$i][0]) {

            $cat_info = get_store_cat_info($category[$i][0]);
            $category[$i]['id'] = $category[$i][0];
            $category[$i]['name'] = $cat_info['cat_name'];
            $category[$i]['nolinkname'] = $cat_info['cat_name'];
            $category[$i]['cat_id'] = $category[$i][0];
            $category[$i]['cat_alias_name'] = $cat_info['cat_alias_name'];

            $twoChild = explode(',', $category[$i][1]);
            for ($j = 0; $j < count($twoChild); $j++) {

                if ($type == 0) {
                    $sql = " select cat_id, cat_name from " . $GLOBALS['ecs']->table('category') . " where parent_id = '" . $twoChild[$j] . "'";
                    $threeChild = $GLOBALS['db']->getAll($sql);

                    $category[$i]['three_' . $twoChild[$j]] = get_category_three_child($threeChild);
                    $category[$i]['three'] .= $category[$i][0] . ',' . $category[$i][1] . ',' . $category[$i]['three_' . $twoChild[$j]]['threeChild'] . ',';
                } elseif ($type == 1) {
                    if ($category[$i][1]) {
                        $category[$i][1] = get_del_str_comma($category[$i][1]);
                        $sql = " SELECT cat_id, cat_name FROM " . $GLOBALS['ecs']->table('category') . " WHERE cat_id IN(" . $category[$i][1] . ")";
                        $child_tree = $GLOBALS['db']->getAll($sql);

                        foreach ($child_tree as $key => $row) {
                            $category[$i]['child_tree'][$key]['id'] = $row['cat_id'];
                            $category[$i]['child_tree'][$key]['name'] = $row['cat_name'];

                            $build_uri = array(
                                'cid' => $row['cat_id'],
                                'urid' => $ru_id,
                                'append' => $row['cat_name']
                            );

                            $domain_url = get_seller_domain_url($ru_id, $build_uri);

                            if ($ru_id) {
                                $category[$i]['child_tree'][$key]['url'] = $domain_url['domain_name'];
                            } else {
                                $category[$i]['child_tree'][$key]['url'] = build_uri('category', array('cid' => $row['cat_id']), $row['cat_name']);
                            }

                            $category[$i]['child_tree'][$key]['cat_id'] = get_child_tree($row['cat_id'], $ru_id);
                        }
                    }
                }
            }

            if ($type == 0) {
                $category[$i]['three'] = substr($category[$i]['three'], 0, -1);
            }
        }
    }

    if ($type == 0) {
        $category = get_link_cat_id($category);
        $category = $category['all_cat'];
    }

    return $category;
}

function get_category_three_child($threeChild) {

    for ($i = 0; $i < count($threeChild); $i++) {
        if (!empty($threeChild[$i]['cat_id'])) {
            $threeChild['threeChild'] .= $threeChild[$i]['cat_id'] . ",";
        }
    }

    $threeChild['threeChild'] = substr($threeChild['threeChild'], 0, -1);

    return $threeChild;
}

function get_link_cat_id($category) {

    for ($i = 0; $i < count($category); $i++) {
        if (!empty($category[$i]['three'])) {
            $category['all_cat'] .= $category[$i]['three'] . ',';
        }
    }

    $category['all_cat'] = substr($category['all_cat'], 0, -1);

    return $category;
}

//获取入驻商家的可用分类权限 end

//前端程序代码-------------------------------------

//协议信息
function get_root_directory_steps($sid) {
    $sql = "select process_title, process_article, fields_next  from " . $GLOBALS['ecs']->table('merchants_steps_process') . " where process_steps = '$sid'";
    $row = $GLOBALS['db']->getRow($sql);

    if ($row['process_article'] > 0) {
        $row['article_centent'] = $GLOBALS['db']->getOne("select content from " . $GLOBALS['ecs']->table('article') . " where article_id = '" . $row['process_article'] . "'");
    }

    return $row;
}

//申请步骤列表
function get_root_steps_process_list($sid) {
    $sql = "select id, process_title, fields_next from " . $GLOBALS['ecs']->table('merchants_steps_process') . " where process_steps = '$sid' AND is_show = 1 order by steps_sort ASC";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $key => $row) {
        $arr[$key]['id'] = $row['id'];
        $arr[$key]['process_title'] = $row['process_title'];
        $arr[$key]['fields_next'] = $row['fields_next'];
    }

    return $arr;
}

function get_merchants_septs_custom_info($table = '', $type = '', $id = '') {

    if ($type == 'pingpai') {
        $id = " and bid = '$id'";
    }

    $sql = "select * from " . $GLOBALS['ecs']->table($table) . " where user_id = '" . $_SESSION['user_id'] . "'" . $id;

    return $GLOBALS['db']->getRow($sql);
}

//流程信息列表
function get_root_merchants_steps_title($pid, $user_id) {

    include_once(ROOT_PATH . '/includes/cls_image.php');
    $image = new cls_image($_CFG['bgcolor']);

    //自定义表单数据插入 start
    $brandId = isset($_REQUEST['brandId']) ? intval($_REQUEST['brandId']) : 0;
    $search_brandType = isset($_REQUEST['search_brandType']) ? htmlspecialchars($_REQUEST['search_brandType']) : '';
    $searchBrandZhInput = isset($_REQUEST['searchBrandZhInput']) ? htmlspecialchars(trim($_REQUEST['searchBrandZhInput'])) : '';
    $searchBrandZhInput = !empty($searchBrandZhInput) ? addslashes($searchBrandZhInput) : '';
    $searchBrandEnInput = isset($_REQUEST['searchBrandEnInput']) ? htmlspecialchars(trim($_REQUEST['searchBrandEnInput'])) : '';
    $searchBrandEnInput = !empty($searchBrandEnInput) ? addslashes($searchBrandEnInput) : '';

    $ec_shop_bid = isset($_REQUEST['ec_shop_bid']) ? intval($_REQUEST['ec_shop_bid']) : 0;
    $ec_shoprz_type = isset($_POST['ec_shoprz_type']) ? intval($_POST['ec_shoprz_type']) : 0;
    $ec_subShoprz_type = isset($_POST['ec_subShoprz_type']) ? intval($_POST['ec_subShoprz_type']) : 0;
    $ec_shop_expireDateStart = isset($_POST['ec_shop_expireDateStart']) ? htmlspecialchars(trim($_POST['ec_shop_expireDateStart'])) : '';
    $ec_shop_expireDateEnd = isset($_POST['ec_shop_expireDateEnd']) ? htmlspecialchars(trim($_POST['ec_shop_expireDateEnd'])) : '';
    $ec_shop_permanent = isset($_POST['ec_shop_permanent']) ? intval($_POST['ec_shop_permanent']) : 0;
    $ec_shop_categoryMain = isset($_POST['ec_shop_categoryMain']) ? intval($_POST['ec_shop_categoryMain']) : 0;

    //品牌基本信息
    $bank_name_letter = isset($_POST['ec_bank_name_letter']) ? htmlspecialchars(trim($_POST['ec_bank_name_letter'])) : $searchBrandEnInput;
    $brandName = isset($_POST['ec_brandName']) ? htmlspecialchars(trim($_POST['ec_brandName'])) : $searchBrandZhInput;
    $brandFirstChar = isset($_POST['ec_brandFirstChar']) ? htmlspecialchars(trim($_POST['ec_brandFirstChar'])) : substr($searchBrandEnInput, 0, 1);
    $brandLogo = isset($_FILES['ec_brandLogo']) ? $_FILES['ec_brandLogo'] : '';
    $brandLogo = $image->upload_image($brandLogo, 'septs_Image');  //图片存放地址 -- data/septs_Image
    $text_brandLogo = isset($_POST['text_brandLogo']) ? trim($_POST['text_brandLogo']) : '';
    $brandType = isset($_POST['ec_brandType']) ? intval($_POST['ec_brandType']) : 0;
    $brand_operateType = isset($_POST['ec_brand_operateType']) ? intval($_POST['ec_brand_operateType']) : 0;
    $brandEndTime = isset($_POST['ec_brandEndTime']) ? trim($_POST['ec_brandEndTime']) : '';
    $brandEndTime_permanent = isset($_POST['ec_brandEndTime_permanent']) ? intval($_POST['ec_brandEndTime_permanent']) : 0;

    //品牌资质证件
    $qualificationNameInput = isset($_POST['ec_qualificationNameInput']) ? $_POST['ec_qualificationNameInput'] : array();
    $qualificationImg = isset($_FILES['ec_qualificationImg']) ? $_FILES['ec_qualificationImg'] : array();
    $expiredDateInput = isset($_POST['ec_expiredDateInput']) ? $_POST['ec_expiredDateInput'] : array();
    $b_fid = isset($_POST['b_fid']) ? $_POST['b_fid'] : array();

    //店铺命名信息
    $ec_shoprz_brandName = isset($_POST['ec_shoprz_brandName']) ? htmlspecialchars(trim($_POST['ec_shoprz_brandName'])) : '';
    $ec_shop_class_keyWords = isset($_POST['ec_shop_class_keyWords']) ? htmlspecialchars(trim($_POST['ec_shop_class_keyWords'])) : '';
    $ec_shopNameSuffix = isset($_POST['ec_shopNameSuffix']) ? htmlspecialchars(trim($_POST['ec_shopNameSuffix'])) : '';
    $ec_rz_shopName = isset($_POST['ec_rz_shopName']) ? htmlspecialchars(trim($_POST['ec_rz_shopName'])) : '';
    $ec_hopeLoginName = isset($_POST['ec_hopeLoginName']) ? htmlspecialchars(trim($_POST['ec_hopeLoginName'])) : '';

    $shop_info = get_merchants_septs_custom_info('merchants_shop_information'); //店铺类型、 可经营类目---信息表

    if ($ec_shop_bid > 0) { //入驻品牌
        $brand_info = get_merchants_septs_custom_info('merchants_shop_brand', 'pingpai', $ec_shop_bid); //品牌表
    } else {
        if ($brandId > 0) {
            if ($search_brandType == 'm_bran') {
                $search_brandType = 'merchants_brands';
            } else {
                $search_brandType = '';
            }

            $brand_info = get_brand_info($brandId, $search_brandType);

            $bank_name_letter = $brand_info['brand_letter'];
            $brandName = $brand_info['brand_name'];
            $brandFirstChar = substr($brand_info['brand_letter'], 0, 1);

            if ($search_brandType != 'merchants_brands') {
                $brandLogo = DATA_DIR . '/brandlogo/' . $brand_info['brand_logo'];
            } else {
                $brandLogo = $brand_info['brand_logo'];
                $brand_m = get_brand_info($brand_info['brand_name'], $search_brandType, 1);
            }
        }
    }

    $sql = "select tid, fields_titles, titles_annotation, steps_style, fields_special, special_type from " . $GLOBALS['ecs']->table('merchants_steps_title') . " where fields_steps='$pid'";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $key => $row) {

        $sql = "select shop_id from " . $GLOBALS['ecs']->table('merchants_shop_information') . " where user_id = '" . $_SESSION['user_id'] . "'";
        $shop_id = $GLOBALS['db']->getOne($sql);

        $arr[$key]['tid'] = $row['tid'];
        $arr[$key]['fields_titles'] = $row['fields_titles'];
        $arr[$key]['titles_annotation'] = $row['titles_annotation'];
        $arr[$key]['steps_style'] = $row['steps_style'];
        $arr[$key]['fields_special'] = $row['fields_special'];
        $arr[$key]['special_type'] = $row['special_type'];

        $sql = "select * from " . $GLOBALS['ecs']->table('merchants_steps_fields_centent') . " where tid = '" . $row['tid'] . "'";
        $centent = $GLOBALS['db']->getRow($sql);
        $cententFields = get_fields_centent_info($centent['id'], $centent['textFields'], $centent['fieldsDateType'], $centent['fieldsLength'], $centent['fieldsNotnull'], $centent['fieldsFormName'], $centent['fieldsCoding'], $centent['fieldsForm'], $centent['fields_sort'], $centent['will_choose'], 'root', $user_id);
        $arr[$key]['cententFields'] = get_array_sort($cententFields, 'fields_sort');

        if ($row['steps_style'] == 1) {

            $ec_authorizeFile = $image->upload_image($_FILES['ec_authorizeFile'], 'septs_Image');  //图片存放地址 -- data/septs_Image
            $ec_authorizeFile = empty($ec_authorizeFile) ? $shop_info['authorizeFile'] : $ec_authorizeFile;
            $ec_shop_hypermarketFile = $image->upload_image($_FILES['ec_shop_hypermarketFile'], 'septs_Image');  //图片存放地址 -- data/septs_Image
            $ec_shop_hypermarketFile = empty($ec_shop_hypermarketFile) ? $shop_info['shop_hypermarketFile'] : $ec_shop_hypermarketFile;

            if ($ec_shop_permanent != 1) {
                $ec_shop_expireDateStart = empty($ec_shop_expireDateStart) ? local_date("Y-m-d H:i", $shop_info['shop_expireDateStart']) : $ec_shop_expireDateStart;
                $ec_shop_expireDateEnd = empty($ec_shop_expireDateEnd) ? local_date("Y-m-d H:i", $shop_info['shop_expireDateEnd']) : $ec_shop_expireDateEnd;

                if (!empty($ec_shop_expireDateStart) || !empty($ec_shop_expireDateEnd)) {
                    $ec_shop_expireDateStart = local_strtotime($ec_shop_expireDateStart);
                    $ec_shop_expireDateEnd = local_strtotime($ec_shop_expireDateEnd);
                }
            } else {
                $ec_shop_expireDateStart = '';
                $ec_shop_expireDateEnd = '';
            }

            //判断数据是否存在，如果存在则引用 start
            if ($ec_shoprz_type == 0) {
                $ec_shoprz_type = $shop_info['shoprz_type'];
            }
            if ($ec_subShoprz_type == 0) {
                $ec_subShoprz_type = $shop_info['subShoprz_type'];
            }
            if ($ec_shop_categoryMain == 0) {
                $ec_shop_categoryMain = $shop_info['shop_categoryMain'];
            }
            //判断数据是否存在，如果存在则引用 end

            $parent = array(//店铺类型数据插入
                'user_id' => $_SESSION['user_id'],
                'shoprz_type' => $ec_shoprz_type,
                'subShoprz_type' => $ec_subShoprz_type,
                'shop_expireDateStart' => $ec_shop_expireDateStart,
                'shop_expireDateEnd' => $ec_shop_expireDateEnd,
                'shop_permanent' => $ec_shop_permanent,
                'authorizeFile' => $ec_authorizeFile,
                'shop_hypermarketFile' => $ec_shop_hypermarketFile,
                'shop_categoryMain' => $ec_shop_categoryMain
            );

            if ($_SESSION['user_id'] > 0) {
                if ($shop_id > 0) {

                    if ($parent['shop_expireDateStart'] == '' || $parent['shop_expireDateEnd'] == '') {
                        if ($ec_shop_permanent != 1) {
                            if ($shop_info['shop_permanent'] == 1) {
                                $parent['shop_permanent'] = $shop_info['shop_permanent'];
                            }
                        }
                    }

                    if (empty($parent['authorizeFile'])) {
                        $parent['shop_permanent'] = 0;
                    } else {
                        if ($parent['shop_expireDateStart'] == '' || $parent['shop_expireDateEnd'] == '') {
                            $parent['shop_permanent'] = 1;
                            $parent['shop_expireDateStart'] = '';
                            $parent['shop_expireDateEnd'] = '';
                        }
                    }

                    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_shop_information'), $parent, 'UPDATE', "user_id = '" . $_SESSION['user_id'] . "'");
                } else {
                    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_shop_information'), $parent, 'INSERT');
                }
            }

            if ($ec_shop_permanent == 0) {
                if ($parent['shop_expireDateStart'] != '') {
                    $parent['shop_expireDateStart'] = local_date("Y-m-d H:i", $shop_info['shop_expireDateStart']);
                }
                if ($parent['shop_expireDateEnd'] != '') {
                    $parent['shop_expireDateEnd'] = local_date("Y-m-d H:i", $shop_info['shop_expireDateEnd']);
                }
            }
        } elseif ($row['steps_style'] == 2) { //一级类目列表
            //2014-11-19 start
            if ($_SESSION['user_id'] > 0) {
                if ($shop_id < 1) {
                    $parent['user_id'] = $_SESSION['user_id'];
                    $parent['shop_categoryMain'] = $ec_shop_categoryMain;
                    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_shop_information'), $parent, 'INSERT');
                }
            }
            //2014-11-19 end

            $arr[$key]['first_cate'] = get_first_cate_list(0, 0, array(), $_SESSION['user_id']);
            $catId_array = get_catId_array();

            $parent['user_shopMain_category'] = implode('-', $catId_array);

            //2014-11-19 start
            if ($ec_shop_categoryMain == 0) {
                $ec_shop_categoryMain = $shop_info['shop_categoryMain'];
                $parent['shop_categoryMain'] = $ec_shop_categoryMain;
            }
            $parent['shop_categoryMain'] = $ec_shop_categoryMain;
            //2014-11-19 end

            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_shop_information'), $parent, 'UPDATE', "user_id = '" . $_SESSION['user_id'] . "'");

            if (!empty($parent['user_shopMain_category'])) {
                get_update_temporarydate_isAdd($catId_array, $_SESSION['user_id']);
            }
            get_update_temporarydate_isAdd($catId_array, $_SESSION['user_id'], 1);
        } elseif ($row['steps_style'] == 3) { //品牌列表
            $arr[$key]['brand_list'] = get_septs_shop_brand_list($_SESSION['user_id']); //品牌列表

            if ($ec_shop_bid > 0 || $brand_m['brand_id'] > 0) { //更新品牌数据
                $bank_name_letter = empty($bank_name_letter) ? $brand_info['bank_name_letter'] : $bank_name_letter;
                $brandName = empty($brandName) ? $brand_info['brandName'] : $brandName;
                $brandFirstChar = empty($brandFirstChar) ? $brand_info['brandFirstChar'] : $brandFirstChar;
                $brandLogo = empty($brandLogo) ? $brand_info['brandLogo'] : $brandLogo;
                $brandType = empty($brandType) ? $brand_info['brandType'] : $brandType;
                $brand_operateType = empty($brand_operateType) ? $brand_info['brand_operateType'] : $brand_operateType;
                $brandEndTime = empty($brandEndTime) ? $brand_info['brandEndTime'] : local_strtotime($brandEndTime);
                $brandEndTime_permanent = empty($brandEndTime_permanent) ? $brand_info['brandEndTime_permanent'] : $brandEndTime_permanent;

                $brandfile_list = get_shop_brandfile_list($ec_shop_bid);
                $arr[$key]['brandfile_list'] = $brandfile_list;

                get_oss_add_file(array($brandLogo));

                $parent = array(
                    'user_id' => $_SESSION['user_id'],
                    'bank_name_letter' => $bank_name_letter,
                    'brandName' => $brandName,
                    'brandFirstChar' => $brandFirstChar,
                    'brandLogo' => $brandLogo,
                    'brandType' => $brandType,
                    'brand_operateType' => $brand_operateType,
                    'brandEndTime' => $brandEndTime,
                    'brandEndTime_permanent' => $brandEndTime_permanent
                );

                if (!empty($parent['brandEndTime'])) {
                    $arr[$key]['parentType']['brandEndTime'] = local_date("Y-m-d H:i", $parent['brandEndTime']); //输出
                }

                if ($_SESSION['user_id'] > 0) {

                    if ($parent['brandEndTime_permanent'] == 1) {
                        $parent['brandEndTime'] = '';
                    }

                    if ($_SESSION['user_id'] == $brand_info['user_id']) {
                        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_shop_brand'), $parent, 'UPDATE', "user_id = '" . $_SESSION['user_id'] . "' and bid = '$ec_shop_bid'");
                        get_shop_brand_file($qualificationNameInput, $qualificationImg, $expiredDateInput, $b_fid, $ec_shop_bid); //品牌资质文件上传
                    }
                }
            } else { //插入品牌数据
                if ($_SESSION['user_id'] > 0) {
                    if ($brandLogo == '') {
                        $brandLogo = $text_brandLogo;
                    }
                    $parent = array(
                        'user_id' => $_SESSION['user_id'],
                        'bank_name_letter' => $bank_name_letter,
                        'brandName' => $brandName,
                        'brandFirstChar' => $brandFirstChar,
                        'brandLogo' => $brandLogo,
                        'brandType' => $brandType,
                        'brand_operateType' => $brand_operateType,
                        'brandEndTime' => $brandEndTime,
                        'brandEndTime_permanent' => $brandEndTime_permanent,
                        'add_time' => gmtime()
                    );

                    if (!empty($brandName)) {
                        $sql = "select bid from " . $GLOBALS['ecs']->table('merchants_shop_brand') . " where brandName = '$brandName' and user_id = '" . $_SESSION['user_id'] . "'";
                        $bRes = $GLOBALS['db']->getOne($sql);

                        if ($bRes > 0) {
                            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_shop_brand'), $parent, 'UPDATE', "user_id = '" . $_SESSION['user_id'] . "' and bid = '$bRes'");
                            get_shop_brand_file($qualificationNameInput, $qualificationImg, $expiredDateInput, $b_fid, $bRes); //品牌资质文件上传

                            $back_pid_key = $row['steps_style'] - 1;
                            $back_url = "merchants_steps.php?step=stepThree&pid_key=" . $back_pid_key;
                            ecs_header("Location: " . $back_url . "\n");
                            exit;
                        } else {

                            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_shop_brand'), $parent, 'INSERT');
                            $bid = $GLOBALS['db']->insert_id();

                            get_shop_brand_file($qualificationNameInput, $qualificationImg, $expiredDateInput, $b_fid, $bid); //品牌资质文件上传
                        }
                    }
                }
            }
        } elseif ($row['steps_style'] == 4) {

            $sql = "select bid, brandName from " . $GLOBALS['ecs']->table('merchants_shop_brand') . " where user_id = '" . $_SESSION['user_id'] . "'";
            $brand_list = $GLOBALS['db']->getAll($sql);
            $arr[$key]['brand_list'] = $brand_list;

            $ec_shoprz_brandName = empty($ec_shoprz_brandName) ? $shop_info['shoprz_brandName'] : $ec_shoprz_brandName;
            $ec_shop_class_keyWords = empty($ec_shop_class_keyWords) ? $shop_info['shop_class_keyWords'] : $ec_shop_class_keyWords;
            $ec_shopNameSuffix = empty($ec_shopNameSuffix) ? $shop_info['shopNameSuffix'] : $ec_shopNameSuffix;
            $ec_rz_shopName = empty($ec_rz_shopName) ? $shop_info['rz_shopName'] : $ec_rz_shopName;
            $ec_hopeLoginName = empty($ec_hopeLoginName) ? $shop_info['hopeLoginName'] : $ec_hopeLoginName;

            if (!empty($ec_rz_shopName)) {
                $parent = array(
                    'user_id' => $_SESSION['user_id'],
                    'shoprz_brandName' => $ec_shoprz_brandName,
                    'shop_class_keyWords' => $ec_shop_class_keyWords,
                    'shopNameSuffix' => $ec_shopNameSuffix,
                    'rz_shopName' => $ec_rz_shopName,
                    'hopeLoginName' => $ec_hopeLoginName,
                );

                if ($_SESSION['user_id'] > 0) {
                    if ($shop_id > 0) {
                        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_shop_information'), $parent, 'UPDATE', "user_id = '" . $_SESSION['user_id'] . "'");
                    } else {
                        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_shop_information'), $parent, 'INSERT');
                    }
                }
            }

            $parent['shoprz_type'] = $shop_info['shoprz_type'];
        }

        $parent['brandEndTime'] = $arr[$key]['parentType']['brandEndTime']; //品牌使用时间
        $arr[$key]['parentType'] = $parent; //自定义显示
        //自定义表单数据插入 end
    }

    return $arr;
}

//更新临时表中的数据为插入
function get_update_temporarydate_isAdd($catId_array, $user_id = 0, $type = 0) {
    $arr = array();

    if ($type == 0) {
        for ($i = 0; $i < count($catId_array); $i++) {
            $parentChild = explode(':', $catId_array[$i]);
            $arr[$i] = explode(',', $parentChild[1]);

            for ($j = 0; $j < count($arr[$i]); $j++) {
                $sql = "update " . $GLOBALS['ecs']->table('merchants_category_temporarydate') . " set is_add = 1 " . " where cat_id = '" . $arr[$i][$j] . "'";
                $GLOBALS['db']->query($sql);
            }
        }
    } else {

        for ($i = 0; $i < count($catId_array); $i++) {
            $parentChild = explode(':', $catId_array[$i]);
            $arr[$i] = explode(',', $parentChild[1]);

            $cat_id = isset($_POST['permanentCat_id_' . $parentChild[0]]) ? $_POST['permanentCat_id_' . $parentChild[0]] : array();
            $dt_id = isset($_POST['permanent_title_' . $parentChild[0]]) ? $_POST['permanent_title_' . $parentChild[0]] : array();
            $permanentFile['name'] = $_FILES['permanentFile_' . $parentChild[0]]['name'];
            $permanentFile['type'] = $_FILES['permanentFile_' . $parentChild[0]]['type'];
            $permanentFile['tmp_name'] = $_FILES['permanentFile_' . $parentChild[0]]['tmp_name'];
            $permanentFile['tmp_name'] = $_FILES['permanentFile_' . $parentChild[0]]['tmp_name'];
            $permanentFile['error'] = $_FILES['permanentFile_' . $parentChild[0]]['error'];
            $permanentFile['size'] = $_FILES['permanentFile_' . $parentChild[0]]['size'];
            $permanent_date = isset($_POST['categoryId_date_' . $parentChild[0]]) ? $_POST['categoryId_date_' . $parentChild[0]] : array();

            if (count($cat_id) > 0) { //操作一级类目证件插入或更新数据
                get_merchants_dt_file_insert_update($cat_id, $dt_id, $permanentFile, $permanent_date, $user_id);
            }
        }
    }

    return $arr;
}

//类目证件插入或更新数据函数
function get_merchants_dt_file_insert_update($cat_id, $dt_id, $permanentFile, $permanent_date, $user_id) {
    include_once(ROOT_PATH . '/includes/cls_image.php');
    $image = new cls_image($_CFG['bgcolor']);

    for ($i = 0; $i < count($cat_id); $i++) {

        $sql = "select * from " . $GLOBALS['ecs']->table('merchants_dt_file') . " where cat_id = '" . $cat_id[$i] . "' and dt_id = '" . $dt_id[$i] . "' and user_id = '" . $_SESSION['user_id'] . "'";
        $row = $GLOBALS['db']->getRow($sql);

        $pFile = $image->upload_image('', 'septs_Image', '', 1, $permanentFile['name'][$i], $permanentFile['type'][$i], $permanentFile['tmp_name'][$i], $permanentFile['error'][$i], $permanentFile['size'][$i]);  //图片存放地址 -- data/septs_Image
        $pFile = empty($pFile) ? $row['permanent_file'] : $pFile;

        get_oss_add_file(array($pFile));

        if (!empty($permanent_date[$i])) {
            $permanent_date[$i] = local_strtotime(trim($permanent_date[$i]));
        } else {
            $permanent_date[$i] = '';
        }


        if (!empty($pFile)) {
            if (!empty($permanent_date[$i])) {
                $catPermanent = 0;
            } else {
                $catPermanent = 1;
            }
        } else {
            $catPermanent = 0;
        }

        $parent = array(
            'cat_id' => intval($cat_id[$i]),
            'dt_id' => intval($dt_id[$i]),
            'user_id' => $user_id,
            'permanent_file' => $pFile,
            'permanent_date' => $permanent_date[$i],
            'cate_title_permanent' => $catPermanent
        );

        if ($row['dtf_id'] > 0) {
            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_dt_file'), $parent, 'UPDATE', "cat_id = '" . $cat_id[$i] . "' and dt_id = '" . $dt_id[$i] . "' and user_id = '" . $_SESSION['user_id'] . "'");
        } else {
            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_dt_file'), $parent, 'INSERT');
        }
    }
}

//入驻品牌列表 start
function get_septs_shop_brand_list($user_id = 0) {
    $sql = "select bid, bank_name_letter, brandName, brandFirstChar, brandLogo, brandType, brand_operateType, brandEndTime from " . $GLOBALS['ecs']->table('merchants_shop_brand') . " where user_id = '" . $user_id . "' order by bid asc";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $key => $row) {
        $key = $key + 1;
        $arr[$key]['bid'] = $row['bid'];
        $arr[$key]['bank_name_letter'] = $row['bank_name_letter'];
        $arr[$key]['brandName'] = $row['brandName'];
        $arr[$key]['brandFirstChar'] = $row['brandFirstChar'];
        $arr[$key]['brandLogo'] = $row['brandLogo'];
        $arr[$key]['brandType'] = $row['brandType'];
        $arr[$key]['brand_operateType'] = $row['brand_operateType'];
        $arr[$key]['brandEndTime'] = local_date("Y-m-d H:i", $row['brandEndTime']);
    }

    return $arr;
}

//复制店铺品牌
function copy_septs_shop_brand_list($user_id) {
    $sql = "INSERT INTO " . $GLOBALS['ecs']->table('merchants_shop_brand') . "( " .
            "bank_name_letter, brandName, brandFirstChar, brandLogo, brandType, brand_operateType, brandEndTime, brandEndTime_permanent ," .
            "site_url, brand_desc, sort_order, is_show, audit_status,add_time) " .
            " SELECT mer.bank_name_letter, mer.brandName, mer.brandFirstChar, mer.brandLogo, mer.brandType, mer.brand_operateType, mer.brandEndTime, mer.brandEndTime_permanent ," .
            " mer.site_url, mer.brand_desc, mer.sort_order, mer.is_show, mer.audit_status," . gmtime() .
            " FROM " . $GLOBALS['ecs']->table('merchants_shop_brand') . " mer" .
            " WHERE mer.user_id = '$user_id'";
    $GLOBALS['db']->query($sql);
}

//品牌资质文件上传
function get_shop_brand_file($qInput, $qImg, $eDinput, $b_fid, $ec_shop_bid) {
    include_once(ROOT_PATH . '/includes/cls_image.php');
    $image = new cls_image($_CFG['bgcolor']);

    for ($i = 0; $i < count($qInput); $i++) {

        $qInput[$i] = trim($qInput[$i]);
        $qImg[$i] = $image->upload_image('', 'septs_Image', '', 1, $qImg['name'][$i], $qImg['type'][$i], $qImg['tmp_name'][$i], $qImg['error'][$i], $qImg['size'][$i]);  //图片存放地址 -- data/septs_Image
        $eDinput[$i] = trim($eDinput[$i]);

        if (empty($qImg[$i])) { //证件是否永久有效
            $qPermanent = 0;
        } else {
            if (!empty($eDinput[$i])) {
                $qPermanent = 0;
            } else {
                $qPermanent = 1;
            }
        }

        if (!empty($eDinput[$i])) {
            $eDinput[$i] = local_strtotime($eDinput[$i]);
        } else {
            $eDinput[$i] = '';
        }

        get_oss_add_file(array($qImg[$i]));

        if (!empty($qInput[$i])) {
            $parent = array(
                'bid' => $ec_shop_bid,
                'qualificationNameInput' => $qInput[$i],
                'qualificationImg' => $qImg[$i],
                'expiredDateInput' => $eDinput[$i],
                'expiredDate_permanent' => $qPermanent
            );

            if (!empty($b_fid[$i])) {

                $sql = "select qualificationImg from " . $GLOBALS['ecs']->table('merchants_shop_brandfile') . " where bid = '$ec_shop_bid' and b_fid = '" . $b_fid[$i] . "'";
                $qualificationImg = $GLOBALS['db']->getOne($sql);

                if (empty($parent['qualificationImg'])) {
                    $parent['qualificationImg'] = $qualificationImg;
                }

                $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_shop_brandfile'), $parent, 'UPDATE', "bid = '$ec_shop_bid' and b_fid = '" . $b_fid[$i] . "'");
            } else {
                $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_shop_brandfile'), $parent, 'INSERT');
            }
        }
    }
}

function get_shop_brandfile_list($ec_shop_bid){
	$sql = "select b_fid, bid, qualificationNameInput, qualificationImg, expiredDateInput, expiredDate_permanent from " .$GLOBALS['ecs']->table('merchants_shop_brandfile'). " where bid = '$ec_shop_bid' order by b_fid asc";
	$res = $GLOBALS['db']->getAll($sql);

	$arr = array();
	foreach($res as $key=>$row){
		$arr[] = $row;
		$arr[$key]['expiredDateInput'] = local_date("Y-m-d H:i", $row['expiredDateInput']);
	}

	return $arr;
}
//入驻品牌列表 end

//会员申请商家入驻表单填写数据插入 start
function get_steps_title_insert_form($pid = 0){

	$steps_title = get_root_merchants_steps_title($pid);

	for($i=0; $i<count($steps_title); $i++){

		if(is_array($steps_title[$i]['cententFields'])){
			$cententFields = $steps_title[$i]['cententFields'];
			for($j=1; $j<=count($cententFields); $j++){
				$arr['formName'] .= $cententFields[$j]['textFields'] . ',';
			}
		}
	}

	$arr['formName'] = substr($arr['formName'], 0, -1);

	return $arr;
}

//返回插入基本信息字段数据
function get_setps_form_insert_date($formName){

	include_once(ROOT_PATH . '/includes/cls_image.php');
	$image = new cls_image($_CFG['bgcolor']);

	$formName = explode(',', $formName);
	$arr = array();
	for($i=0; $i<count($formName); $i++){
		if(substr($formName[$i],-3) == 'Img'){  //如果上传文件字段是图片或者压缩包 字段命名必须是 ******Img 格式 (自定义的上传文件)

			$setps_thumb = $image->upload_image($_FILES[$formName[$i]], 'septs_Image');  //图片存放地址 -- data/septs_Image

                        get_oss_add_file(array($setps_thumb));

			//文本隐藏域数据
			$textImg = $_POST['text_' . $formName[$i]];
			if(empty($setps_thumb)){
				if(!empty($textImg)){
					$setps_thumb = $textImg;
				}
			}

			$arr[$formName[$i]] = $setps_thumb;
		}else{
                        $arr[$formName[$i]] = $_POST[$formName[$i]];
		}

		if(is_array($arr[$formName[$i]])){
			$arr[$formName[$i]] = implode(',', $arr[$formName[$i]]);
		}
	}

	return $arr;
}
//会员申请商家入驻表单填写数据插入 end

//一级类目列表
function get_first_cate_list($parent_id = 0, $type = 0, $catarr = array(), $user_id = 0) {
    if ($type == 1) {

        for ($i = 0; $i < count($catarr); $i++) {
            if (!empty($catarr[$i])) {
                $sql = "DELETE FROM" . $GLOBALS['ecs']->table('merchants_category_temporarydate') . " WHERE cat_id = '" . $catarr[$i] . "' AND user_id = '" . $user_id . "'";
                $GLOBALS['db']->query($sql);
            }
        }

        return array();
    } else {
        $sql = "SELECT cat_id, cat_name FROM " . $GLOBALS['ecs']->table('category') . " where parent_id = '$parent_id'";

        return $GLOBALS['db']->getAll($sql);
    }
}

//查询二级类目详细信息 start //ajax返回类目数组
function get_child_category($cat){

	$arr = array();
	for($i=0; $i<count($cat); $i++){
		if(!empty($cat[$i])){
			$arr[$i] = $cat[$i];
			$arr['cat_id'] .= $cat[$i] . ',';
		}
	}

	$arr['cat_id'] = substr($arr['cat_id'], 0, -1);

	return $arr;
}

//二级类目数据插入临时数据表
function get_add_childCategory_info($cat_id, $user_id) {
    if (empty($cat_id)) {
        $cat_id = 0;
    }

    $sql = "SELECT cat_id, cat_name, parent_id FROM " . $GLOBALS['ecs']->table('category') . " WHERE cat_id in($cat_id) ORDER BY cat_id";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $key => $row) {
        $key = $key + 1;
        $arr[$key]['cat_id'] = $row['cat_id'];
        $arr[$key]['cat_name'] = $row['cat_name'];
        $arr[$key]['parent_name'] = $GLOBALS['db']->getOne("SELECT cat_name FROM " . $GLOBALS['ecs']->table('category') . " WHERE cat_id = '" . $row['parent_id'] . "'");

        $parent = array(
            'user_id' => $user_id,
            'cat_id' => $row['cat_id'],
            'parent_id' => $row['parent_id'],
            'cat_name' => $row['cat_name'],
            'parent_name' => $arr[$key]['parent_name']
        );

        if ($cat_id != 0) {

            $sql = "SELECT ct_id FROM " . $GLOBALS['ecs']->table('merchants_category_temporarydate') . " WHERE cat_id = '" . $row['cat_id'] . "' AND user_id = '$user_id'";
            $ct_id = $GLOBALS['db']->getOne($sql);

            if ($ct_id <= 0) {
                $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_category_temporarydate'), $parent, 'INSERT');
            }
        }
    }

    return $arr;
}

//查询临时数据表中的数据
function get_fine_category_info($cat_id, $user_id){

	if($cat_id != 0){
		get_add_childCategory_info($cat_id, $user_id);
	}

	$sql = "select ct_id, cat_id, cat_name, parent_name from " .$GLOBALS['ecs']->table('merchants_category_temporarydate'). " where user_id = '$user_id'";
	$res = $GLOBALS['db']->getAll($sql);

	$arr = array();
	foreach($res as $key=>$row){
		$key = $key + 1;
		$arr[$key]['ct_id'] = $row['ct_id'];
		$arr[$key]['cat_id'] = $row['cat_id'];
		$arr[$key]['cat_name'] = $row['cat_name'];
		$arr[$key]['parent_name'] = $row['parent_name'];
	}

	return $arr;
}

function get_permanent_parent_cat_id($user_id = 0, $type = 0) {

    if ($type == 1) {
        $group_by = "GROUP BY c.parent_id";
    } else {
        $group_by = '';
    }

    $sql = "SELECT c.parent_id, mct.cat_id FROM " . $GLOBALS['ecs']->table('merchants_category_temporarydate') . " AS mct " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('category') . " AS c ON mct.cat_id = c.cat_id " .
            "WHERE user_id = '" . $user_id . "' " . $group_by;
    return $GLOBALS['db']->getAll($sql);
}

//组合父ID的下级分类数组
function get_catId_array($user_id = 0) {

    if ($user_id <= 0) {
        $user_id = $_SESSION['user_id'];
    }

    $res = get_permanent_parent_cat_id($user_id);

    foreach ($res as $key => $row) {
        @$arr[$row['parent_id']] .= $row['cat_id'] . ',';
    }

    @$arr = get_explode_array($arr);
    return $arr;
}

function get_explode_array($arr) {
    $newArr = array();
    $i = 0;
    foreach ($arr as $key => $row) {
        $newArr[$i] = substr($key . ":" . $row, 0, -1);
        $i++;
    }

    return $newArr;
}

//查询类目证件标题列表
function get_category_permanent_list($user_id) {

    $res = get_permanent_parent_cat_id($user_id, 1);

    $arr = array();
    $arr['parentId'] = '';
    foreach ($res as $key => $row) {
        $arr[$key]['parent_id'] = $row['parent_id'];
        $arr['parentId'] .= $row['parent_id'] . ',';
    }

    $arr['parentId'] = substr($arr['parentId'], 0, -1);
    if (empty($arr['parentId'])) {
        $arr['parentId'] = 0;
    }

    if ($arr['parentId']) {
        $arr['parentId'] = get_del_str_comma($arr['parentId']);
        $cat_in = "cat_id in(" . $arr['parentId'] . ")";
    } else {
        $cat_in = "1";
    }

    $sql = "SELECT dt_id, dt_title, cat_id FROM " . $GLOBALS['ecs']->table('merchants_documenttitle') . " WHERE $cat_in ORDER BY dt_id ASC";
    $res = $GLOBALS['db']->getAll($sql);

    $parentId = $arr['parentId'];
    $arr = array();
    foreach ($res as $key => $row) {
        $arr[$key]['dt_id'] = $row['dt_id'];
        $arr[$key]['dt_title'] = $row['dt_title'];
        $arr[$key]['cat_id'] = $row['cat_id'];
        $arr[$key]['cat_name'] = $GLOBALS['db']->getOne("select cat_name from " . $GLOBALS['ecs']->table('category') . " where cat_id = '" . $row['cat_id'] . "'");

        $sql = "select permanent_file, permanent_date, cate_title_permanent from " . $GLOBALS['ecs']->table('merchants_dt_file') . " where cat_id = '" . $row['cat_id'] . "' and dt_id = '" . $row['dt_id'] . "' and user_id = '" . $user_id . "'";
        $row = $GLOBALS['db']->getRow($sql);

        $arr[$key]['permanent_file'] = $row['permanent_file'];
        $arr[$key]['cate_title_permanent'] = $row['cate_title_permanent'];
        if (!empty($row['permanent_date'])) {
            $arr[$key]['permanent_date'] = local_date("Y-m-d H:i", $row['permanent_date']);
        }
    }

    return $arr;
}

//删除类目时查找父级类目的含有数据数量
function get_temporarydate_ctId_catParent($ct_id) {
    $sql = "select parent_id from " . $GLOBALS['ecs']->table('merchants_category_temporarydate') . " where ct_id = '$ct_id'";
    $parent_id = $GLOBALS['db']->getOne($sql);

    $sql = "select ct_id from " . $GLOBALS['ecs']->table('merchants_category_temporarydate') . " where parent_id = '$parent_id'";
    $num = count($GLOBALS['db']->getAll($sql));

    $arr['parent_id'] = $parent_id;
    $arr['num'] = $num;

    return $arr;
}

//查询二级类目详细信息 end

//获取地区名称
function get_goods_region_name($region_id) {
    $sql = "SELECT region_name FROM " . $GLOBALS['ecs']->table('region') . " WHERE region_id = '$region_id'";
    return $GLOBALS['db']->getOne($sql, true);
}

//获取商品商家信息 start
function get_merchants_shop_info($table = '', $user_id = 0) {

    $sql = "select * from " . $GLOBALS['ecs']->table($table) . " where user_id = '$user_id'";

    return $GLOBALS['db']->getRow($sql);
}

function get_license_comp_adress($steps_adress) {
    $adress = explode(',', $steps_adress);

    $arr['province'] = '';
    $arr['city'] = '';
    $arr['province'] = get_goods_region_name($adress[1]);
    $arr['city'] = get_goods_region_name($adress[2]);

    if (!empty($arr['city'])) {
        $arr['city'] = $arr['city'] . '市';
    }

    return $arr;
}

//获取商品商家信息 end

//仓库 start
//----admin
/**
 * 获取地区仓库列表的函数。 ecmoban模板堂 --zhuo
 *
 * @access  public
 * @param   int     $region_id  上级地区id
 * @return  void
 */
function area_warehouse_list($region_id) {
    $area_arr = array();

    $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('region_warehouse') .
            " WHERE parent_id = '$region_id' ORDER BY region_id";
    $res = $GLOBALS['db']->query($sql);
    $i = 0;
    while ($row = $GLOBALS['db']->fetchRow($res)) {
        $row['type'] = ($row['region_type'] == 0) ? $GLOBALS['_LANG']['country'] : '';
        $row['type'] .= ($row['region_type'] == 1) ? $GLOBALS['_LANG']['province'] : '';
        $row['type'] .= ($row['region_type'] == 2) ? $GLOBALS['_LANG']['city'] : '';
        $row['type'] .= ($row['region_type'] == 3) ? $GLOBALS['_LANG']['cantonal'] : '';

        //$area_arr[] = $row;
        $area_arr[$i]['region_code'] = $row['region_code'];
        $area_arr[$i]['region_id'] = $row['region_id'];
        $area_arr[$i]['regionId'] = $row['regionId'];
        $area_arr[$i]['parent_id'] = $row['parent_id'];
        $area_arr[$i]['region_name'] = $row['region_name'];
        $area_arr[$i]['region_type'] = $row['region_type'];
        $area_arr[$i]['agency_id'] = $row['agency_id'];
        $area_arr[$i]['type'] = $row['type'];
        $area_arr[$i]['child'] = get_child_region($row['regionId']);
        $area_arr[$i]['region_child'] = area_warehouse_list($row['region_id']);

        $i++;
    }

    return $area_arr;
}

//查询是否还有子地区栏目
function get_child_region($region_id=0){
	$sql = "select * from " .$GLOBALS['ecs']->table('region'). " where parent_id = '$region_id'";

	return $GLOBALS['db']->getAll($sql);
}

//获取配送方式列表
function warehouse_shipping_list($goods = array(), $region_id = 0, $number = 1, $goods_region = array()) {

    $sql = "SELECT s.shipping_id, s.shipping_name, s.shipping_code FROM " . $GLOBALS['ecs']->table('shipping') . " AS s WHERE 1";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $key => $row) {
        if (substr($row['shipping_code'], 0, 5) == 'ship_') {
            unset($arr[$key]);
            continue;
        } else {
            $arr[$key]['shipping_id'] = $row['shipping_id'];
            $arr[$key]['shipping_name'] = $row['shipping_name'];
            $arr[$key]['shipping_code'] = $row['shipping_code'];

            if ($region_id > 0) {
                $goods['ru_id'] = $goods['user_id'];
                $shipping = get_goods_freight($goods, $region_id, $goods_region, $number, $row['shipping_code']);
                $arr[$key]['shipping_fee'] = price_format($shipping['shipping_fee'], false);
            }
        }
    }

    return $arr;
}

//查询地区运费
function get_warehouse_freight_type($region_id) {
    //ecmoban模板堂 --zhuo start
    $adminru = get_admin_ru_id();
    if ($adminru['ru_id'] > 0) {
        $ru_id = $adminru['ru_id'];
    } else {
        $ru_id = 0;
    }

    $ruCat = " and wf.user_id = '$ru_id' ";
    //ecmoban模板堂 --zhuo end

    $sql = "select wf.id, wf.configure, wf.shipping_id, wf.region_id, s.shipping_name, rw1.region_name as region_name1, rw2.region_name as region_name2, s.support_cod, s.shipping_code from " . $GLOBALS['ecs']->table('warehouse_freight') . " as wf " .
            " left join " . $GLOBALS['ecs']->table('region_warehouse') . " as rw1 on wf.warehouse_id = rw1.region_id" .
            " left join " . $GLOBALS['ecs']->table('shipping') . " as s on wf.shipping_id = s.shipping_id" .
            " left join " . $GLOBALS['ecs']->table('region_warehouse') . " as rw2 on wf.region_id = rw2.regionId" .
            " where wf.region_id = '$region_id' " . $ruCat . " group by wf.shipping_id order by id asc";

    return $GLOBALS['db']->getAll($sql);
}

//------root
//查询仓库下的省、直辖市区
function get_warehouse_province($type = 'root', $ra_id = 0) {
    $sql = "SELECT region_id AS regionId, region_name FROM " . $GLOBALS['ecs']->table('region') . " WHERE region_type = 1 ORDER BY region_id ASC";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $key => $row) {
        $arr[$key]['region_id'] = $row['regionId'];
        $arr[$key]['region_name'] = $row['region_name'];

        $where = '';
        if ($type == 'admin') {

            if ($ra_id > 0) {
                $where = "ra_id <> '$ra_id' and ";
            }

            $where .= "region_id = '" . $row['regionId'] . "'";

            $date = array('region_id');
            $region_id = get_table_date('merchants_region_info', $where, $date);

            if ($region_id > 0) {
                $arr[$key]['disabled'] = 1;
            } else {
                $arr[$key]['disabled'] = 0;
            }

            if ($ra_id > 0) {
                $where = "ra_id = '$ra_id' and " . "region_id = '" . $row['regionId'] . "'";
                $date = array('region_id');
                $region_id = get_table_date('merchants_region_info', $where, $date);

                if ($region_id > 0) {
                    $arr[$key]['checked'] = 1;
                } else {
                    $arr[$key]['checked'] = 0;
                }
            }
        }
    }

    return $arr;
}

//查询省、直辖市下所有地区
function get_region_city_county($city_district) {

    $sql = "SELECT region_id, region_name FROM " . $GLOBALS['ecs']->table('region') . " WHERE parent_id = '$city_district' ORDER BY region_id ASC";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $key => $row) {
        $arr[$key]['region_id'] = $row['region_id'];
        $arr[$key]['region_name'] = $row['region_name'];
    }

    return $arr;
}

//查询仓库
function get_warehouse_list_goods($region_type = 0){

	$sql = "select region_id, region_name from " .$GLOBALS['ecs']->table('region_warehouse'). " where region_type = '$region_type'";
	$res = $GLOBALS['db']->getAll($sql);

	$arr = array();
	foreach($res as $key=>$row){
		$arr[$key]['region_id'] = $row['region_id'];
		$arr[$key]['region_name'] = $row['region_name'];
	}

	return $arr;
}

function get_warehouse_name_id($region_id = 0,$region_name = ''){ //获取仓库名称或者ID

	if(!empty($region_name)){
		$name_type = "region_name = '$region_name' and region_type = '$region_id'";
		$region_id = '';
		$region = "region_id";
	}else{
		$name_type = '';
		$region_type = '';

		$region_id = "region_id = '$region_id'";

		$region = "region_name";
	}

	$sql = "select " .$region. " from " .$GLOBALS['ecs']->table('region_warehouse'). " where " . $region_id . $name_type;

	return $GLOBALS['db']->getOne($sql);
}

//查询地区名称
function get_region_info($region_id) {
    $sql = "SELECT region_id, region_name, parent_id FROM " . $GLOBALS['ecs']->table('region') . " WHERE region_id = '$region_id' LIMIT 1";
    return $GLOBALS['db']->getRow($sql);
}

//查询会员的收货地址
function get_user_address_region($user_id) {
    $sql = "select address_id, province, city, district from " . $GLOBALS['ecs']->table('user_address') . " where user_id = '$user_id'";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $key => $row) {
        $arr[$key]['address_id'] = $row['address_id'];
        $arr[$key]['province'] = $row['province'];
        $arr[$key]['city'] = $row['city'];
        $arr[$key]['district'] = $row['district'];

        $arr['region_address'] .= $row['province'] . "," . $row['city'] . "," . $row['district'] . ",";
    }
    $arr['region_address'] = substr($arr['region_address'], 0, -1);

    return $arr;
}

//查询用户订单
function get_user_order_area($user_id = 0) {
    $sql = "select country, province, city, district from " . $GLOBALS['ecs']->table('order_info') . " where user_id = '$user_id' order by order_id DESC";
    return $GLOBALS['db']->getRow($sql);
}

function get_user_area_reg($user_id) {
    $sql = "select ut.province, ut.city, ut.district from " . $GLOBALS['ecs']->table('users') . " as u " .
            " left join " . $GLOBALS['ecs']->table('users_type') . " as ut on u.user_id = ut.user_id" .
            " where u.user_id = '$user_id'";
    return $GLOBALS['db']->getRow($sql);
}

function get_province_id_warehouse($province_id) {
    $sql = "select parent_id from " . $GLOBALS['ecs']->table('region_warehouse') . " where regionId = '$province_id'";
    return $GLOBALS['db']->getOne($sql);
}

//查询地区region_id
function get_region_name_goods($region_type = 1, $region_name = '') {
    $sql = "select region_id from " . $GLOBALS['ecs']->table('region') . " where region_name = '$region_name' and region_type = '$region_type'";

    return $GLOBALS['db']->getOne($sql);
}

//查询子地区是否存在，有1个或者N个
function get_region_child_num($id = 0) {
    $sql = 'select region_id from ' . $GLOBALS['ecs']->table('region') . " where parent_id = '$id'";
    $res = $GLOBALS['db']->getAll($sql);

    return count($res);
}

//查询配送地区所属仓库
function get_warehouse_goods_region($province_id) {
    $sql = "select rw2.region_id, rw2.region_name from" . $GLOBALS['ecs']->table('region_warehouse') . " as rw1 left join " . $GLOBALS['ecs']->table('region_warehouse') . " as rw2 on rw1.parent_id = rw2.region_id" . " where rw1.regionId = '$province_id'";
    return $GLOBALS['db']->getRow($sql);
}

//查询商品的默认配送方式运费金额
function get_goods_freight($goods, $warehouse_id = 0, $goods_region = array(), $buy_number = 1, $shipping_code) {

    $sql = "SELECT shipping_id, shipping_code, shipping_name FROM " . $GLOBALS['ecs']->table('shipping') . " WHERE shipping_code = '$shipping_code' LIMIT 1";
    $shipping = $GLOBALS['db']->getRow($sql);

    /* 商品单独设置运费价格 start */
    $goods_transport = array();
    if($goods['goods_transport']){
        $goods['goods_transport'] = substr($goods['goods_transport'], 0, -1);
        $goods['goods_transport'] = explode("-", $goods['goods_transport']);
        if($goods['goods_transport']){
            foreach($goods['goods_transport'] as $key=>$row){

                $transport = explode("|", $row);

                $goods_transport[$key]['goods_id'] = $transport[0];
                $goods_transport[$key]['ru_id'] = $transport[1];
                $goods_transport[$key]['tid'] = $transport[2];
                $goods_transport[$key]['freight'] = $transport[3];
                $goods_transport[$key]['shipping_fee'] = $transport[4];
                $goods_transport[$key]['goods_number'] = $transport[5];
                $goods_transport[$key]['goods_weight'] = $transport[6];
                $goods_transport[$key]['shop_price'] = $transport[7];
            }
        }

        $order_transport = get_order_transport($goods_transport, $goods_region, $shipping['shipping_id'], $shipping_code);
    }
    /* 商品单独设置运费价格 end */

    if($goods['goods_price']){
        $street_configure = get_goods_freight_configure($goods, $warehouse_id, $goods_region['street'], $shipping_code);
        $district_configure = get_goods_freight_configure($goods, $warehouse_id, $goods_region['district'], $shipping_code);
        $city_configure = get_goods_freight_configure($goods, $warehouse_id, $goods_region['city'], $shipping_code);
        $province_configure = get_goods_freight_configure($goods, $warehouse_id, $goods_region['province'], $shipping_code);
        $default_configure = get_goods_default_configure($goods, $warehouse_id, $goods_region, $shipping_code); //by wu

        if ($street_configure) {
            $configure = $street_configure;
        } else if (!empty($district_configure)) {
            $configure = $district_configure;
        } elseif (!empty($city_configure)) {
            $configure = $city_configure;
        } elseif (!empty($province_configure)) {
            $configure = $province_configure;
        } else {
            $configure = $default_configure;
        }

        $goods['number'] = empty($goods['number']) ? $buy_number : $goods['number'];

        $shipping_cfg = sc_unserialize_config($configure);
        $configure_price = goods_shipping_fee($shipping_code, unserialize($configure), $goods['weight'], $goods['goods_price'], $goods['number']);

        $arr['shipping_fee'] = $configure_price;
        $arr['configure_price'] = price_format($configure_price, false);
        $arr['shipping_name'] = $shipping['shipping_name'];
        $arr['shipping_code'] = $shipping['shipping_code'];

        $arr['item_fee'] = price_format($shipping_cfg['item_fee'], false); /* 单件商品的配送价格（默认） */
        $arr['base_fee'] = price_format($shipping_cfg['base_fee'], false); /* N(500或1000克)克以内的价格 */
        $arr['step_fee'] = price_format($shipping_cfg['step_fee'], false); /* 续重每N(500或1000克)克增加的价格 */
        $arr['free_money'] = price_format($shipping_cfg['free_money'], false); //免费额度
        $arr['fee_compute_mode'] = $shipping_cfg['fee_compute_mode']; //费用计算方式
        @$arr['pay_fee'] = price_format($shipping_cfg['pay_fee'], false); //货到付款支付费用

    }else{
        $arr['shipping_fee'] = 0;
    }

    if($order_transport['freight']){
        $arr['shipping_fee'] += $order_transport['sprice']; /* 有配送按配送区域计算运费 */
    }else{
        $arr['shipping_fee'] = $order_transport['sprice'];
    }

    $arr['configure_price'] = price_format($configure_price, false);
    $arr['shipping_name'] = $shipping['shipping_name'];
    $arr['shipping_code'] = $shipping['shipping_code'];
    $arr['warehouse_id'] = $warehouse_id;

    return $arr;
}

//查询商品设置配送地区运费数据
function get_goods_freight_configure($goods, $warehouse_id, $region_id, $shipping_code) {

    $user_id = $goods['ru_id'];

    $date = array('shipping_id');
    $where = "shipping_code = '$shipping_code'";
    $shipping_id = get_table_date('shipping', $where, $date, 2);

    $sql = "SELECT configure FROM " . $GLOBALS['ecs']->table('warehouse_freight') . " where user_id = '$user_id' and warehouse_id = '$warehouse_id' and shipping_id = '$shipping_id' and region_id = '$region_id'";
    return $GLOBALS['db']->getOne($sql, true);
}

//查询模板商品设置配送地区运费数据 by wu
function get_goods_default_configure($goods, $warehouse_id, $region_id, $shipping_code) {

    $user_id = $goods['ru_id'];

    $date = array('shipping_id');
    $where = "shipping_code = '$shipping_code'";
    $shipping_id = get_table_date('shipping', $where, $date, 2);

    $sql = "select * from " . $GLOBALS['ecs']->table('warehouse_freight_tpl') . " where user_id = '$user_id' and shipping_id = '$shipping_id' ";
    $tpl_info = $GLOBALS['db']->getAll($sql);

    foreach ($tpl_info as $tpl) {
        $tpl_status_1 = array_intersect($region_id, explode(',', $tpl['region_id']));
        $tpl_status_2 = in_array($warehouse_id, explode(',', $tpl['warehouse_id']));
        if ($tpl_status_1 && $tpl_status_2) {
            return $tpl['configure'];
        }
    }
    return false;
}

//获取仓库运费模板列表 by wu
function get_ship_tpl_list($shipping_id = 0, $ru_id = 0) {
    $sql = " select * from " . $GLOBALS['ecs']->table('warehouse_freight_tpl') . " where shipping_id='$shipping_id' and user_id='$ru_id'";
    $tpl_list = $GLOBALS['db']->getAll($sql);
    //配送区域、仓库列表
    foreach ($tpl_list as $key => $value) {
        //配送区域
        if (!empty($value['region_id'])) {
            $sql = " SELECT region_name from " . $GLOBALS['ecs']->table('region') . " where region_id in (" . $value['region_id'] . ") ";
            $regions = $GLOBALS['db']->getCol($sql);
            $tpl_list[$key]['regions'] = implode(',', $regions);
        }
        //仓库列表
        if (!empty($value['warehouse_id'])) {
            $sql = " SELECT region_name from " . $GLOBALS['ecs']->table('region_warehouse') . " where region_id in (" . $value['warehouse_id'] . ") ";
            $warehouses = $GLOBALS['db']->getCol($sql);
            $tpl_list[$key]['warehouses'] = implode(' | ', $warehouses);
        }
    }
    return $tpl_list;
}

//获取仓库数组
function get_warehouse_list($type = 0, $goods_id = 0) {

    $sql = "select region_id, region_name from " . $GLOBALS['ecs']->table('region_warehouse') . " where 1 and region_type = '$type'";

    return $GLOBALS['db']->getAll($sql);
}

//批量添加商品仓库库存
function get_insert_warehouse_goods($goods_id = 0, $warehouse_name = array(), $warehouse_number = array(), $warehouse_price = array(), $warehouse_promote_price = array(), $user_id = 0) {

    $add_time = gmtime();
    for ($i = 0; $i < count($warehouse_name); $i++) {
        if (!empty($warehouse_name[$i])) {


            if ($warehouse_number[$i] == 0) {
                $warehouse_number[$i] = 1;
            }

            $sql = "select w_id from " . $GLOBALS['ecs']->table('warehouse_goods') . " where goods_id = '$goods_id' and region_id = '" . $warehouse_name[$i] . "'";
            $w_id = $GLOBALS['db']->getOne($sql);

            $parent = array(
                'goods_id'                  => $goods_id,
                'region_id'                 => $warehouse_name[$i],
                'region_number'             => intval($warehouse_number[$i]),
                'warehouse_price'           => floatval($warehouse_price[$i]),
                'warehouse_promote_price'   => floatval($warehouse_promote_price[$i]),
                'user_id'                   => $user_id,
                'add_time'                  => $add_time
            );

            if ($w_id > 0) {
                $link[] = array('text' => '返回一页', 'href' => 'goods.php?act=edit&goods_id=' . $goods_id . '&extension_code=');
                sys_msg('该商品的仓库库存已存在', 0, $link);
                break;
            } else {
                $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('warehouse_goods'), $parent, 'INSERT');
            }
        }
    }
}

//批量添加商品地区价格
function get_insert_warehouse_area_goods($goods_id = 0, $area_name = array(), $region_number = array(), $region_price = array(), $region_promote_price = array(), $user_id = 0) {

    $add_time = gmtime();
    for ($i = 0; $i < count($area_name); $i++) {
        if (!empty($area_name[$i])) {

            $sql = "select a_id from " . $GLOBALS['ecs']->table('warehouse_area_goods') . " where goods_id = '$goods_id' and region_id = '" . $area_name[$i] . "'";
            $a_id = $GLOBALS['db']->getOne($sql);

            if ($a_id > 0) {
                $link[] = array('text' => '返回一页', 'href' => 'goods.php?act=edit&goods_id=' . $goods_id . '&extension_code=');
                sys_msg('该商品的地区价格已存在', 0, $link);
                break;
            } else {
                $sql = "insert into " . $GLOBALS['ecs']->table('warehouse_area_goods') .
                        "(goods_id, region_id, region_number, region_price, region_promote_price, user_id, add_time)VALUES('" .
                        $goods_id . "','" . $area_name[$i] . "','" . $region_number[$i] . "','" . floatval($region_price[$i]) . "','" . floatval($region_promote_price[$i]) . "','$user_id','$add_time')";

                $GLOBALS['db']->query($sql);
            }
        }
    }
}

//查询仓库列表
function get_warehouse_goods_list($goods_id = 0) {
    $sql = "SELECT wg.w_id, wg.region_id, wg.region_sn, wg.region_number, wg.warehouse_price, wg.warehouse_promote_price, rw.region_name, rw.region_name, wg.give_integral, wg.rank_integral, wg.pay_integral FROM " .
            $GLOBALS['ecs']->table('warehouse_goods') . " as wg, " . $GLOBALS['ecs']->table('region_warehouse') . " as rw " .
            " WHERE wg.region_id = rw.region_id AND wg.goods_id = '$goods_id' ORDER BY rw.region_id asc";

    return $GLOBALS['db']->getAll($sql);
}

//查询仓库列表
function get_warehouse_area_goods_list($goods_id = 0) {
    $sql = "SELECT wag.a_id, wag.region_id, wag.region_sn, wag.region_number, wag.region_price, wag.region_promote_price, wag.region_sort, rw.region_name, rw.parent_id, wag.give_integral, wag.rank_integral, wag.pay_integral FROM " .
            $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag, " . $GLOBALS['ecs']->table('region_warehouse') . " as rw " .
            " WHERE wag.region_id = rw.region_id AND wag.goods_id = '$goods_id' ORDER BY rw.region_id, wag.region_sort asc";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $key => $row) {
        $arr[$key] = $row;
        $arr[$key]['warehouse_name'] = $GLOBALS['db']->getOne("select region_name from " . $GLOBALS['ecs']->table('region_warehouse') . " where region_id = '" . $row['parent_id'] . "'");
    }

    return $arr;
}

//批量添加货号 start
function get_produts_warehouse_list($goods_list) {
    $arr = array();
    for ($i = 0; $i < count($goods_list); $i++) {

        $goods_where = array(
            'name' => $goods_list[$i]['goods_name']
        );

        $warehouse_where = array(
            'name' => $goods_list[$i]['warehouse_id']
        );

        $arr[$i]['goods_id'] = get_products_name($goods_where, 'goods');
        $arr[$i]['warehouse_id'] = get_products_name($warehouse_where, 'region_warehouse');
        $arr[$i]['goods_attr'] = $goods_list[$i]['goods_attr'];
        $arr[$i]['product_sn'] = $goods_list[$i]['product_sn'];
        $arr[$i]['product_number'] = $goods_list[$i]['product_number'];
    }

    return $arr;
}

/**
 * 批量添加货号(仓库模式) bylu
 * @param $goods_list 数据列表
 * @param $attr_nums 属性个数
 * @return array 添加货号后属性
 */
function get_produts_warehouse_list2($goods_list, $attr_num = 0) {

    $arr = array();
    for ($i = 0; $i < count($goods_list); $i++) {

        $goods_where = array(
            'sn_name' => $goods_list[$i]['goods_sn']
        );

        $warehouse_where = array(
            'name' => $goods_list[$i]['warehouse_id']
        );

        $arr[$i]['goods_id'] = get_products_name($goods_where, 'goods');
        $arr[$i]['warehouse_id'] = get_products_name($warehouse_where, 'region_warehouse');
        for ($j = 0; $j < $attr_num; $j++) {

            $attr_info = explode("-", $goods_list[$i]['goods_attr' . $j]);
            $attr_value = isset($attr_info[0]) ? $attr_info[0] : '';
            $attr_id = isset($attr_info[1]) ? $attr_info[1] : 0;

            $where_select = array(
                'goods_id' => $arr[$i]['goods_id'],
                'attr_id' => $attr_id,
                'attr_value' => $attr_value,
            );

            if (empty($arr[$i]['goods_id'])) {
                $admin_id = get_admin_id();
                $where_select['admin_id'] = $admin_id;
            }

            $goods_attr_id = get_goods_attr_id($where_select, array('ga.goods_attr_id'), 1);

            if ($j == $attr_num - 1) {
                $attr_name[$j] = $goods_list[$i]['goods_attr' . $j]; //属性名称 bylu;
                $attr[$j] = $goods_attr_id; //属性id bylu;
            } else {
                $attr_name[$j] = $goods_list[$i]['goods_attr' . $j] . '|'; //属性名称 bylu;
                $attr[$j] = $goods_attr_id . '|'; //属性id bylu;
            }
        }
        $arr[$i]['goods_attr'] = implode('', $attr); //拼凑属性ID;
        $arr[$i]['goods_attr_name'] = implode('', $attr_name); //拼凑属性名称;

        if ($GLOBALS['_CFG']['goods_attr_price'] == 1) {
            $arr[$i]['product_market_price'] = $goods_list[$i]['product_market_price'];
            $arr[$i]['product_price'] = $goods_list[$i]['product_price'];
            $arr[$i]['product_promote_price'] = $goods_list[$i]['product_promote_price'];
        }

        $arr[$i]['product_number'] = $goods_list[$i]['product_number'];
        $arr[$i]['min_quantity'] = $goods_list[$i]['min_quantity'];
        $arr[$i]['product_warn_number'] = $goods_list[$i]['product_warn_number'];

        //如果货品编号为空,自动生成货品编号;
        if (empty($goods_list[$i]['product_sn'])) {
            $arr[$i]['product_sn'] = $goods_list[$i]['goods_sn'] . 'g_p' . $i;
        } else {
            $arr[$i]['product_sn'] = $goods_list[$i]['product_sn'];
        }

        $arr[$i]['bar_code'] = $goods_list[$i]['bar_code'];
    }

    return $arr;
}

/**
 * 批量添加货号(默认模式) bylu
 * @param $goods_list 数据列表
 * @param $attr_nums 属性个数
 * @return array 添加货号后属性
 */
function get_produts_list2($goods_list,$attr_num=0){

    $arr = array();
    for($i=0;$i<count($goods_list);$i++){

        $goods_where = array(
            'id' => $goods_list[$i]['goods_id'],
            'name' => $goods_list[$i]['goods_name'],
            'sn_name' => $goods_list[$i]['goods_sn'],
            'seller_id' => $goods_list[$i]['seller_id'],
        );

        $arr[$i]['goods_id'] = get_products_name($goods_where,'goods');
        $arr[$i]['warehouse_id'] = 0;

        for($j=0;$j<$attr_num;$j++){

            $attr_info = explode("-", $goods_list[$i]['goods_attr' . $j]);
            $attr_value = isset($attr_info[0]) ? $attr_info[0] : '';
            $attr_id = isset($attr_info[1]) ? $attr_info[1] : 0;

            $where_select = array(
                'goods_id' => $arr[$i]['goods_id'],
                'attr_id' => $attr_id,
                'attr_value' => $attr_value,
            );

            if (empty($arr[$i]['goods_id'])) {
                $admin_id = get_admin_id();
                $where_select['admin_id'] = $admin_id;
            }

            $goods_attr_id = get_goods_attr_id($where_select, array('ga.goods_attr_id'), 1);

            if ($j == $attr_num - 1) {
                $attr_name[$j] = $attr_value; //属性名称 bylu;
                $attr[$j] = $goods_attr_id; //属性id bylu;
            } else {
                $attr_name[$j] = !empty($attr_value) ? $attr_value . '|' : ''; //属性名称 bylu;
                $attr[$j] = !empty($goods_attr_id) ? $goods_attr_id . '|' : ''; //属性id bylu;
            }
        }

        $arr[$i]['goods_attr'] = implode('',$attr);//拼凑属性ID;
        $arr[$i]['goods_attr_name'] = implode('',$attr_name);//拼凑属性名称;

        if ($GLOBALS['_CFG']['goods_attr_price'] == 1) {
            $arr[$i]['product_market_price'] = $goods_list[$i]['product_market_price'];
            $arr[$i]['product_price'] = $goods_list[$i]['product_price'];
            $arr[$i]['product_promote_price'] = $goods_list[$i]['product_promote_price'];
        }

        $arr[$i]['product_number'] = $goods_list[$i]['product_number'];
        $arr[$i]['product_warn_number'] = $goods_list[$i]['product_warn_number'];

        //如果货品编号为空,自动生成货品编号;
        if(empty($goods_list[$i]['product_sn'])){
            $arr[$i]['product_sn']=$goods_list[$i]['goods_sn'].'g_p'.$i;
        }else{
            $arr[$i]['product_sn'] = $goods_list[$i]['product_sn'];
        }

        $arr[$i]['bar_code'] = $goods_list[$i]['bar_code'];
    }

    return $arr;
}

/**
 * 批量添加货号(地区模式) bylu
 * @param $goods_list 数据列表
 * @param $attr_nums 属性个数
 * @return array 添加货号后属性
 */
function get_produts_area_list2($goods_list, $attr_num = 0) {

    $arr = array();
    for ($i = 0; $i < count($goods_list); $i++) {

        $goods_where = array(
            'sn_name' => $goods_list[$i]['goods_sn']
        );

        $warehouse_where = array(
            'name' => $goods_list[$i]['area_id']
        );

        $arr[$i]['goods_id'] = get_products_name($goods_where, 'goods');
        $arr[$i]['area_id'] = get_products_name($warehouse_where, 'region_warehouse');

        for ($j = 0; $j < $attr_num; $j++) {

            $attr_info = explode("-", $goods_list[$i]['goods_attr' . $j]);
            $attr_value = isset($attr_info[0]) ? $attr_info[0] : '';
            $attr_id = isset($attr_info[1]) ? $attr_info[1] : 0;

            $where_select = array(
                'goods_id' => $arr[$i]['goods_id'],
                'attr_id' => $attr_id,
                'attr_value' => $attr_value,
            );

            if (empty($arr[$i]['goods_id'])) {
                $admin_id = get_admin_id();
                $where_select['admin_id'] = $admin_id;
            }

            $goods_attr_id = get_goods_attr_id($where_select, array('ga.goods_attr_id'), 1);

            if ($j == $attr_num - 1) {
                $attr_name[$j] = $goods_list[$i]['goods_attr' . $j]; //属性名称 bylu;
                $attr[$j] = $goods_attr_id; //属性id bylu;
            } else {
                $attr_name[$j] = $goods_list[$i]['goods_attr' . $j] . '|'; //属性名称 bylu;
                $attr[$j] = $goods_attr_id . '|'; //属性id bylu;
            }
        }
        $arr[$i]['goods_attr'] = implode('', $attr); //拼凑属性ID;
        $arr[$i]['goods_attr_name'] = implode('', $attr_name); //拼凑属性名称;

        if ($GLOBALS['_CFG']['goods_attr_price'] == 1) {
            $arr[$i]['product_market_price'] = $goods_list[$i]['product_market_price'];
            $arr[$i]['product_price'] = $goods_list[$i]['product_price'];
            $arr[$i]['product_promote_price'] = $goods_list[$i]['product_promote_price'];
        }

        $arr[$i]['product_number'] = $goods_list[$i]['product_number'];
        $arr[$i]['min_quantity'] = $goods_list[$i]['min_quantity'];
        $arr[$i]['product_warn_number'] = $goods_list[$i]['product_warn_number'];

        //如果货品编号为空,自动生成货品编号;
        if (empty($goods_list[$i]['product_sn'])) {
            $arr[$i]['product_sn'] = $goods_list[$i]['goods_sn'] . 'g_p' . $i;
        } else {
            $arr[$i]['product_sn'] = $goods_list[$i]['product_sn'];
        }

        $arr[$i]['bar_code'] = $goods_list[$i]['bar_code'];
    }

    return $arr;
}

function get_produts_warehouse_attr_list($goods_attr = '', $goods_id = 0) {

    $goods_attr = explode(',', $goods_attr);

    $arr = array();
    for ($i = 0; $i < count($goods_attr); $i++) {
        $sql = "select goods_attr_id, attr_value from " . $GLOBALS['ecs']->table('goods_attr') . " where goods_id = '$goods_id' and attr_value = '" . $goods_attr[$i] . "'";
        $row = $GLOBALS['db']->getRow($sql);

        $arr[$i]['goods_attr_id'] = $row['goods_attr_id'];
        $arr[$i]['attr_value'] = $row['attr_value'];

        $arr['goods_attr'] .= $row['goods_attr_id'] . '|';
    }

    $arr['goods_attr'] = substr($arr['goods_attr'], 0, -1);

    return $arr;
}

//查找商品ID
function get_products_name($where = array(), $table = ''){

    if ($table) {
        $as = '';
        if ($table === 'goods') {
            $select = "goods_id";

            $where_select = "1 AND is_delete = 0";
            if(isset($where['name'])){
                $where_select .= " AND goods_name = '" . $where['name'] . "'";
            }

            if(isset($where['sn_name'])){
                $where_select .= " AND goods_sn = '" . $where['sn_name'] . "'";
            }

            if(isset($where['seller_id'])){
                $where_select .= " AND user_id = '" . $where['seller_id'] . "'";
            }

        } elseif ($table === 'region_warehouse') {
            $select = "region_id";

            $where_select = "1";
            if(isset($where['name'])){
                $where_select .= " AND region_name = '" . $where['name'] . "'";
            }
        }

        if (isset($where['id']) && !empty($where['id'])) {
            return $where['id'];
        } else {
            $sql = "SELECT " . $select . " FROM " . $GLOBALS['ecs']->table($table) . " WHERE " . $where_select;
            return $GLOBALS['db']->getOne($sql);
        }
    }
}
//批量添加货号 end

//批量添加商品仓库 start
function get_goods_bacth_warehouse_list($goods_list) {
    $arr = array();
    for ($i = 0; $i < count($goods_list); $i++) {

        $where_goods = "goods_name = '" . $goods_list[$i]['goods_name'] . "'";
        $where_region = "region_name = '" . $goods_list[$i]['warehouse_name'] . "'";

        $arr[$i]['user_id'] = get_table_date('goods', $where_goods, array('user_id'), 2);
        $arr[$i]['goods_id'] = get_table_date('goods', $where_goods, array('goods_id'), 2);
        $arr[$i]['region_id'] = get_table_date('region_warehouse', $where_region, array('region_id'), 2);
        $arr[$i]['region_number'] = $goods_list[$i]['warehouse_number'];
        $arr[$i]['warehouse_price'] = $goods_list[$i]['warehouse_price'];
        $arr[$i]['warehouse_promote_price'] = $goods_list[$i]['warehouse_promote_price'];
        $arr[$i]['add_time'] = gmtime();
    }

    return $arr;
}

function get_insert_bacth_warehouse($goods_list) {
    $arr = array();
    for ($i = 0; $i < count($goods_list); $i++) {

        if ($goods_list[$i]['goods_id'] > 0) {
            if (empty($goods_list[$i]['warehouse_price'])) {
                $goods_list[$i]['warehouse_price'] = 0;
            }

            if (empty($goods_list[$i]['warehouse_promote_price'])) {
                $goods_list[$i]['warehouse_promote_price'] = 0;
            }

            $other['user_id'] = $goods_list[$i]['user_id'];
            $other['goods_id'] = $goods_list[$i]['goods_id'];
            $other['region_id'] = $goods_list[$i]['region_id'];
            $other['region_number'] = $goods_list[$i]['region_number'];
            $other['warehouse_price'] = $goods_list[$i]['warehouse_price'];
            $other['warehouse_promote_price'] = $goods_list[$i]['warehouse_promote_price'];
            $other['add_time'] = $goods_list[$i]['add_time'];

            $sql = "select w_id from " . $GLOBALS['ecs']->table('warehouse_goods') . " where user_id = '" . $other['user_id'] . "' and goods_id = '" . $other['goods_id'] . "'" .
                    " and region_id = '" . $other['region_id'] . "'";

            $res = $GLOBALS['db']->getOne($sql);

            if ($res > 0) {
                $return = 1;
                $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('warehouse_goods'), $other, 'UPDATE', " user_id = '" . $other['user_id'] . "' and goods_id = '" . $other['goods_id'] . "'" .
                        " and region_id = '" . $other['region_id'] . "'");
            } else {
                $return = 0;
                $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('warehouse_goods'), $other, 'INSERT');
            }
        }
    }

    return $return;
}

//批量添加商品仓库 end

//批量添加商品地区 start
function get_goods_bacth_area_list($goods_list) {
    $arr = array();
    for ($i = 0; $i < count($goods_list); $i++) {

        $where_goods = "goods_name = '" . $goods_list[$i]['goods_name'] . "'";
        $where_region = "region_name = '" . $goods_list[$i]['area_name'] . "'";

        $arr[$i]['user_id'] = get_table_date('goods', $where_goods, array('user_id'), 2);
        $arr[$i]['goods_id'] = get_table_date('goods', $where_goods, array('goods_id'), 2);
        $arr[$i]['region_id'] = get_table_date('region_warehouse', $where_region, array('region_id'), 2);
        $arr[$i]['region_number'] = $goods_list[$i]['region_number'];
        $arr[$i]['region_price'] = $goods_list[$i]['region_price'];
        $arr[$i]['region_promote_price'] = $goods_list[$i]['region_promote_price'];
        $arr[$i]['add_time'] = gmtime();
        $arr[$i]['region_sort'] = $goods_list[$i]['region_sort'];
    }

    return $arr;
}

function get_insert_bacth_area($goods_list) {

    $arr = array();
    for ($i = 0; $i < count($goods_list); $i++) {

        if ($goods_list[$i]['goods_id'] > 0) {
            if (empty($goods_list[$i]['region_price'])) {
                $goods_list[$i]['region_price'] = 0;
            }

            if (empty($goods_list[$i]['region_promote_price'])) {
                $goods_list[$i]['region_promote_price'] = 0;
            }

            $other['user_id'] = $goods_list[$i]['user_id'];
            $other['goods_id'] = $goods_list[$i]['goods_id'];
            $other['region_id'] = $goods_list[$i]['region_id'];
            $other['region_number'] = $goods_list[$i]['region_number'];
            $other['region_price'] = $goods_list[$i]['region_price'];
            $other['region_promote_price'] = $goods_list[$i]['region_promote_price'];
            $other['add_time'] = $goods_list[$i]['add_time'];
            $other['region_sort'] = $goods_list[$i]['region_sort'];

            $sql = "select a_id from " . $GLOBALS['ecs']->table('warehouse_area_goods') . " where user_id = '" . $other['user_id'] . "' and goods_id = '" . $other['goods_id'] . "'" .
                    " and region_id = '" . $other['region_id'] . "'";

            $res = $GLOBALS['db']->getOne($sql);

            $arr['goods_id'] = $other['goods_id'];
            if ($res > 0) {
                $arr['return'] = 1;
                $return = $arr;

                $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('warehouse_area_goods'), $other, 'UPDATE', " user_id = '" .
                        $other['user_id'] . "' and goods_id = '" . $other['goods_id'] . "'" .
                        " and region_id = '" . $other['region_id'] . "'");
            } else {
                $arr['return'] = 0;
                $return = $arr;

                $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('warehouse_area_goods'), $other, 'INSERT');
            }
        }
    }

    return $return;
}

//批量添加商品地区 end

//批量添加商品地区属性 start

/**
 * 商品属性单一模式
 * 商品属性列表
 * 更新价格
 */
function get_goods_bacth_warehouse_attr_list($goods_list) {

    for ($i = 0; $i < count($goods_list); $i++) {
        if ($goods_list[$i]['attr_name']) {

            if (empty($goods_list[$i]['goods_id'])) {
                $where_goods = "goods_name = '" . $goods_list[$i]['goods_name'] . "' AND user_id = '" . $goods_list[$i]['seller_id'] . "'";
                $goods_id = get_table_date('goods', $where_goods, array('goods_id'), 2);
            } else {
                $goods_id = $goods_list[$i]['goods_id'];
            }

            $where_region = "region_name = '" . $goods_list[$i]['region_name'] . "'";
            $where_attr = "attr_value = '" . $goods_list[$i]['attr_name'] . "' AND goods_id = '$goods_id'";

            $arr[$i]['goods_id'] = !empty($goods_id) ? $goods_id : 0;
            $arr[$i]['goods_name'] = !empty($goods_list[$i]['goods_name']) ? $goods_list[$i]['goods_name'] : '';
            $arr[$i]['shop_name'] = $goods_list[$i]['shop_name'];
            $arr[$i]['region_name'] = $goods_list[$i]['region_name'];
            $arr[$i]['attr_name'] = $goods_list[$i]['attr_name'];
            $arr[$i]['warehouse_id'] = get_table_date('region_warehouse', $where_region, array('region_id'), 2);
            $arr[$i]['goods_attr_id'] = get_table_date('goods_attr', $where_attr, array('goods_attr_id'), 2);
            $arr[$i]['attr_price'] = $goods_list[$i]['attr_price'];
        }
    }

    return $arr;
}

function get_goods_bacth_area_attr_list($goods_list) {

    for ($i = 0; $i < count($goods_list); $i++) {
        if ($goods_list[$i]['attr_name']) {

            if (empty($goods_list[$i]['goods_id'])) {
                $where_goods = "goods_name = '" . $goods_list[$i]['goods_name'] . "' AND user_id = '" . $goods_list[$i]['seller_id'] . "'";
                $goods_id = get_table_date('goods', $where_goods, array('goods_id'), 2);
            } else {
                $goods_id = $goods_list[$i]['goods_id'];
            }

            $where_region = "region_name = '" . $goods_list[$i]['region_name'] . "'";
            $where_attr = "attr_value = '" . $goods_list[$i]['attr_name'] . "' AND goods_id = '$goods_id'";

            $arr[$i]['goods_id'] = !empty($goods_id) ? $goods_id : 0;
            $arr[$i]['goods_name'] = !empty($goods_list[$i]['goods_name']) ? $goods_list[$i]['goods_name'] : '';
            $arr[$i]['shop_name'] = $goods_list[$i]['shop_name'];
            $arr[$i]['region_name'] = $goods_list[$i]['region_name'];
            $arr[$i]['attr_name'] = $goods_list[$i]['attr_name'];
            $arr[$i]['area_id'] = get_table_date('region_warehouse', $where_region, array('region_id'), 2);
            $arr[$i]['goods_attr_id'] = get_table_date('goods_attr', $where_attr, array('goods_attr_id'), 2);
            $arr[$i]['attr_price'] = $goods_list[$i]['attr_price'];
        }
    }

    return $arr;
}

function get_insert_bacth_area_attr($goods_list) {
    $arr = array();
    for ($i = 0; $i < count($goods_list); $i++) {

        if ($goods_list[$i]['goods_id'] > 0) {
            if (empty($goods_list[$i]['attr_price'])) {
                $goods_list[$i]['attr_price'] = 0;
            }

            $other['goods_id'] = $goods_list[$i]['goods_id'];
            $other['area_id'] = $goods_list[$i]['area_id'];
            $other['goods_attr_id'] = $goods_list[$i]['goods_attr_id'];
            $other['attr_price'] = $goods_list[$i]['attr_price'];
            $other['attrNumber'] = $goods_list[$i]['attr_number'];

            $sql = "select id from " . $GLOBALS['ecs']->table('warehouse_area_attr') . " where goods_id = '" . $other['goods_id'] . "' and area_id = '" . $other['area_id'] . "'" .
                    " and goods_attr_id = '" . $other['goods_attr_id'] . "'";

            $res = $GLOBALS['db']->getOne($sql);

            $arr['goods_id'] = $other['goods_id'];
            if ($res > 0) {
                $arr['return'] = 1;
                $return = $arr;

                $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('warehouse_area_attr'), $other, 'UPDATE', " goods_id = '" . $other['goods_id'] . "' and area_id = '" . $other['area_id'] . "'" .
                        " and goods_attr_id = '" . $other['goods_attr_id'] . "'");
            } else {
                $arr['return'] = 0;
                $return = $arr;

                $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('warehouse_area_attr'), $other, 'INSERT');
            }
        }
    }

    return $return;
}

//批量添加商品地区属性 end

//查询属性商品仓库库存
function get_warehouse_id_attr_number($goods_id = 0, $attr_id = '', $seller_id = 0, $warehouse_id = 0, $area_id = 0, $model_attr = '', $store_id = 0) {

    if (empty($model_attr)) {
        $model_attr = get_table_date("goods", "goods_id = '$goods_id'", array('model_attr'), 2);
    }

    $where = '';
    if (empty($attr_id)) {
        $attr_id = 0;
    } else {
        //去掉复选属性by wu start
        if (strpos($attr_id, '|') !== false) {
            $attr_arr = explode('|', $attr_id);
        } else {
            $attr_arr = explode(',', $attr_id);
        }


        foreach ($attr_arr as $key => $val) {
            //单属性bug
            if (!$val) {
                continue;
            }
            $attr_type = get_goods_attr_id(array('goods_id'=> $goods_id, 'goods_attr_id' => $val), array('a.attr_type'));

            if (($attr_type == 0 || $attr_type == 2) && $attr_arr[$key]) {
                unset($attr_arr[$key]);
            }
        }
        //去掉复选属性by wu end

        //获取货品信息
        foreach ($attr_arr as $key => $val) {
            //单属性bug
            if (!$val) {
                continue;
            }
            $where .= " AND FIND_IN_SET('$val', REPLACE(goods_attr, '|', ',')) ";
        }
    }

    $select = '';
    if ($store_id > 0) {
        /* 门店商品 by kong 20160722 */
        $table = "store_products";
        $where .= " AND store_id = '$store_id'";
    } else {
        $select .= ", product_price, product_promote_price, product_market_price, bar_code";
        /* 普通商品 */
        if ($model_attr == 1) {
            $table = "products_warehouse";
            $where .= " AND warehouse_id = '$warehouse_id'";
        } elseif ($model_attr == 2) {
            $table = "products_area";
            $where .= " AND area_id = '$area_id'";
        } else {
            $table = "products";
        }
    }

    $sql = "SELECT product_id, product_number, product_sn $select FROM " . $GLOBALS['ecs']->table($table) . " WHERE goods_id = '$goods_id'" . $where . " ORDER BY product_id DESC LIMIT 1";
    $row = $GLOBALS['db']->getRow($sql);

    if ($row) {
        $row['product_table'] = $table;
    }

    return $row;
}

//计算会员下订单的商品总运费
function get_goods_order_shipping_fee($goods = array(), $region = '', $shipping_id = 0) {
    $arr = array();
    $arr['shipping_fee'] = 0;

    //订单总运费计算
    $cart_goods = get_warehouse_cart_goods_info($goods, 1, $region, $shipping_id);
    $arr['shipping_fee'] = $cart_goods['shipping']['shipping_fee'];
    $arr['ru_list'] = $cart_goods['ru_list'];
    return $arr;
}

//获取仓库共有多少个地区数量
function get_all_warehouse_area_count() {

    $sql = "select region_id, region_name from " . $GLOBALS['ecs']->table('region_warehouse') . " where parent_id = 0";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $row) {
        $arr[$row['region_id']]['region_id'] = $row['region_id'];
        $arr['region_id'] .= $row['region_id'] . ",";
    }

    $arr['region_id'] = substr($arr['region_id'], 0, -1);

    if (!empty($arr['region_id'])) {
        $sql = "select count(*) from " . $GLOBALS['ecs']->table('region_warehouse') . " where parent_id in(" . $arr['region_id'] . ")";
        $count = $GLOBALS['db']->getOne($sql);
    } else {
        $count = 0;
    }

    return $count;
}

//查询仓库地区列表
function get_warehouse_area_list($warehouse_id = 0, $type = 0, $goods_id = 0, $ru_id = 0) {

    $where = '';
    if($type){
        $where = " AND (SELECT wag.a_id FROM " .$GLOBALS['ecs']->table('warehouse_area_goods'). " AS wag WHERE rw.region_id = wag.region_id AND wag.user_id = '$ru_id' AND wag.goods_id = '$goods_id' LIMIT 1) IS NULL";
    }

    $sql = "SELECT rw.region_id, rw.region_name FROM " . $GLOBALS['ecs']->table('region_warehouse') ." AS rw". " WHERE rw.parent_id = '$warehouse_id' $where";
    return $GLOBALS['db']->getAll($sql);
}

//查询地区ID和名称
function get_area_info($province_id = 0, $type = 0) {

    if($type == 1){
        $region_id = $province_id;
        $where = "WHERE region_id = '$region_id'";
    }else{
        $where = "WHERE regionId = '$province_id'";
    }

    $sql = "SELECT region_id, regionId, region_name, parent_id FROM " . $GLOBALS['ecs']->table('region_warehouse') . " $where LIMIT 1";
    $row = $GLOBALS['db']->getRow($sql);

    if($type == 1 && $row && $row['parent_id'] != 0){
        $sql = "SELECT region_name FROM " . $GLOBALS['ecs']->table('region_warehouse') . " WHERE region_id = '" .$row['parent_id']. "' LIMIT 1";
        $warehouse_name = $GLOBALS['db']->getOne($sql, true);

        $row['area_name'] = $row['region_name'];
        $row['region_name'] = $warehouse_name;
    }

    return $row;
}

//操作新数组attr_id --应用后台 start
function get_new_goods_attribute($goods_id, $_attribute = array()) {

    $arr = array();
    foreach ($_attribute as $key => $row) {
        $arr[$key] = $row;
        $arr[$key]['attr_valuesId'] = get_goods_attr_values_id($row['attr_values'], $row['goods_attr_id']);
        $arr[$key]['goods_attr'] = get_attribute_goods_attr($row['attr_id']);
        $arr[$key]['goods_attr'] = product_list($goods_id, '', $arr[$key]['goods_attr']['goods_attr_id']);
    }

    return $arr;
}

function get_attribute_goods_attr($attr_id = 0) {
    $sql = "select goods_attr_id from " . $GLOBALS['ecs']->table('goods_attr') . " where attr_id = '$attr_id'";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $key => $row) {
        $arr[$key] = $row;
        $arr['goods_attr_id'] .= $row['goods_attr_id'] . ",";
    }

    if (!empty($arr['goods_attr_id'])) {
        $arr['goods_attr_id'] = substr($arr['goods_attr_id'], 0, -1);
    }

    return $arr;
}

function get_goods_attr_values_id($attr_values = array(), $goods_attr_id = array()) {

    $arr = array();
    for ($i = 0; $i < count($attr_values); $i++) {
        $arr[$i]['attr_value'] = $attr_values[$i];
        $arr[$i]['goods_attr_id'] = $goods_attr_id[$i];
    }

    return $arr;
}

//操作新数组attr_id --应用后台 end

//获取所有的仓库地区列表
function get_fine_warehouse_area_all($parent_id = 0, $goods_id = 0, $goods_attr_id = 0) {

    $sql = "select region_id, region_name, parent_id from " . $GLOBALS['ecs']->table('region_warehouse') . " where parent_id = '$parent_id'";
    $res = $GLOBALS['db']->getAll($sql);

    $where = "";
    if(empty($goods_id)){
        $admin_id = get_admin_id();
        $where = " AND admin_id = '$admin_id'";
    }

    $arr = array();
    foreach ($res as $key => $row) {
        $arr[$key]['region_id'] = $row['region_id'];
        $arr[$key]['region_name'] = $row['region_name'];

        if ($row['parent_id'] == 0) {
            $arr[$key]['child'] = get_fine_warehouse_area_all($row['region_id'], $goods_id, $goods_attr_id);
        }

        $sql = "select * from " . $GLOBALS['ecs']->table('warehouse_area_attr') . " where goods_id = '$goods_id' and goods_attr_id = '$goods_attr_id' and area_id = '" . $row['region_id'] . "' $where LIMIT 1";
        $area_attr = $GLOBALS['db']->getRow($sql);

        $arr[$key]['area_attr'] = $area_attr;
    }

    return $arr;
}

//获取所有的仓库地区列表
function get_fine_warehouse_all($parent_id = 0, $goods_id = 0, $goods_attr_id = 0) {

    $where = "";
    if(empty($goods_id)){
        $admin_id = get_admin_id();
        $where = " AND wa.admin_id = '$admin_id'";
    }

    $sql = "SELECT rw.region_id, rw.region_name, wa.attr_price, wa.id FROM " . $GLOBALS['ecs']->table('region_warehouse') . " AS rw" .
            " LEFT JOIN " . $GLOBALS['ecs']->table('warehouse_attr') . " AS wa on rw.region_id = wa.warehouse_id AND wa.goods_id = '$goods_id' AND wa.goods_attr_id = '$goods_attr_id' $where" .
            " WHERE rw.parent_id = '$parent_id'";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $key => $row) {
        $arr[$key]['goods_attr_id'] = $goods_attr_id;
        $arr[$key]['region_id'] = $row['region_id'];
        $arr[$key]['region_name'] = $row['region_name'];
        $arr[$key]['attr_price'] = $row['attr_price'];
        $arr[$key]['id'] = $row['id'];
    }

    return $arr;
}

//查询该地区商品已在进货单中N件
function get_goods_cart_num($goods_id = 0, $warehouse_id = 0) {

    if (!empty($_SESSION['user_id'])) {
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
    }

    $sql = "SELECT goods_number FROM " . $GLOBALS['ecs']->table('cart') .
            " WHERE goods_id = '$goods_id' AND warehouse_id = '$warehouse_id' AND " . $sess_id;
    return $GLOBALS['db']->getOne($sql);
}

//仓库 end

//订单分主订单和从订单 start
/**
 * 得到新订单号
 * @return  string
 */
function get_order_child_sn($order_id = 0 , $ru_id = 0)
{
    $time = explode ( " ", microtime () );
    $time = $time[1] . ($time[0] * 1000);
    $time = explode ( ".", $time);
    $time = isset($time[1]) ? $time[1] : 0;
    $time = local_date('YmdHis') + $time;

    /* 选择一个随机的方案 */
    mt_srand((double) microtime() * 1000000);
    $time = $time . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

    if($_SESSION['order_done_sn'] == $time){
        $time += 1;
    }

    return $time;
}

//获取主订单信息
function get_main_order_info($order_id = 0, $type = 0) {
    $sql = "select * from " . $GLOBALS['ecs']->table('order_info') . " where order_id = '$order_id'";
    $row = $GLOBALS['db']->getRow($sql);

    if ($type == 1) {
        $row['all_ruId'] = get_main_order_goods_info($order_id, 1); //订单中所有商品所属商家ID,0代表自营商品，其它商家商品
        $order_goods = get_main_order_goods_info($order_id);
        $order_goods_count = count(array_unique(array_column($order_goods, 'goods_id')));
        $row['order_goods_goods'] = $order_goods_count;
        $ru_id = explode(",", $row['all_ruId']['ru_id']);
        if ($order_goods_count > 1) {
            $row['order_goods'] = get_main_order_goods_info($order_id);
            $row['newInfo'] = get_new_ru_goods_info($row['all_ruId'], $row['order_goods']);
            $row['newOrder'] = get_new_order_info($row['newInfo']);
            $row['orderBonus'] = get_new_order_info($row['newInfo'], 1, $row['bonus_id']); //处理商家分单红包
            $row['orderFavourable'] = get_new_order_info($row['newInfo'], 2); //处理商家分单优惠活动
        }
    }

    return $row;
}

//获取订单信息--或者--订单中所有商品所属商家ID,0代表自营商品，其它商家商品
function get_main_order_goods_info($order_id = 0, $type = 0) { //is_shipping
    $sql = "SELECT og.*, g.goods_weight as goodsweight, g.is_shipping FROM " . $GLOBALS['ecs']->table('order_goods') . " as og, " .
            $GLOBALS['ecs']->table('goods') . " as g" . " WHERE og.goods_id = g.goods_id AND og.order_id = '$order_id'";

    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    if ($type == 1) {
        $arr['ru_id'] = '';
    }
    foreach ($res as $key => $row) {

        $sql = "SELECT shipping_type FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '" . $row['order_id'] . "'";
        $row['shipping_type'] = $GLOBALS['db']->getOne($sql, true);

        if ($type == 0) {
            $arr[] = $row;
        } else {
            $arr['ru_id'] .= $row['ru_id'] . ',';
        }
    }

    if ($type == 1) {
        $arr['ru_id'] = explode(',', substr($arr['ru_id'], 0, -1));
        $arr['ru_id'] = array_unique($arr['ru_id']);
        $arr['ru_id'] = implode(',', $arr['ru_id']);
    }

    return $arr;
}

//主次订单拆分新数组
function get_new_ru_goods_info($all_ruId = '', $order_goods = array()) {
    $all_ruId = $all_ruId['ru_id'];
    $arr = array();

    if (!empty($all_ruId)) {
        $all_ruId = explode(',', $all_ruId);
        $all_ruId = array_values($all_ruId);
    }
    if ($all_ruId) {
        for ($i = 0; $i < count($order_goods); $i++) {
            $goods_id = $order_goods[$i]['goods_id'];
//            for ($j = 0; $j < count($all_ruId); $j++) {
//                if ($order_goods[$i]['ru_id'] == $all_ruId[$j]) {
                    $arr[$goods_id][$i] = $order_goods[$i];
//                }
//            }
        }
    }

    return $arr;
}

//运算分单后台每个订单商品总金额以及划分红包类型使用所属商家
function get_new_order_info($newInfo, $type = 0, $bonus_id = 0) {

    $arr = array();

    if ($type == 0) {
        foreach ($newInfo as $key => $row) {
            $arr[$key]['goods_amount'] = 0;
            $arr[$key]['shopping_fee'] = 0;
            $arr[$key]['goods_id'] = 0;

            $arr[$key]['ru_list'] = get_cart_goods_combined_freight($row, 2, '', $key); //计算商家运费

            $row = array_values($row);
            for ($j = 0; $j < count($row); $j++) {
                $arr[$key]['goods_id'] = $row[$j]['goods_id'];

                //ecmoban模板堂 --zhuo start 商品金额促销
                $goods_amount = $row[$j]['goods_price'] * $row[$j]['goods_number'];
                if ($goods_amount > 0) {
                    $goods_con = get_con_goods_amount($goods_amount, $row[$j]['goods_id'], 0, 0, $row[$j]['parent_id']);

                    $goods_con['amount'] = explode(',', $goods_con['amount']);
                    $amount = min($goods_con['amount']);

                    $arr[$key]['goods_amount'] += $amount;
                } else {
                    $arr[$key]['goods_amount'] += $row[$j]['goods_price'] * $row[$j]['goods_number']; //原始
                }

                $arr[$key]['shopping_fee'] = $arr[$key]['ru_list']['shipping_fee'];
                //ecmoban模板堂 --zhuo end 商品金额促销
            }
        }
    } elseif ($type == 1) { //红包
        foreach ($newInfo as $key => $row) {

            $arr[$key]['user_id'] = $key;
            $bonus = get_bonus_merchants($bonus_id, $key); //红包信息
            $arr[$key]['bonus'] = $bonus;
        }
    } elseif ($type == 2) { //优惠活动
        foreach ($newInfo as $key => $row) {
            $arr[$key]['user_id'] = $key;

            if ($key > 0) {
                $arr[$key]['compute_discount'] = compute_discount($type, $row, 1);
            } else {
                $arr[$key]['compute_discount'] = array('discount' => 0, 'name' => array());
            }
        }
    }

    return $arr;
}

/**
 * 分单插入数据
 * @param type $orderInfo---订单信息包含订单商品
 * @param type $row -- 纯订单信息
 * @param type $order_id
 */
function get_insert_order_goods_single($orderInfo, $row, $order_id, $ru_number) {
    $newOrder = $orderInfo['newOrder'];
    $orderBonus = $orderInfo['orderBonus'];
    $newInfo = $orderInfo['newInfo'];
    $orderFavourable = $orderInfo['orderFavourable'];
    $surplus = $row['surplus']; //余额
    $integral_money = $row['integral_money']; //积分
    $shipping_fee = $row['shipping_fee']; //运费
    $use_bonus = 0;
    $discount = $row['discount']; //折扣金额
    $commonuse_discount = get_single_order_fav($discount, $orderFavourable, 1); //全场通用折扣金额
    $discount_child = 0;
    $residue_integral = 0;
    $bonus_id = $row['bonus_id']; //红包ID
    $bonus = $row['bonus']; //红包金额
    $coupons = $row['coupons']; //红包金额

    $usebonus_type = get_bonus_all_goods($bonus_id); //全场通用红包 val:1

    $shipping_id = $row['shipping_id'];
    $shipping_name = $row['shipping_name'];
    $shipping_code = $row['shipping_code'];
    $shipping_type = $row['shipping_type'];

    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    $arr = array();
    $sms_send = array();
    $i = 0;


    foreach ($newInfo as $key => $info) {
        $first = array_first($info);
        $ru_id = $first['ru_id'];
        $i +=1;
        $arr[$key] = $info;

        $row['extension_code'] = $first['extension_code'];
        $row['extension_id'] = $first['extension_id'];

        $shipping = get_seller_shipping_order($ru_id, $shipping_id, $shipping_name, $shipping_code, $shipping_type);

        $row['shipping_id'] = $shipping['shipping_id'];
        $row['shipping_name'] = $shipping['shipping_name'];
        $row['shipping_code'] = $shipping['shipping_code'];
        $row['shipping_type'] = $shipping['shipping_type'];

        // 插入订单表 start
        $error_no = 0;
        do {

            $row['order_sn'] = get_order_child_sn($order_id, $ru_id); //获取新订单号
            $_SESSION['order_done_sn'] = $row['order_sn'];

            $row['main_order_id'] = $order_id; //获取主订单ID
            $row['goods_amount'] = $newOrder[$key]['goods_amount']; //商品总金额
            //折扣 start
            if ($commonuse_discount['has_terrace'] == 1) {
                if ($ru_id == 0) { //优惠活动全场通用折扣金额算入平台
                    $row['discount'] = $commonuse_discount['discount']; //全场通用折扣金额
                } else {
                    $row['discount'] = $orderFavourable[$key]['compute_discount']['discount']; //全场通用折扣金额
                }
            } else {
                $row['discount'] = $orderFavourable[$key]['compute_discount']['discount'] + $commonuse_discount['discount']; //折扣金额
                $commonuse_discount['discount'] = 0;
            }
            //折扣 end

            /* 优惠券 */
            $order_coupons = get_user_order_coupons($order_id, $ru_id, 1);
            if($order_coupons){
                $row['coupons'] = $coupons;
            }else{
                $row['coupons'] = 0;
            }

            //获取默认运费模式运费 by wu start
            $row['shipping_fee'] = 0;
            $sellerOrderInfo = array();
            $sellerOrderInfo['ru_id'] = $ru_id;
            $sellerOrderInfo['weight'] = 0;
            $sellerOrderInfo['goods_price'] = 0;
            $sellerOrderInfo['number'] = 0;
            $sellerOrderInfo['region'] = array($row['country'], $row['province'], $row['city'], $row['district'], $row['street']);
            $sellerOrderInfo['shipping_id'] = $row['shipping_id'];

            if (!empty($newOrder[$key]['ru_list'])) {
                foreach ($newOrder[$key]['ru_list'] as $k => $v) {
                    if (isset($v['order_id'])) {
                        $sellerOrderInfo['weight'] += floatval($v['weight']);
                        $sellerOrderInfo['goods_price'] += floatval($v['goods_price']);
                        $sellerOrderInfo['number'] += intval($v['number']);
                    }
                }

                $row['shipping_fee'] = getSellerShippingFee($sellerOrderInfo, $arr[$key]);
            }

            $coupons = array();
            if (isset($row['uc_id']) && !empty($row['uc_id'])) {
                $coupons = get_coupons($row['uc_id'], array('c.cou_id', 'c.cou_man', 'c.cou_type', 'c.ru_id', 'c.cou_money', 'cu.uc_id'));
            }

            /* 优惠券 免邮 start */
            if (!empty($coupons) && $ru_id == $coupons['ru_id']) {
                if ($coupons['cou_type'] == 5) {
                    if ($newOrder[$key]['goods_amount'] >= $coupons['cou_man'] || $coupons['cou_man'] == 0) {

                        $cou_region = get_coupons_region($coupons['cou_id']);
                        $cou_region = !empty($cou_region) ? explode(",", $cou_region) : array();
                        if ($cou_region) {
                            if (!in_array($row['province'], $cou_region)) {
                                $row['shipping_fee'] = 0;
                            }
                        } else {
                            $row['shipping_fee'] = 0;
                        }
                    }
                }
            }
            /* 优惠券 免邮 end */

            //获取默认运费模式运费 by wu end
            $row['order_amount'] = $newOrder[$key]['goods_amount'] + $row['shipping_fee']; //订单应付金额

            /* 税额 */
            $row['tax'] = get_order_invoice_total($row['goods_amount'], $row['inv_content']);
            $row['order_amount'] = $row['order_amount'] + $row['tax'];

            //减去优惠券金额 start
            if($row['coupons'] > 0){
                if($row['order_amount'] >= $row['coupons']){
                    $row['order_amount'] -= $row['coupons'];
                }else{
                    $row['coupons'] = $row['order_amount'];
                    $row['order_amount'] = 0;
                }
            }
            //减去优惠券金额 end

            //规避折扣之后订单金额为负数
            if ($commonuse_discount['has_terrace'] == 0) {

                if ($discount_child > 0) {
                    $row['discount'] += $discount_child;
                }
                if ($row['discount'] > 0) {
                    if ($row['order_amount'] > $row['discount']) {
                        $row['order_amount'] -= $row['discount'];
                    } else {
                        $discount_child = $row['discount'] - $row['order_amount']; //剩余折扣金额
                        $row['discount'] = $row['order_amount'];
                        $row['order_amount'] = 0;
                    }
                }
            } else {
                $row['order_amount'] -= $row['discount'];
            }

            // 减去红包 start
            if ($usebonus_type == 1) {
                if ($bonus > 0) {
                    if ($row['order_amount'] >= $bonus) {
                        $row['order_amount'] = $row['order_amount'] - $bonus;
                        $row['bonus'] = $bonus;
                        $bonus = 0;
                    } else {
                        $bonus = $bonus - $row['order_amount'];
                        $row['bonus'] = $row['order_amount'];
                        $row['order_amount'] = 0;
                    }

                    $row['bonus_id'] = $bonus_id;
                } else {
                    $row['bonus'] = 0;
                    $row['bonus_id'] = 0;
                }
            } else {
                if (isset($orderBonus[$key]['bonus']['type_money'])) {
                    $use_bonus = min($orderBonus[$key]['bonus']['type_money'], $row['order_amount']); // 实际减去的红包金额
                    $row['order_amount'] -= $use_bonus;
                    $row['bonus'] = $orderBonus[$key]['bonus']['type_money'];
                    $row['bonus_id'] = $row['bonus_id'];
                } else {
                    $row['bonus'] = 0;
                    $row['bonus_id'] = 0;
                }
            }

            // 减去红包 end
            //余额 start
            if ($surplus > 0) {
                if ($row['extension_code'] == 'presale') {
                    $presale = presale_info($row['extension_id']);

                    if ($presale['deposit'] >= 0) {
                        $order_amount = 0;
                        foreach ($info as $g_k => $goods) {
                            $order_amount += $goods['goods_number']  * $presale['deposit'];
                        }
                    } else {
                        $order_amount = $row['order_amount'];
                    }
                } else {
                    $order_amount = $row['order_amount'];
                }

                if ($surplus >= $order_amount) {
                    $surplus = $surplus - $order_amount;
                    $row['surplus'] = $order_amount; //订单金额等于当前使用余额
                    if ($order_amount == $row['order_amount']) {
                        $row['order_amount'] = 0;
                    } else {
                        $row['order_amount'] = $row['order_amount'] - $order_amount;
                    }

                } else {
                    $row['order_amount'] = $order_amount - $surplus;
                    $row['surplus'] = $surplus;
                    $surplus = 0;
                }
            } else {
                $row['surplus'] = 0;
            }

            //余额 end
            //积分 start by kong
            if (false) {
                //禁用积分
                if ($integral_money > 0) {
                    if ($i < $ru_number) {
                        $integral_ratio = get_integral_ratio($order_id, $info); //子订单商品可用积分比例
                        $row['integral_money'] = round($integral_money * $integral_ratio, 2);
                        $row['integral'] = $integral_money * $integral_ratio;
                        $row['order_amount'] = $row['order_amount'] - (round($integral_money * $integral_ratio, 2));
                        $residue_integral += $integral_money * $integral_ratio;
                    } else {
                        $row['integral'] = $integral_money - $residue_integral;
                        $row['integral_money'] = round($row['integral'], 2);
                        $row['order_amount'] = $row['order_amount'] - (round($row['integral'], 2));
                    }
                } else {
                    $row['integral_money'] = 0;
                    $row['integral'] = 0;
                }

                $row['integral'] = intval(integral_of_value($row['integral'])); //转换积分
                //积分 end
            }


            $row['order_amount'] = number_format($row['order_amount'], 2, '.', ''); //格式化价格为一个数字


            /* 如果订单金额为0（使用余额或积分或红包支付），修改订单状态为已确认、已付款 */
            if ($row['extension_code'] == 'presale') {
                if ($row['order_amount'] <= 0) {
                    //预定订单 尾款为0  直接已支付
                    $row['order_status'] = OS_CONFIRMED;
                    $row['confirm_time'] = gmtime();
                    $row['pay_status'] = PS_PAYED;
                    $row['pay_time'] = gmtime();
                } else {
                    //预定订单 尾款不为0
                    if ($row['surplus'] > 0) {
                        //已支付定金
                        $row['pay_status'] = PS_PAYED_PART; //部分付款
                        $row['order_status'] = OS_CONFIRMED; //已确认
                    } else {
                        $row['order_status'] = 0;
                        $row['confirm_time'] = 0;
                        $row['pay_status'] = 0;
                        $row['pay_time'] = 0;
                    }
                }
            } else {
                if ($row['order_amount'] <= 0) {
                    $row['order_status'] = OS_CONFIRMED;
                    $row['confirm_time'] = gmtime();
                    $row['pay_status'] = PS_PAYED;
                    $row['pay_time'] = gmtime();

                } else {
                    $row['order_status'] = 0;
                    $row['confirm_time'] = 0;
                    $row['pay_status'] = 0;
                    $row['pay_time'] = 0;
                }

            }
//            if ($row['order_amount'] <= 0) {
//                if ($row['extension_code'] == 'presale') {
//                    $row['order_status'] = PS_PAYED_PART;
//                    $row['pay_status'] = OS_CONFIRMED;
//                } else {
//                    $row['order_status'] = OS_CONFIRMED;
//                    $row['confirm_time'] = gmtime();
//                    $row['pay_status'] = PS_PAYED;
//                    $row['pay_time'] = gmtime();
//                }
//
//            } else {
//                $row['order_status'] = 0;
//                $row['confirm_time'] = 0;
//                $row['pay_status'] = 0;
//                $row['pay_time'] = 0;
//            }


            unset($row['order_id']);
            //商家---剔除自提点信息
            if ($row['shipping_code'] != 'cac') {
                $row['point_id'] = 0;
                $row['shipping_dateStr'] = '';
            }

            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_info'), $row, 'INSERT');
            $new_orderId = $GLOBALS['db']->insert_id();

            $error_no = $GLOBALS['db']->errno();

            if ($error_no > 0 && $error_no != 1062) {
                die($GLOBALS['db']->errorMsg());
            }

            /* 如果需要，发短信 */
            if ($key == 0) {
                $sms_shop_mobile = $GLOBALS['_CFG']['sms_shop_mobile']; //手机
            } else {
                $sql = "SELECT mobile FROM " . $GLOBALS['ecs']->table('seller_shopinfo') . " WHERE ru_id = '$key'";
                $sms_shop_mobile = $GLOBALS['db']->getOne($sql); //手机
                $sql = "SELECT seller_email FROM " . $GLOBALS['ecs']->table('seller_shopinfo') . " WHERE ru_id = '$key'";
            }

            //是否开启下单自动发短信、邮件 by wu start
            $sql = " select * from " . $GLOBALS['ecs']->table('crons') . " where cron_code='auto_sms' and enable=1 LIMIT 1";
            $auto_sms = $GLOBALS['db']->getRow($sql);

            /* 给商家发短信 */
            if ($GLOBALS['_CFG']['sms_order_placed'] == '1' && $sms_shop_mobile != '') {
                if (!empty($auto_sms)) {
                    $sql = " INSERT INTO " . $GLOBALS['ecs']->table('auto_sms') . " (item_id,item_type,user_id,ru_id,order_id,add_time) " .
                            " VALUES " .
                            "(NULL,1,'" . $row['user_id'] . "','" . $key . "','" . $new_orderId . "','" . time() . "')";
                    $GLOBALS['db']->query($sql);
                } else {

                    $shop_name = get_shop_name($key, 1);
                    $order_region = get_flow_user_region($new_orderId);
                    //阿里大鱼短信接口参数
                    $smsParams = array(
                        'shop_name' => $shop_name,
                        'shopname' => $shop_name,
                        'order_sn' => $row['order_sn'],
                        'ordersn' => $row['order_sn'],
                        'consignee' => $row['consignee'],
                        'order_region' => $order_region,
                        'orderregion' => $order_region,
                        'address' => $row['address'],
                        'order_mobile' => $row['mobile'],
                        'ordermobile' => $row['mobile'],
                        'mobile_phone' => $sms_shop_mobile,
                        'mobilephone' => $sms_shop_mobile
                    );

                    if ($GLOBALS['_CFG']['sms_type'] == 0) {

                        huyi_sms($smsParams, 'sms_order_placed');

                    } elseif ($GLOBALS['_CFG']['sms_type'] >=1) {

                        $result = sms_ali($smsParams, 'sms_order_placed'); //阿里大鱼短信变量传值，发送时机传值
                        $sms_send[$key] = $result;
                    }
                }
            }
        } while ($error_no == 1062); //如果是订单号重复则重新提交数据

        $arr[$key] = array_values($arr[$key]);
        for ($j = 0; $j < count($arr[$key]); $j++) {
            $arr[$key][$j]['order_id'] = $new_orderId;
            unset($arr[$key][$j]['rec_id']);
            $arr[$key][$j]['goods_name'] = addslashes($arr[$key][$j]['goods_name']);
            $arr[$key][$j]['goods_attr'] = addslashes($arr[$key][$j]['goods_attr']);
            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_goods'), $arr[$key][$j], 'INSERT');
        }

        /* 插入支付日志 by wanganlin */
        $row['log_id'] = insert_pay_log($new_orderId, $row['order_amount'], PAY_ORDER);
    }

    if ($GLOBALS['_CFG']['sms_type'] >=1) {
        $resp = $GLOBALS['ecs']->ali_yu($sms_send, 1);
    }
}

/*获取子订单积分比例 by kong*/
function get_integral_ratio($order_id = 0,$info=array()){
    // 获取订单商品总共可用积分
    $count_goods_integral = get_integral($order_id);
    $goods_id = array();
    if(!empty($info)){
        foreach($info as $v){
            $goods_id[] = $v['goods_id'];
        }
    }

    /*获取分单商品总共可用积分*/
    $chlid_goods_integral = get_integral($order_id,$goods_id);
    $integral_ratio = $chlid_goods_integral/$count_goods_integral;

    return $integral_ratio;
}

/*获取指定订单，订单商品总共可用积分 by kong*/
function get_integral($order_id = 0,$goods_id=array()){

    $where= '' ;
    if(!empty($goods_id)){
       $where = "AND og.goods_id ".db_create_in($goods_id);
    }

    $sql="SELECT g.integral*og.goods_number as integral FROM".$GLOBALS['ecs']->table('goods')." AS g "
            . "LEFT JOIN ".$GLOBALS['ecs']->table('order_goods')." AS og ON g.goods_id = og.goods_id WHERE og.order_id='$order_id'".$where;
    $rel =  $GLOBALS['db']->getAll($sql);

    $count = 0;
    foreach($rel as $v){
        $count += $v['integral'];
    }

    return $count;
}

//商家配送方式分单分组
function get_seller_shipping_order($ru_id = array(), $shipping_id = array(), $shipping_name = array(), $shipping_code = array(), $shipping_type = array()){
    $shipping_id = explode(',', $shipping_id);
    $shipping_name = explode(',', $shipping_name);
    $shipping_code = explode(',', $shipping_code);
    $shipping_type = explode(',', $shipping_type);

    $shippingId = '';
    $shippingName = '';
    $shippingCode = '';
    $shippingType = '';

    foreach($shipping_id as $key=>$row){
        $row = explode('|', $row);
        if($row[0] == $ru_id){
            $shippingId = $row[1];
        }
    }

    foreach($shipping_name as $key=>$row){
        $row = explode('|', $row);
        if($row[0] == $ru_id){
            $shippingName = $row[1];
        }
    }

    if($shipping_code){
        foreach($shipping_code as $key=>$row){
            $row = explode('|', $row);
            if($row[0] == $ru_id){
                $shippingCode = $row[1];
            }
        }
    }

    if($shipping_type){
        foreach($shipping_type as $key=>$row){
            $row = explode('|', $row);
            if($row[0] == $ru_id){
                $shippingType = $row[1];
            }
        }
    }

    $shipping = array('shipping_id' => $shippingId, 'shipping_name' => $shippingName, 'shipping_code' => $shippingCode, 'shipping_type' => $shippingType);
    return $shipping;
}

//查询订单中所使用的红包等归属信息，所属商家(ID : bt.user_id)
function get_bonus_merchants($bonus_id = 0, $user_id = 0) {
    $sql = "select bt.user_id, bt.type_money from " . $GLOBALS['ecs']->table('user_bonus') . " as ub" .
            " left join " . $GLOBALS['ecs']->table('bonus_type') . " as bt on ub.bonus_type_id = bt.type_id" .
            " where ub.bonus_id = '$bonus_id' and bt.user_id = '$user_id'";

    return $GLOBALS['db']->getRow($sql);
}

//根据订单商品查询商品信息
function get_order_goods_toInfo($order_id = 0) {
    $sql = "SELECT og.goods_id, g.goods_name, g.goods_thumb, oi.extension_code as oi_extension_code, og.goods_number, og.goods_price, og.goods_price, og.extension_code as og_extension_code, og.goods_name AS extension_name, oi.order_sn FROM " . $GLOBALS['ecs']->table('order_goods') . " as og " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('order_info') . " AS oi ON og.order_id = oi.order_id " .
            "left join " . $GLOBALS['ecs']->table('goods') . " as g on og.goods_id = g.goods_id " .
            "left join " . $GLOBALS['ecs']->table('goods_activity') . " as ga on og.goods_id = ga.act_id AND ga.review_status = 3 " .
            "WHERE og.order_id = '$order_id' group by og.rec_id order by g.goods_id";

    $res = $GLOBALS['db']->getAll($sql);
    $arr = array();
    foreach ($res as $key => $row) {

        $arr[$key]['goods_id'] = $row['goods_id'];
        if ($row['og_extension_code'] == 'package_buy') {
            $row['goods_name'] = $row['extension_name'];
			$activity = get_goods_activity_info($row['goods_id'], array('act_id', 'activity_thumb'));
            if($activity){
                $row['goods_thumb'] = $activity['activity_thumb'];
            }
        }
        $arr[$key]['goods_name'] = $row['goods_name'];
        $arr[$key]['goods_number'] = $row['goods_number'];
        $arr[$key]['og_extension_code'] = $row['og_extension_code'];
        $arr[$key]['goods_price'] = price_format($row['goods_price'], false);
        $arr[$key]['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
		$extension_id = $GLOBALS['db']->getOne("SELECT extension_id FROM ". $GLOBALS['ecs']->table('order_info') ." WHERE order_id = '$order_id'" );
        if($row['og_extension_code'] == 'presale'){
			$arr[$key]['url'] = build_uri('presale', array('act' => 'view', 'presaleid' => $extension_id), $row['goods_name']);
		}elseif($row['oi_extension_code'] == 'group_buy'){
			$arr[$key]['url'] = build_uri('group_buy', array('gbid'=>$extension_id));
		}elseif($row['oi_extension_code'] == 'snatch'){
			$arr[$key]['url'] = build_uri('snatch', array('sid'=>$extension_id));
		}elseif($row['oi_extension_code'] == 'seckill'){
			$arr[$key]['url'] =	build_uri('seckill', array('act' => "view", 'secid' => $extension_id));
		}elseif($row['oi_extension_code'] == 'auction'){
			$arr[$key]['url'] =	build_uri('auction', array('auid'=>$extension_id));
		}elseif($row['oi_extension_code'] == 'exchange_goods'){
			$arr[$key]['url'] = build_uri('exchange_goods', array('gid'=>$extension_id));
		} elseif($row['oi_extension_code'] == 'wholesale'){
            $arr[$key]['url'] = build_uri('wholesale2', array('gbid'=>$extension_id));
        } elseif($row['oi_extension_code'] == 'sample'){
            //by zxk 样品订单
            $arr[$key]['url'] = build_uri('sample', array('acid'=>$extension_id, 'act' => "view"));
        }
		else{
			$arr[$key]['url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
		}


        $arr[$key]['trade_id'] = find_snapshot($row['order_sn'], $row['goods_id']);
        //超值礼包图片
        if ($row['og_extension_code'] == 'package_buy') {
            $activity = get_goods_activity_info($row['goods_id'], array('act_id', 'activity_thumb'));

            if ($activity) {
                $row['goods_thumb'] = $activity['activity_thumb'];
                //$row['package_goods_list'] = get_package_goods($activity['act_id']);
                $arr[$key]['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
            }
        }
    }

    return $arr;
}

//查询订单分单信息
function get_child_order_info($order_id) {
    $sql = "SELECT order_sn, order_amount, shipping_fee, order_id, shipping_name, money_paid, surplus FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE main_order_id = '$order_id'";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $key => $row) {
        $arr[$key]['order_sn'] = $row['order_sn'];
        $arr[$key]['order_id'] = $row['order_id'];
        $arr[$key]['shipping_name'] = $row['shipping_name'];

        $arr[$key]['order_amount'] = $row['order_amount'];
        $arr[$key]['amount_formated'] = price_format($row['order_amount'], false);
        $arr[$key]['shipping_fee_formated'] = price_format($row['shipping_fee'], false);

        $arr[$key]['pay_total'] = $row['money_paid'] + $row['surplus'];
        $arr[$key]['total_formated'] = price_format($row['money_paid'] + $row['surplus'], false);
    }

    return $arr;
}

//订单分主订单和从订单 end

//获取列表商家
function get_merchants_user_list() {
    $sql = "select msi.* from " . $GLOBALS['ecs']->table('merchants_shop_information') . " as msi" . " where 1";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $key => $row) {
        $arr[$key] = $row;

        $date = array('user_name');
        $user_name = get_table_date('users', "user_id = '" . $row['user_id'] . "'", $date, 2);
        $arr[$key]['user_name'] = $user_name;
    }

    return $arr;
}

//区域划分 start
function get_region_area_divide() {
    $sql = "select ra_id, ra_name from " . $GLOBALS['ecs']->table('merchants_region_area') . " where 1 order by ra_sort asc";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $key => $row) {
        $arr[$key] = $row;
        $arr[$key]['area_list'] = get_to_area_list($row['ra_id']);
    }

    return $arr;
}

function get_to_area_list($ra_id = 0) {
    $sql = "select ra_id, region_id from " . $GLOBALS['ecs']->table('merchants_region_info') . " where ra_id = '$ra_id' order by region_id asc";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $key => $row) {
        $arr[$key] = $row;
        $date = array('region_name');
        $arr[$key]['region_name'] = get_table_date('region', "region_id = '" . $row['region_id'] . "'", $date, 2);
    }

    return $arr;
}

//区域划分 end

//独立店铺 start

//店铺导航
function get_user_store_category($ru_id) {
    $sql = "select cat_id, cat_name, sort_order as vieworder from " . $GLOBALS['ecs']->table('merchants_category') .
            " where user_id = '$ru_id' and is_show = 1 AND show_in_nav = 1";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $key => $row) {
        $arr[$key] = $row;

        $build_uri = array(
            'cid' => $row['cat_id'],
            'urid' => $ru_id,
            'append' => $row['cat_name']
        );

        $domain_url = get_seller_domain_url($ru_id, $build_uri);
        $arr[$key]['url'] = $domain_url['domain_name'];
        $arr[$key]['opennew'] = 0;
    }

    $navigator_list = get_merchants_navigator($ru_id);
    $arr = array_merge($navigator_list['middle'], $arr);

    return $arr;
}

function get_store_category_child($parent_id, $ru_id) {
    $sql = "select c.cat_id, c.cat_name from " . $GLOBALS['ecs']->table('merchants_category') . " as mc " .
            " left join " . $GLOBALS['ecs']->table('category') . " as c on mc.cat_id = c.cat_id " .
            " where c.parent_id = '$parent_id' and mc.user_id = '$ru_id' and mc.is_show";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $key => $row) {
        $arr[$key]['cat_id'] = $row['cat_id'];
        $arr[$key]['cat_name'] = $row['cat_name'];
        $arr[$key]['url'] = build_uri('merchants_store', array('cid' => $row['cat_id'], 'urid' => $ru_id), $row['cat_name']);
        $arr[$key]['child'] = get_store_category_child($row['cat_id']);
    }

    return $arr;
}

//独立店铺 end

/**
 * 商品销量
 * 不建议使用
 */
function selled_count($goods_id, $type = '') {

    if (!empty($type)) {
        $where = " AND og.order_id = oi.order_id and oi.extension_code = '$type'";
    } else {
        $where = " AND og.order_id = oi.order_id ";
    }

    $where .= "AND (oi.order_status = '" . OS_CONFIRMED . "' OR oi.order_status = '" . OS_SPLITED . "') " .
            "AND (oi.pay_status = '" . PS_PAYED . "' OR oi.pay_status = '" . PS_PAYING . "') " .
            "AND (oi.shipping_status = '" . SS_SHIPPED . "' OR oi.shipping_status = '" . SS_RECEIVED . "')";

    $where .= " group by g.goods_id";

    $sql = "select count(og.goods_number) as count from " . $GLOBALS['ecs']->table('order_goods') . " as og , " . $GLOBALS['ecs']->table('goods') . " as g , " . $GLOBALS['ecs']->table('order_info') . " as oi " .
            " where og.goods_id = g.goods_id and og.goods_id ='" . $goods_id . "'" . $where;

    $res = $GLOBALS['db']->getOne($sql);
    if ($res > 0) {
        return $res;
    } else {
        return 0;
    }
}

//查询一级与二级分类
function get_oneTwo_category($parent_id = 0) {
    $sql = "select cat_id, cat_name from " . $GLOBALS['ecs']->table('category') . " where parent_id = '$parent_id'";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $key => $row) {
        $arr[$key] = $row;
        $arr[$key]['child'] = get_oneTwo_category($row['cat_id']);

        if (empty($arr[$key]['child'])) {
            unset($arr[$key]['child']);
        }
    }

    return $arr;
}

//通过地区ID查询地区名称
function get_order_region_name($region_id = 0) {
    $where = "region_id = '$region_id'";
    $date = array('region_name');
    $region = get_table_date('region', $where, $date);

    return $region;
}

//获取购物选择商品最终金额
function get_cart_check_goods($cart_goods, $rec_id = '', $type = 0){

    $arr['subtotal_discount'] = 0;
    $arr['subtotal_amount'] = 0;
    $arr['subtotal_number'] = 0;
    $arr['save_amount'] = 0;
    if(!empty($rec_id)){
        if($cart_goods){
            foreach($cart_goods as $row){
                $arr['subtotal_amount'] += $row['subtotal'];
                $arr['subtotal_number'] += $row['goods_number'];
                $arr['save_amount'] += $row['dis_amount'];
            }
        }
    }

    $arr['subtotal_amount'] = $arr['subtotal_amount'] - $arr['save_amount'];
    return $arr;
}

//弹出层数据
function get_all_area_list($parent_id = 0, $region_type = 1){

	$where = '';
	if($region_type > 1){
		$where .= " r.parent_id = '$parent_id' AND r.region_type = '$region_type'";
	}else{
		$where .= " rw.regionId > 0 and r.region_id = rw.regionId AND r.region_type = '$region_type'";
	}

	$sql = "SELECT r.region_id, r.region_name FROM " .$GLOBALS['ecs']->table('region_warehouse'). " as rw, " .
			$GLOBALS['ecs']->table('region') ." as r ". " WHERE " . $where . " GROUP BY r.region_id order by r.region_id ASC";

	$res = $GLOBALS['db']->getAll($sql);

	$arr = array();
	foreach($res as $key=>$row){
		$arr[$key]['region_id'] = $row['region_id'];
		$arr[$key]['region_name'] = $row['region_name'];
	}

	return $arr;
}

//ecmoban模板堂 --zhuo start 天猫属性

//获取商品区间价格的最小和最大值价格 start
function get_goods_minMax_price($goods_id = 0, $warehouse_id = 0, $area_id = 0, $goods_price, $market_price, $type = 1) {

    $model_attr = get_table_date("goods", "goods_id = '$goods_id'", array('model_attr'), 2);

    if ($model_attr == 1) { //仓库属性
        $where .= " AND wa.warehouse_id = '$warehouse_id'";
        $slelect = ', wa.attr_price as attr_price';

        $leftJoin = " LEFT JOIN " . $GLOBALS['ecs']->table('warehouse_attr') . " AS wa on ga.goods_attr_id = wa.goods_attr_id ";
    } elseif ($model_attr == 2) { //地区属性
        $where .= " AND waa.area_id = '$area_id'";
        $slelect = ', waa.attr_price as attr_price';

        $leftJoin = " LEFT JOIN " . $GLOBALS['ecs']->table('warehouse_area_attr') . " AS waa on ga.goods_attr_id = waa.goods_attr_id ";
    } elseif ($model_attr == 0) {
        $slelect = ', ga.attr_price as attr_price';
        $where = '';
        $leftJoin = '';
    }

    $sql = 'SELECT ga.attr_id ' . $slelect . ' FROM ' . $GLOBALS['ecs']->table('goods_attr') . " as ga " . $leftJoin . " WHERE ga.goods_id = '$goods_id' " . $where;
    $arr_res = $GLOBALS['db']->getAll($sql);

    $arr_k = array();
    if ($arr_res) {
        foreach ($arr_res as $val) {
            $arr_k .= $val['attr_id'] . '@';
        }
        $arr_k = rtrim($arr_k, '@');

        $k_res = explode('@', $arr_k);
        $k_res = array_flip(array_flip($k_res));
    }

    $new_arr = array();
    if ($k_res) {
        foreach ($k_res as $val) {
            foreach ($arr_res as $v) {
                if ($v['attr_id'] == $val) {
                    $new_arr[$val][] = $v['attr_price'];
                }
            }
        }
    }

    if ($type == 1) {
        $new_arr = get_unset_null_array($new_arr, 2);
    }

    $new_arr_res = array();
    if ($new_arr) {
        foreach ($new_arr as $k => $val) {
            $new_arr_res[$k]['max'] = $val[array_search(max($val), $val)];
            $new_arr_res[$k]['min'] = $val[array_search(min($val), $val)];
        }

        $num_res_max = 0;
        $num_res_min = 0;
        foreach ($new_arr_res as $val) {
            $num_res_max += $val['max'];
            $num_res_min += $val['min'];
        }
    }

    if($GLOBALS['_CFG']['add_shop_price'] == 0){
        $num_res_min = 0;
    }else{
        $num_res_max = 0;
    }

    if ($type == 1) { //商品组合购买
        $arr['goods_min'] = $goods_price + $num_res_min;
        $arr['goods_max'] = $goods_price + $num_res_max;

        $arr['market_min'] = $market_price + $num_res_min;
        $arr['market_max'] = $market_price + $num_res_max;
    } elseif ($type == 2) { //商品普通购买
        $goodsLeftJoin = '';
        $goodsLeftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
        $goodsLeftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

        $sql = 'SELECT ' .
                "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, " .
                "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, " .
                'g.promote_start_date, g.promote_end_date, g.is_promote ' .
                $goodsLeftJoin .
                'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
                "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
                ' FROM ' . $GLOBALS['ecs']->table('goods') . " as g " .
                "WHERE goods_id = '$goods_id'";

        $goods = $GLOBALS['db']->getRow($sql);

        if ($goods['promote_price'] > 0) {
            $promote_price = bargain_price($goods['promote_price'], $goods['promote_start_date'], $goods['promote_end_date']);
        } else {
            $promote_price = 0;
        }

        $promote_price = ($promote_price > 0) ? $promote_price : '';

        if (!empty($promote_price)) {
            $arr['promote_minPrice'] = price_format($promote_price + $num_res_min);
            $arr['promote_maxPrice'] = price_format($promote_price + $num_res_max);
        } else {
            $arr['promote_minPrice'] = $promote_price;
            $arr['promote_maxPrice'] = $promote_price;
        }

        $arr['shop_minPrice'] = price_format($goods['shop_price'] + $num_res_min);
        $arr['shop_maxPrice'] = price_format($goods['shop_price'] + $num_res_max);
    }

    return $arr;
}

//删掉值为0的数组
/*
 * 1 = 一维数组
 * 2 = 二维数组
*/
function get_unset_null_array($arr = array(), $type = 0) {

    $arr = array_values($arr);

    $new_arr = array();
    if ($arr && $type == 2) {
        for ($i = 0; $i < count($arr); $i++) {
            for ($j = 0; $j < count($arr[$i]); $j++) {
                if ($arr[$i][$j] > 0) {
                    $new_arr[$i][$j] = $arr[$i][$j];
                }
            }
        }
    } elseif ($arr && $type == 1) {
        for ($i = 0; $i < count($arr); $i++) {
            if ($arr[$i] > 0) {
                $new_arr[$i] = $arr[$i];
            }
        }
    }

    return $new_arr;
}

//查询已选择组合购买商品的区间价格
function get_choose_goods_combo_cart($fittings, $number = 1){
    $arr = array();

    $arr['fittings_min'] = 0;
    $arr['fittings_max'] = 0;
    $arr['market_min'] = 0;
    $arr['market_max'] = 0;
    $arr['save_price'] = '';
    $arr['collocation_number'] = 0;
    $arr['save_minPrice'] = 0;
    $arr['save_maxPrice'] = 0;
    $arr['fittings_price'] = 0;
    $arr['fittings_market_price'] = 0;
    $arr['save_price_amount'] = 0;
    $arr['groupId'] = 0;
    $arr['all_price_ori'] = 0;
    $arr['return_attr'] = 0;

    if($fittings){
        foreach($fittings as $key=>$row){
            $arr[$key]['goods_id']                      =	$row['goods_id'];
            $arr[$key]['market_price']                  =	$row['market_price'] + $row['attr_price']; //实际市场价
            $arr[$key]['fittings_minPrice']             =	$row['fittings_minPrice'];		//配件区间价格 min
            $arr[$key]['fittings_maxPrice']             =	$row['fittings_maxPrice'];		//配件区间价格 max
            $arr[$key]['market_minPrice'] 		=	$row['market_minPrice'];		//市场区间价格 min
            $arr[$key]['market_maxPrice'] 		=	$row['market_maxPrice'];		//市场区间价格 max
            $arr[$key]['shop_price_ori']                =	$row['shop_price_ori'];			//商品原价
            $arr[$key]['fittings_price_ori'] 		=	$row['fittings_price_ori'];		//配件价格
            $arr[$key]['attr_price']                    =	$row['attr_price'];			//配件商品属性金额
            $arr[$key]['spare_price_ori'] 		=	$row['spare_price_ori'];		//商品原价 - 配件价格 = 节省价
            $arr[$key]['group_id'] 			=	!empty($row['group_id']) ? $row['group_id'] : 0;				//组ID
            $arr[$key]['is_attr'] 			=	get_cart_combo_goods_product_list($row['goods_id']);

            if($GLOBALS['_CFG']['add_shop_price'] == 0){
                $row['attr_price'] = 0;
            }

            if($arr[$key]['group_id'] == 0){
                $arr[$key]['price_ori']     =	$row['shop_price_ori'] + $row['attr_price'];
            }else{
                $arr[$key]['price_ori']     =	$row['fittings_price_ori'] + $row['attr_price'];
            }

            $arr['save_price_amount'] += $row['spare_price_ori']; //配件商品节省总金额
            $arr['fittings_price'] += $arr[$key]['price_ori']; //配件商品总金额
            $arr['fittings_market_price'] += $row['market_price']; //配件商品市场价总金额

            $arr['save_price'] .= $row['spare_price_ori'] . ",";

            if(!empty($row['group_id'])){
                $arr['groupId'] .= $row['group_id'] . ",";
            }
        }

        $arr['collocation_number'] = count($fittings) - 1;

        $arr['save_price'] = substr($arr['save_price'], 0, -1);
        $arr['save_price'] = explode(',', $arr['save_price']);
        $arr['save_price'] = get_unset_null_array($arr['save_price'], 1);

        $arr['save_minPrice'] = min($arr['save_price']);
        $arr['save_maxPrice'] = get_save_maxPrice($arr['save_price']);

        $arr['groupId'] = substr($arr['groupId'], 1, -1);
        $arr['groupId'] = explode(',', $arr['groupId']);
        $arr['groupId'] = array_unique($arr['groupId']);
        $arr['groupId'] = implode(',', $arr['groupId']);

        $minmax_values = get_min_or_max_values($arr);

        $arr['fittings_min'] = $minmax_values['fittings_minPrice'];
        $arr['fittings_max'] = $minmax_values['fittings_maxPrice'];
        $arr['market_min'] = $minmax_values['market_minPrice'];
        $arr['market_max'] = $minmax_values['market_maxPrice'];

        $arr['return_attr'] = $minmax_values['return_attr']; //判断配件商品是否有属性
        $arr['all_price_ori'] = $minmax_values['all_price_ori'];
        $arr['all_market_price'] = $minmax_values['all_market_price'];
    }

    return $arr;
}

//获取数组里面最小值和最大值
function get_min_or_max_values($arr){

    $unsetStr = "fittings_min,fittings_max,market_min,market_max,save_price,collocation_number,save_minPrice,save_maxPrice,fittings_price,save_price_amount,groupId,all_price_ori,return_attr,fittings_market_price";
    $unsetStr = explode(',', $unsetStr);

    foreach($unsetStr as $str){
        unset($arr[$str]);
    }

    $newArr = array();
    $newArr['fittings_minPrice'] = '';
    $newArr['fittings_maxPrice'] = '';
    $newArr['market_minPrice'] = '';
    $newArr['market_maxPrice'] = '';
    $newArr['is_attr'] = '';
    $shop_price = 0;
    $market_price = 0;
    $newArr['all_price_ori'] = 0;
    $newArr['return_attr'] = 0;
    $newArr['all_market_price'] = 0;

    foreach ($arr as $key=>$row){
        if($key > 0){
            $newArr['all_price_ori']        += $row['price_ori'] . ',';
            $newArr['all_market_price']     += $row['market_minPrice'] . ',';
            $newArr['fittings_minPrice']    .= $row['fittings_minPrice'] . ',';
            $newArr['fittings_maxPrice']    .= $row['fittings_maxPrice'] . ',';
            $newArr['market_minPrice']      .= $row['market_minPrice'] . ',';
            $newArr['market_maxPrice']      .= $row['market_maxPrice'] . ',';
            $newArr['is_attr']              .= $row['is_attr'] . ',';
        }
    }

    $is_attr = explode(",", substr($newArr['is_attr'], 0, -1));

    foreach($is_attr as $key=>$row){
        $newArr['return_attr'] += $row;
    }

    $fittings_maxPrice = explode(",", substr($newArr['fittings_maxPrice'], 0, -1));
    $market_maxPrice = explode(",", substr($newArr['market_maxPrice'], 0, -1));

    foreach($fittings_maxPrice as $key=>$shop){
        $shop_price += $shop;
    }

    $newArr['fittings_maxPrice'] = $shop_price;

    foreach($market_maxPrice  as $key=>$market){
        $market_price += $market;
    }

    $newArr['market_maxPrice'] = $market_price;

    $newArr['fittings_minPrice'] = $arr[0]['fittings_minPrice'] + min(explode(",", substr($newArr['fittings_minPrice'], 0, -1)));
    $newArr['fittings_maxPrice'] = $arr[0]['fittings_maxPrice'] + $newArr['fittings_maxPrice'];
    $newArr['market_minPrice'] = $arr[0]['market_minPrice'] + min(explode(",", substr($newArr['market_minPrice'], 0, -1)));
    $newArr['market_maxPrice'] = $arr[0]['market_maxPrice'] + $newArr['market_maxPrice'];

    $newArr['all_price_ori'] = $arr[0]['price_ori'] + $newArr['all_price_ori']; //实际搭配价
    $newArr['all_market_price'] = $arr[0]['market_price'] + $arr[0]['attr_price'] + $newArr['all_market_price']; //实际搭配市场价

    return $newArr;
}

//查询组合购买里面的配件商品是否有货品
function get_cart_combo_goods_product_list($goods_id){

    $sql = "SELECT goods_attr_id, goods_id, attr_id FROM " .$GLOBALS['ecs']->table('goods_attr'). " WHERE goods_id = '$goods_id'";
    $attr_list = $GLOBALS['db']->getAll($sql);

    if($attr_list){ //当商品没有货品时
        return 1;
    }else{
        return 0;
    }
}

function get_save_maxPrice($save_price){
	$save_maxPrice = 0;
	if($save_price){
		foreach($save_price as $key=>$row){
			$save_maxPrice += $row;
		}
	}

	return $save_maxPrice;
}

//查询商品属性类型列表
function get_goods_attr_type_list($goods_id = 0, $type = 0){
	$sql = "select a.attr_id, a.attr_name from " .$GLOBALS['ecs']->table('goods_attr') ." as ga " .
				" left join " .$GLOBALS['ecs']->table('attribute'). " as a on ga.attr_id = a.attr_id " .
				" where goods_id = '$goods_id' group by a.attr_id ORDER BY a.sort_order, a.attr_id, ga.goods_attr_id";
	$attr_list = $GLOBALS['db']->getAll($sql);

	if($type == 1){
		$attr_list = count($attr_list);
	}

	return $attr_list;
}
//获取商品区间价格的最小和最大值价格 end

//查询订单是否红包全场通用
function get_bonus_all_goods($bonus_id){
    $sql = "SELECT t.usebonus_type FROM " .$GLOBALS['ecs']->table('bonus_type') ." as t, " .$GLOBALS['ecs']->table('user_bonus') ." as ub". " WHERE t.type_id = ub.bonus_type_id AND ub.bonus_id = '$bonus_id'";
    return $GLOBALS['db']->getOne($sql);
}

//数组字符串排序
function get_arr_str_key($arr = array()){

    $str = '';
    if($arr){
        $new_arr = array();
        foreach($arr as $key=>$row){
            $strlen =  mb_strlen($row, 'utf8');
            $new_arr[$key]['len'] = $strlen;
            $new_arr[$key]['val'] = $row;
        }

        $new_arr = get_array_sort($new_arr, 'len','desc');
    }

    return $str;
}

//查询商家设置运费方式
function get_seller_shipping_type($ru_id){
    $sql = "SELECT s.shipping_id, s.shipping_name, s.shipping_code FROM " .$GLOBALS['ecs']->table('seller_shopinfo') ." AS ss, ".
            $GLOBALS['ecs']->table('shipping') ." AS s".
            " WHERE ss.shipping_id = s.shipping_id AND ru_id = '$ru_id'";
    return $GLOBALS['db']->getRow($sql);
}

//获取所有城市信息 by wang
function get_city_region()
{
    $sql="SELECT `region_id`, `parent_id`, `region_name` FROM ".$GLOBALS['ecs']->table('region')." WHERE region_type = 2 AND parent_id > 0";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach($res as $key=>$row){
        $date = array('region_id');
        $where = "regionId = '" .$row['region_id']. "' AND region_type = 2";
        $region_id = get_table_date('region_warehouse', $where, $date, 2);

        if($region_id){
            $row['is_has'] = 1;
        }else{
            $row['is_has'] = 0;
        }

        $arr[$key] = $row;
    }

    return $arr;
}

function get_search_city_true($region_id){
    $date = array('region_id', 'parent_id', 'region_name');
    $where = "region_name = '" .$area_array['area_name']. "' AND region_type = 2";
    $city_info = get_table_date('region', $where, $date, 1);
}

//by wang获得推荐品牌信息
function get_recommend_brands($num = 0) {
    $where = ' where  b.is_show = 1 AND be.is_recommend=1  order by b.sort_order asc ';
    if ($num > 0) {
        $where.=" limit 0,$num";
    }
    $sql = "select b.* from " . $GLOBALS['ecs']->table('brand') . " as b left join " . $GLOBALS['ecs']->table('brand_extend') . " as be on b.brand_id=be.brand_id " . $where;

    return $GLOBALS['db']->getAll($sql);
}

//批量删除运费
function get_freight_batch_remove($id) {
    for ($i = 0; $i < count($id); $i++) {
        $sql = "delete from " . $GLOBALS['ecs']->table('warehouse_freight') . " where id = " . $id[$i];
        $GLOBALS['db']->query($sql);
    }
}

//商品运费by wu start
function goodsShippingFee($goods_id = 0, $warehouse_id = 0, $region = array(), $seckill_price = '') {

    $transport_info = array();

    //初始运费信息
    $shippingInfo = array(
        'shipping_id' => 0,
        'shipping_code' => '',
        'shipping_name' => '',
        'shipping_type' => $GLOBALS['_CFG']['freight_model'],
        'shipping_fee' => '',
        'shipping_fee_formated' => '',
        'free_money' => '',
        'is_shipping' => 0 //是否支持配送 0:不支持，1:支持
    );

    //初始运费
    $shippingFee = 0;


    //获取商品信息
    $leftJoin .= " LEFT JOIN " .$GLOBALS['ecs']->table('warehouse_goods'). " AS wg ON g.goods_id = wg.goods_id AND wg.region_id = '$warehouse_id' ";
    $leftJoin .= " LEFT JOIN " .$GLOBALS['ecs']->table('warehouse_area_goods'). " AS wag ON g.goods_id = wag.goods_id AND wag.region_id = '$area_id' ";

    $sql = " SELECT g.goods_id, g.freight, g.user_id AS ru_id, g.user_id, g.tid, g.is_shipping, g.shipping_fee, g.goods_weight, g.shop_price, " .
            "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS goods_price " .
            " FROM " . $GLOBALS['ecs']->table('goods') ." AS g ".
            $leftJoin .
            'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
                "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            " WHERE g.goods_id = '$goods_id' LIMIT 1";
    $goodsInfo = $GLOBALS['db']->getRow($sql);

    /**
     * 商品
     * 运费模板
     */
    if ($goodsInfo['freight'] == 2)
    {

        if (is_numeric($seckill_price)) {
            $goodsInfo['shop_price'] = $seckill_price;
        }

        //查询商家设置送方式
        $sellerShippingInfo = get_seller_shipping_type($goodsInfo['user_id']);

        $where = 1;
        if ($sellerShippingInfo) {
            $shippingInfo['shipping_id'] = $sellerShippingInfo['shipping_id'];
            $shippingInfo['shipping_code'] = $sellerShippingInfo['shipping_code'];
            $shippingInfo['shipping_name'] = $sellerShippingInfo['shipping_name'];

            $where .= " AND s.shipping_id = '" . $sellerShippingInfo['shipping_id'] . "'";
        }

        $transport_info = get_goods_transport($goodsInfo['tid']);

        $val = array();
        if ($transport_info) {
            if ($transport_info['freight_type'] == 1) {
                //获取配送区域
                $sql = "SELECT gtt.*, s.shipping_id, s.shipping_code, s.shipping_name, " .
                        "s.shipping_desc, s.insure, s.support_cod, gtt.configure FROM " . $GLOBALS['ecs']->table('shipping') . " AS s, " .
                        $GLOBALS['ecs']->table('goods_transport_tpl') . ' AS gtt ' .
                        " WHERE " . $where . " AND gtt.shipping_id = s.shipping_id " .
                        " AND s.enabled = 1 AND gtt.user_id = '" . $goodsInfo['user_id'] . "' AND gtt.tid = '" . $goodsInfo['tid'] . "'" .
                        " AND (FIND_IN_SET('" . $region[1] . "', gtt.region_id) OR FIND_IN_SET('" . $region[2] . "', gtt.region_id) OR FIND_IN_SET('" . $region[3] . "', gtt.region_id) OR FIND_IN_SET('" . $region[4] . "', gtt.region_id))" .
                        " LIMIT 1";
                $val = $GLOBALS['db']->getRow($sql);
            } else {
                $sql = "SELECT s.shipping_id, s.shipping_code, s.shipping_name, shipping_order FROM " . $GLOBALS['ecs']->table('shipping') . " AS s " .
                        " LEFT JOIN " . $GLOBALS['ecs']->table('goods_transport_extend') . " AS gted ON gted.tid = '" . $goodsInfo['tid'] . "' AND gted.ru_id = '" . $goodsInfo['user_id'] . "'" .
                        " LEFT JOIN " . $GLOBALS['ecs']->table('goods_transport_express') . " AS gte ON gted.tid = gte.tid AND gte.ru_id = '" . $goodsInfo['user_id'] . "'" .
                        " WHERE " . $where . " AND FIND_IN_SET(s.shipping_id, gte.shipping_id) " .
                        " AND ((FIND_IN_SET('" . $region[1] . "', gted.top_area_id)) OR (FIND_IN_SET('" . $region[2] . "', gted.area_id) OR FIND_IN_SET('" . $region[3] . "', gted.area_id) OR FIND_IN_SET('" . $region[4] . "', gted.area_id)))" .
                        " LIMIT 1";
                $val = $GLOBALS['db']->getRow($sql);
            }
        }

        $is_shipping = 0;
        if (!empty($val)) {
            $is_shipping = 1;
        } else {
            $shipping_list = available_shipping_list($region, $goodsInfo['user_id'], 1);
            if ($shipping_list) {
                $val = $shipping_list[0];
                $is_shipping = 1;

                if ($sellerShippingInfo && $sellerShippingInfo['shipping_id']) {
                    $cfg = array(
                        array('name' => 'item_fee', 'value' => 0),
                        array('name' => 'base_fee', 'value' => 0),
                        array('name' => 'step_fee', 'value' => 0),
                        array('name' => 'free_money', 'value' => 100000)
                    );

                    if (!isset($sellerShippingInfo['configure']) && empty($sellerShippingInfo['configure'])) {
                        $sellerShippingInfo['configure'] = serialize($cfg);
                    }

                    $val = $sellerShippingInfo;
                }
            }
        }

        if ($goodsInfo['is_shipping']) {
            $shippingFee = 0;
        } else {
            if (!empty($goodsInfo['freight'])) {

                $transportInfo = get_goods_transport($goodsInfo['tid']);

                if ($transportInfo) {
                    if ($transportInfo['freight_type']) {
                        /**
                         * 商品
                         * 运费模板
                         * 快递模板
                         */
                        $transport_tpl = get_goods_transport_tpl($goodsInfo, $region);
                        $shippingFee = $transport_tpl['shippingFee'];
                        $is_shipping = $transport_tpl['is_shipping'];
                    } else {
                        /**
                         * 商品
                         * 运费模板
                         * 自定义
                         */
                        $transport = array('top_area_id', 'area_id', 'tid', 'ru_id', 'sprice');
                        $transport_where = " AND ru_id = '" . $goodsInfo['user_id'] . "' AND tid = '" . $goodsInfo['tid'] . "'";
                        $goods_transport = $GLOBALS['ecs']->get_select_find_in_set(2, $region[2], $transport, $transport_where, 'goods_transport_extend', 'area_id');
                        $shippingFee = $goods_transport['sprice'];
                    }
                }else{
                    $is_shipping = 0;
                }
            }
        }
    }

    /**
     * 商品
     * 固定运费
     */
    elseif ($goodsInfo['freight'] == 1)
    {
        $shippingFee = $goodsInfo['shipping_fee'];
        $is_shipping = 1;
    }

    $shippingInfo['shipping_title'] = isset($transport_info['shipping_title']) ? $transport_info['shipping_title'] : '';
    $shippingInfo['shipping_fee'] = $shippingFee;
    $shippingInfo['shipping_fee_formated'] = price_format($shippingFee, false);
    $shippingInfo['is_shipping'] = $is_shipping; //是否支持配送

    return $shippingInfo;
}

/**
 * 商品地区运费模板
 */
function get_goods_transport_tpl($goodsInfo = array(), $region = array(), $shippingInfo = array(), $goods_number = 1){

    $goodsInfo['goods_weight'] = isset($goodsInfo['goods_weight']) ? $goodsInfo['goods_weight'] : $goodsInfo['goodsweight'];
    $goodsInfo['shop_price'] = isset($goodsInfo['shop_price']) ? $goodsInfo['shop_price'] : $goodsInfo['goods_price'];

    if(empty($shippingInfo)){

        $is_goods = 1;

        /**
         * 商品详情显示
         */
        //查询商家设置送方式
        $shippingInfo = get_seller_shipping_type($goodsInfo['user_id']);
        if(!$shippingInfo){
            $tpl_shipping = get_goods_transport_tpl_shipping($goodsInfo['tid'], 0, $region);
            if ($tpl_shipping) {
                $shippingInfo = $tpl_shipping[0];
            }
        }else{
            $shippingInfo = get_goods_transport_tpl_shipping($goodsInfo['tid'], $shippingInfo['shipping_id'], $region);
        }
    }else{

        $is_goods = 0;

        /**
         * 进货单显示/订单分单
         */
        $shippingInfo = get_goods_transport_tpl_shipping($goodsInfo['tid'], $shippingInfo['shipping_id'], $region);
    }

    $where = '';
    if($shippingInfo && $shippingInfo['shipping_id']){
        $where .= " AND s.shipping_id = '" . $shippingInfo['shipping_id'] . "'";
    }else{
        $shippingInfo = get_goods_transport_tpl_shipping($goodsInfo['tid'], 0, $region, $is_goods);

        if($shippingInfo){
            $shippingInfo = isset($shippingInfo[0]) ? $shippingInfo[0] : array();
        }
    }

    //获取配送区域
    $sql = "SELECT gtt.*, s.shipping_id, s.shipping_code, s.shipping_name, " .
                "s.shipping_desc, s.insure, s.support_cod, gtt.configure FROM " .$GLOBALS['ecs']->table('shipping'). " AS s, " .
                $GLOBALS['ecs']->table('goods_transport_tpl') . ' AS gtt ' .
            " WHERE gtt.shipping_id = s.shipping_id " . $where .
            " AND s.enabled = 1 AND gtt.user_id = '" . $goodsInfo['user_id'] . "' AND gtt.tid = '" .$goodsInfo['tid']. "'".
            " AND (FIND_IN_SET('" .$region[1]. "', gtt.region_id) OR FIND_IN_SET('" .$region[2]. "', gtt.region_id) OR FIND_IN_SET('" .$region[3]. "', gtt.region_id) OR FIND_IN_SET('" .$region[4]. "', gtt.region_id))" .
            " LIMIT 1";
    $val = $GLOBALS['db']->getRow($sql);

    //是否支持配送
    $is_shipping = 0;
    if($val){
        $is_shipping = 1;
    }

    if(!$shippingInfo){
        $shippingInfo = array(
            'shipping_id' => 0,
            'shipping_code' => '',
            'configure' => '',
        );
    }

    $shippingFee = 0;
    if($is_shipping){

        if(empty($shippingInfo) || $shippingInfo && empty($shippingInfo['shipping_id'])){
            $shippingInfo = $val;
        }

        $goods_weight = $goodsInfo['goods_weight'] * $goods_number;
        $shop_price = $goodsInfo['shop_price'] * $goods_number;
        $shippingFee = shipping_fee($shippingInfo['shipping_code'], $shippingInfo['configure'], $goods_weight, $shop_price, $goods_number);
        $shippingCfg = unserialize_config($shippingInfo['configure']);
        $free_money = price_format($shippingCfg['free_money'], false);
    }

    $arr = array(
        'shippingFee' => $shippingFee,
        'shipping_fee_formated' => price_format($shippingFee, false),
        'is_shipping' => $is_shipping,
        'shipping_id' => $shippingInfo['shipping_id']  //购物流程需要
    );

    return $arr;
}

/**
 * 获取商品运费模板的运费方式
 */
function get_goods_transport_tpl_shipping($tid = 0, $shipping_id = 0 , $region = array(), $type = 0, $limit = 0){

    $where = "";
    if($shipping_id){
        $where .= " AND gtt.shipping_id = '$shipping_id'";
    }

    if($limit){
        $where .= " LIMIT " . $limit;
    }

    $sql = "SELECT gtt.*, s.shipping_name, s.shipping_code FROM " .$GLOBALS['ecs']->table("goods_transport_tpl") ." AS gtt".
            " LEFT JOIN " . $GLOBALS['ecs']->table("shipping") ." AS s ON gtt.shipping_id = s.shipping_id".
            " WHERE gtt.tid = '$tid' $where";

    $arr = array();
    if($type == 1){
        $res = $GLOBALS['db']->getAll($sql);
        foreach($res as $key=>$row){
            $region_id = !empty($row['region_id']) ? explode(",", $row['region_id']) : array();

            if ($region) {
                foreach ($region as $rk => $rrow) {
                    if ($region_id && in_array($rrow, $region_id)) {
                        $arr[] = $row;
                    }else{
                        continue;
                    }
                }
            }
        }
    }else{
        $res = $GLOBALS['db']->getRow($sql);
        $region_id = !empty($res) && !empty($res['region_id']) ? explode(",", $res['region_id']) : array();

        if ($region) {
            foreach ($region as $key => $row) {
                if ($region_id && in_array($row, $region_id)) {
                    return $res;
                }
            }
        }
    }

    return $arr;
}

/**
 * 运算商品详情设置非按区域运费模式价格
 */
function get_order_transport($goods_list, $consignee = array(), $shipping_id = 0, $shipping_code = ''){

    $sprice = 0;
    $type_left = array();
    $freight = 0;

    if($goods_list && $shipping_code != 'cac'){

        /**
         * 商品运费模板
         * 自定义
         */
        $custom_shipping = get_goods_custom_shipping($goods_list);

        /**
         * 商品运费模板
         * 快递模板
         */
        $area_shipping = get_goods_area_shipping($goods_list, $shipping_id, $shipping_code, $consignee);
        foreach($goods_list as $key=>$row){
            if($row['freight'] && $row['is_shipping'] == 0){
                if($row['freight'] == 1){
                    /**
                     * 商品
                     * 固定运费
                     */
                    $sprice += $row['shipping_fee'] * $row['goods_number'];
                }else{

                    $trow = get_goods_transport($row['tid']);

                    if($trow['freight_type'] == 0){
                        /**
                         * 商品
                         * 运费模板
                         * 区域运费
                         */
                        $transport = array('top_area_id', 'area_id', 'tid', 'ru_id', 'sprice');
                        $transport_where = " AND ru_id = '" . $row['ru_id'] . "' AND tid = '" . $row['tid'] . "'";
                        $goods_transport = $GLOBALS['ecs']->get_select_find_in_set(2, $consignee['city'], $transport, $transport_where, 'goods_transport_extend', 'area_id');

                        if ($goods_transport) {
                            $ship_transport = array('tid', 'ru_id', 'shipping_fee');
                            $ship_transport_where = " AND ru_id = '" . $row['ru_id'] . "' AND tid = '" . $row['tid'] . "'";
                            $goods_ship_transport = $GLOBALS['ecs']->get_select_find_in_set(2, $shipping_id, $ship_transport, $ship_transport_where, 'goods_transport_express', 'shipping_id');
                        }

                        $goods_transport['sprice'] = isset($goods_transport['sprice']) ? $goods_transport['sprice'] : 0;
                        $goods_ship_transport['shipping_fee'] = isset($goods_ship_transport['shipping_fee']) ? $goods_ship_transport['shipping_fee'] : 0;

                        /* 是否免运费 start */
                        if ($custom_shipping && $custom_shipping[$row['tid']]['amount'] >= $trow['free_money'] && $trow['free_money'] > 0) {
                            $is_shipping = 1; /* 免运费 */
                        }else{
                            $is_shipping = 0; /* 有运费 */
                        }
                        /* 是否免运费 end */

                        if ($is_shipping == 0) {
                            if ($trow['type'] == 1) {
                                $sprice += $goods_transport['sprice'] * $row['goods_number'] + $goods_ship_transport['shipping_fee'] * $row['goods_number'];
                            } else {
                                $type_left[$row['tid']] = $goods_transport['sprice'] + $goods_ship_transport['shipping_fee'];
                            }
                        }
                    }
                }
            }else{
                $freight += 1;
            }
        }

        $unified_total = get_cart_unified_freight_total($type_left);

        $arr = array(
            'sprice' => $area_shipping['shipping_fee'] + $sprice + $unified_total, //固定运费 + 运费模板
            'freight' => $freight //是否有按配送区域计算运费的商品
        );

    }else{
        $arr = array(
            'sprice' => 0, //上门取货运费为0
            'freight' => $freight //是否有按配送区域计算运费的商品
        );
    }

    return $arr;
}

/**
 * 商品运费模板
 * 自定义
 *
 * $goods_list 进货单商品
 */
function get_goods_custom_shipping($goods_list) {
    $tid_arr1 = array();
    foreach ($goods_list as $key => $row) {
        $tid_arr1[$row['tid']][$key] = $row;
    }

    $tid_arr2 = array();
    foreach ($tid_arr1 as $key => $row) {
        $row = !empty($row) ? array_values($row) : $row;

        $tid_arr2[$key]['weight'] = 0;
        $tid_arr2[$key]['number'] = 0;
        $tid_arr2[$key]['amount'] = 0;
        foreach ($row as $gkey => $grow) {
            $tid_arr2[$key]['weight'] += $grow['goodsweight'] * $grow['goods_number'];
            $tid_arr2[$key]['number'] += $grow['goods_number'];
            $tid_arr2[$key]['amount'] += $grow['goods_price'] * $grow['goods_number'];
        }
    }

    return $tid_arr2;
}

/**
 * 商品运费模板
 * 快递模板
 *
 * $goods_list 进货单商品
 */
function get_goods_area_shipping($goods_list, $shipping_id = 0, $shipping_code = '', $consignee = array()){

    $tid_arr1 = array();
    foreach ($goods_list as $key => $row) {
        $tid_arr1[$row['tid']][$key] = $row;
    }

    $tid_arr2 = array();
    foreach ($tid_arr1 as $key => $row) {
        $row = !empty($row) ? array_values($row) : $row;

        $tid_arr2[$key]['weight'] = 0;
        $tid_arr2[$key]['number'] = 0;
        $tid_arr2[$key]['amount'] = 0;
        foreach ($row as $gkey => $grow) {
            $tid_arr2[$key]['weight'] += $grow['goodsweight'] * $grow['goods_number'];
            $tid_arr2[$key]['number'] += $grow['goods_number'];
            $tid_arr2[$key]['amount'] += $grow['goods_price'] * $grow['goods_number'];
        }
    }

    if(empty($shipping_id)){
        $select = array('shipping_code' => $shipping_code);
        $shipping_info = shipping_info($select, array('shipping_id'));
        $shipping_id = $shipping_info['shipping_id'];
    }

    if(empty($shipping_code)){
        $shipping_info = shipping_info($shipping_id, array('shipping_code'));
        $shipping_code = $shipping_info['shipping_code'];
    }

    $region    = array($consignee['country'], $consignee['province'], $consignee['city'], $consignee['district'], $consignee['street']);
    $shipping_fee = 0;
    foreach($tid_arr2 as $key=>$row){

        $sql = "SELECT * FROM " .$GLOBALS['ecs']->table('goods_transport_tpl'). " WHERE tid = '$key' AND shipping_id = '$shipping_id'" .
                " AND ((FIND_IN_SET('" . $region[1] . "', region_id)) OR (FIND_IN_SET('" . $region[2] . "', region_id) OR FIND_IN_SET('" . $region[3] . "', region_id) OR FIND_IN_SET('" . $region[4] . "', region_id)))" .
                " LIMIT 1";
        $transport_tpl = $GLOBALS['db']->getRow($sql);

        $configure = !empty($transport_tpl) && $transport_tpl['configure'] ? unserialize($transport_tpl['configure']) : '';

        if(!empty($configure)){
            $tid_arr2[$key]['shipping_fee'] = shipping_fee($shipping_code, $configure, $row['weight'], $row['amount'], $row['number']);
        }else{
            $tid_arr2[$key]['shipping_fee'] = 0;
        }

        $shipping_fee += $tid_arr2[$key]['shipping_fee'];
    }

    $arr = array('tid_list' => $tid_arr2, 'shipping_fee' => $shipping_fee);
    return $arr;
}

/**
 * 进货单商品统一运费
 */
function get_cart_unified_freight_total($total){

    $sprice = 0;

    if($total){
        foreach($total as $key=>$row){
            $sprice += $row;
        }
    }

    return $sprice;
}

/**
 * 运费模板信息
 */
function get_goods_transport($tid = 0){
    $sql = "SELECT * FROM " .$GLOBALS['ecs']->table('goods_transport'). " WHERE tid = '$tid' LIMIT 1";
    return $GLOBALS['db']->getRow($sql);
}

//合算单快递运费金额
function get_goods_shipping_fee($goodsInfo, $val, $sellerShippingInfo, $warehouse_id){
    //默认运费模式
    if ($GLOBALS['_CFG']['freight_model'] == 0) {
        $shippingFee = shipping_fee($val['shipping_code'], $val['configure'], $goodsInfo['goods_weight'], $goodsInfo['shop_price'], 1);
        $shippingCfg = unserialize_config($val['configure']);
        $free_money = price_format($shippingCfg['free_money'], false);
    }

    $arr = array(
        'shippingFee' => $shippingFee,
        'free_money' => $free_money
    );

    return $arr;
}
//商品运费by wu end

//商家订单运费by wu(region, ru_id, weight, goods_price, number)
function getSellerShippingFee($sellerOrderInfo = array(), $cart_goods) {

    //获取配送区域
    $sql = 'SELECT s.shipping_id, s.shipping_code ' .
            'FROM ' . $GLOBALS['ecs']->table('shipping') . ' AS s ' .
            " WHERE s.shipping_id = '" . $sellerOrderInfo['shipping_id'] . "' LIMIT 1";
    $val = $GLOBALS['db']->getRow($sql);

    if($sellerOrderInfo['region']){
        $sellerOrderInfo['region'] = array_values($sellerOrderInfo['region']);
    }

    $consignee['country']= $sellerOrderInfo['region'][0];
    $consignee['province'] = $sellerOrderInfo['region'][1];
    $consignee['city'] = $sellerOrderInfo['region'][2];
    $consignee['district'] = $sellerOrderInfo['region'][3];
    $consignee['street'] = $sellerOrderInfo['region'][4];
    $order_transpor = get_order_transport($cart_goods, $consignee, $val['shipping_id'], $val['shipping_code']);

    $shippingFee = 0;
    if ($order_transpor['freight']) {
        $shippingFee += $order_transpor['sprice']; //有配送按配送区域计算运费
    } else {
        $shippingFee = $order_transpor['sprice'];
    }

    return $shippingFee;
}

/**
 * 检测当前用户白条相关权限(是否授权,是否额度为0,是否逾期) bylu
 * @param null $stages_qishu 分期期数
 * @param bool|false $is_jiesuan 是否进货单结算入口
 */
function bt_auth_check($stages_qishu = null,$is_jiesuan=false){

    include_once('includes/cls_json.php');
    $json  = new JSON;

    //判断当前用户是否有白条授权(未被授权不能下单,白条额度为0不能下单)
    if(!empty($stages_qishu) && $stages_qishu > -1){
        $bt_sql="SELECT amount FROM ".$GLOBALS['ecs']->table('baitiao')." WHERE user_id='".$_SESSION['user_id']."'";
        $user_baitao_amount=$GLOBALS['db']->getOne($bt_sql);

        if(empty($user_baitao_amount)){
            $result['error'] = 1;
            $result['message']=$GLOBALS['_LANG']['bt_noll_impower'];
            die($json->encode($result));
        }elseif($user_baitao_amount<=0){
            $result['error'] = 1;
            $result['message']=$GLOBALS['_LANG']['bt_noll_balance'];
            die($json->encode($result));
        }
    }
    //判断是否可以下单(白条逾期就不能下单)
    $bt_sql = "SELECT b.*,bl.repay_date,bl.is_stages,bl.yes_num, bl.order_id FROM " . $GLOBALS['ecs']->table('baitiao') . " AS b " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('baitiao_log') . " AS bl ON b.baitiao_id=bl.baitiao_id " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('order_info') . "  AS o ON bl.order_id=o.order_id " .
            " WHERE bl.is_repay=0 AND b.user_id='" . $_SESSION['user_id'] . "' AND o.order_id > 0";
    $bt_info = $GLOBALS['db']->getAll($bt_sql);

    if(!empty($bt_info))
    {
        $over_date=gmtime()-(($bt_info[0]['over_repay_trem'])*24*3600);

        foreach($bt_info as $k=>$val)
        {
            //如果是白条分期订单,重新计算最后还款日期 bylu;
            if($bt_info[$k]['is_stages']==1){

                $repay_date=unserialize($bt_info[$k]['repay_date']);//数组;
                $repay_date=  local_strtotime($repay_date[$bt_info[$k]['yes_num']+1]);//当前期预定还款时间;
                $over_date=gmtime();//当前时间;

                if($over_date>=$repay_date)
                {
                    if($is_jiesuan){
                        show_message($GLOBALS['_LANG']['bt_forbid_pay'],$GLOBALS['_LANG']['bt_go_refund'],'user.php?act=baitiao');
                        die;
                    }else{
                        $result['error'] = 1;
                        $result['message']=$GLOBALS['_LANG']['bt_forbid_pay'];
                        die($json->encode($result));
                    }
                }

            }else{
                if($over_date>=$val['repay_date'])
                {
                    if($is_jiesuan){
                        show_message($GLOBALS['_LANG']['bt_overdue'],$GLOBALS['_LANG']['bt_go_refund'],'user.php?act=baitiao');
                        die;
                    }else{
                        $result['error'] = 1;
                        $result['message']=$GLOBALS['_LANG']['bt_overdue'];
                        die($json->encode($result));
                    }
                }
            }
        }
    }
}

/**
 * 计算运费
 * @param   string  $shipping_code      配送方式代码
 * @param   mix     $shipping_config    配送方式配置信息
 * @param   float   $goods_weight       商品重量
 * @param   float   $goods_amount       商品金额
 * @param   float   $goods_number       商品数量
 * @return  float   运费
 */
function shipping_fee($shipping_code, $shipping_config, $goods_weight, $goods_amount, $goods_number = 0)
{
    if (!is_array($shipping_config))
    {
        $shipping_config = unserialize($shipping_config);
    }

    $filename = ROOT_PATH . 'includes/modules/shipping/' . $shipping_code . '.php';
    if (file_exists($filename))
    {
        include_once($filename);

        $obj = new $shipping_code($shipping_config);

        return $obj->calculate($goods_weight, $goods_amount, $goods_number);
    }
    else
    {
        return 0;
    }
}

/**
 * 处理序列化的支付、配送的配置参数
 * 返回一个以name为索引的数组
 *
 * @access  public
 * @param   string       $cfg
 * @return  void
 */
function unserialize_config($cfg)
{
    if (is_string($cfg) && ($arr = unserialize($cfg)) !== false)
    {
        $config = array();

        foreach ($arr AS $key => $val)
        {
            $config[$val['name']] = $val['value'];
        }

        return $config;
    }
    else
    {
        return false;
    }
}


/**
 * 在线客服 bylu
 */
function IM($appkey,$secretkey){

    $user_id=$_SESSION['user_id'];

    include "plugins/aliyunim/TopSdk.php";
    include "includes/lib_order.php";
    date_default_timezone_set('Asia/Shanghai');

    $c = new TopClient;
    $c->appkey = $appkey;
    $c->secretKey = $secretkey;
    $req = new OpenimUsersAddRequest();

    //判断用户是否登入,登入了就用登入的用户名,未登入就使用匿名;
    if($user_id > 0){

        $user_info = user_info($user_id);

        $userinfos = new Userinfos;
        $userinfos->nick=$user_info['user_name'];
        $userinfos->icon_url=$user_info['user_picture'];
        $userinfos->email=$user_info['email'];
        $userinfos->mobile=$user_info['mobile_phone'];
        $userinfos->userid='dsc'.$user_info['user_id'];
        $userinfos->password='dsc'.$user_info['user_id'];
//        $userinfos->remark='dsc'.$user_info['user_id'];//备注
        $userinfos->career='未填写';
        $userinfos->address="未填写";
        $userinfos->name=$user_info['user_name'];
        $userinfos->gender=$user_info['sex'] == 1?'M':($user_info['sex']==2?'F':'');
        $userinfos->wechat="未填写";
        $userinfos->qq=$user_info['qq'];
        $userinfos->weibo="未填写";
        $req->setUserinfos(json_encode($userinfos));
        $resp = $c->execute($req);

    }else{
            $user_info['user_id']='ni'.time().mt_rand(0,9);
            $_SESSION['user_ni_id']=$user_info['user_id'];
            $user_info['user_name']='匿名_'.$user_info['user_id'];
            $userinfos = new Userinfos;
            $userinfos->nick=$user_info['user_name'];
            $userinfos->userid=$user_info['user_id'];
            $userinfos->password=$user_info['user_id'];
            $userinfos->name=$user_info['user_name'];
            $userinfos->remark=$user_info['user_id'];
            $req->setUserinfos(json_encode($userinfos));
            $resp = $c->execute($req);
    }
}



/**
 * 商品属性组合 bylu
 */
function attr_group() {
    $t = func_get_args();
    if(func_num_args() == 1) return call_user_func_array( __FUNCTION__, $t[0] );
    $a = array_shift($t);
    if(! is_array($a)) $a = array($a);
    $a = array_chunk($a, 1);
    do {
        $r = array();
        $b = array_shift($t);
        if(! is_array($b)) $b = array($b);
        foreach($a as $p)
            foreach(array_chunk($b, 1) as $q)
                $r[] = array_merge($p, $q);
        $a = $r;
    }while($t);
    return $r;
}
function combination( $arr, $num=0) {
    $len = count($arr);
    if($num == 0) $num = $len;
    $res = array();
    for($i=1,$n=pow(2, $len);$i<$n;++$i) {
        $tmp = str_pad(base_convert($i, 10, 2), $len, '0', STR_PAD_LEFT);
        $t = array();
        for($j=0;$j<$len;++$j) {
            if($tmp{$j} == '1') {
                $t[] = $arr[$j];
            }
        }
        if(count($t) == $num) $res[] = $t;
    }
    return $res;
}

/**
 * 注册送优惠券 bylu
 * @param $user_id 用户ID
 */
function register_coupons($user_id){

    //获取注册类型的优惠券信息;
    $res = get_coupons_type_info2(1);

    if(!empty($res)){
        foreach($res as $k=>$v){
            //获取当前的注册券已被发放的数量(防止发放数量超过设定发放数量)
            $num=$GLOBALS['db']->getOne(" SELECT COUNT(uc_id) FROM ".$GLOBALS['ecs']->table('coupons_user')." WHERE cou_id='".$v['cou_id']."'");
            if($v['cou_total'] <= $num) {
                continue;
            }

            //注册送注册券
            $cou_id = $v['cou_id'];
            $uc_sn = $v['uc_sn'];
            $sql = "INSERT INTO ".$GLOBALS['ecs']->table('coupons_user')." VALUES (NULL,'$user_id','$cou_id',0,$uc_sn,0,0)";
            $GLOBALS['db']->query($sql);
        }
    }

}

/***获取优惠券类型信息(不带分页) bylu
 * @param string $cou_type 优惠券类型 1:注册送,2:购物送,3:全场送,4:会员送  默认返回所有类型数据
 * @return array
 */
function get_coupons_type_info2($cou_type='1,2,3,4'){

    //获取格林尼治时间戳(用于判断优惠券是否已过期)
    $time = gmtime();

    $sql="SELECT * FROM ".$GLOBALS['ecs']->table('coupons').
            "WHERE review_status = 3 AND cou_type IN($cou_type) AND $time<cou_end_time ";

    $arr = $GLOBALS['db']->getAll($sql);

    //生成优惠券编号
    foreach($arr as $k=>$v){
        $arr[$k]['uc_sn']=$time.rand(10,99);
    }

    return $arr;

}

/***获取优惠券类型信息(带分页) bylu
 * @param string $cou_type 优惠券类型 1:注册送,2:购物送,3:全场送,4:会员送  默认返回所有类型数据
 * @param string $ru_id 商家ID,默认显示所有商家和平台发放的 优惠券;
 * @return array
 */
function get_coupons_type_info($cou_type='1,2,3,4,5', $ru_id = 0){

    //判断是否要取出指定的商家发放的优惠券
    $where="";
    if(!empty($ru_id)){
        $where.=" AND ru_id = $ru_id ";
    }

    //判断是否输入了搜索优惠券名称;
    $cou_name=  addslashes(trim($_REQUEST['cou_name']));
    if(!empty($cou_name)){
        $where.=" AND cou_name like '%$cou_name%' ";
    }

    $result = get_filter();

    if ($result === false)
    {
    /* 过滤条件 */

    $filter['sort_by']    = empty($_REQUEST['sort_by']) ? 'cou_id' : trim($_REQUEST['sort_by']);
    $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);

    $filter['review_status']    = empty($_REQUEST['review_status']) ? 0 : intval($_REQUEST['review_status']);

    if( $filter['review_status']){
            $where .= " AND review_status = '" .$filter['review_status']. "' ";
        }

    $sql = "SELECT COUNT(*) FROM ".$GLOBALS['ecs']->table('coupons').
        "WHERE cou_type IN($cou_type) $where";
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    /* 分页大小 */
    $filter = page_and_size($filter);

        $sql="SELECT * FROM ".$GLOBALS['ecs']->table('coupons').
        "WHERE cou_type IN($cou_type) $where  ORDER BY $filter[sort_by] $filter[sort_order]";

    set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }

    $arr = array();
    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

    while ($row = $GLOBALS['db']->fetchRow($res))
    {
		$row['type'] = $row['cou_type'];
        $row['cou_type'] = $row['cou_type']==1?'<span class="green">注册赠券</span>':($row['cou_type']==2?'<span class="blue">购物赠券</span>':($row['cou_type']==3?'<span class="red">全场赠券</span>':($row['cou_type']==4?'<span class="org">会员赠券</span>':($row['cou_type']==5?'<span class="yellow">免邮券</span>':''))));
        $row['user_name'] = get_shop_name($row['ru_id'], 1);//优惠券所属商家;
        $row['cou_start_time'] = local_date('Y-m-d',$row['cou_start_time']);
        $row['cou_end_time'] = local_date('Y-m-d',$row['cou_end_time']);
        $row['cou_is_use'] = $row['cou_is_use']==0?'<span class="green">未使用</span>':'<span class="red">已使用</span>';
        $row['cou_is_time'] = local_strtotime($row['cou_end_time'])>gmtime()?'<span class="green">未过期</span>':'<span class="red">已过期</span>';
        $region_arr = get_cou_region_list($row['cou_id']);
        $row['free_value_name'] = $region_arr['free_value_name'];

        $arr[] = $row;

    }
    $arr = array('item' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;

}


/***
 * 获取用户拥有的优惠券 默认返回所有用户所拥有的优惠券; bylu
 * @param string $user_id 用户ID;
 * @param bool|false $is_use 找出当前用户可以使用的
 * @param bool|false $total 订单总价
 * @param bool|false $cart_goods 商品信息
 * @param bool|false $user 用于区分是否会员中心里取数据(会员中心里的优惠券不能分组)
 * @return mixed 优惠券数组
 */
function get_user_coupons_list($user_id = '', $is_use = false, $total = '', $cart_goods = false, $user = true, $cart_ru_id = -1, $act_type = 'user') {

    $time = gmtime();

    $cart_where = '';
    if ($cart_ru_id && $cart_ru_id > -1) {
        $cart_where .= " AND cu.is_use = 0";
    }

    //可使用的(平台用平台发的,商家用商家发的,当订单中混合了平台与商家的商品时,各自计算各自的商品总价是否达到各自发放的优惠券门槛,达到的话当前整个订单即可使用该优惠券)
    if ($is_use && isset($total) && $cart_goods) {

        foreach ($cart_goods as $k => $v) {
            $res[$v['ru_id']]['order_total'] += $v['goods_price'] * $v['goods_number'];
            $res[$v['ru_id']]['seller_id'] = $v['ru_id'];
            $res[$v['ru_id']]['goods_id'] .= $v['goods_id'] . ",";
            $res[$v['ru_id']]['cat_id'] .= $v['cat_id'] . ",";
            $res[$v['ru_id']]['goods'][$v['goods_id']] = $v;
        }

        $arr = array();
        $couarr = array();

        foreach($res as $key=>$row){
            $row['goods_id'] = get_del_str_comma($row['goods_id']);
            $row['cat_id'] = get_del_str_comma($row['cat_id']);

            $cart_where .= " AND c.ru_id = '" .$row['seller_id']. "'";

            $sql = "SELECT c.*, cu.uc_id FROM " . $GLOBALS['ecs']->table("coupons_user") . " AS cu " .
                    " LEFT JOIN " . $GLOBALS['ecs']->table("coupons") . " AS c ON cu.cou_id = c.cou_id " .
                    " WHERE c.review_status = 3 AND c.cou_end_time > $time AND $time > c.cou_start_time" .
                    " AND " . $row['order_total'] . " >= c.cou_man" .
                    " AND cu.order_id = 0 AND cu.user_id = '$user_id'" . $cart_where . " GROUP BY cu.uc_id";
            $couarr[$key] = $GLOBALS['db']->getAll($sql);

            if ($couarr[$key]) {
                foreach ($couarr[$key] as $ckey => $crow) {
                    $couarr[$key][$ckey]['shop_name'] = get_shop_name($crow['ru_id'], 1);
                }
            }

            $goods_ids = array();
            if(isset($row['goods_id']) && $row['goods_id'] && !is_array($row['goods_id'])){
                $goods_ids = explode(",", $row['goods_id']);
                $goods_ids = array_unique($goods_ids);
            }

            $goods_cats = array();
            if(isset($row['cat_id']) && $row['cat_id'] && !is_array($row['cat_id'])){
                $goods_cats = explode(",", $row['cat_id']);
                $goods_cats = array_unique($goods_cats);
            }

            if (($goods_ids || $goods_cats) && $couarr[$key]) {
                foreach ($couarr[$key] as $rk => $rrow) {
                    if ($rrow['cou_goods']) {
                        $cou_goods = explode(",", $rrow['cou_goods']); //可使用优惠券商品
                        $cou_goods_prices = 0;
                        foreach ($goods_ids as $m => $n) {
                            if (in_array($n, $cou_goods)) {
                                $cou_goods_prices += $row['goods'][$n]['subtotal'];
                                if ($cou_goods_prices > $rrow['cou_man']) {
                                    $arr[] = $rrow;
                                    break;
                                }
                            }
                        }
                    }elseif($rrow['spec_cat']){

                        $spec_cat = get_cou_children($rrow['spec_cat']);
                        $spec_cat = explode(",", $spec_cat);
                        $cou_goods_prices = 0;
                        foreach ($goods_cats as $m => $n) {
                            if (in_array($n, $spec_cat)) {
                                foreach($row['goods'] as $key=>$val){
                                    if($n == $val['cat_id']){
                                        $cou_goods_prices += $val['subtotal'];
                                    }
                                }
                                if ($cou_goods_prices > $rrow['cou_man']) {
                                    $arr[] = $rrow;
                                    continue;
                                }
                            }
                        }
                    }else{
                        $arr[] = $rrow;
                    }
                }
            }
        }

        return $arr;
    } else {

        if (!empty($user_id) && $user) {
            $where = " WHERE cu.user_id IN(" . $user_id . ") AND c.review_status = 3";
        } else if (!empty($user_id)) {
            $where = " WHERE cu.user_id IN(" . $user_id . ") AND c.review_status = 3";
        }

        $select = "";
        $leftjoin = "";
        if($act_type == 'cart'){
            $where .= " AND c.cou_end_time > $time AND $time";
        }else{
            $select = ", o.order_sn, o.add_time";
            $leftjoin = " LEFT JOIN " . $GLOBALS['ecs']->table('order_info') ." AS o ON cu.order_id = o.order_id ";
        }

        $sql = " SELECT c.*, cu.* $select FROM " .
                $GLOBALS['ecs']->table('coupons_user') . " AS cu " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('coupons') .
                " AS c ON c.cou_id = cu.cou_id " .
                $leftjoin .
                $where . $cart_where . " GROUP BY cu.uc_id";

        $res = $GLOBALS['db']->getAll($sql);

        if($res){
            foreach($res as $key => $row){
                $res[$key]['shop_name'] = get_shop_name($row['ru_id'], 1);
            }
        }

        return $res;
    }
}

/**
 * 获取当前优惠券的不包邮地区
 * @param $cou_id 优惠券ID
 * @return $cou_region 不包邮地区
 */
function get_coupons_region($cou_id = 0) {
    $sql = "SELECT region_list FROM " . $GLOBALS['ecs']->table('coupons_region') . " WHERE cou_id = '$cou_id'";
    return $GLOBALS['db']->getOne($sql, true);
}

/**
 * 获取当前商品可用的优惠券
 * @param $goods_id 商品ID
 */
function get_goods_coupons_list($goods_id = '') {

    if (!$goods_id)
        return false;

    $time = gmtime();

    $ru_id = $GLOBALS['db']->getOne("SELECT user_id FROM " . $GLOBALS['ecs']->table('goods') . "
              WHERE goods_id =$goods_id");

    $sql = " SELECT cu.*,c.* FROM " . $GLOBALS['ecs']->table('coupons') . " c
            LEFT JOIN " . $GLOBALS['ecs']->table('coupons_user') . " cu ON c.cou_id=cu.cou_id
            WHERE  c.cou_end_time > $time
            AND (c.cou_goods =0 OR FIND_IN_SET($goods_id,c.cou_goods))
            AND c.cou_type IN (3,4)
            AND c.ru_id = '" . $ru_id . "'
            GROUP BY c.cou_id";

    return $GLOBALS['db']->getAll($sql);
}

/**
 * 通过 用户优惠券ID 获取该条优惠券详情 bylu
 * @param $uc_id 用户优惠券ID
 * @return mixed
 */
function get_coupons($uc_id = 0, $select = array()){
    $time = gmtime();

    if($select && is_array($select)){
        $select = implode(",", $select);
    }else{
        $select = "c.*, cu.*";
    }

    $sql = " SELECT $select FROM " . $GLOBALS['ecs']->table('coupons_user') . " cu " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('coupons') . " c ON c.cou_id = cu.cou_id " .
            " WHERE cu.uc_id = '$uc_id' AND cu.user_id = '" . $_SESSION['user_id'] . "' AND c.cou_end_time > $time GROUP BY cu.uc_id LIMIT 1 ";
    return $GLOBALS['db']->getRow($sql);
}

//计算运费总金额
function available_shipping_fee($ru_list){

    $shipping_fee = 0;
    if($ru_list){
        foreach($ru_list AS $k=>$v){
            $shipping_fee += $v['shipping']['shipping_fee'];
        }
    }

    $arr['shippingFee'] = $shipping_fee;
    $arr['shipping_fee'] = price_format($shipping_fee, false);
    return $arr;
}

/*
* 获取当前用户订单可使用储值卡列表
* @param user_id 用户ID
* @param cart_category 进货单商品所属分类ID
* @param cart_goods 进货单商品ID
* @return
*/
function get_user_value_card($user_id, $cart_goods, $cart_value) {
    $arr = array();
    /* 判断用户可用储值卡的使用范围（店铺） */
    $sql = " SELECT v.vid, t.use_merchants FROM " . $GLOBALS['ecs']->table('value_card_type') .
            " AS t LEFT JOIN " . $GLOBALS['ecs']->table('value_card') . " AS v ON v.tid = t.id WHERE v.user_id = '$user_id' ";
    $use_merchants = $GLOBALS['db']->getAll($sql);

    $shop_ids = array();
    if ($use_merchants) {
        foreach ($use_merchants as $val) {
            if ($val['use_merchants'] == 'all') {
                $sql = " SELECT user_id FROM " . $GLOBALS['ecs']->table('merchants_shop_information') . " WHERE merchants_audit = 1 ";
                $res = $GLOBALS['db']->getAll($sql);
                if ($res) {
                    foreach ($res as $v) {
                        $shop_ids[$val['vid']][] = $v['user_id'];
                    }
                }
            } elseif ($val['use_merchants'] == 'self') {
                $sql = " SELECT user_id FROM " . $GLOBALS['ecs']->table('merchants_shop_information') . " WHERE merchants_audit = 1 AND self_run = 1 ";
                $res = $GLOBALS['db']->getAll($sql);

                $self_id = array(array('user_id' => 0));
                if ($res) {
                    $res = array_merge($res, $self_id);
                }

                if ($res) {
                    foreach ($res as $v) {
                        $shop_ids[$val['vid']][] = $v['user_id'];
                    }
                }
            } elseif ($val['use_merchants'] == '') {
                $shop_ids[$val['vid']] = array();
            } else {
                $shop_ids[$val['vid']] = explode(',', $val['use_merchants']);
            }
        }
    }

    //仅支持平台和指定商铺
    foreach ($cart_goods as $val) {
        foreach ($shop_ids as $k => $v) {
            if ($val['ru_id'] > 0 && !in_array($val['ru_id'], $v)) {
                unset($shop_ids[$k]);
            }
        }
    }

    if (empty($shop_ids)) {
        return array('is_value_cart' => 0);
    } else {
        $value_card_ids = implode(",", array_keys($shop_ids));
    }

    if ($user_id > 0) {
        $where = " WHERE 1 ";
        $where .= " AND vc.user_id = '$user_id' AND vc.card_money > 0 AND vc.vid IN ($value_card_ids) ";

        $sql = " SELECT t.name, t.use_condition, t.spec_goods, t.spec_cat, vc.card_money, vc.vid FROM " . $GLOBALS['ecs']->table('value_card') . " AS vc " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('value_card_type') . " AS t ON vc.tid = t.id " . $where;
        $result = $GLOBALS['db']->getAll($sql);

        foreach ($result as $k => $v) {
            if (empty($v['use_condition'])) {//全部自营
                $arr[$k]['vc_id'] = $v['vid'];
                $arr[$k]['name'] = $v['name'];
                $arr[$k]['card_money'] = $v['card_money'];
            } elseif ($v['use_condition'] == 1) {//指定分类
                if (comparison_cat($cart_goods, $v['spec_cat'])) {
                    $arr[$k]['vc_id'] = $v['vid'];
                    $arr[$k]['name'] = $v['name'];
                    $arr[$k]['card_money'] = $v['card_money'];
                }
            } elseif ($v['use_condition'] == 2) {//指定商品
                if (comparison_goods($cart_goods, $v['spec_goods'])) {
                    $arr[$k]['vc_id'] = $v['vid'];
                    $arr[$k]['name'] = $v['name'];
                    $arr[$k]['card_money'] = $v['card_money'];
                }
            }
        }
    }

    return $arr;
}

/*
* 比对进货单内商品与储值卡使用条件是否一致
*/
function comparison_goods($cart_goods, $spec_goods) {
    $spec_goods = explode(',', $spec_goods);
    $error = 0;
    foreach ($cart_goods as $v) {
        if (!in_array($v['goods_id'], $spec_goods)) {
            $error += 1;
        }
    }
    if ($error > 0) {
        return false;
    } else {
        return true;
    }
}

/*
* 比对进货单内商品分类与储值卡使用条件是否一致
*/
function comparison_cat($cart_goods, $spec_cat) {
    $spec_cat = explode(',', $spec_cat);
    $error = 0;

    foreach ($spec_cat as $v) {
        $cat_keys = get_array_keys_cat($v);
        $cat[] = array_unique(array_merge(array($v), $cat_keys));
    }

    foreach ($cat as $v) {
        foreach ($v as $val) {
            $arr[] = $val;
        }
    }

    $arr = array_unique($arr);

    foreach ($cart_goods as $v) {
        if (!in_array($v['cat_id'], $arr)) {
            $error += 1;
        }
    }

    if ($error > 0) {
        return false;
    } else {
        return true;
    }
}

/**
 * 后台管理员ID
 */
function get_admin_name() {
    $self = explode("/", substr(PHP_SELF, 1));
    $count = count($self);

    $admin_name = '';
    if ($count > 1) {
        $real_path = $self[$count - 2];
        if ($real_path == ADMIN_PATH) {
            $admin_name = $_SESSION['admin_name'];
        } elseif ($real_path == SELLER_PATH) {
            $admin_name = $_SESSION['seller_name'];
        }
    }

    return $admin_name;
}

/**
 * 优惠活动
 * 查询全场通用优惠活动品牌值列表
 */
function return_act_range_ext($act_range_ext, $userFav_type, $act_range){

    if ($act_range_ext) {
        if ($userFav_type == 1 && $act_range == FAR_BRAND) {
            $id_list = explode(',', $act_range_ext);
            $brand_sql = "SELECT brand_id FROM " . $GLOBALS['ecs']->table('brand') . " WHERE brand_id " . db_create_in($id_list);
            $brand = $GLOBALS['db']->getCol($brand_sql);
            $id_list = !empty($brand) ? array_merge($id_list, $brand) : '';
            $id_list = array_unique($id_list);
            $act_range_ext = implode(",", $id_list);
        }
    }

    return $act_range_ext;
}

/**
 * 检测
 * 购物流程商品配送方式
 */
function get_flowdone_goods_list($cart_goods_list, $tmp_shipping_id_arr){

    if ($cart_goods_list && $tmp_shipping_id_arr) {
        foreach ($cart_goods_list as $key => $val) {
            foreach ($tmp_shipping_id_arr as $k => $v) {
                if ($v[1] > 0 && $val['ru_id'] == $v[0]) {
                    $cart_goods_list[$key]['tmp_shipping_id'] = $v[1];
                }
            }
        }
    }

    return $cart_goods_list;
}

/**
 * 获取商家信息
 */
function get_seller_info($ru_id = 0, $select = array()) {
    if ($select && is_array($select)) {
        $select = implode(",", $select);
    } else {
        $select = "*";
    }

    $sql = "SELECT $select FROM " . $GLOBALS['ecs']->table('seller_shopinfo') . " WHERE ru_id = '$ru_id' LIMIT 1";
    return $GLOBALS['db']->getRow($sql);
}

/**
 * 订单线上付款日志
 * $id 支付日志ID或订单ID
 */
function get_pay_log($id, $type = 0, $is_paid = null) {

    if ($type == 1) {
        $select = "log_id, is_paid";
        $where = "order_id = '$id'";
    } else {
        $select = "*";
        $where = "log_id = '$id'";
    }

    if (is_null($is_paid) && !is_array($is_paid)) {
        $where .= " AND is_paid = 0";
    } elseif (!is_null($is_paid) && !is_array($is_paid)) {
        $where .= " AND is_paid = '$is_paid'";
    }

    $sql = "SELECT " . $select . " FROM " . $GLOBALS['ecs']->table('pay_log') . " WHERE " . $where . " LIMIT 1";
    return $GLOBALS['db']->getRow($sql);
}

/*
* 配送区域ID start
*/
function region_parent($region_id) {

    $result = '';
    $sql = " SELECT parent_id FROM " . $GLOBALS['ecs']->table('region') . " WHERE region_id = '$region_id' ";
    $parent_id = $GLOBALS['db']->getOne($sql);
    if ($parent_id <= 1) {
        $result .= $parent_id . ",";
    } else {
        $result .= $parent_id . ",";
        $result .= region_parent($parent_id);
    }

    $result = get_del_str_comma($result);
    return $result;
}

function region_children($region_id) {
    $result = '';
    $sql = " SELECT region_id FROM " . $GLOBALS['ecs']->table('region') . " WHERE parent_id = '$region_id' ";
    $region_ids = $GLOBALS['db']->getAll($sql);

    $result = false;
    if (!empty($region_ids)) {

        foreach ($region_ids as $v) {
            $result .= $v['region_id'] . ',';
            $result .= region_children($v['region_id']);
        }

        $result = get_del_str_comma($result);
    }

    return $result;
}
/*
* 配送区域ID end
*/

?>
