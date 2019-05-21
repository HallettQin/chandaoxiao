<?php

/**
 * 公用函数库
 * ============================================================================
 * 版权所有 2016-2018 产供销网络科技(广州)有限公司，并保留所有权利。
 * 网站地址: http://www.chandaoxiao.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: Hallett
*/

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

/**
 * 创建像这样的查询: "IN('a','b')";
 *
 * @access   public
 * @param    mix      $item_list      列表数组或字符串
 * @param    string   $field_name     字段名称
 *
 * @return   void
 */
function db_create_in($item_list, $field_name = '', $not = '')
{
    if(!empty($not)){
        $not = " " . $not;
    }

    if (empty($item_list))
    {
        return $field_name  . $not . " IN ('') ";
    }
    else
    {
        if (!is_array($item_list))
        {
            $item_list = explode(',', $item_list);
        }
        $item_list = array_unique($item_list);
        $item_list_tmp = '';
        foreach ($item_list AS $item)
        {
            if ($item !== '')
            {
                $item = addslashes($item);
                $item_list_tmp .= $item_list_tmp ? ",'$item'" : "'$item'";
            }
        }
        if (empty($item_list_tmp))
        {
            return $field_name . $not . " IN ('') ";
        }
        else
        {
            return $field_name . $not . ' IN (' . $item_list_tmp . ') ';
        }
    }
}

/**
 * 验证输入的邮件地址是否合法
 *
 * @access  public
 * @param   string      $email      需要验证的邮件地址
 *
 * @return bool
 */
function is_email($user_email)
{
    $chars = "/^([a-z0-9+_]|\\-|\\.)+@(([a-z0-9_]|\\-)+\\.)+[a-z]{2,6}\$/i";
    if (strpos($user_email, '@') !== false && strpos($user_email, '.') !== false)
    {
        if (preg_match($chars, $user_email))
        {
            return true;
        }
        else
        {
            return false;
        }
    }
    else
    {
        return false;
    }
}


/**
 * 检查是否为一个合法的时间格式
 *
 * @access  public
 * @param   string  $time
 * @return  void
 */
function is_time($time)
{
    $pattern = '/[\d]{4}-[\d]{1,2}-[\d]{1,2}\s[\d]{1,2}:[\d]{1,2}:[\d]{1,2}/';

    return preg_match($pattern, $time);
}

/**
 * 获得查询时间和次数，并赋值给smarty
 *
 * @access  public
 * @return  void
 */
function assign_query_info()
{
    if ($GLOBALS['db']->queryTime == '')
    {
        $query_time = 0;
    }
    else
    {
        if (PHP_VERSION >= '5.0.0')
        {
            $query_time = number_format(microtime(true) - $GLOBALS['db']->queryTime, 6);
        }
        else
        {
            list($now_usec, $now_sec)     = explode(' ', microtime());
            list($start_usec, $start_sec) = explode(' ', $GLOBALS['db']->queryTime);
            $query_time = number_format(($now_sec - $start_sec) + ($now_usec - $start_usec), 6);
        }
    }
    $GLOBALS['smarty']->assign('query_info', sprintf($GLOBALS['_LANG']['query_info'], $GLOBALS['db']->queryCount, $query_time));

    /* 内存占用情况 */
    if ($GLOBALS['_LANG']['memory_info'] && function_exists('memory_get_usage'))
    {
        $GLOBALS['smarty']->assign('memory_info', sprintf($GLOBALS['_LANG']['memory_info'], memory_get_usage() / 1048576));
    }

    /* 是否启用了 gzip */
    $gzip_enabled = gzip_enabled() ? $GLOBALS['_LANG']['gzip_enabled'] : $GLOBALS['_LANG']['gzip_disabled'];
    $GLOBALS['smarty']->assign('gzip_enabled', $gzip_enabled);
}

/**
 * 创建地区的返回信息
 *
 * @access  public
 * @param   array   $arr    地区数组 *
 * @return  void
 */
function region_result($parent, $sel_name, $type)
{
    global $cp;

    $arr = get_regions($type, $parent);
    foreach ($arr AS $v)
    {
        $region      =& $cp->add_node('region');
        $region_id   =& $region->add_node('id');
        $region_name =& $region->add_node('name');

        $region_id->set_data($v['region_id']);
        $region_name->set_data($v['region_name']);
    }
    $select_obj =& $cp->add_node('select');
    $select_obj->set_data($sel_name);
}

/**
 * 获得指定国家的所有省份
 *
 * @access      public
 * @param       int     country    国家的编号
 * @return      array
 */
function get_regions($type = 0, $parent = 0)
{
    $sql = 'SELECT region_id, region_name FROM ' . $GLOBALS['ecs']->table('region') .
            " WHERE region_type = '$type' AND parent_id = '$parent'";

    return $GLOBALS['db']->GetAll($sql);
}

/**
 * 获得配送区域中指定的配送方式的配送费用的计算参数
 *
 * @access  public
 * @param   int     $area_id        配送区域ID
 *
 * @return array;
 */
function get_shipping_config($area_id)
{
    /* 获得配置信息 */
    $sql = 'SELECT configure FROM ' . $GLOBALS['ecs']->table('shipping_area') . " WHERE shipping_area_id = '$area_id'";
    $cfg = $GLOBALS['db']->GetOne($sql);

    if ($cfg)
    {
        /* 拆分成配置信息的数组 */
        $arr = unserialize($cfg);
    }
    else
    {
        $arr = array();
    }

    return $arr;
}

/**
 * 初始化会员数据整合类
 *
 * @access  public
 * @return  object
 */
function &init_users()
{
    $set_modules = false;
    static $cls = null;
    if ($cls != null)
    {
        return $cls;
    }
    include_once(ROOT_PATH . 'includes/modules/integrates/' . $GLOBALS['_CFG']['integrate_code'] . '.php');
    $cfg = unserialize($GLOBALS['_CFG']['integrate_config']);
    $cls = new $GLOBALS['_CFG']['integrate_code']($cfg);

    return $cls;
}

function cat_level_html($cat_list, $ru_id, $type = 0, $table = 'category') {

    $html = '';

    if ($cat_list) {
        foreach ($cat_list as $k => $row) {
            $sql = " select cat_id from " . $GLOBALS['ecs']->table($table) . " where parent_id='" . $row['cat_id'] . "'";
            $child_exist = $GLOBALS['db']->getOne($sql, true);
            if ($child_exist) {
                $show_status = "up";
            } else {
                $show_status = "down";
            }

            $html .= '<tr align="center" id="' . $row['level'] . '_' . $row['cat_id'] . '" class="' . $row['parent_id'] . '_' . $row['level'] . '">
                            <td align="left" id="level_' . $row['level'] . '_' . $row['cat_id'] . '" class="first-cell"><div class="first_column">';
            if ($row['is_leaf'] != 1) {
                $html .= '<i data-level="' . $row['level'] . '" data-catid="' . $row['cat_id'] . '" data-isclick="0" style="margin-left:' . $row['level'] . 'em;" id="icon_' . $row['level'] . '_' . $row['cat_id'] . '" class="' . $show_status . '"></i>';
            } else {
                $html .= '<img width="9" height="9" border="0" style="margin-left:' . $row['level'] . 'em;vertical-align:middle; margin-top:-1px;" id="icon_' . $row['level'] . '_' . $row['cat_id'] . '" src="images/menu_arrow.gif">';
            }

            $html .= '<span><a href="goods.php?act=list&amp;cat_id=' . $row['cat_id'] . '&cat_type=seller">' . $row['cat_name'] . '</a></span>';

            if ($row['cat_image']) {
                $html .= '<img src="../' . $row['cat_image'] . '" border="0" style="vertical-align:middle;" width="60px" height="21px">';
            }
            $html .= '</div></td>';



            if ($type == 1) {
                if ($ru_id == 0) {
                    $html .= '<td style="color:#F00;">' . $row['user_name'] . '</td>';
                }
            }

            $html .= '<td>' . $row['goods_num'] . '</td>';

            $html .= '<td><span onclick="listTable.edit(this, ' . "'edit_measure_unit'" . ', ' . $row['cat_id'] . ')">' . $row['measure_unit'] . '</span></td>';
            $html .= '</td>';

            if ($ru_id == 0) {
                if ($row['show_in_nav'] == 1) {
                    $html .= '<td><img onclick="listTable.toggle(this, ' . "'toggle_show_in_nav'" . ', ' . $row['cat_id'] . ')" src="images/yes.gif"></td>';
                } else {
                    $html .= '<td><img onclick="listTable.toggle(this, ' . "'toggle_show_in_nav'" . ', ' . $row['cat_id'] . ')" src="images/no.gif"></td>';
                }

                if ($row['is_show'] == 1) {
                    $html .= '<td><img onclick="listTable.toggle(this, ' . "'toggle_is_show'" . ', ' . $row['cat_id'] . ')" src="images/yes.gif"></td>';
                } else {
                    $html .= '<td><img onclick="listTable.toggle(this, ' . "'toggle_is_show'" . ', ' . $row['cat_id'] . ')" src="images/no.gif"></td>';
                }
            }

            if ($type == 0) {
                if ($ru_id == 0) {
                    $html .= '<td><span onclick="listTable.edit(this, ' . "'edit_grade'" . ', ' . $row['cat_id'] . ')">' . $row['grade'] . '</span></td>';
                }
            } else {
                $html .= '<td><span onclick="listTable.edit(this, ' . "'edit_grade'" . ', ' . $row['cat_id'] . ')">' . $row['grade'] . '</span></td>';
                $html .= '<td align="center"><span onclick="listTable.edit(this, ' . "'edit_sort_order'" . ', ' . $row['cat_id'] . ')">' . $row['sort_order'] . '</span></td>';
            }

            if ($type == 1) {
                if($row['is_show']){
                    $html .= '<td align="center"><img src="images/yes.gif" onclick="listTable.toggle(this, ' . "'toggle_is_show'" . ', ' . $row['cat_id'] . ')" title="点击" class="pointer" /></td>';
                }else{
                    $html .= '<td align="center"><img src="images/no.gif" onclick="listTable.toggle(this, ' . "'toggle_is_show'" . ', ' . $row['cat_id'] . ')" title="点击" class="pointer" /></td>';
                }
            }

            $html .= '<td align="center">';

            if($type == 1){
                $html .= '<a href="category_store.php?act=move&amp;cat_id=' . $row['cat_id'] . '" class="blue">转移商品</a>';
            }else{
                $html .= '<a href="category.php?act=move&amp;cat_id=' . $row['cat_id'] . '" class="blue">转移商品</a>';
            }

            if ($ru_id == 0) {
                $html .= ' |
                                <a href="category.php?act=edit&amp;cat_id=' . $row['cat_id'] . '" class="blue">编辑</a> |
                                <a title="移除" href="javascript:;" onclick="listTable.remove(' . $row['cat_id'] . ',' . "'您确定要删除吗？'" . ')" class="blue">移除</a>';
            }

            if ($ru_id && $row['ru_id']) {
                $html .= ' |
                                    <a href="category_store.php?act=edit&amp;cat_id=' . $row['cat_id'] . '" class="blue">编辑</a> |
                                    <a title="移除" onclick="listTable.remove(' . $row['cat_id'] . ',' . "'您确定要删除吗？'" . ')" href="javascript:;" class="blue">移除</a>';
            }
            $html .= '</td>
                        </tr>';
        }
    }

    return $html;
}
//后台商品分类 end

//循环加载 start
function flush_echo($data)
{
	ob_end_flush();
	ob_implicit_flush(true);
	echo $data;
}

function show_js_message($message,$ext=0)
{
	flush_echo('<script type="text/javascript">showmessage(\''.addslashes($message).'\','.$ext.');</script>'."\r\n");
}

function sc_stime(){
    return gmtime() + microtime();
}

function sc_timer($stime)
{
	$etime = gmtime() + microtime();
	$pass_time = sprintf("%.2f", $etime-$stime);

	//消耗时间
        return $pass_time;
}
//循环加载 end

/**
 * 过滤和排序所有分类，返回一个带有缩进级别的数组
 *
 * @access  private
 * @param   int     $cat_id     上级分类ID
 * @param   array   $arr        含有所有分类的数组
 * @param   int     $level      级别
 * @return  void
 */
function cat_options($spec_cat_id, $arr)
{
    static $cat_options = array();

    if (isset($cat_options[$spec_cat_id]))
    {
        return $cat_options[$spec_cat_id];
    }

    if (!isset($cat_options[0]))
    {
        $level = $last_cat_id = 0;
        $options = $cat_id_array = $level_array = array();
        $data = read_static_cache('cat_option_static');
        if ($data === false)
        {
            while (!empty($arr))
            {
                foreach ($arr AS $key => $value)
                {
                    $cat_id = $value['cat_id'];
                    if ($level == 0 && $last_cat_id == 0)
                    {
                        if ($value['parent_id'] > 0)
                        {
                            break;
                        }

                        $options[$cat_id]          = $value;
                        $options[$cat_id]['level'] = $level;
                        $options[$cat_id]['id']    = $cat_id;
                        $options[$cat_id]['name']  = $value['cat_name'];
                        unset($arr[$key]);

                        if ($value['has_children'] == 0)
                        {
                            continue;
                        }
                        $last_cat_id  = $cat_id;
                        $cat_id_array = array($cat_id);
                        $level_array[$last_cat_id] = ++$level;
                        continue;
                    }

                    if ($value['parent_id'] == $last_cat_id)
                    {
                        $options[$cat_id]          = $value;
                        $options[$cat_id]['level'] = $level;
                        $options[$cat_id]['id']    = $cat_id;
                        $options[$cat_id]['name']  = $value['cat_name'];
                        unset($arr[$key]);

                        if ($value['has_children'] > 0)
                        {
                            if (end($cat_id_array) != $last_cat_id)
                            {
                                $cat_id_array[] = $last_cat_id;
                            }
                            $last_cat_id    = $cat_id;
                            $cat_id_array[] = $cat_id;
                            $level_array[$last_cat_id] = ++$level;
                        }
                    }
                    elseif ($value['parent_id'] > $last_cat_id)
                    {
                        break;
                    }
                }

                $count = count($cat_id_array);
                if ($count > 1)
                {
                    $last_cat_id = array_pop($cat_id_array);
                }
                elseif ($count == 1)
                {
                    if ($last_cat_id != end($cat_id_array))
                    {
                        $last_cat_id = end($cat_id_array);
                    }
                    else
                    {
                        $level = 0;
                        $last_cat_id = 0;
                        $cat_id_array = array();
                        continue;
                    }
                }

                if ($last_cat_id && isset($level_array[$last_cat_id]))
                {
                    $level = $level_array[$last_cat_id];
                }
                else
                {
                    $level = 0;
                }
            }
            //如果数组过大，不采用静态缓存方式
            if (count($options) <= 2000)
            {
                write_static_cache('cat_option_static', $options);
            }
        }
        else
        {
            $options = $data;
        }
        $cat_options[0] = $options;
    }
    else
    {
        $options = $cat_options[0];
    }

    if (!$spec_cat_id)
    {
        return $options;
    }
    else
    {
        if (empty($options[$spec_cat_id]))
        {
            return array();
        }

        $spec_cat_id_level = $options[$spec_cat_id]['level'];

        foreach ($options AS $key => $value)
        {
            if ($key != $spec_cat_id)
            {
                unset($options[$key]);
            }
            else
            {
                break;
            }
        }

        $spec_cat_id_array = array();
        foreach ($options AS $key => $value)
        {
            if (($spec_cat_id_level == $value['level'] && $value['cat_id'] != $spec_cat_id) ||
                ($spec_cat_id_level > $value['level']))
            {
                break;
            }
            else
            {
                $spec_cat_id_array[$key] = $value;
            }
        }
        $cat_options[$spec_cat_id] = $spec_cat_id_array;

        return $spec_cat_id_array;
    }
}

/**
 * 载入配置信息
 *
 * @access  public
 * @return  array
 */
function load_config()
{
    $arr = array();
    $certi_url = '';

    $data = read_static_cache('shop_config');
    if ($data === false || empty($data))
    {
        $sql = 'SELECT code, value FROM ' . $GLOBALS['ecs']->table('shop_config') . ' WHERE parent_id > 0';
        $res = $GLOBALS['db']->getAll($sql);

        foreach ($res AS $row)
        {
            $arr[$row['code']] = $row['value'];
        }
        /*处理客服QQ数组 by kong*/
        if($arr['qq']){
            $kf_qq=array_filter(preg_split('/\s+/', $arr['qq']));
            if(!empty($kf_qq[0])){
                $kf_qq=explode("|",$kf_qq[0]);
                if($kf_qq){
                    if(!empty($kf_qq[1])){
                        $kf_qq_one = $kf_qq[1];
                    }
                }
            }
        }else{
            $kf_qq_one = "";
        }
        /*处理客服旺旺数组 by kong*/
        if($arr['ww']){
            $kf_ww=array_filter(preg_split('/\s+/', $arr['ww']));
            if(!empty($kf_ww[0])){
                $kf_ww=explode("|",$kf_ww[0]);
                if(!empty($kf_ww[1])){
                    $kf_ww_one = $kf_ww[1];
                }else{
                    $kf_ww_one ="";
                }
            }
        }else{
            $kf_ww_one ="";
        }

        $certi_url = 'http://ecshop.ecmoban.com/dsc.php';
        if(empty($arr['certi']) || $arr['certi'] != $certi_url){
            $sql = 'UPDATE ' . $GLOBALS['ecs']->table('shop_config') . " SET value = '$certi_url' WHERE code = 'certi'";
            $row = $GLOBALS['db']->query($sql);
        }

        $arr['certi']                = isset($arr['default_storage']) && !empty($arr['certi']) ? $arr['certi'] : $certi_url;

        /* 对数值型设置处理 */
        $arr['watermark_alpha']      = intval($arr['watermark_alpha']);
        $arr['market_price_rate']    = floatval($arr['market_price_rate']);
        $arr['integral_scale']       = floatval($arr['integral_scale']);
        //$arr['integral_percent']     = floatval($arr['integral_percent']);
        $arr['cache_time']           = intval($arr['cache_time']);
        $arr['thumb_width']          = intval($arr['thumb_width']);
        $arr['thumb_height']         = intval($arr['thumb_height']);
        $arr['image_width']          = intval($arr['image_width']);
        $arr['image_height']         = intval($arr['image_height']);
        $arr['best_number']          = !empty($arr['best_number']) && intval($arr['best_number']) > 0 ? intval($arr['best_number'])     : 3;
        $arr['new_number']           = !empty($arr['new_number']) && intval($arr['new_number']) > 0 ? intval($arr['new_number'])      : 3;
        $arr['hot_number']           = !empty($arr['hot_number']) && intval($arr['hot_number']) > 0 ? intval($arr['hot_number'])      : 3;
        $arr['promote_number']       = !empty($arr['promote_number']) && intval($arr['promote_number']) > 0 ? intval($arr['promote_number'])  : 3;
        $arr['top_number']           = intval($arr['top_number'])      > 0 ? intval($arr['top_number'])      : 10;
        $arr['history_number']       = intval($arr['history_number'])  > 0 ? intval($arr['history_number'])  : 5;
        $arr['comments_number']      = intval($arr['comments_number']) > 0 ? intval($arr['comments_number']) : 5;
        $arr['article_number']       = intval($arr['article_number'])  > 0 ? intval($arr['article_number'])  : 5;
        $arr['page_size']            = intval($arr['page_size'])       > 0 ? intval($arr['page_size'])       : 10;
        $arr['bought_goods']         = intval($arr['bought_goods']);
        $arr['goods_name_length']    = intval($arr['goods_name_length']);
        $arr['top10_time']           = intval($arr['top10_time']);
        $arr['goods_gallery_number'] = intval($arr['goods_gallery_number']) ? intval($arr['goods_gallery_number']) : 5;
        $arr['no_picture']           = !empty($arr['no_picture']) ? $arr['no_picture'] : 'images/no_picture.gif'; // 修改默认商品图片的路径
        $arr['qq']                   = !empty($kf_qq_one) ? $kf_qq_one : '';// by kong 改
        $arr['ww']                   = !empty($kf_ww_one) ? $kf_ww_one : '';// by kong 改
        $arr['default_storage']      = isset($arr['default_storage']) ? intval($arr['default_storage']) : 1;
        $arr['min_goods_amount']     = isset($arr['min_goods_amount']) ? floatval($arr['min_goods_amount']) : 0;
        $arr['one_step_buy']         = empty($arr['one_step_buy']) ? 0 : 1;
        $arr['invoice_type']         = !isset($arr['invoice_type']) && empty($arr['invoice_type']) ? array('type' => array(), 'rate' => array()) : $arr['invoice_type'];
        $arr['show_order_type']      = isset($arr['show_order_type']) ? $arr['show_order_type'] : 0;    // 显示方式默认为列表方式
        $arr['help_open']            = isset($arr['help_open']) ? $arr['help_open'] : 1;    // 显示方式默认为列表方式

        $arr['cat_belongs']            = isset($arr['cat_belongs']) ? $arr['cat_belongs'] : 0;

        if (!is_array($arr['invoice_type'])) {
            $arr['invoice_type'] = dsc_unserialize($arr['invoice_type']);
        }

        if (!isset($GLOBALS['_CFG']['dsc_version']))
        {
            /* 如果没有版本号则默认为2.0.5 */
            $GLOBALS['_CFG']['dsc_version'] = 'v1.0';
        }

        //限定语言项
        $lang_array = array('zh_cn', 'zh_tw', 'en_us');
        if (empty($arr['lang']) || !in_array($arr['lang'], $lang_array))
        {
            $arr['lang'] = 'zh_cn'; // 默认语言为简体中文
        }

        if (empty($arr['integrate_code']))
        {
            $arr['integrate_code'] = 'ecshop'; // 默认的会员整合插件为 ecshop
        }

        $arr['site_domain'] = get_site_domain($arr['site_domain']);

        write_static_cache('shop_config', $arr);
    }
    else
    {
        $certi_url   = $GLOBALS['db']->getOne("SELECT value FROM ".$GLOBALS['ecs']->table('shop_config')." WHERE code = 'certi'");
        $certi_size = 'http://ecshop.ecmoban.com/dsc.php';
        if(empty($certi_url) || $certi_url != $certi_size){
            $sql = 'UPDATE ' . $GLOBALS['ecs']->table('shop_config') . " SET value = '$certi_size' WHERE code = 'certi'";
            $row = $GLOBALS['db']->query($sql);
        }

        $data['site_domain'] = isset($data['site_domain']) && !empty($data['site_domain']) ? $data['site_domain'] : '';
        $data['site_domain'] = get_site_domain($data['site_domain']);

        $arr = $data;
    }

    return $arr;
}

/**
 * 取得品牌列表
 * @return array 品牌列表 id => name
 */
function get_brand_list($goods_id = 0, $type = 0, $ru_id = 0) {

    if ($goods_id > 0) {
        $sql = "SELECT user_id FROM " . $GLOBALS['ecs']->table('goods') . " WHERE goods_id = '$goods_id'";
        $seller_id = $GLOBALS['db']->getOne($sql, true);
    } else {
        if ($ru_id > 0) {
            $seller_id = $ru_id;
        } else {
            $adminru = get_admin_ru_id();
            $seller_id = $adminru['ru_id'];
        }
    }

    if ($type == 2) {
        $sql = 'SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table('brand') . ' WHERE 1';
        $brand_list = $GLOBALS['db']->getOne($sql, true);
    } else {
        $sql = 'SELECT brand_id, brand_name, brand_first_char FROM ' . $GLOBALS['ecs']->table('brand') . ' ORDER BY sort_order';
        $res = $GLOBALS['db']->getAll($sql);

        $brand_list = array();
        foreach ($res AS $key => $row) {

            if ($seller_id) {
                $val['is_brand'] = get_seller_brand_count($row['brand_id'], $seller_id);
            } else {
                $val['is_brand'] = 1;
            }

            if ($val['is_brand'] > 0) {
                if ($type == 1) {
                    $brand_list[$key]['brand_id'] = $row['brand_id'];
                    $brand_list[$key]['brand_name'] = addslashes($row['brand_name']);
                    $brand_list[$key]['brand_first_char'] = $row['brand_first_char'];
                } else {
                    $brand_list[$row['brand_id']] = addslashes($row['brand_name']);
                }
            }else{
                unset($brand_list[$row['brand_id']]);
            }
        }
    }

    if($brand_list && is_array($brand_list)){
        $brand_list = array_values($brand_list);
    }

    return $brand_list;
}

/**
 * 取得商家品牌列表
 * @return array 品牌列表 id => name
 */
function get_store_brand_list()
{
    //ecmoban模板堂 --zhuo
    $sql = 'SELECT bid, brandName FROM ' . $GLOBALS['ecs']->table('merchants_shop_brand') . " where user_id > 0 AND audit_status = 1 ORDER BY bid ASC";
    $res = $GLOBALS['db']->getAll($sql);

    $brand_list = array();
    foreach ($res AS $row) {
        $brand_list[$row['bid']] = addslashes($row['brandName']);
    }

    return $brand_list;
}

/**
 * 获得某个分类下
 *
 * @access  public
 * @param   int     $cat
 * @return  array
 */
function get_brands($cat = 0, $app = 'brand', $num = 0, $page = 1, $page_size = 8)
{
    global $page_libs;
    $template = basename(PHP_SELF);
    $template = substr($template, 0, strrpos($template, '.'));
    static $static_page_libs = null;
    if ($static_page_libs == null)
    {
            $static_page_libs = $page_libs;
    }

    $row = read_static_cache('get_brands_list'.$cat, '/temp/static_caches/');
    if ($row === false) {
        $children = ($cat > 0) ? '1 AND ' . get_children($cat) : 1;

        $sql = "SELECT b.brand_id, b.brand_name, b.brand_logo, b.index_img, b.brand_desc, COUNT(*) AS goods_num, IF(b.brand_logo > '', '1', '0') AS tag, b.site_url ".
                "FROM " . $GLOBALS['ecs']->table('brand') . "AS b ".
                " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON g.brand_id = b.brand_id AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 ".
                "WHERE $children AND b.is_show = 1 " .
                "GROUP BY b.brand_id HAVING goods_num > 0 ORDER BY tag DESC, b.sort_order ASC";

        if (isset($static_page_libs[$template]['/library/brands.lbi']))
        {
            $num = get_library_number("brands");
            $sql .= " LIMIT $num ";
        }else if($num > 0){
            $sql .= " LIMIT $num ";
        }

        $row = $GLOBALS['db']->getAll($sql);

        foreach ($row AS $key => $val)
        {
            if($val['site_url'] && strlen($val['site_url']) > 8){
                $row[$key]['url'] = $val['site_url'];
            }else{
                $row[$key]['url'] = build_uri($app, array('cid' => $cat, 'bid' => $val['brand_id']), $val['brand_name']);
            }

            $row[$key]['brand_desc'] = htmlspecialchars($val['brand_desc'],ENT_QUOTES);
            $row[$key]['brand_logo'] = DATA_DIR . '/brandlogo/'.$val['brand_logo'];//by wang
            $row[$key]['index_img'] = empty($val['index_img']) ? '' : DATA_DIR . '/indeximg/'.$val['index_img']; //品牌专区大图 by wu

            //OSS文件存储ecmoban模板堂 --zhuo start
            if($GLOBALS['_CFG']['open_oss'] == 1){
                $bucket_info = get_bucket_info();
                $row[$key]['brand_logo'] = $bucket_info['endpoint'] . DATA_DIR . '/brandlogo/'.$val['brand_logo'];
                $row[$key]['index_img'] = empty($val['index_img']) ? '' : $bucket_info['endpoint'] . DATA_DIR . '/indeximg/'.$val['index_img']; //品牌专区大图 by wu
            }
            //OSS文件存储ecmoban模板堂 --zhuo end
            //获取是否收藏
            if(defined('THEME_EXTENSION') && $_SESSION['user_id'] > 0){
                $row[$key]['is_collect'] = get_collect_user_brand($val['brand_id']);
            }
        }

        write_static_cache('get_brands_list'.$cat, $row, '/temp/static_caches/');
    }

    if (defined('THEME_EXTENSION')) {
        $page_array = $GLOBALS['ecs']->page_array($page_size, $page, $row);
        $row = $page_array['list'];
    }

    return $row;
}

//by wang 楼层品牌
function get_floor_brand($brand_ids) {
    $row = array();

    if (is_array($brand_ids)) {
        $sql = "SELECT brand_id, brand_name, brand_logo, brand_desc from " . $GLOBALS['ecs']->table('brand') . " where brand_id " . db_create_in($brand_ids);

        $row = $GLOBALS['db']->getAll($sql);

        foreach ($row AS $key => $val) {
            $row[$key]['url'] = build_uri('brandn', array('bid' => $val['brand_id']), $val['brand_name']);
            $row[$key]['brand_desc'] = htmlspecialchars($val['brand_desc'], ENT_QUOTES);
            $row[$key]['brand_logo'] = DATA_DIR . '/brandlogo/' . $val['brand_logo']; //by wang
            //OSS文件存储ecmoban模板堂 --zhuo start
            if ($GLOBALS['_CFG']['open_oss'] == 1 && $val['brand_logo']) {
                $bucket_info = get_bucket_info();
                $row[$key]['brand_logo'] = $bucket_info['endpoint'] . DATA_DIR . '/brandlogo/' . $val['brand_logo'];
            }
            //OSS文件存储ecmoban模板堂 --zhuo end
        }
    }
    return $row;
}

//检测分类下是否存在有商品的品牌 by wang
function cat_brand_count($cat = 0)
{
    $children = ($cat > 0) ? '1 AND ' . get_children($cat) : 1;

    $sql = "SELECT b.brand_id, b.brand_name, b.brand_logo, b.brand_desc, COUNT(*) AS goods_num, IF(b.brand_logo > '', '1', '0') AS tag ".
            "FROM " . $GLOBALS['ecs']->table('brand') . "AS b ".
            " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON g.brand_id = b.brand_id AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 ".
            "WHERE $children AND b.is_show = 1 " .
            "GROUP BY b.brand_id HAVING goods_num > 0 ORDER BY tag DESC, b.sort_order ASC LIMIT 0,1";

    $row = $GLOBALS['db']->getAll($sql);

    return $row;
}

/**
 *  所有的促销活动信息
 *
 * @access  public
 * @return  array
 */
function get_promotion_info($goods_id = '', $ru_id = 0)
{
    $snatch = array();
    $group = array();
    $auction = array();
    $package = array();
    $favourable = array();
    $list_array = array();

    $gmtime = gmtime();
    $sql = 'SELECT act_id, act_name, act_type, start_time, end_time FROM ' . $GLOBALS['ecs']->table('goods_activity') . " WHERE review_status = 3 AND is_finished=0 AND start_time <= '$gmtime' AND end_time >= '$gmtime' AND user_id = '$ru_id'";
    if(!empty($goods_id))
    {
        $sql .= " AND goods_id = '$goods_id'";
    }

    $res = $GLOBALS['db']->getAll($sql);
    foreach ($res as $data)
    {
        switch ($data['act_type'])
        {
            case GAT_SNATCH: //夺宝奇兵
                $snatch[$data['act_id']]['act_name'] = $data['act_name'];
                $snatch[$data['act_id']]['url'] = build_uri('snatch', array('sid' => $data['act_id']));
                $snatch[$data['act_id']]['time'] = sprintf($GLOBALS['_LANG']['promotion_time'], local_date('Y-m-d', $data['start_time']), local_date('Y-m-d', $data['end_time']));
                $snatch[$data['act_id']]['sort'] = $data['start_time'];
                $snatch[$data['act_id']]['type'] = 'snatch';
                break;

            case GAT_GROUP_BUY: //团购
                $group[$data['act_id']]['act_name'] = $data['act_name'];
                $group[$data['act_id']]['url'] = build_uri('group_buy', array('gbid' => $data['act_id']));
                $group[$data['act_id']]['time'] = sprintf($GLOBALS['_LANG']['promotion_time'], local_date('Y-m-d', $data['start_time']), local_date('Y-m-d', $data['end_time']));
                $group[$data['act_id']]['sort'] = $data['start_time'];
                $group[$data['act_id']]['type'] = 'group_buy';
                break;

            case GAT_AUCTION: //拍卖
                $auction[$data['act_id']]['act_name'] = $data['act_name'];
                $auction[$data['act_id']]['url'] = build_uri('auction', array('auid' => $data['act_id']));
                $auction[$data['act_id']]['time'] = sprintf($GLOBALS['_LANG']['promotion_time'], local_date('Y-m-d', $data['start_time']), local_date('Y-m-d', $data['end_time']));
                $auction[$data['act_id']]['sort'] = $data['start_time'];
                $auction[$data['act_id']]['type'] = 'auction';
                break;

            case GAT_PACKAGE: //礼包
                $package[$data['act_id']]['act_name'] = $data['act_name'];
                $package[$data['act_id']]['url'] = 'package.php#' . $data['act_id'];
                $package[$data['act_id']]['time'] = sprintf($GLOBALS['_LANG']['promotion_time'], local_date('Y-m-d', $data['start_time']), local_date('Y-m-d', $data['end_time']));
                $package[$data['act_id']]['sort'] = $data['start_time'];
                $package[$data['act_id']]['type'] = 'package';
                break;
        }
    }

    if($ru_id > 0){
        $fav_where = "(user_id = '$ru_id' OR userFav_type = 1)";
    }else{
        $fav_where = "user_id = '$ru_id'";
    }

    $user_rank = ',' . $_SESSION['user_rank'] . ',';
    $favourable = array();
    $sql = 'SELECT act_id, act_range, act_range_ext, act_name, start_time, end_time, act_type, userFav_type FROM ' . $GLOBALS['ecs']->table('favourable_activity') . " WHERE review_status = 3 AND start_time <= '$gmtime' AND end_time >= '$gmtime' AND " . $fav_where;
    if(!empty($goods_id))
    {
        $sql .= " AND CONCAT(',', user_rank, ',') LIKE '%" . $user_rank . "%'";
    }

    $res = $GLOBALS['db']->getAll($sql);

    if(empty($goods_id))
    {
        foreach ($res as $rows)
        {
            $favourable[$rows['act_id']]['act_name'] = $rows['act_name'];
            $favourable[$rows['act_id']]['url'] = 'activity.php';
            $favourable[$rows['act_id']]['time'] = sprintf($GLOBALS['_LANG']['promotion_time'], local_date('Y-m-d', $rows['start_time']), local_date('Y-m-d', $rows['end_time']));
            $favourable[$rows['act_id']]['sort'] = $rows['start_time'];
            $favourable[$rows['act_id']]['type'] = 'favourable';
            $favourable[$rows['act_id']]['act_type'] = $rows['act_type'];
        }
    }
    else
    {
        $sql = "SELECT g.cat_id, g.brand_id FROM " . $GLOBALS['ecs']->table('goods') ." as g".
		   $leftJoin.
           " WHERE g.goods_id = '$goods_id' LIMIT 1";
        $row = $GLOBALS['db']->getRow($sql);

        $category_id = $row['cat_id'];
        $brand_id = $row['brand_id'];

        foreach ($res as $rows)
        {
            if ($rows['act_range'] == FAR_ALL)
            {
                $favourable[$rows['act_id']]['act_name'] = $rows['act_name'];
                $favourable[$rows['act_id']]['url'] = 'activity.php';
                $favourable[$rows['act_id']]['time'] = sprintf($GLOBALS['_LANG']['promotion_time'], local_date('Y-m-d', $rows['start_time']), local_date('Y-m-d', $rows['end_time']));
                $favourable[$rows['act_id']]['sort'] = $rows['start_time'];
                $favourable[$rows['act_id']]['type'] = 'favourable';
                $favourable[$rows['act_id']]['act_type'] = $rows['act_type'];
            }
            elseif ($rows['act_range'] == FAR_CATEGORY)
            {
                /* 找出分类id的子分类id */
                $id_list = array();
                $raw_id_list = explode(',', $rows['act_range_ext']);

                foreach ($raw_id_list as $id)
                {
                    /**
                    * 当前分类下的所有子分类
                    * 返回一维数组
                    */
                   $cat_keys = get_array_keys_cat(intval($id));
                   $list_array[$rows['act_id']][$id] = $cat_keys;
                }

                $list_array = !empty($list_array) ? array_merge($raw_id_list, $list_array[$rows['act_id']]) : $raw_id_list;
                $id_list = arr_foreach($list_array);
                $id_list = array_unique($id_list);

                $ids = join(',', array_unique($id_list));

                if (strpos(',' . $ids . ',', ',' . $category_id . ',') !== false)
                {
                    $favourable[$rows['act_id']]['act_name'] = $rows['act_name'];
                    $favourable[$rows['act_id']]['url'] = 'activity.php';
                    $favourable[$rows['act_id']]['time'] = sprintf($GLOBALS['_LANG']['promotion_time'], local_date('Y-m-d', $rows['start_time']), local_date('Y-m-d', $rows['end_time']));
                    $favourable[$rows['act_id']]['sort'] = $rows['start_time'];
                    $favourable[$rows['act_id']]['type'] = 'favourable';
                    $favourable[$rows['act_id']]['act_type'] = $rows['act_type'];
                }
            }
            elseif ($rows['act_range'] == FAR_BRAND)
            {
                $rows['act_range_ext'] = return_act_range_ext($rows['act_range_ext'], $rows['userFav_type'], $rows['act_range']);
                if (strpos(',' . $rows['act_range_ext'] . ',', ',' . $brand_id . ',') !== false)
                {
                    $favourable[$rows['act_id']]['act_name'] = $rows['act_name'];
                    $favourable[$rows['act_id']]['url'] = 'activity.php';
                    $favourable[$rows['act_id']]['time'] = sprintf($GLOBALS['_LANG']['promotion_time'], local_date('Y-m-d', $rows['start_time']), local_date('Y-m-d', $rows['end_time']));
                    $favourable[$rows['act_id']]['sort'] = $rows['start_time'];
                    $favourable[$rows['act_id']]['type'] = 'favourable';
                    $favourable[$rows['act_id']]['act_type'] = $rows['act_type'];
                }
            }
            elseif ($rows['act_range'] == FAR_GOODS)
            {
                if (strpos(',' . $rows['act_range_ext'] . ',', ',' . $goods_id . ',') !== false)
                {
                    $favourable[$rows['act_id']]['act_name'] = $rows['act_name'];
                    $favourable[$rows['act_id']]['url'] = 'activity.php';
                    $favourable[$rows['act_id']]['time'] = sprintf($GLOBALS['_LANG']['promotion_time'], local_date('Y-m-d', $rows['start_time']), local_date('Y-m-d', $rows['end_time']));
                    $favourable[$rows['act_id']]['sort'] = $rows['start_time'];
                    $favourable[$rows['act_id']]['type'] = 'favourable';
                    $favourable[$rows['act_id']]['act_type'] = $rows['act_type'];
                }
            }
        }
    }

    $sort_time = array();
    $arr = array_merge($snatch, $group, $auction, $package, $favourable);
    foreach($arr as $key => $value)
    {
        $sort_time[] = $value['sort'];
    }
    array_multisort($sort_time, SORT_NUMERIC, SORT_DESC, $arr);

    return $arr;
}

/**
 * 获得指定分类下的子分类的数组
 *
 * @access  public
 * @param   int     $cat_id     分类的ID
 * @param   int     $type   查子分类
 * @param   int     $get_rid   去掉其它，保留分类ID
 * @param   string  $table   表名称
 * @param   string  $seller_shop_cat 商家分类集
 * @param   int     $cat_level 层级
 * @param   int     $user_id 商家ID
 * @return  mix
 */
function cat_list($cat_id = 0, $type = 0, $getrid = 0, $table = 'category', $seller_shop_cat = array(), $cat_level = 0, $user_id = 0) {
    if($getrid == 0){
        $select = ', cat_name, cat_alias_name';
        if($table == 'merchants_category'){
            $select .= ', user_id';
        }elseif($table == 'category'){
			$select .= ', cat_icon, style_icon';
		}
    }else{
        $select = '';
    }

    $where = '';
    if ($seller_shop_cat) {
        if ($seller_shop_cat['parent'] && $seller_shop_cat['parent'] && $cat_level < 3) {
            $seller_shop_cat['parent'] = get_del_str_comma($seller_shop_cat['parent']);
            $where .= " AND cat_id IN(" . $seller_shop_cat['parent'] . ")";
        }
    }

    if($table == 'merchants_category' && $user_id){
        $where .= " AND user_id = '$user_id'";
    }

    $sql = "SELECT cat_id $select FROM " . $GLOBALS['ecs']->table($table) . " WHERE parent_id = '$cat_id' $where AND is_show = 1 ORDER BY sort_order ASC, cat_id ASC";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();

    if ($res) {
        foreach ($res as $key => $row) {

            if($getrid == 0){
                $row['cat_name'] =  htmlspecialchars(addslashes(str_replace("\r\n","",$row['cat_name'])), ENT_QUOTES);//特殊字符处理
                $row['level'] = 0;
                $row['select'] = str_repeat('&nbsp;', $row['level'] * 4);
                $arr[$row['cat_id']] = $row;

                if($table == 'merchants_category'){

                    $build_uri = array(
                        'cid' => $row['cat_id'],
                        'urid' => $row['user_id'],
                        'append' => $row['cat_name']
                    );

                    $domain_url = get_seller_domain_url($row['user_id'], $build_uri);
                    $arr[$row['cat_id']]['url'] = $domain_url['domain_name'];
                }else{
                    $arr[$row['cat_id']]['url'] = build_uri('category', array('cid' => $row['cat_id']), $row['cat_name']);
                }
            }else{
                $arr[$row['cat_id']]['cat_id'] = $row['cat_id'];
            }

            if ($type) {
                $arr[$row['cat_id']]['child_tree'] = get_child_tree_pro($row['cat_id'], 0, $table, $getrid, $user_id);
            }
            //图标
            if (defined('THEME_EXTENSION') && $getrid == 0 && $table == 'category') {
                $arr[$row['cat_id']]['cat_icon'] = $row['cat_icon'];
                $arr[$row['cat_id']]['style_icon'] = $row['style_icon'];
            }
        }
    }

    return $arr;
}

/**
 * 获得指定分类下所有底层分类的ID
 *
 * @access  public
 * @param   integer     $cat        指定的分类ID
 * @return  string
 */
function get_children($cat = 0, $type = 0, $child_three = 0, $table = 'category', $type_cat = '')
{
    /**
     * 当前分类下的所有子分类
     * 返回一维数组
     */
    $cat_keys = get_array_keys_cat($cat, 0, $table);

    if($type != 2){
        if (empty($type_cat)) {
            if ($type == 1) {
                $type_cat = 'gc.cat_id ';
            } elseif ($type == 3) {
                $type_cat = 'wc.cat_id ';
            } elseif ($type == 4) {
                $type_cat = 'w.wholesale_cat_id ';
            } else {
                $type_cat = 'g.cat_id ';
            }
        }

        if($child_three == 1){
            if($cat){
                return $type_cat . db_create_in($cat);
            }else{
                return $type_cat . db_create_in('');
            }

        }else{

            $cat = array_unique(array_merge(array($cat), $cat_keys));

            if($cat){
                $cat = db_create_in($cat);
            }else{
                $cat = db_create_in('');
            }
            return $type_cat . $cat;
        }
    }else{
        $cat_keys = !empty($cat_keys) ? implode(",", $cat_keys) : '';
        return $cat_keys;
    }
}


/**
 * 获得指定文章分类下所有底层分类的ID
 *
 * @access  public
 * @param   integer     $cat        指定的分类ID
 *
 * @return void
 */
function get_article_children ($cat = 0)
{
    return db_create_in(array_unique(array_merge(array($cat), array_keys(article_cat_list($cat,0,false)))), 'cat_id');
}

/**
 * 获取邮件模板
 *
 * @access  public
 * @param:  $tpl_name[string]       模板代码
 *
 * @return array
 */
function get_mail_template($tpl_name)
{
    $sql = 'SELECT template_subject, is_html, template_content FROM ' . $GLOBALS['ecs']->table('mail_templates') . " WHERE template_code = '$tpl_name'";

    return $GLOBALS['db']->GetRow($sql);

}

/**
 * 记录订单操作记录
 *
 * @access  public
 * @param   string  $order_sn           订单编号
 * @param   integer $order_status       订单状态
 * @param   integer $shipping_status    配送状态
 * @param   integer $pay_status         付款状态
 * @param   string  $note               备注
 * @param   string  $username           用户名，用户自己的操作则为 buyer
 * @param   intval  $confirm_take_time  确认收货时间
 * @return  void
 */
function order_action($order_sn, $order_status, $shipping_status, $pay_status, $note = '', $username = null, $place = 0, $confirm_take_time = 0)
{
    if(!empty($confirm_take_time)){
        $log_time = $confirm_take_time;
    }else{
        $log_time = gmtime();
    }

    $admin_id = get_admin_id();

    if (is_null($username))
    {
        $username = $GLOBALS['db']->getOne("SELECT user_name FROM " .$GLOBALS['ecs']->table('admin_user'). " WHERE user_id = '$admin_id'", true);
    }

    $sql = 'INSERT INTO ' . $GLOBALS['ecs']->table('order_action') .
                ' (order_id, action_user, order_status, shipping_status, pay_status, action_place, action_note, log_time) ' .
            'SELECT ' .
                "order_id, '$username', '$order_status', '$shipping_status', '$pay_status', '$place', '$note', '$log_time' " .
            'FROM ' . $GLOBALS['ecs']->table('order_info') . " WHERE order_sn = '$order_sn'";
    $GLOBALS['db']->query($sql);
}

/**
 * 格式化商品价格
 *
 * @access  public
 * @param   float   $price  商品价格
 * @return  string
 */
function price_format($price = 0, $change_price = true)
{
    if (empty($price)) {
        $price = 0;
    }

    if ($change_price && defined('ECS_ADMIN') === false)
    {
        switch ($GLOBALS['_CFG']['price_format'])
        {
            case 0:
                $price = number_format($price, 2, '.', '');
                break;
            case 1: // 保留不为 0 的尾数
                $price = preg_replace('/(.*)(\\.)([0-9]*?)0+$/', '\1\2\3', number_format($price, 2, '.', ''));

                if (substr($price, -1) == '.')
                {
                    $price = substr($price, 0, -1);
                }
                break;
            case 2: // 不四舍五入，保留1位
                $price = substr(number_format($price, 2, '.', ''), 0, -1);
                break;
            case 3: // 直接取整
                $price = intval($price);
                break;
            case 4: // 四舍五入，保留 1 位
                $price = number_format($price, 1, '.', '');
                break;
            case 5: // 先四舍五入，不保留小数
                $price = round($price);
                break;
        }
    }
    else
    {
        @$price = number_format($price, 2, '.', '');

    }

    return sprintf($GLOBALS['_CFG']['currency_format'], $price);
}

/**
 * 返回订单虚拟商品是否货齐
 *
 * @access  public
 * @param   int   $order_id   订单id值
 * @param   bool  $is_number   是否货齐 0 已齐 1 未齐
 *
 * @return array()
 */
function order_virtual_card_count($order_id = 0){

    $sql = 'SELECT goods_id  FROM '.
           $GLOBALS['ecs']->table('order_goods') .
           " WHERE order_id = '$order_id' AND is_real = 0 AND (goods_number - send_number) > 0 AND extension_code = 'virtual_card' ";
    $goods_list = $GLOBALS['db']->getAll($sql);

    $is_number = 0;
    if($goods_list){
        foreach($goods_list as $key => $row){
            $sql = "SELECT COUNT(*) FROM " .$GLOBALS['ecs']->table('virtual_card'). " WHERE goods_id = '" .$row['goods_id']. "' AND is_saled = 0 AND order_sn = ''";

            if(!$GLOBALS['db']->getOne($sql)){
                $is_number = 1;
                continue;
            }
        }
    }

    return $is_number;
}

/**
 * 返回订单中的虚拟商品
 *
 * @access  public
 * @param   int   $order_id   订单id值
 * @param   bool  $shipping   是否已经发货
 *
 * @return array()
 */
function get_virtual_goods($order_id, $shipping = false)
{
    if ($shipping)
    {
        $sql = 'SELECT goods_id, goods_name, send_number AS num, extension_code FROM '.
           $GLOBALS['ecs']->table('order_goods') .
           " WHERE order_id = '$order_id' AND extension_code = 'virtual_card'";
    }
    else
    {
        $sql = 'SELECT goods_id, goods_name, (goods_number - send_number) AS num, extension_code FROM '.
           $GLOBALS['ecs']->table('order_goods') .
           " WHERE order_id = '$order_id' AND is_real = 0 AND (goods_number - send_number) > 0 AND extension_code = 'virtual_card' ";
    }
    $res = $GLOBALS['db']->getAll($sql);

    $virtual_goods = array();
    foreach ($res AS $row)
    {
        $virtual_goods[$row['extension_code']][] = array('goods_id' => $row['goods_id'], 'goods_name' => $row['goods_name'], 'num' => $row['num']);
    }

    return $virtual_goods;
}

/**
 *  虚拟商品发货
 *
 * @access  public
 * @param   array  $virtual_goods   虚拟商品数组
 * @param   string $msg             错误信息
 * @param   string $order_sn        订单号。
 * @param   string $process         设定当前流程：split，发货分单流程；other，其他，默认。
 *
 * @return bool
 */
function virtual_goods_ship(&$virtual_goods, &$msg, $order_sn, $return_result = false, $process = 'other')
{
    $virtual_card = array();
    foreach ($virtual_goods AS $code => $goods_list)
    {
        /* 只处理虚拟卡 */
        if ($code == 'virtual_card')
        {
            foreach ($goods_list as $goods)
            {
                if (virtual_card_shipping($goods, $order_sn, $msg, $process))
                {
                    if ($return_result)
                    {
                        $virtual_card[] = array('goods_id'=>$goods['goods_id'], 'goods_name'=>$goods['goods_name'], 'info'=>virtual_card_result($order_sn, $goods));
                    }
                }
                else
                {
                    return false;
                }
            }
            $GLOBALS['smarty']->assign('virtual_card',      $virtual_card);
        }
    }

    return true;
}

/**
 *  虚拟卡发货
 *
 * @access  public
 * @param   string      $goods      商品详情数组
 * @param   string      $order_sn   本次操作的订单
 * @param   string      $msg        返回信息
 * @param   string      $process    设定当前流程：split，发货分单流程；other，其他，默认。
 *
 * @return  boolen
 */
function virtual_card_shipping ($goods, $order_sn, &$msg, $process = 'other')
{
    /* 包含加密解密函数所在文件 */
    include_once(ROOT_PATH . 'includes/lib_code.php');

    /* 检查有没有缺货 */
    $sql = "SELECT COUNT(*) FROM ".$GLOBALS['ecs']->table('virtual_card')." WHERE goods_id = '$goods[goods_id]' AND is_saled = 0 ";
    $num = $GLOBALS['db']->GetOne($sql);

    if ($num < $goods['num'])
    {
        $msg .= sprintf($GLOBALS['_LANG']['virtual_card_oos'], $goods['goods_name']);

        return false;
    }

     /* 取出卡片信息 */
     $sql = "SELECT card_id, card_sn, card_password, end_date, crc32 FROM ".$GLOBALS['ecs']->table('virtual_card')." WHERE goods_id = '$goods[goods_id]' AND is_saled = 0  LIMIT " . $goods['num'];
     $arr = $GLOBALS['db']->getAll($sql);

     $card_ids = array();
     $cards = array();

     foreach ($arr as $virtual_card)
     {
        $card_info = array();

        /* 卡号和密码解密 */
        if ($virtual_card['crc32'] == 0 || $virtual_card['crc32'] == crc32(AUTH_KEY))
        {
            $card_info['card_sn'] = decrypt($virtual_card['card_sn']);
            $card_info['card_password'] = decrypt($virtual_card['card_password']);
        }
        elseif ($virtual_card['crc32'] == crc32(OLD_AUTH_KEY))
        {
            $card_info['card_sn'] = decrypt($virtual_card['card_sn'], OLD_AUTH_KEY);
            $card_info['card_password'] = decrypt($virtual_card['card_password'], OLD_AUTH_KEY);
        }
        else
        {
            $msg .= 'error key';

            return false;
        }
        $card_info['end_date'] = date($GLOBALS['_CFG']['date_format'], $virtual_card['end_date']);
        $card_ids[] = $virtual_card['card_id'];
        $cards[] = $card_info;
     }

     /* 标记已经取出的卡片 */
    $sql = "UPDATE ".$GLOBALS['ecs']->table('virtual_card')." SET ".
           "is_saled = 1 ,".
           "order_sn = '$order_sn' ".
           "WHERE " . db_create_in($card_ids, 'card_id');
    if (!$GLOBALS['db']->query($sql, 'SILENT'))
    {
        $msg .= $GLOBALS['db']->error();

        return false;
    }

    /* 更新库存 */
    $sql = "UPDATE ".$GLOBALS['ecs']->table('goods'). " SET goods_number = goods_number - '$goods[num]' WHERE goods_id = '$goods[goods_id]'";
    $GLOBALS['db']->query($sql);

    if (true)
    {
        /* 获取订单信息 */
        $sql = "SELECT order_id, order_sn, consignee, email FROM ".$GLOBALS['ecs']->table('order_info'). " WHERE order_sn = '$order_sn'";
        $order = $GLOBALS['db']->GetRow($sql);

        /* 更新订单信息 */
        if ($process == 'split')
        {
            $sql = "UPDATE ".$GLOBALS['ecs']->table('order_goods'). "
                    SET send_number = send_number + '" . $goods['num'] . "'
                    WHERE order_id = '" . $order['order_id'] . "'
                    AND goods_id = '" . $goods['goods_id'] . "' ";
        }
        else
        {
            $sql = "UPDATE ".$GLOBALS['ecs']->table('order_goods'). "
                    SET send_number = '" . $goods['num'] . "'
                    WHERE order_id = '" . $order['order_id'] . "'
                    AND goods_id = '" . $goods['goods_id'] . "' ";
        }

        if (!$GLOBALS['db']->query($sql, 'SILENT'))
        {
            $msg .= $GLOBALS['db']->error();

            return false;
        }
    }

    /* 发送邮件 */
    $GLOBALS['smarty']->assign('virtual_card',                   $cards);
    $GLOBALS['smarty']->assign('order',                          $order);
    $GLOBALS['smarty']->assign('goods',                          $goods);

    $GLOBALS['smarty']->assign('send_time', date('Y-m-d H:i:s'));
    $GLOBALS['smarty']->assign('shop_name', $GLOBALS['_CFG']['shop_name']);
    $GLOBALS['smarty']->assign('send_date', date('Y-m-d'));
    $GLOBALS['smarty']->assign('sent_date', date('Y-m-d'));

    $tpl = get_mail_template('virtual_card');
    $content = $GLOBALS['smarty']->fetch('str:' . $tpl['template_content']);
    send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html']);

    return true;
}

/**
 *  返回虚拟卡信息
 *
 * @access  public
 * @param
 *
 * @return void
 */
function virtual_card_result($order_sn, $goods)
{
    /* 包含加密解密函数所在文件 */
    include_once(ROOT_PATH . 'includes/lib_code.php');

    /* 获取已经发送的卡片数据 */
    $sql = "SELECT card_sn, card_password, end_date, crc32 FROM ".$GLOBALS['ecs']->table('virtual_card')." WHERE goods_id= '$goods[goods_id]' AND order_sn = '$order_sn' ";
    $res= $GLOBALS['db']->query($sql);

    $cards = array();

    while ($row = $GLOBALS['db']->FetchRow($res))
    {
        /* 卡号和密码解密 */
        if ($row['crc32'] == 0 || $row['crc32'] == crc32(AUTH_KEY))
        {
            $row['card_sn'] = decrypt($row['card_sn']);
            $row['card_password'] = decrypt($row['card_password']);
        }
        elseif ($row['crc32'] == crc32(OLD_AUTH_KEY))
        {
            $row['card_sn'] = decrypt($row['card_sn'], OLD_AUTH_KEY);
            $row['card_password'] = decrypt($row['card_password'], OLD_AUTH_KEY);
        }
        else
        {
            $row['card_sn'] = '***';
            $row['card_password'] = '***';
        }

        $cards[] = array('card_sn'=>$row['card_sn'], 'card_password'=>$row['card_password'], 'end_date'=>date($GLOBALS['_CFG']['date_format'], $row['end_date']));
    }

    return $cards;
}

/**
 * 获取指定 id snatch 活动的结果
 *
 * @access  public
 * @param   int   $id       snatch_id
 *
 * @return  array           array(user_name, bie_price, bid_time, num)
 *                          num通常为1，如果为2表示有2个用户取到最小值，但结果只返回最早出价用户。
 */
function get_snatch_result($id)
{
    $sql = 'SELECT u.user_id, u.user_name, u.email, lg.bid_price, lg.bid_time, count(*) as num' .
            ' FROM ' . $GLOBALS['ecs']->table('snatch_log') . ' AS lg '.
            ' LEFT JOIN ' . $GLOBALS['ecs']->table('users') . ' AS u ON lg.user_id = u.user_id'.
            " WHERE lg.snatch_id = '$id'".
            ' GROUP BY lg.bid_price' .
            ' ORDER BY num ASC, lg.bid_price ASC, lg.bid_time ASC LIMIT 1';
    $rec = $GLOBALS['db']->GetRow($sql);

    if ($rec)
    {
        $rec['bid_time']  = local_date($GLOBALS['_CFG']['time_format'], $rec['bid_time']);
        $rec['formated_bid_price'] = price_format($rec['bid_price'], false);

        /* 活动信息 */
        $sql = 'SELECT ext_info " .
               " FROM ' . $GLOBALS['ecs']->table('goods_activity') .
               " WHERE review_status = 3 AND act_id= '$id' AND act_type=" . GAT_SNATCH.
               " LIMIT 1";
        $row = $GLOBALS['db']->getOne($sql);
        $info = unserialize($row);

        if (!empty($info['max_price']))
        {
            $rec['buy_price'] = ($rec['bid_price'] > $info['max_price']) ? $info['max_price'] : $rec['bid_price'];
        }
        else
        {
            $rec['buy_price'] = $rec['bid_price'];
        }



        /* 检查订单 */
        $sql = "SELECT COUNT(*)" .
                " FROM " . $GLOBALS['ecs']->table('order_info') .
                " WHERE extension_code = 'snatch'" .
                " AND extension_id = '$id'" .
                " AND order_status " . db_create_in(array(OS_CONFIRMED, OS_UNCONFIRMED));

        $rec['order_count'] = $GLOBALS['db']->getOne($sql);
    }

    return $rec;
}

/**
 *  清除指定后缀的模板缓存或编译文件
 *
 * @access  public
 * @param  bool       $is_cache  是否清除缓存还是清出编译文件
 * @param  string     $ext       需要删除的文件名，不包含后缀
 *
 * @return int        返回清除的文件个数
 */
function clear_tpl_files($is_cache = true, $ext = '',$filename='')
{
    if(empty($filename)){
        $filename = "admin";
    }
    //ecmoban模板堂 --zhuo memcached start
    if($GLOBALS['_CFG']['open_memcached'] == 1){
        return $GLOBALS['cache']->clear();
    }
    //ecmoban模板堂 --zhuo memcached end

    $dirs = array();

    if (isset($GLOBALS['shop_id']) && $GLOBALS['shop_id'] > 0)
    {
        $tmp_dir = DATA_DIR ;
    }
    else
    {
        $tmp_dir = 'temp';
    }
    if ($is_cache)
    {
        $cache_dir = ROOT_PATH . $tmp_dir . '/caches/';
        $dirs[] = ROOT_PATH . $tmp_dir . '/query_caches/';
        $dirs[] = ROOT_PATH . $tmp_dir . '/static_caches/';
        for($i = 0; $i < 16; $i++)
        {
            $hash_dir = $cache_dir . dechex($i);
            $dirs[] = $hash_dir . '/';
        }
    }
    else
    {
        $dirs[] = ROOT_PATH . $tmp_dir . '/compiled/';
        $dirs[] = ROOT_PATH . $tmp_dir . '/compiled/'.$filename.'/';
    }

    $str_len = strlen($ext);
    $count   = 0;

    foreach ($dirs AS $dir)
    {
        $folder = @opendir($dir);

        if ($folder === false)
        {
            continue;
        }

        while ($file = readdir($folder))
        {
            if ($file == '.' || $file == '..' || $file == 'index.htm' || $file == 'index.html' || $file == '.gitignore')
            {
                continue;
            }
            if (is_file($dir . $file))
            {
                /* 如果有文件名则判断是否匹配 */
                $pos = ($is_cache) ? strrpos($file, '_') : strrpos($file, '.');

                if ($str_len > 0 && $pos !== false)
                {
                    $ext_str = substr($file, 0, $pos);

                    if ($ext_str == $ext)
                    {
                        if (@unlink($dir . $file))
                        {
                            $count++;
                        }
                    }
                }
                else
                {
                    if (@unlink($dir . $file))
                    {
                        $count++;
                    }
                }
            }
        }
        closedir($folder);
    }

    return $count;
}

/**
 * 清除模版编译文件
 *
 * @access  public
 * @param   mix     $ext    模版文件名， 不包含后缀
 * @return  void
 */
function clear_compiled_files($ext = '')
{
    return clear_tpl_files(false, $ext);
}

/**
 * 清除缓存文件
 *
 * @access  public
 * @param   mix     $ext    模版文件名， 不包含后缀
 * @return  void
 */
function clear_cache_files($ext = '')
{
    return clear_tpl_files(true, $ext);
}

/**
 * 清除模版编译和缓存文件
 *
 * @access  public
 * @param   mix     $ext    模版文件名后缀
 * @return  void
 */
function clear_all_files($ext = '',$filename='')
{
    return clear_tpl_files(false, $ext,$filename) + clear_tpl_files(true,  $ext,$filename);
}

/**
 * 页面上调用的js文件
 *
 * ./ 上一级目录
 * ../上上级目录
 * 默认是当前文件同级目录的js目录
 *
 * @access  public
 * @param   string      $files
 * @return  void.
 */
function smarty_insert_scripts($args)
{
    static $scripts = array();

    $arr = explode(',', str_replace(' ','',$args['files']));

    $str = '';
    foreach ($arr AS $val)
    {
        if (in_array($val, $scripts) == false)
        {
            $scripts[] = $val;
            if ($val{0} == '.')
            {
                $str .= '<script type="text/javascript" src="' . $val . '"></script>';
            }
            else
            {
                $str .= '<script type="text/javascript" src="js/' . $val . '"></script>';
            }
        }
    }

    return $str;
}

function js_path() {

    $self = explode("/", substr(PHP_SELF, 1));
    $count = count($self);
    if ($count > 1) {
        $real_path = $self[$count - 2];
        if ($real_path == ADMIN_PATH && $GLOBALS['_CFG']['site_domain']) {
            $path = $GLOBALS['_CFG']['site_domain'] . ADMIN_PATH . "/";
        } elseif ($real_path == SELLER_PATH && $GLOBALS['_CFG']['site_domain']) {
            $path = $GLOBALS['_CFG']['site_domain'] . SELLER_PATH . "/";
        }
    } else {
        $path = $GLOBALS['_CFG']['site_domain'];
    }

    return $path;
}

/**
 * 创建分页的列表
 *
 * @access  public
 * @param   integer $count
 * @return  string
 */
function smarty_create_pages($params)
{
    extract($params);

    $str = '';
    $len = 10;

    if (empty($page))
    {
        $page = 1;
    }

    if (!empty($count))
    {
        $step = 1;
        $str .= "<option value='1'>1</option>";

        for ($i = 2; $i < $count; $i += $step)
        {
            $step = ($i >= $page + $len - 1 || $i <= $page - $len + 1) ? $len : 1;
            $str .= "<option value='$i'";
            $str .= $page == $i ? " selected='true'" : '';
            $str .= ">$i</option>";
        }

        if ($count > 1)
        {
            $str .= "<option value='$count'";
            $str .= $page == $count ? " selected='true'" : '';
            $str .= ">$count</option>";
        }
    }

    return $str;
}

/*
 * 设置伪静态链接 by wu
 * $initUrl     传入链接
 * 其他变量同build_uri()函数用法
 */
function setRewrite($initUrl = '', $params = '', $append = '', $page = 0, $keywords = '', $size = 0) {
    $url = false;
    $rewrite = intval($GLOBALS['_CFG']['rewrite']);
    $baseUrl = basename($initUrl);
    $urlArr = explode('?', $baseUrl);

    if ($rewrite && !empty($urlArr[0]) && strpos($urlArr[0], '.php')) {
        //程序名
        $app = str_replace('.php', '', $urlArr[0]);

        //取id值
        @parse_str($urlArr[1], $queryArr);
        if (isset($queryArr['id'])) {
            $id = intval($queryArr['id']);
        }

        //链接中包含id
        if (!empty($id)) {
            //判断id类型
            switch ($app) {
                case 'history_list': $idType = array('cid' => $id);
                    break;
                case 'category': $idType = array('cid' => $id);
                    break;
                case 'goods': $idType = array('gid' => $id);
                    break;
                case 'presale': $idType = array('presaleid' => $id);
                    break;
                case 'brand': $idType = array('bid' => $id);
                    break;
                case 'brandn': $idType = array('bid' => $id);
                    break;
                case 'article_cat': $idType = array('acid' => $id);
                    break;
                case 'article': $idType = array('aid' => $id);
                    break;
                case 'merchants': $idType = array('mid' => $id);
                    break;
                case 'merchants_index': $idType = array('urid' => $id);
                    break;
                case 'group_buy': $idType = array('gbid' => $id);
                    break;
                case 'seckill': $idType = array('secid' => $id);
                    break;
                case 'auction': $idType = array('gbid' => $id);
                    break;
                case 'snatch': $idType = array('sid' => $id);
                    break;
                case 'exchange': $idType = array('cid' => $id);
                    break;
                case 'exchange_goods': $idType = array('gid' => $id);
                    break;
                case 'gift_gard': $idType = array('cid' => $id);
                    break;
                default: $idType = array('id' => '');
                    break;
            }
        }
        //链接中不含id
        else {
            switch ($app) {
                case 'index' : $idType = NULL;
                    break;
                case 'brand' : $idType = NULL;
                    break;
                case 'brandn' : $idType = NULL;
                    break;
                case 'group_buy' : $idType = NULL;
                    break;
                case 'seckill' : $idType = NULL;
                    break;
                case 'auction' : $idType = NULL;
                    break;
                case 'package' : $idType = NULL;
                    break;
                case 'activity' : $idType = NULL;
                    break;
                case 'snatch' : $idType = NULL;
                    break;
                case 'exchange' : $idType = NULL;
                    break;
                case 'store_street' : $idType = NULL;
                    break;
                case 'presale' : $idType = NULL;
                    break;
                case 'categoryall' : $idType = NULL;
                    break;
                case 'merchants' : $idType = NULL;
                    break;
                case 'merchants_index' : $idType = NULL;
                    break;
                case 'message' : $idType = NULL;
                    break;
                case 'wholesale' : $idType = NULL;
                    break;
                case 'gift_gard' : $idType = NULL;
                    break;
                case 'history_list' : $idType = NULL;
                    break;
                case 'merchants_steps' : $idType = NULL;
                    break;
                case 'merchants_steps_site' : $idType = NULL;
                    break;
                default: $idType = array('id' => '');
                    break;
            }
        }

        //rewrite
        if ($idType == NULL) {
            $url = $GLOBALS['_CFG']['site_domain'] . $app . '.html';
        } else {
            $params = empty($params) ? $idType : $params;
            $url = build_uri($app, $params, $append, $page, $keywords, $size);
        }
    }

    if ($url) {
        return $url;
    } else {
        if ((strpos($initUrl, 'http://') === false && strpos($initUrl, 'https://') === false)) {
            return $GLOBALS['_CFG']['site_domain'] . $initUrl;
        } else {
            return $initUrl;
        }
    }
}

/**
 * 重写 URL 地址
 *
 * @access  public
 * @param   string  $app        执行程序
 * @param   array   $params     参数数组
 * @param   string  $append     附加字串
 * @param   integer $page       页数
 * @param   string  $keywords   搜索关键词字符串
 * @return  void
 */
function build_uri($app, $params, $append = '', $page = 0, $keywords = '', $size = 0)
{
    static $rewrite = NULL;

    if ($rewrite === NULL)
    {
        $rewrite = intval($GLOBALS['_CFG']['rewrite']);
    }

    $args = array('cid'   => 0,
                  'gid'   => 0,
                  'bid'   => 0,
                  'acid'  => 0,
                  'aid'   => 0,
                //ecmoban模板堂 --zhuo start
                'mid'   => 0,
                'urid'   => 0,
                'ubrand'   => 0,
                'chkw'   => '',
                'is_ship'=>'',//by wang
                'hid' => 0,
                //ecmoban模板堂 --zhuo end
                  'sid'   => 0,
                  'gbid'  => 0,
                  'auid'  => 0,
                  'sort'  => '',
                  'order' => '',
                  'status' => -1,
                  'secid' => 0 ,//liu
                  'tmr' => 0
                );

    extract(array_merge($args, $params));
    $uri = '';
    switch ($app)
    {
        //ecmoban模板堂 --zhuo start 浏览列表插件
        case 'history_list':
            if ($rewrite)
            {
                $uri = 'history_list-' . $cid;

                if (!empty($page))
                {
                    $uri .= '-' . $page;
                }
            }
            else
            {
                $uri = 'history_list.php?cat_id=' . $cid;

                if (!empty($page))
                {
                    $uri .= '&amp;page=' . $page;
                }
            }

            break;
		//ecmoban模板堂 --zhuo end 浏览列表插件

        case 'category':
            if (empty($cid))
            {
                return false;
            }
            else
            {
                if ($rewrite)
                {
                    $uri = 'category-' . $cid;
                    if (isset($bid) && !empty($bid))
                    {
                        $uri .= '-b' . $bid;
                    }

                    //ecmoban模板堂 --zhuo start
                    if (isset($ubrand) && !empty($ubrand))
                    {
                        $uri .= '-ubrand' . $ubrand;
                    }
                    //ecmoban模板堂 --zhuo end

                    if (isset($price_min))
                    {
                        $uri .= '-min'.$price_min;
                    }
                    if (isset($price_max))
                    {
                        $uri .= '-max'.$price_max;
                    }
                    if (isset($filter_attr) && $filter_attr)
                    {
                        $uri .= '-attr' . $filter_attr;
                    }
                    if (isset($ship) && !empty($ship))
                    {
                        $uri .= '-ship' . $ship;
                    }
                    if (isset($self) && !empty($self))
                    {
                        $uri .= '-self' . $self;
                    }
                    if (isset($have) && !empty($have))
                    {
                        $uri .= '-have' . $have;
                    }
                    if (!empty($page))
                    {
                        $uri .= '-' . $page;
                    }
                    if (!empty($sort))
                    {
                        $uri .= '-' . $sort;
                    }
                    if (!empty($order))
                    {
                        $uri .= '-' . $order;
                    }
                }
                else
                {
                    $uri = 'category.php?id=' . $cid;
                    if (!empty($bid))
                    {
                        $uri .= '&amp;brand=' . $bid;
                    }

                    //ecmoban模板堂 --zhuo start
                    if (!empty($ubrand))
                    {
                        $uri .= '&amp;ubrand=' . $ubrand;
                    }
                    //ecmoban模板堂 --zhuo end

                    if (isset($price_min) && !empty($price_min))
                    {
                        $uri .= '&amp;price_min=' . $price_min;
                    }
                    if (isset($price_max) && !empty($price_max))
                    {
                        $uri .= '&amp;price_max=' . $price_max;
                    }

                    if (isset($filter_attr) && !empty($filter_attr))
                    {
                        $uri .='&amp;filter_attr=' . $filter_attr;
                    }

                    if (isset($ship) && !empty($ship))
                    {
                        $uri .='&amp;ship=' . $ship;
                    }

                    if (isset($self) && !empty($self))
                    {
                        $uri .='&amp;self=' . $self;
                    }

                    if (isset($have) && !empty($have))
                    {
                        $uri .='&amp;have=' . $have;
                    }

                    if (!empty($page))
                    {
                        $uri .= '&amp;page=' . $page;
                    }
                    if (!empty($sort))
                    {
                        $uri .= '&amp;sort=' . $sort;
                    }
                    if (!empty($order))
                    {
                        $uri .= '&amp;order=' . $order;
                    }
                }
            }

            break;
        case 'group_buy_category':
            if (empty($cid))
            {
                return false;
            }
            else
            {
                $uri = "group_buy.php?act=list&cat_id=". $cid;
            }

            if (!empty($bid))
            {
                $uri .= '&amp;brand=' . $bid;
            }
            break;
        case 'presale_category':
            if (empty($cid))
            {
                return false;
            }
            else
            {
                $uri = "presale.php?act=category&cat_id=". $cid;
            }

            if (!empty($bid))
            {
                $uri .= '&amp;brand=' . $bid;
            }
            break;
        case 'sample_category':
            if (empty($cid))
            {
                return false;
            }
            else
            {
                $uri = "sample.php?act=list&id=". $cid;
            }

            if (!empty($bid))
            {
                $uri .= '&amp;brand=' . $bid;
            }
            break;
        case 'wholesale_category':
            if (empty($cid))
            {
                return false;
            }
            else
            {
                $uri = "wholesale_cat.php?act=list&id=". $cid;
            }

            if (!empty($bid))
            {
                $uri .= '&amp;brand=' . $bid;
            }
            break;

            case 'wholesale':
            if (empty($cid) && empty($act))
            {
                return false;
            }
            else
            {
                if($rewrite){
                    $uri = 'wholesale';
                    if(!empty($cid)){
                        $uri .= '-' . $cid;
                    }

                    if (!empty($cid))
                    {
                        $uri .= '-c' . $cid;
                    }

                    if (isset($status) && $status != -1)
                    {
                        $uri .= '-status' . $status;
                    }

                    if (!empty($act))
                    {
                        $uri .= '-' . $act;
                    }
                }else{
                    $uri = 'wholesale.php?';
                    if (!empty($act))
                    {
                        $uri .= 'act=' . $act;
                    }
                    if (!empty($cid))
                    {
                        $uri .= '&amp;id=' . $cid;
                    }

                    if(isset($status) && $status != -1){
                        $uri .= '&amp;status=' . $status;
                    }


                }
            }

            break;

            case 'wholesale_cat':
            if (empty($cid) && empty($act))
            {
                return false;
            }
            else
            {
                if($rewrite){
                    $uri = 'wholesale_cat';
                    if(!empty($cid)){
                        $uri .= '-' . $cid;
                    }

                    if (isset($status) && $status != -1)
                    {
                        $uri .= '-status' . $status;
                    }

                    if (!empty($act))
                    {
                        $uri .= '-' . $act;
                    }
                }else{
                    $uri = 'wholesale_cat.php?';

                    if (!empty($cid))
                    {
                        $uri .= 'id=' . $cid;
                    }
                    if(isset($status) && $status != -1){
                        $uri .= '&amp;status=' . $status;
                    }

                    if (!empty($act))
                    {
                        $uri .= '&amp;act=' . $act;
                    }

					if (!empty($page))
                    {
                        $uri .= '&amp;page=' . $page;
                    }
                }
            }

            break;

            case 'wholesale_goods':
            if (empty($aid))
            {
                return false;
            }
            else
            {
                $uri = $rewrite ? 'wholesale_goods-' . $aid : 'wholesale_goods.php?id=' . $aid;
            }

            break;

            case 'wholesale_purchase':
            if (empty($gid) && empty($act))
            {
                return false;
            }
            else
            {
                if($rewrite){
                    $uri = 'wholesale_purchase';
                    if(!empty($gid)){
                        $uri .= '-' . $gid;
                    }

                    if (!empty($act))
                    {
                        $uri .= '-' . $act;
                    }
                }else{
                    $uri = 'wholesale_purchase.php?';

                    if (!empty($gid))
                    {
                        $uri .= 'id=' . $gid;
                    }

                    if (!empty($act))
                    {
                        $uri .= '&amp;act=' . $act;
                    }
                }
            }

            break;

        case 'goods':
            if (empty($gid))
            {
                return false;
            }
            else
            {
                $uri = $rewrite ? 'goods-' . $gid : 'goods.php?id=' . $gid;
            }

            break;
        case 'presale':
            if (empty($presaleid) && empty($act))
            {
                return false;
            }
            else
            {
                if($rewrite){
                    $uri = 'presale';
                    if(!empty($presaleid)){
                        $uri .= '-' . $presaleid;
                    }

                    if (!empty($cid))
                    {
                        $uri .= '-c' . $cid;
                    }

                    if (isset($status) && $status != -1)
                    {
                        $uri .= '-status' . $status;
                    }

                    if (!empty($act))
                    {
                        $uri .= '-' . $act;
                    }
                }else{
                    $uri = 'presale.php?';
                    if (!empty($presaleid))
                    {
                        $uri .= 'id=' . $presaleid;
                    }

                    if (!empty($cid))
                    {
                        $uri .= 'cat_id=' . $cid;
                    }

                    if(isset($status) && $status != -1){
                        $uri .= '&amp;status=' . $status;
                    }

                    if (!empty($act))
                    {
                        $uri .= '&amp;act=' . $act;
                    }
                }
            }

            break;
        case 'sample':
            if (empty($acid) && empty($act))
            {
                return false;
            }
            else
            {
                $uri = 'sample.php?';
                if (!empty($act))
                {
                    $uri .= 'act=' . $act;
                }

                if (!empty($cid))
                {
                    $uri .= '&amp;id=' . $cid;
                }

                if (!empty($acid))
                {
                    $uri .= '&amp;id=' . $acid;
                }

                if(isset($status) && $status != -1){
                    $uri .= '&amp;status=' . $status;
                }
            }
            break;
        case 'categoryall':
            if (empty($urid))
            {
                return false;
            }
            else
            {
                if($rewrite){
                    $uri = 'categoryall';
                    if(!empty($urid)){
                        $uri .= '-' . $urid;
                    }
                }else{
                    $uri = 'categoryall.php';
                    if (!empty($urid))
                    {
                        $uri .= '?id=' . $urid;
                    }
                }
            }

            break;
        case 'brand':
            if (empty($bid))
            {
                return false;
            }
            else
            {
                if ($rewrite)
                {
                    $uri = 'brand-' . $bid;

                    if (!empty($mbid))
                    {
                        $uri .= '-mbid' . $mbid;
                    }

                    if (!empty($cid))
                    {
                        $uri .= '-c' . $cid;
                    }
                    //by wang start
                    if (isset($price_min) && !empty($price_min))
                    {
                        $uri .= '-min' . $price_min;
                    }
                    if (isset($price_max) && !empty($price_max))
                    {
                        $uri .= '-max' . $price_max;
                    }
                    if (isset($ship) && !empty($ship))
                    {
                        $uri .= '-ship'. $ship;
                    }
                    if (isset($self) && !empty($self))
                    {
                        $uri .= '-self'. $self;
                    }
                    //by wang end

                    if (!empty($page))
                    {
                        $uri .= '-' . $page;
                    }
                    if (!empty($sort))
                    {
                        $uri .= '-' . $sort;
                    }
                    if (!empty($order))
                    {
                        $uri .= '-' . $order;
                    }
                }
                else
                {
                    $uri = 'brand.php?id=' . $bid;

                    if (!empty($mbid))
                    {
                        $uri .= '&amp;mbid=' . $mbid;
                    }

                    if (!empty($cid))
                    {
                        $uri .= '&amp;cat=' . $cid;
                    }
                    //by wang start
                    if (isset($price_min))
                    {
                        $uri .= '&amp;price_min=' . $price_min;
                    }
                    if (isset($price_max))
                    {
                        $uri .= '&amp;price_max=' . $price_max;
                    }
                    if (isset($ship) && !empty($ship))
                    {
                        $uri .= '&amp;ship='. $ship;
                    }
                    if (isset($self) && !empty($self))
                    {
                        $uri .= '&amp;self='. $self;
                    }
                    if (!empty($page))
                    {
                        $uri .= '&amp;page=' . $page;
                    }
                    //by wang end
                    if (!empty($sort))
                    {
                        $uri .= '&amp;sort=' . $sort;
                    }
                    if (!empty($order))
                    {
                        $uri .= '&amp;order=' . $order;
                    }
                }
            }

            break;
        case 'brandn':
            if (empty($bid))
            {
                return false;
            }
            else
            {
                if ($rewrite)
                {
                    $uri = 'brandn-' . $bid;
                    if (isset($cid) && !empty($cid))
                    {
                        $uri .= '-c' . $cid;
                    }
                    if (!empty($page))
                    {
                        $uri .= '-' . $page;
                    }

                    if (!empty($sort))
                    {
                        $uri .= '-' . $sort;
                    }
                    if (!empty($order))
                    {
                        $uri .= '-' . $order;
                    }
                    if (!empty($act))
                    {
                        $uri .= '-' . $act;
                    }
                }
                else
                {
                    $uri = 'brandn.php?id=' . $bid;
                    if (!empty($cid))
                    {
                        $uri .= '&amp;cat=' . $cid;
                    }
                    if (!empty($page))
                    {
                        $uri .= '&amp;page=' . $page;
                    }
                    //by wang start
                    if (isset($price_min))
                    {
                        $uri .= '&amp;price_min=' . $price_min;
                    }
                    if (isset($price_max))
                    {
                        $uri .= '&amp;price_max=' . $price_max;
                    }
                    if (isset($is_ship) && !empty($is_ship))
                    {
                        $uri .= '&amp;is_ship='. $is_ship;
                    }
                    //by wang end
                    if (!empty($sort))
                    {
                        $uri .= '&amp;sort=' . $sort;
                    }
                    if (!empty($order))
                    {
                        $uri .= '&amp;order=' . $order;
                    }
                    if (!empty($act))
                    {
                        $uri .= '&amp;act=' . $act;
                    }
                }
            }

            break;
        case 'article_cat':
            if (empty($acid))
            {
                return false;
            }
            else
            {
                if ($rewrite)
                {
                    $uri = 'article_cat-' . $acid;
                    if (!empty($page))
                    {
                        $uri .= '-' . $page;
                    }
                    if (!empty($sort))
                    {
                        $uri .= '-' . $sort;
                    }
                    if (!empty($order))
                    {
                        $uri .= '-' . $order;
                    }
                    if (!empty($keywords))
                    {
                        $uri .= '-' . $keywords;
                    }
                }
                else
                {
                    $uri = 'article_cat.php?id=' . $acid;
                    if (!empty($page))
                    {
                        $uri .= '&amp;page=' . $page;
                    }
                    if (!empty($sort))
                    {
                        $uri .= '&amp;sort=' . $sort;
                    }
                    if (!empty($order))
                    {
                        $uri .= '&amp;order=' . $order;
                    }
                    if (!empty($keywords))
                    {
                        $uri .= '&amp;keywords=' . $keywords;
                    }
                }
            }

            break;
        case 'article':
            if (empty($aid))
            {
                return false;
            }
            else
            {
                $uri = $rewrite ? 'article-' . $aid : 'article.php?id=' . $aid;
            }

            break;
	//ecmoban模板堂 --zhuo start
	case 'merchants':
            if (empty($mid))
            {
                return false;
            }
            else
            {
                $uri = $rewrite ? 'merchants-' . $mid : 'merchants.php?id=' . $mid;
            }

            break;
	case 'merchants_index':
            if (empty($urid) && empty($merchant_id))
            {
                return false;
            }
            else
            {
                if ($urid) {
                    if ($rewrite) {
                        $uri = '';
                        $uri .= 'merchants_index-' . $urid;
                    } else {
                        $uri = 'merchants_index.php?merchant_id=' . $urid;
                    }
                }

                if ($merchant_id) {
                    if ($rewrite) {
                        $uri = '';
                        $uri .= 'merchants_index-' . $merchant_id;
                    } else {
                        $uri = 'merchants_index.php?merchant_id=' . $merchant_id;
                    }
                }
            }

            break;
        case 'merchants_store':
            if (empty($urid))
            {
                return false;
            }
            else
            {
                if ($rewrite)
                {
                    $uri = '';

                    if(isset($domain_name) && !empty($domain_name))
                    {
                        $uri .= $domain_name . "/";
                    }

                    $uri .= 'merchants_store-' . $urid;

                    if (!empty($cid))
                    {
                        $uri .= '-c' . $cid;
                    }
                    if (!empty($bid))
                    {
                        $uri .= '-b' . $bid;
                    }
                    if (!empty($keyword))
                    {
                        $uri .= '-keyword' . $keyword;
                    }
                    if (isset($price_min))
                    {
                        $uri .= '-min'.$price_min;
                    }
                    if (isset($price_max))
                    {
                        $uri .= '-max'.$price_max;
                    }
                    if (isset($filter_attr))
                    {
                        $uri .= '-attr' . $filter_attr;
                    }
                    if (!empty($page))
                    {
                        $uri .= '-' . $page;
                    }
                    if (!empty($sort))
                    {
                        $uri .= '-' . $sort;
                    }
                    if (!empty($order))
                    {
                        $uri .= '-' . $order;
                    }
                }
                else
                {
                    $uri = 'merchants_store.php?merchant_id=' . $urid;

                    if (!empty($cid))
                    {
                        $uri .= '&amp;id=' . $cid;
                    }

                    if (!empty($bid))
                    {
                        $uri .= '&amp;brand=' . $bid;
                    }
                    if (!empty($keyword))
                    {
                        $uri .= '&amp;keyword=' . $keyword;
                    }

                    if (isset($price_min))
                    {
                        $uri .= '&amp;price_min=' . $price_min;
                    }
                    if (isset($price_max))
                    {
                        $uri .= '&amp;price_max=' . $price_max;
                    }
                    if (!empty($filter_attr))
                    {
                        $uri .='&amp;filter_attr=' . $filter_attr;
                    }

                    if (!empty($page))
                    {
                        $uri .= '&amp;page=' . $page;
                    }
                    if (!empty($sort))
                    {
                        $uri .= '&amp;sort=' . $sort;
                    }
                    if (!empty($order))
                    {
                        $uri .= '&amp;order=' . $order;
                    }
                }
            }
            break;

            case 'merchants_store_shop':
            if (empty($urid))
            {
                return false;
            }
            else
            {
                if ($rewrite)
                {
                    $uri .= 'merchants_store_shop-' . $urid;

                    if (!empty($page))
                    {
                        $uri .= '-' . $page;
                    }
                    if (!empty($sort))
                    {
                        $uri .= '-' . $sort;
                    }
                    if (!empty($order))
                    {
                        $uri .= '-' . $order;
                    }
                }
                else
                {
                    $uri = 'merchants_store_shop.php?id=' . $urid;

                    if (!empty($page))
                    {
                        $uri .= '&amp;page=' . $page;
                    }
                    if (!empty($sort))
                    {
                        $uri .= '&amp;sort=' . $sort;
                    }
                    if (!empty($order))
                    {
                        $uri .= '&amp;order=' . $order;
                    }
                }

            }
            break;
		//ecmoban模板堂 --zhuo end
        case 'group_buy':
            if (empty($gbid) && empty($act))
            {
                return false;
            }
            else
            {
                $uri = 'group_buy.php?';

                if (!empty($act))
                {
                    $uri .= 'act=' . $act;
                } else {
                    $uri .= 'act=view';
                }

                if (!empty($gbid))
                {
                    $uri .= '&amp;id=' . $gbid;
                }

                if (!empty($cid))
                {
                    $uri .= '&amp;cat_id=' . $cid;
                }


//                $uri = $rewrite ? 'group_buy-' . $gbid : 'group_buy.php?act=view&amp;id=' . $gbid;
            }

            break;
        case 'wholesale2':
            //添加生产批发订单
            if (empty($gbid))
            {
                return false;
            }
            else
            {
                $uri = 'wholesale_goods.php?id=' . $gbid;
            }

            break;
        case 'auction':
            if (empty($auid))
            {
                return false;
            }
            else
            {
                $uri = $rewrite ? 'auction-' . $auid : 'auction.php?act=view&amp;id=' . $auid;
            }

            break;
        case 'snatch':
            if (empty($sid))
            {
                return false;
            }
            else
            {
                $uri = $rewrite ? 'snatch-' . $sid : 'snatch.php?id=' . $sid;
            }

            break;
        case 'history_list':
            if (empty($hid))
            {
                return false;
            }
            else
            {
                $uri = $rewrite ? 'history_list-' . $hid : 'history_list.php?act=user&amp;id=' . $hid;
            }

            break;
        case 'search':
            $uri = 'search.php?keywords=' . $chkw;

            if (!empty($bid))
            {
                $uri .= '&amp;brand=' . $bid;
            }
            if (isset($price_min))
            {
                $uri .= '&amp;price_min=' . $price_min;
            }
            if (isset($price_max))
            {
                $uri .= '&amp;price_max=' . $price_max;
            }
            if (!empty($filter_attr))
            {
                $uri .= '&amp;filter_attr=' . $filter_attr;
            }

            break;
        case 'user':
            if (empty($act))
            {
                return false;
            }
            else
            {
                if($rewrite){
                    $uri = 'user';
                    if (!empty($act))
                    {
                        $uri .= '-' . $act;
                    }
                }else{
                    $uri = 'user.php?';
                    if (!empty($act))
                    {
                        $uri .= 'act=' . $act;
                    }
                }
            }

            break;
        case 'exchange':
            if (empty($cid))
            {
                if (!empty($page)){
                    $uri = 'exchange-' . $cid;
                    if ($rewrite)
                    {
                        $uri .= '-' . $page;
                    }
                    else
                    {
                        $uri = 'exchange.php?';
                        $uri .= 'page=' . $page;
                    }
                }
                else
                {
                    return false;
                }
            }
            else
            {
                if ($rewrite)
                {
                    $uri = 'exchange-' . $cid;
                    if (isset($price_min))
                    {
                        $uri .= '-min'.$price_min;
                    }
                    if (isset($price_max))
                    {
                        $uri .= '-max'.$price_max;
                    }
                    if (!empty($page))
                    {
                        $uri .= '-' . $page;
                    }
                    if (!empty($sort))
                    {
                        $uri .= '-' . $sort;
                    }
                    if (!empty($order))
                    {
                        $uri .= '-' . $order;
                    }
                }
                else
                {
                    $uri = 'exchange.php?cat_id=' . $cid;
                    if (isset($price_min))
                    {
                        $uri .= '&amp;integral_min=' . $price_min;
                    }
                    if (isset($price_max))
                    {
                        $uri .= '&amp;integral_max=' . $price_max;
                    }

                    if (!empty($page))
                    {
                        $uri .= '&amp;page=' . $page;
                    }
                    if (!empty($sort))
                    {
                        $uri .= '&amp;sort=' . $sort;
                    }
                    if (!empty($order))
                    {
                        $uri .= '&amp;order=' . $order;
                    }
                }
            }

            break;
        case 'exchange_goods':
            if (empty($gid))
            {
                return false;
            }
            else
            {
                $uri = $rewrite ? 'exchange-id' . $gid : 'exchange.php?id=' . $gid . '&amp;act=view';
            }

            break;

        //ecmoban模板堂 --zhuo start
        case 'gift_gard':
            if (empty($cid))
            {
                return false;
            }
            else
            {
                if ($rewrite) {
                    $uri = 'gift_gard-' . $cid;
                    if (!empty($page)) {
                        $uri .= '-' . $page;
                    }
                    if (!empty($sort)) {
                        $uri .= '-' . $sort;
                    }
                    if (!empty($order)) {
                        $uri .= '-' . $order;
                    }
                } else {
                    $uri = 'gift_gard.php?cat_id=' . $cid;
                    if (!empty($page)) {
                        $uri .= '&amp;page=' . $page;
                    }
                    if (!empty($sort)) {
                        $uri .= '&amp;sort=' . $sort;
                    }
                    if (!empty($order)) {
                        $uri .= '&amp;order=' . $order;
                    }
                }
            }

            break;
        //ecmoban模板堂 --zhuo end
        /* 秒杀活动详情 begin liu */
        case 'seckill':
            if (empty($act)) {
                if (!empty($cid)) {
                    $uri = $rewrite ? 'seckill-' . $cid : 'seckill.php?cat_id=' . $cid;
                } else {
                    return false;
                }
            } else {
                if($rewrite){
                    $uri = 'seckill-' . $secid;

                    if(!empty($act)){
                        $uri .= '-' . $act;
                    }

                }else{
                    $uri = 'seckill.php?id=' . $secid;

                    if($act == 'view'){
                        $uri .= "&amp;act=view";
                    }
                    if($tmr){
                        $uri .= "&tmr=1";
                    }
                }
            }

            break;
        /* 秒杀活动详情 end */
        default:
            return false;
            break;
    }

    if ($rewrite)
    {
        if ($rewrite == 2 && !empty($append))
        {
            $uri .= '-' . urlencode(preg_replace('/[\.|\/|\?|&|\+|\\\|\'|"|,]+/', '', $append));
        }

        if(!in_array($app, array('search'))){
            $uri .= '.html';
        }
    }
    if (($rewrite == 2) && (strpos(strtolower(EC_CHARSET), 'utf') !== 0))
    {
        $uri = urlencode($uri);
    }

    $site_domain = '';
    if(!isset($domain_name) && empty($domain_name))
    {
        $site_domain = $GLOBALS['_CFG']['site_domain'];
    }

    return $site_domain . $uri;
}

/**
 * 格式化重量：小于1千克用克表示，否则用千克表示
 * @param   float   $weight     重量
 * @return  string  格式化后的重量
 */
function formated_weight($weight)
{
    $weight = round(floatval($weight), 3);
    if ($weight > 0)
    {
        if ($weight < 1)
        {
            /* 小于1千克，用克表示 */
            return intval($weight * 1000) . $GLOBALS['_LANG']['gram'];
        }
        else
        {
            /* 大于1千克，用千克表示 */
            return $weight . $GLOBALS['_LANG']['kilogram'];
        }
    }
    else
    {
        return 0;
    }
}

/**
 * 记录帐户变动
 * @param   int     $user_id        用户id
 * @param   float   $user_money     可用余额变动
 * @param   float   $frozen_money   冻结余额变动
 * @param   int     $rank_points    等级积分变动
 * @param   int     $pay_points     消费积分变动
 * @param   string  $change_desc    变动说明
 * @param   int     $change_type    变动类型：参见常量文件
 * @return  void
 */
function log_account_change($user_id, $user_money = 0, $frozen_money = 0, $rank_points = 0, $pay_points = 0, $change_desc = '', $change_type = ACT_OTHER, $order_type = 0,$deposit_fee = 0)
{
    $is_go = true;
    $is_user_money = 0;
    $is_pay_points = 0;
    //控制只有后台执行，前台不操作以下程序
    if($change_desc && $order_type){
        $change_desc_arr = explode(" ", $change_desc);
        if(count($change_desc_arr) >= 2){
            $sql = "SELECT order_id, main_order_id FROM " .$GLOBALS['ecs']->table('order_info'). " WHERE order_sn = '" .$change_desc_arr['1']. "' LIMIT 1";
            $ordor_res = $GLOBALS['db']->getRow($sql);

            if($ordor_res){
                if($ordor_res['main_order_id'] > 0){  //操作无效或取消订单时，先查询该订单是否有主订单

                    $sql = "SELECT order_sn FROM " .$GLOBALS['ecs']->table('order_info'). "WHERE order_id = '" .$ordor_res['main_order_id']. "' LIMIT 1";
                    $ordor_main = $GLOBALS['db']->getRow($sql);

                    $order_surplus_desc = sprintf($GLOBALS['_LANG']['return_order_surplus'], $ordor_main['order_sn']);
                    $order_integral_desc = sprintf($GLOBALS['_LANG']['return_order_integral'], $ordor_main['order_sn']);

                    //查询该订单的主订单是否已操作过无效或取消订单
                    $sql = "SELECT log_id FROM " .$GLOBALS['ecs']->table('account_log'). " WHERE change_desc IN(" ."'" .$order_surplus_desc. "'" .",'" .$order_integral_desc. "'". ")";
                    $log_res = $GLOBALS['db']->getAll($sql);

                    if($log_res){
                        $is_go = false;
                    }
                }else{
                    $sql = "SELECT order_id, order_sn FROM " .$GLOBALS['ecs']->table('order_info'). " WHERE main_order_id = '" .$ordor_res['order_id']. "'";
                    $main_ordor_res = $GLOBALS['db']->getAll($sql);

                    if($main_ordor_res > 0){
                        foreach($main_ordor_res as $key=>$row){
                            $order_surplus_desc = sprintf($GLOBALS['_LANG']['return_order_surplus'], $row['order_sn']);
                            $order_integral_desc = sprintf($GLOBALS['_LANG']['return_order_integral'], $row['order_sn']);

                            $main_change_desc = sprintf($GLOBALS['_LANG']['return_order_surplus'], $row['order_sn']);
                            $sql = "SELECT user_money, pay_points FROM " .$GLOBALS['ecs']->table('account_log'). " WHERE change_desc IN(" ."'" .$order_surplus_desc. "'" .",'" .$order_integral_desc. "'". ")";
                            $parent_account_log = $GLOBALS['db']->getAll($sql);

                            if($user_money){
                                $is_user_money += $parent_account_log[0]['user_money'];
                            }

                            if($pay_points){
                               $is_pay_points += $parent_account_log[1]['pay_points'];
                            }

                        }
                    }

                    if($user_money){
                        $user_money -= $is_user_money;
                    }

                    if($pay_points){
                       $pay_points -= $is_pay_points;
                    }
                }
            }
        }
    }

    if($is_go && ($user_money || $frozen_money || $rank_points || $pay_points)){

        /* 插入帐户变动记录 */
        $account_log = array(
            'user_id'       => $user_id,
            'user_money'    => $user_money,
            'frozen_money'  => $frozen_money,
            'rank_points'   => $rank_points,
            'pay_points'    => $pay_points,
            'change_time'   => gmtime(),
            'change_desc'   => $change_desc,
            'change_type'   => $change_type,
            'deposit_fee'   => $deposit_fee
        );

        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('account_log'), $account_log, 'INSERT');

        /* 更新用户信息 */
        $sql = "UPDATE " . $GLOBALS['ecs']->table('users') .
                " SET user_money = user_money + ('$user_money'+ '$deposit_fee')," .
                " frozen_money = frozen_money + ('$frozen_money')," .
                " rank_points = rank_points + ('$rank_points')," .
                " pay_points = pay_points + ('$pay_points')" .
                " WHERE user_id = '$user_id' LIMIT 1";
        $GLOBALS['db']->query($sql);

        /* 更新会员当前等级 start */
        $sql = "SELECT rank_points FROM " . $GLOBALS['ecs']->table("users") . " WHERE user_id = '$user_id'";
        $user_rank_points = $GLOBALS['db']->getOne($sql, true);

        $sql = 'SELECT rank_id, discount FROM ' . $GLOBALS['ecs']->table('user_rank') . " WHERE special_rank = 0 AND min_points <= " . $user_rank_points . ' AND max_points > ' . $user_rank_points . " LIMIT 1";
        $rank_row = $GLOBALS['db']->getRow($sql);

        if ($rank_row) {
            $rank_row['discount'] = $rank_row['discount'] / 100.00;
        } else {
            $rank_row['discount'] = 1;
            $rank_row['rank_id'] = 0;
        }
        /* 更新会员当前等级 end */

        $sql = "UPDATE " . $GLOBALS['ecs']->table('users') . "SET user_rank = '" . $rank_row['rank_id'] . "' WHERE user_id = '$user_id'";
        $GLOBALS['db']->query($sql);

        $sql = "UPDATE " . $GLOBALS['ecs']->table('sessions') . "SET user_rank = '" . $rank_row['rank_id'] . "', discount= '" . $rank_row['discount'] . "' WHERE userid = '$user_id' AND adminid = 0";
        $GLOBALS['db']->query($sql);
    }
}

/**
 * 商家帐户变动
 *
 * @access  public
 * @param   int     $cat_id     分类的ID
 * @return  mix
 */
function log_seller_account_change($ru_id, $seller_money = 0, $frozen_money = 0){

    if($seller_money || $frozen_money){
        /* 更新用户信息 */
        $sql = "UPDATE " . $GLOBALS['ecs']->table('seller_shopinfo') .
                " SET seller_money = seller_money + ('$seller_money')," .
                " frozen_money = frozen_money + ('$frozen_money')" .
                " WHERE ru_id = '$ru_id' LIMIT 1";
        $GLOBALS['db']->query($sql);
    }
}

/**
 * 支付成功后  商家金额变化
 * @param $order_id
 */
function order_payed_seller_accoun_change($order_id) {
    $ecs = $GLOBALS['ecs'];
    $db = $GLOBALS['db'];
    $sql = "SELECT COUNT(order_id) AS child_num,money_paid,surplus FROM " . $ecs->table('order_info') . " WHERE main_order_id ='$order_id'";
    $child_order = $db->getOne($sql);
    if ($child_order > 1) {
        //子订单
        $sql = "SELECT order_id,user_id,money_paid,consignee,mobile,surplus,order_sn,order_id,shipping_name,shipping_fee,order_amount FROM " . $ecs->table('order_info') . " WHERE main_order_id ='$order_id'";
        $child_order_info = $db->getAll($sql);
        foreach($child_order_info as $k=>$v){
            $seller_id = $GLOBALS['db']->getOne("SELECT ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '" . $v['order_id'] . "'", true);
            if ($seller_id) {
                    $sql = "SELECT u.user_id AS seller_id, IFNULL(s.cycle, 0) AS cycle, p.percent_value FROM " . $GLOBALS['ecs']->table('merchants_shop_information') . " AS u " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_server') . " AS s ON u.user_id = s.user_id" .
                " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_percent') . " AS p ON s.suppliers_percent = p.percent_id" .
                " WHERE u.user_id = '$seller_id'";
                $seller = $GLOBALS['db']->getRow($sql);

                $order_amount = $v['money_paid'] + $v['surplus'];

                $proportion = $seller['percent_value'];
                $gain_proportion = 100 - $proportion;   //收取比例
                $should_proportion = $proportion;       //应结比例

                $frozen_money = $gain_proportion;
                $drp_money = 0;
                $return_amount = 0;
                $gain_commission = ($order_amount - $drp_money - $return_amount) * ($gain_proportion / 100);
                $gain_commission = number_format($gain_commission, 2, '.', '');

                $should_amount = $order_amount - $gain_commission;

                log_seller_account_change($seller['seller_id'], 0, $should_amount);
                merchants_account_log($seller['seller_id'], 0, $should_amount, '订单支付', 6);

                /**
                 * 商家支付订单结算
                 */
                $data = array(
                    'order_id' => $v['order_id'], //子订单ID
                    'seller_id' => $seller_id,
                    'dateline' => gmtime(),
                    'user_id' => $v['user_id'],
                    'order_amount' => $order_amount,
                    'goods_amount' => 0,
                    'type' => 0,
                    'proportion' => $proportion,
                    'should_amount' => $should_amount
                );
                $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('seller_jisuan_order'), $data, 'INSERT');
            }
        }
    } else {
        $sql = "SELECT order_id, user_id, money_paid,consignee,mobile,surplus,order_sn,order_id,shipping_name,shipping_fee,order_amount FROM " . $ecs->table('order_info') . " WHERE order_id ='$order_id'";
        $order = $db->getRow($sql);
        $seller_id = $GLOBALS['db']->getOne("SELECT ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '" . $order['order_id'] . "'", true);

        if ($seller_id) {
            $sql = "SELECT u.user_id AS seller_id, IFNULL(s.cycle, 0) AS cycle, p.percent_value FROM " . $GLOBALS['ecs']->table('merchants_shop_information') . " AS u " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_server') . " AS s ON u.user_id = s.user_id" .
                " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_percent') . " AS p ON s.suppliers_percent = p.percent_id" .
                " WHERE u.user_id = '$seller_id'";
            $seller = $GLOBALS['db']->getRow($sql);

            $order_amount = $order['money_paid'] + $order['surplus'];

            $proportion = $seller['percent_value'];
            $gain_proportion = 100 - $proportion;   //收取比例
            $should_proportion = $proportion;       //应结比例

            $drp_money = 0;
            $return_amount = 0;
            $gain_commission = ($order_amount - $drp_money - $return_amount) * ($gain_proportion / 100);
            $gain_commission = number_format($gain_commission, 2, '.', '');

            $should_amount = $order_amount - $gain_commission;

            log_seller_account_change($seller['seller_id'], 0, $should_amount);
            merchants_account_log($seller['seller_id'], 0, $should_amount, '订单支付', 5);

            /**
             * 商家支付订单结算
             */
            $data = array(
                'order_id' => $order_id,
                'seller_id' => $seller_id,
                'dateline' => gmtime(),
                'user_id' => $order['user_id'],
                'order_amount' => $order_amount,
                'goods_amount' => 0,
                'type' => 0,
                'proportion' => $proportion,
                'should_amount' => $should_amount
            );
            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('seller_jisuan_order'), $data, 'INSERT');
        }
    }


}

/**
 * 订单退货退款
 * @param $order_id
 * @param $ret_id
 */
function order_refound_seller_account_change1111($order_id, $ret_id) {
    $ecs = $GLOBALS['ecs'];
    $db = $GLOBALS['db'];

    $sql = "SELECT order_id, money_paid,consignee,mobile,surplus,order_sn,order_id,shipping_name,shipping_fee,order_amount FROM " . $ecs->table('order_info') . " WHERE order_id ='$order_id'";
    $order = $db->getRow($sql);

    $seller_id = $GLOBALS['db']->getOne("SELECT ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '" . $order['order_id'] . "'", true);

    if ($seller_id) {
        $sql = "SELECT * FROM" . $GLOBALS['ecs']->table('order_return') . " WHERE ret_id = " . $ret_id;
        $order_return = $GLOBALS['db']->getRow($sql);

        /**
         * 取的退换货订单商品
         */
        $return_order = return_order_info($ret_id);
        //退款金额
        $should_return = $return_order['should_return'] - $return_order['discount_amount'];

        $order = order_info($order_id);
        //商品总价
        $order_amount = $order['goods_amount'];

        $sql = "SELECT u.user_id AS seller_id, IFNULL(s.cycle, 0) AS cycle, p.percent_value FROM " . $GLOBALS['ecs']->table('merchants_shop_information') . " AS u " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_server') . " AS s ON u.user_id = s.user_id" .
            " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_percent') . " AS p ON s.suppliers_percent = p.percent_id" .
            " WHERE u.user_id = '$seller_id'";
        $seller = $GLOBALS['db']->getRow($sql);

        $proportion = $seller['percent_value'];
        $gain_proportion = 100 - $proportion;   //收取比例
        $should_proportion = $proportion;       //应结比例

        $drp_money = 0;
        $return_amount = 0;
        $gain_commission = ($order_amount - $drp_money - $return_amount) * ($gain_proportion / 100);
        $gain_commission = number_format($gain_commission, 2, '.', '');

        //总额结算
        $should_amount = $order_amount - $gain_commission;

        $return_gain_commission = ($should_return - $drp_money - $return_amount) * ($gain_proportion / 100);
        $return_gain_commission = number_format($return_gain_commission, 2, '.', '');
        $return_should_amount = $should_return - $return_gain_commission;

        //剩余结算金额
        $has_should_amount = order_seller_should_account($order_id);

        //该订单剩余退款 少于应退款
        if ($has_should_amount < $return_gain_commission) {
            $return_should_amount = $has_should_amount;
        }

        if ($order['order_status'] == SS_RECEIVED) {
            //已确认收货订单 金额已从冻结到可用  所以必须从可用中扣除
            log_seller_account_change($seller['seller_id'], -$return_should_amount, 0);
            merchants_account_log($seller['seller_id'], -$return_should_amount,0,  '订单退款', 6);

            /**
             * 商家退款订单结算
             */
            $data = array(
                'order_id' => $order_id,
                'seller_id' => $seller_id,
                'return_ret_id' => $ret_id,
                'dateline' => gmtime(),
                'user_id' => $order['user_id'],
                'order_amount' => $order_amount,
                'type' => 1,
                'proportion' => $proportion,
                'should_amount' => $return_should_amount
            );
            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('seller_jisuan_order'), $data, 'INSERT');

        } else {
            //未确认收货订单
            log_seller_account_change($seller['seller_id'], 0, -$return_should_amount);
            merchants_account_log($seller['seller_id'], 0, -$return_should_amount, '订单退款', 6);

            /**
             * 商家退款订单结算
             */
            $data = array(
                'order_id' => $order_id,
                'seller_id' => $seller['seller_id'],
                'return_ret_id' => $ret_id,
                'dateline' => gmtime(),
                'user_id' => $order['user_id'],
                'order_amount' => $order_amount,
                'type' => 1,
                'proportion' => $proportion,
                'should_amount' => $return_should_amount
            );
            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('seller_jisuan_order'), $data, 'INSERT');
        }

    }
}

/**
 * 确认收货 商家冻结金额转到可用余额
 * @param $order_id
 */
function order_confirm_seller_account_change_2($order_id) {
    $sql = "SELECT * from " . $GLOBALS['ecs']->table('seller_jisuan_order') . " WHERE order_id = '$order_id' AND type = 0";
    $bill = $GLOBALS['db']->getRow($sql);

    //订单应结金额
    $should_amount = order_seller_should_account($bill['order_id']);

    //未确认收货订单
    log_seller_account_change($bill['seller_id'], $should_amount, -$should_amount);
    merchants_account_log($bill['seller_id'], $should_amount, -$should_amount, '确认收货解冻', 4);
}

/**
 * 确认收货 商家转到可用余额
 * @param $order_id
 */
function order_confirm_seller_account_change($order_id, $insert_id=0) {
    $sql = "SELECT order_id, user_id, order_sn , order_status, shipping_status, pay_status, " .
        "order_amount, goods_amount, tax, shipping_fee, seller_shipping_fee, insure_fee, pay_fee, pack_fee, card_fee, " .
        "bonus, integral_money, coupons, discount, money_paid, surplus" .
        " FROM ".$GLOBALS['ecs']->table('order_info') ." WHERE order_id = '$order_id'";
    $order = $GLOBALS['db']->GetRow($sql);

    $seller_id = $GLOBALS['db']->getOne("SELECT ru_id FROM " .$GLOBALS['ecs']->table('order_goods'). " WHERE order_id = '$order_id'", true);
    $value_card = $GLOBALS['db']->getOne("SELECT use_val FROM " .$GLOBALS['ecs']->table('value_card_record'). " WHERE order_id = '$order_id'", true);

    //订单退款金额
    $order['return_amount'] = get_order_return_amount($order_id);

    //结算物流全部为0
    $order['shipping_fee'] = 0;


    //商品总额
    $order['total_fee'] = $order['goods_amount'] + $order['tax']  +  $order['shipping_fee'] + $order['insure_fee'] + $order['pay_fee'] + $order['card_fee'];

    //佣金总金额
    $order['commission_total_fee'] = $order['goods_amount'] + $order['tax']  + $order['insure_fee'] + $order['pay_fee'] + $order['pack_fee'] + $order['card_fee'] - $order['discount'] - $order['coupons'] - $order['integral_money'] - $order['bonus'];

    //商家比例
    $seller =  common_get_seller_commission_info($seller_id);
    $proportion = $seller['percent_value'];
    $order['drp_money'] = 0;
    $order['return_shippingfee'] = 0;
    if ($seller['commission_model']) {
        /**
         * 分类佣金
         */
        $cat_commission = common_get_cat_gain_should_amount($order);
        $gain_commission = $cat_commission['gain_commission'];
        $should_amount = $cat_commission['should_amount'];
    } else {
        /**
         * 店铺佣金
         */
        $commission = common_get_gain_should_amount($proportion, $order);
        $should_amount = $commission['should_amount'];
        $gain_commission = $commission['gain_commission'];
    }

    //商家比例
    $commission = common_get_seller_commission_info($seller_id);
    $other = [];
    $other['proportion'] = $commission['percent_value'];
    $other['commission_model'] = $commission['commission_model'];
    //分类比例
    $commission_rate = get_commission_rate($order_id, 1);
    $other['should_amount'] = $should_amount;
    $other['gain_commission'] = $gain_commission;

    //添加物流
    $other['shipping_fee'] = $order['seller_shipping_fee'];

    if ($insert_id) {
        //插入结算比例 及 应结金额
        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('seller_bill_order'), $other, 'UPDATE', "id = " .$insert_id);
    }

    //确认收货订单
    log_seller_account_change($seller_id, $should_amount, 0);
    merchants_account_log($seller_id, $should_amount, 0, '确认收货', 2);
}


/**
 * 订单确认收货后 退货
 * @param $order_id
 */
function order_refound_seller_has_confirm_account_change($order_id, $ret_id, $refund_amount) {
    if (true) {
        $confirm_take_time = gmtime();
        //已生成账单退款
        $sql = "SELECT * FROM ". $GLOBALS['ecs']->table('seller_bill_order') . " WHERE order_id = '$order_id' AND return_status = 0 order by id asc";
        $bill = $GLOBALS['db']->getRow($sql);
        if ($bill) {
            //已生成账单
            //再后一个账单添加减东西
            $seller_id = $GLOBALS['db']->getOne("SELECT ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '" .$order_id . "'", true);

            $other = array(
                'user_id' => $bill['user_id'],
                'seller_id' => $seller_id,
                'order_id' => $bill['order_id'],
                'return_rec_id' => $ret_id,
                'order_sn' => $bill['order_sn'],
                'order_status' => $bill['order_status'],
                'shipping_status' => SS_RECEIVED,
                'pay_status' => $bill['pay_status'],
                'return_amount' => $refund_amount,
                'return_shippingfee' => 0,
                'tax' => $bill['tax'],
                'bonus' => $bill['bonus'],
                'integral_money' => $bill['integral_money'],
                'coupons' => $bill['coupons'],
                'discount' => $bill['discount'],
                'value_card' => $bill['value_card'],
                'money_paid' => $bill['money_paid'],
                'confirm_take_time' => $confirm_take_time,
                'return_status' => 1
            );
            $other['proportion'] = $bill['proportion'];
            $other['commission_model'] = $bill['commission_model'];

            if ($seller_id) {
                $insert_id = get_order_bill_log($other);

                $sql = "SELECT order_id, user_id, order_sn , order_status, shipping_status, pay_status, " .
                    "order_amount, goods_amount, tax, shipping_fee, seller_shipping_fee, insure_fee, pay_fee, pack_fee, card_fee, " .
                    "bonus, integral_money, coupons, discount, money_paid, surplus" .
                    " FROM ".$GLOBALS['ecs']->table('order_info') ." WHERE order_id = '$order_id'";
                $order = $GLOBALS['db']->GetRow($sql);

                $seller_id = $GLOBALS['db']->getOne("SELECT ru_id FROM " .$GLOBALS['ecs']->table('order_goods'). " WHERE order_id = '$order_id'", true);
                $value_card = $GLOBALS['db']->getOne("SELECT use_val FROM " .$GLOBALS['ecs']->table('value_card_record'). " WHERE order_id = '$order_id'", true);

                //订单退款金额
                $order['return_amount'] = get_order_return_amount($order_id);

                //结算物流全部为0
                $order['shipping_fee'] = 0;


                //商品总额
                $order['total_fee'] = $order['goods_amount'] + $order['tax']  +  $order['shipping_fee'] + $order['insure_fee'] + $order['pay_fee'] + $order['card_fee'];

                //佣金总金额
                $order['commission_total_fee'] = $order['goods_amount'] + $order['tax']  + $order['insure_fee'] + $order['pay_fee'] + $order['pack_fee'] + $order['card_fee'] - $order['discount'] - $order['coupons'] - $order['integral_money'] - $order['bonus'];

                //商家比例
                $proportion = $bill['proportion'];
                $order['drp_money'] = 0;
                $order['return_shippingfee'] = 0;

                $sql = "SELECT * FROM ". $GLOBALS['ecs']->table('order_return') . " WHERE ret_id = '$ret_id'";
                $order_return = $GLOBALS['db']->getRow($sql);
                $goods_id = $order_return['goods_id'];

                if (!$bill['commission_model']) {
                    /**
                     * 店铺佣金
                     */
                    $commission = common_get_gain_should_amount($proportion, $order);
                    $should_amount = $commission['should_amount'];
                    $gain_commission = $commission['gain_commission'];
                }

                $refound_fee = order_refound_fee($order_id, $ret_id); //已退金额
                $order['return_amount'] += $refund_amount;
                if (!$bill['commission_model']) {
                    /**
                     * 店铺佣金
                     */

                    $commission2 = common_get_gain_should_amount($proportion, $order);
                    $should_amount2 = $commission2['should_amount'];
                    $gain_commission2 = $commission2['gain_commission'];
                }


                $other = [];
                if (!$bill['commission_model']) {
                    $seller_money = $should_amount - $should_amount2;
                    //商家应退金额
                    $other['should_amount'] = $seller_money;
                    $other['gain_commission'] = 0;
                } else {
                    //获取商品ID
                    /**
                     * 分类佣金
                     */
                    $sql = "SELECT goods_id, should_return FROM ".$GLOBALS['ecs']->table('order_return') ." WHERE ret_id = '$ret_id'";
                    $order_return = $GLOBALS['db']->GetRow($sql);
                    $goods_id = $order_return['goods_id'];
                    $should_return = $order_return['should_return'];

                    //商品分类比例
                    $sql = "SELECT proportion FROM ".$GLOBALS['ecs']->table('seller_bill_goods') ." WHERE order_id = '$order_id' AND goods_id = '$goods_id'";
                    $proportion = $GLOBALS['db']->GetOne($sql);

                    $seller_money = $should_return * $proportion;
                    $other['should_amount'] = $should_return * $proportion;
                    $other['gain_commission'] = 0;
                }


                if ($insert_id) {
                    //插入结算比例 及 应结金额
                    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('seller_bill_order'), $other, 'UPDATE', "id = " .$insert_id);
                }

                //确认收货后 退货
                log_seller_account_change($seller_id, -$seller_money, 0);
                merchants_account_log($seller_id, -$seller_money, 0, '退货', 2);
            }
        }
    }
}

/**
 * 导入物流价格
 * @param $invoice_no 发货单号
 * @param $weight  重量
 * @param $price    价格
 * @param int $add 每单添加金额
 */
function order_save_kuaidi_weight_price($kuaidi_id, $invoice_no, $weight, $price, $add=0) {
    $seller_shipping_fee = number_format(floatval($price) + intval($add), 2);
    $kuaidi_shipping_fee = number_format(floatval($price), 2);

    $sql = " UPDATE " . $GLOBALS['ecs']->table('order_info') . " SET seller_shipping_fee = $seller_shipping_fee, kuaidi_shipping_fee = '$kuaidi_shipping_fee', kuaidi_id = '$kuaidi_id', is_kuaidi_update = 1 WHERE invoice_no = '$invoice_no'" ;
    $GLOBALS['db']->query($sql);
}

/**
 * 平台分类佣金比例模式
 * 返回结果
 */
function common_get_cat_gain_should_amount($value, $commission_rate=0, $type=1){
    if ($value['goods_amount'] <= 0) {
        $value['goods_amount'] = 1;
    }

    $value['return_amount'] = $value['return_amount'] + $value['return_shippingfee'];

    if (1 == $type) {
        $commission_rate = get_order_goods_commission($value['order_id']);
    }

    if ($value['total_fee'] == $value['return_amount']) {
        $gain_commission = 0;
        $should_amount = 0;
    } else {
        $should_amount = $commission_rate * ($value['commission_total_fee'] - $value['return_amount']) / $value['goods_amount'] + $value['shipping_fee'];
        $gain_commission = $value['commission_total_fee'] - $should_amount - $value['drp_money'] - $value['return_amount'];
    }

    $arr = array(
        'gain_commission' => $gain_commission,
        'should_amount' => $should_amount
    );

    return $arr;
}

/**
 * 获取分类佣金比例金额
 */
function common_get_goods_cat_commission($order_id, $type = 0){
    $sql = " SELECT goods_id, goods_price, goods_number FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '$order_id' ";
    $order_goods = $GLOBALS['db']->getAll($sql);

    $commission = 0; //浮点数，保留两位数
    $cat = array();
    if ($order_goods) {
        foreach ($order_goods as $goods) {

            if($type == 1){
                $rate = get_commission_rate($goods['goods_id'], $type);

                $cat[$goods['goods_id']]['commission_rate'] = $rate['commission_rate'];
                $cat[$goods['goods_id']]['cat_id'] = $rate['cat_id'];

                $commission_rate = $rate['commission_rate'];
            }else{
                $commission_rate = get_commission_rate($goods['goods_id']);
            }
        }
    }
    return $commission_rate;
}

/**
 * 店铺佣金比例模式
 * 返回结果
 */
function common_get_gain_should_amount($proportion, $order){
    /**
     * 店铺佣金
     */
    $gain_proportion = 100 - $proportion;   //收取比例
    $should_proportion = $proportion;       //应结比例

    $arr = array();

    $order['return_amount'] = $order['return_amount'] + $order['return_shippingfee'];

    if ($order['return_amount'] == $order['total_fee']) {
        $arr['gain_commission'] = 0;
        $arr['should_amount'] = 0;
    } else {
        $gain_commission = ($order['commission_total_fee'] - $order['drp_money'] - $order['return_amount']) * ($gain_proportion / 100);
        $gain_commission = number_format($gain_commission, 2, '.', '');

        $arr['gain_commission'] = $gain_commission;

        //应结比例不包括 运费
        $arr['should_amount'] = $order['commission_total_fee'] - $gain_commission - $order['return_amount'];
    }

    return $arr;
}


/**
 * 获取订单应结金额
 * @param $order_id
 * @return int
 */
function order_seller_should_account ($order_id) {
    $sql = "SELECT * from " . $GLOBALS['ecs']->table('seller_jisuan_order') . " WHERE order_id = '$order_id'";
    $bills = $GLOBALS['db']->getAll($sql);
    $should_amount = 0;
    if ($bills) {
        $order = array_first($bills);
        foreach ($bills as $k => $bill) {
            if ($bill['type']) {
                $should_amount -= $bill['should_amount'];
            } else {
                $should_amount += $bill['should_amount'];
            }
        }
    }
    return $should_amount;
}

/**
 * 商家帐户变动记录
 *
 * @access  public
 * @param   int     $cat_id     分类的ID
 * @return  mix
 */
function merchants_account_log($ru_id, $user_money = 0, $frozen_money = 0, $change_desc, $change_type = 1){
    //$change_type 0提现  1充值  2结算 3管理员调节 4解冻 5支付 6退款
    if ($user_money || $frozen_money) {
        $log = array(
            'user_id' => $ru_id,
            'user_money' => $user_money,
            'frozen_money' => $frozen_money,
            'change_time' => gmtime(),
            'change_desc' => $change_desc,
            'change_type' => $change_type
        );
        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_account_log'), $log, 'INSERT');
    }
}

/**
 * 获得指定分类下的子分类的数组 临时函数  by kong
 *
 * @access  public
 * @param   int     $cat_id     分类的ID
 * @param   int     $selected   当前选中分类的ID
 * @param   boolean $re_type    返回的类型: 值为真时返回下拉列表,否则返回数组
 * @param   int     $level      限定返回的级数。为0时返回所有级数
 * @return  mix
 */
function article_cat_list_new($cat_id = 0, $selected = 0, $re_type = true, $level = 0)
{
    static $res = NULL;

    if ($res === NULL)
    {
        $data = read_static_cache('art_cat_pid_releate');

        if ($data === false)
        {
            $sql = "SELECT c.*, COUNT(s.cat_id) AS has_children, COUNT(a.article_id) AS aricle_num,a.description ".
               ' FROM ' . $GLOBALS['ecs']->table('article_cat') . " AS c".
               " LEFT JOIN " . $GLOBALS['ecs']->table('article_cat') . " AS s ON s.parent_id=c.cat_id".
               " LEFT JOIN " . $GLOBALS['ecs']->table('article') . " AS a ON a.cat_id=c.cat_id".
               " GROUP BY c.cat_id ".
               " ORDER BY parent_id, sort_order ASC";

            $res = $GLOBALS['db']->getAll($sql);

            write_static_cache('art_cat_pid_releate', $res);

        }
        else
        {
            $res = $data;
        }
    }

    if (empty($res) == true)
    {
        return $re_type ? '' : array();
    }

    $options = article_cat_options($cat_id, $res); // 获得指定分类下的子分类的数组

    /* 截取到指定的缩减级别 */
    if ($level > 0)
    {
        if ($cat_id == 0)
        {
            $end_level = $level;
        }
        else
        {
            $first_item = reset($options); // 获取第一个元素
            $end_level  = $first_item['level'] + $level;
        }

        /* 保留level小于end_level的部分 */
        foreach ($options AS $key => $val)
        {
            if ($val['level'] >= $end_level)
            {
                unset($options[$key]);
            }
        }
    }

    $pre_key = 0;
    foreach ($options AS $key => $value)
    {
        $options[$key]['has_children'] = 1;
        if ($pre_key > 0)
        {
            if ($options[$pre_key]['cat_id'] == $options[$key]['parent_id'])
            {
                $options[$pre_key]['has_children'] = 1;
            }
        }
        $pre_key = $key;
    }

    if ($re_type == true)
    {
        $select = '';
        foreach ($options AS $var)
        {
            $select .= '<li><a href="javascript:;" cat_type="'.$var['cat_type'].'" data-value="' . $var['cat_id'] . '" ';
            $select .= ' cat_type="' . $var['cat_type'] . '" class="ftx-01">';
            if ($var['level'] > 0)
            {
                $select .= str_repeat('&nbsp;', $var['level'] * 4);
            }
            $select .= htmlspecialchars(addslashes(str_replace("\r\n","",$var['cat_name']))) . '</a></li>';
        }

        return $select;
    }
}

/**
 * 获得指定分类下的子分类的数组
 *
 * @access  public
 * @param   int     $cat_id     分类的ID
 * @param   int     $selected   当前选中分类的ID
 * @param   boolean $re_type    返回的类型: 值为真时返回下拉列表,否则返回数组
 * @param   int     $level      限定返回的级数。为0时返回所有级数
 * @return  mix
 */
function article_cat_list($cat_id = 0, $selected = 0, $re_type = true, $level = 0)
{
    static $res = NULL;

    if ($res === NULL)
    {
        $data = read_static_cache('art_cat_pid_releate');

        if ($data === false)
        {
            $sql = "SELECT c.*, COUNT(s.cat_id) AS has_children, COUNT(a.article_id) AS aricle_num,a.description ".
               ' FROM ' . $GLOBALS['ecs']->table('article_cat') . " AS c".
               " LEFT JOIN " . $GLOBALS['ecs']->table('article_cat') . " AS s ON s.parent_id=c.cat_id".
               " LEFT JOIN " . $GLOBALS['ecs']->table('article') . " AS a ON a.cat_id=c.cat_id".
               " GROUP BY c.cat_id ".
               " ORDER BY parent_id, sort_order ASC";

            $res = $GLOBALS['db']->getAll($sql);

            write_static_cache('art_cat_pid_releate', $res);

        }
        else
        {
            $res = $data;
        }
    }

    if (empty($res) == true)
    {
        return $re_type ? '' : array();
    }

    $options = article_cat_options($cat_id, $res); // 获得指定分类下的子分类的数组

    /* 截取到指定的缩减级别 */
    if ($level > 0)
    {
        if ($cat_id == 0)
        {
            $end_level = $level;
        }
        else
        {
            $first_item = reset($options); // 获取第一个元素
            $end_level  = $first_item['level'] + $level;
        }

        /* 保留level小于end_level的部分 */
        foreach ($options AS $key => $val)
        {
            if ($val['level'] >= $end_level)
            {
                unset($options[$key]);
            }
        }
    }

    $pre_key = 0;
    foreach ($options AS $key => $value)
    {
        $options[$key]['has_children'] = 1;
        if ($pre_key > 0)
        {
            if ($options[$pre_key]['cat_id'] == $options[$key]['parent_id'])
            {
                $options[$pre_key]['has_children'] = 1;
            }
        }
        $pre_key = $key;
    }

    if ($re_type == true)
    {
        $select = '';
        foreach ($options AS $var)
        {
            $select .= '<option value="' . $var['cat_id'] . '" ';
            $select .= ' cat_type="' . $var['cat_type'] . '" ';
            $select .= ($selected == $var['cat_id']) ? "selected='ture'" : '';
            $select .= '>';
            if ($var['level'] > 0)
            {
                $select .= str_repeat('&nbsp;', $var['level'] * 4);
            }
            $select .= htmlspecialchars(addslashes($var['cat_name'])) . '</option>';
        }

        return $select;
    }
    else
    {
        foreach ($options AS $key => $value)
        {
            $options[$key]['url'] = build_uri('article_cat', array('acid' => $value['cat_id']), $value['cat_name']);
        }
        return $options;
    }
}
/**
 * 过滤和排序所有文章分类，返回一个带有缩进级别的数组
 *
 * @access  private
 * @param   int     $cat_id     上级分类ID
 * @param   array   $arr        含有所有分类的数组
 * @param   int     $level      级别
 * @return  void
 */
function article_cat_options($spec_cat_id, $arr)
{
    static $cat_options = array();

    if (isset($cat_options[$spec_cat_id]))
    {
        return $cat_options[$spec_cat_id];
    }

    if (!isset($cat_options[0]))
    {
        $level = $last_cat_id = 0;
        $options = $cat_id_array = $level_array = array();
        while (!empty($arr))
        {
            foreach ($arr AS $key => $value)
            {
                $cat_id = $value['cat_id'];
                if ($level == 0 && $last_cat_id == 0)
                {
                    if ($value['parent_id'] > 0)
                    {
                        break;
                    }

                    $options[$cat_id]          = $value;
                    $options[$cat_id]['level'] = $level;
                    $options[$cat_id]['id']    = $cat_id;
                    $options[$cat_id]['name']  = $value['cat_name'];
                    unset($arr[$key]);

                    if ($value['has_children'] == 0)
                    {
                        continue;
                    }
                    $last_cat_id  = $cat_id;
                    $cat_id_array = array($cat_id);
                    $level_array[$last_cat_id] = ++$level;
                    continue;
                }

                if ($value['parent_id'] == $last_cat_id)
                {
                    $options[$cat_id]          = $value;
                    $options[$cat_id]['level'] = $level;
                    $options[$cat_id]['id']    = $cat_id;
                    $options[$cat_id]['name']  = $value['cat_name'];
                    unset($arr[$key]);

                    if ($value['has_children'] > 0)
                    {
                        if (end($cat_id_array) != $last_cat_id)
                        {
                            $cat_id_array[] = $last_cat_id;
                        }
                        $last_cat_id    = $cat_id;
                        $cat_id_array[] = $cat_id;
                        $level_array[$last_cat_id] = ++$level;
                    }
                }
                elseif ($value['parent_id'] > $last_cat_id)
                {
                    break;
                }
            }

            $count = count($cat_id_array);
            if ($count > 1)
            {
                $last_cat_id = array_pop($cat_id_array);
            }
            elseif ($count == 1)
            {
                if ($last_cat_id != end($cat_id_array))
                {
                    $last_cat_id = end($cat_id_array);
                }
                else
                {
                    $level = 0;
                    $last_cat_id = 0;
                    $cat_id_array = array();
                    continue;
                }
            }

            if ($last_cat_id && isset($level_array[$last_cat_id]))
            {
                $level = $level_array[$last_cat_id];
            }
            else
            {
                $level = 0;
            }
        }
        $cat_options[0] = $options;
    }
    else
    {
        $options = $cat_options[0];
    }

    if (!$spec_cat_id)
    {
        return $options;
    }
    else
    {
        if (empty($options[$spec_cat_id]))
        {
            return array();
        }

        $spec_cat_id_level = $options[$spec_cat_id]['level'];

        foreach ($options AS $key => $value)
        {
            if ($key != $spec_cat_id)
            {
                unset($options[$key]);
            }
            else
            {
                break;
            }
        }

        $spec_cat_id_array = array();
        foreach ($options AS $key => $value)
        {
            if (($spec_cat_id_level == $value['level'] && $value['cat_id'] != $spec_cat_id) ||
                ($spec_cat_id_level > $value['level']))
            {
                break;
            }
            else
            {
                $spec_cat_id_array[$key] = $value;
            }
        }
        $cat_options[$spec_cat_id] = $spec_cat_id_array;

        return $spec_cat_id_array;
    }
}

/**
 * 调用UCenter的函数
 *
 * @param   string  $func
 * @param   array   $params
 *
 * @return  mixed
 */
function uc_call($func, $params=null)
{
    restore_error_handler();
    if (!function_exists($func))
    {
        include_once(ROOT_PATH . 'uc_client/client.php');
    }

    $res = call_user_func_array($func, $params);

    set_error_handler('exception_handler');

    return $res;
}

/**
 * error_handle回调函数
 *
 * @return
 */
function exception_handler($errno, $errstr, $errfile, $errline)
{
    return;
}

/**
 * 重新获得商品图片与商品相册的地址
 *
 * @param int $goods_id 商品ID
 * @param string $image 原商品相册图片地址
 * @param boolean $thumb 是否为缩略图
 * @param string $call 调用方法(商品图片还是商品相册)
 * @param boolean $del 是否删除图片
 *
 * @return string   $url
 */
function get_image_path($goods_id, $image = '', $thumb = false, $call = 'goods', $del = false, $retain = false)
{
    //OSS文件存储ecmoban模板堂 --zhuo start
    if (!empty($image) && (strpos($image, 'http://') === false && strpos($image, 'https://') === false && strpos($image, 'errorImg.png') === false)) {
        if ($GLOBALS['_CFG']['open_oss'] == 1) {
            $bucket_info = get_bucket_info();
            $image = $bucket_info['endpoint'] . $image;
        } else {
            $image = $GLOBALS['_CFG']['site_domain'] . $image;
        }
    }
    //OSS文件存储ecmoban模板堂 --zhuo end

    $return = is_admin_seller_path();

    if($return < 3){

        if (!empty($image) && (strpos($image, 'http://') === false && strpos($image, 'https://') === false && strpos($image, 'errorImg.png') === false)) {
            if($return == 1){
                $image = $GLOBALS['ecs']->url() . $image;
            }elseif($return == 2){
                $image = $GLOBALS['ecs']->seller_url() . $image;
            }else{
                $image = $GLOBALS['ecs']->stores_url() . $image;
            }
        }
    }

    if($retain){
        $url = $image;
    }else{
        $url = empty($image) ? $GLOBALS['_CFG']['no_picture'] : $image;
    }

    return $url;
}

/**
 * 调用使用UCenter插件时的函数
 *
 * @param   string  $func
 * @param   array   $params
 *
 * @return  mixed
 */
function user_uc_call($func, $params = null)
{
    if (isset($GLOBALS['_CFG']['integrate_code']) && $GLOBALS['_CFG']['integrate_code'] == 'ucenter')
    {
        restore_error_handler();
        if (!function_exists($func))
        {
            include_once(ROOT_PATH . 'includes/lib_uc.php');
        }

        $res = call_user_func_array($func, $params);

        set_error_handler('exception_handler');

        return $res;
    }
    else
    {
        return;
    }

}

/**
 * 取得商品优惠价格列表
 *
 * @param   string  $goods_id    商品编号
 * @param   string  $price_type  价格类别(0为全店优惠比率，1为商品优惠价格，2为分类优惠比率)
 *
 * @return  优惠价格列表
 */
function get_volume_price_list($goods_id, $price_type = '1')
{
    $volume_price = array();
    $temp_index   = '0';

    $sql = "SELECT `id` , `volume_number` , `volume_price`".
           " FROM " .$GLOBALS['ecs']->table('volume_price'). "".
           " WHERE `goods_id` = '" . $goods_id . "' AND `price_type` = '" . $price_type . "'".
           " ORDER BY `volume_number`";

    $res = $GLOBALS['db']->getAll($sql);

    foreach ($res as $k => $v)
    {
        $volume_price[$temp_index]['id']       = $v['id'];
        $volume_price[$temp_index]['number']       = $v['volume_number'];
        $volume_price[$temp_index]['price']        = $v['volume_price'];
        $volume_price[$temp_index]['format_price'] = price_format($v['volume_price']);
        $temp_index ++;
    }
    return $volume_price;
}

/**
 * 取得商品最终使用价格
 *
 * @param   string  $goods_id      商品编号
 * @param   string  $goods_num     购买数量
 * @param   boolean $is_spec_price 是否加入规格价格
 * @param   mix     $spec          规格ID的数组或者逗号分隔的字符串
 * @param   intval  $add_tocart      0,1  1代表非购物车进入该方法（SKU价格）
 * @param   intval  $show_goods      0,1  商品详情页ajax，1代表SKU价格开启（SKU价格）
 *
 * @return  商品最终购买价格
 */
function get_final_price($goods_id, $goods_num = '1', $is_spec_price = false, $spec = array(), $warehouse_id = 0, $area_id = 0, $type = 0, $presale = 0, $add_tocart = 1, $show_goods = 0, $product_promote_price = 0) {

    $spec_price = 0;

    $warehouse_area['warehouse_id'] = $warehouse_id;
    $warehouse_area['area_id'] = $area_id;

    if ($is_spec_price) {
        if (!empty($spec)) {
            $spec_price = spec_price($spec, $goods_id, $warehouse_area);
        }
    }

    $final_price = '0'; //商品最终购买价格
    $volume_price = '0'; //商品优惠价格
    $promote_price = '0'; //商品促销价格
    $user_price = '0'; //商品会员价格
    $user_rank = $_SESSION['user_rank']; //用户等级

    //取得商品优惠价格列表
    $price_list = get_volume_price_list($goods_id, '1');

    if (!empty($price_list)) {
        foreach ($price_list as $value) {
            if ($goods_num >= $value['number']) {
                $volume_price = $value['price'];
            }
        }
    }

    //预售条件---预售没有会员价、、折扣价
    $sql = "SELECT count(*) FROM " . $GLOBALS['ecs']->table('presale_activity') . " AS pa, " . $GLOBALS['ecs']->table('goods') . " AS g WHERE pa.goods_id = '$goods_id' AND pa.review_status = 3 AND pa.goods_id = g.goods_id ";
    $is_presale = $GLOBALS['db']->getOne($sql);

    $where = "";
    if ($is_presale > 0 || $presale == 1) {
        $user_rank = 1;
        $discount = 1; //会员折扣
    }else{

        //zxk
        //样品条件--样品没有会员价、、折扣价
        $sql = "SELECT count(*) FROM " . $GLOBALS['ecs']->table('sample_activity') . " AS sa, " . $GLOBALS['ecs']->table('goods') . " AS g WHERE sa.goods_id = '$goods_id' AND sa.review_status = 3 AND sa.goods_id = g.goods_id AND g.is_on_sale = 0";
        $is_sample = $GLOBALS['db']->getOne($sql);
        if ($is_sample) {
            $user_rank = 1;
            $discount = 1; //会员折扣
        } else {
            $discount = $_SESSION['discount']; //会员折扣
        }
    }
    //ecmoban模板堂 --zhuo start

    $leftJoin = '';
    $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('warehouse_goods') . " AS wg ON g.goods_id = wg.goods_id AND wg.region_id = '$warehouse_id' ";
    $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('warehouse_area_goods') . " AS wag ON g.goods_id = wag.goods_id AND wag.region_id = '$area_id' ";
    //ecmoban模板堂 --zhuo end
    //取得商品促销价格列表
    /* 取得商品信息 */
    $sql = "SELECT " .
            "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$discount'), g.shop_price * '$discount')  AS shop_price, " .
            "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, " .
            " g.promote_start_date, g.promote_end_date, mp.user_price, g.user_id, g.model_price, g.model_attr " .
            " FROM " . $GLOBALS['ecs']->table('goods') . " AS g " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp ON mp.goods_id = g.goods_id AND mp.user_rank = '$user_rank' " .
            $leftJoin .
            " WHERE g.goods_id = '" . $goods_id . "'" .
            " AND g.is_delete = 0 LIMIT 1";
    $goods = $GLOBALS['db']->getRow($sql);

    if($GLOBALS['_CFG']['add_shop_price'] == 0 && empty($product_promote_price)){
        $product_spec = !empty($spec) && is_array($spec) ? implode(",", $spec) : '';
        $products = get_warehouse_id_attr_number($goods_id, $product_spec, $goods['user_id'], $warehouse_id, $area_id);
        $product_promote_price = isset($products['product_promote_price']) ? $products['product_promote_price'] : 0;
    }

    if($GLOBALS['_CFG']['add_shop_price'] == 0 && !empty($product_promote_price)){
        $goods['promote_price'] = $product_promote_price;
    }

    /* 计算商品的促销价格 */
    if ($goods['promote_price'] > 0) {
        $promote_price = bargain_price($goods['promote_price'], $goods['promote_start_date'], $goods['promote_end_date']);
    } else {
        $promote_price = 0;
    }

    //取得商品会员价格列表
    if(!empty($spec_price) && $GLOBALS['_CFG']['add_shop_price'] == 0){
        if($add_tocart == 1){
            $user_price = $goods['shop_price'];
        }else{
            /* 会员等级价格 */
            if(!empty($goods['user_price'])){
                $user_price = $goods['user_price'];
            }else{
                $user_price = $spec_price * $discount;
            }
        }

        /* SKU价格 */
        if($show_goods == 1){
            /* 会员等级价格 */
            if(!empty($goods['user_price'])){
                $spec_price = $goods['user_price'];
            }else{
                $spec_price = $spec_price * $discount;
            }
        }
    }else{
        $user_price = $goods['shop_price'];
    }

    //比较商品的促销价格，会员价格，优惠价格
    if (empty($volume_price) && empty($promote_price)) {
        //如果优惠价格，促销价格都为空则取会员价格
        $final_price = $user_price;
    } elseif (!empty($volume_price) && empty($promote_price)) {
        //如果优惠价格为空时不参加这个比较。
        $final_price = min($volume_price, $user_price);
    } elseif (empty($volume_price) && !empty($promote_price)) {
        //如果促销价格为空时不参加这个比较。
        $final_price = min($promote_price, $user_price);
    } elseif (!empty($volume_price) && !empty($promote_price)) {
        //取促销价格，会员价格，优惠价格最小值
        $final_price = min($volume_price, $promote_price, $user_price);
    } else {
        $final_price = $user_price;
    }

    //如果需要加入规格价格
    if ($is_spec_price) {
        if (!empty($spec)) {
            if ($type == 0) {
                if($add_tocart == 1){
                    $final_price += $spec_price;
                }
            }
        }
    }

    if ($GLOBALS['_CFG']['add_shop_price'] == 1) {
        if ($type == 1 && $promote_price == 0) {
            //返回商品属性价
            $final_price = $spec_price;
        }
    }

    //返回商品最终购买价格
    return $final_price;
}

/**
 * 将 goods_attr_id 的序列按照 attr_id 重新排序
 *
 * 注意：非规格属性的id会被排除
 *
 * @access      public
 * @param       array       $goods_attr_id_array        一维数组
 * @param       string      $sort                       序号：asc|desc，默认为：asc
 *
 * @return      string
 */
function sort_goods_attr_id_array($goods_attr_id_array, $sort = 'asc')
{
    if (empty($goods_attr_id_array))
    {
        return $goods_attr_id_array;
    }

    //重新排序
    $sql = "SELECT a.attr_type, v.attr_value, v.goods_attr_id, attr_checked
            FROM " .$GLOBALS['ecs']->table('attribute'). " AS a
            LEFT JOIN " .$GLOBALS['ecs']->table('goods_attr'). " AS v
                ON v.attr_id = a.attr_id
                AND a.attr_type = 1
            WHERE v.goods_attr_id " . db_create_in($goods_attr_id_array) . "
            ORDER BY a.sort_order, a.attr_id, v.goods_attr_id $sort";

    $row = $GLOBALS['db']->GetAll($sql);

    $return_arr = array();
    foreach ($row as $value)
    {
        $return_arr['sort'][]   = $value['goods_attr_id'];
        $return_arr['row'][$value['goods_attr_id']]    = $value;
    }

    return $return_arr;
}

/**
 *
 * 是否存在规格
 *
 * @access      public
 * @param       array       $goods_attr_id_array        一维数组
 *
 * @return      string
 */
function is_spec($goods_attr_id_array, $sort = 'asc')
{
    if (empty($goods_attr_id_array))
    {
        return $goods_attr_id_array;
    }

    //重新排序
    $sql = "SELECT a.attr_type, v.attr_value, v.goods_attr_id
            FROM " .$GLOBALS['ecs']->table('attribute'). " AS a
            LEFT JOIN " .$GLOBALS['ecs']->table('goods_attr'). " AS v
                ON v.attr_id = a.attr_id
                AND a.attr_type = 1
            WHERE v.goods_attr_id " . db_create_in($goods_attr_id_array) . "
            ORDER BY a.sort_order, a.attr_id, v.goods_attr_id $sort";
    $row = $GLOBALS['db']->GetAll($sql);

    $return_arr = array();
    foreach ($row as $value)
    {
        $return_arr['sort'][]   = $value['goods_attr_id'];

        $return_arr['row'][$value['goods_attr_id']]    = $value;
    }

    if(!empty($return_arr))
    {
        return true;
    }
    else
    {
        return false;
    }
}

/**
 * 获取指定id package 的信息
 *
 * @access  public
 * @param   int         $id         package_id
 *
 * @return array       array(package_id, package_name, goods_id,start_time, end_time, min_price, integral)
 */
function get_package_info($id, $path = '')
{
    global $ecs, $db,$_CFG;
    $id = is_numeric($id)?intval($id):0;
    $now = gmtime();

    $where = '';
    if(empty($path)){
        $where = " AND review_status = 3 ";
    }

    $sql = "SELECT act_id AS id, user_id AS ru_id, act_name AS package_name, goods_id , goods_name, start_time, end_time, act_desc, ext_info, user_id, activity_thumb, review_status, review_content ".
           " FROM " . $GLOBALS['ecs']->table('goods_activity') .
           " WHERE act_id='$id' AND act_type = " . GAT_PACKAGE . $where;

    $package = $GLOBALS['db']->GetRow($sql);

    /* 将时间转成可阅读格式 */
    if ($package['start_time'] <= $now && $package['end_time'] >= $now)
    {
        $package['is_on_sale'] = "1";
    }
    else
    {
        $package['is_on_sale'] = "0";
    }
    $package['start_time'] = local_date('Y-m-d H:i:s', $package['start_time']);
    $package['end_time']   = local_date('Y-m-d H:i:s', $package['end_time']);
    $row = unserialize($package['ext_info']);
    unset($package['ext_info']);
    if ($row)
    {
        foreach ($row as $key=>$val)
        {
            $package[$key] = $val;
        }
    }

    $sql = "SELECT pg.package_id, pg.goods_id, pg.goods_number, pg.admin_id, ".
           " g.goods_sn, g.goods_name, g.market_price, g.goods_thumb, g.is_real, ".
           " IFNULL(mp.user_price, g.shop_price * '$_SESSION[discount]') AS rank_price " .
           " FROM " . $GLOBALS['ecs']->table('package_goods') . " AS pg ".
           "   LEFT JOIN ". $GLOBALS['ecs']->table('goods') . " AS g ".
           "   ON g.goods_id = pg.goods_id ".
           " LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp ".
                "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' ".
           " WHERE pg.package_id = " . $id. " ".
           " ORDER BY pg.package_id, pg.goods_id";

    $goods_res = $GLOBALS['db']->getAll($sql);

    $market_price        = 0;
    $real_goods_count    = 0;
    $virtual_goods_count = 0;

    foreach($goods_res as $key => $val)
    {
        $goods_res[$key]['goods_thumb']         = get_image_path($val['goods_id'], $val['goods_thumb'], true);
        $goods_res[$key]['market_price_format'] = price_format($val['market_price']);
        $goods_res[$key]['rank_price_format']   = price_format($val['rank_price']);
        $market_price += $val['market_price'] * $val['goods_number'];
        /* 统计实体商品和虚拟商品的个数 */
        if ($val['is_real'])
        {
            $real_goods_count++;
        }
        else
        {
            $virtual_goods_count++;
        }
    }

    if ($real_goods_count > 0)
    {
        $package['is_real']            = 1;
    }
    else
    {
        $package['is_real']            = 0;
    }

    $package['goods_list']            = $goods_res;
    $package['market_package']        = $market_price;
    $package['market_package_format'] = price_format($market_price);
    $package['package_price_format']  = price_format($package['package_price']);

    return $package;
}

/**
 * 获得指定礼包的商品
 *
 * @access  public
 * @param   integer $package_id
 * @return  array
 */
function get_package_goods($package_id, $seller_id = 0 ,$type = 0)
{
    $sql = "SELECT pg.goods_id, g.goods_name, pg.goods_number, p.goods_attr, p.product_number, p.product_id, g.goods_weight, g.goods_thumb ,g.shop_price
            FROM " . $GLOBALS['ecs']->table('package_goods') . " AS pg
                LEFT JOIN " .$GLOBALS['ecs']->table('goods') . " AS g ON pg.goods_id = g.goods_id
                LEFT JOIN " . $GLOBALS['ecs']->table('products') . " AS p ON pg.product_id = p.product_id
            WHERE pg.package_id = '$package_id'";
    if ($package_id == 0 && $seller_id == 0)
    {
        $sql .= " AND pg.admin_id = '$_SESSION[admin_id]'";
    }
    elseif ($package_id == 0 && $seller_id > 0)
    {
        $sql .= " AND pg.admin_id = '$_SESSION[seller_id]'";
    }

    $resource = $GLOBALS['db']->query($sql);
    if (!$resource)
    {
        return array();
    }

    $row = array();

    /* 生成结果数组 取存在货品的商品id 组合商品id与货品id */
    $good_product_str = '';
    while ($_row = $GLOBALS['db']->fetch_array($resource))
    {
        $_row['goods_thumb'] = get_image_path($_row['goods_id'], $_row['goods_thumb'], true);

        /* 商品重量 */
        $_row['goodsweight']  = $_row['goods_weight'];

        if ($_row['product_id'] > 0)
        {
            /* 取存商品id */
            $good_product_str .= ',' . $_row['goods_id'];

            /* 组合商品id与货品id */
            $_row['g_p'] = $_row['goods_id'] . '_' . $_row['product_id'];
        }
        else
        {
            /* 组合商品id与货品id */
            $_row['g_p'] = $_row['goods_id'];
        }

        $_row['url']  = build_uri('goods', array('gid' => $_row['goods_id']), $_row['goods_name']);
        $_row['shop_price'] = price_format($_row['shop_price']);
        if($type == 1){
            $_row['products'] = get_good_products($_row['goods_id']);
        }
        //生成结果数组
        $row[] = $_row;
    }
    $good_product_str = trim($good_product_str, ',');

    /* 释放空间 */
    unset($resource, $_row, $sql);

    /* 取商品属性 */
    if ($good_product_str != '')
    {
        $sql = "SELECT goods_attr_id, attr_value FROM " .$GLOBALS['ecs']->table('goods_attr'). " WHERE goods_id IN ($good_product_str)";
        $result_goods_attr = $GLOBALS['db']->getAll($sql);

        $_goods_attr = array();
        foreach ($result_goods_attr as $value)
        {
            $_goods_attr[$value['goods_attr_id']] = $value['attr_value'];
        }
    }

    /* 过滤货品 */
    $format[0] = '%s[%s]--[%d]';
    $format[1] = '%s--[%d]';
    foreach ($row as $key => $value)
    {
        $row[$key]['goods_name_pack'] = $value['goods_name'];
        if ($value['goods_attr'] != '')
        {
            $goods_attr_array = explode('|', $value['goods_attr']);

            $goods_attr = array();
            foreach ($goods_attr_array as $_attr)
            {
                $goods_attr[] = $_goods_attr[$_attr];
            }

            $row[$key]['goods_name'] = sprintf($format[0], $value['goods_name'], implode('，', $goods_attr), $value['goods_number']);
        }
        else
        {
            $row[$key]['goods_name'] = sprintf($format[1], $value['goods_name'], $value['goods_number']);
        }
    }

    return $row;
}

/**
 * 取商品的货品列表
 *
 * @param       mixed       $goods_id       单个商品id；多个商品id数组；以逗号分隔商品id字符串
 * @param       string      $conditions     sql条件
 *
 * @return  array
 */
function get_good_products($goods_id, $conditions = '')
{
    if (empty($goods_id))
    {
        return array();
    }

    switch (gettype($goods_id))
    {
        case 'integer':

            $_goods_id = "goods_id = '" . intval($goods_id) . "'";

        break;

        case 'string':
        case 'array':

            $_goods_id = db_create_in($goods_id, 'goods_id');

        break;
    }

    /* 取货品 */
    $sql = "SELECT * FROM " .$GLOBALS['ecs']->table('products'). " WHERE $_goods_id $conditions";
    $result_products = $GLOBALS['db']->getAll($sql);

    /* 取商品属性 */
    $sql = "SELECT goods_attr_id, attr_value FROM " .$GLOBALS['ecs']->table('goods_attr'). " WHERE $_goods_id";
    $result_goods_attr = $GLOBALS['db']->getAll($sql);

    $_goods_attr = array();
    foreach ($result_goods_attr as $value)
    {
        $_goods_attr[$value['goods_attr_id']] = $value['attr_value'];
    }

    /* 过滤货品 */
    foreach ($result_products as $key => $value)
    {
        $goods_attr_array = explode('|', $value['goods_attr']);
        if (is_array($goods_attr_array))
        {
            $goods_attr = array();
            foreach ($goods_attr_array as $_attr)
            {
                $goods_attr[] = $_goods_attr[$_attr];
            }

            $goods_attr_str = implode('，', $goods_attr);
        }

        $result_products[$key]['goods_attr_str'] = $goods_attr_str;
    }

    return $result_products;
}

/**
 * 取商品的下拉框Select列表
 *
 * @param       int      $goods_id    商品id
 *
 * @return  array
 */
function get_good_products_select($goods_id)
{
    $return_array = array();
    $products = get_good_products($goods_id);

    if (empty($products))
    {
        return $return_array;
    }

    foreach ($products as $value)
    {
        $return_array[$value['product_id']] = $value['goods_attr_str'];
    }

    return $return_array;
}

/**
 * 取商品的规格列表
 *
 * @param       int      $goods_id    商品id
 * @param       string   $conditions  sql条件
 *
 * @return  array
 */
function get_specifications_list($goods_id, $conditions = '')
{
    /* 取商品属性 */
    $sql = "SELECT ga.goods_attr_id, ga.attr_id, ga.attr_value, a.attr_name
            FROM " .$GLOBALS['ecs']->table('goods_attr'). " AS ga, " .$GLOBALS['ecs']->table('attribute'). " AS a
            WHERE ga.attr_id = a.attr_id
            AND ga.goods_id = '$goods_id'
            $conditions";
    $result = $GLOBALS['db']->getAll($sql);

    $return_array = array();
    foreach ($result as $value)
    {
        $return_array[$value['goods_attr_id']] = $value;
    }

    return $return_array;
}

/**
 * 调用array_combine函数
 *
 * @param   array  $keys
 * @param   array  $values
 *
 * @return  $combined
 */
if (!function_exists('array_combine')) {
    function array_combine($keys, $values)
    {
        if (!is_array($keys)) {
            user_error('array_combine() expects parameter 1 to be array, ' .
                gettype($keys) . ' given', E_USER_WARNING);
            return;
        }

        if (!is_array($values)) {
            user_error('array_combine() expects parameter 2 to be array, ' .
                gettype($values) . ' given', E_USER_WARNING);
            return;
        }

        $key_count = count($keys);
        $value_count = count($values);
        if ($key_count !== $value_count) {
            user_error('array_combine() Both parameters should have equal number of elements', E_USER_WARNING);
            return false;
        }

        if ($key_count === 0 || $value_count === 0) {
            user_error('array_combine() Both parameters should have number of elements at least 0', E_USER_WARNING);
            return false;
        }

        $keys    = array_values($keys);
        $values  = array_values($values);

        $combined = array();
        for ($i = 0; $i < $key_count; $i++) {
            $combined[$keys[$i]] = $values[$i];
        }

        return $combined;
    }
}

//ecmoban模板堂 --zhuo start
function get_class_nav($cat_id, $table = 'category'){

	$sql = "select cat_id,cat_name,parent_id from " . $GLOBALS['ecs']->table($table) ." where cat_id = '$cat_id'";
	$res = $GLOBALS['db']->getAll($sql);

	foreach($res as $key => $row){
		$arr[$key]['cat_id'] 	= $row['cat_id'];
		$arr[$key]['cat_name'] 	= $row['cat_name'];
		$arr[$key]['parent_id'] = $row['parent_id'];

		$arr['catId'] .= $row['cat_id'] . ",";
		$arr[$key]['child'] = get_parent_child($row['cat_id'], $table);

		if(empty($arr[$key]['child']['catId'])){
			$arr['catId'] = $arr['catId'];
		}else{
			$arr['catId'] .= $arr[$key]['child']['catId'];
		}
	}

	return $arr;
}

function get_parent_child($parent_id = 0, $table = 'category'){
	$sql = "select cat_id,cat_name,parent_id from " . $GLOBALS['ecs']->table($table) ." where parent_id = '$parent_id'";
	$res = $GLOBALS['db']->getAll($sql);

	foreach($res as $key => $row){
		$arr[$key]['cat_id'] 	= $row['cat_id'];
		$arr[$key]['cat_name'] 	= $row['cat_name'];
		$arr[$key]['parent_id'] = $row['parent_id'];

		$arr['catId'] .= $row['cat_id'] . ",";
		$arr[$key]['child'] = get_parent_child($row['cat_id']);

		$arr['catId'] .= $arr[$key]['child']['catId'];
	}

	return $arr;
}

/**
 * 查询扩展分类商品id
 *
 *@param int cat_id
 *
 *@return int extentd_count
 * by guan
 */
function get_goodsCat_num($cat_id, $goods_ids = array(), $ruCat = '')
{
    $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('goods_cat') . " AS gc left join " .$GLOBALS['ecs']->table('goods'). " as g on gc.goods_id = g.goods_id WHERE g.is_delete = 0 and gc.cat_id in($cat_id)" . $ruCat;

	$cat_goods = $GLOBALS['db']->getAll($sql);
	foreach($cat_goods as $key => $val)
	{
		if(in_array($val['goods_id'], $goods_ids))
		{
			unset($cat_goods[$key]);
		}
	}
    return count($cat_goods);
}

//guan start end

/**
 * 商品限购
 */
function get_purchasing_goods_info($goods_id = 0) {//获取商品限购数量
    $sql = "SELECT is_xiangou,xiangou_num, xiangou_start_date, xiangou_end_date, goods_name FROM " . $GLOBALS['ecs']->table('goods') . "WHERE goods_id = '$goods_id' LIMIT 1";
    return $GLOBALS['db']->getRow($sql);
}

/**
 * 查询限购商品已购买数量
 */
function get_for_purchasing_goods($start_date = 0, $end_date = 0, $goods_id = 0, $user_id = 0, $extension_code = '',$attr_id = ''){

	$where = '';
	if(!empty($extension_code)){
		$where = " AND oi.extension_code = '$extension_code'";
	}
        if($attr_id){
            $where .= " AND og.goods_attr_id = '".$attr_id."'";
        }
        $where .= " AND oi.order_status <> " . OS_CANCELED;
        if($extension_code != 'group_buy'){
            $where .= " AND oi.user_id = " . $user_id;
        }
	$sql = "SELECT og.goods_number FROM " .$GLOBALS['ecs']->table('order_goods'). " as og, ".$GLOBALS['ecs']->table('order_info'). " as oi " .
                "WHERE oi.order_id = og.order_id " .
                " AND og.goods_id = '" .$goods_id. "' AND oi.add_time > '" . $start_date . "' AND oi.add_time < '" . $end_date ."'". $where;
	$res = $GLOBALS['db']->getAll($sql);

        $goods_number = 0;
        foreach($res as $row){
            $goods_number += $row['goods_number'];
        }

        return array('goods_number' => $goods_number);
}

/**
 * 查询限购商品已购买数量 by zxk  覆盖 get_for_purchasing_goods
 */
function get_for_purchasing_goods_new($act_id, $extension_code = '') {
    $where = '';

    if(!empty($extension_code)){
        $where = " AND oi.extension_code = '$extension_code'";
    }

    //订单状态
    $where .= " AND oi.order_status not in (" .OS_CANCELED .",".OS_INVALID.",".OS_RETURNED_PART.")";
    if ($extension_code == 'group_buy') {
        //支付状态
        $where .= " AND oi.pay_status = " . PS_PAYED;
    } elseif ($extension_code == 'presale') {
        $where .= " AND oi.pay_status not in (" .PS_PAYED .",".PS_PAYED_PART.")";
    }


    $sql = "SELECT og.goods_number FROM " .$GLOBALS['ecs']->table('order_goods'). " as og, ".$GLOBALS['ecs']->table('order_info'). " as oi " .
        "WHERE oi.order_id = og.order_id " . " AND oi.extension_id = " . $act_id . $where;
    $res = $GLOBALS['db']->getAll($sql);



    $goods_number = 0;
    foreach($res as $row){
        $goods_number += $row['goods_number'];
    }

    return array('goods_number' => $goods_number);
}

/**
 * 查询店铺分类
 */
function get_fine_store_category($options, $web_type, $array_type = 0, $ru_id){

	$cat_array = array();
	if($web_type == 'admin' || $web_type == 'goodsInfo'){
		$sql = "select cat_id, user_id from " .$GLOBALS['ecs']->table('merchants_category'). " where 1";
		$store_cat = $GLOBALS['db']->getAll($sql);

		foreach($store_cat as $row){
			$cat_array[$row['cat_id']]['cat_id'] = $row['cat_id'];
			$cat_array[$row['cat_id']]['user_id'] = $row['user_id'];
		}
	}

	if($web_type == 'admin'){
		if($cat_array){
			if($array_type == 0){
				$options = array_diff_key($options, $cat_array);
			}else{
				$options = array_intersect_key($options, $cat_array);
			}
		}

		return $options;
	}elseif($web_type == 'goodsInfo' && $ru_id == 0){
		$options = array_diff_key($options, $cat_array);
		return $options;
	}else{
		return $options;
	}
}
//ecmoban模板堂 --zhuo end

/* 记录浏览历史 ecmoban模板堂 --zhuo start 浏览列表插件*/
function cate_history($size, $page, $sort, $order, $warehouse_id = 0, $area_id = 0, $ship = 0, $self = 0) {
    $str = '';
    if (!empty($_COOKIE['ECS']['list_history'])) {
        $where = db_create_in($_COOKIE['ECS']['list_history'], 'g.goods_id');

        if($self == 1){
            $where .= " AND (g.user_id = 0 or msi.self_run = 1) ";
        }

        if($ship == 1){ //ecmoban模板堂 --zhuo
            $where .= " AND g.is_shipping = 1 ";
        }

        $leftJoin = '';

        $shop_price = "wg.warehouse_price, wg.warehouse_promote_price, wag.region_price, wag.region_promote_price, g.model_price, g.model_attr, ";
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
		$leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_shop_information') . " as msi on msi.user_id = g.user_id ";

        if ($GLOBALS['_CFG']['open_area_goods'] == 1) {
            $leftJoin .= " left join " . $GLOBALS['ecs']->table('link_area_goods') . " as lag on g.goods_id = lag.goods_id ";
            $where .= " and lag.region_id = '$area_id' ";
        }

        if ($sort == 'last_update') {
            $sort = 'g.last_update';
        }

        $sql = 'SELECT b.brand_name,g.is_shipping, g.goods_sn, g.brand_id, g.goods_id, g.goods_name, g.user_id, g.goods_thumb,g.sales_volume, g.user_id, msi.self_run, g.model_attr, ' .
                "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, " .
                "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, " .
                "g.product_price, g.product_promote_price " .
                ' FROM ' . $GLOBALS['ecs']->table('goods') . " as g " .
                " left join " . $GLOBALS['ecs']->table('brand') . " as b" . " on g.brand_id = b.brand_id " .
                $leftJoin .
                'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
                "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
                " WHERE $where AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 group by g.goods_id  ORDER BY $sort $order";
        $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);

        $arr = array();
        while ($row = $GLOBALS['db']->fetchRow($res)) {

            if ($row['promote_price'] > 0) {
                $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
            } else {
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

            $arr[$row['goods_id']]['goods_id'] = $row['goods_id'];
            $arr[$row['goods_id']]['goods_sn'] = $row['goods_sn'];
            $arr[$row['goods_id']]['sales_volume'] = $row['sales_volume'];
            $arr[$row['goods_id']]['goods_name'] = $row['goods_name'];
            $arr[$row['goods_id']]['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ? sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
            $arr[$row['goods_id']]['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);

            $arr[$row['goods_id']]['shop_price'] = price_format($row['shop_price']);
            $arr[$row['goods_id']]['promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';

            $arr[$row['goods_id']]['brand_name'] = $row['brand_name'];
            $arr[$row['goods_id']]['url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
            $arr[$row['goods_id']]['brand_url'] = build_uri('brand', array('bid' => $row['brand_id']), $row['brand_name']);

            $basic_info = get_shop_info_content($row['user_id']);
            $arr[$row['goods_id']]['kf_type'] = $basic_info['kf_type'];

            /* 处理客服QQ数组 by kong */
            $arr[$row['goods_id']]['kf_qq'] = $basic_info['kf_qq'];

            /* 处理客服旺旺数组 by kong */
            $arr[$row['goods_id']]['kf_ww'] = $basic_info['kf_ww'];

            $goods_id = $row['goods_id'];
            $count = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('comment') . " where id_value ='$goods_id' AND status = 1 AND parent_id = 0");
            $arr[$row['goods_id']]['review_count'] = $count;

            $arr[$row['goods_id']]['rz_shopName'] = get_shop_name($row['user_id'], 1); //店铺名称
            $arr[$row['goods_id']]['user_id'] = $row['user_id'];
            $arr[$row['goods_id']]['is_shipping'] = $row['is_shipping'];
            $arr[$row['goods_id']]['self_run'] = $row['self_run'];

            $build_uri = array(
                'urid' => $row['user_id'],
                'append' => $arr[$row['goods_id']]['rz_shopName'],
            );

            $domain_url = get_seller_domain_url($row['user_id'], $build_uri);
            $arr[$row['goods_id']]['store_url'] = $domain_url['domain_name'];

            $mc_all = ments_count_all($row['goods_id']);       //总条数
            $mc_one = ments_count_rank_num($row['goods_id'], 1);  //一颗星
            $mc_two = ments_count_rank_num($row['goods_id'], 2);     //两颗星
            $mc_three = ments_count_rank_num($row['goods_id'], 3);    //三颗星
            $mc_four = ments_count_rank_num($row['goods_id'], 4);  //四颗星
            $mc_five = ments_count_rank_num($row['goods_id'], 5);  //五颗星
            $arr[$row['goods_id']]['zconments'] = get_conments_stars($mc_all, $mc_one, $mc_two, $mc_three, $mc_four, $mc_five);

            $arr[$row['goods_id']]['is_collect'] = get_collect_user_goods($row['goods_id']);

            $arr[$row['goods_id']]['pictures'] = get_goods_gallery($row['goods_id']); // 商品相册

            if ($GLOBALS['_CFG']['customer_service'] == 0) {
                $seller_id = 0;
            } else {
                $seller_id = $row['user_id'];
            }

            /*  @author-bylu 判断当前商家是否允许"在线客服" */
            $shop_information = get_shop_name($seller_id); //通过ru_id获取到店铺信息;
            $arr[$row['goods_id']]['is_IM'] = $shop_information['is_IM']; //平台是否允许商家使用"在线客服";
            if ($seller_id == 0) {
                //判断平台是否开启了IM在线客服
                if ($GLOBALS['db']->getOne("SELECT kf_im_switch FROM " . $GLOBALS['ecs']->table('seller_shopinfo') . "WHERE ru_id = 0", true)) {
                    $arr[$row['goods_id']]['is_dsc'] = true;
                } else {
                    $arr[$row['goods_id']]['is_dsc'] = false;
                }
            } else {
                $arr[$row['goods_id']]['is_dsc'] = false;
            }
        }
    }

    return $arr;
}

function cate_history_count()
{
    $str = '';
    if (!empty($_COOKIE['ECS']['list_history']))
    {
        $where = db_create_in($_COOKIE['ECS']['list_history'], 'g.goods_id');
        $sql   = 'SELECT b.brand_name, g.brand_id, g.goods_id, g.goods_name, g.goods_thumb, g.shop_price, g.promote_price, g.is_promote FROM ' . $GLOBALS['ecs']->table('goods') . " as g left join " . $GLOBALS['ecs']->table('brand') . " as b" . " on g.brand_id = b.brand_id " .
                " WHERE $where AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0";
        $res = count($GLOBALS['db']->getAll($sql));
	}

	return $res;
}
/* 记录浏览历史 ecmoban模板堂 --zhuo end 浏览列表插件*/

/**
 *
 * 退货原因列表
 * @staticvar null $res     by  Leah
 * @param type $cause_id     自增id
 * @param type $re_type      返回的类型: 值为真时返回下拉列表,否则返回数组
 * @param type $level        限定返回的级数。为0时返回所有级数
 * @param type $is_show_all  如果为true显示所有分类，如果为false隐藏不可见分类。
 * @return string
 */
function cause_list($cause_id = 0, $selected = 0, $re_type = true, $level = 0, $is_show_all = true)
{

    static $res = NULL;

    if ($res === NULL)
    {
       // $data = array();
        //$data = read_static_cache('cause_pid_releate');

            $sql = "SELECT c.cause_id, c.cause_name, c.sort_order, c.is_show ,c.parent_id , COUNT(s.cause_id) AS has_children ".
                'FROM ' . $GLOBALS['ecs']->table('return_cause') . " AS c ".
                "LEFT JOIN " . $GLOBALS['ecs']->table('return_cause') . " AS s ON s.parent_id=c.cause_id ".
                "GROUP BY c.cause_id ".
                'ORDER BY c.parent_id, c.sort_order ASC';
            $res = $GLOBALS['db']->getAll($sql);
            //如果数组过大，不采用静态缓存方式
            if (count($res) <= 1000)
            {
                write_static_cache('cause_pid_releate', $res);
            }


    }

    if (empty($res) == true)
    {
        return $re_type ? '' : array();
    }

    $options = cause_options($cause_id, $res); // 获得指定分类下的子分类的数组

    $children_level = 99999; //大于这个分类的将被删除
    if ($is_show_all == false)
    {
        foreach ($options as $key => $val)
        {
            if ($val['level'] > $children_level)
            {
                unset($options[$key]);
            }
            else
            {
                if ($val['is_show'] == 0)
                {
                    unset($options[$key]);
                    if ($children_level > $val['level'])
                    {
                        $children_level = $val['level']; //标记一下，这样子分类也能删除
                    }
                }
                else
                {
                    $children_level = 99999; //恢复初始值
                }
            }
        }
    }
    /* 截取到指定的缩减级别 */
    if ($level > 0)
    {
        if ($cause_id == 0)
        {
            $end_level = $level;
        }
        else
        {
            $first_item = reset($options); // 获取第一个元素
            $end_level  = $first_item['level'] + $level;
        }

        /* 保留level小于end_level的部分 */
        foreach ($options AS $key => $val)
        {
            if ($val['level'] >= $end_level)
            {
                unset($options[$key]);
            }
        }
    }

    if ($re_type == true)
    {
        $select = '';
        foreach ($options AS $var)
        {
            $select .= '<option value="' . $var['cause_id'] . '" ';
            $select .= ($selected == $var['cause_id']) ? "selected='ture'" : '';
            $select .= '>';
            if ($var['level'] > 0)
            {
                $select .= str_repeat('&nbsp;', $var['level'] * 4);
            }
            $select .= htmlspecialchars(addslashes($var['cause_name']), ENT_QUOTES) . '</option>';
        }

        return $select;
    }
    else
    {
        foreach ($options AS $key => $value)
        {
            $options[$key]['url'] = build_uri('reutrn_cause', array('cid' => $value['cause_id']), $value['cause_name']);
        }

        return $options;
    }
}

/**
 * 获取顶部退换货原因 by Leah
 */
function get_parent_cause(){

   $sql = "SELECT * FROM " .$GLOBALS['ecs']->table('return_cause') . " WHERE parent_id = 0  AND is_show = 1  ORDER BY sort_order";
   $result = $GLOBALS['db'] -> getAll( $sql );
   if(is_array($result)){

       $select = '';
        foreach ($result AS $var)
        {
            $select .= '<option value="' . $var['cause_id'] . '" ';
            $select .= ($selected == $var['cause_id']) ? "selected='ture'" : '';
            $select .= '>';
            if ($var['level'] > 0)
            {
                $select .= str_repeat('&nbsp;', $var['level'] * 4);
            }
            $select .= htmlspecialchars(addslashes($var['cause_name']), ENT_QUOTES) . '</option>';
        }

        return $select;
   }

   else {
       return array();
   }

}
/**
 * by Leah
 * @staticvar array $cat_options
 * @param type $spec_cat_id
 * @param type $arr
 * @return array
 */
function cause_options($spec_cat_id, $arr)
{
    static $cat_options = array();

    if (isset($cat_options[$spec_cat_id]))
    {
        return $cat_options[$spec_cat_id];
    }

    if (!isset($cat_options[0]))
    {
        $level = $last_cat_id = 0;
        $options = $cat_id_array = $level_array = array();
        //$data = read_static_cache('cause_option_static');
        //$data = array();
//        if ($data === false)
//        {
            while (!empty($arr))
            {

                foreach ($arr AS $key => $value)
                {
                    $cat_id = $value['cause_id'];
                    if ($level == 0 && $last_cat_id == 0)
                    {
                        if ($value['parent_id'] > 0)
                        {
                            break;
                        }

                        $options[$cat_id]          = $value;
                        $options[$cat_id]['level'] = $level;
                        $options[$cat_id]['id']    = $cat_id;
                        $options[$cat_id]['name']  = $value['cause_name'];
                        unset($arr[$key]);

                        if ($value['has_children'] == 0)
                        {
                            continue;
                        }
                        $last_cat_id  = $cat_id;
                        $cat_id_array = array($cat_id);
                        $level_array[$last_cat_id] = ++$level;
                        continue;
                    }

                    if ($value['parent_id'] == $last_cat_id)
                    {
                        $options[$cat_id]          = $value;
                        $options[$cat_id]['level'] = $level;
                        $options[$cat_id]['id']    = $cat_id;
                        $options[$cat_id]['name']  = $value['cause_name'];
                        unset($arr[$key]);

                        if ($value['has_children'] > 0)
                        {
                            if (end($cat_id_array) != $last_cat_id)
                            {
                                $cat_id_array[] = $last_cat_id;
                            }
                            $last_cat_id    = $cat_id;
                            $cat_id_array[] = $cat_id;
                            $level_array[$last_cat_id] = ++$level;
                        }
                    }
                    elseif ($value['parent_id'] > $last_cat_id)
                    {
                        break;
                    }
                }

                $count = count($cat_id_array);
                if ($count > 1)
                {
                    $last_cat_id = array_pop($cat_id_array);
                }
                elseif ($count == 1)
                {
                    if ($last_cat_id != end($cat_id_array))
                    {
                        $last_cat_id = end($cat_id_array);
                    }
                    else
                    {
                        $level = 0;
                        $last_cat_id = 0;
                        $cat_id_array = array();
                        continue;
                    }
                }

                if ($last_cat_id && isset($level_array[$last_cat_id]))
                {
                    $level = $level_array[$last_cat_id];
                }
                else
                {
                    $level = 0;
                }
            }
            //如果数组过大，不采用静态缓存方式
            if (count($options) <= 2000)
            {
               // write_static_cache('cause_option_static', $options);
            }
//        }
//        else
//        {
//            $options = $data;
//        }
        $cat_options[0] = $options;
    }
    else
    {
        $options = $cat_options[0];
    }

    if (!$spec_cat_id)
    {
        return $options;
    }
    else
    {
        if (empty($options[$spec_cat_id]))
        {
            return array();
        }

        $spec_cat_id_level = $options[$spec_cat_id]['level'];

        foreach ($options AS $key => $value)
        {
            if ($key != $spec_cat_id)
            {
                unset($options[$key]);
            }
            else
            {
                break;
            }
        }

        $spec_cat_id_array = array();
        foreach ($options AS $key => $value)
        {
            if (($spec_cat_id_level == $value['level'] && $value['cause_id'] != $spec_cat_id) ||
                ($spec_cat_id_level > $value['level']))
            {
                break;
            }
            else
            {
                $spec_cat_id_array[$key] = $value;
            }
        }
        $cat_options[$spec_cat_id] = $spec_cat_id_array;

        return $spec_cat_id_array;
    }
}
/**
 * 记录订单操作记录 by　　Leah
 *
 * @access  public
 * @param   string  $order_sn           订单编号
 * @param   integer $order_status       订单状态
 * @param   integer $shipping_status    配送状态
 * @param   integer $pay_status         付款状态
 * @param   string  $note               备注
 * @param   string  $username           用户名，用户自己的操作则为 buyer
 * @return  void
 */
function return_action($ret_id, $return_status, $refound_status, $note = '', $username = null, $place = 0)
{
    if (is_null($username))
    {
        $username = get_admin_name();
    }

    $sql = 'INSERT INTO ' . $GLOBALS['ecs']->table('return_action') .
                ' (ret_id, action_user, return_status, refound_status, action_place, action_note, log_time) ' .
            'SELECT ' .
                "ret_id, '$username', '$return_status', '$refound_status', '$place', '$note', '" .gmtime() . "' " .
            'FROM ' . $GLOBALS['ecs']->table('order_return') . " WHERE ret_id = '$ret_id'";
    $GLOBALS['db']->query($sql);
}


/**
 * 取出单个晒单图片
 *
 * @param $goods_id int
 * @param $order_id int
 *
 * return $single array()
 *
 * @author guan
 */

function get_single($goods_id, $order_id)
{
	$sql = "SELECT * FROM " . $GLOBALS['ecs']->table('single') . "WHERE goods_id='$goods_id' AND order_id='$order_id' AND is_audit=1";
	$singles = $GLOBALS['db']->getRow($sql);
	$imaegs = array();
	foreach($singles as $k => $v)
	{
		$sql = "SELECT * FROM " . $GLOBALS['ecs']->table('goods_gallery') . " WHERE single_id='$singles[single_id]'";
		$images = $GLOBALS['db']->getAll($sql);
	}

	return $images;
}

/**
 * 取出单个晒单信息
 *
 * @param int $goods_id
 * @param int $order_id
 * @return array()
 */
function get_single_detaile($goods_id, $order_id=0)
{
	if(empty($order_id))
	{
		$order_where = '';
	}
	else
	{
		$order_where = " AND order_id='$order_id' ";
	}
	$sql = "SELECT * FROM " . $GLOBALS['ecs']->table('single') . "WHERE goods_id='$goods_id'$order_where AND is_audit=1 ORDER BY addtime";
	$singles = $GLOBALS['db']->getRow($sql);

	$sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('comment') . " WHERE single_id='$singles[single_id]' ORDER BY add_time";
	$singles['comment_nums'] = $GLOBALS['db']->getOne($sql);
	$singles['addtime'] = local_date('Y-m-d H:i:s', $singles['addtime']);

	return $singles;
}

/**
 * 对二维数组排序
 *
 * @param array(array()) $arr
 * @param key $keys
 * @param ASC | DESC  $type
 * @return $new_array array(array())
 *
 * @author guan
 */
function dimensional_array_sort($arr,$keys,$type='DESC'){
	$keysvalue = $new_array = array();
	foreach ($arr as $k=>$v){
		$keysvalue[$k] = $v[$keys];
	}
	if($type == 'ASC'){
		asort($keysvalue);
	}else{
		arsort($keysvalue);
	}
	reset($keysvalue);
	foreach ($keysvalue as $k=>$v){
		$new_array[$k] = $arr[$k];
	}
	return $new_array;
}

//店铺搜索 start
function get_store_shop_list($libType = 0, $keywords = '', $count = 0, $size = 16, $page = 1, $sort = 'shop_id', $order = 'DESC', $warehouse_id = 0, $area_id = 0, $store_province = 0, $store_city = 0, $store_district = 0, $store_user = '') {
    require_once('includes/cls_pager.php');

    $id = '"';
    if ($keywords) {
        $id .= "keywords-" . $keywords . "|";
    }

    if ($warehouse_id) {
        $id .= "warehouse_id-" . $warehouse_id . "|";
    }

    if ($area_id) {
        $id .= "area_id-" . $area_id . "|";
    }

    if ($store_province) {
        $id .= "store_province-" . $store_province . "|";
    }

    if ($store_city) {
        $id .= "store_city-" . $store_city . "|";
    }

    if ($store_district) {
        $id .= "store_district-" . $store_district . "|";
    }

    if ($sort) {
        $id .= "sort-" . $sort . "|";
    }

    if ($order) {
        $id .= "order-" . $order . "|";
    }

    if ($store_user) {
        $id .= "store_user-" . $store_user . "|";
    }

    $substr = substr($id, -1);
    if ($substr == "|") {
        $id = substr($id, 0, -1);
    }

    $id .= '"';

    $store_shop = new Pager($count, $size, '', $id, 0, $page, 'store_shop_gotoPage', 1, $libType);
    $limit = $store_shop->limit;
    $pager = $store_shop->fpage(array(0, 4, 5, 6, 9));

    $whereShop = " 1 ";
    $where = '1';
    $keywords = mysql_like_quote(trim($keywords));
    if (!empty($keywords)) {
        $where .= " AND (shoprz_brandName LIKE '%$keywords%' OR shopNameSuffix LIKE '%$keywords%' OR rz_shopName LIKE '%$keywords%') ";

        $user_list = '';
        $sql = "SELECT user_id FROM " . $GLOBALS['ecs']->table('merchants_shop_information') . " where " . $where;

        $shop_list = $GLOBALS['db']->getAll($sql);

        if ($shop_list) {
            foreach ($shop_list as $key => $row) {
                $user_list .= $row['user_id'] . ",";
            }
            $user_list = substr($user_list, 0, -1);
        } else {

            $reviewGodds = '';
            if ($GLOBALS['_CFG']['review_goods'] == 1) {
                $reviewGodds = ' AND review_status > 2 ';
            }

            $sql = "SELECT user_id FROM " . $GLOBALS['ecs']->table('goods') . " where goods_name LIKE '%$keywords%' $reviewGodds group by goods_id";
            $goodsUlist = $GLOBALS['db']->getAll($sql);

            if ($goodsUlist) {
                foreach ($goodsUlist as $key => $row) {
                    $user_list .= $row['user_id'] . ",";
                }
                $user_list = substr($user_list, 0, -1);
            }
        }

        if (!empty($user_list)) {
            $whereShop .= " AND msi.user_id in(" . $user_list . ")";
        } else {
            $whereShop .= " AND msi.user_id in(0)";
        }
    } else {
        if ($store_user) {
            $whereShop .= " AND msi.user_id in(" . $store_user . ")";
        }
    }

    $where_table = '';
    $select = '';

    if ($sort == 'sales_volume') {
        $select .= ", (SELECT SUM(og.goods_number) FROM " . $GLOBALS['ecs']->table('order_info') . " AS oi, " . $GLOBALS['ecs']->table('order_goods') . " AS og " .
                " WHERE oi.order_id = og.order_id AND og.ru_id = msi.user_id " .
                " AND (oi.order_status = '" . OS_CONFIRMED . "' OR  oi.order_status = '" . OS_SPLITED . "' OR oi.order_status = '" . OS_SPLITING_PART . "') " .
                " AND (oi.pay_status  = '" . PS_PAYING . "' OR  oi.pay_status  = '" . PS_PAYED . "')) AS sales_volume ";
    } elseif ($sort == 'goods_number') {
        $select .= ", ((SELECT SUM(g.goods_number) FROM " . $GLOBALS['ecs']->table('goods') . " AS g " .
                " WHERE g.user_id = msi.user_id AND g.review_status > 2)) AS goods_number ";
    }

    if ($store_province > 0 || $store_city > 0 || $store_district > 0) {
        $where_table .= ", " . $GLOBALS['ecs']->table('seller_shopinfo') . " AS ssfo ";
        $whereShop .= "AND msi.user_id = ssfo.ru_id ";
    }

    if ($store_province > 0) {
        $whereShop .= "AND ssfo.province = '$store_province' ";
    }

    if ($store_city > 0) {
        $whereShop .= "AND ssfo.city = '$store_city' ";
    }

    if ($store_district > 0) {
        $whereShop .= "AND ssfo.district = '$store_district' ";
    }

    if ($libType == 0) {
        $whereShop .= "AND msi.is_street = 1 ";
    }
    $sql = "SELECT msi.shop_id, msi.user_id, msi.shoprz_brandName, msi.shopNameSuffix, msi.self_run $select FROM " .
            $GLOBALS['ecs']->table('merchants_shop_information') . " as msi " . $where_table . " where $whereShop" .
            " AND msi.merchants_audit = 1 ORDER BY $sort $order " . $limit;

    $res = $GLOBALS['db']->query($sql);

    $arr = array();
    while ($row = $GLOBALS['db']->fetchRow($res)) {
        //判断店铺是否关闭
        $sql = "SELECT status FROM".$GLOBALS['ecs']->table('seller_shopinfo')."WHERE ru_id = '".$row['user_id']."' LIMIT 1";
        $shop_status = $GLOBALS['db']->getOne($sql);
        //关闭则跳跳过
        if($shop_status == 0){
            continue;
        }
        $arr[$row['shop_id']]['shop_id'] = $row['shop_id'];
        $arr[$row['shop_id']]['shoprz_brandName'] = $row['shoprz_brandName'];
        $arr[$row['shop_id']]['shopNameSuffix'] = $row['shopNameSuffix'];
		$arr[$row['shop_id']]['self_run'] = $row['self_run'];
        $arr[$row['shop_id']]['shop_name'] = get_shop_name($row['user_id'], 3); //店铺名称
        $arr[$row['shop_id']]['shopName'] = get_shop_name($row['user_id'], 1); //店铺名称
        $arr[$row['shop_id']]['brand_list'] = get_shop_brand_list($row['user_id']); //商家品牌
        $arr[$row['shop_id']]['address'] = get_shop_address_info($row['user_id']); //商家所在位置
        $arr[$row['shop_id']]['sales_volume'] = !empty($row['sales_volume']) ? $row['sales_volume'] : 0;
        $grade_info = get_seller_grade($row['user_id']);
        $arr[$row['shop_id']]['grade_img'] = $grade_info['grade_img'];
        $arr[$row['shop_id']]['grade_name'] = $grade_info['grade_name'];

        $shop_info = get_shop_info_content($row['user_id']);
        $arr[$row['shop_id']]['shop_logo'] = str_replace('../', '', $shop_info['shop_logo']); //商家logo
        $arr[$row['shop_id']]['logo_thumb'] = str_replace('../', '', $shop_info['logo_thumb']); //商家缩略图
        $arr[$row['shop_id']]['street_thumb'] = $shop_info['street_thumb']; //店铺街封面图
        $arr[$row['shop_id']]['brand_thumb'] = $shop_info['brand_thumb']; //店铺街品牌图
        //OSS文件存储ecmoban模板堂 --zhuo start
        if ($GLOBALS['_CFG']['open_oss'] == 1) {
            $bucket_info = get_bucket_info();
            $arr[$row['shop_id']]['shop_logo'] = $bucket_info['endpoint'] . $arr[$row['shop_id']]['shop_logo']; //商家logo
            $arr[$row['shop_id']]['logo_thumb'] = $bucket_info['endpoint'] . $arr[$row['shop_id']]['logo_thumb']; //商家缩略图
            $arr[$row['shop_id']]['street_thumb'] = $bucket_info['endpoint'] . $arr[$row['shop_id']]['street_thumb']; //店铺街封面图
            $arr[$row['shop_id']]['brand_thumb'] = $bucket_info['endpoint'] . $arr[$row['shop_id']]['brand_thumb']; //店铺街品牌图
        }
        //OSS文件存储ecmoban模板堂 --zhuo end

        $arr[$row['shop_id']]['street_desc'] = $shop_info['street_desc']; //店铺街描述
        $arr[$row['shop_id']]['merch_cmt'] = get_merchants_goods_comment($row['user_id']); //商家总体评分
        $arr[$row['shop_id']]['shopNameSuffix'] = $row['shopNameSuffix'];
        $arr[$row['shop_id']]['ru_id'] = $row['user_id'];

        $build_uri = array(
            'urid' => $row['user_id'],
            'append' => $arr[$row['shop_id']]['shop_name']
        );

        $domain_url = get_seller_domain_url($row['user_id'], $build_uri);
        $arr[$row['shop_id']]['shop_url'] = $domain_url['domain_name'];

        $arr[$row['shop_id']]['store_shop_url'] = build_uri('merchants_store_shop', array('urid' => $row['user_id']), $arr[$row['shop_id']]['shop_name']);

        $arr[$row['shop_id']]['goods_count'] = get_shop_goods_count_list($row['user_id'], $warehouse_id, $area_id); //商品数量
        $arr[$row['shop_id']]['goods_list'] = get_shop_goods_count_list($row['user_id'], $warehouse_id, $area_id, 1); //商品数量
        /*获取是否关注*/
        $arr[$row['shop_id']]['collect_store'] = 0;
        if($_SESSION['user_id'] > 0){
            $sql = "SELECT rec_id FROM " . $GLOBALS['ecs']->table('collect_store') . " WHERE user_id = '" . $_SESSION['user_id'] . "' AND ru_id = '" . $row['user_id'] . "' ";
            $arr[$row['shop_id']]['collect_store'] = $GLOBALS['db']->getOne($sql);
        }

		/*处理客服相关代码 start*/
        $sql="select * from ".$GLOBALS['ecs']->table('seller_shopinfo')." where ru_id='" .$row['user_id']. "'";
        $basic_info = $GLOBALS['db']->getRow($sql);
        $arr[$row['shop_id']]['kf_type'] = $basic_info['kf_type'];

        /*处理客服旺旺数组 by kong*/
        if($basic_info['kf_ww']){
            $kf_ww=array_filter(preg_split('/\s+/', $basic_info['kf_ww']));
            $kf_ww=explode("|",$kf_ww[0]);
            if(!empty($kf_ww[1])){
                $arr[$row['shop_id']]['kf_ww'] = $kf_ww[1];
            }else{
                $arr[$row['shop_id']]['kf_ww'] ="";
            }

        }else{
            $arr[$row['shop_id']]['kf_ww'] ="";
        }
        /*处理客服QQ数组 by kong*/
        if($basic_info['kf_qq']){
            $kf_qq=array_filter(preg_split('/\s+/', $basic_info['kf_qq']));
            $kf_qq=explode("|",$kf_qq[0]);
            if(!empty($kf_qq[1])){
                $arr[$row['shop_id']]['kf_qq'] = $kf_qq[1];
            }else{
                $arr[$row['shop_id']]['kf_qq'] = "";
            }

        }else{
            $arr[$row['shop_id']]['kf_qq'] = "";
        }

        /*  @author-bylu 判断当前商家是否允许"在线客服" start  */
        $shop_information = get_shop_name($row['user_id']); //通过ru_id获取到店铺信息;
        $arr[$row['shop_id']]['is_IM'] = $shop_information['is_IM']; //平台是否允许商家使用"在线客服";
        //判断当前商家是平台,还是入驻商家 bylu
        if ($row['user_id'] == 0) {
            //判断平台是否开启了IM在线客服
            if ($GLOBALS['db']->getOne("SELECT kf_im_switch FROM " . $GLOBALS['ecs']->table('seller_shopinfo') . "WHERE ru_id = 0", true)) {
                $arr[$row['shop_id']]['is_dsc'] = true;
            } else {
                $arr[$row['shop_id']]['is_dsc'] = false;
            }
        } else {
            $arr[$row['shop_id']]['is_dsc'] = false;
        }
        /*  @author-bylu  end  */
		/*处理客服相关代码 end*/
    }

    $result = array('shop_list' => $arr, 'pager' => $pager);
    return $result;
}

//店铺搜索数量
function get_store_shop_count($keywords = '', $sort = 'shop_id', $store_province = 0, $store_city = 0, $store_district = 0, $store_user = '', $libType = 0) {

    $whereShop = " 1 ";
    $where = '1';
    $keywords = mysql_like_quote(trim($keywords));
    if (!empty($keywords)) {
        $where .= " AND (shoprz_brandName LIKE '%$keywords%' OR shopNameSuffix LIKE '%$keywords%' OR rz_shopName LIKE '%$keywords%') ";
        $user_list = '';
        $sql = "SELECT user_id FROM " . $GLOBALS['ecs']->table('merchants_shop_information') . " where " . $where;
        $shop_list = $GLOBALS['db']->getAll($sql);

        if ($shop_list) {
            foreach ($shop_list as $key => $row) {
                $user_list .= $row['user_id'] . ",";
            }
            $user_list = substr($user_list, 0, -1);
        } else {

            $reviewGodds = '';
            if ($GLOBALS['_CFG']['review_goods'] == 1) {
                $reviewGodds = ' AND review_status > 2 ';
            }

            $sql = "SELECT user_id FROM " . $GLOBALS['ecs']->table('goods') . " where goods_name LIKE '%$keywords%' $reviewGodds group by goods_id";
            $goodsUlist = $GLOBALS['db']->getAll($sql);

            if ($goodsUlist) {
                foreach ($goodsUlist as $key => $row) {
                    $user_list .= $row['user_id'] . ",";
                }
                $user_list = substr($user_list, 0, -1);
            }
        }

        if (!empty($user_list)) {
            $whereShop .= " AND msi.user_id in(" . $user_list . ")";
        } else {
            $whereShop .= " AND msi.user_id in(0)";
        }
    } else {
        if ($store_user) {
            $whereShop .= " AND msi.user_id in(" . $store_user . ")";
        }
    }

    $where_table = '';
    $select = '';
    if ($sort == 'sales_volume') {

        $no_main_order = " and (select count(*) from " .$GLOBALS['ecs']->table('order_info'). " as oi2 where oi2.main_order_id = oi.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示

        $select .= ", (SELECT SUM(og.goods_number) FROM " . $GLOBALS['ecs']->table('order_info') . " AS oi, " . $GLOBALS['ecs']->table('order_goods') . " AS og " .
                " WHERE oi.order_id = og.order_id AND og.ru_id = msi.user_id " .
                " AND (oi.order_status = '" . OS_CONFIRMED . "' OR  oi.order_status = '" . OS_SPLITED . "' OR oi.order_status = '" . OS_SPLITING_PART . "') " .
                " AND (oi.pay_status  = '" . PS_PAYING . "' OR  oi.pay_status  = '" . PS_PAYED . "') " .$no_main_order. ") AS sales_volume ";
    } elseif ($sort == 'goods_number') {
        $select .= ", ((SELECT SUM(g.goods_number) FROM " . $GLOBALS['ecs']->table('goods') . " AS g " .
                " WHERE g.user_id = msi.user_id AND g.review_status > 2)) AS goods_number ";
    }

    if ($store_province > 0 || $store_city > 0 || $store_district > 0) {
        $where_table .= ", " . $GLOBALS['ecs']->table('seller_shopinfo') . " AS ssfo ";
        $whereShop .= "AND msi.user_id = ssfo.ru_id ";
    }

    if ($store_province > 0) {
        $whereShop .= "AND ssfo.province = '$store_province' ";
    }

    if ($store_city > 0) {
        $whereShop .= "AND ssfo.city = '$store_city' ";
    }

    if ($store_district > 0) {
        $whereShop .= "AND ssfo.district = '$store_district' ";
    }

	if($libType == 0){
		$whereShop .= " AND msi.is_street = 1 ";
	}

    $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('merchants_shop_information') . " AS msi" . $where_table . " where $whereShop " .
            " AND msi.merchants_audit = 1 ";

    return $GLOBALS['db']->getOne($sql);
}

function get_store_shop_goods_list($keywords = '', $size, $page, $sort, $order, $warehouse_id, $area_id) {

    $whereGodds = "1";
    $where = '1';
    $keywords = mysql_like_quote(trim($keywords));
    if (!empty($keywords)) {
        $where .= " AND (shoprz_brandName LIKE '%$keywords%' OR shopNameSuffix LIKE '%$keywords%') ";

        $user_list = '';
        $sql = "SELECT user_id FROM " . $GLOBALS['ecs']->table('merchants_shop_information') . " where " . $where;
        $shop_list = $GLOBALS['db']->getAll($sql);

        if ($shop_list) {
            foreach ($shop_list as $key => $row) {
                $user_list .= $row['user_id'] . ",";
            }
            $user_list = substr($user_list, 0, -1);
        } else {

            $reviewGodds = '';
            if ($GLOBALS['_CFG']['review_goods'] == 1) {
                $reviewGodds = ' AND review_status > 2 ';
            }

            $sql = "SELECT user_id FROM " . $GLOBALS['ecs']->table('goods') . " where goods_name LIKE '%$keywords%' $reviewGodds group by goods_id";
            $goodsUlist = $GLOBALS['db']->getAll($sql);

            if ($goodsUlist) {
                foreach ($goodsUlist as $key => $row) {
                    if ($row['user_id'] > 0) {
                        $user_list .= $row['user_id'] . ",";
                    }
                }
            }
        }

        if (!empty($user_list)) {
            if (strpos($user_list, ",") !== FALSE) {
                $user_list = substr($user_list, 0, -1);
            }

            $whereGodds .= " AND g.user_id in(" . $user_list . ")";
        }
    } else {
        $whereGodds .= " AND g.user_id > 0 ";
    }

    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
	$leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_shop_information') . " as msi on msi.user_id = g.user_id ";

    if ($GLOBALS['_CFG']['open_area_goods'] == 1) {
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('link_area_goods') . " as lag on g.goods_id = lag.goods_id ";
        $whereGodds .= " AND lag.region_id = '$area_id' ";
    }

    $whereGodds .= " AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 ";

    if ($GLOBALS['_CFG']['review_goods'] == 1) {
        $whereGodds .= ' AND g.review_status > 2 ';
    }

    if($sort == 'shop_price'){
        $sort = "g.shop_price";
    }elseif($sort == 'last_update'){
        $sort = "g.last_update";
    }

    $select = "g.goods_id, g.sales_volume, g.goods_thumb,g.is_shipping, g.goods_name, g.user_id, g.promote_start_date, g.promote_end_date, g.market_price, msi.self_run, ";
    $select .= "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, ";
    $select .= "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price";

    $leftJoin .= 'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' ";

    $sql = "SELECT " . $select . " FROM " . $GLOBALS['ecs']->table('goods') . " as g " .
            $leftJoin .
            " where $whereGodds ORDER BY $sort $order";

    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);

    while ($row = $GLOBALS['db']->fetchRow($res)) {

        if ($row['promote_price'] > 0) {
            $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
        } else {
            $promote_price = 0;
        }

        $arr[$row['goods_id']]['market_price'] = price_format($row['market_price']);
        $arr[$row['goods_id']]['shop_price'] = price_format($row['shop_price']);
        $arr[$row['goods_id']]['promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';
		$arr[$row['goods_id']]['sales_volume'] = $row['sales_volume'];
        $arr[$row['goods_id']]['goods_id'] = $row['goods_id'];
        $arr[$row['goods_id']]['goods_name'] = $row['goods_name'];
        $arr[$row['goods_id']]['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
        $arr[$row['goods_id']]['goods_url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
        $arr[$row['goods_id']]['is_shipping'] = $row['is_shipping'];
		$arr[$row['goods_id']]['self_run'] = $row['self_run'];

        $sql="select * from ".$GLOBALS['ecs']->table('seller_shopinfo')." where ru_id='" .$row['user_id']. "'";
        $basic_info = $GLOBALS['db']->getRow($sql);
        $arr[$row['goods_id']]['kf_type'] = $basic_info['kf_type'];

        /* 处理客服QQ数组 by kong */
        if ($basic_info['kf_qq']) {
            $kf_qq = array_filter(preg_split('/\s+/', $basic_info['kf_qq']));
            $kf_qq = explode("|", $kf_qq[0]);
            if (!empty($kf_qq[1])) {
                $arr[$row['goods_id']]['kf_qq'] = $kf_qq[1];
            } else {
                $arr[$row['goods_id']]['kf_qq'] = "";
            }
        } else {
            $arr[$row['goods_id']]['kf_qq'] = "";
        }
        /* 处理客服旺旺数组 by kong */
        if ($basic_info['kf_ww']) {
            $kf_ww = array_filter(preg_split('/\s+/', $basic_info['kf_ww']));
            $kf_ww = explode("|", $kf_ww[0]);
            if (!empty($kf_ww[1])) {
                $arr[$row['goods_id']]['kf_ww'] = $kf_ww[1];
            } else {
                $arr[$row['goods_id']]['kf_ww'] = "";
            }
        } else {
            $arr[$row['goods_id']]['kf_ww'] = "";
        }

        $arr[$row['goods_id']]['shop_name'] = get_shop_name($row['user_id'], 1); //店铺名称

        $build_uri = array(
            'urid' => $row['user_id'],
            'append' => $arr[$key]['shop_name']
        );

        $domain_url = get_seller_domain_url($row['user_id'], $build_uri);
        $arr[$row['goods_id']]['shop_url'] = $domain_url['domain_name'];

        $cmt_count = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('comment') . " where id_value ='" . $row['goods_id'] . "' AND status = 1 AND parent_id = 0");
        $arr[$row['goods_id']]['cmt_count'] = $cmt_count;
        $arr[$row['goods_id']]['brand_list'] = get_shop_brand_list($row['user_id']); //商家品牌

        $arr[$row['goods_id']]['is_collect'] = get_collect_user_goods($row['goods_id']);

        $arr[$row['goods_id']]['pictures'] = get_goods_gallery($row['goods_id'], 6);// 商品相册

        $shop_information = get_shop_name($row['user_id']); //通过ru_id获取到店铺信息;
        $arr[$row['goods_id']]['is_IM'] = $shop_information['is_IM']; //平台是否允许商家使用"在线客服";

        //判断当前商家是平台,还是入驻商家 bylu
        if ($row['user_id'] == 0) {
            //判断平台是否开启了IM在线客服
            if ($GLOBALS['db']->getOne("SELECT kf_im_switch FROM " . $GLOBALS['ecs']->table('seller_shopinfo') . "WHERE ru_id = 0")) {
                $arr[$row['goods_id']]['is_dsc'] = true;
            } else {
                $arr[$row['goods_id']]['is_dsc'] = false;
            }
        } else {
            $arr[$row['goods_id']]['is_dsc'] = false;
        }
    }

    return $arr;
}

function get_store_shop_goods_count($keywords, $sort) {

    $whereGodds = "1";
    $where = '1';

    $keywords = mysql_like_quote(trim($keywords));
    if (!empty($keywords)) {
        $where .= " AND (shoprz_brandName LIKE '%$keywords%' OR shopNameSuffix LIKE '%$keywords%') ";

        $user_list = '';
        $sql = "SELECT user_id FROM " . $GLOBALS['ecs']->table('merchants_shop_information') . " where " . $where;
        $shop_list = $GLOBALS['db']->getAll($sql);

        if ($shop_list) {
            foreach ($shop_list as $key => $row) {
                $user_list .= $row['user_id'] . ",";
            }
            $user_list = substr($user_list, 0, -1);
        } else {

            $reviewGodds = '';
            if ($GLOBALS['_CFG']['review_goods'] == 1) {
                $reviewGodds = ' AND review_status > 2 ';
            }

            $sql = "SELECT user_id FROM " . $GLOBALS['ecs']->table('goods') . " where goods_name LIKE '%$keywords%' $reviewGodds group by goods_id";
            $goodsUlist = $GLOBALS['db']->getAll($sql);

            if ($goodsUlist) {
                foreach ($goodsUlist as $key => $row) {
                    if ($row['user_id'] > 0) {
                        $user_list .= $row['user_id'] . ",";
                    }
                }
            }
        }

        if (!empty($user_list)) {
            if (strpos($user_list, ",") !== FALSE) {
                $user_list = substr($user_list, 0, -1);
            }
            $whereGodds .= " AND g.user_id in(" . $user_list . ")";
        }
    } else {
        $whereGodds .= " AND g.user_id > 0 ";
    }

    if ($GLOBALS['_CFG']['review_goods'] == 1) {
        $whereGodds .= ' AND g.review_status > 2 ';
    }

    $sql = "SELECT count(*) FROM " . $GLOBALS['ecs']->table('goods') . " as g " .
            " where $whereGodds AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0";
    $res = $GLOBALS['db']->getOne($sql);

    return $res;
}

//店铺品牌列表
function get_shop_brand_list($user_id = 0) {
    $sql = "SELECT bid as brand_id, brandName as brand_name FROM " . $GLOBALS['ecs']->table('merchants_shop_brand') . " WHERE user_id = '$user_id' AND audit_status = 1 ORDER BY bid ASC";
    return $res = $GLOBALS['db']->getAll($sql);
}

//商家所在位置
function get_shop_address_info($user_id = 0){
        $res = get_shop_info_content($user_id);
        $province = get_shop_address($res['province']);
        $city = get_shop_address($res['city']);
        $region = $province  . str_repeat("&nbsp;", 2) . $city;

	return $region;
}

function get_shop_address($region, $type = 0){

    if($type == 1){
            $region = str_replace(array('省', '市'), '', $region);
            $select = "region_id";
            $where = "region_name = '$region'";
    }else{
            $select = "region_name";
            $where = "region_id = '$region'";
    }

    $sql = "SELECT " .$select. " FROM " . $GLOBALS['ecs']->table('region') . " where " . $where;
    return $GLOBALS['db']->getOne($sql);
}

//店铺信息
function get_shop_info_content($user_id = 0) {
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('seller_shopinfo') . " where ru_id = '$user_id' LIMIT 1";
    $basic_info = $GLOBALS['db']->getRow($sql);
    if ($basic_info['kf_type']) {
        /* 处理客服旺旺数组 by kong */
        if ($basic_info['kf_ww']) {
            $kf_ww = array_filter(preg_split('/\s+/', $basic_info['kf_ww']));
            foreach ($kf_ww as $k => $v) {
                $basic_info['kf_ww_all'][] = explode("|", $v);
            }

            $kf_ww = explode("|", $kf_ww[0]);
            if (!empty($kf_ww[1])) {
                $basic_info['kf_ww'] = $kf_ww[1];
            } else {
                $basic_info['kf_ww'] = "";
            }
        } else {
            $basic_info['kf_ww'] = "";
        }
    } else {
        /* 处理客服QQ数组 by kong */
        if ($basic_info['kf_qq']) {
            $kf_qq = array_filter(preg_split('/\s+/', $basic_info['kf_qq']));
            foreach ($kf_qq as $k => $v) {
                $basic_info['kf_qq_all'][] = explode("|", $v);
            }

            $kf_qq = explode("|", $kf_qq[0]);
            if (!empty($kf_qq[1])) {
                $basic_info['kf_qq'] = $kf_qq[1];
            } else {
                $basic_info['kf_qq'] = "";
            }
        } else {
            $basic_info['kf_qq'] = "";
        }
    }

    return $basic_info;
}

//商家商品数量
function get_shop_goods_count_list($user_id, $warehouse_id, $area_id, $type = 0, $isType = '', $show_type = 0) {

    $leftJoin = '';
    $where = "1";

    if ($GLOBALS['_CFG']['review_goods'] == 1) {
        $where .= ' AND g.review_status > 2 ';
    }
    if ($type == 1) {
        $arr = array();

        $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

        if ($GLOBALS['_CFG']['open_area_goods'] == 1) {
            $leftJoin .= " left join " . $GLOBALS['ecs']->table('link_area_goods') . " as lag on g.goods_id = lag.goods_id ";
            $where .= " AND lag.region_id = '$area_id' ";
        }

        $leftJoin .= 'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
                "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' ";

        $select = "g.goods_id, g.goods_thumb, g.goods_name, g.user_id, g.promote_start_date, g.promote_end_date, g.market_price, g.sales_volume, g.model_attr, ";
        $select .= "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, ";
        $select .= "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, ";
        $select .= "g.product_price, g.product_promote_price ";
    } else {
        $select = "count(*)";
    }

    if ($isType == 'store_best') {
        $where .= ' AND g.store_best = 1';
        $where .= " and g.user_id > $user_id ";
    } else {
        $where .= " and g.user_id = '$user_id' ";
    }

    $where .= " AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 ";

    if ($type == 1) {
        if ($show_type == 1) {
            $limit = "limit 0,6";
        } else {
            $limit = "limit 0,5";
        }
        $where .= ' order by g.sort_order ASC ' . $limit;
    }

    $sql = "SELECT " . $select . " FROM " . $GLOBALS['ecs']->table('goods') . " as g " . $leftJoin . " WHERE $where ";

    if ($type == 1) {
        $res = $GLOBALS['db']->getAll($sql);

        foreach ($res as $key => $row) {

            if ($row['promote_price'] > 0) {
                $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
            } else {
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

            $arr[$key]['market_price'] = price_format($row['market_price']);
            $arr[$key]['shop_price'] = price_format($row['shop_price']);
            $arr[$key]['promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';

            $arr[$key]['goods_id'] = $row['goods_id'];
            $arr[$key]['goods_name'] = $row['goods_name'];
            $arr[$key]['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
            $arr[$key]['goods_url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
            $arr[$key]['sales_volume'] = $row['sales_volume']; //销量

            $basic_info = get_shop_info_content($row['user_id']);
            $arr[$key]['kf_type'] = $basic_info['kf_type'];

            /* 处理客服QQ数组 by kong */
            if ($basic_info['kf_qq']) {
                $kf_qq = array_filter(preg_split('/\s+/', $basic_info['kf_qq']));
                $kf_qq = explode("|", $kf_qq[0]);
                if (!empty($kf_qq[1])) {
                    $arr[$key]['kf_qq'] = $kf_qq[1];
                } else {
                    $arr[$key]['kf_qq'] = '';
                }
            } else {
                $arr[$key]['kf_qq'] = "";
            }
            /* 处理客服旺旺数组 by kong */
            if ($basic_info['kf_ww']) {
                $kf_ww = array_filter(preg_split('/\s+/', $basic_info['kf_ww']));
                $kf_ww = explode("|", $kf_ww[0]);
                if (!empty($kf_ww[1])) {
                    $arr[$key]['kf_ww'] = $kf_ww[1];
                } else {
                    $arr[$key]['kf_ww'] = "";
                }
            } else {
                $arr[$key]['kf_ww'] = "";
            }

            $arr[$key]['shop_name'] = get_shop_name($row['user_id'], 1); //店铺名称
            $arr[$key]['shop_url'] = build_uri('merchants_store', array('cid' => 0, 'urid' => $row['user_id']), $arr[$key]['shop_name']);

            $cmt_count = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('comment') . " where id_value ='" . $row['goods_id'] . "' AND status = 1 AND parent_id = 0");
            $arr[$key]['cmt_count'] = $cmt_count;
        }

        return $arr;
    } else {
        return $GLOBALS['db']->getOne($sql);
    }
}

//商家商品数量
function get_shop_goods_cmt_list($user_id, $warehouse_id, $area_id, $price_min, $price_max, $page, $size, $sort, $order) {

    $leftJoin = '';
    $where = "1";
    $where .= " AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 ";

    if ($min > 0) {
        $where .= " AND IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) >= $min ";
    }

    if ($max > 0) {
        $where .= " AND IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) <= $max ";
    }

    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

    if ($GLOBALS['_CFG']['open_area_goods'] == 1) {
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('link_area_goods') . " as lag on g.goods_id = lag.goods_id ";
        $where .= " AND lag.region_id = '$area_id' ";
    }

    if ($GLOBALS['_CFG']['review_goods'] == 1) {
        $where .= ' AND g.review_status > 2 ';
    }

    $leftJoin .= 'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' ";

    $select = "g.goods_id, g.goods_thumb, g.goods_name, g.user_id, g.promote_start_date, g.promote_end_date, g.market_price, g.sales_volume, g.model_attr, ";
    $select .= "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, ";
    $select .= "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, ";
    $select .= "g.product_price, g.product_promote_price ";

    if ($sort == 'last_update') {
        $sort = 'g.last_update';
    }

    $sql = "SELECT " . $select . " FROM " . $GLOBALS['ecs']->table('goods') . " as g " . $leftJoin . " WHERE $where AND g.user_id = '$user_id'  ORDER BY $sort $order";
    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);

    while ($row = $GLOBALS['db']->fetchRow($res)) {

        if ($row['promote_price'] > 0) {
            $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
        } else {
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

        $arr[$row['goods_id']]['market_price'] = price_format($row['market_price']);
        $arr[$row['goods_id']]['shop_price'] = price_format($row['shop_price']);
        $arr[$row['goods_id']]['promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';

        $arr[$row['goods_id']]['sales_volume'] = $row['sales_volume'];
        $arr[$row['goods_id']]['goods_id'] = $row['goods_id'];
        $arr[$row['goods_id']]['goods_name'] = $row['goods_name'];
        $arr[$row['goods_id']]['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
        $arr[$row['goods_id']]['goods_url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
        $arr[$row['goods_id']]['user_id'] = $row['user_id'];

        $basic_info = get_shop_info_content($row['user_id']);
        $arr[$row['goods_id']]['kf_type'] = $basic_info['kf_type'];
        $arr[$row['goods_id']]['kf_ww'] = $basic_info['kf_ww'];
        $arr[$row['goods_id']]['kf_qq'] = $basic_info['kf_qq'];

        $arr[$row['goods_id']]['shop_name'] = get_shop_name($row['user_id'], 1); //店铺名称

        $build_uri = array(
            'urid' => $row['user_id'],
            'append' => $arr[$key]['shop_name']
        );

        $domain_url = get_seller_domain_url($row['user_id'], $build_uri);
        $arr[$row['goods_id']]['shop_url'] = $domain_url['domain_name'];

        $cmt_count = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('comment') . " where id_value ='" . $row['goods_id'] . "' AND status = 1 AND parent_id = 0");
        $arr[$row['goods_id']]['cmt_count'] = $cmt_count;
        $arr[$row['goods_id']]['is_collect'] = get_collect_user_goods($row['goods_id']);

        $shop_information = get_shop_name($row['user_id']); //通过ru_id获取到店铺信息;
        $arr[$row['goods_id']]['is_IM'] = $shop_information['is_IM']; //平台是否允许商家使用"在线客服";

        $arr[$row['goods_id']]['pictures'] = get_goods_gallery($row['goods_id'], 6); // 商品相册
        //判断当前商家是平台,还是入驻商家 bylu
        if ($row['user_id'] == 0) {
            //判断平台是否开启了IM在线客服
            if ($GLOBALS['db']->getOne("SELECT kf_im_switch FROM " . $GLOBALS['ecs']->table('seller_shopinfo') . "WHERE ru_id = 0")) {
                $arr[$row['goods_id']]['is_dsc'] = true;
            } else {
                $arr[$row['goods_id']]['is_dsc'] = false;
            }
        } else {
            $arr[$row['goods_id']]['is_dsc'] = false;
        }
    }

    return $arr;
}

function get_shop_goods_cmt_count($user_id, $price_min, $price_max){

	$where = "";
	if($GLOBALS['_CFG']['review_goods'] == 1){
			$where .= ' AND review_status > 2 ';
	}

	if ($min > 0)
    {
        $where .= " AND IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) >= $min ";
    }

    if ($max > 0)
    {
        $where .= " AND IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) <= $max ";
    }

	$sql = "SELECT COUNT(*) FROM " .$GLOBALS['ecs']->table('goods') ." WHERE user_id = '$user_id' AND is_on_sale = 1 AND is_alone_sale = 1 AND is_delete = 0 " . $where;
	$res = $GLOBALS['db']->getOne($sql);

	return $res;

}
//店铺搜索 end

//确认订单的用户收货地址列表
function get_order_user_address_list($user_id) {

    //&nbsp; {$address.region}
    $sql = "SELECT ua.*, " .
            "concat(IFNULL(p.region_name, ''), " .
            "'  ', IFNULL(t.region_name, ''), " .
            "'  ', IFNULL(d.region_name, ''), " .
            " '  ', IFNULL(s.region_name, '')) AS region " .
            "FROM " . $GLOBALS['ecs']->table('user_address') . " AS ua " .
            //"LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS c ON ua.country = c.region_id " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS p ON ua.province = p.region_id " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS t ON ua.city = t.region_id " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS d ON ua.district = d.region_id " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS s ON ua.street = s.region_id " .
            " WHERE user_id = '$user_id' GROUP BY ua.address_id"; // and audit = 1

    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $row) {
        $arr[] = $row;
    }

    return $arr;
}

/**  //ecmoban模板堂 --zhuo 可用、即将到期、已使用
 *
 * @access  public
 * @param   int         $user_id         用户ID
 * @param   int         $num             列表显示条数
 * @param   int         $start           显示起始位置
 *
 * @return  array       $arr             红保列表
 */
function get_user_bouns_new_list($user_id = 0, $page = 1, $type = 0, $pageFunc = '', $amount = 0, $size = 10, $cart_ru_id = -1)
{
    require_once('includes/cls_pager.php');

    $day = local_getdate();
    $cur_date = local_mktime(23, 59, 59, $day['mon'], $day['mday'], $day['year']);
    $before_date = local_mktime(0, 0, 0, $day['mon'], $day['mday'], $day['year']) - 2 * 24 * 3600; //前三天时间

    $useDate = " AND b.use_start_date < " .$cur_date. " AND b.use_end_date > " . $cur_date;

    $where = '';
    if($cart_ru_id > -1){
        $where .= " AND IF(b.usebonus_type > 0, 1, b.user_id IN($cart_ru_id))";
    }

    if($type == 0){
        $uOrder = " AND u.order_id = 0";
        $arrName = "available_list";
    }elseif($type == 1){
        $uOrder = " AND u.order_id = 0";
        $useDate = " AND b.use_start_date >= " .$before_date. " AND b.use_end_date > " . $cur_date;
        $arrName = "expire_list";
    }elseif($type == 2){
        $uOrder = " AND u.order_id > 0";
        $arrName = "useup_list";
    }

    $sql = "SELECT COUNT(*) FROM " .$GLOBALS['ecs']->table('user_bonus') ." as u,".$GLOBALS['ecs']->table('bonus_type'). " AS b".
            " WHERE u.bonus_type_id = b.type_id AND b.review_status = 3 " .$uOrder. " AND u.user_id = '$user_id' " . $useDate . $where;
    $record_count = $GLOBALS['db']->getOne($sql);

    $bouns_paper = '';
    $limit = '';
    if($amount == 0){
        $bouns =new Pager($record_count, $size, '', $user_id, 0, $page, $pageFunc, 1);
        $limit = $bouns->limit;
        $bouns_paper = $bouns->fpage(array(0,4,5,6,9));
    }

    $sql = "SELECT  u.bonus_id, u.bonus_sn, u.order_id, u.bind_time, b.type_name, b.type_money,b.min_amount, b.min_goods_amount, b.use_start_date, b.use_end_date, ".
        "b.usebonus_type, b.user_id AS ru_id FROM " .$GLOBALS['ecs']->table('user_bonus'). " AS u ,".
        $GLOBALS['ecs']->table('bonus_type'). " AS b".
        " WHERE u.bonus_type_id = b.type_id AND b.review_status = 3 " .$uOrder. " AND u.user_id = '" .$user_id. "' " .$useDate . $where ." order by u.bonus_id DESC ". $limit;
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();

    foreach($res as $key=>$row)
    {
        $arr[$key]['bonus_id']   = $row['bonus_id'];
        /* 先判断是否被使用，然后判断是否开始或过期 */

        if($type < 2){
            $arr[$key]['status'] = $GLOBALS['_LANG']['not_use'];
        }elseif($type == 2){
            $arr[$key]['status'] = '<a href="user.php?act=order_detail&order_id=' .$row['order_id']. '" >' .$GLOBALS['_LANG']['had_use']. '</a>';
        }


        $arr[$key]['shop_name'] = get_shop_name($row['ru_id'], 1); //店铺名称
        $arr[$key]['usebonus_type']   = $row['usebonus_type'];
        $arr[$key]['bonus_sn']   = $row['bonus_sn'];
        $arr[$key]['bouns_amount']   = $row['type_money'];
        $arr[$key]['type_money']   = price_format($row['type_money']);
        $arr[$key]['min_goods_amount']   = price_format($row['min_goods_amount']);

        $arr[$key]['use_startdate']   = local_date($GLOBALS['_CFG']['time_format'], $row['use_start_date']);
        $arr[$key]['use_enddate']     = local_date($GLOBALS['_CFG']['time_format'], $row['use_end_date']);
        $arr[$key]['bind_time']     = local_date($GLOBALS['_CFG']['time_format'], $row['bind_time']);
        $arr[$key]['type_name']     = $row['type_name'];
        $arr[$key]['min_goods_amount_old']   = $row['min_goods_amount'];
    }

    $bouns = array($arrName => $arr, 'record_count' => $record_count, 'paper' => $bouns_paper);

    return $bouns;

}

/**  已绑定储值卡列表
 *
 * @access  public
 * @param   int         $user_id         用户ID
 * @param   int         $num             列表显示条数
 * @param   int         $start           显示起始位置
 *
 * @return  array       $arr             储值卡列表
 */
function get_user_bind_vc_list($user_id = 0, $page = 1, $type = 0, $pageFunc = '', $amount = 0, $size = 10)
{
    require_once('includes/cls_pager.php');

    $sql =  "SELECT t.name, t.use_condition, v.vc_value, t.is_rec, v.vid, v.value_card_sn, v.card_money, v.bind_time,v.end_time FROM " .$GLOBALS['ecs']->table('value_card'). " AS v ".
			" LEFT JOIN ". $GLOBALS['ecs']->table('value_card_type') ." AS t ON v.tid = t.id ".
			" WHERE v.user_id = '$user_id' order by v.vid DESC ";

    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
	$now = gmtime();
    foreach($res as $key=>$row)
    {
		if($now > $row['end_time']){
			$arr[$key]['status']   = false;
		}else{
			$arr[$key]['status']   = true;
		}

        /* 先判断是否被使用，然后判断是否开始或过期 */
		$arr[$key]['name']    		= $row['name'];
		$arr[$key]['vid']    		= $row['vid'];
		$arr[$key]['value_card_sn'] = $row['value_card_sn'];
		$arr[$key]['vc_value']    	= price_format($row['vc_value']);
		$arr[$key]['use_condition']= condition_format($row['use_condition']);
		$arr[$key]['is_rec']    	= $row['is_rec'];
		$arr[$key]['card_money']    = price_format($row['card_money']);
        $arr[$key]['bind_time']     = local_date($GLOBALS['_CFG']['time_format'], $row['bind_time']);
        $arr[$key]['end_time']      = local_date('Y-m-d H:i:s', $row['end_time']);
    }
    return $arr;
}

/**  指定储值卡使用详情
 *
 * @access  public
 * @param   int     $vid   储值卡编号
 * @return  array   $arr   储值卡使用详情列表
 */
function value_card_use_info($vc_id = 0)
{
    require_once('includes/cls_pager.php');

    $sql =  "SELECT o.order_sn, r.rid, r.use_val, r.add_val, r.record_time FROM " .$GLOBALS['ecs']->table('value_card_record'). " AS r ".
			" LEFT JOIN ". $GLOBALS['ecs']->table('order_info') ." AS o ON r.order_id = o.order_id ".
			" WHERE r.vc_id = '$vc_id' order by r.rid DESC ";

    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();

    foreach($res as $key=>$row)
    {
		$arr[$key]['rid']    = $row['rid'];
		$arr[$key]['order_sn']    = $row['order_sn'];
		$arr[$key]['use_val']    = price_format($row['use_val']);
		$arr[$key]['add_val']    = price_format($row['add_val']);
        $arr[$key]['record_time']     = local_date($GLOBALS['_CFG']['time_format'], $row['record_time']);
    }

    return $arr;
}

//合算可用礼品卡总金额
function get_bouns_amount_list($bouns_list){

	$bouns_amount = 0;
	foreach($bouns_list['available_list'] as $key=>$row){
		$bouns_amount += $row['bouns_amount'];
	}

	return price_format($bouns_amount);
}

//入驻查询品牌
function get_merchants_search_brand($val = '' , $type = 0, $brand_type = '', $brand_name = '', $brand_letter = ''){

    $sqltype = '';
    $arr = array();
    $res = array();
    if(!empty($val) || ($type == 2 && (!empty($brand_name) && !empty($brand_letter)))){
        if($type == 2 || $type == 3){
            if($brand_type == 'm_bran'){
                $date = array('bid as brand_id', 'brandName as brand_name', 'bank_name_letter as brand_letter');
                $where = " bid = '$val' AND audit_status = 1";
                $res = get_table_date('merchants_shop_brand', $where, $date);
            }else{
                $date = array('brand_id', 'brand_name', 'brand_letter');

                if($type == 2){
                    if(empty($val)){
                        if(!empty($brand_name)){
                            $where = " brand_name = '$brand_name'";
                        }else{
                            $where = " brand_letter = '$brand_letter'";
                        }
                    }else{
                        $where = " brand_id = '$val'";
                    }
                }else{
                    $where = " 1";
                    $sqltype = 1;
                }

                $res = get_table_date('brand', $where, $date, $sqltype);
            }

        }else{

            if($type == 1){
                $sql = "SELECT brand_id, brand_name, brand_letter FROM " .$GLOBALS['ecs']->table('brand'). " WHERE brand_letter REGEXP '^$val'";
                $res1 = $GLOBALS['db']->getAll($sql);

            }else{
                $sql = "SELECT brand_id, brand_name, brand_letter FROM " .$GLOBALS['ecs']->table('brand'). " WHERE brand_name REGEXP '^$val'";
                $res1 = $GLOBALS['db']->getAll($sql);
            }

            $res = $res1;
        }
    }

    return $res;
}

/*
 *获取品牌数据
 */
function get_link_brand_list($brand_id, $type = 0, $sqlType = 0){

    if($type == 1){ //商家品牌
        $select = "b.bid as brand_id, b.brandName as brand_name";
        $table = "merchants_shop_brand";
        $where = "lb.bid = b.bid AND lb.bid = '$brand_id'";
    }elseif($type == 2){ //自营品牌
        $select = "b.brand_id, b.brand_name";
        $table = "brand";
        $where = "lb.brand_id = b.brand_id AND lb.brand_id = '$brand_id'";
    }elseif($type == 3){ //取出关联品牌数据 商家品牌ID
        $select = "b.brand_id, b.brand_name";
        $table = "brand";
        $where = "lb.brand_id = b.brand_id AND lb.bid = '$brand_id'";
    }elseif($type == 4){ //取出关联品牌数据 自营品牌ID
        $select = "b.brand_id, b.brand_name";
        $table = "brand";
        $where = "lb.brand_id = b.brand_id AND lb.brand_id = '$brand_id'";
    }

    $sql = "SELECT $select FROM " .$GLOBALS['ecs']->table('link_brand'). " as lb, " .$GLOBALS['ecs']->table($table). " as b WHERE $where";

    if($sqlType == 1){
        return $GLOBALS['db']->getAll($sql);
    }else{
        return $GLOBALS['db']->getRow($sql);
    }
}

// flow 和 flow_consignee公用
function get_update_flow_Consignee($address_id = 0)
{
    $consignee = array();
    if ($address_id) {
        $sql = "UPDATE " . $GLOBALS['ecs']->table('users') . " SET address_id = '$address_id' WHERE user_id = '" . $_SESSION['user_id'] . "'";
        $GLOBALS['db']->query($sql);

        $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('user_address') . " WHERE address_id = '$address_id'";

        $consignee =  $GLOBALS['db']->getRow($sql);
    }

    return $consignee;
}

/**
 * 调用购物车信息
 *
 * @access  public
 * @return  string
 */
function get_cart_info($type = 0)
{
    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
        $c_sess = " c.user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
        $c_sess = " c.session_id = '" . real_cart_mac_ip() . "' ";
    }

    $limit = '';
    if ($type == 1) {
        $limit = " LIMIT 0,4";
    }
    //ecmoban模板堂 --zhuo end

    $sql = 'SELECT c.*,g.goods_name,g.goods_thumb,g.goods_id,c.goods_number,c.goods_price' .
            ' FROM ' . $GLOBALS['ecs']->table('cart') . " AS c " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON g.goods_id=c.goods_id " .
            " WHERE " . $c_sess . " AND rec_type = '" . CART_GENERAL_GOODS . "'" . $limit;
    $row = $GLOBALS['db']->GetAll($sql);
    $arr = array();
    $cart_value = '';
    foreach ($row AS $k => $v) {
        $arr[$k]['goods_thumb'] = get_image_path($v['goods_id'], $v['goods_thumb'], true);
        $arr[$k]['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
                sub_str($v['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $v['goods_name'];
        $arr[$k]['url'] = build_uri('goods', array('gid' => $v['goods_id']), $v['goods_name']);
        $arr[$k]['goods_number'] = $v['goods_number'];
        $arr[$k]['goods_name'] = $v['goods_name'];
        $arr[$k]['goods_price'] = price_format($v['goods_price']);
        $arr[$k]['rec_id'] = $v['rec_id'];
        $arr[$k]['warehouse_id'] = $v['warehouse_id'];
        $arr[$k]['area_id'] = $v['area_id'];
        $cart_value = !empty($cart_value) ? $cart_value . ',' . $v['rec_id'] : $v['rec_id'];

        $properties = get_goods_properties($v['goods_id'], $v['warehouse_id'], $v['area_id'], $v['goods_attr_id'], 1);

        if ($properties['spe']) {
            $arr[$k]['spe'] = array_values($properties['spe']);
        } else {
            $arr[$k]['spe'] = array();
        }
    }

    $sql = 'SELECT SUM(goods_number) AS number, SUM(goods_price * goods_number) AS amount' .
            ' FROM ' . $GLOBALS['ecs']->table('cart') .
            " WHERE " . $sess_id . " AND rec_type = '" . CART_GENERAL_GOODS . "'";
    $row = $GLOBALS['db']->GetRow($sql);

    if ($row) {
        $number = intval($row['number']);
        $amount = floatval($row['amount']);
    } else {
        $number = 0;
        $amount = 0;
    }

    if ($type == 1) {

        $cart = array('goods_list' => $arr, 'number' => $number, 'amount' => price_format($amount, false));

        return $cart;
    } elseif ($type == 2) {
        //by wang
        $cart = array('goods_list' => $arr, 'number' => $number, 'amount' => price_format($amount, false));

        return $cart;
    } else {
        $GLOBALS['smarty']->assign('number', $number);
        $GLOBALS['smarty']->assign('amount', $amount);
        $GLOBALS['smarty']->assign('cart_info', $row);

        $GLOBALS['smarty']->assign('cart_value', $cart_value); //by wang
        $GLOBALS['smarty']->assign('str', sprintf($GLOBALS['_LANG']['cart_info'], $number, price_format($amount, false)));
        $GLOBALS['smarty']->assign('goods', $arr);

        $output = $GLOBALS['smarty']->fetch('library/cart_info.lbi');
        return $output;
    }
}

//商品连接地址
function get_return_goods_url($goods_id = 0, $goods_name = ''){
    if(empty($goods_name)){
        $goods_name = $GLOBALS['db']->getOne("SELECT goods_name FROM " .$GLOBALS['ecs']->table('goods'). " WHERE goods_id = '$goods_id'");
    }

    $url = build_uri('goods', array('gid'=>$goods_id), $goods_name);
    return $url;
}

//分类地址
function get_return_category_url($cat_id = 0){
    $cat_name = $GLOBALS['db']->getOne("SELECT cat_name FROM " .$GLOBALS['ecs']->table('category'). " WHERE cat_id = '$cat_id'");
    $url = build_uri('category', array('cid'=>$cat_id), $cat_name);
    return $url;
}

//店铺商品列表地址
function get_return_store_shop_url($ru_id = 0, $shop_name = ''){

    if(empty($shop_name)){
        $shop_name = get_shop_name($ru_id, 1);
    }

    $url = build_uri('merchants_store_shop', array('urid'=>$ru_id), $shop_name);
    return $url;
}

//店铺地址
function get_return_store_url($params = '', $append = ''){
    $url = build_uri('merchants_store', $params, $append);
    return $url;
}

//搜索地址
function get_return_search_url($keywords = ''){
    $url = build_uri('search', array('chkw'=>$keywords), $keywords);
    return $url;
}

function get_return_self_url(){
    $cur_url = $_SERVER["PHP_SELF"]."?".$_SERVER["QUERY_STRING"];
    $cur_url = explode('/', $cur_url);
    $cur_url = $cur_url[count($cur_url) - 1];

    return $cur_url;
}


//导航右边查询分类树 start
function get_category_tree_leve_one($parent_id = 0, $type = 0){
    $sql = "SELECT cat_id, cat_name, style_icon, cat_icon, category_links, cat_alias_name FROM " .$GLOBALS['ecs']->table('category'). " WHERE parent_id = 0 AND is_show = 1 ORDER BY sort_order ASC, cat_id ASC limit 16"; //by kong 限制显示分类数量
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach($res as $key=>$row){
        $arr[$row['cat_id']]['id'] = $row['cat_id'];
        $arr[$row['cat_id']]['cat_alias_name'] = $row['cat_alias_name'];
        $arr[$row['cat_id']]['url'] = build_uri('category', array('cid' => $row['cat_id']), $row['cat_name']);
        $arr[$row['cat_id']]['style_icon'] = $row['style_icon']; //分类菜单图标
        $arr[$row['cat_id']]['cat_icon'] = $row['cat_icon']; //自定义图标

        if(!empty($row['category_links']))
        {
            if(empty($type)){
                $cat_name_arr = explode('、', $row['cat_name']);
                if(!empty($cat_name_arr))
                {
                        $category_links_arr = explode("\r\n", $row['category_links']);
                }

                $cat_name_str = "";
                foreach($cat_name_arr as $cat_name_key => $cat_name_val)
                {
                        $link_str = $category_links_arr[$cat_name_key];

                        $cat_name_str .= '<a href="'.$link_str.'" target="_blank" class="division_cat">' . $cat_name_val;

                        if(count($cat_name_arr) == ($cat_name_key+1))
                        {
                                $cat_name_str .= '</a>';
                        }
                        else
                        {
                                $cat_name_str .= '</a>、';
                        }
                }

                $arr[$row['cat_id']]['name'] = $cat_name_str;
                $arr[$row['cat_id']]['category_link'] = 1;
                $arr[$row['cat_id']]['oldname'] = $row['cat_name'];//by EcMoban-weidong   保留原生元素
            }else{
                $arr[$row['cat_id']]['name'] = $row['cat_name'];
                $arr[$row['cat_id']]['oldname'] = $row['cat_name'];//by EcMoban-weidong   保留原生元素
            }
        }
        else
        {
                $arr[$row['cat_id']]['name'] = $row['cat_name'];
        }

        $arr[$row['cat_id']]['nolinkname'] = $row['cat_name'];

        if($type == 1){
            $arr[$row['cat_id']]['child_tree'] = cat_list($row['cat_id'], 1);
        }

        $sql = 'SELECT * ' .
               ' FROM ' . $GLOBALS['ecs']->table('category') .
               " WHERE parent_id = '" .$row['cat_id']. "' AND is_show = 1 ORDER BY sort_order ASC, cat_id ASC";
        $res = $GLOBALS['db']->getAll($sql);

        foreach($res as $key2 => $val)
        {
            $arr[$row['cat_id']]['child_two'][$key2]['cat_id'] =$val['cat_id'];
            $arr[$row['cat_id']]['child_two'][$key2]['cat_name'] = $val['cat_name'];
            $arr[$row['cat_id']]['child_two'][$key2]['url'] = build_uri('category', array('cid' => $val['cat_id']), $val['cat_name']);
            $arr[$row['cat_id']]['child_two'][$key2]['cat_icon'] =$val['cat_icon'];
        }
    }

    return $arr;
}

/**
 * 获取分类品牌
 */
function get_category_brands_ad($cat_id){

    $arr['ad_position'] = '';
    $arr['brands'] = '';

    $cat_name = '';
    for($i=1;$i<=$GLOBALS['_CFG']['auction_ad'];$i++){
        $cat_name .= "'cat_tree_" . $cat_id . "_" . $i . "',";
    }

    $cat_name = substr($cat_name, 0, -1);
    $arr['ad_position'] = get_ad_posti_child($cat_name);

    $g_children = get_children($cat_id);
    $gc_children = get_children($cat_id, 1);

    // 获取分类下品牌
    $sql = "SELECT b.brand_id, b.brand_name,  b.brand_logo, COUNT(*) AS goods_num, IF(b.brand_logo > '', '1', '0') AS tag ".
            "FROM " . $GLOBALS['ecs']->table('brand') . "AS b ".
            " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON g.brand_id = b.brand_id AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 ".
            " LEFT JOIN ". $GLOBALS['ecs']->table('goods_cat') . " AS gc ON g.goods_id = gc.goods_id " .
            "WHERE ($g_children OR $gc_children) AND b.is_show = 1 " .
            "GROUP BY b.brand_id HAVING goods_num > 0 ORDER BY b.sort_order, b.brand_id ASC LIMIT 0,12";

    $brands = $GLOBALS['db']->getAll($sql);

    $sql = "SELECT cat_name FROM " .$GLOBALS['ecs']->table('category'). " WHERE cat_id = '$cat_id' LIMIT 1";
    $name = $GLOBALS['db']->getOne($sql);

    foreach ($brands AS $key => $val)
    {
            $temp_key = $key;
            $brands[$temp_key]['brand_name'] = $val['brand_name'];
            $brands[$temp_key]['url'] = build_uri('category', array('cid' => $cat_id, 'bid' => $val['brand_id']), $name);

            $brands[$temp_key]['brand_logo'] = $GLOBALS['_CFG']['site_domain'] . DATA_DIR . '/brandlogo/'.$val['brand_logo'];//by wang
            //OSS文件存储ecmoban模板堂 --zhuo start
            if($GLOBALS['_CFG']['open_oss'] == 1){
                $bucket_info = get_bucket_info();
                $brands[$temp_key]['brand_logo'] = $bucket_info['endpoint'] . DATA_DIR . '/brandlogo/'.$val['brand_logo'];
            }
            //OSS文件存储ecmoban模板堂 --zhuo end

            // 判断品牌是否被选中
            if ($brand == $brands[$key]['brand_id'])
            {
                    $brands[$temp_key]['selected'] = 1;
            }
            else
            {
                    $brands[$temp_key]['selected'] = 0;
            }
    }

    $arr['brands'] = $brands;

    return $arr;
}

//页面分类树导航顶级分类专题模块
function get_category_topic($cat_id = 0){

    $arr = array();
    $sql = "SELECT category_topic FROM " .$GLOBALS['ecs']->table('category'). " WHERE cat_id = '$cat_id' LIMIT 1";
    if($res = $GLOBALS['db']->getRow($sql))
    {
        if($res['category_topic']){
            $category_topic_arr = explode("\r\n", $res['category_topic']);
            foreach($category_topic_arr as $key => $row)
            {
                if($row){
                    $row = explode("|", $row);
                    $arr[$key]['topic_name'] = $row[0];
                    $arr[$key]['topic_link'] = $row[1];
                }

            }
        }
    }
    return $arr;
}
//导航右边查询分类树 end

// 打印 by qin
function print_arr($arr)
{
    echo '<pre>';
    print_r($arr);
    exit;
}

//顶级分类页导航 start
function get_parent_cat_tree($cat_id){
    //顶级分类页分类显示
    $categories_child = read_static_cache('cat_top_cache'.$cat_id);

    //将数据写入缓存文件 by wang
    if(!$categories_child)
    {
            $categories_child = get_parent_cat_child($cat_id);
            write_static_cache('cat_top_cache'.$cat_id,$categories_child);
    }

    return $categories_child;
}
//顶级分类页导航 end

function get_template_js($arr = array()){
    $str = '';
    if($arr){
        foreach($arr as $row){
            $str .= '<script type="text/javascript" src="' .$GLOBALS['_CFG']['site_domain']. 'themes/' .$GLOBALS['_CFG']['template']. '/js/' .$row. '.js"></script> ';
        }
    }

    return $str;
}

/*
 * 平台分类
 * 获取上下级分类列表 by wu
 * $cat_id      分类id
 * $relation    关系 0:自己 1:上级 2:下级
 * $self        是否包含自己 true:包含 false:不包含
 */
function get_select_category($cat_id = 0, $relation = 0, $self = true, $user_id = 0, $table = 'category') {
    //静态数组
    static $cat_list = array();
    $cat_list[] = intval($cat_id);

    if ($relation == 0) {
        return $cat_list;
    } elseif ($relation == 1) {
        $sql = "SELECT parent_id FROM " . $GLOBALS['ecs']->table($table) . " WHERE cat_id='" . $cat_id . "' ";
        $parent_id = $GLOBALS['db']->getOne($sql);
        if (!empty($parent_id)) {
            get_select_category($parent_id, $relation, $self);
        }
        //删除自己
        if ($self == false) {
            unset($cat_list[0]);
        }
        $cat_list[] = 0;
        //去掉重复，主要是0
        return array_reverse(array_unique($cat_list));
    } elseif ($relation == 2) {
        $sql = "SELECT cat_id FROM " . $GLOBALS['ecs']->table($table) . " WHERE parent_id='" . $cat_id . "' ";
        $child_id = $GLOBALS['db']->getCol($sql);
        if (!empty($child_id)) {
            foreach ($child_id as $key => $val) {
                get_select_category($val, $relation, $self);
            }
        }
        //删除自己
        if ($self == false) {
            unset($cat_list[0]);
        }
        return $cat_list;
    }
}

//获取商家分类 by wu
function get_merchant_category($cat_id = 0, $ru_id = 0) {
    $sql = "SELECT c.cat_id, c.cat_name FROM " . $GLOBALS['ecs']->table('category') . " AS c " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_category') . " AS mc on mc.cat_id=c.cat_id " .
            " WHERE c.parent_id='$cat_id' AND mc.user_id='$ru_id' ORDER BY c.sort_order, c.cat_id DESC";
    $res = $GLOBALS['db']->getAll($sql);
    return $res;
}

//平台分类--调用下级分类列表 by wu
function insert_select_category($cat_id = 0, $child_cat_id = 0, $cat_level = 0, $select_jsId = 'cat_parent_id', $type = 0, $table = 'category', $seller_shop_cat = array()) {

    $cat_level = $cat_level + 1;
    //获取下级分类列表
    $child_category = cat_list($cat_id, 0, 0, $table, $seller_shop_cat, $cat_level);

    $GLOBALS['smarty']->assign('child_category', $child_category);

    //下级选中分类
    $GLOBALS['smarty']->assign('child_cat_id', $child_cat_id);

    //下级分类等级
    $GLOBALS['smarty']->assign('cat_level', $cat_level);

    //匹配js id
    $GLOBALS['smarty']->assign('select_jsId', $select_jsId);

    //输出类型 0:输出分类id和分类等级 1:只输出分类id 2:只输出分类等级
    $GLOBALS['smarty']->assign('type', $type);

    $html = $GLOBALS['smarty']->fetch('templates/get_select_category.dwt');

    return $html;
}

//商家分类--调用下级分类列表 by wu
function insert_seller_select_category($cat_id = 0, $child_cat_id = 0, $cat_level = 0, $select_jsId = 'cat_parent_id', $type = 0, $table = 'category', $seller_shop_cat = array(), $user_id = 0) {

    //获取下级分类列表
    $child_category = cat_list($cat_id, 0, 0, $table, $seller_shop_cat, 0, $user_id);
    $GLOBALS['smarty']->assign('child_category', $child_category);

    //下级选中分类
    $GLOBALS['smarty']->assign('child_cat_id', $child_cat_id);

    //下级分类等级
    $GLOBALS['smarty']->assign('cat_level', $cat_level + 1);

    //匹配js id
    $GLOBALS['smarty']->assign('select_jsId', $select_jsId);

    //输出类型 0:输出分类id和分类等级 1:只输出分类id 2:只输出分类等级
    $GLOBALS['smarty']->assign('type', $type);

    $html = $GLOBALS['smarty']->fetch('templates/get_select_category_seller.dwt');

    return $html;
}

//商家入驻分类
function get_seller_mainshop_cat($ru_id){
    $sql = "select user_shopMain_category from " .$GLOBALS['ecs']->table('merchants_shop_information'). " where user_id = '$ru_id'";
    return $GLOBALS['db']->getOne($sql);
}

//获取商家域名
function get_seller_domain(){
    $get_domain = $GLOBALS['ecs']->get_domain();

    $sql = "SELECT * FROM " .$GLOBALS['ecs']->table('seller_domain'). " WHERE domain_name = '$get_domain' AND is_enable = 1";
    return $GLOBALS['db']->getRow($sql);
}

//超值礼品包商品的重量和数量
function get_package_goods_info($package_list = array()){
    if($package_list)
    {
        $arr = array();
        $arr['goods_weight'] = 0;

        foreach($package_list as $key=>$row)
        {
            $arr[$key]['goods_weight'] = $row['goods_number'] * $row['goods_weight'];
            $arr['goods_weight'] += $arr[$key]['goods_weight'];
        }

        return $arr;
    }
}

//有存在虚拟和实体商品
function get_goods_flow_type($cart_value){

    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    //有存在虚拟和实体商品 start
    if (exist_real_goods(0, $flow_type, $cart_value))
    {
        $goods_flow_type = 101; //实体商品
    }
    else
    {
        $goods_flow_type = 100; //虚拟商品
    }

    $GLOBALS['smarty']->assign('goods_flow_type', $goods_flow_type);
    //有存在虚拟和实体商品 end
}

//处理用户名截取字符串 by wu
function setAnonymous($user_name) {
    if (ord(substr($user_name, 0, 1)) > 129) {
        $str_1 = substr($user_name, 0, 3);
    } else {
        $str_1 = substr($user_name, 0, 1);
    }
    if (ord(substr($user_name, -1)) > 129) {
        $str_2 = substr($user_name, -3);
    } else {
        $str_2 = substr($user_name, -1);
    }
    $user_name = $str_1 . '***' . $str_2;
    return $user_name;
}

/*过期申请失效处理  by kong grade*/
function get_invalid_apply($type = 0){
    $grade_apply_time = 1;
    if($GLOBALS['_CFG']['grade_apply_time'] > 0){
        $grade_apply_time = $GLOBALS['_CFG']['grade_apply_time'];
    }
    $time=gmtime()-24*60*60*$grade_apply_time;
    if($type == 1){
        $sql = "DELETE FROM".$GLOBALS['ecs']->table('seller_template_apply')."WHERE pay_status = 0 AND apply_status = 0 AND add_time < '".$time."'";
    }else{
        $sql=" UPDATE".$GLOBALS['ecs']->table('seller_apply_info')." SET apply_status = 3 WHERE is_paid = 0 AND add_time < '".$time."'";
    }
    return $GLOBALS['db']->query($sql);
}

/*获取商家等级*/
function get_seller_grade($ru_id = 0, $type = 0){

    if($type){
        $ru_id = implode(',', $ru_id);
        $where = "g.ru_id IN($ru_id)";
    }else{
        $where = "g.ru_id = '$ru_id' LIMIT 1";
    }

    $sql="SELECT s.grade_name, s.grade_img, s.grade_introduce, s.white_bar, g.grade_id, g.add_time, g.year_num, g.amount FROM".$GLOBALS['ecs']->table('seller_grade')." AS s "
            . "LEFT JOIN ".$GLOBALS['ecs']->table('merchants_grade')." AS g ON s.id = g.grade_id WHERE $where";

    if($type){
        $str = 1;
        $res = $GLOBALS['db']->getAll($sql);
        foreach($res as $k=>$v){
            if($v['white_bar'] == 0){
                $str = 0;
                break;
            }
        }

        return $str;
    }else{
        return $GLOBALS['db']->getRow($sql);
    }
}


/*等级到期处理*/
function grade_expire(){
    $time = gmtime();
    $where = " WHERE add_time+365*24*60*60*year_num < ".$time;
    //获取默认商家等级id
    $sql = "SELECT id FROM".$GLOBALS['ecs']->table('seller_grade')."WHERE is_default = 1";
    $grade_id = $GLOBALS['db']->getOne($sql);

    //存在默认等级  重置到期等级为默认等级  否则删除该商家等级
    if($grade_id > 0){
        $sql = "UPDATE".$GLOBALS['ecs']->table('merchants_grade')."SET grade_id = ".$grade_id ." , add_time = ".$time." , year_num = 1".$where;
    }else{
        $sql = "DELETE FROM ".$GLOBALS['ecs']->table('merchants_grade').$where;
    }
    return $GLOBALS['db']->query($sql);
}


//付款更新众筹信息 by wu
function update_zc_project($order_id = 0) {
    //取得订单信息
    $sql = " SELECT user_id, is_zc_order, zc_goods_id FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '$order_id' ";
    $order_info = $GLOBALS['db']->getRow($sql);
    $user_id = $order_info['user_id'];
    $is_zc_order = $order_info['is_zc_order'];
    $zc_goods_id = $order_info['zc_goods_id'];

    if ($is_zc_order == 1 && $zc_goods_id > 0) {
        //获取众筹商品信息
        $sql = " select * from " . $GLOBALS['ecs']->table('zc_goods') . " where id = '$zc_goods_id' ";
        $zc_goods_info = $GLOBALS['db']->getRow($sql);
        $pid = $zc_goods_info['pid'];
        $goods_price = $zc_goods_info['price'];

        //增加众筹商品支持的用户数量
        $sql = " UPDATE " . $GLOBALS['ecs']->table('zc_goods') . " SET backer_num = backer_num+1 WHERE id = '$zc_goods_id' ";
        $GLOBALS['db']->query($sql);

        //增加众筹商品支持的用户id
        $sql = "SELECT backer_list FROM " . $GLOBALS['ecs']->table('zc_goods') . " WHERE id = '$zc_goods_id'";
        $backer_list = $GLOBALS['db']->getOne($sql);
        if (empty($backer_list)) {
            $backer_list = $user_id;
        } else {
            $backer_list = $backer_list . ',' . $user_id;
        }
        $sql = "UPDATE " . $GLOBALS['ecs']->table('zc_goods') . " SET backer_list='$backer_list' WHERE id = '$zc_goods_id'";
        $GLOBALS['db']->query($sql);

        //增加众筹项目的支持用户总数量、增加众筹项目总金额
        $sql = "UPDATE " . $GLOBALS['ecs']->table('zc_project') . " SET join_num=join_num+1, join_money=join_money+$goods_price WHERE id = '$pid'";
        $GLOBALS['db']->query($sql);
    }
}

//判断是否有上传文件 by wu
function have_file_upload()
{
	if(!empty($_FILES) && count($_FILES) > 0)
	{
		foreach($_FILES as $key => $val)
		{
			if(empty($val['name']))
			{
				unset($_FILES[$key]);
			}
		}
		if(!empty($_FILES) && count($_FILES) > 0)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	else
	{
		return false;
	}
}

//获取众筹商品信息 by wu
function  get_zc_goods_info($order_id = 0)
{
	$sql = " SELECT is_zc_order, zc_goods_id FROM ".$GLOBALS['ecs']->table('order_info')." WHERE order_id = '$order_id' ";
	$order = $GLOBALS['db']->getRow($sql);

	if($order['is_zc_order'])
	{
		$sql = " SELECT zg.*, zg.id as gid, zp.* FROM ".$GLOBALS['ecs']->table('zc_goods')." AS zg ".
			" LEFT JOIN ".$GLOBALS['ecs']->table('zc_project')." AS zp on zp.id = zg.pid ".
			" WHERE zg.id = '$order[zc_goods_id]' ";
		$zc_goods_info = $GLOBALS['db']->getRow($sql);
		//处理数据
		$zc_goods_info['start_time'] = local_date('Y-m-d', $zc_goods_info['start_time']);
		$zc_goods_info['end_time'] = local_date('Y-m-d', $zc_goods_info['end_time']);
		$zc_goods_info['formated_amount'] = price_format($zc_goods_info['amount']);
		$zc_goods_info['formated_price'] = price_format($zc_goods_info['price']);
		$zc_goods_info['formated_shipping_fee'] = price_format($zc_goods_info['shipping_fee']);
		$zc_goods_info['return_time'] = sprintf($GLOBALS['_LANG']['zc_return_detail'], $zc_goods_info['return_time']);
		return $zc_goods_info;
	}
	return false;
}

//查询插件权限
function get_user_action_list($admin_id = 0, $string = ''){
    $sql = "SELECT action_list FROM " .$GLOBALS['ecs']->table('admin_user'). " WHERE user_id = '$admin_id'";
    $action_list = $GLOBALS['db']->getOne($sql);

    return $action_list;
}

//查询插件权限
function get_merchants_permissions($action_list, $string = ''){
    if($action_list == 'all'){
        return 1;
    }else{
        $action_list = explode(',', $action_list);
        if(in_array($string, $action_list)){
            return 1;
        }else{
            return 0;
        }
    }
}

//获取票税列表
function get_invoice_list($invoice, $order_type = 0, $inv_content = '') {

    $arr = array();
    if ($invoice['type']) {
        $type = array_values($invoice['type']);
        $rate = array_values($invoice['rate']);

        for ($i = 0; $i < count($type); $i++) {
            if($order_type == 1){
                if ($type[$i] == $inv_content) {
                    $arr['type'] = $type[$i];
                    $arr['rate'] = $rate[$i];
                }
            }else{
                $arr[$i]['type'] = $type[$i];
                $arr[$i]['rate'] = $rate[$i];
            }
        }
    }

    return $arr;
}

/*
 * 平台分类
 * 获取当级分类列表 by wu
 * $cat_id      分类id
 * $relation    关系 0:自己 1:上级 2:下级
 */
function get_category_list($cat_id = 0, $relation = 0, $seller_shop_cat = array(), $user_id = 0, $for_level = 0, $table = 'category') {
    if ($relation == 0) {
        $parent_id = $GLOBALS['db']->getOne(" SELECT parent_id FROM " . $GLOBALS['ecs']->table($table) . " WHERE cat_id = '$cat_id' ");
    } elseif ($relation == 1) {
        $parent_id = $GLOBALS['db']->getOne(" SELECT parent_id FROM " . $GLOBALS['ecs']->table($table) . " WHERE cat_id = '$cat_id' ");
    } elseif ($relation == 2) {
        $parent_id = $cat_id;
    }

    $where = '';
    if($user_id){
        if(isset($seller_shop_cat['parent']) && $seller_shop_cat['parent'] && $for_level < 3){
            $seller_shop_cat['parent'] = get_del_str_comma($seller_shop_cat['parent']);
            $where .= " AND cat_id IN(" .$seller_shop_cat['parent']. ")";
        }
    }

    $parent_id = empty($parent_id) ? 0 : $parent_id;

    $sql = "SELECT cat_id, cat_name FROM " . $GLOBALS['ecs']->table($table) . " WHERE parent_id = '$parent_id' $where";
    $category_list = $GLOBALS['db']->getAll($sql);
    foreach ($category_list as $key => $val) {
        if ($cat_id == $val['cat_id']) {
            $is_selected = 1;
        } else {
            $is_selected = 0;
        }
        $category_list[$key]['is_selected'] = $is_selected;

        $category_list[$key]['url'] = build_uri($table, array('cid' => $val['cat_id']), $val['cat_name']);
    }

    return $category_list;
}

/*
 * 搜索品牌列表
 */
function search_brand_list($goods_id = 0, $ru_id = null) {

    $seller_id = 0;
    if (!is_null($ru_id)) {
        $seller_id = $ru_id;
    } else {
        if ($goods_id > 0) {
            $sql = "SELECT user_id FROM " . $GLOBALS['ecs']->table('goods') . " WHERE goods_id = '$goods_id'";
            $seller_id = $GLOBALS['db']->getOne($sql, true);
        } else {
            $adminru = get_admin_ru_id();
            $seller_id = $adminru['ru_id'];
        }
    }

    $letter = !isset($_REQUEST['letter']) && empty($_REQUEST['letter']) ? '' : dsc_addslashes(trim($_REQUEST['letter']));
    $keyword = !isset($_REQUEST['keyword']) && empty($_REQUEST['keyword']) ? '' : dsc_addslashes(trim($_REQUEST['keyword']));

    $where = "";
    if (!empty($keyword)) {
        $where .= " AND (brand_name LIKE '%" . mysql_like_quote($keyword) . "%' OR brand_letter LIKE '%" . mysql_like_quote($keyword) . "%') ";
    }

    $sql = 'SELECT brand_id, brand_name FROM ' . $GLOBALS['ecs']->table('brand') . ' WHERE 1 ' . $where . ' ORDER BY sort_order';
    $res = $GLOBALS['db']->getAll($sql);

    $brand_list = read_static_cache('pin_brands', '/data/sc_file/');
    if ($brand_list === false && empty($keyword)) {

        $pin = new pin();

        $brand_list = array();
        foreach ($res AS $key => $val) {

            if ($seller_id) {
                $val['is_brand'] = get_seller_brand_count($val['brand_id'], $seller_id);
            } else {
                $val['is_brand'] = 1;
            }

            if ($val['is_brand'] > 0) {
                $brand_list[$key]['brand_id'] = $val['brand_id'];
                $brand_list[$key]['brand_name'] = $val['brand_name'];
                $brand_list[$key]['letter'] = strtoupper(substr($pin->Pinyin($val['brand_name'], EC_CHARSET), 0, 1));
            } else {
                unset($brand_list[$key]);
            }
        }

        !empty($brand_list) ? ksort($brand_list) : $brand_list;
        write_static_cache('pin_brands', $brand_list, '/data/sc_file/');
    } else {

        $brand_list = $res;
        if ($brand_list) {
            $pin = new pin();
            foreach ($brand_list AS $key => $val) {

                if ($seller_id) {
                    $val['is_brand'] = get_seller_brand_count($val['brand_id'], $seller_id);
                } else {
                    $val['is_brand'] = 1;
                }

                if ($val['is_brand'] > 0) {
                    $brand_list[$key]['brand_id'] = $val['brand_id'];
                    $brand_list[$key]['brand_name'] = $val['brand_name'];
                    $brand_list[$key]['letter'] = strtoupper(substr($pin->Pinyin($val['brand_name'], EC_CHARSET), 0, 1));
                } else {
                    unset($brand_list[$key]);
                }
            }
        }

        $arr = array();

        if ($brand_list) {
            foreach ($brand_list AS $key => $val) {
                if (!empty($letter) && empty($keyword)) {
                    if ($letter == "QT" && !$brand_list[$key]['letter']) {
                        $arr[$key] = $val;
                    } elseif ($letter == $brand_list[$key]['letter']) {
                        $arr[$key] = $val;
                    }
                } else {
                    $arr = $brand_list;
                }
            }
        }

        $brand_list = $arr;
    }

    return $brand_list;
}

/**
 * 获取商家品牌数量
 */
function get_seller_brand_count($brand_id = 0, $seller_id = 0) {

    $where = '1';
    if($brand_id){
        $where .= " AND lb.brand_id = '$brand_id'";
    }

    if($seller_id){
        $where .= " AND msb.user_id = '$seller_id'";
    }

    $sql = "SELECT lb.brand_id FROM " . $GLOBALS['ecs']->table('link_brand') . " AS lb," .
            $GLOBALS['ecs']->table('brand') . " AS b, " .
            $GLOBALS['ecs']->table('merchants_shop_brand') . " AS msb " .
            " WHERE $where AND lb.brand_id = b.brand_id AND lb.bid = msb.bid";

    $res = $GLOBALS['db']->getAll($sql, true);
    $count = count($res);
    return $count;
}

/**
 * 取得可用的配送方式列表
 * @param   array   $region_id_list     收货人地区id数组（包括国家、省、市、区）
 * @return  array   配送方式数组
 */
function available_shipping_list($region, $ru_id = 0, $is_limit = 0)
{
    $limit = '';
    if($is_limit){
        $limit = " LIMIT 0, 1";
    }

    $shipping_list = array();
    $sql = "SELECT s.* FROM " . $GLOBALS['ecs']->table('shipping') . " AS s " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('goods_transport_tpl') . ' AS gtt ON s.shipping_id = gtt.shipping_id' .
            " WHERE gtt.user_id = '$ru_id' AND s.enabled = 1" .
            " AND (FIND_IN_SET('" . $region[1] . "', gtt.region_id) OR FIND_IN_SET('" . $region[2] . "', gtt.region_id) OR FIND_IN_SET('" . $region[3] . "', gtt.region_id) OR FIND_IN_SET('" . $region[4] . "', gtt.region_id))" .
            " GROUP BY s.shipping_id" . $limit;
    $shipping_list1 = $GLOBALS['db']->getAll($sql);

    $sql = "SELECT s.* FROM " . $GLOBALS['ecs']->table('shipping') . " AS s " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('goods_transport_extend') . " AS gted ON gted.ru_id = '$ru_id'" .
            " LEFT JOIN " . $GLOBALS['ecs']->table('goods_transport_express') . " AS gte ON gted.tid = gte.tid AND gte.ru_id = '$ru_id'" .
            " WHERE FIND_IN_SET(s.shipping_id, gte.shipping_id) " .
            " AND ((FIND_IN_SET('" . $region[1] . "', gted.top_area_id)) OR (FIND_IN_SET('" . $region[2] . "', gted.area_id) OR FIND_IN_SET('" . $region[3] . "', gted.area_id) OR FIND_IN_SET('" . $region[4] . "', gted.area_id)))" .
            " GROUP BY s.shipping_id";
    $shipping_list2 = $GLOBALS['db']->getAll($sql);

    if ($shipping_list1 && $shipping_list2) {
        $shipping_list = array_merge($shipping_list1, $shipping_list2);
    } elseif ($shipping_list1) {
        $shipping_list = $shipping_list1;
    } elseif ($shipping_list2) {
        $shipping_list = $shipping_list2;
    }

    if ($shipping_list) {
        //去掉重复配送方式 start
        $new_shipping = array();
        foreach ($shipping_list as $key => $val) {
            @$new_shipping[$val['shipping_code']][] = $key;
        }

        foreach ($new_shipping as $key => $val) {
            if (count($val) > 1) {
                for ($i = 1; $i < count($val); $i++) {
                    unset($shipping_list[$val[$i]]);
                }
            }
        }
        //去掉重复配送方式 end

        $shipping_list = get_array_sort($shipping_list, 'shipping_order');
    }

    $cfg = array(
        array('name' => 'item_fee', 'value' => 0),
        array('name' => 'base_fee', 'value' => 0),
        array('name' => 'step_fee', 'value' => 0),
        array('name' => 'free_money', 'value' => 100000)
    );

    if ($shipping_list) {
        foreach ($shipping_list as $key => $row) {

            if (!isset($row['configure']) && empty($row['configure'])) {
                $shipping_list[$key]['configure'] = serialize($cfg);
            }
        }
    }

    return $shipping_list;
}

/* 返回地址 by wu */
function get_complete_address($info = array())
{
	$complete_address = array();
	if($info['country'])
	{
		$region_info = get_region_info($info['country']);
		$complete_address[] = $region_info['region_name'];
	}
	if($info['province'])
	{
		$region_info = get_region_info($info['province']);
		$complete_address[] = $region_info['region_name'];
	}
	if($info['city'])
	{
		$region_info = get_region_info($info['city']);
		$complete_address[] = $region_info['region_name'];
	}
	if($info['district'])
	{
		$region_info = get_region_info($info['district']);
		$complete_address[] = $region_info['region_name'];
	}
	$complete_address = implode(' ', $complete_address);
	return $complete_address;
}

/* 获取记录信息 by wu */
function get_store_order_info($id = 0, $type = 'id')
{
	if($type == 'id')
	{
		$sql = " SELECT * FROM ".$GLOBALS['ecs']->table('store_order')." WHERE id = '$id' ";
	}
	if($type == 'order_id')
	{
		$sql = " SELECT * FROM ".$GLOBALS['ecs']->table('store_order')." WHERE order_id = '$id' ";
	}

	$store_order_info = $GLOBALS['db']->getRow($sql);
	return $store_order_info;
}

/* 获取商家门店列表 by wu */
function get_store_list($order_id = 0)
{
	$ru_id = get_ru_id($order_id);
	$sql = " SELECT * FROM ".$GLOBALS['ecs']->table('offline_store')." WHERE ru_id = '$ru_id' ";
	$store_list = $GLOBALS['db']->getAll($sql);
	foreach($store_list as $key=>$val)
	{
		$info = array('country' => $val['country'],
			'province' => $val['province'],
			'city'     => $val['city'],
			'district' => $val['district']
		);
		$store_list[$key]['complete_store_address'] = get_complete_address($info) . ' ' . $val['stores_address'];
	}
	return $store_list;
}

/* 通过订单商品返回ru_id by wu */
function get_ru_id($order_id = 0)
{
	$sql = " SELECT ru_id FROM ".$GLOBALS['ecs']->table('order_goods')." WHERE order_id = '$order_id' LIMIT 1 ";
	$ru_id = $GLOBALS['db']->getOne($sql);
	if(!$ru_id)
	{
		$adminru = get_admin_ru_id();
		$ru_id = $adminru['ru_id'];
	}
	return $ru_id;
}

/*
 * 重定义商品价格
 * 商品价格 + 属性价格
 * start
 * 获取商品列表第一组属性价格
 */
function get_goods_one_attr_price($goods, $warehouse_id = 0, $area_id = 0, $promote_price = 0, $goods_product = array()) {

    $goods_product = array(
        'product_price' => $goods['product_price'],
        'product_promote_price' => $goods['product_promote_price']
    );

    $products = array();
    $market_price = $goods['market_price'];
    $org_price = $goods['org_price'];
    $shop_price = $goods['shop_price'];

    if ($GLOBALS['_CFG']['add_shop_price'] == 0 && $goods['model_attr'] == 0) {
        $time = gmtime();
        if ($goods_product && $goods_product['product_price'] > 0) {
            $shop_price = $goods_product['product_price'] * $_SESSION['discount'];
            if ($time >= $goods['promote_start_date'] && $time <= $goods['promote_end_date']) {
                $promote_price = $goods_product['product_promote_price'];
            }
        } else {
            $spec_price = 0;
            $properties = get_goods_properties($goods['goods_id'], $warehouse_id, $area_id, '', 0, $goods['model_attr'], 0);  // 获得商品的规格和属性
            $spe = !empty($properties['spe']) ? array_values($properties['spe']) : $properties['spe'];

            $arr = array();
            $arr['attr_id'] = '';
            if ($spe) {
                foreach ($spe as $key => $val) {
                    if ($val['values']) {
                        if ($val['is_checked']) {
                            $arr[$key]['values'] = get_goods_checked_attr($val['values']);
                        } else {
                            $arr[$key]['values'] = $val['values'][0];
                        }
                    }

                    if ($arr[$key]['values']['id']) {
                        $arr['attr_id'] .= $arr[$key]['values']['id'] . ",";
                    }
                }

                $arr['attr_id'] = get_del_str_comma($arr['attr_id']);
            }

            if (!empty($arr['attr_id'])) {
                $products = get_warehouse_id_attr_number($goods['goods_id'], $arr['attr_id'], $goods['user_id'], 0, 0, $goods['model_attr']);

                if ($products) {

                    $products['product_market_price'] = isset($products['product_market_price']) ? $products['product_market_price'] : 0;
                    $products['product_price'] = isset($products['product_price']) ? $products['product_price'] : 0;
                    $products['product_promote_price'] = isset($products['product_promote_price']) ? $products['product_promote_price'] : 0;

                    $market_price = $products['product_market_price'];
                    $org_price = $products['product_price'] * $_SESSION['discount'];
                    $shop_price = $products['product_price'] * $_SESSION['discount'];

                    if ($time >= $goods['promote_start_date'] && $time <= $goods['promote_end_date']) {
                        $promote_price = $products['product_promote_price'];
                    }

                    $other = array(
                        'product_table' => $products['product_table'],
                        'product_id' => $products['product_id'],
                        'product_price' => $products['product_price'],
                        'product_promote_price' => $products['product_promote_price']
                    );

                    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('goods'), $other, 'UPDATE', "goods_id = '" . $goods['goods_id'] . "'");
                }
            }
        }
    }

    $price = array(
        'goods_id' => !empty($goods['goods_id']) ? $goods['goods_id'] : 0,
        'market_price' => $market_price,
        'org_price' => $org_price,
        'shop_price' => $shop_price,
        'promote_price' => $promote_price
    );

    return $price;
}

/**
 * 重定义商品价格
 * 获取商品属性默认选择中数组
 * end
 */
function get_goods_checked_attr($values){
    foreach($values as $key=>$val){
        if($val['checked']){
            return $val;
        }
    }
}

/**
 * 获得商品分类的所有信息
 *
 * @param   integer     $cat_id     指定的分类ID
 *
 * @return  mix
 */
function get_cat_info($cat_id = 0, $select = array(), $table = 'category')
{
    if($select){
        $select = implode(",", $select);
    }else{
        $select = "*";
    }

    $sql = "SELECT $select FROM " .$GLOBALS['ecs']->table($table). " WHERE cat_id = '$cat_id' LIMIT 1";
    $row = $GLOBALS['db']->getRow($sql);

    return $row;
}

/**
 * 单条数据
 * 获取商品属性ID
 * goods_attr_id
 * $where_select 查询条件
 * $select 查询内容
 * $attr_type 唯一属性、单选属性、复选属性
 * $retuen_db 返回值模式（0-单条、1-单组、2-多组）
 */
function get_goods_attr_id($where_select = array(), $select = array(), $attr_type = 0, $retuen_db = 0) {

    if($select){
        $select = implode(",", $select);
    }else{
        $select = "ga.*, a.*";
    }

    $where = '';
    if(isset($where_select['goods_id'])){
        $where .= " AND ga.goods_id = '" .$where_select['goods_id']. "'";
    }

    if(isset($where_select['attr_value']) && !empty($where_select['attr_value'])){
        $where .= " AND ga.attr_value = '" .$where_select['attr_value']. "'";
    }

    if(isset($where_select['attr_id']) && !empty($where_select['attr_id'])){
        $where .= " AND ga.attr_id = '" .$where_select['attr_id']. "'";
    }

    if(isset($where_select['goods_attr_id']) && !empty($where_select['goods_attr_id'])){
        $where .= " AND ga.goods_attr_id = '" .$where_select['goods_attr_id']. "'";
    }

    if(isset($where_select['admin_id']) && !empty($where_select['admin_id'])){
        $where .= " AND ga.admin_id = '" .$where_select['admin_id']. "'";
    }

    if($attr_type && is_array($attr_type)){
        $attr_type = implode(",", $attr_type);
        $where .= " AND a.attr_type IN($attr_type)";
    }else{
        if($attr_type){
            $where .= " AND a.attr_type = '$attr_type'";
        }
    }

    $where .= " ORDER BY a.sort_order, a.attr_id, ga.goods_attr_id";
    if($retuen_db == 1){
        $where .= " LIMIT 1";
    }

    $sql = " SELECT $select FROM " . $GLOBALS['ecs']->table('goods_attr') . " AS ga, " .
            $GLOBALS['ecs']->table('attribute') . " AS a" .
            " WHERE ga.attr_id = a.attr_id $where";

    if($retuen_db == 1){
        return $GLOBALS['db']->getRow($sql);
    }elseif($retuen_db == 2){
        return $GLOBALS['db']->getAll($sql);
    }else{
        return $GLOBALS['db']->getOne($sql, true);
    }
}

/**
 * 获取活动信息
 */
function get_goods_activity_info($act_id = 0, $select = array()) {

    if(!empty($select) && is_array($select)){
        $select = implode(",", $select);
    }elseif(empty($select)){
        $select = '*';
    }

    $sql = "SELECT $select FROM " . $GLOBALS['ecs']->table('goods_activity') . " WHERE review_status = 3 AND act_id = '$act_id'";
    $activity = $GLOBALS['db']->getRow($sql);

    if($activity){
        $activity['goods_thumb'] = get_image_path($activity['act_id'], $activity['activity_thumb'], true);
    }

    return $activity;
}

/*
 * 获取商品分类佣金比率 by wu
 */
function get_commission_rate($goods_id = 0, $type = 0) {
    $sql = " SELECT cat_id FROM " . $GLOBALS['ecs']->table('goods') . " WHERE goods_id = '$goods_id' ";
    $cat_id = $GLOBALS['db']->getOne($sql);
    $commission_rate = 0;
    while ($cat_id > 0) {
        $sql = " SELECT commission_rate FROM " . $GLOBALS['ecs']->table('category') . " WHERE cat_id = '$cat_id' ";
        $commission_rate = $GLOBALS['db']->getOne($sql);
        if ($commission_rate > 0) {
            break;
        } else {
            $sql = " SELECT parent_id FROM " . $GLOBALS['ecs']->table('category') . " WHERE cat_id = '$cat_id' ";
            $cat_id = $GLOBALS['db']->getOne($sql);
        }
    }
    if ($commission_rate > 0) {
        $commission_rate /= 100;
    }

    if($type == 1){

        $arr = array(
            'commission_rate'   => $commission_rate,
            'cat_id'   => $cat_id,
        );

        return $arr;
    }else{
        return $commission_rate;
    }
}

/*
 * 获取订单商品佣金（未考虑实付订单金额）
 */
function get_order_goods_commission($order_id = 0, $type = 0) {

    $sql = " SELECT goods_id, goods_price, goods_number FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '$order_id' ";
    $order_goods = $GLOBALS['db']->getAll($sql);

    $commission = 0; //浮点数，保留两位数
    $cat = array();
    if ($order_goods) {
        foreach ($order_goods as $goods) {

            if($type == 1){
                $rate = get_commission_rate($goods['goods_id'], $type);

                $cat[$goods['goods_id']]['commission_rate'] = $rate['commission_rate'];
                $cat[$goods['goods_id']]['cat_id'] = $rate['cat_id'];

                $commission_rate = $rate['commission_rate'];
            }else{
                $commission_rate = get_commission_rate($goods['goods_id']);
            }

            $commission += $goods['goods_price'] * $goods['goods_number'] * $commission_rate;
        }
    }

    if($type == 1){

        $arr = array(
            'commission'   => $commission,
            'cat'   => $cat
        );

        return $arr;
    }else{
        return $commission;
    }

}

/**
 * 易源数据接口（https://www.showapi.com/）
 * 创建参数(包括签名的处理)
 */
function get_showapi() {

    $paramArr = array(
        'showapi_appid' => '29464',  //appid
        'code' => '737110900011' //条形码
    );

    $showapi_secret = "ad31a785a8614098a4e16227c175145d"; //secret

    $paraStr = "";
    $signStr = "";
    ksort($paramArr);
    foreach ($paramArr as $key => $val) {
        if ($key != '' && $val != '') {
            $signStr .= $key . $val;
            $paraStr .= $key . '=' . urlencode($val) . '&';
        }
    }
    $signStr .= $showapi_secret; //排好序的参数加上secret,进行md5
    $sign = strtolower(md5($signStr));
    $paraStr .= 'showapi_sign=' . $sign; //将md5后的值作为参数,便于服务器的效验

    $http = new Http();
    $hres = $http->doPost("http://route.showapi.com/66-22", $paraStr);
    return json_decode($hres, true);
}

/* 极速数据扫码接口（http://www.jisuapi.com/） by wu */
function get_jsapi($paramArr = array()) {
	$paraStr = "";
    foreach ($paramArr as $key => $val) {
        if ($key != '' && $val != '') {
            $signStr .= $key . $val;
            $paraStr .= $key . '=' . urlencode($val) . '&';
        }
    }

	$url = "http://api.jisuapi.com/barcode2/query";
    $http = new Http();
    $hres = $http->doPost($url, $paraStr);
    return json_decode($hres, true);
}

/* 获取扫码配置数据 by wu */
function get_scan_code_config($ru_id = 0) {
    $config = get_table_date('seller_shopinfo', "ru_id = '$ru_id'", array('js_appkey', 'js_appsecret'));
    return $config;
}

/* 获取扩展数据 by wu */

function get_goods_extend_info($goods_id = 0) {
    $arr = array();
    $select = "width, height, depth, origincountry, originplace, assemblycountry, barcodetype, catena, isbasicunit, packagetype, grossweight, netweight, netcontent, licensenum, healthpermitnum";
    $sql = " SELECT $select FROM " . $GLOBALS['ecs']->table('goods_extend') . " WHERE goods_id = '$goods_id' ";
    $extend_info = $GLOBALS['db']->getRow($sql);
    foreach ($extend_info as $key => $val) {
        if (isset($GLOBALS['_LANG'][$key]) && !empty($val)) {
            $arr[$GLOBALS['_LANG'][$key]] = $val;
        }
    }
    return $arr;
}

/**
 * 获取当前位置店铺
 */
function get_goods_user_area_position($ru_id = 0, $city_id = 0, $spec_arr = '', $goods_id = 0, $provinces_id = 0, $district_id = 0, $type = 0, $store_id = 0, $limit = 0){
    $where = "";
    if($goods_id > 0){
        $where .= "AND s.goods_id ='$goods_id'";
    }
    if($provinces_id > 0){
        $where .= " AND o.province = ".$provinces_id;
    }
    if($city_id > 0){
        $where .= " AND o.city = ".$city_id;
    }
    if($district_id > 0){
        $where .= " AND o.district = ".$district_id;
    }
    if($store_id > 0){
        $where .= " AND o.id = ".$store_id;
    }else{
        $where .= " AND o.ru_id = '$ru_id'";
    }

    if($limit == 1){
        $limit = " LIMIT 1";
    }else{
        $limit = '';
    }

    $sql = "SELECT o.id,s.goods_id,s.goods_number,o.ru_id,o.stores_name, o.province, o.city, o.district, o.stores_address, o.stores_tel, o.stores_opening_hours FROM "
            .$GLOBALS['ecs']->table('offline_store')." AS o LEFT JOIN ".$GLOBALS['ecs']->table('store_goods')." AS s ON o.id = s.store_id "
            . "WHERE  o.is_confirm=1 ".$where ." GROUP BY o.id $limit";
    $store_list = $GLOBALS['db']->getAll($sql);
    if($store_list){
        if($spec_arr){
            $is_spec = explode(',', $spec_arr);
        }

        foreach($store_list as $key=>$row){
            $unset_type = 0;
            if (is_spec($is_spec) == true) {
                $products = get_warehouse_id_attr_number($row['goods_id'], $spec_arr, $row['ru_id'], 0, 0, '', $row['id']); //获取属性库存
                $store_list[$key]['goods_number'] = $products['product_number'];

                if ($products['product_number'] == 0) {
                    unset($store_list[$key]);
                    $unset_type = 1;
                }
            }
            if($type == 0 && $unset_type == 0){
                $region = array(
                    'province' => $row['province'],
                    'city' => $row['city'],
                    'district' => $row['district'],
                );
                $store_list[$key]['area_info'] = get_area_region_info($region);
            }
        }
    }
    if(!empty($store_list)){
        sort($store_list);
    }
    return $store_list;
}

/* 使用限制条件格式化 */
function condition_format($conditon) {
    switch ($conditon) {
        case 1:
            return $GLOBALS['_LANG']['spec_cat'];
            break;
        case 2:
            return $GLOBALS['_LANG']['spec_goods'];
            break;
        case 0:
            return $GLOBALS['_LANG']['all_goods'];
        default:
            return 'N/A';
            break;
    }
}

/**
 * 获取上一级地区
 */
function get_parent_regions($region_id = 0){
    $sql = 'SELECT region_id,region_name FROM ' . $GLOBALS['ecs']->table('region') .
            " WHERE parent_id = (SELECT parent_id FROM " . $GLOBALS['ecs']->table('region')." WHERE region_id = '$region_id' )";
    return $GLOBALS['db']->GetAll($sql);
}

/**
 * 清除缓存
 */
function set_clear_cache($dirName = '', $arr = array(), $type = 0) {
    $j = 0;
    if (is_dir($dirName)) {
        if ($handle = opendir($dirName)) {
            while (false !== ( $item = readdir($handle) )) {
                if ($item != "." && $item != ".." && $item != ADMIN_PATH && $item != SELLER_PATH && $item != STORES_PATH && $item != 'index.htm' && $item != 'index.html') {
                    $aaa[] = $item;
                    if (!is_dir("$dirName/$item")) {
                        if ($arr) {
                            if ($type > 0) {
                                $i = 0;
                                foreach ($arr as $k => $v) {
                                    if ($v) {
                                        if (strstr($item, $v)) {
                                            $i++;
                                        }
                                    }
                                }
                                if ($i == 0) {
                                    $j ++;
                                    @unlink("$dirName/$item");
                                }
                                for ($i = 0; $i < 16; $i++) {
                                    $hash_dir = ROOT_PATH . "temp/caches/" . dechex($i);
                                    $dirs = $hash_dir;
                                    set_clear_cache($dirs);
                                }
                            } else {
                                foreach ($arr as $k => $v) {
                                    if ($v) {
                                        if (strstr($item, $v)) {
                                            $j ++;
                                            @unlink("$dirName/$item");
                                        }
                                    }
                                }
                            }
                        } else {
                            $j ++;
                            @unlink("$dirName/$item");
                        }
                    }
                }
            }
            closedir($handle);
        }
    }
    return $j;
}

/* 优惠券分类 */
function get_cou_children($cat = ''){

    $catlist = '';
    if($cat){
        $cat = explode(",", $cat);
        foreach($cat as $key=>$row){
            $catlist .= get_children($row, 2) . ",";
        }

        $catlist = get_del_str_comma($catlist, 0, -1);
        $catlist = array_unique(explode(",", $catlist));
        $catlist = implode(",", $catlist);
        $cat = implode(",", $cat);
        $catlist = !empty($catlist) ? $catlist .",". $cat : $cat;
    }

    return $catlist;
}
//获取指定分类的顶级分类
function get_topparent_cat($cat_id = 0){
    //静态数组
    static $cat_list = '';
    $sql = "SELECT parent_id,cat_id,cat_name FROM " . $GLOBALS['ecs']->table("category") . " WHERE cat_id='" . $cat_id . "' ORDER BY sort_order";
    $cat_info = $GLOBALS['db']->getRow($sql);
    if (!empty($cat_info['parent_id'])) {
        get_topparent_cat($cat_info['parent_id']);
    }else{
        $cat_list = $cat_info;
    }
    return $cat_list;
}

/* 获取分类店铺
 * $cat_id 分类id
 * $num 获取数量
 * 返回店铺列表
 */
function get_category_store($cat_id = 0, $num = 6){
	$children = get_children($cat_id);
    // 获取分类下品牌
    $sql = " SELECT g.user_id, COUNT(*) AS goods_num, ss.shop_name, ss.shop_title, ss.shop_logo, ss.logo_thumb, ss.street_thumb, ss.brand_thumb, ss.street_desc ".
            " FROM " . $GLOBALS['ecs']->table('goods') . "AS g ".
            " LEFT JOIN " . $GLOBALS['ecs']->table('seller_shopinfo') . " AS ss ON ss.ru_id = g.user_id ".
            " WHERE $children AND g.user_id > 0 AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 " .
            " GROUP BY g.user_id HAVING goods_num > 0 ORDER BY goods_num DESC LIMIT 0,$num ";
    $store_list = $GLOBALS['db']->getAll($sql);
	foreach($store_list as $key=>$row){
		$build_uri = array(
            'urid' => $row['user_id'],
            'append' => $row['shop_name']
        );
        $domain_url = get_seller_domain_url($row['user_id'], $build_uri);
		$store_list[$key]['shop_url'] = $domain_url['domain_name'];
	}
	return $store_list;
}

/* 获取楼层内容 by wu
 * type: index-首页，category-分类
 * id: 可以是cat_id或其他
 */
function get_floor_data($type = 'index', $id = 0) {
    $data = array();
    if ($type == 'index') {
        $sql = 'SELECT c.cat_id, c.cat_name, c.cat_alias_name FROM ' . $GLOBALS['ecs']->table('template') . " AS t " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('category') . " AS c ON c.cat_id = t.id " .
                " WHERE t.filename = 'index' AND t.type =1 AND t.theme='" . $GLOBALS['_CFG']['template'] . "' AND t.remarks='' order by t.sort_order asc";
        $template = $GLOBALS['db']->getAll($sql);
        foreach ($template as $key => $val) {
            $arr['id'] = $val['cat_id'];
            $arr['name'] = $val['cat_alias_name'];
            $data[] = $arr;
        }
    }

    return $data;
}

/* 获取上传附件大小 by wu
 * type: 0-字节，1-格式化
 */
function upload_size_limit($type = 0)
{
    $upload_size_limit = $GLOBALS['_CFG']['upload_size_limit'] == '-1' ? ini_get('upload_max_filesize') . 'B' : $GLOBALS['_CFG']['upload_size_limit'] . 'KB';
	$upload_size_limit = strtoupper($upload_size_limit);

	if($type == 0){
		$size = $upload_size_limit{strlen($upload_size_limit) - 2};
		$upload_size_limit = intval(preg_replace("/(KB|MB)/i", "", $upload_size_limit));
		switch($size)
		{
			case 'M': $upload_size_limit *= 1024*1024; break;
			case 'K': $upload_size_limit *= 1024; break;
		}
	}

	return $upload_size_limit;
}

//导航右边查询分类树 start
function get_top_category_tree($parent_id = 0){

    $sql = "SELECT cat_id, cat_name, style_icon, cat_icon, category_links, cat_alias_name FROM " .$GLOBALS['ecs']->table('category'). " WHERE parent_id = 0 AND is_show = 1 ORDER BY sort_order ASC, cat_id ASC "; //by kong 限制显示分类数量
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach($res as $key=>$row){
        $arr[$row['cat_id']]['id'] = $row['cat_id'];
        $arr[$row['cat_id']]['cat_alias_name'] = $row['cat_alias_name'];
        $arr[$row['cat_id']]['url'] = build_uri('seckill', array('cid' => $row['cat_id']), $row['cat_name']);
        $arr[$row['cat_id']]['style_icon'] = $row['style_icon']; //分类菜单图标
        $arr[$row['cat_id']]['cat_icon'] = $row['cat_icon']; //自定义图标

        $arr[$row['cat_id']]['nolinkname'] = $row['cat_name'];

    }
    return $arr;
}

//筛选获取分类/品牌/商品ID下的商品列表 by wu
function get_filter_goods_list($filter = array('goods_ids' => '', 'cat_ids' => '', 'brand_ids' => '', 'user_id' => 0), $size = 10, $page = 1, $sort = "sort_order", $order = "ASC", $warehouse_id = 0, $area_id = 0, $type = '')
{
    $leftJoin = '';
    $where = " AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 ";

    //商品
    if (isset($filter['goods_ids']) && !empty($filter['goods_ids'])) {
        $where .= " AND g.goods_id " .  db_create_in($filter['goods_ids']);
    }

    //分类
    if (isset($filter['cat_ids']) && !empty($filter['cat_ids'])) {
        $cat_ids = array();
        foreach (explode(',', $filter['cat_ids']) as $key => $val) {
            $cat_ids[] = "$val";
            $cat_keys = get_array_keys_cat($val);
            $cat_ids = array_merge($cat_ids, $cat_keys);
        }
        $cat_ids = array_unique($cat_ids);
        $where .= " AND g.cat_id " . db_create_in($cat_ids);
    }

    //品牌
    if (isset($filter['brand_ids']) && !empty($filter['brand_ids'])) {
        $where = " AND g.brand_id " . db_create_in($filter['brand_ids']);
    }

    //商家
    if (isset($filter['user_id'])) {
        $where .= " AND g.user_id = '" .$filter['user_id']. "' ";
    }

    //关联
    $shop_price = " IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price ";
    $promote_price = " IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price ";
    $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('warehouse_goods') . " AS wg ON g.goods_id = wg.goods_id AND wg.region_id = '$warehouse_id' ";
    $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('warehouse_area_goods') . " AS wag ON g.goods_id = wag.goods_id AND wag.region_id = '$area_id' ";
    $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' ";

    $count_where = '';
    if($type == 'goods'){
        $select = "g.goods_id";
        $count_where .= $where . " LIMIT " . $size;
    }else{
        $select  = "COUNT(*)";
        $count_where = $where;
    }

    //总数
    $sql = " SELECT  " .$select.
            " FROM " . $GLOBALS['ecs']->table('goods') . " AS g " .
            " WHERE 1 $count_where ";

    if($type == 'goods'){
        $record_count = count($GLOBALS['db']->getAll($sql));
    }else{
        $record_count = $GLOBALS['db']->getOne($sql);
    }

    $page_count = $record_count > 0 ? ceil($record_count / $size) : 1;
    //查询
    $sql = " SELECT g.goods_id, g.goods_name, g.goods_thumb, g.promote_start_date, g.promote_end_date, g.product_price, g.product_promote_price, " .
            $shop_price . "," . $promote_price .
            " FROM " . $GLOBALS['ecs']->table('goods') . " AS g " .
            $leftJoin .
            " WHERE 1 $where GROUP BY g.goods_id ORDER BY $sort $order ";
    //分页
    $start = ($page - 1) * $size;
    $res = $GLOBALS['db']->selectLimit($sql, $size, $start);

    //处理
    $arr = array();
    while ($row = $GLOBALS['db']->fetchRow($res)) {

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

        $arr[$row['goods_id']]['goods_id'] = $row['goods_id'];
        $arr[$row['goods_id']]['goods_name'] = $row['goods_name'];
        $arr[$row['goods_id']]['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
        $arr[$row['goods_id']]['url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
        $arr[$row['goods_id']]['shop_price'] = price_format($row['shop_price']);
        $arr[$row['goods_id']]['promote_price'] = ($promote_price) > 0 ? price_format($promote_price) : '';
    }

    return array('goods_list' => $arr, 'page_count' => $page_count, 'record_count' => $record_count);
}

/**
 * 重新获得品牌图片的地址
 * @return string   $url
 */
function get_brand_image_path($image = '')
{
    $url = empty($image) ? $GLOBALS['_CFG']['no_brand'] : $image;
    return $url;
}

/**
 * 创建已付款订单快照信息
 * @return string   $url
 */
function create_snapshot($order_id = 0) {
    $sql = " SELECT order_id FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE main_order_id = '$order_id' ";
    if ($order_ids = $GLOBALS['db']->getAll($sql)) {//是否有子订单
        foreach ($order_ids as $val) {
            $sql = "SELECT oi.order_sn, oi.user_id, og.ru_id, og.goods_id, og.goods_name, og.goods_sn, og.goods_attr, og.goods_attr_id, og.goods_price, og.goods_number,oi.shipping_fee, g.goods_weight, g.add_time, g.goods_desc, g.goods_img FROM " . $GLOBALS['ecs']->table('order_info') .
                    " AS oi LEFT JOIN " . $GLOBALS['ecs']->table('order_goods') . " AS og ON oi.order_id = og.order_id " .
                    " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON g.goods_id = og.goods_id " .
                    " WHERE oi.order_id = '$val[order_id]' ";
            $result = $GLOBALS['db']->getAll($sql);
            foreach ($result as $v) {
                insert_snapshot($v);
            }
        }
    } else {
        $sql = " SELECT oi.order_sn, oi.user_id, og.ru_id, og.goods_id, og.goods_name, og.goods_sn, og.goods_attr, og.goods_attr_id, og.goods_price, og.goods_number,oi.shipping_fee, g.goods_weight, g.add_time, g.goods_desc, g.goods_img FROM " . $GLOBALS['ecs']->table('order_info') .
                " AS oi LEFT JOIN " . $GLOBALS['ecs']->table('order_goods') . " AS og ON oi.order_id = og.order_id " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON g.goods_id = og.goods_id " .
                " WHERE oi.order_id = '$order_id' ";
        $result = $GLOBALS['db']->getAll($sql);
        foreach ($result as $v) {
            insert_snapshot($v);
        }
    }
}

/**
 * 将数据插入到
 * @return string   $url
 */
function insert_snapshot($arr = array())
 {
    $arr = is_array($arr) ? $arr : array();

    if ($arr) {
        $snapshot_info = array(
            'order_sn' => $arr['order_sn'],
            'user_id' => $arr['user_id'],
            'goods_id' => $arr['goods_id'],
            'goods_name' => $arr['goods_name'],
            'goods_sn' => $arr['goods_sn'],
            'shop_price' => $arr['goods_price'],
            'goods_number' => $arr['goods_number'],
            'shipping_fee' => $arr['shipping_fee'],
            'rz_shopName' => get_shop_name($arr['ru_id'], 1),
            'goods_weight' => $arr['goods_weight'],
            'add_time' => $arr['add_time'],
            'goods_attr' => $arr['goods_attr'],
            'goods_attr_id' => $arr['goods_attr_id'],
            'ru_id' => $arr['ru_id'],
            'goods_desc' => $arr['goods_desc'],
            'goods_img' => $arr['goods_img'],
            'snapshot_time' => gmtime()
        );

        return $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('trade_snapshot'), $snapshot_info, 'INSERT');
    }else{
        return 0;
    }
}

/**
 * 查找是否存在快照
 * @return string   $url
 */
function find_snapshot($order_sn = '', $goods_id = 0) {
    $sql = " SELECT trade_id FROM " . $GLOBALS['ecs']->table('trade_snapshot') . " WHERE order_sn = '$order_sn' AND goods_id = '$goods_id' ";
    return $GLOBALS['db']->getOne($sql);
}

/**
 * 预售数量
 */
function get_presale_num($order_id) {
    $sql = "SELECT pa.pre_num , og.goods_id FROM " . $GLOBALS['ecs']->table('presale_activity') . " AS pa"
            . " LEFT JOIN " . $GLOBALS['ecs']->table('order_goods') . " AS og ON pa.goods_id = og.goods_id "
            . " WHERE og.order_id = '$order_id'";
    $res = $GLOBALS['db']->getAll($sql);
    foreach ($res as $v) {
        $pre_num = $v['pre_num'];
        $pre_num += 1;
        $goods_id = $v['goods_id'];
        $sql = "update " . $GLOBALS['ecs']->table('presale_activity') . " set pre_num='$pre_num' WHERE goods_id = '$goods_id'";
        $GLOBALS['db']->query($sql);
    }
}



/**
 * 获取是否已经更新销量
 */
function is_update_sale($order_id) {
    $sql = "SELECT is_update_sale FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '$order_id'";
    return $GLOBALS['db']->getOne($sql, true);
}

/**
*更新商品销量
*/
function get_goods_sale($order_id = 0, $order = array()) {

    if(empty($order)){
        $sql = "SELECT order_id, pay_status, shipping_status FROM " .$GLOBALS['ecs']->table('order_info'). " WHERE order_id = '$order_id' LIMIT 1";
        $order = $GLOBALS['db']->getRow($sql);
    }

    $is_volume = 0;
    if ($GLOBALS['_CFG']['sales_volume_time'] == SALES_PAY && $order['pay_status'] == PS_PAYED) {
        $is_volume = 1;
    }elseif($GLOBALS['_CFG']['sales_volume_time'] == SALES_SHIP && $order['shipping_status'] == SS_SHIPPED){
        $is_volume = 1;
    }

    if ($is_volume == 1) {

        $is_update_sale = is_update_sale($order['order_id']);
        if ($is_update_sale < 1) {
            $sql = "SELECT goods_id,goods_number FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '" . $order['order_id'] . "'";
            $order_res = $GLOBALS['db']->getAll($sql);
			$where = " AND (select count(*) from " .$GLOBALS['ecs']->table('order_info'). " as oi2 where oi2.main_order_id = o.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示
            foreach ($order_res as $idx => $val) {
                $sql = 'SELECT SUM(og.goods_number) as goods_number ' .
                        'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g, ' .
                        $GLOBALS['ecs']->table('order_info') . ' AS o, ' .
                        $GLOBALS['ecs']->table('order_goods') . ' AS og ' .
                        " WHERE g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 AND og.order_id = o.order_id AND og.goods_id = g.goods_id " .
                        " AND g.goods_id = '" . $val['goods_id'] . "'" . $where;
                $sales_volume = $GLOBALS['db']->getOne($sql);
                $sql = "UPDATE " . $GLOBALS['ecs']->table('goods') . " SET sales_volume = '$sales_volume' WHERE goods_id ='" . $val['goods_id'] . "'";
                $GLOBALS['db']->query($sql);
            }
        }
    }
}

/**
 * 记录会员操作日志
 * @param   int     $user_id        用户id
 * @param   string  $change_desc    变动说明
 * @param   int     $change_type    变动类型：参见常量文件
 * @return  void
 */
function users_log_change($user_id,$change_type = USER_LOGIN)
{
    $ipCity = new ipCity();
    $change_city = $ipCity->getCity(real_ip());
    $admin_id = 0;
    if($_SESSION['admin_id'] > 0){
        $admin_id = $_SESSION['admin_id'];
    }
    /* 插入操作记录 */
    $users_log = array(
        'user_id'       => $user_id,
        'change_time'   => gmtime(),
        'change_type'   => $change_type,
        'ip_address'    => real_ip(),
        'change_city'   => $change_city,
        'admin_id'      => $admin_id,
        'logon_service' => 'pc'
    );
    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('users_log'), $users_log, 'INSERT');
}
/**
 * 判断管理员修改内容，划分修改类
 * @return  void
 */
function users_log_change_type($old_user = array(),$other = array(),$user_id = 0)
{
    //修改邮箱
    if($old_user['old_email'] != $other['email']){
        users_log_change($user_id,USER_EMAIL);
    }
    //修改信用额度
    if($old_user['old_credit_line'] != $other['credit_line']){
        users_log_change($user_id,USER_LINE);
    }
    //修改密码
    if($old_user['password']){
        users_log_change($user_id,USER_LPASS);
    }
    //修改手机
    if($old_user['old_mobile_phone'] != $other['mobile_phone']){
        users_log_change($user_id,USER_PHONE);
    }
    //其他会员信息
    if($old_user['old_user_rank'] != $other['user_rank'] || $old_user['old_sex'] != $other['sex']
            || $old_user['old_birthday'] != $other['birthday'] || $old_user['old_msn'] != $other['msn']
            || $old_user['old_qq'] != $other['qq'] || $old_user['old_office_phone'] != $other['office_phone']
            || $old_user['old_home_phone'] != $other['home_phone'] || $old_user['old_passwd_answer'] != $other['passwd_answer']
            || $old_user['old_sel_question'] != $other['sel_question'])
    {
        users_log_change($user_id,USER_INFO);
    }
}
/**
 * 设置表单提交token
 * @param   string  $cookie    cookie名称
 * @return  void
 */
function set_prevent_token($cookie = ''){
    if($cookie){
        unset($_COOKIE[$cookie]);

        $sc_rand = rand(1000, 9999);
        $sc_guid = sc_guid();

        $prevent_cookie = MD5($sc_guid . "-" . $sc_rand);
        setcookie($cookie, $prevent_cookie, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

        $GLOBALS['smarty']->assign('sc_guid', $sc_guid);
        $GLOBALS['smarty']->assign('sc_rand', $sc_rand);
    }
}
/**
 * 根据token判断表单是否重复提交
 * @param   string  $cookie    cookie名称
 * @return  void
 */
function get_prevent_token($cookie = ''){
    $is_prevent = 0;
    if($cookie){
        $sc_rand = isset($_POST['sc_rand']) && !empty($_POST['sc_rand']) ? dsc_addslashes(trim($_POST['sc_rand'])) : '';
        $sc_guid = isset($_POST['sc_guid']) && !empty($_POST['sc_guid']) ? dsc_addslashes(trim($_POST['sc_guid'])) : '';
        $prevent_cookie = MD5($sc_guid . "-" . $sc_rand);
        if (!empty($sc_guid) && !empty($sc_rand) && isset($_COOKIE[$cookie])) {

            if (!empty($_COOKIE[$cookie])) {
                if (!($_COOKIE[$cookie] == $prevent_cookie)) {
                    $is_prevent = 1;
                }
            } else {
                $is_prevent = 1;
            }
        }
    }
    return $is_prevent;
}

/*
* 检查是否存在主订单 如果子订单均完成付款 改变主订单状态
*/
function check_main_order_status($order_id) {
    $sql = " SELECT main_order_id FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '$order_id' ";
    $main_order_id = $GLOBALS['db']->getOne($sql);
    if ($main_order_id) {
        $sql = " SELECT order_id FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE main_order_id = '$main_order_id' ";
        $order_ids = $GLOBALS['db']->getAll($sql);

        $order_status = OS_CONFIRMED;
        $pay_status = PS_PAYED;

        foreach ($order_ids as $v) {
            $sql = " SELECT order_status, pay_status FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '" . $v['order_id'] . "' ";
            $order_info = $GLOBALS['db']->getRow($sql);
            if ($order_info['order_status'] != OS_CONFIRMED) {//有待细化 目前如果有主订单 下面子订单不是已确认 就设置为未确认
                $order_status = OS_UNCONFIRMED;
            }
            if ($order_info['pay_status'] != PS_PAYED) {//有待细化 目前如果有主订单 下面子订单不是已付款 就设置为未付款
                $pay_status = PS_UNPAYED;
            }
        }
        $sql = " UPDATE " . $GLOBALS['ecs']->table('order_info') . " SET order_status = '$order_status', pay_status = '$pay_status' WHERE order_id = '$main_order_id' ";
        $GLOBALS['db']->query($sql);
    }
}

//获取免邮券不包邮地区
function get_cou_region_list($cou_id = 0){
    $arr = array('free_value_name'=>'');
    $sql = "SELECT region_list FROM" . $GLOBALS['ecs']->table('coupons_region') . "WHERE cou_id = '$cou_id' LIMIT 1";
    $arr['free_value'] = $GLOBALS['db']->getOne($sql);
    $sql = "SELECT region_name FROM" . $GLOBALS['ecs']->table('region') . "WHERE region_id" . db_create_in($arr['free_value']);
    $region_list = $GLOBALS['db']->getCol($sql);
    if ($region_list) {
        $arr['free_value_name'] = implode(",", $region_list);
    }
    return $arr;
}

//获取推荐
function get_adv_child($ad_arr) {
    $in_ad_arr = substr($ad_arr,0,strlen($ad_arr)-1);
    if (!$in_ad_arr) {
        return [];
    }
    $ad_child = get_ad_posti_child($in_ad_arr);
    return  $ad_child;
}

/**
 * 去除字符串末尾的PKCS7 Padding
 * @param string $source    带有padding字符的字符串
 */
function stripPKSC7Padding($source){
    $block = mcrypt_get_block_size('tripledes', 'cbc');
    $pad = ord(substr($source, -1));
//    if ($pad < 1 || $pad > $block)
//        $pad = 0;
    if ($pad < 1) {
        $pad = 0;
    }
    return substr($source, 0, (strlen($source) - $pad));
//    $block = mcrypt_get_block_size('tripledes', 'cbc');
//    $char = substr($source, -1, 1);
//    $num = ord($char);
//    if($num > 8){
//        return $source;
//    }
//
//    $len = strlen($source);
//    for($i = $len - 1; $i >= $len - $num; $i--){
//        if(ord(substr($source, $i, 1)) != $num){
//            return $source;
//        }
//    }
//    $source = substr($source, 0, -$num);
//    return $source;
}

/**
 * 为字符串添加PKCS7 Padding
 * @param string $source    源字符串
*/
function addPKCS7Padding($source){
    $block = mcrypt_get_block_size('tripledes', 'cbc');
    $pad = $block - (strlen($source) % $block);
    if ($pad <= $block) {
        $char = chr($pad);
        $source .= str_repeat($char, $pad);
    }
    return $source;
}

 /**
 * 解密
 * @param String input 解密的字符串
 * @param String key   解密的key
 * @return String
 */
function aesDecrypt($sStr) {
    $key = 'contentWindowHig';
    $iv = 'contentDocuments';
    $decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, md5($key), base64_decode($sStr), MCRYPT_MODE_CBC, $iv);
    return stripPKSC7Padding($decrypted);
}

/**
 * 解密
 * @param String input 解密的字符串
 * @param String key   解密的key
 * @return String
 */
function aesEncrypt($sStr) {
    $key = 'contentWindowHig';
    $iv = 'contentDocuments';
    $sStr = addPKCS7Padding($sStr);
    $encrypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, md5($key), $sStr, MCRYPT_MODE_CBC, $iv);
    return $encrypted = base64_encode($encrypted);
}

/**
 * 发货快递记录
 * @param $order_id 订单ID
 * @param $invoice_no 发货单号
 * @param $shipping_fee
 * @param $shipping_time
 * @param $shipping_status
 */
function deliver_order($order_id, $invoice_no, $shipping_id, $shipping_name, $shipping_fee, $shipping_time, $shipping_status) {
    $data = [];
    $data['order_id'] = $order_id;
    $data['invoice_no'] = $invoice_no;
    $data['shipping_id'] = $shipping_id;
    $data['shipping_name'] = $shipping_name;
    $data['shipping_fee'] = $shipping_fee;
    $data['shipping_time'] = $shipping_time;
    $data['shipping_status'] = $shipping_status;

    $sql = "SELECT order_id FROM " . $GLOBALS['ecs']->table('kuaidi_bill_order') .
        " WHERE order_id = '$order_id' LIMIT 1";
    $row = $GLOBALS['db']->getRow($sql);

    if ($row) {
        if ($row['is_kuaidi_update']) {
            return;
        }
        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('kuaidi_bill_order'), $data, 'UPDATE', 'invoice_id=\'' . $row['invoice_id'] . '\'');
    } else {
        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('kuaidi_bill_order'), $data, 'INSERT');
    }
}

/**
 * 保存发货公司更新数据
 * @param $invoice_no
 * @param $weight
 * @param $price
 * @param int $add
 */
function deliver_order_kuaidi_weight_price ($kuaidi_id, $invoice_no, $weight, $price, $add=0) {
    $seller_shipping_fee = number_format(floatval($price) + intval($add), 2);
    $kuaidi_shipping_fee = number_format(floatval($price), 2);

    $sql = " UPDATE " . $GLOBALS['ecs']->table('kuaidi_bill_order') . " SET seller_shipping_fee = $seller_shipping_fee, kuaidi_shipping_fee = '$kuaidi_shipping_fee', kuaidi_id = '$kuaidi_id', weight = '$weight', is_kuaidi_update = 1 WHERE invoice_no = '$invoice_no'" ;
    $GLOBALS['db']->query($sql);
}

/**
 * 确认收货 快递公司金额变化
 * @param $orde_id
 */
function order_confirm_kuaidi_account_change($order_id) {
    $sql = "SELECT order_id, shipping_fee, kuaidi_shipping_fee, kuaidi_id, is_kuaidi_update" .
        " FROM ".$GLOBALS['ecs']->table('order_info') ." WHERE order_id = '$order_id'";
    $order = $GLOBALS['db']->GetRow($sql);

    $should_amount = $order['is_kuaidi_update'] == 1 ? $order['kuaidi_shipping_fee'] : 0;

    //确认收货订单
    log_kuaidi_account_change($order['kuaidi_id'], $should_amount, 0);
    kuaidi_account_log($order['kuaidi_id'], $should_amount, 0, '确认收货', 2);
}

/**
 * 确认收货
 * @param $orde_id
 */
function order_confirm_change($order_id, $insert_id) {
    //商家金额变化
    order_confirm_seller_account_change($order_id, $insert_id);


    //商家快递扣分
    $sql = "SELECT invoice_id, order_id, shipping_fee, kuaidi_shipping_fee, is_kuaidi_update, is_jiesuan" .
        " FROM ".$GLOBALS['ecs']->table('kuaidi_bill_order') ." WHERE order_id = '$order_id'";
    $order = $GLOBALS['db']->GetRow($sql);

    $confirm_take_time = gmtime();

    if (!$order['is_jiesuan']) {
        //未结算
        //商家扣除快递费
        order_confirm_seller_kuaidi_account_change($order_id);

        //快递公司金额变化
        order_confirm_kuaidi_account_change($order_id);

        $sql = "UPDATE " . $GLOBALS['ecs']->table('kuaidi_bill_order') .
            " SET is_jiesuan = 1, confirm_take_time = $confirm_take_time".
            " WHERE invoice_id = '".$order['invoice_id']."' LIMIT 1";
        $GLOBALS['db']->query($sql);
    }


}

/**
 * 快递公司帐户变动
 *
 * @access  public
 * @param   int     $cat_id     分类的ID
 * @return  mix
 */
function log_kuaidi_account_change($id, $available_money = 0, $frozen_money = 0){
    if($available_money || $frozen_money){
        /* 更新用户信息 */
        $sql = "UPDATE " . $GLOBALS['ecs']->table('kuaidi') .
            " SET available_money = available_money + ('$available_money')," .
            " frozen_money = frozen_money + ('$frozen_money')" .
            " WHERE id = '$id' LIMIT 1";
        $GLOBALS['db']->query($sql);
        return $GLOBALS['db']->affected_rows();
    }
}

/**
 * 快递公司帐户变动记录
 *
 * @access  public
 * @param   int     $cat_id     分类的ID
 * @return  mix
 */
function kuaidi_account_log($id, $available_money = 0, $frozen_money = 0, $change_desc, $change_type = 1){
    //$change_type 0提现  1提现冻结  2结算 3管理员调节 4解冻
    if ($available_money || $frozen_money) {
        $log = array(
            'kuaidi_id' => $id,
            'available_money' => $available_money,
            'frozen_money' => $frozen_money,
            'change_time' => gmtime(),
            'change_desc' => $change_desc,
            'change_type' => $change_type
        );
        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('kuaidi_account_log'), $log, 'INSERT');
    }
}

/**
 * 整单更新金额
 */

function order_refound_change($order_id) {
    $sql = "SELECT invoice_id, order_id, shipping_fee, kuaidi_shipping_fee, is_kuaidi_update, is_jiesuan" .
        " FROM ".$GLOBALS['ecs']->table('kuaidi_bill_order') ." WHERE order_id = '$order_id'";
    $order = $GLOBALS['db']->GetRow($sql);

    //整单退款  添加商家账单
    $seller_id = $GLOBALS['db']->getOne("SELECT ru_id FROM " .$GLOBALS['ecs']->table('order_goods'). " WHERE order_id = '" .$order['order_id']. "'", true);
    $value_card = $GLOBALS['db']->getOne("SELECT use_val FROM " .$GLOBALS['ecs']->table('value_card_record'). " WHERE order_id = '" .$order['order_id']. "'", true);

    $return_amount = get_order_return_amount($order['order_id']);

    $sql = "SELECT order_id, user_id, order_sn , order_status, shipping_status, pay_status, auto_delivery_time, add_time, pay_time, " .
        "order_amount, goods_amount, tax, invoice_type, shipping_fee, seller_shipping_fee, insure_fee, pay_fee, pack_fee, card_fee, shipping_time, " .
        "bonus, integral_money, coupons, discount, money_paid, surplus, confirm_take_time, tax_id, extension_code " .
        "FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '" .$order['order_id']. "' LIMIT 1";

    $orderInfo = $GLOBALS['db']->getRow($sql);

    $other = array(
        'user_id'               => $orderInfo['user_id'],
        'seller_id'             => $seller_id,
        'order_id'              => $orderInfo['order_id'],
        'order_sn'              => $orderInfo['order_sn'],
        'order_status'          => $orderInfo['order_status'],
        'shipping_status'       => SS_RECEIVED,
        'pay_status'            => $orderInfo['pay_status'],
        'order_amount'          => $orderInfo['order_amount'],
        'return_amount'         => $orderInfo['goods_amount'],
        'goods_amount'          => $orderInfo['goods_amount'],
        'tax'                   => $orderInfo['tax'],
        'tax_id'                => $orderInfo['tax_id'],
        'invoice_type'          => $orderInfo['invoice_type'],
        'shipping_fee'          => $orderInfo['seller_shipping_fee'],
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
        'confirm_take_time'     => gmtime(),
        'return_status'         => 0
    );

    //商家比例
    $commission = common_get_seller_commission_info($seller_id);
    $other = [];
    $other['proportion'] = $commission['percent_value'];
    $other['commission_model'] = $commission['commission_model'];
    $other['should_amount'] = 0;
    $other['gain_commission'] = 0;

    if($seller_id){
        $insert_id = get_order_bill_log($other);
    }

    if (!$order['is_jiesuan']) {
        //未结算
        //商家扣除快递费
        order_refound_seller_account_change($order_id);

        //快递公司添加快递费
        order_refound_kuaidi_account_change($order_id);

        $sql = "UPDATE " . $GLOBALS['ecs']->table('kuaidi_bill_order') .
            " SET is_jiesuan = 1, confirm_take_time = $confirm_take_time".
            " WHERE invoice_id = '".$order['invoice_id']."' LIMIT 1";
        $GLOBALS['db']->query($sql);
    }

}

/**
 * 全部退货退款 直接添加快递金额
 * @param $order_id
 */
function order_refound_kuaidi_account_change($order_id) {
    $sql = "SELECT order_id, shipping_fee, kuaidi_shipping_fee, kuaidi_id, is_kuaidi_update" .
        " FROM ".$GLOBALS['ecs']->table('order_info') ." WHERE order_id = '$order_id'";
    $order = $GLOBALS['db']->GetRow($sql);

    $should_amount = $order['is_kuaidi_update'] == 1 ? $order['kuaidi_shipping_fee'] : 0;

    //全部退款后
    log_kuaidi_account_change($order['kuaidi_id'], $should_amount, 0);
    kuaidi_account_log($order['kuaidi_id'], $should_amount, 0, '退货', 2);
}

/**
 * 全部退货退款 直接扣除商家金额
 * @param $order_id
 */
function order_refound_seller_account_change($order_id) {
    $sql = "SELECT order_id, shipping_fee, seller_shipping_fee, is_kuaidi_update" .
        " FROM ".$GLOBALS['ecs']->table('order_info') ." WHERE order_id = '$order_id'";
    $order = $GLOBALS['db']->GetRow($sql);

    $should_amount = $order['is_kuaidi_update'] == 1 ? $order['seller_shipping_fee'] : 0;

    $seller_id = $GLOBALS['db']->getOne("SELECT ru_id FROM " .$GLOBALS['ecs']->table('order_goods'). " WHERE order_id = '$order_id'", true);

    //全部退款扣除
    log_seller_account_change($seller_id, -$should_amount, 0);
    merchants_account_log($seller_id, -$should_amount, 0, '退货扣除运费', 2);
}

/**
 * 确认收货 商家金额
 * @param $order_id
 */
function order_confirm_seller_kuaidi_account_change($order_id) {
    $sql = "SELECT order_id, shipping_fee, seller_shipping_fee, is_kuaidi_update" .
        " FROM ".$GLOBALS['ecs']->table('order_info') ." WHERE order_id = '$order_id'";
    $order = $GLOBALS['db']->GetRow($sql);

    $should_amount = $order['is_kuaidi_update'] == 1 ? $order['seller_shipping_fee'] : 0;

    $seller_id = $GLOBALS['db']->getOne("SELECT ru_id FROM " .$GLOBALS['ecs']->table('order_goods'). " WHERE order_id = '$order_id'", true);

    //确认收货订单
    log_seller_account_change($seller_id, -$should_amount, 0);
    merchants_account_log($seller_id, -$should_amount, 0, '确认收货扣除运费', 2);
}

/**
 * 判断是否是所有商品被退
 */
function order_refound_is_all($goods_number_return = 0, $rec_id = 0, $order_goods = [], $order_info = [], $refund_amount = 0) {
    $goods_number = 0;
    $goods_count = count($order_goods);
    $i = 1;

    foreach ($order_goods as $k => $v) {
        if ($rec_id == $v['rec_id']) {
            $goods_number = $v['goods_number'];
        }
        $sql = "SELECT ret_id FROM" . $GLOBALS['ecs']->table('order_return') . " WHERE rec_id = '" . $v['rec_id'] . "' AND order_id = '" . $v['order_id'] . "' AND refound_status = 1";
        if ($GLOBALS['db']->getOne($sql) > 0) {
            $i++;
        }
    }
    if ($goods_number > $goods_number_return || $goods_count > $i) {
        return false;
    } else {
        return true;
    }
}

//商家佣金比例信息
function common_get_seller_commission_info($ru_id = 0){
    $sql = "SELECT ms.server_id, ms.commission_model, ms.suppliers_percent, mp.percent_value FROM " .$GLOBALS['ecs']->table('merchants_server'). " AS ms " .
        "LEFT JOIN " .$GLOBALS['ecs']->table('merchants_percent'). " AS mp ON ms.suppliers_percent = mp.percent_id " .
        "WHERE ms.user_id = '$ru_id'";

    return $GLOBALS['db']->getRow($sql);
}

/**
 * 平台分类佣金比例模式
 * 返回结果
 */
function common_get_cat_gain_should_amount2($value, $commission_rate=0, $type=1){
    if ($value['goods_amount'] <= 0) {
        $value['goods_amount'] = 1;
    }

    $value['return_amount'] = $value['return_amount'] + $value['return_shippingfee'];

    if ($value['total_fee'] == $value['return_amount']) {
        $gain_commission = 0;
        $should_amount = 0;
    } else {
        $should_amount = $commission_rate * ($value['commission_total_fee'] - $value['return_amount']) / $value['goods_amount'] + $value['shipping_fee'];
        $gain_commission = $value['commission_total_fee'] - $should_amount - $value['drp_money'] - $value['return_amount'];
    }

    $arr = array(
        'gain_commission' => $gain_commission,
        'should_amount' => $should_amount
    );

    return $arr;
}

//生产周期快到通知
function order_production_cycle_notification() {
    $time = gmtime();

    //拼单
    $sql = 'SELECT ga.* FROM ' . $GLOBALS['ecs']->table('goods_activity') . "AS ga".
        " WHERE ga.review_status = 3 AND end_time + (production_cycle - 1 ) * 24 * 60 * 60 < ".$time;

    $activitys = $GLOBALS['db']->GetAll($sql);

    foreach ($activitys as $k => $v) {
        $user_id = $v['user_id']; //商家用户ID

        $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('notification_activity_log') ." WHERE extension_id = " . $v['act_id'] . ' AND extension_code = "group_buy" AND status = 0';
        if (!$GLOBALS['db']->GetOne($sql)) {
            $notification_id = add_notification(['user_id'=>$user_id, 'user_type' => 1, 'type' => 'system', '拼单：'.$v['act_name'] . '生产周期快到期了，请注意！', 'attachment' => '']);

            add_notification_activity_log([
                'extension_id' => $v['act_id'], //子订单ID,
                'extension_code' => 'group_buy', //0 平台 1制造商 2 采购商 3 会员
                'notification_id'  => $notification_id,
            ]);
        }

    }

    //预订
    $sql = 'SELECT ga.* FROM ' . $GLOBALS['ecs']->table('presale_activity') . "AS ga".
        " WHERE ga.review_status = 3 AND pay_end_time + (production_cycle - 1 ) * 24 * 60 * 60 < ".$time;
    $activitys = $GLOBALS['db']->GetAll($sql);

    foreach ($activitys as $k => $v) {
        $user_id = $v['user_id']; //商家用户ID

        $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('notification_activity_log') ." WHERE extension_id = " . $v['act_id'] . ' AND extension_code = "presale" AND status = 0';
        if (!$GLOBALS['db']->GetOne($sql)) {
            $notification_id = add_notification(['user_id'=>$user_id, 'user_type' => 1, 'type' => 'system', '预订：'.$v['act_name'] . '生产周期快到期了，请注意！', 'attachment' => '']);

            add_notification_activity_log([
                'extension_id' => $v['act_id'], //子订单ID,
                'extension_code' => 'group_buy', //0 平台 1制造商 2 采购商 3 会员
                'notification_id'  => $notification_id,
            ]);
        }
    }

    //样品
    $sql = 'SELECT oi.order_id, ga.* FROM ' . $GLOBALS['ecs']->table('order_info') . "AS oi inner join ". $GLOBALS['ecs']->table('sample_activity') . ' as ga ON ga.act_id = oi.extension_id AND oi.extension_code = \'sample\' where oi.pay_time + (ga.production_cycle - 1 ) * 24 * 60 * 60 < '.$time . ' AND oi.shipping_status not '.db_create_in([SS_SHIPPED, OS_SHIPPED_PART, SS_RECEIVED]) . ' AND oi.pay_status '.db_create_in([PS_PAYED]);
    $orders = $GLOBALS['db']->GetAll($sql);

    foreach ($orders as $k => $v) {
        $user_id = $v['user_id']; //商家用户ID

        $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('notification_activity_log') ." WHERE extension_id = " . $v['act_id'] . ' AND extension_code = "sample" AND status = 0 AND order_id = '.$v['order_id'];
        if (!$GLOBALS['db']->GetOne($sql)) {
            $notification_id = add_notification(['user_id'=>$user_id, 'user_type' => 1, 'type' => 'system', '样品：'.$v['act_name'] . '生产周期快到期了，请注意！', 'attachment' => '']);

            add_notification_activity_log([
                'extension_id' => $v['act_id'], //子订单ID,
                'extension_code' => 'group_buy', //0 平台 1制造商 2 采购商 3 会员
                'notification_id'  => $notification_id,
                'order_id' => $v['order_id']
            ]);
        }
    }
}

/**
 * 活动通知
 * @param $v
 */
function add_notification_activity_log($v) {
    $data = array(
        'extension_id' => $v['extension_id'], //子订单ID,
        'extension_code' => $v['extension_code'], //0 平台 1制造商 2 采购商 3 会员
        'notification_id'  => $v['notification_id'],
        'add_time' => gmtime(),
        'status'    => 0
    );
    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('notification_activity_log'), $data, 'INSERT');
}

/**
 * 通知
 */
function add_notification($v){
    $data = array(
        'user_id' => $v['user_id'], //子订单ID,
        'user_type' => $v['user_type'], //0 平台 1制造商 2 采购商 3 会员
        'type'  => $v['type'],
        'content' => $v['content'],
        'attachment' => $v['attachment'],
        'add_time' => gmtime(),
        'status'    => 0
    );
    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('notification'), $data, 'INSERT');
    $insert_id = $GLOBALS['db']->insert_id();
    return $insert_id;
}
?>
