<?php

use App\Extensions\Http;
use App\Extensions\Util;

/**
 * 创建像这样的查询: "IN('a','b')";
 *
 * @access   public
 * @param    mix $item_list 列表数组或字符串
 * @param    string $field_name 字段名称
 *
 * @return   void
 */
function db_create_in($item_list, $field_name = '', $not = '')
{
    if (!empty($not)) {
        $not = " " . $not;
    }

    if (empty($item_list)) {
        return $field_name  . $not . " IN ('') ";
    } else {
        if (!is_array($item_list)) {
            $item_list = explode(',', $item_list);
        }
        $item_list = array_unique($item_list);
        $item_list_tmp = '';
        foreach ($item_list as $item) {
            if ($item !== '') {
                $item = addslashes($item);
                $item_list_tmp .= $item_list_tmp ? ",'$item'" : "'$item'";
            }
        }
        if (empty($item_list_tmp)) {
            return $field_name . $not . " IN ('') ";
        } else {
            return $field_name . $not . ' IN (' . $item_list_tmp . ') ';
        }
    }
}

/**
 * 验证输入的邮件地址是否合法
 *
 * @access  public
 * @param   string $email 需要验证的邮件地址
 *
 * @return bool
 */
function is_email($user_email)
{
    $chars = "/^([a-z0-9+_]|\\-|\\.)+@(([a-z0-9_]|\\-)+\\.)+[a-z]{2,6}\$/i";
    if (strpos($user_email, '@') !== false && strpos($user_email, '.') !== false) {
        if (preg_match($chars, $user_email)) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

/**
 * @param $mobile
 * @return bool
 */
function is_mobile($mobile)
{
    if (!is_numeric($mobile)) {
        return false;
    }
    return preg_match('/^1(3[0-9]|4[57]|5[0-35-9]|7[0135678]|8[0-9])\d{8}$/', $mobile) ? true : false;
}

/**
 * 检查是否为一个合法的时间格式
 *
 * @access  public
 * @param   string $time
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
    if ($GLOBALS['db']->queryTime == '') {
        $query_time = 0;
    } else {
        if (PHP_VERSION >= '5.0.0') {
            $query_time = number_format(microtime(true) - $GLOBALS['db']->queryTime, 6);
        } else {
            list($now_usec, $now_sec) = explode(' ', microtime());
            list($start_usec, $start_sec) = explode(' ', $GLOBALS['db']->queryTime);
            $query_time = number_format(($now_sec - $start_sec) + ($now_usec - $start_usec), 6);
        }
    }
    $GLOBALS['smarty']->assign('query_info', sprintf($GLOBALS['_LANG']['query_info'], $GLOBALS['db']->queryCount, $query_time));

    /* 内存占用情况 */
    if ($GLOBALS['_LANG']['memory_info'] && function_exists('memory_get_usage')) {
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
 * @param   array $arr 地区数组 *
 * @return  void
 */
function region_result($parent, $sel_name, $type)
{
    global $cp;

    $arr = get_regions($type, $parent);
    foreach ($arr as $v) {
        $region =& $cp->add_node('region');
        $region_id =& $region->add_node('id');
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
 * @param   int $area_id 配送区域ID
 *
 * @return array;
 */
function get_shipping_config($area_id)
{
    /* 获得配置信息 */
    $sql = 'SELECT configure FROM ' . $GLOBALS['ecs']->table('shipping_area') . " WHERE shipping_area_id = '$area_id'";
    $cfg = $GLOBALS['db']->GetOne($sql);

    if ($cfg) {
        /* 拆分成配置信息的数组 */
        $arr = unserialize($cfg);
    } else {
        $arr = [];
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
    if ($cls != null) {
        return $cls;
    }
    include_once(ADDONS_PATH . 'integrates/' . $GLOBALS['_CFG']['integrate_code'] . '.php');
    $cfg = unserialize($GLOBALS['_CFG']['integrate_config']);
    $cls = new $GLOBALS['_CFG']['integrate_code']($cfg);

    return $cls;
}

/**
 * 获得指定分类下的子分类的数组
 *
 * @access  public
 * @param   int $cat_id 分类的ID
 * @param   int $selected 当前选中分类的ID
 * @param   boolean $re_type 返回的类型: 值为真时返回下拉列表,否则返回数组
 * @param   int $level 限定返回的级数。为0时返回所有级数
 * @param   int $is_show_all 如果为true显示所有分类，如果为false隐藏不可见分类。
 * @return  mix
 */
function cat_list($cat_id = 0, $type = 0, $getrid = 0, $table = 'category', $seller_shop_cat = [], $cat_level = 0, $user_id = 0)
{
    if ($getrid == 0) {
        $select = ', cat_name, cat_alias_name';
        if ($table == 'merchants_category') {
            $select .= ', user_id';
        }
    } else {
        $select = '';
    }

    $where = '';
    if ($seller_shop_cat) {
        if ($seller_shop_cat['parent'] && $seller_shop_cat['parent'] && $cat_level < 3) {
            $seller_shop_cat['parent'] = get_del_str_comma($seller_shop_cat['parent']);
            $where .= " AND cat_id IN(" . $seller_shop_cat['parent'] . ")";
        }
    }

    if ($table == 'merchants_category' && $user_id) {
        $where .= " AND user_id = '$user_id'";
    }

    $sql = "SELECT cat_id $select FROM " . $GLOBALS['ecs']->table($table) . " WHERE parent_id = '$cat_id' $where AND is_show = 1 ORDER BY sort_order ASC, cat_id ASC";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = [];

    if ($res) {
        foreach ($res as $key => $row) {
            if ($getrid == 0) {
                $row['cat_name'] = htmlspecialchars(addslashes(str_replace("\r\n", "", $row['cat_name'])), ENT_QUOTES);//特殊字符处理
                $row['level'] = 0;
                $row['select'] = str_repeat('&nbsp;', $row['level'] * 4);
                $arr[$row['cat_id']] = $row;

                if ($table == 'merchants_category') {
                    $build_uri = [
                        'cid' => $row['cat_id'],
                        'urid' => $row['user_id'],
                        'append' => $row['cat_name']
                    ];

                    $domain_url = get_seller_domain_url($row['user_id'], $build_uri);
                    $arr[$row['cat_id']]['url'] = $domain_url['domain_name'];
                } else {
                    $arr[$row['cat_id']]['url'] = build_uri('category', ['cid' => $row['cat_id']], $row['cat_name']);
                }
            } else {
                $arr[$row['cat_id']]['cat_id'] = $row['cat_id'];
            }

            if ($type) {
                $arr[$row['cat_id']]['child_tree'] = get_child_tree_pro($row['cat_id'], 0, $table, $getrid, $user_id);
            }
        }
    }

    return $arr;
}

//循环加载 start
function flush_echo($data)
{
    ob_end_flush();
    ob_implicit_flush(true);
    echo $data;
}

function show_js_message($message, $ext = 0)
{
    flush_echo('<script type="text/javascript">showmessage(\'' . addslashes($message) . '\',' . $ext . ');</script>' . "\r\n");
}

function arr_stime()
{
    return time() + microtime();
}

function sc_timer($stime)
{
    $etime = time() + microtime();
    $pass_time = sprintf("%.2f", $etime - $stime);

    //消耗时间
    return $pass_time;
}

//循环加载 end

/**
 * 载入配置信息
 *
 * @access  public
 * @return  array
 */
function load_ecsconfig()
{
    $arr = [];

    $data = read_static_cache('shop_config');
    if ($data === false) {
        $sql = 'SELECT code, value FROM ' . $GLOBALS['ecs']->table('shop_config') . ' WHERE parent_id > 0';
        $res = $GLOBALS['db']->getAll($sql);

        foreach ($res as $row) {
            $arr[$row['code']] = $row['value'];
        }

        /* 对数值型设置处理 */
        $arr['watermark_alpha'] = intval($arr['watermark_alpha']);
        $arr['market_price_rate'] = floatval($arr['market_price_rate']);
        $arr['integral_scale'] = floatval($arr['integral_scale']);
        //$arr['integral_percent']     = floatval($arr['integral_percent']);
        $arr['cache_time'] = intval($arr['cache_time']);
        $arr['thumb_width'] = intval($arr['thumb_width']);
        $arr['thumb_height'] = intval($arr['thumb_height']);
        $arr['image_width'] = intval($arr['image_width']);
        $arr['image_height'] = intval($arr['image_height']);
        $arr['best_number'] = !empty($arr['best_number']) && intval($arr['best_number']) > 0 ? intval($arr['best_number']) : 3;
        $arr['new_number'] = !empty($arr['new_number']) && intval($arr['new_number']) > 0 ? intval($arr['new_number']) : 3;
        $arr['hot_number'] = !empty($arr['hot_number']) && intval($arr['hot_number']) > 0 ? intval($arr['hot_number']) : 3;
        $arr['promote_number'] = !empty($arr['promote_number']) && intval($arr['promote_number']) > 0 ? intval($arr['promote_number']) : 3;
        $arr['top_number'] = intval($arr['top_number']) > 0 ? intval($arr['top_number']) : 10;
        $arr['history_number'] = intval($arr['history_number']) > 0 ? intval($arr['history_number']) : 5;
        $arr['comments_number'] = intval($arr['comments_number']) > 0 ? intval($arr['comments_number']) : 5;
        $arr['article_number'] = intval($arr['article_number']) > 0 ? intval($arr['article_number']) : 5;
        $arr['page_size'] = intval($arr['page_size']) > 0 ? intval($arr['page_size']) : 10;
        $arr['bought_goods'] = intval($arr['bought_goods']);
        $arr['goods_name_length'] = intval($arr['goods_name_length']);
        $arr['top10_time'] = intval($arr['top10_time']);
        $arr['goods_gallery_number'] = intval($arr['goods_gallery_number']) ? intval($arr['goods_gallery_number']) : 5;
        $arr['no_picture'] = !empty($arr['no_picture']) ? str_replace('../', './', $arr['no_picture']) : 'images/no_picture.gif'; // 修改默认商品图片的路径
        $arr['qq'] = !empty($arr['qq']) ? $arr['qq'] : '';
        $arr['ww'] = !empty($arr['ww']) ? $arr['ww'] : '';
        $arr['default_storage'] = isset($arr['default_storage']) ? intval($arr['default_storage']) : 1;
        $arr['min_goods_amount'] = isset($arr['min_goods_amount']) ? floatval($arr['min_goods_amount']) : 0;
        $arr['one_step_buy'] = empty($arr['one_step_buy']) ? 0 : 1;
        $arr['invoice_type'] = empty($arr['invoice_type']) ? ['type' => [], 'rate' => []] : unserialize($arr['invoice_type']);
        $arr['show_order_type'] = isset($arr['show_order_type']) ? $arr['show_order_type'] : 0;    // 显示方式默认为列表方式
        $arr['help_open'] = isset($arr['help_open']) ? $arr['help_open'] : 1;    // 显示方式默认为列表方式
        $arr['currency_format'] = !empty($arr['currency_format']) ? strip_tags($arr['currency_format']) : '';
        $arr['tengxun_key'] = (isset($arr['tengxun_key']) && !empty($arr['tengxun_key'])) ? strip_tags($arr['tengxun_key']) : 'F75BZ-54UKV-6AGPT-UYF6Z-BLUBV-22BAE';

        //限定语言项
        $lang_array = ['zh_cn', 'zh_tw', 'en_us'];
        if (empty($arr['lang']) || !in_array($arr['lang'], $lang_array)) {
            $arr['lang'] = 'zh_cn'; // 默认语言为简体中文
        }
        $arr['lang'] = str_replace('_', '-', $arr['lang']);

        if (empty($arr['integrate_code'])) {
            $arr['integrate_code'] = 'ecshop'; // 默认的会员整合插件为 ecshop
        }
        write_static_cache('shop_config', $arr);
    } else {
        $arr = $data;
    }

    return $arr;
}

/**
 * 过滤和排序所有分类，返回一个带有缩进级别的数组
 *
 * @access  private
 * @param   int $cat_id 上级分类ID
 * @param   array $arr 含有所有分类的数组
 * @param   int $level 级别
 * @return  void
 */
function cat_options($spec_cat_id, $arr)
{
    static $cat_options = [];

    if (isset($cat_options[$spec_cat_id])) {
        return $cat_options[$spec_cat_id];
    }

    if (!isset($cat_options[0])) {
        $level = $last_cat_id = 0;
        $options = $cat_id_array = $level_array = [];
        $data = read_static_cache('cat_option_static');
        if ($data === false) {
            foreach ($arr as $key => $value) {
                $cat_id = $value['cat_id'];
                if ($level == 0 && $last_cat_id == 0) {
                    if ($value['parent_id'] > 0) {
                        break;
                    }

                    $options[$cat_id] = $value;
                    $options[$cat_id]['level'] = $level;
                    $options[$cat_id]['id'] = $cat_id;
                    $options[$cat_id]['name'] = $value['cat_name'];
                    unset($arr[$key]);

                    if ($value['has_children'] == 0) {
                        continue;
                    }
                    $last_cat_id = $cat_id;
                    $cat_id_array = [$cat_id];
                    $level_array[$last_cat_id] = ++$level;
                    continue;
                }

                if ($value['parent_id'] == $last_cat_id) {
                    $options[$cat_id] = $value;
                    $options[$cat_id]['level'] = $level;
                    $options[$cat_id]['id'] = $cat_id;
                    $options[$cat_id]['name'] = $value['cat_name'];
                    unset($arr[$key]);

                    if ($value['has_children'] > 0) {
                        if (end($cat_id_array) != $last_cat_id) {
                            $cat_id_array[] = $last_cat_id;
                        }
                        $last_cat_id = $cat_id;
                        $cat_id_array[] = $cat_id;
                        $level_array[$last_cat_id] = ++$level;
                    }
                } elseif ($value['parent_id'] > $last_cat_id) {
                    break;
                }
            }

            $count = count($cat_id_array);
            if ($count > 1) {
                $last_cat_id = array_pop($cat_id_array);
            } elseif ($count == 1) {
                if ($last_cat_id != end($cat_id_array)) {
                    $last_cat_id = end($cat_id_array);
                } else {
                    $level = 0;
                    $last_cat_id = 0;
                    $cat_id_array = [];
                }
            }

            if ($last_cat_id && isset($level_array[$last_cat_id])) {
                $level = $level_array[$last_cat_id];
            } else {
                $level = 0;
            }

            //如果数组过大，不采用静态缓存方式
            if (count($options) <= 2000) {
                write_static_cache('cat_option_static', $options);
            }
        } else {
            $options = $data;
        }
        $cat_options[0] = $options;
    } else {
        $options = $cat_options[0];
    }

    if (!$spec_cat_id) {
        return $options;
    } else {
        if (empty($options[$spec_cat_id])) {
            return [];
        }

        $spec_cat_id_level = $options[$spec_cat_id]['level'];

        foreach ($options as $key => $value) {
            if ($key != $spec_cat_id) {
                unset($options[$key]);
            } else {
                break;
            }
        }

        $spec_cat_id_array = [];
        foreach ($options as $key => $value) {
            if (($spec_cat_id_level == $value['level'] && $value['cat_id'] != $spec_cat_id) ||
                ($spec_cat_id_level > $value['level'])
            ) {
                break;
            } else {
                $spec_cat_id_array[$key] = $value;
            }
        }
        $cat_options[$spec_cat_id] = $spec_cat_id_array;

        return $spec_cat_id_array;
    }
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

    $brand_list = [];
    foreach ($res as $row) {
        $brand_list[$row['bid']] = addslashes($row['brandName']);
    }

    return $brand_list;
}

/**
 * 获得某个分类下
 *
 * @access  public
 * @param   int $cat
 * @return  array
 */
function get_brands($cat = 0, $app = 'brand', $num = 0)
{
    global $page_libs;
    $template = basename(PHP_SELF);
    $template = substr($template, 0, strrpos($template, '.'));
    // include_once(ROOT_PATH . ADMIN_PATH . '/includes/lib_template.php');
    static $static_page_libs = null;
    if ($static_page_libs == null) {
        $static_page_libs = $page_libs;
    }

    $children = ($cat > 0) ? '1 AND ' . get_children($cat) : 1;

    $sql = "SELECT b.brand_id, b.brand_name, b.brand_logo, b.brand_desc, COUNT(*) AS goods_num, IF(b.brand_logo > '', '1', '0') AS tag " .
        "FROM " . $GLOBALS['ecs']->table('brand') . "AS b " .
        " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON g.brand_id = b.brand_id AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 " .
        "WHERE $children AND b.is_show = 1 " .
        "GROUP BY b.brand_id HAVING goods_num > 0 ORDER BY tag DESC, b.sort_order ASC";

    if (isset($static_page_libs[$template]['/library/brands.lbi'])) {
        $num = get_library_number("brands");
        $sql .= " LIMIT $num ";
    } elseif ($num > 0) {
        $sql .= " LIMIT $num ";
    }
    $row = $GLOBALS['db']->getAll($sql);

    foreach ($row as $key => $val) {
        $row[$key]['url'] = build_uri($app, ['cid' => $cat, 'bid' => $val['brand_id']], $val['brand_name']);
        $row[$key]['brand_desc'] = htmlspecialchars($val['brand_desc'], ENT_QUOTES);
        $row[$key]['brand_logo'] = DATA_DIR . '/brandlogo/' . $val['brand_logo'];//by wang

        //OSS文件存储ecmoban模板堂 --zhuo start
        if ($GLOBALS['_CFG']['open_oss'] == 1) {
            $bucket_info = get_bucket_info();
            $row[$key]['brand_logo'] = $bucket_info['endpoint'] . DATA_DIR . '/brandlogo/' . $val['brand_logo'];
        }
        //OSS文件存储ecmoban模板堂 --zhuo end
    }

    return $row;
}

//by wang 楼层品牌
function get_floor_brand($brand_ids)
{
    $row = [];

    if (is_array($brand_ids)) {
        $sql = "SELECT brand_id, brand_name, brand_logo, brand_desc from " . $GLOBALS['ecs']->table('brand') . " where brand_id " . db_create_in($brand_ids);

        $row = $GLOBALS['db']->getAll($sql);

        foreach ($row as $key => $val) {
            $row[$key]['url'] = build_uri('brand', ['bid' => $val['brand_id']], $val['brand_name']);
            $row[$key]['brand_desc'] = htmlspecialchars($val['brand_desc'], ENT_QUOTES);
            $row[$key]['brand_logo'] = DATA_DIR . '/brandlogo/' . $val['brand_logo'];//by wang

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

/**
 *  所有的促销活动信息
 *
 * @access  public
 * @return  array
 */
function get_promotion_info($goods_id = '', $ru_id = 0)
{
    $snatch = [];
    $group = [];
    $auction = [];
    $package = [];
    $favourable = [];

    $gmtime = gmtime();
    $sql = 'SELECT act_id, act_name, act_type, start_time, end_time FROM ' . $GLOBALS['ecs']->table('goods_activity') . " WHERE is_finished=0 AND start_time <= '$gmtime' AND end_time >= '$gmtime' AND user_id = '$ru_id'";
    if (!empty($goods_id)) {
        $sql .= " AND goods_id = '$goods_id'";
    }

    $res = $GLOBALS['db']->getAll($sql);
    foreach ($res as $data) {
        switch ($data['act_type']) {
            case GAT_SNATCH: //夺宝奇兵
                $snatch[$data['act_id']]['act_name'] = $data['act_name'];
                $snatch[$data['act_id']]['url'] = build_uri('snatch', ['sid' => $data['act_id']]);
                $snatch[$data['act_id']]['time'] = sprintf(L('promotion_time'), local_date('Y-m-d', $data['start_time']), local_date('Y-m-d', $data['end_time']));
                $snatch[$data['act_id']]['sort'] = $data['start_time'];
                $snatch[$data['act_id']]['type'] = 'snatch';
                break;

            case GAT_GROUP_BUY: //团购
                $group[$data['act_id']]['act_name'] = $data['act_name'];
                $group[$data['act_id']]['url'] = build_uri('group_buy', ['gbid' => $data['act_id']]);
                $group[$data['act_id']]['time'] = sprintf(L('promotion_time'), local_date('Y-m-d', $data['start_time']), local_date('Y-m-d', $data['end_time']));
                $group[$data['act_id']]['sort'] = $data['start_time'];
                $group[$data['act_id']]['type'] = 'group_buy';
                break;

            case GAT_AUCTION: //拍卖
                $auction[$data['act_id']]['act_name'] = $data['act_name'];
                $auction[$data['act_id']]['url'] = build_uri('auction', ['auid' => $data['act_id']]);
                $auction[$data['act_id']]['time'] = sprintf(L('promotion_time'), local_date('Y-m-d', $data['start_time']), local_date('Y-m-d', $data['end_time']));
                $auction[$data['act_id']]['sort'] = $data['start_time'];
                $auction[$data['act_id']]['type'] = 'auction';
                break;

            case GAT_PACKAGE: //礼包
                $package[$data['act_id']]['act_name'] = $data['act_name'];
                $package[$data['act_id']]['url'] = 'package.php#' . $data['act_id'];
                $package[$data['act_id']]['time'] = sprintf(L('promotion_time'), local_date('Y-m-d', $data['start_time']), local_date('Y-m-d', $data['end_time']));
                $package[$data['act_id']]['sort'] = $data['start_time'];
                $package[$data['act_id']]['type'] = 'package';
                break;
        }
    }

    if ($ru_id > 0) {
        $fav_where = "(user_id = '$ru_id' OR userFav_type = 1)";
    } else {
        $fav_where = "user_id = '$ru_id'";
    }

    $user_rank = ',' . $_SESSION['user_rank'] . ',';
    $leftJoin = '';
    $favourable = [];
    $sql = 'SELECT act_id, act_range, act_range_ext, act_name, start_time, end_time, act_type FROM ' . $GLOBALS['ecs']->table('favourable_activity') . " WHERE start_time <= '$gmtime' AND end_time >= '$gmtime' AND " . $fav_where;
    if (!empty($goods_id)) {
        $sql .= " AND CONCAT(',', user_rank, ',') LIKE '%" . $user_rank . "%'";
    }
    $res = $GLOBALS['db']->getAll($sql);

    if (empty($goods_id)) {
        foreach ($res as $rows) {
            $favourable[$rows['act_id']]['act_name'] = $rows['act_name'];
            $favourable[$rows['act_id']]['url'] = 'activity.php';
            $favourable[$rows['act_id']]['time'] = sprintf(L('promotion_time'), local_date('Y-m-d', $rows['start_time']), local_date('Y-m-d', $rows['end_time']));
            $favourable[$rows['act_id']]['sort'] = $rows['start_time'];
            $favourable[$rows['act_id']]['type'] = 'favourable';
            $favourable[$rows['act_id']]['act_type'] = $rows['act_type'];
        }
    } else {
        $sql = "SELECT g.cat_id, g.brand_id FROM " . $GLOBALS['ecs']->table('goods') . " as g" . $leftJoin .
            " WHERE g.goods_id = '$goods_id'";
        $row = $GLOBALS['db']->getRow($sql);
        $category_id = $row['cat_id'];
        $brand_id = $row['brand_id'];

        foreach ($res as $rows) {
            if ($rows['act_range'] == FAR_ALL) {
                $favourable[$rows['act_id']]['act_name'] = $rows['act_name'];
                $favourable[$rows['act_id']]['url'] = 'activity.php';
                $favourable[$rows['act_id']]['time'] = sprintf(L('promotion_time'), local_date('Y-m-d', $rows['start_time']), local_date('Y-m-d', $rows['end_time']));
                $favourable[$rows['act_id']]['sort'] = $rows['start_time'];
                $favourable[$rows['act_id']]['type'] = 'favourable';
                $favourable[$rows['act_id']]['act_type'] = $rows['act_type'];
            } elseif ($rows['act_range'] == FAR_CATEGORY) {
                /* 找出分类id的子分类id */
                $id_list = [];
                $raw_id_list = explode(',', $rows['act_range_ext']);
                foreach ($raw_id_list as $id) {
                    $id_list = array_merge($id_list, array_keys(cat_list($id, 0, 0)));
                }
                $ids = join(',', array_unique($id_list));

                if (strpos(',' . $ids . ',', ',' . $category_id . ',') !== false) {
                    $favourable[$rows['act_id']]['act_name'] = $rows['act_name'];
                    $favourable[$rows['act_id']]['url'] = 'activity.php';
                    $favourable[$rows['act_id']]['time'] = sprintf(L('promotion_time'), local_date('Y-m-d', $rows['start_time']), local_date('Y-m-d', $rows['end_time']));
                    $favourable[$rows['act_id']]['sort'] = $rows['start_time'];
                    $favourable[$rows['act_id']]['type'] = 'favourable';
                    $favourable[$rows['act_id']]['act_type'] = $rows['act_type'];
                }
            } elseif ($rows['act_range'] == FAR_BRAND) {
                if (strpos(',' . $rows['act_range_ext'] . ',', ',' . $brand_id . ',') !== false) {
                    $favourable[$rows['act_id']]['act_name'] = $rows['act_name'];
                    $favourable[$rows['act_id']]['url'] = 'activity.php';
                    $favourable[$rows['act_id']]['time'] = sprintf(L('promotion_time'), local_date('Y-m-d', $rows['start_time']), local_date('Y-m-d', $rows['end_time']));
                    $favourable[$rows['act_id']]['sort'] = $rows['start_time'];
                    $favourable[$rows['act_id']]['type'] = 'favourable';
                    $favourable[$rows['act_id']]['act_type'] = $rows['act_type'];
                }
            } elseif ($rows['act_range'] == FAR_GOODS) {
                if (strpos(',' . $rows['act_range_ext'] . ',', ',' . $goods_id . ',') !== false) {
                    $favourable[$rows['act_id']]['act_name'] = $rows['act_name'];
                    $favourable[$rows['act_id']]['url'] = 'activity.php';
                    $favourable[$rows['act_id']]['time'] = sprintf(L('promotion_time'), local_date('Y-m-d', $rows['start_time']), local_date('Y-m-d', $rows['end_time']));
                    $favourable[$rows['act_id']]['sort'] = $rows['start_time'];
                    $favourable[$rows['act_id']]['type'] = 'favourable';
                    $favourable[$rows['act_id']]['act_type'] = $rows['act_type'];
                }
            }
        }
    }

    $sort_time = [];
    $arr = array_merge($snatch, $group, $auction, $package, $favourable);
    foreach ($arr as $key => $value) {
        $sort_time[] = $value['sort'];
    }
    array_multisort($sort_time, SORT_NUMERIC, SORT_DESC, $arr);

    return $arr;
}

/**
 * 获得指定分类下所有底层分类的ID
 *
 * @access  public
 * @param   integer $cat 指定的分类ID
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
        if ($child_three == 1) {
            if ($cat) {
                return $type_cat . db_create_in($cat);
            } else {
                return $type_cat . db_create_in('');
            }
        } else {
            $cat = array_unique(array_merge([$cat], $cat_keys));

            if ($cat) {
                $cat = db_create_in($cat);
            } else {
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
 * 分类一维数组
 */
function get_array_keys_cat($cat_id, $type = 0, $table = 'category')
{
    $list = arr_foreach(cat_list($cat_id, 1, 1, $table));

    if ($type == 1) {
        if ($list) {
            $list = implode(',', $list);
            $list = get_del_str_comma($list);
        }
    }

    return $list;
}


/**
 * 获得指定文章分类下所有底层分类的ID
 *
 * @access  public
 * @param   integer $cat 指定的分类ID
 *
 * @return void
 */
function get_article_children($cat = 0)
{
    return db_create_in(array_unique(array_merge([$cat], array_keys(article_cat_list($cat, 0, false)))), 'cat_id');
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
 * @param   string $order_sn 订单编号
 * @param   integer $order_status 订单状态
 * @param   integer $shipping_status 配送状态
 * @param   integer $pay_status 付款状态
 * @param   string $note 备注
 * @param   string $username 用户名，用户自己的操作则为 buyer
 * @return  void
 */
function order_action($order_sn, $order_status, $shipping_status, $pay_status, $note = '', $username = null, $place = 0, $confirm_take_time = 0)
{
    if (!empty($confirm_take_time)) {
        $log_time = $confirm_take_time;
    } else {
        $log_time = gmtime();
    }

    if (is_null($username)) {
        $username = $_SESSION['admin_name'];
    }

    $sql = 'INSERT INTO ' . $GLOBALS['ecs']->table('order_action') .
        ' (order_id, action_user, order_status, shipping_status, pay_status, action_place, action_note, log_time) ' .
        'SELECT ' .
        "order_id, '$username', '$order_status', '$shipping_status', '$pay_status', '$place', '$note', '$log_time'" .
        ' FROM ' . $GLOBALS['ecs']->table('order_info') . " WHERE order_sn = '$order_sn'";
    $GLOBALS['db']->query($sql);
}

/**
 * 格式化商品价格
 *
 * @access  public
 * @param   float $price 商品价格
 * @return  string
 */
function price_format($price, $change_price = true)
{
    if ($price === '') {
        $price = 0;
    }
    if ($change_price && defined('ECS_ADMIN') === false) {
        switch ($GLOBALS['_CFG']['price_format']) {
            case 0:
                $price = number_format($price, 2, '.', '');
                break;
            case 1: // 保留不为 0 的尾数
                $price = preg_replace('/(.*)(\\.)([0-9]*?)0+$/', '\1\2\3', number_format($price, 2, '.', ''));

                if (substr($price, -1) == '.') {
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
    } else {
        @$price = number_format($price, 2, '.', '');
    }

    return sprintf($GLOBALS['_CFG']['currency_format'], $price);
}

/**
 * 返回订单中的虚拟商品
 *
 * @access  public
 * @param   int $order_id 订单id值
 * @param   bool $shipping 是否已经发货
 *
 * @return array()
 */
function get_virtual_goods($order_id, $shipping = false)
{
    if ($shipping) {
        $sql = 'SELECT goods_id, goods_name, send_number AS num, extension_code FROM ' .
            $GLOBALS['ecs']->table('order_goods') .
            " WHERE order_id = '$order_id' AND extension_code > ''";
    } else {
        $sql = 'SELECT goods_id, goods_name, (goods_number - send_number) AS num, extension_code FROM ' .
            $GLOBALS['ecs']->table('order_goods') .
            " WHERE order_id = '$order_id' AND is_real = 0 AND (goods_number - send_number) > 0 AND extension_code > '' ";
    }
    $res = $GLOBALS['db']->getAll($sql);

    $virtual_goods = [];
    foreach ($res as $row) {
        $virtual_goods[$row['extension_code']][] = ['goods_id' => $row['goods_id'], 'goods_name' => $row['goods_name'], 'num' => $row['num']];
    }

    return $virtual_goods;
}

/**
 *  虚拟商品发货
 *
 * @access  public
 * @param   array $virtual_goods 虚拟商品数组
 * @param   string $msg 错误信息
 * @param   string $order_sn 订单号。
 * @param   string $process 设定当前流程：split，发货分单流程；other，其他，默认。
 *
 * @return bool
 */
function virtual_goods_ship(&$virtual_goods, &$msg, $order_sn, $return_result = false, $process = 'other')
{
    $virtual_card = [];
    foreach ($virtual_goods as $code => $goods_list) {
        /* 只处理虚拟卡 */
        if ($code == 'virtual_card') {
            foreach ($goods_list as $goods) {
                if (virtual_card_shipping($goods, $order_sn, $msg, $process)) {
                    if ($return_result) {
                        $virtual_card[] = ['goods_id' => $goods['goods_id'], 'goods_name' => $goods['goods_name'], 'info' => virtual_card_result($order_sn, $goods)];
                    }
                } else {
                    return false;
                }
            }

            $GLOBALS['smarty']->assign('virtual_card', $virtual_card);
        }
    }

    return true;
}

/**
 *  虚拟卡发货
 *
 * @access  public
 * @param   string $goods 商品详情数组
 * @param   string $order_sn 本次操作的订单
 * @param   string $msg 返回信息
 * @param   string $process 设定当前流程：split，发货分单流程；other，其他，默认。
 *
 * @return  boolen
 */
function virtual_card_shipping($goods, $order_sn, &$msg, $process = 'other')
{
    /* 包含加密解密函数所在文件 */
    include_once(BASE_PATH . 'Helpers/code_helper.php');

    /* 检查有没有缺货 */
    $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('virtual_card') . " WHERE goods_id = '$goods[goods_id]' AND is_saled = 0 ";
    $num = $GLOBALS['db']->GetOne($sql);

    if ($num < $goods['num']) {
        $msg .= sprintf($GLOBALS['_LANG']['virtual_card_oos'], $goods['goods_name']);

        return false;
    }

    /* 取出卡片信息 */
    $sql = "SELECT card_id, card_sn, card_password, end_date, crc32 FROM " . $GLOBALS['ecs']->table('virtual_card') . " WHERE goods_id = '$goods[goods_id]' AND is_saled = 0  LIMIT " . $goods['num'];
    $arr = $GLOBALS['db']->getAll($sql);

    $card_ids = [];
    $cards = [];

    foreach ($arr as $virtual_card) {
        $card_info = [];

        /* 卡号和密码解密 */
        if ($virtual_card['crc32'] == 0 || $virtual_card['crc32'] == crc32(AUTH_KEY)) {
            $card_info['card_sn'] = _decrypt($virtual_card['card_sn']);
            $card_info['card_password'] = _decrypt($virtual_card['card_password']);
        } elseif ($virtual_card['crc32'] == crc32(OLD_AUTH_KEY)) {
            $card_info['card_sn'] = _decrypt($virtual_card['card_sn'], OLD_AUTH_KEY);
            $card_info['card_password'] = _decrypt($virtual_card['card_password'], OLD_AUTH_KEY);
        } else {
            $msg .= 'error key';

            return false;
        }
        $card_info['end_date'] = date($GLOBALS['_CFG']['date_format'], $virtual_card['end_date']);
        $card_ids[] = $virtual_card['card_id'];
        $cards[] = $card_info;
    }

    /* 标记已经取出的卡片 */
    $sql = "UPDATE " . $GLOBALS['ecs']->table('virtual_card') . " SET " .
        "is_saled = 1 ," .
        "order_sn = '$order_sn' " .
        "WHERE " . db_create_in($card_ids, 'card_id');

    if (!$GLOBALS['db']->query($sql)) {
        $msg .= $GLOBALS['db']->error();

        return false;
    }

    /* 更新库存 */
    $sql = "UPDATE " . $GLOBALS['ecs']->table('goods') . " SET goods_number = goods_number - '$goods[num]' WHERE goods_id = '$goods[goods_id]'";
    $GLOBALS['db']->query($sql);

    if (true) {
        /* 获取订单信息 */
        $sql = "SELECT order_id, order_sn, consignee, email FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_sn = '$order_sn'";
        $order = $GLOBALS['db']->GetRow($sql);

        /* 更新订单信息 */
        if ($process == 'split') {
            $sql = "UPDATE " . $GLOBALS['ecs']->table('order_goods') . "
                    SET send_number = send_number + '" . $goods['num'] . "'
                    WHERE order_id = '" . $order['order_id'] . "'
                    AND goods_id = '" . $goods['goods_id'] . "' ";
        } else {
            $sql = "UPDATE " . $GLOBALS['ecs']->table('order_goods') . "
                    SET send_number = '" . $goods['num'] . "'
                    WHERE order_id = '" . $order['order_id'] . "'
                    AND goods_id = '" . $goods['goods_id'] . "' ";
        }

        if (!$GLOBALS['db']->query($sql)) {
            $msg .= $GLOBALS['db']->error();

            return false;
        }
    }

    /* 发送邮件 */
    $GLOBALS['smarty']->assign('virtual_card', $cards);
    $GLOBALS['smarty']->assign('order', $order);
    $GLOBALS['smarty']->assign('goods', $goods);

    $GLOBALS['smarty']->assign('send_time', date('Y-m-d H:i:s'));
    $GLOBALS['smarty']->assign('shop_name', $GLOBALS['_CFG']['shop_name']);
    $GLOBALS['smarty']->assign('send_date', date('Y-m-d'));
    $GLOBALS['smarty']->assign('sent_date', date('Y-m-d'));

    $tpl = get_mail_template('virtual_card');
    $content = $GLOBALS['smarty']->fetch('', $tpl['template_content']);
    return send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html']);
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
    include_once(BASE_PATH . 'Helpers/code_helper.php');

    /* 获取已经发送的卡片数据 */
    $sql = "SELECT card_sn, card_password, end_date, crc32 FROM " . $GLOBALS['ecs']->table('virtual_card') . " WHERE goods_id= '$goods[goods_id]' AND order_sn = '$order_sn' ";
    $res = $GLOBALS['db']->query($sql);

    $cards = [];
    foreach ($res as $row) {
        /* 卡号和密码解密 */
        if ($row['crc32'] == 0 || $row['crc32'] == crc32(AUTH_KEY)) {
            $row['card_sn'] = _decrypt($row['card_sn']);
            $row['card_password'] = _decrypt($row['card_password']);
        } elseif ($row['crc32'] == crc32(OLD_AUTH_KEY)) {
            $row['card_sn'] = _decrypt($row['card_sn'], OLD_AUTH_KEY);
            $row['card_password'] = _decrypt($row['card_password'], OLD_AUTH_KEY);
        } else {
            $row['card_sn'] = '***';
            $row['card_password'] = '***';
        }

        $cards[] = ['card_sn' => $row['card_sn'], 'card_password' => $row['card_password'], 'end_date' => date($GLOBALS['_CFG']['date_format'], $row['end_date'])];
    }

    return $cards;
}

/**
 * 获取指定 id snatch 活动的结果
 *
 * @access  public
 * @param   int $id snatch_id
 *
 * @return  array           array(user_name, bie_price, bid_time, num)
 *                          num通常为1，如果为2表示有2个用户取到最小值，但结果只返回最早出价用户。
 */
function get_snatch_result($id)
{
    $sql = 'SELECT u.user_id, u.user_name, u.email, lg.bid_price, lg.bid_time, count(*) as num' .
        ' FROM ' . $GLOBALS['ecs']->table('snatch_log') . ' AS lg ' .
        ' LEFT JOIN ' . $GLOBALS['ecs']->table('users') . ' AS u ON lg.user_id = u.user_id' .
        " WHERE lg.snatch_id = '$id'" .
        ' GROUP BY lg.bid_price' .
        ' ORDER BY num ASC, lg.bid_price ASC, lg.bid_time ASC LIMIT 1';
    $rec = $GLOBALS['db']->GetRow($sql);

    if ($rec) {
        $rec['bid_time'] = local_date($GLOBALS['_CFG']['time_format'], $rec['bid_time']);
        $rec['formated_bid_price'] = price_format($rec['bid_price'], false);

        /* 活动信息 */
        $sql = 'SELECT ext_info " .
               " FROM ' . $GLOBALS['ecs']->table('goods_activity') .
            " WHERE act_id= '$id' AND act_type=" . GAT_SNATCH .
            " LIMIT 1";
        $row = $GLOBALS['db']->getOne($sql);
        $info = unserialize($row);

        if (!empty($info['max_price'])) {
            $rec['buy_price'] = ($rec['bid_price'] > $info['max_price']) ? $info['max_price'] : $rec['bid_price'];
        } else {
            $rec['buy_price'] = $rec['bid_price'];
        }


        /* 检查订单 */
        $sql = "SELECT COUNT(*)" .
            " FROM " . $GLOBALS['ecs']->table('order_info') .
            " WHERE extension_code = 'snatch'" .
            " AND extension_id = '$id'" .
            " AND order_status " . db_create_in([OS_CONFIRMED, OS_UNCONFIRMED]);

        $rec['order_count'] = $GLOBALS['db']->getOne($sql);
    }

    return $rec;
}

/**
 *  清除指定后缀的模板缓存或编译文件
 *
 * @access  public
 * @param  bool $is_cache 是否清除缓存还是清出编译文件
 * @param  string $ext 需要删除的文件名，不包含后缀
 *
 * @return int        返回清除的文件个数
 */
function clear_tpl_files($is_cache = true, $ext = '')
{
    $cachePath = ROOT_PATH . 'storage/framework/';
    $dirs = ['cache', 'data', 'temp', 'views'];
    foreach ($dirs as $dir) {
        Util::delDir($cachePath . $dir);
    }
    return true;
}

/**
 * 清除模版编译文件
 *
 * @access  public
 * @param   mix $ext 模版文件名， 不包含后缀
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
 * @param   mix $ext 模版文件名， 不包含后缀
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
 * @param   mix $ext 模版文件名后缀
 * @return  void
 */
function clear_all_files($ext = '', $filename = '')
{
    return clear_tpl_files(false, $ext, $filename) + clear_tpl_files(true, $ext, $filename);
}

/*
 * 删除目录
 * 删除目录下文件
 * $dir 目录位置
 * $strpos 文件名称
 * $is_rmdir 是否删除目录
 */
function get_deldir($dir, $strpos = '', $is_rmdir = false)
{
    $dh = opendir($dir);
    while ($file = readdir($dh)) {
        if ($file != "." && $file != "..") {
            $fullpath = $dir . "/" . $file;

            if ($strpos) { //删除指定名称文件
                $spos = strpos($fullpath, $strpos);
                if ($spos !== false) {
                    if (!is_dir($fullpath)) {
                        unlink($fullpath);
                    } else {
                        deldir($fullpath);
                    }
                }
            } else {  //删除所有文件
                if (!is_dir($fullpath)) {
                    unlink($fullpath);
                } else {
                    deldir($fullpath);
                }
            }
        }
    }

    closedir($dh);

    //删除当前文件夹
    if ($is_rmdir == true) {
        if (rmdir($dir)) {
            return true;
        } else {
            return false;
        }
    }
}

/**
 * 页面上调用的js文件
 *
 * @access  public
 * @param   string $files
 * @return  void
 */
function smarty_insert_scripts($args)
{
    static $scripts = [];

    $arr = explode(',', str_replace(' ', '', $args['files']));

    $str = '';
    foreach ($arr as $val) {
        if (in_array($val, $scripts) == false) {
            $scripts[] = $val;
            if ($val{0} == '.') {
                $str .= '<script type="text/javascript" src="' . $val . '"></script>';
            } else {
                $str .= '<script type="text/javascript" src="js/' . $val . '"></script>';
            }
        }
    }

    return $str;
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

    if (empty($page)) {
        $page = 1;
    }

    if (!empty($count)) {
        $step = 1;
        $str .= "<option value='1'>1</option>";

        for ($i = 2; $i < $count; $i += $step) {
            $step = ($i >= $page + $len - 1 || $i <= $page - $len + 1) ? $len : 1;
            $str .= "<option value='$i'";
            $str .= $page == $i ? " selected='true'" : '';
            $str .= ">$i</option>";
        }

        if ($count > 1) {
            $str .= "<option value='$count'";
            $str .= $page == $count ? " selected='true'" : '';
            $str .= ">$count</option>";
        }
    }

    return $str;
}

/**
 * 重写 URL 地址
 *
 * @access  public
 * @param   string $app 执行程序
 * @param   array $params 参数数组
 * @param   string $append 附加字串
 * @param   integer $page 页数
 * @param   string $keywords 搜索关键词字符串
 * @return  void
 */
function build_uri($app, $params, $append = '', $page = 0, $keywords = '', $size = 0)
{
    static $rewrite = null;

    if ($rewrite === null) {
        $rewrite = intval($GLOBALS['_CFG']['rewrite']);
    }
    $args = ['cid' => 0,
        'gid' => 0,
        'bid' => 0,
        'acid' => 0,
        'aid' => 0,
        //ecmoban模板堂 --zhuo start
        'mid' => 0,
        'urid' => 0,
        'ubrand' => 0,
        'chkw' => '',
        'is_ship' => '',//by wang
        'hid' => 0,
        //ecmoban模板堂 --zhuo end
        'sid' => 0,
        'gbid' => 0,
        'auid' => 0,
        'sort' => '',
        'order' => '',
        'actid' => 0,//ectouch
        'stid' => 0,//ectouch
    ];
    extract(array_merge($args, $params));
    $uri = '';
    switch ($app) {
        case 'category':
            if (empty($cid)) {
                return false;
            } else {
                $data = ['id' => $cid];
                if (!empty($bid)) {
                    $data['brand'] = $bid;
                }
                //ecmoban模板堂 --zhuo start
                if (!empty($ubrand)) {
                    $data['ubrand'] = $ubrand;
                }
                //ecmoban模板堂 --zhuo end
                if (isset($price_min)) {
                    $data['price_min'] = $price_min;
                }
                if (isset($price_max)) {
                    $data['price_max'] = $price_max;
                }
                if (!empty($filter_attr)) {
                    $data['filter_attr'] = $filter_attr;
                }
                if (!empty($page)) {
                    $data['page'] = $page;
                }
                if (!empty($sort)) {
                    $data['sort'] = $sort;
                }
                if (!empty($order)) {
                    $data['order'] = $order;
                }
                $uri = url('category/index/products', $data);
            }
            break;
        case 'goods':
            if (empty($gid)) {
                return false;
            } else {
                $uri = url('goods/index/index', ['id' => $gid, 'u' => $_SESSION['user_id']]);
            }
            break;
        //ectouch
        case 'activity':
            if (empty($actid)) {
                return false;
            } else {
                $uri = url('activity/index/detail', ['id' => $actid]);
            }
            break;
        //by ectouch
        case 'community':
            if (empty($params['coid'])) {
                return false;
            } else {
                $uri = url('community/index/detail', ['id' => $params['coid'], 'type' => $params['type']]);
            }
            break;
        //by ectouch
        case 'topic':
            if (empty($params['topic_id'])) {
                return false;
            } else {
                $uri = url('topic/index/detail', ['topic_id' => $params['topic_id']]);
            }
            break;
        //by ectouch
        case 'presale':
            if (empty($params['id'])) {
                return false;
            } else {
                $uri = url('presale/index/detail', ['id' => $params['id']]);
            }
            break;
        case 'brand':
            if (empty($bid)) {
                return false;
            } else {
                $uri = url('brand/index/detail', ['id' => $bid]);
            }
            break;
        case 'article_cat':
            if (empty($acid)) {
                return false;
            } else {
                $data = ['id' => $acid];
                if (!empty($page)) {
                    $data['page'] = $page;
                }
                if (!empty($sort)) {
                    $data['sort'] = $sort;
                }
                if (!empty($order)) {
                    $data['order'] = $order;
                }
                if (!empty($keywords)) {
                    $data['keywords'] = $keywords;
                }
                $uri = url('article/index/category', $data);
            }
            break;
        case 'article':
            if (empty($aid)) {
                return false;
            } else {
                $uri = url('article/index/detail', ['id' => $aid]);
            }
            break;
        case 'groupbuy':
            if (empty($gbid)) {
                return false;
            } else {
                $uri = url('groupbuy/index/detail', ['id' => $gbid]);
            }
            break;
        case 'sample':
            /*by zxk*/
            if (empty($params['id'])) {
                return false;
            } else {
                $uri = url('sample/index/detail', ['id' => $params['id']]);
            }
            break;
        case 'wholesale':
            /*by zxk*/
            if (empty($params['id'])) {
                return false;
            } else {
                $uri = url('wholesale/index/detail', ['id' => $params['id']]);
            }
            break;
        case 'store':
            if (empty($stid)) {
                return false;
            } else {
                $uri = url('store/index/shop_info', ['id' => $stid]);
            }
            break;
        case 'auction':
            if (empty($auid)) {
                return false;
            } else {
                $uri = url('auction/index/detail', ['id' => $auid]);
            }
            break;
        case 'snatch':
            if (empty($sid)) {
                return false;
            } else {
                $uri = url('snatch/index/index', ['id' => $sid]);
            }
            break;
        case 'search':
            break;
        case 'exchange':
            $data = ['cat_id' => $cid];
            if (isset($price_min)) {
                $data['integral_min'] = $price_min;
            }
            if (isset($price_max)) {
                $data['integral_max'] = $price_max;
            }
            if (!empty($page)) {
                $data['page'] = $page;
            }
            if (!empty($sort)) {
                $data['sort'] = $sort;
            }
            if (!empty($order)) {
                $data['order'] = $order;
            }
            $uri = url('exchange/index/index', $data);
            break;
        case 'exchange_goods':
            if (empty($gid)) {
                return false;
            } else {
                $uri = url('exchange/index/detail', ['id' => $gid]);
            }
            break;
        default:
            return false;
            break;
    }

    return $uri;
}

/**
 * 格式化重量：小于1千克用克表示，否则用千克表示
 * @param   float $weight 重量
 * @return  string  格式化后的重量
 */
function formated_weight($weight)
{
    $weight = round(floatval($weight), 3);
    if ($weight > 0) {
        if ($weight < 1) {
            /* 小于1千克，用克表示 */
            return intval($weight * 1000) . $GLOBALS['_LANG']['gram'];
        } else {
            /* 大于1千克，用千克表示 */
            return $weight . $GLOBALS['_LANG']['kilogram'];
        }
    } else {
        return 0;
    }
}

/**
 * 记录帐户变动
 * @param   int $user_id 用户id
 * @param   float $user_money 可用余额变动
 * @param   float $frozen_money 冻结余额变动
 * @param   int $rank_points 等级积分变动
 * @param   int $pay_points 消费积分变动
 * @param   string $change_desc 变动说明
 * @param   int $change_type 变动类型：参见常量文件
 * @return  void
 */
function log_account_change($user_id, $user_money = 0, $frozen_money = 0, $rank_points = 0, $pay_points = 0, $change_desc = '', $change_type = ACT_OTHER)
{
    //ecmoban模板堂 --zhuo satrt
    $sql = 'SELECT u.user_id, u.email, u.user_name, u.user_money, u.mobile_phone, u.pay_points' .
        ' FROM ' . $GLOBALS['ecs']->table('users') . ' AS u ' .
        " WHERE u.user_id = '$user_id'";
    $user_info = $GLOBALS['db']->getRow($sql);

    if ($user_info['user_money'] == 0 && $user_money < 0) {
        $user_money = 0;
    }

    if ($user_info['user_money'] == 0 && $pay_points < 0) {
        $user_money = 0;
    }
    //ecmoban模板堂 --zhuo end

    /* 插入帐户变动记录 */
    $account_log = [
        'user_id' => $user_id,
        'user_money' => $user_money,
        'frozen_money' => $frozen_money,
        'rank_points' => $rank_points,
        'pay_points' => $pay_points,
        'change_time' => gmtime(),
        'change_desc' => $change_desc,
        'change_type' => $change_type
    ];
    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('account_log'), $account_log, 'INSERT');

    /* 更新用户信息 */
    $sql = "UPDATE " . $GLOBALS['ecs']->table('users') .
        " SET user_money = user_money + ('$user_money')," .
        " frozen_money = frozen_money + ('$frozen_money')," .
        " rank_points = rank_points + ('$rank_points')," .
        " pay_points = pay_points + ('$pay_points')" .
        " WHERE user_id = '$user_id' LIMIT 1";
    $GLOBALS['db']->query($sql);
}

/**
 * 获得指定分类下的子分类的数组
 *
 * @access  public
 * @param   int $cat_id 分类的ID
 * @param   int $selected 当前选中分类的ID
 * @param   boolean $re_type 返回的类型: 值为真时返回下拉列表,否则返回数组
 * @param   int $level 限定返回的级数。为0时返回所有级数
 * @return  mix
 */
function article_cat_list($cat_id = 0, $selected = 0, $re_type = true, $level = 0)
{
    static $res = null;

    if ($res === null) {
        $data = read_static_cache('art_cat_pid_releate');
        if ($data === false) {
            $sql = "SELECT c.*, COUNT(s.cat_id) AS has_children, COUNT(a.article_id) AS aricle_num,a.description " .
                ' FROM ' . $GLOBALS['ecs']->table('article_cat') . " AS c" .
                " LEFT JOIN " . $GLOBALS['ecs']->table('article_cat') . " AS s ON s.parent_id=c.cat_id" .
                " LEFT JOIN " . $GLOBALS['ecs']->table('article') . " AS a ON a.cat_id=c.cat_id" .
                " GROUP BY c.cat_id " .
                " ORDER BY parent_id, sort_order ASC";
            $res = $GLOBALS['db']->getAll($sql);
            write_static_cache('art_cat_pid_releate', $res);
        } else {
            $res = $data;
        }
    }

    if (empty($res) == true) {
        return $re_type ? '' : [];
    }

    $options = article_cat_options($cat_id, $res); // 获得指定分类下的子分类的数组

    /* 截取到指定的缩减级别 */
    if ($level > 0) {
        if ($cat_id == 0) {
            $end_level = $level;
        } else {
            $first_item = reset($options); // 获取第一个元素
            $end_level = $first_item['level'] + $level;
        }

        /* 保留level小于end_level的部分 */
        foreach ($options as $key => $val) {
            if ($val['level'] >= $end_level) {
                unset($options[$key]);
            }
        }
    }

    $pre_key = 0;
    foreach ($options as $key => $value) {
        $options[$key]['has_children'] = 1;
        if ($pre_key > 0) {
            if ($options[$pre_key]['cat_id'] == $options[$key]['parent_id']) {
                $options[$pre_key]['has_children'] = 1;
            }
        }
        $pre_key = $key;
    }

    if ($re_type == true) {
        $select = '';
        foreach ($options as $var) {
            $select .= '<option value="' . $var['cat_id'] . '" ';
            $select .= ' cat_type="' . $var['cat_type'] . '" ';
            $select .= ($selected == $var['cat_id']) ? "selected='ture'" : '';
            $select .= '>';
            if ($var['level'] > 0) {
                $select .= str_repeat('&nbsp;', $var['level'] * 4);
            }
            $select .= htmlspecialchars(addslashes($var['cat_name'])) . '</option>';
        }

        return $select;
    } else {
        foreach ($options as $key => $value) {
            $options[$key]['url'] = build_uri('article_cat', ['acid' => $value['cat_id']], $value['cat_name']);
        }
        return $options;
    }
}

/**
 * 过滤和排序所有文章分类，返回一个带有缩进级别的数组
 *
 * @access  private
 * @param   int $cat_id 上级分类ID
 * @param   array $arr 含有所有分类的数组
 * @param   int $level 级别
 * @return  void
 */
function article_cat_options($spec_cat_id, $arr)
{
    static $cat_options = [];

    if (isset($cat_options[$spec_cat_id])) {
        return $cat_options[$spec_cat_id];
    }

    if (!isset($cat_options[0])) {
        $level = $last_cat_id = 0;
        $options = $cat_id_array = $level_array = [];
        while (!empty($arr)) {
            foreach ($arr as $key => $value) {
                $cat_id = $value['cat_id'];
                if ($level == 0 && $last_cat_id == 0) {
                    if ($value['parent_id'] > 0) {
                        break;
                    }

                    $options[$cat_id] = $value;
                    $options[$cat_id]['level'] = $level;
                    $options[$cat_id]['id'] = $cat_id;
                    $options[$cat_id]['name'] = $value['cat_name'];
                    unset($arr[$key]);

                    if ($value['has_children'] == 0) {
                        continue;
                    }
                    $last_cat_id = $cat_id;
                    $cat_id_array = [$cat_id];
                    $level_array[$last_cat_id] = ++$level;
                    continue;
                }

                if ($value['parent_id'] == $last_cat_id) {
                    $options[$cat_id] = $value;
                    $options[$cat_id]['level'] = $level;
                    $options[$cat_id]['id'] = $cat_id;
                    $options[$cat_id]['name'] = $value['cat_name'];
                    unset($arr[$key]);

                    if ($value['has_children'] > 0) {
                        if (end($cat_id_array) != $last_cat_id) {
                            $cat_id_array[] = $last_cat_id;
                        }
                        $last_cat_id = $cat_id;
                        $cat_id_array[] = $cat_id;
                        $level_array[$last_cat_id] = ++$level;
                    }
                } elseif ($value['parent_id'] > $last_cat_id) {
                    break;
                }
            }

            $count = count($cat_id_array);
            if ($count > 1) {
                $last_cat_id = array_pop($cat_id_array);
            } elseif ($count == 1) {
                if ($last_cat_id != end($cat_id_array)) {
                    $last_cat_id = end($cat_id_array);
                } else {
                    $level = 0;
                    $last_cat_id = 0;
                    $cat_id_array = [];
                    continue;
                }
            }

            if ($last_cat_id && isset($level_array[$last_cat_id])) {
                $level = $level_array[$last_cat_id];
            } else {
                $level = 0;
            }
        }
        $cat_options[0] = $options;
    } else {
        $options = $cat_options[0];
    }

    if (!$spec_cat_id) {
        return $options;
    } else {
        if (empty($options[$spec_cat_id])) {
            return [];
        }

        $spec_cat_id_level = $options[$spec_cat_id]['level'];

        foreach ($options as $key => $value) {
            if ($key != $spec_cat_id) {
                unset($options[$key]);
            } else {
                break;
            }
        }

        $spec_cat_id_array = [];
        foreach ($options as $key => $value) {
            if (($spec_cat_id_level == $value['level'] && $value['cat_id'] != $spec_cat_id) ||
                ($spec_cat_id_level > $value['level'])
            ) {
                break;
            } else {
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
 * @param   string $func
 * @param   array $params
 *
 * @return  mixed
 */
function uc_call($func, $params = null)
{
    restore_error_handler();
    if (!function_exists($func)) {
        include_once(dirname(ROOT_PATH) . '/uc_client/client.php');
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
 * @param string $image
 * @param string $path
 * @param null $default
 *
 * @return null|string
 */
function get_image_path($image = '', $path = '', $default = null)
{
    if (strtolower(substr($image, 0, 4)) == 'http') {
        $url = $image;
    } else {
        $no_picture = is_null($default) ? elixir('img/no_image.jpg') : $default;
        $path = empty($path) ? '' : rtrim($path, '/') . '/';
        if (C('shop.open_oss') == 1) {
            $bucket_info = get_bucket_info();
            $bucket_info['endpoint'] = empty($bucket_info['endpoint']) ? $bucket_info['outside_site'] : $bucket_info['endpoint'];
            $url = empty($image) ? $no_picture : rtrim($bucket_info['endpoint'], '/') . '/' . $path . $image;
        } else {
            $img_path = $path . $image;
            if (empty($image) || !file_exists(dirname(ROOT_PATH) . '/' . $img_path)) {
                $url = $no_picture;
            } else {
                $url = __STATIC__ . '/' . $img_path;
            }
        }
    }

    return $url;
}

/**
 * 重新获得众筹商品图片与商品相册的地址
 *
 * @param int $goods_id 商品ID
 * @param string $image 原商品相册图片地址
 * @param boolean $thumb 是否为缩略图
 * @param string $call 调用方法(商品图片还是商品相册)
 * @param boolean $del 是否删除图片
 *
 * @return string   $url
 */
function get_zc_image_path($image = '', $path = '')
{
    if (strtolower(substr($image, 0, 4)) == 'http') {
        $url = $image;
    } else {
        $no_picture = elixir('img/no_image.jpg');
        $path = empty($path) ? '' : rtrim($path, '/') . '/';
        $img_path = $path . $image;
        if (empty($image) || !file_exists(dirname(ROOT_PATH) . '/' . $img_path)) {
            $url = $no_picture;
        } else {
            $url = __STATIC__ . '/' . $img_path;
        }
    }

    return $url;
}

function get_data_path($url = '', $path = '', $default = null)
{
    return get_image_path($url, 'data/' . $path, $default);
}

/**
 * 调用使用UCenter插件时的函数
 *
 * @param   string $func
 * @param   array $params
 *
 * @return  mixed
 */
function user_uc_call($func, $params = null)
{
    if (isset($GLOBALS['_CFG']['integrate_code']) && $GLOBALS['_CFG']['integrate_code'] == 'ucenter') {
        restore_error_handler();
        if (!function_exists($func)) {
            include_once(BASE_PATH . 'Helpers/uc_helper.php');
        }

        $res = call_user_func_array($func, $params);

        set_error_handler('exception_handler');

        return $res;
    } else {
        return;
    }
}

/**
 * 取得商品优惠价格列表
 *
 * @param   string $goods_id 商品编号
 * @param   string $price_type 价格类别(0为全店优惠比率，1为商品优惠价格，2为分类优惠比率)
 *
 * @return  优惠价格列表
 */
function get_volume_price_list($goods_id, $price_type = '1')
{
    $volume_price = [];
    $temp_index = '0';

    $sql = "SELECT `volume_number` , `volume_price`" .
        " FROM " . $GLOBALS['ecs']->table('volume_price') . "" .
        " WHERE `goods_id` = '" . $goods_id . "' AND `price_type` = '" . $price_type . "'" .
        " ORDER BY `volume_number`";

    $res = $GLOBALS['db']->getAll($sql);

    foreach ($res as $k => $v) {
        $volume_price[$temp_index] = [];
        $volume_price[$temp_index]['number'] = $v['volume_number'];
        $volume_price[$temp_index]['price'] = $v['volume_price'];
        $volume_price[$temp_index]['format_price'] = price_format($v['volume_price']);
        $temp_index++;
    }
    return $volume_price;
}

/**
 * 取得商品最终使用价格
 *
 * @param   string $goods_id 商品编号
 * @param   string $goods_num 购买数量
 * @param   boolean $is_spec_price 是否加入规格价格
 * @param   mix $spec 规格ID的数组或者逗号分隔的字符串
 * @param   intval $add_tocart 0,1  1代表非进货单进入该方法（SKU价格）
 * @param   intval $show_goods 0,1  商品详情页ajax，1代表SKU价格开启（SKU价格）
 *
 * @return  商品最终购买价格
 */
function get_final_price($goods_id, $goods_num = '1', $is_spec_price = false, $spec = [], $warehouse_id = 0, $area_id = 0, $type = 0, $presale = 0, $add_tocart = 1, $show_goods = 0, $product_promote_price = 0) {

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
    $sql = "SELECT count(*) FROM " . $GLOBALS['ecs']->table('presale_activity') . " AS pa, " . $GLOBALS['ecs']->table('goods') . " AS g WHERE pa.goods_id = '$goods_id' AND pa.review_status = 3 AND pa.end_time > " . gmtime() . " AND pa.goods_id = g.goods_id AND g.is_on_sale = 0";
    $is_presale = $GLOBALS['db']->getOne($sql);

    $where = "";
    if ($is_presale > 0 || $presale == 1) {
        $user_rank = 1;
        $discount = 1; //会员折扣
    }else{
        $discount = $_SESSION['discount']; //会员折扣
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
 * @param       array $goods_attr_id_array 一维数组
 * @param       string $sort 序号：asc|desc，默认为：asc
 *
 * @return      string
 */
function sort_goods_attr_id_array($goods_attr_id_array, $sort = 'asc')
{
    if (empty($goods_attr_id_array)) {
        return $goods_attr_id_array;
    }

    //重新排序
    $sql = "SELECT a.attr_type, v.attr_value, v.goods_attr_id, v.attr_checked
            FROM " . $GLOBALS['ecs']->table('attribute') . " AS a
            LEFT JOIN " . $GLOBALS['ecs']->table('goods_attr') . " AS v
                ON v.attr_id = a.attr_id
                AND a.attr_type = 1
            WHERE v.goods_attr_id " . db_create_in($goods_attr_id_array) . "
            ORDER BY a.sort_order, v.attr_id $sort";


    $row = $GLOBALS['db']->GetAll($sql);

    $return_arr = [];
    foreach ($row as $value) {
        $return_arr['sort'][] = $value['goods_attr_id'];

        $return_arr['row'][$value['goods_attr_id']] = $value;
    }

    return $return_arr;
}

/**
 *
 * 是否存在规格
 *
 * @access      public
 * @param       array $goods_attr_id_array 一维数组
 *
 * @return      string
 */
function is_spec($goods_attr_id_array, $sort = 'asc')
{
    if (empty($goods_attr_id_array)) {
        return $goods_attr_id_array;
    }

    //重新排序
    $sql = "SELECT a.attr_type, v.attr_value, v.goods_attr_id
            FROM " . $GLOBALS['ecs']->table('attribute') . " AS a
            LEFT JOIN " . $GLOBALS['ecs']->table('goods_attr') . " AS v
                ON v.attr_id = a.attr_id
                AND a.attr_type = 1
            WHERE v.goods_attr_id " . db_create_in($goods_attr_id_array) . "
            ORDER BY a.attr_id $sort";
    $row = $GLOBALS['db']->GetAll($sql);

    $return_arr = [];
    foreach ($row as $value) {
        $return_arr['sort'][] = $value['goods_attr_id'];

        $return_arr['row'][$value['goods_attr_id']] = $value;
    }

    if (!empty($return_arr)) {
        return true;
    } else {
        return false;
    }
}

/**
 * 获取指定id package 的信息
 *
 * @access  public
 * @param   int $id package_id
 *
 * @return array       array(package_id, package_name, goods_id,start_time, end_time, min_price, integral)
 */
function get_package_info($id)
{
    global $ecs, $db, $_CFG;
    $id = is_numeric($id) ? intval($id) : 0;
    $now = gmtime();

    $sql = "SELECT act_id AS id,  act_name AS package_name, goods_id , goods_name, start_time, end_time, act_desc, ext_info, user_id, activity_thumb " .
        " FROM " . $GLOBALS['ecs']->table('goods_activity') .
        " WHERE act_id='$id' AND act_type = " . GAT_PACKAGE;

    $package = $db->GetRow($sql);

    /* 将时间转成可阅读格式 */
    if ($package['start_time'] <= $now && $package['end_time'] >= $now) {
        $package['is_on_sale'] = "1";
    } else {
        $package['is_on_sale'] = "0";
    }
    $package['start_time'] = local_date('Y-m-d H:i', $package['start_time']);
    $package['end_time'] = local_date('Y-m-d H:i', $package['end_time']);
    $row = unserialize($package['ext_info']);
    unset($package['ext_info']);
    if ($row) {
        foreach ($row as $key => $val) {
            $package[$key] = $val;
        }
    }

    $sql = "SELECT pg.package_id, pg.goods_id, pg.goods_number, pg.admin_id, " .
        " g.goods_sn, g.goods_name, g.market_price, g.goods_thumb, g.is_real, " .
        " IFNULL(mp.user_price, g.shop_price * '$_SESSION[discount]') AS rank_price " .
        " FROM " . $GLOBALS['ecs']->table('package_goods') . " AS pg " .
        "   LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g " .
        "   ON g.goods_id = pg.goods_id " .
        " LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
        "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
        " WHERE pg.package_id = " . $id . " " .
        " ORDER BY pg.package_id, pg.goods_id";

    $goods_res = $GLOBALS['db']->getAll($sql);

    $market_price = 0;
    $real_goods_count = 0;
    $virtual_goods_count = 0;

    foreach ($goods_res as $key => $val) {
        $goods_res[$key]['goods_thumb'] = get_image_path($val['goods_thumb']);
        $goods_res[$key]['market_price_format'] = price_format($val['market_price']);
        $goods_res[$key]['rank_price_format'] = price_format($val['rank_price']);
        $market_price += $val['market_price'] * $val['goods_number'];
        /* 统计实体商品和虚拟商品的个数 */
        if ($val['is_real']) {
            $real_goods_count++;
        } else {
            $virtual_goods_count++;
        }
    }

    if ($real_goods_count > 0) {
        $package['is_real'] = 1;
    } else {
        $package['is_real'] = 0;
    }

    $package['goods_list'] = $goods_res;
    $package['market_package'] = $market_price;
    $package['market_package_format'] = price_format($market_price);
    $package['package_price_format'] = price_format($package['package_price']);

    return $package;
}

/**
 * 获得指定礼包的商品
 *
 * @access  public
 * @param   integer $package_id
 * @return  array
 */
function get_package_goods($package_id, $seller_id = 0, $type = 0)
{
    $sql = "SELECT pg.goods_id, g.goods_name, pg.goods_number, p.goods_attr, p.product_number, p.product_id, g.goods_weight, g.goods_thumb, g.shop_price * '$_SESSION[discount]' AS rank_price
            FROM " . $GLOBALS['ecs']->table('package_goods') . " AS pg
                LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON pg.goods_id = g.goods_id
                LEFT JOIN " . $GLOBALS['ecs']->table('products') . " AS p ON pg.product_id = p.product_id
            WHERE pg.package_id = '$package_id'";
    if ($package_id == 0 && $seller_id == 0) {
        $sql .= " AND pg.admin_id = '$_SESSION[admin_id]'";
    } elseif ($package_id == 0 && $seller_id > 0) {
        $sql .= " AND pg.admin_id = '$_SESSION[seller_id]'";
    }

    $resource = $GLOBALS['db']->query($sql);
    if (!$resource) {
        return [];
    }

    $row = [];

    /* 生成结果数组 取存在货品的商品id 组合商品id与货品id */
    $good_product_str = '';
    foreach ($resource as $_row) {
        $_row['goods_thumb'] = get_image_path($_row['goods_thumb']);
        /* 商品重量 */
        $_row['goodsweight'] = $_row['goods_weight'];

        if ($_row['product_id'] > 0) {
            /* 取存商品id */
            $good_product_str .= ',' . $_row['goods_id'];

            /* 组合商品id与货品id */
            $_row['g_p'] = $_row['goods_id'] . '_' . $_row['product_id'];
        } else {
            /* 组合商品id与货品id */
            $_row['g_p'] = $_row['goods_id'];
        }

        $_row['url'] = build_uri('goods', ['gid' => $_row['goods_id']], $_row['goods_name']);
        $_row['shop_price'] = price_format($_row['shop_price']);
        if ($type == 1) {
            $_row['products'] = get_good_products($_row['goods_id']);
        }
        //生成结果数组
        $row[] = $_row;
    }
    $good_product_str = trim($good_product_str, ',');

    /* 释放空间 */
    unset($resource, $_row, $sql);

    /* 取商品属性 */
    if ($good_product_str != '') {
        $sql = "SELECT goods_attr_id, attr_value FROM " . $GLOBALS['ecs']->table('goods_attr') . " WHERE goods_id IN ($good_product_str)";
        $result_goods_attr = $GLOBALS['db']->getAll($sql);

        $_goods_attr = [];
        foreach ($result_goods_attr as $value) {
            $_goods_attr[$value['goods_attr_id']] = $value['attr_value'];
        }
    }

    /* 过滤货品 */
    $format[0] = '%s[%s]--[%d]';
    $format[1] = '%s--[%d]';
    foreach ($row as $key => $value) {
        $row[$key]['goods_name_pack'] = $value['goods_name'];
        if ($value['goods_attr'] != '') {
            $goods_attr_array = explode('|', $value['goods_attr']);

            $goods_attr = [];
            foreach ($goods_attr_array as $_attr) {
                $goods_attr[] = $_goods_attr[$_attr];
            }

            $row[$key]['goods_name'] = sprintf($format[0], $value['goods_name'], implode('，', $goods_attr), $value['goods_number']);
        } else {
            $row[$key]['goods_name'] = sprintf($format[1], $value['goods_name'], $value['goods_number']);
        }
    }

    return $row;
}

/**
 * 取商品的货品列表
 *
 * @param       mixed $goods_id 单个商品id；多个商品id数组；以逗号分隔商品id字符串
 * @param       string $conditions sql条件
 *
 * @return  array
 */
function get_good_products($goods_id, $conditions = '')
{
    if (empty($goods_id)) {
        return [];
    }

    switch (gettype($goods_id)) {
        case 'integer':

            $_goods_id = "goods_id = '" . intval($goods_id) . "'";

            break;

        case 'string':
        case 'array':

            $_goods_id = db_create_in($goods_id, 'goods_id');

            break;
    }

    /* 取货品 */
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('products') . " WHERE $_goods_id $conditions";
    $result_products = $GLOBALS['db']->getAll($sql);

    /* 取商品属性 */
    $sql = "SELECT goods_attr_id, attr_value FROM " . $GLOBALS['ecs']->table('goods_attr') . " WHERE $_goods_id";
    $result_goods_attr = $GLOBALS['db']->getAll($sql);

    $_goods_attr = [];
    foreach ($result_goods_attr as $value) {
        $_goods_attr[$value['goods_attr_id']] = $value['attr_value'];
    }

    /* 过滤货品 */
    foreach ($result_products as $key => $value) {
        $goods_attr_array = explode('|', $value['goods_attr']);
        if (is_array($goods_attr_array)) {
            $goods_attr = [];
            foreach ($goods_attr_array as $_attr) {
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
 * @param       int $goods_id 商品id
 *
 * @return  array
 */
function get_good_products_select($goods_id)
{
    $return_array = [];
    $products = get_good_products($goods_id);

    if (empty($products)) {
        return $return_array;
    }

    foreach ($products as $value) {
        $return_array[$value['product_id']] = $value['goods_attr_str'];
    }

    return $return_array;
}

/**
 * 取商品的规格列表
 *
 * @param       int $goods_id 商品id
 * @param       string $conditions sql条件
 *
 * @return  array
 */
function get_specifications_list($goods_id, $conditions = '')
{
    /* 取商品属性 */
    $sql = "SELECT ga.goods_attr_id, ga.attr_id, ga.attr_value, a.attr_name
            FROM " . $GLOBALS['ecs']->table('goods_attr') . " AS ga, " . $GLOBALS['ecs']->table('attribute') . " AS a
            WHERE ga.attr_id = a.attr_id
            AND ga.goods_id = '$goods_id'
            $conditions";
    $result = $GLOBALS['db']->getAll($sql);

    $return_array = [];
    foreach ($result as $value) {
        $return_array[$value['goods_attr_id']] = $value;
    }

    return $return_array;
}

/**
 * 调用array_combine函数
 *
 * @param   array $keys
 * @param   array $values
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

        $keys = array_values($keys);
        $values = array_values($values);

        $combined = [];
        for ($i = 0; $i < $key_count; $i++) {
            $combined[$keys[$i]] = $values[$i];
        }

        return $combined;
    }
}

//ecmoban模板堂 --zhuo start
function get_class_nav($cat_id)
{
    $sql = "select cat_id,cat_name,parent_id from " . $GLOBALS['ecs']->table('category') . " where cat_id = '$cat_id'";
    $res = $GLOBALS['db']->getAll($sql);

    foreach ($res as $key => $row) {
        $arr[$key]['cat_id'] = $row['cat_id'];
        $arr[$key]['cat_name'] = $row['cat_name'];
        $arr[$key]['parent_id'] = $row['parent_id'];

        $arr['catId'] .= $row['cat_id'] . ",";
        $arr[$key]['child'] = get_parent_child($row['cat_id']);

        if (empty($arr[$key]['child']['catId'])) {
            $arr['catId'] = $arr['catId'];
        } else {
            $arr['catId'] .= $arr[$key]['child']['catId'];
        }
    }

    //get_print_r($arr);
    return $arr;
}

function get_parent_child($parent_id = 0)
{
    $sql = "select cat_id,cat_name,parent_id from " . $GLOBALS['ecs']->table('category') . " where parent_id = '$parent_id'";
    $res = $GLOBALS['db']->getAll($sql);

    foreach ($res as $key => $row) {
        $arr[$key]['cat_id'] = $row['cat_id'];
        $arr[$key]['cat_name'] = $row['cat_name'];
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
 * @param int cat_id
 *
 * @return int extentd_count
 * by guan
 */
function get_goodsCat_num($cat_id, $goods_ids = [], $ruCat = '')
{
    $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('goods_cat') . " AS gc left join " . $GLOBALS['ecs']->table('goods') . " as g on gc.goods_id = g.goods_id WHERE g.is_delete = 0 and gc.cat_id in($cat_id)" . $ruCat;

    $cat_goods = $GLOBALS['db']->getAll($sql);
    foreach ($cat_goods as $key => $val) {
        if (in_array($val['goods_id'], $goods_ids)) {
            unset($cat_goods[$key]);
        }
    }
    return count($cat_goods);
}

//guan start end

/**
 * 商品限购
 */
function get_purchasing_goods_info($goods_id = 0)
{//获取商品限购数量
    $sql = "SELECT is_xiangou,xiangou_num, xiangou_start_date, xiangou_end_date FROM " . $GLOBALS['ecs']->table('goods') . "WHERE goods_id = '$goods_id' LIMIT 1";
    return $GLOBALS['db']->getRow($sql);
}

/**
 * 查询限购商品已购买数量
 */
function get_for_purchasing_goods($start_date = 0, $end_date = 0, $goods_id = 0, $user_id = 0, $extension_code = '')
{
    $where = '';
    if (!empty($extension_code)) {
        $where = " AND oi.extension_code = '$extension_code'";
    }

    $where .= " AND oi.order_status <> " . OS_CANCELED;

    $sql = "SELECT og.goods_number FROM " . $GLOBALS['ecs']->table('order_goods') . " as og, " . $GLOBALS['ecs']->table('order_info') . " as oi " .
        "WHERE oi.order_id = og.order_id AND oi.user_id = " . $user_id .
        " AND og.goods_id = '" . $goods_id . "' AND oi.add_time > '" . $start_date . "' AND oi.add_time < '" . $end_date . "'" . $where;
    $res = $GLOBALS['db']->getAll($sql);

    $goods_number = 0;
    foreach ($res as $row) {
        $goods_number += $row['goods_number'];
    }

    return ['goods_number' => $goods_number];
}


/**
 * 查询限购商品已购买数量 by zxk  覆盖 get_for_purchasing_goods
 */
function get_for_purchasing_goods_new($act_id, $extension_code = '')
{
    $where = '';

    if (!empty($extension_code)) {
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


    $sql = "SELECT og.goods_number FROM " . $GLOBALS['ecs']->table('order_goods') . " as og, " . $GLOBALS['ecs']->table('order_info') . " as oi " .
        "WHERE oi.order_id = og.order_id " . " AND oi.extension_id = " . $act_id . $where;
    $res = $GLOBALS['db']->getAll($sql);

    $goods_number = 0;
    foreach ($res as $row) {
        $goods_number += $row['goods_number'];
    }

    return array('goods_number' => $goods_number);
}


/**
 * 查询店铺分类
 */
function get_fine_store_category($options, $web_type, $array_type = 0, $ru_id)
{
    $cat_array = [];
    if ($web_type == 'admin' || $web_type == 'goodsInfo') {
        $sql = "select cat_id, user_id from " . $GLOBALS['ecs']->table('merchants_category') . " where 1";
        $store_cat = $GLOBALS['db']->getAll($sql);

        foreach ($store_cat as $row) {
            $cat_array[$row['cat_id']]['cat_id'] = $row['cat_id'];
            $cat_array[$row['cat_id']]['user_id'] = $row['user_id'];
        }
    }

    if ($web_type == 'admin') {
        if ($cat_array) {
            if ($array_type == 0) {
                $options = array_diff_key($options, $cat_array);
            } else {
                $options = array_intersect_key($options, $cat_array);
            }
        }

        return $options;
    } elseif ($web_type == 'goodsInfo' && $ru_id == 0) {
        $options = array_diff_key($options, $cat_array);
        return $options;
    } else {
        return $options;
    }
}

//ecmoban模板堂 --zhuo end

/* 记录浏览历史 ecmoban模板堂 --zhuo start 浏览列表插件*/
function cate_history($size, $page, $sort, $order, $warehouse_id = 0, $area_id = 0, $ship = 0, $self = 0)
{
    $str = '';
    if (!empty($_COOKIE['ECS']['list_history'])) {
        $where = db_create_in($_COOKIE['ECS']['list_history'], 'g.goods_id');

        if ($self == 1) {
            $where .= " AND (g.user_id = 0 or msi.self_run = 1) ";
        }

        if ($ship == 1) { //ecmoban模板堂 --zhuo
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

        $sql = 'SELECT b.brand_name,g.is_shipping, g.goods_sn, g.brand_id, g.goods_id, g.goods_name, g.user_id, g.goods_thumb,g.sales_volume, g.user_id, msi.self_run, ' .
            "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, " .
            "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price " .
            ' FROM ' . $GLOBALS['ecs']->table('goods') . " as g " .
            " left join " . $GLOBALS['ecs']->table('brand') . " as b" . " on g.brand_id = b.brand_id " .
            $leftJoin .
            'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            " WHERE $where AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 group by g.goods_id  ORDER BY $sort $order";
        $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);

        $arr = [];
        foreach ($res as $row) {
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
            $price_other = [
                'market_price' => $row['market_price'],
                'org_price' => $row['org_price'],
                'shop_price' => $row['shop_price'],
                'promote_price' => $promote_price,
            ];

            $price_info = get_goods_one_attr_price($row['goods_id'], $warehouse_id, $area_id, $price_other);
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
            $arr[$row['goods_id']]['goods_thumb'] = get_image_path($row['goods_thumb']);

            $arr[$row['goods_id']]['shop_price'] = price_format($row['shop_price']);
            $arr[$row['goods_id']]['promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';

            $arr[$row['goods_id']]['brand_name'] = $row['brand_name'];
            $arr[$row['goods_id']]['url'] = build_uri('goods', ['gid' => $row['goods_id']], $row['goods_name']);
            $arr[$row['goods_id']]['brand_url'] = build_uri('brand', ['bid' => $row['brand_id']], $row['brand_name']);

            $sql = "select * from " . $GLOBALS['ecs']->table('seller_shopinfo') . " where ru_id='" . $row['user_id'] . "'";
            $basic_info = $GLOBALS['db']->getRow($sql);
            $arr[$row['goods_id']]['kf_type'] = $basic_info['kf_type'];
            $arr[$row['goods_id']]['kf_ww'] = $basic_info['kf_ww'];
            $arr[$row['goods_id']]['kf_qq'] = $basic_info['kf_qq'];

            $goods_id = $row['goods_id'];
            $count = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('comment') . " where id_value ='$goods_id' AND status = 1 AND parent_id = 0");
            $arr[$row['goods_id']]['review_count'] = $count;

            if ($row['user_id'] > 0) {
                $sql = "select shoprz_brandName, shopNameSuffix from " . $GLOBALS['ecs']->table('merchants_shop_information') . " where user_id='" . $row['user_id'] . "'";
                $shop_info = $GLOBALS['db']->getRow($sql);
                $arr[$row['goods_id']]['rz_shopName'] = $shop_info['shoprz_brandName'] . $shop_info['shopNameSuffix'];
            } else {
                $sql = "select shop_name from " . $GLOBALS['ecs']->table('seller_shopinfo') . " where ru_id='" . $row['user_id'] . "'";
                $shop_name = $GLOBALS['db']->getOne($sql);
                $arr[$row['goods_id']]['rz_shopName'] = $shop_name;
            }
            $arr[$row['goods_id']]['user_id'] = $row['user_id'];
            $arr[$row['goods_id']]['is_shipping'] = $row['is_shipping'];
            $arr[$row['goods_id']]['self_run'] = $row['self_run'];
            $arr[$row['goods_id']]['store_url'] = build_uri('merchants_store', ['urid' => $row['user_id']], $arr[$row['goods_id']]['rz_shopName']);

            $arr[$row['goods_id']]['count'] = selled_count($row['goods_id']);

            $mc_all = ments_count_all($row['goods_id']);       //总条数
            $mc_one = ments_count_rank_num($row['goods_id'], 1);        //一颗星
            $mc_two = ments_count_rank_num($row['goods_id'], 2);        //两颗星
            $mc_three = ments_count_rank_num($row['goods_id'], 3);    //三颗星
            $mc_four = ments_count_rank_num($row['goods_id'], 4);        //四颗星
            $mc_five = ments_count_rank_num($row['goods_id'], 5);        //五颗星
            $arr[$row['goods_id']]['zconments'] = get_conments_stars($mc_all, $mc_one, $mc_two, $mc_three, $mc_four, $mc_five);

            $arr[$row['goods_id']]['is_collect'] = get_collect_user_goods($row['goods_id']);
            $arr[$row['goods_id']]['pictures'] = get_goods_gallery($row['goods_id']); // 商品相册

            /*  @author-bylu 判断当前商家是否允许"在线客服" */
            $shop_information = get_shop_name($row['user_id']); //通过ru_id获取到店铺信息;
            $arr[$row['goods_id']]['is_IM'] = $shop_information['is_IM']; //平台是否允许商家使用"在线客服";
            if ($row['user_id'] == 0) {
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
    if (!empty($_COOKIE['ECS']['list_history'])) {
        $where = db_create_in($_COOKIE['ECS']['list_history'], 'g.goods_id');
        $sql = 'SELECT b.brand_name, g.brand_id, g.goods_id, g.goods_name, g.goods_thumb, g.shop_price, g.promote_price, g.is_promote FROM ' . $GLOBALS['ecs']->table('goods') . " as g left join " . $GLOBALS['ecs']->table('brand') . " as b" . " on g.brand_id = b.brand_id " .
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
 * @param type $cause_id 自增id
 * @param type $re_type 返回的类型: 值为真时返回下拉列表,否则返回数组
 * @param type $level 限定返回的级数。为0时返回所有级数
 * @param type $is_show_all 如果为true显示所有分类，如果为false隐藏不可见分类。
 * @return string
 */
function cause_list($cause_id = 0, $selected = 0, $re_type = true, $level = 0, $is_show_all = true)
{
    static $res = null;

    if ($res === null) {
        // $data = array();
        //$data = read_static_cache('cause_pid_releate');

        $sql = "SELECT c.cause_id, c.cause_name, c.sort_order, c.is_show ,c.parent_id , COUNT(s.cause_id) AS has_children " .
            'FROM ' . $GLOBALS['ecs']->table('return_cause') . " AS c " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('return_cause') . " AS s ON s.parent_id=c.cause_id " .
            "GROUP BY c.cause_id " .
            'ORDER BY c.parent_id, c.sort_order ASC';
        $res = $GLOBALS['db']->getAll($sql);
        //如果数组过大，不采用静态缓存方式
        if (count($res) <= 1000) {
            write_static_cache('cause_pid_releate', $res);
        }
    }

    if (empty($res) == true) {
        return $re_type ? '' : [];
    }

    $options = cause_options($cause_id, $res); // 获得指定分类下的子分类的数组

    $children_level = 99999; //大于这个分类的将被删除
    if ($is_show_all == false) {
        foreach ($options as $key => $val) {
            if ($val['level'] > $children_level) {
                unset($options[$key]);
            } else {
                if ($val['is_show'] == 0) {
                    unset($options[$key]);
                    if ($children_level > $val['level']) {
                        $children_level = $val['level']; //标记一下，这样子分类也能删除
                    }
                } else {
                    $children_level = 99999; //恢复初始值
                }
            }
        }
    }
    /* 截取到指定的缩减级别 */
    if ($level > 0) {
        if ($cause_id == 0) {
            $end_level = $level;
        } else {
            $first_item = reset($options); // 获取第一个元素
            $end_level = $first_item['level'] + $level;
        }

        /* 保留level小于end_level的部分 */
        foreach ($options as $key => $val) {
            if ($val['level'] >= $end_level) {
                unset($options[$key]);
            }
        }
    }

    if ($re_type == true) {
        $select = '';
        foreach ($options as $var) {
            $select .= '<option value="' . $var['cause_id'] . '" ';
            $select .= ($selected == $var['cause_id']) ? "selected='ture'" : '';
            $select .= '>';
            if ($var['level'] > 0) {
                $select .= str_repeat('&nbsp;', $var['level'] * 4);
            }
            $select .= htmlspecialchars(addslashes($var['cause_name']), ENT_QUOTES) . '</option>';
        }

        return $select;
    } else {
        foreach ($options as $key => $value) {
            $options[$key]['url'] = build_uri('reutrn_cause', ['cid' => $value['cause_id']], $value['cause_name']);
        }

        return $options;
    }
}

/**
 * 获取顶部退换货原因 by Leah
 */
function get_parent_cause()
{
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('return_cause') . " WHERE parent_id = 0  AND is_show = 1  ORDER BY sort_order";
    $result = $GLOBALS['db']->getAll($sql);
    if (is_array($result)) {
        $select = '';
        foreach ($result as $var) {
            $select .= '<option value="' . $var['cause_id'] . '" ';
            $select .= ($selected == $var['cause_id']) ? "selected='ture'" : '';
            $select .= '>';
            if ($var['level'] > 0) {
                $select .= str_repeat('&nbsp;', $var['level'] * 4);
            }
            $select .= htmlspecialchars(addslashes($var['cause_name']), ENT_QUOTES) . '</option>';
        }

        return $select;
    } else {
        return [];
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
    static $cat_options = [];

    if (isset($cat_options[$spec_cat_id])) {
        return $cat_options[$spec_cat_id];
    }

    if (!isset($cat_options[0])) {
        $level = $last_cat_id = 0;
        $options = $cat_id_array = $level_array = [];
        //$data = read_static_cache('cause_option_static');
        //$data = array();
        //        if ($data === false)
        //        {
        while (!empty($arr)) {
            foreach ($arr as $key => $value) {
                $cat_id = $value['cause_id'];
                if ($level == 0 && $last_cat_id == 0) {
                    if ($value['parent_id'] > 0) {
                        break;
                    }

                    $options[$cat_id] = $value;
                    $options[$cat_id]['level'] = $level;
                    $options[$cat_id]['id'] = $cat_id;
                    $options[$cat_id]['name'] = $value['cause_name'];
                    unset($arr[$key]);

                    if ($value['has_children'] == 0) {
                        continue;
                    }
                    $last_cat_id = $cat_id;
                    $cat_id_array = [$cat_id];
                    $level_array[$last_cat_id] = ++$level;
                    continue;
                }

                if ($value['parent_id'] == $last_cat_id) {
                    $options[$cat_id] = $value;
                    $options[$cat_id]['level'] = $level;
                    $options[$cat_id]['id'] = $cat_id;
                    $options[$cat_id]['name'] = $value['cause_name'];
                    unset($arr[$key]);

                    if ($value['has_children'] > 0) {
                        if (end($cat_id_array) != $last_cat_id) {
                            $cat_id_array[] = $last_cat_id;
                        }
                        $last_cat_id = $cat_id;
                        $cat_id_array[] = $cat_id;
                        $level_array[$last_cat_id] = ++$level;
                    }
                } elseif ($value['parent_id'] > $last_cat_id) {
                    break;
                }
            }

            $count = count($cat_id_array);
            if ($count > 1) {
                $last_cat_id = array_pop($cat_id_array);
            } elseif ($count == 1) {
                if ($last_cat_id != end($cat_id_array)) {
                    $last_cat_id = end($cat_id_array);
                } else {
                    $level = 0;
                    $last_cat_id = 0;
                    $cat_id_array = [];
                    continue;
                }
            }

            if ($last_cat_id && isset($level_array[$last_cat_id])) {
                $level = $level_array[$last_cat_id];
            } else {
                $level = 0;
            }
        }
        //如果数组过大，不采用静态缓存方式
        if (count($options) <= 2000) {
            // write_static_cache('cause_option_static', $options);
        }
        //        }
        //        else
        //        {
        //            $options = $data;
        //        }
        $cat_options[0] = $options;
    } else {
        $options = $cat_options[0];
    }

    if (!$spec_cat_id) {
        return $options;
    } else {
        if (empty($options[$spec_cat_id])) {
            return [];
        }

        $spec_cat_id_level = $options[$spec_cat_id]['level'];

        foreach ($options as $key => $value) {
            if ($key != $spec_cat_id) {
                unset($options[$key]);
            } else {
                break;
            }
        }

        $spec_cat_id_array = [];
        foreach ($options as $key => $value) {
            if (($spec_cat_id_level == $value['level'] && $value['cause_id'] != $spec_cat_id) ||
                ($spec_cat_id_level > $value['level'])
            ) {
                break;
            } else {
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
 * @param   string $order_sn 订单编号
 * @param   integer $order_status 订单状态
 * @param   integer $shipping_status 配送状态
 * @param   integer $pay_status 付款状态
 * @param   string $note 备注
 * @param   string $username 用户名，用户自己的操作则为 buyer
 * @return  void
 */
function return_action($ret_id, $return_status, $refound_status, $note = '', $username = null, $place = 0)
{
    if (is_null($username)) {
        $username = $_SESSION['admin_name'];
    }

    $sql = 'INSERT INTO ' . $GLOBALS['ecs']->table('return_action') .
        ' (ret_id, action_user, return_status, refound_status, action_place, action_note, log_time) ' .
        'SELECT ' .
        "ret_id, '$username', '$return_status', '$refound_status', '$place', '$note', '" . gmtime() . "' " .
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
    $imaegs = [];
    foreach ($singles as $k => $v) {
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
function get_single_detaile($goods_id, $order_id = 0)
{
    if (empty($order_id)) {
        $order_where = '';
    } else {
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
 * @param array (array()) $arr
 * @param key $keys
 * @param ASC | DESC $type
 * @return $new_array array(array())
 *
 * @author guan
 */
function dimensional_array_sort($arr, $keys, $type = 'DESC')
{
    $keysvalue = $new_array = [];
    foreach ($arr as $k => $v) {
        $keysvalue[$k] = $v[$keys];
    }
    if ($type == 'ASC') {
        asort($keysvalue);
    } else {
        arsort($keysvalue);
    }
    reset($keysvalue);
    foreach ($keysvalue as $k => $v) {
        $new_array[$k] = $arr[$k];
    }
    return $new_array;
}

//获取店铺排名 by zxk
function get_store_list_top10($cat_id) {
    $whereShop = " 1 ";

    $where_table = '';
    $select = '';


    $select .= ", (SELECT SUM(og.goods_number) FROM " . $GLOBALS['ecs']->table('order_info') . " AS oi, " . $GLOBALS['ecs']->table('order_goods') . " AS og " .
        " WHERE oi.order_id = og.order_id AND og.ru_id = msi.user_id " .
        " AND (oi.order_status = '" . OS_CONFIRMED . "' OR  oi.order_status = '" . OS_SPLITED . "' OR oi.order_status = '" . OS_SPLITING_PART . "') " .
        " AND (oi.pay_status  = '" . PS_PAYING . "' OR  oi.pay_status  = '" . PS_PAYED . "')) AS sales_volume ";

    $select .= ", ((SELECT SUM(g.goods_number) FROM " . $GLOBALS['ecs']->table('goods') . " AS g " .
        " WHERE g.user_id = msi.user_id AND g.review_status > 2)) AS goods_number ";

    if ($cat_id) {
        $whereShop .= " AND msi.shop_categoryMain = ".$cat_id;
    }

    $sql = "SELECT msi.shop_id, msi.user_id, msi.shoprz_brandName, msi.shopNameSuffix $select FROM " .
        $GLOBALS['ecs']->table('merchants_shop_information') . " as msi LEFT JOIN " . $GLOBALS['ecs']->table('merchants_grade') . " AS mg ON mg.ru_id = msi.user_id " . $where_table . " where $whereShop" .
        " AND msi.merchants_audit = 1 ORDER BY sales_volume desc, goods_number desc";

    $res = $GLOBALS['db']->selectLimit($sql, 10, 0);

    $arr = [];
    foreach ($res as $row) {
        $_arr['shop_id'] = $row['shop_id'];
        $_arr['shop_id'] = $row['shop_id'];
        $_arr['shopNameSuffix'] = $row['shopNameSuffix'];
        $_arr['shopName'] = get_shop_name($row['user_id'], 1); //店铺名称
        $_arr['sales_volume'] = !empty($row['sales_volume']) ? $row['sales_volume'] : 0;

        $shop_info = get_shop_info_content($row['user_id']);
        $_arr['shop_logo'] = str_replace('../', '', $shop_info['shop_logo']); //商家logo
        $_arr['logo_thumb'] = str_replace('../', '', $shop_info['logo_thumb']); //商家缩略图
        $_arr['street_thumb'] = get_image_path(ltrim($shop_info['street_thumb'], '../')); //店铺街封面图

        //店铺地址
        $_arr['shop_url'] = build_uri('store', ['stid' => $row['user_id']]);
        $arr[] = $_arr;
    }

    $result = ['shop_list' => $arr];
    return $result;

}

//店铺搜索 start
function get_store_shop_list($libType = 0, $keywords = '', $count = 0, $size = 15, $page = 1, $sort = 'shop_id', $order = 'DESC', $warehouse_id = 0, $area_id = 0, $store_province = 0, $store_city = 0, $store_district = 0, $store_user = '')
{
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

    $substr = substr($id, -1);
    if ($substr == "|") {
        $id = substr($id, 0, -1);
    }

    $id .= '"';


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
    $sql = "SELECT msi.shop_id, msi.user_id, msi.shoprz_brandName, msi.shopNameSuffix $select FROM " .
        $GLOBALS['ecs']->table('merchants_shop_information') . " as msi LEFT JOIN " . $GLOBALS['ecs']->table('merchants_grade') . " AS mg ON mg.ru_id = msi.user_id " . $where_table . " where $whereShop" .
        " AND msi.merchants_audit = 1 ORDER BY $sort $order ";

    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);

    $arr = [];
    foreach ($res as $row) {
        $arr[$row['shop_id']]['shop_id'] = $row['shop_id'];
        $arr[$row['shop_id']]['shoprz_brandName'] = $row['shoprz_brandName'];
        $arr[$row['shop_id']]['shopNameSuffix'] = $row['shopNameSuffix'];
        $arr[$row['shop_id']]['shop_name'] = get_shop_name($row['user_id'], 3); //店铺名称
        $arr[$row['shop_id']]['shopName'] = get_shop_name($row['user_id'], 1); //店铺名称
        $arr[$row['shop_id']]['brand_list'] = get_shop_brand_list($row['user_id']); //商家品牌
        $arr[$row['shop_id']]['address'] = get_shop_address_info($row['user_id']); //商家所在位置
        $arr[$row['shop_id']]['sales_volume'] = !empty($row['sales_volume']) ? $row['sales_volume'] : 0;

        $shop_info = get_shop_info_content($row['user_id']);
        $arr[$row['shop_id']]['shop_logo'] = str_replace('../', '', $shop_info['shop_logo']); //商家logo
        $arr[$row['shop_id']]['logo_thumb'] = str_replace('../', '', $shop_info['logo_thumb']); //商家缩略图
        $arr[$row['shop_id']]['street_thumb'] = $shop_info['street_thumb']; //店铺街封面图
        $arr[$row['shop_id']]['brand_thumb'] = $shop_info['brand_thumb']; //店铺街品牌图
        $arr[$row['shop_id']]['street_desc'] = $shop_info['street_desc']; //店铺街描述
        $arr[$row['shop_id']]['merch_cmt'] = get_merchants_goods_comment($row['user_id']); //商家总体评分

        $arr[$row['shop_id']]['shopNameSuffix'] = $row['shopNameSuffix'];

        $arr[$row['shop_id']]['ru_id'] = $row['user_id'];
        $arr[$row['shop_id']]['shop_url'] = build_uri('merchants_store', ['cid' => 0, 'urid' => $row['user_id']], $arr[$row['shop_id']]['shop_name']);
        $arr[$row['shop_id']]['goods_count'] = get_shop_goods_count_list($row['user_id'], $warehouse_id, $area_id); //商品数量
        $arr[$row['shop_id']]['goods_list'] = get_shop_goods_count_list($row['user_id'], $warehouse_id, $area_id, 1); //商品数量
    }

    $result = ['shop_list' => $arr, 'pager' => $pager];
    return $result;
}

//店铺搜索数量
function get_store_shop_count($keywords = '', $sort = 'shop_id', $store_province = 0, $store_city = 0, $store_district = 0, $store_user = '')
{
    $whereShop = " 1 ";
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

    $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('merchants_shop_information') . " AS msi" . $where_table . " where $whereShop" .
        " AND msi.is_street = 1 AND msi.merchants_audit = 1";

    return $GLOBALS['db']->getOne($sql);
}

function get_store_shop_goods_list($keywords = '', $size, $page, $sort, $order, $warehouse_id, $area_id)
{
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
            if (strpos($user_list, ",") !== false) {
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

    $select = "g.goods_id, g.goods_thumb,g.is_shipping, g.goods_name, g.user_id, g.promote_start_date, g.promote_end_date, g.market_price, msi.self_run, ";
    $select .= "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, ";
    $select .= "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price";

    $leftJoin .= 'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
        "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' ";

    $sql = "SELECT " . $select . " FROM " . $GLOBALS['ecs']->table('goods') . " as g " .
        $leftJoin .
        " where $whereGodds ORDER BY $sort $order";

    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);
    foreach ($res as $row) {
        if ($row['promote_price'] > 0) {
            $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
        } else {
            $promote_price = 0;
        }

        $arr[$row['goods_id']]['market_price'] = price_format($row['market_price']);
        $arr[$row['goods_id']]['shop_price'] = price_format($row['shop_price']);
        $arr[$row['goods_id']]['promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';

        $arr[$row['goods_id']]['goods_id'] = $row['goods_id'];
        $arr[$row['goods_id']]['goods_name'] = $row['goods_name'];
        $arr[$row['goods_id']]['goods_thumb'] = get_image_path($row['goods_thumb']);
        $arr[$row['goods_id']]['goods_url'] = build_uri('goods', ['gid' => $row['goods_id']], $row['goods_name']);
        $arr[$row['goods_id']]['order_count'] = selled_count($row['goods_id']);
        $arr[$row['goods_id']]['is_shipping'] = $row['is_shipping'];
        $arr[$row['goods_id']]['self_run'] = $row['self_run'];
        $basic_info = get_shop_info_content($row['user_id']);
        $arr[$row['goods_id']]['kf_type'] = $basic_info['kf_type'];
        $arr[$row['goods_id']]['kf_ww'] = $basic_info['kf_ww'];
        $arr[$row['goods_id']]['kf_qq'] = $basic_info['kf_qq'];

        $arr[$row['goods_id']]['shop_name'] = get_shop_name($row['user_id'], 1); //店铺名称
        $arr[$row['goods_id']]['shop_url'] = build_uri('merchants_store', ['cid' => 0, 'urid' => $row['user_id']], $arr[$key]['shop_name']);

        $cmt_count = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('comment') . " where id_value ='" . $row['goods_id'] . "' AND status = 1 AND parent_id = 0");
        $arr[$row['goods_id']]['cmt_count'] = $cmt_count;
        $arr[$row['goods_id']]['brand_list'] = get_shop_brand_list($row['user_id']); //商家品牌

        $arr[$row['goods_id']]['is_collect'] = get_collect_user_goods($row['goods_id']);
    }

    return $arr;
}

function get_store_shop_goods_count($keywords, $sort)
{
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
            if (strpos($user_list, ",") !== false) {
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
function get_shop_brand_list($user_id = 0)
{
    $sql = "SELECT bid as brand_id, brandName as brand_name FROM " . $GLOBALS['ecs']->table('merchants_shop_brand') . " where user_id = '$user_id' AND audit_status = 1 order by bid ASC";
    return $res = $GLOBALS['db']->getAll($sql);
}

//商家所在位置
function get_shop_address_info($user_id = 0)
{
    $res = get_shop_info_content($user_id);
    $province = get_shop_address($res['province']);
    $city = get_shop_address($res['city']);
    $region = $province . str_repeat("&nbsp;", 2) . $city;

    return $region;
}

function get_shop_address($region, $type = 0)
{
    if ($type == 1) {
        $region = str_replace(['省', '市'], '', $region);
        $select = "region_id";
        $where = "region_name = '$region'";
    } else {
        $select = "region_name";
        $where = "region_id = '$region'";
    }

    $sql = "SELECT " . $select . " FROM " . $GLOBALS['ecs']->table('region') . " where " . $where;
    return $GLOBALS['db']->getOne($sql);
}

//店铺信息
function get_shop_info_content($user_id = 0)
{
    $sql = "SELECT country, province, city, shop_logo, logo_thumb, street_thumb, brand_thumb, street_desc, kf_type, kf_ww, kf_qq FROM " . $GLOBALS['ecs']->table('seller_shopinfo') . " where ru_id = '$user_id'";
    return $GLOBALS['db']->getRow($sql);
}

//商家商品数量
function get_shop_goods_count_list($user_id, $warehouse_id, $area_id, $type = 0, $isType = '', $show_type = 0)
{
    $leftJoin = '';
    $where = "1";

    if ($GLOBALS['_CFG']['review_goods'] == 1) {
        $where .= ' AND g.review_status > 2 ';
    }
    if ($type == 1) {
        $arr = [];

        $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

        if ($GLOBALS['_CFG']['open_area_goods'] == 1) {
            $leftJoin .= " left join " . $GLOBALS['ecs']->table('link_area_goods') . " as lag on g.goods_id = lag.goods_id ";
            $where .= " AND lag.region_id = '$area_id' ";
        }

        $leftJoin .= 'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' ";

        //$where .= ' AND (store_hot = 1 OR store_new = 1 OR store_best = 1)';
        $select = "g.goods_id, g.goods_thumb, g.goods_name, g.user_id, g.promote_start_date, g.promote_end_date, g.market_price, ";
        $select .= "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, ";
        $select .= "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price";
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
            $price_other = [
                'market_price' => $row['market_price'],
                'org_price' => $row['org_price'],
                'shop_price' => $row['shop_price'],
                'promote_price' => $promote_price,
            ];

            $price_info = get_goods_one_attr_price($row['goods_id'], $warehouse_id, $area_id, $price_other);
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
            $arr[$key]['goods_thumb'] = get_image_path($row['goods_thumb']);
            $arr[$key]['goods_url'] = build_uri('goods', ['gid' => $row['goods_id']], $row['goods_name']);
            $arr[$key]['order_count'] = selled_count($row['goods_id']);

            $basic_info = get_shop_info_content($row['user_id']);
            $arr[$key]['kf_type'] = $basic_info['kf_type'];
            $arr[$key]['kf_ww'] = $basic_info['kf_ww'];
            $arr[$key]['kf_qq'] = $basic_info['kf_qq'];

            $arr[$key]['shop_name'] = get_shop_name($row['user_id'], 1); //店铺名称
            $arr[$key]['shop_url'] = build_uri('merchants_store', ['cid' => 0, 'urid' => $row['user_id']], $arr[$key]['shop_name']);

            $cmt_count = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('comment') . " where id_value ='" . $row['goods_id'] . "' AND status = 1 AND parent_id = 0");
            $arr[$key]['cmt_count'] = $cmt_count;
        }

        return $arr;
    } else {
        return $GLOBALS['db']->getOne($sql);
    }
}

//商家商品数量
function get_shop_goods_cmt_list($user_id, $warehouse_id, $area_id, $price_min, $price_max, $page, $size, $sort, $order)
{
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

    $select = "g.goods_id, g.goods_thumb, g.goods_name, g.user_id, g.promote_start_date, g.promote_end_date, g.market_price, ";
    $select .= "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, ";
    $select .= "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price";

    if ($sort == 'last_update') {
        $sort = 'g.last_update';
    }

    $sql = "SELECT " . $select . " FROM " . $GLOBALS['ecs']->table('goods') . " as g " . $leftJoin . " WHERE $where AND g.user_id = '$user_id'  ORDER BY $sort $order";
    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);

    foreach ($res as $row) {
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
        $price_other = [
            'market_price' => $row['market_price'],
            'org_price' => $row['org_price'],
            'shop_price' => $row['shop_price'],
            'promote_price' => $promote_price,
        ];

        $price_info = get_goods_one_attr_price($row['goods_id'], $warehouse_id, $area_id, $price_other);
        $row = !empty($row) ? array_merge($row, $price_info) : $row;
        $promote_price = $row['promote_price'];
        /**
         * 重定义商品价格
         * end
         */

        $arr[$row['goods_id']]['market_price'] = price_format($row['market_price']);
        $arr[$row['goods_id']]['shop_price'] = price_format($row['shop_price']);
        $arr[$row['goods_id']]['promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';

        $arr[$row['goods_id']]['goods_id'] = $row['goods_id'];
        $arr[$row['goods_id']]['goods_name'] = $row['goods_name'];
        $arr[$row['goods_id']]['goods_thumb'] = get_image_path($row['goods_thumb']);
        $arr[$row['goods_id']]['goods_url'] = build_uri('goods', ['gid' => $row['goods_id']], $row['goods_name']);
        $arr[$row['goods_id']]['order_count'] = selled_count($row['goods_id']);

        $basic_info = get_shop_info_content($row['user_id']);
        $arr[$row['goods_id']]['kf_type'] = $basic_info['kf_type'];
        $arr[$row['goods_id']]['kf_ww'] = $basic_info['kf_ww'];
        $arr[$row['goods_id']]['kf_qq'] = $basic_info['kf_qq'];

        $arr[$row['goods_id']]['shop_name'] = get_shop_name($row['user_id'], 1); //店铺名称
        $arr[$row['goods_id']]['shop_url'] = build_uri('merchants_store', ['cid' => 0, 'urid' => $row['user_id']], $arr[$key]['shop_name']);

        $cmt_count = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('comment') . " where id_value ='" . $row['goods_id'] . "' AND status = 1 AND parent_id = 0");
        $arr[$row['goods_id']]['cmt_count'] = $cmt_count;
        $arr[$row['goods_id']]['is_collect'] = get_collect_user_goods($row['goods_id']);
    }

    return $arr;
}

function get_shop_goods_cmt_count($user_id, $price_min, $price_max)
{
    $where = "";
    if ($GLOBALS['_CFG']['review_goods'] == 1) {
        $where .= ' AND review_status > 2 ';
    }

    if ($min > 0) {
        $where .= " AND IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) >= $min ";
    }

    if ($max > 0) {
        $where .= " AND IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) <= $max ";
    }

    $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('goods') . " WHERE user_id = '$user_id' AND is_on_sale = 1 AND is_alone_sale = 1 AND is_delete = 0 " . $where;
    $res = $GLOBALS['db']->getOne($sql);

    return $res;
}

//店铺搜索 end

function get_seller_info_area()
{
    $sql = "select province, city, district from " . $GLOBALS['ecs']->table('seller_shopinfo') . " where ru_id = 0";
    return $GLOBALS['db']->getRow($sql);
}

//确认订单的用户收货地址列表
function get_order_user_address_list($user_id)
{

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

    $arr = [];
    foreach ($res as $row) {
        $arr[] = $row;
    }

    return $arr;
}

/**  //ecmoban模板堂 --zhuo 可用、即将到期、已使用
 *
 * @access  public
 * @param   int $user_id 用户ID
 * @param   int $num 列表显示条数
 * @param   int $start 显示起始位置
 *
 * @return  array       $arr             红保列表
 */
function get_user_bouns_new_list($user_id = 0, $page = 1, $type = 0, $pageFunc = '', $amount = 0)
{
    $day = local_getdate();
    $cur_date = local_mktime(23, 59, 59, $day['mon'], $day['mday'], $day['year']);
    $before_date = local_mktime(0, 0, 0, $day['mon'], $day['mday'], $day['year']) - 2 * 24 * 3600; //前三天时间

    $useDate = " AND b.use_start_date < " . $cur_date . " AND b.use_end_date > " . $cur_date;

    if ($type == 0) {
        $uOrder = " AND u.order_id = 0";
        $arrName = "available_list";
    } elseif ($type == 1) {
        $uOrder = " AND u.order_id = 0";
        $useDate = " AND b.use_start_date >= " . $before_date . " AND b.use_end_date > " . $cur_date;
        $arrName = "expire_list";
    } elseif ($type == 2) {
        $uOrder = " AND u.order_id > 0";
        $arrName = "useup_list";
    }

    $record_count = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('user_bonus') . " as u," . $GLOBALS['ecs']->table('bonus_type') . " AS b" . " WHERE u.bonus_type_id = b.type_id " . $uOrder . " AND u.user_id = '$user_id' " . $useDate);

    $bouns_paper = '';
    $limit = '';
    if ($amount == 0) {
        $bouns = new Pager($record_count, $size, '', $user_id, 0, $page, $pageFunc, 1);
        $limit = $bouns->limit;
        $bouns_paper = $bouns->fpage([0, 4, 5, 6, 9]);
    }

    $sql = "SELECT  u.bonus_id, u.bonus_sn, u.order_id, u.bind_time, b.type_name, b.type_money,b.min_amount, b.min_goods_amount, b.use_start_date, b.use_end_date, " .
        "b.usebonus_type, b.user_id AS ru_id FROM " . $GLOBALS['ecs']->table('user_bonus') . " AS u ," .
        $GLOBALS['ecs']->table('bonus_type') . " AS b" .
        " WHERE u.bonus_type_id = b.type_id " . $uOrder . " AND u.user_id = '" . $user_id . "' " . $useDate . " order by u.bonus_id DESC " . $limit;
    $res = $GLOBALS['db']->getAll($sql);

    $arr = [];

    foreach ($res as $key => $row) {
        $arr[$key]['bonus_id'] = $row['bonus_id'];
        /* 先判断是否被使用，然后判断是否开始或过期 */

        if ($type < 2) {
            $arr[$key]['status'] = $GLOBALS['_LANG']['not_use'];
        } elseif ($type == 2) {
            $arr[$key]['status'] = '<a href="user.php?act=order_detail&order_id=' . $row['order_id'] . '" >' . $GLOBALS['_LANG']['had_use'] . '</a>';
        }


        $arr[$key]['shop_name'] = get_shop_name($row['ru_id'], 1); //店铺名称
        $arr[$key]['usebonus_type'] = $row['usebonus_type'];
        $arr[$key]['bonus_sn'] = $row['bonus_sn'];
        $arr[$key]['bouns_amount'] = $row['type_money'];
        $arr[$key]['type_money'] = price_format($row['type_money']);
        $arr[$key]['min_goods_amount'] = price_format($row['min_goods_amount']);

        $arr[$key]['use_startdate'] = local_date($GLOBALS['_CFG']['time_format'], $row['use_start_date']);
        $arr[$key]['use_enddate'] = local_date($GLOBALS['_CFG']['time_format'], $row['use_end_date']);
        $arr[$key]['bind_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['bind_time']);
    }

    $bouns = [$arrName => $arr, 'record_count' => $record_count, 'paper' => $bouns_paper];

    return $bouns;
}

//合算可用礼品卡总金额
function get_bouns_amount_list($bouns_list)
{
    $bouns_amount = 0;
    foreach ($bouns_list['available_list'] as $key => $row) {
        $bouns_amount += $row['bouns_amount'];
    }

    return price_format($bouns_amount);
}

// flow 和 flow_consignee公用
function get_update_flow_Consignee($address_id)
{
    $sql = "UPDATE " . $GLOBALS['ecs']->table('users') . " SET address_id = '$address_id' where user_id = '" . $_SESSION['user_id'] . "'";
    $GLOBALS['db']->query($sql);

    $sql = "select * from " . $GLOBALS['ecs']->table('user_address') . " where address_id = '$address_id'";

    return $GLOBALS['db']->getRow($sql);
}

/**
 * 调用进货单信息
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
        " WHERE " . $c_sess . " AND rec_type = '" . CART_GENERAL_GOODS . "' AND stages_qishu='-1' AND store_id = 0 " . $limit;
    $row = $GLOBALS['db']->GetAll($sql);
    $arr = [];
    $cart_value = '';
    foreach ($row as $k => $v) {
        $arr[$k]['goods_thumb'] = get_image_path($v['goods_thumb']);
        $arr[$k]['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
            sub_str($v['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $v['goods_name'];
        $arr[$k]['url'] = build_uri('goods', ['gid' => $v['goods_id']], $v['goods_name']);
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
            $arr[$k]['spe'] = [];
        }
    }

    $sql = 'SELECT SUM(goods_number) AS number, SUM(goods_price * goods_number) AS amount' .
        ' FROM ' . $GLOBALS['ecs']->table('cart') .
        " WHERE " . $sess_id . " AND rec_type = '" . CART_GENERAL_GOODS . "' AND stages_qishu='-1' AND store_id = 0 ";
    $row = $GLOBALS['db']->GetRow($sql);

    if ($row) {
        $number = intval($row['number']);
        $amount = floatval($row['amount']);
    } else {
        $number = 0;
        $amount = 0;
    }

    if ($type == 1) {
        $cart = ['goods_list' => $arr, 'number' => $number, 'amount' => price_format($amount, false)];

        return $cart;
    } elseif ($type == 2) {
        //by wang
        $cart = ['goods_list' => $arr, 'number' => $number, 'amount' => price_format($amount, false)];

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
function get_return_goods_url($goods_id = 0, $goods_name = '')
{
    if (empty($goods_name)) {
        $goods_name = $GLOBALS['db']->getOne("SELECT goods_name FROM " . $GLOBALS['ecs']->table('goods') . " WHERE goods_id = '$goods_id'");
    }

    $url = build_uri('goods', ['gid' => $goods_id], $goods_name);
    return $url;
}

//分类地址
function get_return_category_url($cat_id = 0)
{
    $cat_name = $GLOBALS['db']->getOne("SELECT cat_name FROM " . $GLOBALS['ecs']->table('category') . " WHERE cat_id = '$cat_id'");
    $url = build_uri('category', ['cid' => $cat_id], $cat_name);
    return $url;
}

//分类地址
function get_return_store_shop_url($ru_id = 0)
{
    $shop_name = get_shop_name($ru_id, 1);
    $url = build_uri('merchants_store_shop', ['urid' => $ru_id], $shop_name);
    return $url;
}

//搜索地址
function get_return_search_url($keywords = '')
{
    $url = build_uri('search', ['chkw' => $keywords], $keywords);
    return $url;
}

function get_return_self_url()
{
    $cur_url = $_SERVER["PHP_SELF"] . "?" . $_SERVER["QUERY_STRING"];
    $cur_url = explode('/', $cur_url);
    $cur_url = $cur_url[count($cur_url) - 1];

    return $cur_url;
}

//导航右边查询分类树 start
function get_category_tree_leve_one($parent_id = 0)
{
    $sql = "SELECT cat_id, cat_name, category_links FROM " . $GLOBALS['ecs']->table('category') . " WHERE parent_id = 0 AND is_show = 1 ORDER BY sort_order ASC, cat_id ASC";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = [];
    foreach ($res as $key => $row) {
        $arr[$row['cat_id']]['id'] = $row['cat_id'];
        $arr[$row['cat_id']]['url'] = build_uri('category', ['cid' => $row['cat_id']], $row['cat_name']);

        if (!empty($row['category_links'])) {
            if (empty($type)) {
                $cat_name_arr = explode('、', $row['cat_name']);
                if (!empty($cat_name_arr)) {
                    $category_links_arr = explode("\r\n", $row['category_links']);
                }

                $cat_name_str = "";
                foreach ($cat_name_arr as $cat_name_key => $cat_name_val) {
                    $link_str = $category_links_arr[$cat_name_key];

                    $cat_name_str .= '<a href="' . $link_str . '" target="_blank" class="division_cat">' . $cat_name_val;

                    if (count($cat_name_arr) == ($cat_name_key + 1)) {
                        $cat_name_str .= '</a>';
                    } else {
                        $cat_name_str .= '</a>、';
                    }
                }

                $arr[$row['cat_id']]['name'] = $cat_name_str;
                $arr[$row['cat_id']]['category_link'] = 1;
                $arr[$row['cat_id']]['oldname'] = $row['cat_name'];//by EcMoban-weidong   保留原生元素
            } else {
                $arr[$row['cat_id']]['name'] = $row['cat_name'];
                $arr[$row['cat_id']]['oldname'] = $row['cat_name'];//by EcMoban-weidong   保留原生元素
            }
        } else {
            $arr[$row['cat_id']]['name'] = $row['cat_name'];
        }
    }

    return $arr;
}

//查询顶级分类的子分类
function get_category_parentChild_tree1($parent_id = 0, $cat_type = 0, $cat_list = 0)
{
    $sql = "SELECT cat_id,cat_alias_name, cat_name FROM " . $GLOBALS['ecs']->table('category') . " WHERE parent_id = '$parent_id' ORDER BY sort_order ASC, cat_id ASC";
    $res = $GLOBALS['db']->getAll($sql);

    $new_arr = [];
    $arr = [];
    if ($res) {
        foreach ($res as $key => $row) {
            $arr[$row['cat_id']]['id'] = $row['cat_id'];

            if ($cat_type == 0) {
                $arr[$row['cat_id']]['cat_name'] = $row['cat_name'];
                $arr[$row['cat_id']]['level'] = 1;
                $arr[$row['cat_id']]['cat_alias_name'] = $row['cat_alias_name'];
                $arr[$row['cat_id']]['name'] = $row['cat_name'];
                $arr[$row['cat_id']]['url'] = build_uri('category', ['cid' => $row['cat_id']], $row['cat_name']);
            }
            $arr[$row['cat_id']]['cat_id'] = get_category_parentChild_tree2($row['cat_id'], $cat_type);
            if ($cat_list == 1) {
                $r = [$row['cat_id'] => [
                    "cat_id" => $arr[$row['cat_id']]['id'],
                    "cat_name" => $arr[$row['cat_id']]['cat_name'],
                    "level" => $arr[$row['cat_id']]['level'],
                    "id" => $arr[$row['cat_id']]['id'],
                    "name" => $row['cat_name'],
                    "cat_alias_name" => $row['cat_alias_name'],
                    'url' => build_uri('category', ['cid' => $row['cat_id']], $row['cat_name'])]
                ];
                array_push($new_arr, $r, $arr[$row['cat_id']]['cat_id']);
            }
        }
        if ($cat_list == 1) {
            $new_arr = get_new_arr_one_tree($new_arr);
        }
    }
    return $arr;
}

function get_category_parentChild_tree2($parent_id = 0, $cat_type = 0)
{
    $sql = "SELECT cat_id, cat_name FROM " . $GLOBALS['ecs']->table('category') . " WHERE parent_id = '$parent_id' ORDER BY sort_order ASC, cat_id ASC";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = [];
    foreach ($res as $key => $row) {
        $arr[$row['cat_id']]['id'] = $row['cat_id'];

        if ($cat_type == 0) {
            $arr[$row['cat_id']]['cat_name'] = $row['cat_name'];
            $arr[$row['cat_id']]['level'] = 2;
            $arr[$row['cat_id']]['cat_id'] = $row['cat_id'];
            $arr[$row['cat_id']]['name'] = $row['cat_name'];
            $arr[$row['cat_id']]['url'] = build_uri('category', ['cid' => $row['cat_id']], $row['cat_name']);
        }
    }

    if ($cat_list == 1) {
        return $new_arr;
    } else {
        return $arr;
    }
}

function get_new_arr_one_tree($arr)
{
    $new_arr = [];
    $r = [];
    if ($arr) {
        foreach ($arr as $rows) {
            foreach ($rows as $row) {
                array_push($new_arr, $row);
            }
        }
    }

    return $new_arr;
}

function get_category_brands_ad($cat_id)
{
    $arr['ad_position'] = '';
    $arr['brands'] = '';

    $cat_name = '';
    for ($i = 1; $i <= $GLOBALS['_CFG']['auction_ad']; $i++) {
        $cat_name .= "'cat_tree_" . $cat_id . "_" . $i . "',";
    }

    $cat_name = substr($cat_name, 0, -1);
    $arr['ad_position'] = get_ad_posti_child($cat_name);

    $children = get_category_parentChild_tree1($cat_id, 1);
    $children = arr_foreach($children);

    if ($children) {
        $children = implode(",", $children) . "," . $cat_id;
        $children = get_children($children, 0, 1);
    } else {
        $children = "g.cat_id IN ($cat_id)";
    }

    // 获取分类下品牌
    $sql = "SELECT b.brand_id, b.brand_name,  b.brand_logo, COUNT(*) AS goods_num, IF(b.brand_logo > '', '1', '0') AS tag " .
        "FROM " . $GLOBALS['ecs']->table('brand') . "AS b " .
        " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON g.brand_id = b.brand_id AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 " .
        " LEFT JOIN " . $GLOBALS['ecs']->table('goods_cat') . " AS gc ON g.goods_id = gc.goods_id " .
        "WHERE ($children OR " . 'gc.cat_id ' . db_create_in(array_unique(array_merge([$cat_id], array_keys(cat_list($cat_id, 0, 0))))) . ") AND b.is_show = 1 " .
        "GROUP BY b.brand_id HAVING goods_num > 0 ORDER BY b.sort_order, b.brand_id ASC LIMIT 0,12";

    $brands = $GLOBALS['db']->getAll($sql);

    $sql = "SELECT cat_name FROM " . $GLOBALS['ecs']->table('category') . " WHERE cat_id = '$cat_id' LIMIT 1";
    $name = $GLOBALS['db']->getOne($sql);

    foreach ($brands as $key => $val) {
        $temp_key = $key;
        $brands[$temp_key]['brand_name'] = $val['brand_name'];
        $brands[$temp_key]['url'] = build_uri('category', ['cid' => $cat_id, 'bid' => $val['brand_id']], $name);

        // 判断品牌是否被选中
        if ($brand == $brands[$key]['brand_id']) {
            $brands[$temp_key]['selected'] = 1;
        } else {
            $brands[$temp_key]['selected'] = 0;
        }
    }

    $arr['brands'] = $brands;

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
function get_parent_cat_tree($cat_id)
{
    //顶级分类页分类显示
    $categories_child = read_static_cache('cat_top_cache' . $cat_id);

    //将数据写入缓存文件 by wang
    if (!$categories_child) {
        $categories_child = get_parent_cat_child($cat_id);
        write_static_cache('cat_top_cache' . $cat_id, $categories_child);
    }

    return $categories_child;
}

//顶级分类页导航 end


/**
 * 友好的时间显示
 *
 * @param int $sTime 待显示的时间
 * @param string $type 类型. normal | mohu | full | ymd | other
 * @param string $alt 已失效
 * @return string
 */
function friendlyDate($sTime, $type = 'normal', $alt = 'false')
{
    if (!$sTime) {
        return '';
    }
    //sTime=源时间，cTime=当前时间，dTime=时间差
    $cTime = time();
    $dTime = $cTime - $sTime;
    $dDay = intval(date("z", $cTime)) - intval(date("z", $sTime));
    //$dDay     =   intval($dTime/3600/24);
    $dYear = intval(date("Y", $cTime)) - intval(date("Y", $sTime));
    //normal：n秒前，n分钟前，n小时前，日期
    if ($type == 'normal') {
        if ($dTime < 60) {
            if ($dTime < 10) {
                return '刚刚';    //by yangjs
            } else {
                return intval(floor($dTime / 10) * 10) . "秒前";
            }
        } elseif ($dTime < 3600) {
            return intval($dTime / 60) . "分钟前";
            //今天的数据.年份相同.日期相同.
        } elseif ($dYear == 0 && $dDay == 0) {
            //return intval($dTime/3600)."小时前";
            return '今天' . date('H:i', $sTime);
        } elseif ($dYear == 0) {
            return date("m月d日 H:i", $sTime);
        } else {
            return date("Y-m-d H:i", $sTime);
        }
    } elseif ($type == 'mohu') {
        if ($dTime < 60) {
            return $dTime . "秒前";
        } elseif ($dTime < 3600) {
            return intval($dTime / 60) . "分钟前";
        } elseif ($dTime >= 3600 && $dDay == 0) {
            return intval($dTime / 3600) . "小时前";
        } elseif ($dDay > 0 && $dDay <= 7) {
            return intval($dDay) . "天前";
        } elseif ($dDay > 7 && $dDay <= 30) {
            return intval($dDay / 7) . '周前';
        } elseif ($dDay > 30) {
            return intval($dDay / 30) . '个月前';
        }
        //full: Y-m-d , H:i:s
    } elseif ($type == 'full') {
        return date("Y-m-d , H:i:s", $sTime);
    } elseif ($type == 'ymd') {
        return date("Y-m-d", $sTime);
    } elseif ($type == 'moremohu') {
        if ($dTime < 60) {
            return $dTime . "秒前";
        } elseif ($dTime < 3600) {
            return intval($dTime / 60) . "分钟前";
        } elseif ($dTime >= 3600 && $dDay == 0) {
            return intval($dTime / 3600) . "小时前";
        } elseif ($dDay > 0 && $dDay <= 7) {
            return intval($dDay) . "天前";
        } elseif ($dDay > 7 && $dDay <= 30) {
            return intval($dDay / 7) . '周前';
        } else {
            return '一个月前';
        }
    } else {
        if ($dTime < 60) {
            return $dTime . "秒前";
        } elseif ($dTime < 3600) {
            return intval($dTime / 60) . "分钟前";
        } elseif ($dTime >= 3600 && $dDay == 0) {
            return intval($dTime / 3600) . "小时前";
        } elseif ($dYear == 0) {
            return date("Y-m-d H:i:s", $sTime);
        } else {
            return date("Y-m-d H:i:s", $sTime);
        }
    }
}

//OSS添加图片
function get_oss_add_file($file = [])
{
    //OSS文件存储ecmoban模板堂 --zhuo start
    if ($file) {
        if ($GLOBALS['_CFG']['open_oss'] == 1) {
            $urlip = get_ip_url(dirname($GLOBALS['ecs']->url()));
            $bucket_info = get_bucket_info();
            $url = $urlip . "oss.php?act=upload";
            $Http = new Http();
            $post_data = [
                'bucket' => $bucket_info['bucket'],
                'keyid' => $bucket_info['keyid'],
                'keysecret' => $bucket_info['keysecret'],
                'is_cname' => $bucket_info['is_cname'],
                'endpoint' => $bucket_info['outside_site'],
                'object' => $file
            ];
            $Http->doPost($url, $post_data);
        }
        //OSS文件存储ecmoban模板堂 --zhuo end
    }
}

//OSS删除图片
function get_oss_del_file($file = [])
{
    //OSS文件存储ecmoban模板堂 --zhuo start
    if ($file) {
        //OSS文件存储ecmoban模板堂 --zhuo start
        if ($GLOBALS['_CFG']['open_oss'] == 1) {
            $bucket_info = get_bucket_info();
            $url = $GLOBALS['ecs']->url();

            $self = explode("/", substr(PHP_SELF, 1));
            $count = count($self);
            if ($count > 1) {
                $real_path = $self[$count - 2];
                if ($real_path == 'seller') {
                    $str_len = -(str_len('seller') + 1);
                    $url = substr($GLOBALS['ecs']->url(), 0, $str_len);
                }
            }

            $urlip = get_ip_url($url);
            $url = $urlip . "oss.php?act=del_file";
            $Http = new Http();
            $post_data = [
                'bucket' => $bucket_info['bucket'],
                'keyid' => $bucket_info['keyid'],
                'keysecret' => $bucket_info['keysecret'],
                'is_cname' => $bucket_info['is_cname'],
                'endpoint' => $bucket_info['outside_site'],
                'object' => $file
            ];

            $Http->doPost($url, $post_data);
        }
        //OSS文件存储ecmoban模板堂 --zhuo end
    }
}

/*
 * 获取域名IP
 */
function get_ip_url($url, $type = 0)
{
    if (strpos($url, 'http://') !== false) {
        $http = "http://";
    } elseif (strpos($url, 'https://') !== false) {
        $http = "https://";
    }
    $file = dirname(ROOT_PATH) . "/data/urlip.txt";
    $file = file_get_contents($file);
    if ($type == 1) {
        return $http . $file;
    } else {
        return $http . $file . '/';
    }
}

//获取OSS Bucket信息
function get_bucket_info()
{
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('oss_configure') . " WHERE is_use = 1 LIMIT 1";
    $res = $GLOBALS['db']->getRow($sql);

    if ($res) {
        $regional = substr($res['regional'], 0, 2);
        if ($regional == 'us' || $regional == 'ap') {
            $res['outside_site'] = "http://" . $res['bucket'] . ".oss-" . $res['regional'] . ".aliyuncs.com";
            $res['inside_site'] = "http://" . $res['bucket'] . ".oss-" . $res['regional'] . "-internal.aliyuncs.com";
        } else {
            $res['outside_site'] = "http://" . $res['bucket'] . ".oss-cn-" . $res['regional'] . ".aliyuncs.com";
            $res['inside_site'] = "http://" . $res['bucket'] . ".oss-cn-" . $res['regional'] . "-internal.aliyuncs.com";
        }
    }

    return $res;
}

//商品详情图片替换
function get_goods_desc_images_preg($endpoint = '', $text_desc = '', $str_file = 'goods_desc')
{
    if ($text_desc) {
        $preg = '/<img.*?src=[\"|\']?(.*?)[\"|\'].*?>/i';
        preg_match_all($preg, $text_desc, $desc_img);
    } else {
        $desc_img = '';
    }

    $arr = [];
    if ($desc_img && $endpoint) {
        foreach ($desc_img[1] as $key => $row) {
            $row = explode('images', $row);
            $arr[] = $endpoint . 'images' . $row[1];
        }

        if ($desc_img[1]) {
            if (count($desc_img[1]) > 1) {
                $desc_img[1] = array_unique($desc_img[1]);//剔除重复值，防止重复添加域名
                foreach ($desc_img[1] as $key => $row) {
                    if (strpos($row, "http://") === false && strpos($row, "https://") === false) {
                        $row_str = substr($row, 0, 1);
                        $str = substr($endpoint, str_len($endpoint) - 1);
                        if ($str == "/" && $row_str == "/") {
                            $endpoint = substr($endpoint, 0, -1);
                        }
                        // 处理手机端商品详情图片 手机相册图路径 ../data/gallery_album/
                        if ($str_file == 'desc_mobile') {
                            $text_desc = str_replace($row, $endpoint . ltrim($row, '../'), $text_desc);
                        } else {
                            $text_desc = str_replace($row, $endpoint . $row, $text_desc);
                        }
                    }
                }
            } else {
                if (strpos($text_desc, $endpoint) === false) {
                    $text_desc = str_replace("/" . 'images', $endpoint . 'images', $text_desc);
                }
            }
        }
    }

    $res = ['images_list' => $arr, $str_file => $text_desc];
    return $res;
}

//获取票税列表
function get_invoice_list($invoice, $order_type = 0, $inv_content = '')
{
    $arr = [];
    if ($invoice['type']) {
        $type = array_values($invoice['type']);
        $rate = array_values($invoice['rate']);

        for ($i = 0; $i < count($type); $i++) {
            if ($order_type == 1) {
                if ($type[$i] == $inv_content) {
                    $arr['type'] = $type[$i];
                    $arr['rate'] = $rate[$i];
                }
            } else {
                $arr[$i]['type'] = $type[$i];
                $arr[$i]['rate'] = $rate[$i];
            }
        }
    }

    return $arr;
}

/**
 * 取得可用的配送方式列表
 * @param   array $region_id_list 收货人地区id数组（包括国家、省、市、区）
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

/**
 * 处理序列化的支付、配送的配置参数
 * 返回一个以name为索引的数组
 *
 * @access  public
 * @param   string $cfg
 * @return  void
 */
function unserialize_config($cfg)
{
    if (is_string($cfg) && ($arr = unserialize($cfg)) !== false) {
        $config = [];

        foreach ($arr as $key => $val) {
            $config[$val['name']] = $val['value'];
        }

        return $config;
    } else {
        return false;
    }
}

/*
 * 重定义商品价格
 * 商品价格 + 属性价格
 * start
 * 获取商品列表第一组属性价格
 */
function get_goods_one_attr_price($goods, $warehouse_id = 0, $area_id = 0, $promote_price = 0, $goods_product = []) {

    $goods_product = [
        'product_price' => $goods['product_price'],
        'product_promote_price' => $goods['product_promote_price']
    ];

    $products = [];
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

            $arr = [];
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

                    $other = [
                        'product_table' => $products['product_table'],
                        'product_id' => $products['product_id'],
                        'product_price' => $products['product_price'],
                        'product_promote_price' => $products['product_promote_price']
                    ];

                    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('goods'), $other, 'UPDATE', "goods_id = '" . $goods['goods_id'] . "'");
                }
            }
        }
    }

    $price = [
        'goods_id' => !empty($goods['goods_id']) ? $goods['goods_id'] : 0,
        'market_price' => $market_price,
        'org_price' => $org_price,
        'shop_price' => $shop_price,
        'promote_price' => $promote_price
    ];

    return $price;
}

/**
 * 重定义商品价格
 * 获取商品属性默认选择中数组
 * end
 */
function get_goods_checked_attr($values)
{
    foreach ($values as $key => $val) {
        if ($val['checked']) {
            return $val;
        }
    }
}

/**
 * 去除字符串中首尾逗号
 * 去除字符串中出现两个连续逗号
 */
function get_del_str_comma($str = '') {

    if ($str && is_array($str)) {
        return $str;
    } else {
        if ($str) {
            $str = str_replace(",,", ",", $str);

            $str1 = substr($str, 0, 1);
            $str2 = substr($str, str_len($str) - 1);

            if ($str1 === "," && $str2 !== ",") {
                $str = substr($str, 1);
            } elseif ($str1 !== "," && $str2 === ",") {
                $str = substr($str, 0, -1);
            } elseif ($str1 === "," && $str2 === ",") {
                $str = substr($str, 1);
                $str = substr($str, 0, -1);
            }
        }

        return $str;
    }
}

/**
 *
 * 重定义用户名
 * end
 */
function encrypt_username($username)
{
    $username_start = mb_substr($username, 0, 1, 'utf-8');
    $username_end = mb_substr($username, -1, 1, 'utf-8');
    $username_new = $username_start . '****' . $username_end;
    return $username_new;
}

//超值礼品包商品的重量和数量
function get_package_goods_info($package_list = [])
{
    if ($package_list) {
        $arr = [];
        $arr['goods_weight'] = 0;

        foreach ($package_list as $key => $row) {
            $arr[$key]['goods_weight'] = $row['goods_number'] * $row['goods_weight'];
            $arr['goods_weight'] += $arr[$key]['goods_weight'];
        }

        return $arr;
    }
}

/*
 * 获取订单商品佣金（未考虑实付订单金额）
 */
function get_order_goods_commission($order_id = 0, $type = 0)
{
    $sql = " SELECT goods_id, goods_price, goods_number FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '$order_id' ";
    $order_goods = $GLOBALS['db']->getAll($sql);

    $commission = 0; //浮点数，保留两位数
    $cat = [];
    if ($order_goods) {
        foreach ($order_goods as $goods) {
            if ($type == 1) {
                $rate = get_commission_rate($goods['goods_id'], $type);

                $cat[$goods['goods_id']]['commission_rate'] = $rate['commission_rate'];
                $cat[$goods['goods_id']]['cat_id'] = $rate['cat_id'];

                $commission_rate = $rate['commission_rate'];
            } else {
                $commission_rate = get_commission_rate($goods['goods_id']);
            }

            $commission += $goods['goods_price'] * $goods['goods_number'] * $commission_rate;
        }
    }

    if ($type == 1) {
        $arr = [
            'commission' => $commission,
            'cat' => $cat
        ];

        return $arr;
    } else {
        return $commission;
    }
}

/*
 * 获取商品分类佣金比率 by wu
 */
function get_commission_rate($goods_id = 0, $type = 0)
{
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

    if ($type == 1) {
        $arr = [
            'commission_rate' => $commission_rate,
            'cat_id' => $cat_id,
        ];

        return $arr;
    } else {
        return $commission_rate;
    }
}

/**
 * 创建已付款订单快照信息
 * @return string   $url
 */
function create_snapshot($order_id = 0)
{
    $sql = " SELECT order_id FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE main_order_id = '$order_id' ";
    if ($order_ids = $GLOBALS['db']->getAll($sql)) {//是否有子订单
        foreach ($order_ids as $val) {
            $sql = "SELECT oi.order_sn, oi.user_id, og.ru_id, og.goods_id, og.goods_name, og.goods_sn, og.goods_attr, og.goods_attr_id, og.goods_price, og.goods_number,og.shipping_fee, g.goods_weight, g.add_time, g.goods_desc, g.goods_img FROM " . $GLOBALS['ecs']->table('order_info') .
                    " AS oi LEFT JOIN " . $GLOBALS['ecs']->table('order_goods') . " AS og ON oi.order_id = og.order_id " .
                    " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON g.goods_id = og.goods_id " .
                    " WHERE oi.order_id = '$val[order_id]' ";
            $result = $GLOBALS['db']->getAll($sql);
            foreach ($result as $v) {
                insert_snapshot($v);
            }
        }
    } else {
        $sql = " SELECT oi.order_sn, oi.user_id, og.ru_id, og.goods_id, og.goods_name, og.goods_sn, og.goods_attr, og.goods_attr_id, og.goods_price, og.goods_number,og.shipping_fee, g.goods_weight, g.add_time, g.goods_desc, g.goods_img FROM " . $GLOBALS['ecs']->table('order_info') .
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
function insert_snapshot($arr = [])
{
    $arr = is_array($arr) ? $arr : [];

    if ($arr) {
        $snapshot_info = [
            'order_sn' => $arr['order_sn'],
            'user_id' => $arr['user_id'],
            'goods_id' => $arr['goods_id'],
            'goods_name' => addslashes($arr['goods_name']),
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
            'goods_desc' => addslashes($arr['goods_desc']),
            'goods_img' => $arr['goods_img'],
            'snapshot_time' => gmtime()
        ];

        return $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('trade_snapshot'), $snapshot_info, 'INSERT');
    } else {
        return 0;
    }
}

/**
 *更新商品销量
 */
function get_goods_sale($order_id)
{
    $sql = 'SELECT goods_id,goods_number FROM ' . $GLOBALS['ecs']->table('order_goods') . ' WHERE order_id =' . $order_id;
    $order_res = $GLOBALS['db']->getAll($sql);
    foreach ($order_res as $idx => $val) {
        $sql = 'SELECT SUM(og.goods_number) as goods_number ' .
            'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g, ' .
            $GLOBALS['ecs']->table('order_info') . ' AS o, ' .
            $GLOBALS['ecs']->table('order_goods') . ' AS og ' .
            " WHERE g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 AND og.order_id = o.order_id AND og.goods_id = g.goods_id " .
            " AND g.goods_id=" . $val['goods_id'];
        $sales_volume = $GLOBALS['db']->getOne($sql);
        $sql = "update " . $GLOBALS['ecs']->table('goods') . " set sales_volume='$sales_volume' WHERE goods_id =" . $val['goods_id'];
        $GLOBALS['db']->query($sql);
    }
}

/**
 * 获取是否已经更新销量
 */
function is_update_sale($order_id)
{
    $sql = "SELECT is_update_sale FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '$order_id'";
    return $GLOBALS['db']->getOne($sql, true);
}
