<?php

/**
 * ECSHOP 管理中心商品相关函数
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
 * 取得推荐类型列表
 * @return  array   推荐类型列表
 */
function get_intro_list()
{
    return array(
        'is_best'    => $GLOBALS['_LANG']['is_best'],
        'is_new'     => $GLOBALS['_LANG']['is_new'],
        'is_hot'     => $GLOBALS['_LANG']['is_hot'],
        'is_promote' => $GLOBALS['_LANG']['is_promote'],
        'all_type' => $GLOBALS['_LANG']['all_type'],
    );
}

/**
 * 取得重量单位列表
 * @return  array   重量单位列表
 */
function get_unit_list()
{
    return array(
        '1'     => $GLOBALS['_LANG']['unit_kg'],
        '0.001' => $GLOBALS['_LANG']['unit_g'],
    );
}

/**
 * 取得会员等级列表
 * @return  array   会员等级列表
 */
function get_user_rank_list()
{
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('user_rank') .
           " ORDER BY min_points";

    return $GLOBALS['db']->getAll($sql);
}

/**
 * 取得某商品的会员价格列表
 * @param   int     $goods_id   商品编号
 * @return  array   会员价格列表 user_rank => user_price
 */
function get_member_price_list($goods_id)
{
    /* 取得会员价格 */
    $price_list = array();
    $sql = "SELECT user_rank, user_price FROM " .
           $GLOBALS['ecs']->table('member_price') .
           " WHERE goods_id = '$goods_id'";
    $res = $GLOBALS['db']->query($sql);
    while ($row = $GLOBALS['db']->fetchRow($res))
    {
        $price_list[$row['user_rank']] = $row['user_price'];
    }

    return $price_list;
}

/**
 * 插入或更新商品属性
 *
 * @param   int     $goods_id           商品编号
 * @param   array   $id_list            属性编号数组
 * @param   array   $is_spec_list       是否规格数组 'true' | 'false'
 * @param   array   $value_price_list   属性值数组
 * @return  array                       返回受到影响的goods_attr_id数组
 */
function handle_goods_attr($goods_id, $id_list, $is_spec_list, $value_price_list)
{
    $goods_attr_id = array();

    /* 循环处理每个属性 */
    foreach ($id_list AS $key => $id)
    {
        $is_spec = $is_spec_list[$key];
        if ($is_spec == 'false')
        {
            $value = $value_price_list[$key];
            $price = '';
        }
        else
        {
            $value_list = array();
            $price_list = array();
            if ($value_price_list[$key])
            {
                $vp_list = explode(chr(13), $value_price_list[$key]);
                foreach ($vp_list AS $v_p)
                {
                    $arr = explode(chr(9), $v_p);
                    $value_list[] = $arr[0];
                    $price_list[] = $arr[1];
                }
            }
            $value = join(chr(13), $value_list);
            $price = join(chr(13), $price_list);
        }

        // 插入或更新记录
        $sql = "SELECT goods_attr_id FROM " . $GLOBALS['ecs']->table('goods_attr') . " WHERE goods_id = '$goods_id' AND attr_id = '$id' AND attr_value = '$value' LIMIT 0, 1";
        $result_id = $GLOBALS['db']->getOne($sql);
        if (!empty($result_id))
        {
            $sql = "UPDATE " . $GLOBALS['ecs']->table('goods_attr') . "
                    SET attr_value = '$value'
                    WHERE goods_id = '$goods_id'
                    AND attr_id = '$id'
                    AND goods_attr_id = '$result_id'";

            $goods_attr_id[$id] = $result_id;
        }
        else
        {
            $sql = "INSERT INTO " . $GLOBALS['ecs']->table('goods_attr') . " (goods_id, attr_id, attr_value, attr_price) " .
                    "VALUES ('$goods_id', '$id', '$value', '$price')";
        }

        $GLOBALS['db']->query($sql);

        if ($goods_attr_id[$id] == '')
        {
            $goods_attr_id[$id] = $GLOBALS['db']->insert_id();
        }
    }

    return $goods_attr_id;
}

/**
 * 保存某商品的会员价格
 * @param   int     $goods_id   商品编号
 * @param   array   $rank_list  等级列表
 * @param   array   $price_list 价格列表
 * @return  void
 */
function handle_member_price($goods_id, $rank_list, $price_list)
{
    /* 循环处理每个会员等级 */
    foreach ($rank_list AS $key => $rank)
    {
        /* 会员等级对应的价格 */
        $price = $price_list[$key];

        // 插入或更新记录
        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('member_price') .
               " WHERE goods_id = '$goods_id' AND user_rank = '$rank'";
        if ($GLOBALS['db']->getOne($sql) > 0)
        {
            /* 如果会员价格是小于0则删除原来价格，不是则更新为新的价格 */
            if ($price < 0)
            {
                $sql = "DELETE FROM " . $GLOBALS['ecs']->table('member_price') .
                       " WHERE goods_id = '$goods_id' AND user_rank = '$rank' LIMIT 1";
            }
            else
            {
                $sql = "UPDATE " . $GLOBALS['ecs']->table('member_price') .
                       " SET user_price = '$price' " .
                       "WHERE goods_id = '$goods_id' " .
                       "AND user_rank = '$rank' LIMIT 1";
            }
        }
        else
        {
            if ($price == -1)
            {
                $sql = '';
            }
            else
            {
                $sql = "INSERT INTO " . $GLOBALS['ecs']->table('member_price') . " (goods_id, user_rank, user_price) " .
                       "VALUES ('$goods_id', '$rank', '$price')";
            }
        }

        if ($sql)
        {
            $GLOBALS['db']->query($sql);
        }
    }
}

/**
 * 保存某商品的扩展分类
 * @param   int     $goods_id   商品编号
 * @param   array   $cat_list   分类编号数组
 * @return  void
 */
function handle_other_cat($goods_id, $cat_list)
{
    /* 查询现有的扩展分类 */
    $sql = "SELECT cat_id FROM " . $GLOBALS['ecs']->table('goods_cat') .
            " WHERE goods_id = '$goods_id'";
    $exist_list = $GLOBALS['db']->getCol($sql);

    /* 删除不再有的分类 */
    $delete_list = array_diff($exist_list, $cat_list);
    if ($delete_list)
    {
        $sql = "DELETE FROM " . $GLOBALS['ecs']->table('goods_cat') .
                " WHERE goods_id = '$goods_id' " .
                "AND cat_id " . db_create_in($delete_list);
        $GLOBALS['db']->query($sql);
    }

    /* 添加新加的分类 */
    $add_list = array_diff($cat_list, $exist_list, array(0));
    foreach ($add_list AS $cat_id)
    {
        // 插入记录
        $sql = "INSERT INTO " . $GLOBALS['ecs']->table('goods_cat') .
                " (goods_id, cat_id) " .
                "VALUES ('$goods_id', '$cat_id')";
        $GLOBALS['db']->query($sql);
    }
}

/**
 * 保存某商品的关联商品
 * @param   int     $goods_id
 * @return  void
 */
function handle_link_goods($goods_id)
{
    $sql = "UPDATE " . $GLOBALS['ecs']->table('link_goods') . " SET " .
            " goods_id = '$goods_id' " .
            " WHERE goods_id = '0'" .
            " AND admin_id = '$_SESSION[admin_id]'";
    $GLOBALS['db']->query($sql);

    $sql = "UPDATE " . $GLOBALS['ecs']->table('link_goods') . " SET " .
            " link_goods_id = '$goods_id' " .
            " WHERE link_goods_id = '0'" .
            " AND admin_id = '$_SESSION[admin_id]'";
    $GLOBALS['db']->query($sql);
}

/**
 * 保存某商品的配件
 * @param   int     $goods_id
 * @return  void
 */
function handle_group_goods($goods_id)
{
    $sql = "UPDATE " . $GLOBALS['ecs']->table('group_goods') . " SET " .
            " parent_id = '$goods_id' " .
            " WHERE parent_id = '0'" .
            " AND admin_id = '$_SESSION[admin_id]'";
    $GLOBALS['db']->query($sql);
}

/**
 * 保存某商品的关联文章
 * @param   int     $goods_id
 * @return  void
 */
function handle_goods_article($goods_id)
{
    $sql = "UPDATE " . $GLOBALS['ecs']->table('goods_article') . " SET " .
            " goods_id = '$goods_id' " .
            " WHERE goods_id = '0'" .
            " AND admin_id = '$_SESSION[admin_id]'";
    $GLOBALS['db']->query($sql);
}

/**
 * 保存某商品的关联地区 create by qin
 * @param   int     $goods_id
 * @return  void
 */
function handle_goods_area($goods_id)
{
    $sql = "UPDATE " . $GLOBALS['ecs']->table('link_area_goods') . " SET " .
            " goods_id = '$goods_id' " .
            " WHERE goods_id = '0'" .
            " AND ru_id = (SELECT ru_id FROM " . $GLOBALS['ecs']->table('admin_user') . "  WHERE user_id = '$_SESSION[admin_id]') ";
    $GLOBALS['db']->query($sql);
}

/** 
 * 保存某商品的相册图片
 * @param   int     $goods_id
 * @param   array   $image_files
 * @param   array   $image_descs
 * @return  void
 */
function handle_gallery_image($goods_id, $image_files, $image_descs, $image_urls, $single_id = 0, $files_type = 0)
{
    if ($files_type == 0) {
        $files_type = 'single_id';
    } elseif ($files_type = 1) {
        $files_type = 'dis_id';
    }
    
    $admin_id = get_admin_id();
    $admin_temp_dir = "seller";
    $admin_temp_dir = ROOT_PATH . "temp" . '/' . $admin_temp_dir . '/' . "admin_" . $admin_id;
    
    // 如果目标目录不存在，则创建它 
    if (!file_exists($admin_temp_dir)) {
        make_dir($admin_temp_dir);
    }

    /* 是否处理缩略图 */
    $proc_thumb = (isset($GLOBALS['shop_id']) && $GLOBALS['shop_id'] > 0)? false : true;
    foreach ($image_descs AS $key => $img_desc)
    {
        /* 是否成功上传 */
        $flag = false;
        if (isset($image_files['error']))
        {
            if ($image_files['error'][$key] == 0)
            {
                $flag = true;
            }
        }
        else
        {
            if ($image_files['tmp_name'][$key] != 'none' && $image_files['tmp_name'][$key])
            {
                $flag = true;
            }
        }
        if ($flag)
        {
            $upload = array(
                'name' => $image_files['name'][$key],
                'type' => $image_files['type'][$key],
                'tmp_name' => $image_files['tmp_name'][$key],
                'size' => $image_files['size'][$key],
            );
            if (isset($image_files['error']))
            {
                $upload['error'] = $image_files['error'][$key];
            }
            $img_original = $GLOBALS['image']->upload_image($upload, array('type' => 1));
            if ($img_original === false)
            {
           
                if($is_ajax == 'ajax'){
                    $result['error']='1';
                    $result['massege']=sprintf($_LANG['img_url_too_big'], $key + 1, $htm_maxsize);
                    return;
                }else{
                    sys_msg($GLOBALS['image']->error_msg(), 1, array(), false);
                }
            
            }
            $img_url = $img_original;

            // 生成缩略图
            if ($proc_thumb)
            {
                $thumb_url = $GLOBALS['image']->make_thumb(array('img' => $img_original, 'type' => 1), $GLOBALS['_CFG']['thumb_width'],  $GLOBALS['_CFG']['thumb_height']);
                $thumb_url = is_string($thumb_url) ? $thumb_url : '';
            }
            else
            {
                $thumb_url = $img_original;
            }
            
            // 如果服务器支持GD 则添加水印
            if ($proc_thumb && gd_version() > 0)
            {
                $pos        = strpos(basename($img_original), '.');
                $newname    = dirname($img_original) . '/' . $GLOBALS['image']->random_filename() . substr(basename($img_original), $pos);
                copy($img_original, $newname);
                $img_url    = $newname;

                $GLOBALS['image']->add_watermark($img_url,'',$GLOBALS['_CFG']['watermark'], $GLOBALS['_CFG']['watermark_place'], $GLOBALS['_CFG']['watermark_alpha']);
            }
            
            /* 重新格式化图片名称 */
            if ($goods_id == 0) {
                $img_original = reformat_image_name('gallery', $single_id, $img_original, 'source');
                $img_url = reformat_image_name('gallery', $single_id, $img_url, 'goods');
                $thumb_url = reformat_image_name('gallery_thumb', $single_id, $thumb_url, 'thumb');
            } else {
                $img_original = reformat_image_name('gallery', $goods_id, $img_original, 'source');
                $img_url = reformat_image_name('gallery', $goods_id, $img_url, 'goods');
                $thumb_url = reformat_image_name('gallery_thumb', $goods_id, $thumb_url, 'thumb');
            }

            $sql = "INSERT INTO " . $GLOBALS['ecs']->table('goods_gallery') . " (goods_id, img_url, img_desc, thumb_url, img_original, " .$files_type. ") " . 
					"VALUES ('$goods_id', '$img_url', '$gallery_count', '$thumb_url', '$img_original', '$single_id')";
            $GLOBALS['db']->query($sql);
            $thumb_img_id[]=$GLOBALS['db']->insert_id();
            /* 不保留商品原图的时候删除原图 */
            if ($proc_thumb && !$GLOBALS['_CFG']['retain_original_img'] && !empty($img_original))
            {
                $GLOBALS['db']->query("UPDATE " . $GLOBALS['ecs']->table('goods_gallery') . " SET img_original='' WHERE `goods_id`='$goods_id'");
                @unlink('../' . $img_original);
            }

        }
        elseif (!empty($image_urls[$key]) && ($image_urls[$key] != $GLOBALS['_LANG']['img_file']) && ($image_urls[$key] != 'http://') && (strpos($image_urls[$key], 'http://') !== false || strpos($image_urls[$key], 'https://') !== false))
        {
            if(get_http_basename($image_urls[$key], $admin_temp_dir)){
                
                $image_url = trim($image_urls[$key]);
                //定义原图路径
                $down_img = $admin_temp_dir . "/" . basename($image_url);

                $img_wh = $GLOBALS['image']->get_width_to_height($down_img, $GLOBALS['_CFG']['image_width'], $GLOBALS['_CFG']['image_height']);
                $GLOBALS['_CFG']['image_width'] = isset($img_wh['image_width']) ? $img_wh['image_width'] : $GLOBALS['_CFG']['image_width'];
                $GLOBALS['_CFG']['image_height'] = isset($img_wh['image_height']) ? $img_wh['image_height'] : $GLOBALS['_CFG']['image_height'];

                $goods_img = $GLOBALS['image']->make_thumb(array('img' => $down_img, 'type' => 1), $GLOBALS['_CFG']['image_width'], $GLOBALS['_CFG']['image_height']);

                // 生成缩略图
                if ($proc_thumb) {
                    $thumb_url = $GLOBALS['image']->make_thumb(array('img' => $down_img, 'type' => 1), $GLOBALS['_CFG']['thumb_width'], $GLOBALS['_CFG']['thumb_height']);
                    $thumb_url = reformat_image_name('gallery_thumb', $goods_id, $thumb_url, 'thumb');
                } else {
                    $thumb_url = $GLOBALS['image']->make_thumb(array('img' => $down_img, 'type' => 1));
                    $thumb_url = reformat_image_name('gallery_thumb', $goods_id, $thumb_url, 'thumb');
                }

                $img_original = reformat_image_name('gallery', $goods_id, $down_img, 'source');
                $img_url = reformat_image_name('gallery', $goods_id, $goods_img, 'goods');
                
                $sql = "INSERT INTO " . $GLOBALS['ecs']->table('goods_gallery') . " (goods_id, img_url, img_desc, thumb_url, img_original, " .$files_type. ") " .
                        "VALUES ('$goods_id', '$img_url', '$gallery_count', '$thumb_url', '$img_original', '$single_id')";
                $GLOBALS['db']->query($sql);
                $thumb_img_id[]=$GLOBALS['db']->insert_id();
                @unlink($down_img);
            }
        }
        
        get_oss_add_file(array($img_url, $thumb_url, $img_original));
    }
}

function get_goods_gallery_count($goods_id = 0,$is_lib = 0){
	$table = "goods_gallery";
	if($is_lib){
		$table = "goods_lib_gallery";
	}
    $sql = "SELECT COUNT(*) FROM " .$GLOBALS['ecs']->table($table). " WHERE goods_id = '$goods_id'";
    return $GLOBALS['db']->getOne($sql);
}

/** by kong 添加商品相册
 * 保存某商品的相册图片
 * @param   int     $goods_id
 * @param   array   $image_files
 * @param   array   $image_descs
 * @return  void
 */
function handle_gallery_image_add($goods_id, $image_files, $image_descs, $image_urls, $single_id = 0, $files_type = 0,$is_ajax, $gallery_count = 0, $is_lib = 0)
{
    if ($files_type == 0) {
        $files_type = 'single_id';
    } elseif ($files_type = 1) {
        $files_type = 'dis_id';
    }
    
	$table = "goods_gallery";
	if($is_lib){
		$table = "goods_lib_gallery";
	}
	
    $admin_id = get_admin_id();
    $admin_temp_dir = "seller";
    $admin_temp_dir = ROOT_PATH . "temp" . '/' . $admin_temp_dir . '/' . "admin_" . $admin_id;
    
    // 如果目标目录不存在，则创建它 
    if (!file_exists($admin_temp_dir)) {
        make_dir($admin_temp_dir);
    }

    /* 是否处理缩略图 */
    $proc_thumb = (isset($GLOBALS['shop_id']) && $GLOBALS['shop_id'] > 0)? false : true;
    foreach ($image_descs AS $key => $img_desc)
    {
        /* 是否成功上传 */
        $flag = false;
        if (isset($image_files['error']))
        {
            if ($image_files['error'][$key] == 0)
            {
                $flag = true;
            }
        }
        else
        {
            if ($image_files['tmp_name'][$key] != 'none' && $image_files['tmp_name'][$key])
            {
                $flag = true;
            }
        }
        if ($flag)
        {
            $upload = array(
                'name' => $image_files['name'][$key],
                'type' => $image_files['type'][$key],
                'tmp_name' => $image_files['tmp_name'][$key],
                'size' => $image_files['size'][$key],
            );
            if (isset($image_files['error']))
            {
                $upload['error'] = $image_files['error'][$key];
            }
            $img_original = $GLOBALS['image']->upload_image($upload, array('type' => 1));
            if ($img_original === false)
            {
           
                if($is_ajax == 'ajax'){
                    $result['error']='1';
                    $result['massege']=sprintf($_LANG['img_url_too_big'], $key + 1, $htm_maxsize);
                    return;
                }else{
                    sys_msg($GLOBALS['image']->error_msg(), 1, array(), false);
                }
            
            }
            $img_url = $img_original;

            // 生成缩略图
            if ($proc_thumb)
            {
                $thumb_url = $GLOBALS['image']->make_thumb(array('img' => $img_original, 'type' => 1), $GLOBALS['_CFG']['thumb_width'],  $GLOBALS['_CFG']['thumb_height']);
                $thumb_url = is_string($thumb_url) ? $thumb_url : '';
            }
            else
            {
                $thumb_url = $img_original;
            }
            
            // 如果服务器支持GD 则添加水印
            if ($proc_thumb && gd_version() > 0)
            {
                $pos        = strpos(basename($img_original), '.');
                $newname    = dirname($img_original) . '/' . $GLOBALS['image']->random_filename() . substr(basename($img_original), $pos);
                copy($img_original, $newname);
                $img_url    = $newname;

                $GLOBALS['image']->add_watermark($img_url,'',$GLOBALS['_CFG']['watermark'], $GLOBALS['_CFG']['watermark_place'], $GLOBALS['_CFG']['watermark_alpha']);
            }
            
            /* 重新格式化图片名称 */
            if ($goods_id == 0) {
                $img_original = reformat_image_name('gallery', $single_id, $img_original, 'source');
                $img_url = reformat_image_name('gallery', $single_id, $img_url, 'goods');
                $thumb_url = reformat_image_name('gallery_thumb', $single_id, $thumb_url, 'thumb');
            } else {
                $img_original = reformat_image_name('gallery', $goods_id, $img_original, 'source');
                $img_url = reformat_image_name('gallery', $goods_id, $img_url, 'goods');
                $thumb_url = reformat_image_name('gallery_thumb', $goods_id, $thumb_url, 'thumb');
            }

            $sql = "INSERT INTO " . $GLOBALS['ecs']->table($table) . " (goods_id, img_url, img_desc, thumb_url, img_original, " .$files_type. ") " . 
					"VALUES ('$goods_id', '$img_url', '$gallery_count', '$thumb_url', '$img_original', '$single_id')";
            $GLOBALS['db']->query($sql);
            $thumb_img_id[]=$GLOBALS['db']->insert_id();
            /* 不保留商品原图的时候删除原图 */
            if ($proc_thumb && !$GLOBALS['_CFG']['retain_original_img'] && !empty($img_original))
            {
                $GLOBALS['db']->query("UPDATE " . $GLOBALS['ecs']->table($table) . " SET img_original='' WHERE `goods_id`='$goods_id'");
                @unlink('../' . $img_original);
            }

        }
        elseif (!empty($image_urls[$key]) && ($image_urls[$key] != $GLOBALS['_LANG']['img_file']) && ($image_urls[$key] != 'http://') && (strpos($image_urls[$key], 'http://') !== false || strpos($image_urls[$key], 'https://') !== false))
        {
            if(get_http_basename($image_urls[$key], $admin_temp_dir)){
                
                $image_url = trim($image_urls[$key]);
                //定义原图路径
                $down_img = $admin_temp_dir . "/" . basename($image_url);

                $img_wh = $GLOBALS['image']->get_width_to_height($down_img, $GLOBALS['_CFG']['image_width'], $GLOBALS['_CFG']['image_height']);
                $GLOBALS['_CFG']['image_width'] = isset($img_wh['image_width']) ? $img_wh['image_width'] : $GLOBALS['_CFG']['image_width'];
                $GLOBALS['_CFG']['image_height'] = isset($img_wh['image_height']) ? $img_wh['image_height'] : $GLOBALS['_CFG']['image_height'];

                $goods_img = $GLOBALS['image']->make_thumb(array('img' => $down_img, 'type' => 1), $GLOBALS['_CFG']['image_width'], $GLOBALS['_CFG']['image_height']);

                // 生成缩略图
                if ($proc_thumb) {
                    $thumb_url = $GLOBALS['image']->make_thumb(array('img' => $down_img, 'type' => 1), $GLOBALS['_CFG']['thumb_width'], $GLOBALS['_CFG']['thumb_height']);
                    $thumb_url = reformat_image_name('gallery_thumb', $goods_id, $thumb_url, 'thumb');
                } else {
                    $thumb_url = $GLOBALS['image']->make_thumb(array('img' => $down_img, 'type' => 1));
                    $thumb_url = reformat_image_name('gallery_thumb', $goods_id, $thumb_url, 'thumb');
                }

                $img_original = reformat_image_name('gallery', $goods_id, $down_img, 'source');
                $img_url = reformat_image_name('gallery', $goods_id, $goods_img, 'goods');
                
                $sql = "INSERT INTO " . $GLOBALS['ecs']->table($table) . " (goods_id, img_url, img_desc, thumb_url, img_original, " .$files_type. ") " .
                        "VALUES ('$goods_id', '$img_url', '$gallery_count', '$thumb_url', '$img_original', '$single_id')";
                $GLOBALS['db']->query($sql);
                $thumb_img_id[]=$GLOBALS['db']->insert_id();
                @unlink($down_img);
            }
        }
        
        get_oss_add_file(array($img_url, $thumb_url, $img_original));
    }
    
    if(!empty($_SESSION['thumb_img_id' . $_SESSION['admin_id']])) {
        $_SESSION['thumb_img_id' . $_SESSION['admin_id']] = array_merge($thumb_img_id, $_SESSION['thumb_img_id' . $_SESSION['admin_id']]);
    } else {
        $_SESSION['thumb_img_id' . $_SESSION['admin_id']] = $thumb_img_id;
    }
}

/**
 * 修改商品某字段值
 * @param   string  $goods_id   商品编号，可以为多个，用 ',' 隔开
 * @param   string  $field      字段名
 * @param   string  $value      字段值
 * @return  bool
 */
function update_goods($goods_id, $field, $value, $content = '', $type = '') { //ecmoban模板堂 --zhuo  $content = ''
    if ($goods_id) {
        /* 清除缓存 */
        clear_cache_files();

        $date = array('model_attr');

        $where = "goods_id = '$goods_id'";
        $model_attr = get_table_date('goods', $where, $date, 2);

        //ecmoban模板堂 --zhuo start
        $table = "goods";
        if ($type == 'updateNum') {
            if ($model_attr == 1) {
                $table = "warehouse_goods";
                $field = 'region_number';
            } elseif ($model_attr == 2) {
                $table = "warehouse_area_goods";
                $field = 'region_number';
            }
        }

        if ($value == 2 && !empty($content)) {
            $content = "review_content = '$content', ";
        }
        //ecmoban模板堂 --zhuo end

        if ($field == 'is_on_sale') {
            if($value == 1){
                $sql = "SELECT act_id FROM " .$GLOBALS['ecs']->table('presale_activity'). " WHERE goods_id " . db_create_in($goods_id);
                if($GLOBALS['db']->getOne($sql, true)){
                    $GLOBALS['db']->query("DELETE FROM " . $GLOBALS['ecs']->table('presale_activity') . " WHERE goods_id " . db_create_in($goods_id));
                    $GLOBALS['db']->query("DELETE FROM " . $GLOBALS['ecs']->table('cart') . " WHERE goods_id " . db_create_in($goods_id));
                }
            }else{
                $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart') . " WHERE goods_id " . db_create_in($goods_id);
                $GLOBALS['db']->query($sql);
            }
        }
        if ($field == 'review_status' && $value < 3)
        {
            $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart') . " WHERE goods_id " . db_create_in($goods_id);
            $GLOBALS['db']->query($sql);
        }
        
        $sql = "UPDATE " . $GLOBALS['ecs']->table($table) .
                " SET $field = '$value' , " . $content . " last_update = '" . gmtime() . "' " .
                "WHERE goods_id " . db_create_in($goods_id);
        return $GLOBALS['db']->query($sql);
    } else {
        return false;
    }
}

/**
 * 从回收站删除多个商品
 * @param   mix     $goods_id   商品id列表：可以逗号格开，也可以是数组
 * @return  void
 */
function delete_goods($goods_id)
{
    if (empty($goods_id))
    {
        return;
    }

    /* 取得有效商品id */
    $sql = "SELECT DISTINCT goods_id FROM " . $GLOBALS['ecs']->table('goods') .
            " WHERE goods_id " . db_create_in($goods_id) . " AND is_delete = 1";
    $goods_id = $GLOBALS['db']->getCol($sql);
    if (empty($goods_id))
    {
        return;
    }
    
    //OSS文件存储ecmoban模板堂 --zhuo start
    if($GLOBALS['_CFG']['open_oss'] == 1){
        $bucket_info = get_bucket_info();
        $urlip = get_ip_url($GLOBALS['ecs']->url());
        $url = $urlip . "oss.php?act=del_file";
        $Http = new Http();
    }
    //OSS文件存储ecmoban模板堂 --zhuo end

    /* 删除商品图片和轮播图片文件 */
    $sql = "SELECT goods_thumb, goods_img, original_img " .
            "FROM " . $GLOBALS['ecs']->table('goods') .
            " WHERE goods_id " . db_create_in($goods_id);
    $res = $GLOBALS['db']->query($sql);
    while ($goods = $GLOBALS['db']->fetchRow($res))
    {
        if (!empty($goods['goods_thumb']))
        {
            @unlink('../' . $goods['goods_thumb']);
        }
        if (!empty($goods['goods_img']))
        {
            @unlink('../' . $goods['goods_img']);
        }
        if (!empty($goods['original_img']))
        {
            @unlink('../' . $goods['original_img']);
        }
        
        //OSS文件存储ecmoban模板堂 --zhuo start
        if($GLOBALS['_CFG']['open_oss'] == 1){
            $post_data = array(
                'bucket'        => $bucket_info['bucket'],
                'keyid'         => $bucket_info['keyid'],
                'keysecret'     => $bucket_info['keysecret'],
                'is_cname'      => $bucket_info['is_cname'],
                'endpoint'      => $bucket_info['outside_site'],
                'object' => array(
                    $goods['goods_thumb'],
                    $goods['goods_img'],
                    $goods['original_img']
                    ) 
            );

            $Http->doPost($url, $post_data);
        }
        //OSS文件存储ecmoban模板堂 --zhuo end
    }

    /* 删除商品 */
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('goods') .
            " WHERE goods_id " . db_create_in($goods_id);
    $GLOBALS['db']->query($sql);

    /* 删除商品的货品记录 */
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('products') .
            " WHERE goods_id " . db_create_in($goods_id);
    $GLOBALS['db']->query($sql);

    /* 删除商品相册的图片文件 */
    $sql = "SELECT img_url, thumb_url, img_original " .
            "FROM " . $GLOBALS['ecs']->table('goods_gallery') .
            " WHERE goods_id " . db_create_in($goods_id);
    $res = $GLOBALS['db']->query($sql);
    while ($row = $GLOBALS['db']->fetchRow($res))
    {
        if (!empty($row['img_url']))
        {
            @unlink('../' . $row['img_url']);
        }
        if (!empty($row['thumb_url']))
        {
            @unlink('../' . $row['thumb_url']);
        }
        if (!empty($row['img_original']))
        {
            @unlink('../' . $row['img_original']);
        }
        
        //OSS文件存储ecmoban模板堂 --zhuo start
        if($GLOBALS['_CFG']['open_oss'] == 1){
            $post_data = array(
                'bucket'        => $bucket_info['bucket'],
                'keyid'         => $bucket_info['keyid'],
                'keysecret'     => $bucket_info['keysecret'],
                'is_cname'      => $bucket_info['is_cname'],
                'endpoint'      => $bucket_info['outside_site'],
                'object' => array(
                    $row['img_url'],
                    $row['thumb_url'],
                    $row['img_original']
                    ) 
            );

            $Http->doPost($url, $post_data);
        }
        //OSS文件存储ecmoban模板堂 --zhuo end
    }

    /* 删除商品相册 */
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('goods_gallery') . " WHERE goods_id " . db_create_in($goods_id);
    $GLOBALS['db']->query($sql);

    /* 删除相关表记录 */
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('collect_goods') . " WHERE goods_id " . db_create_in($goods_id);
    $GLOBALS['db']->query($sql);
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('goods_article') . " WHERE goods_id " . db_create_in($goods_id);
    $GLOBALS['db']->query($sql);
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('goods_attr') . " WHERE goods_id " . db_create_in($goods_id);
    $GLOBALS['db']->query($sql);
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('goods_cat') . " WHERE goods_id " . db_create_in($goods_id);
    $GLOBALS['db']->query($sql);
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('member_price') . " WHERE goods_id " . db_create_in($goods_id);
    $GLOBALS['db']->query($sql);
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('group_goods') . " WHERE parent_id " . db_create_in($goods_id);
    $GLOBALS['db']->query($sql);
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('group_goods') . " WHERE goods_id " . db_create_in($goods_id);
    $GLOBALS['db']->query($sql);
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('link_goods') . " WHERE goods_id " . db_create_in($goods_id);
    $GLOBALS['db']->query($sql);
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('link_goods') . " WHERE link_goods_id " . db_create_in($goods_id);
    $GLOBALS['db']->query($sql);
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('tag') . " WHERE goods_id " . db_create_in($goods_id);
    $GLOBALS['db']->query($sql);
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('comment') . " WHERE comment_type = 0 AND id_value " . db_create_in($goods_id);
    $GLOBALS['db']->query($sql);
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart') . " WHERE goods_id " . db_create_in($goods_id);
    $GLOBALS['db']->query($sql);
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('presale_activity') . " WHERE goods_id " . db_create_in($goods_id);
    $GLOBALS['db']->query($sql);
    
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('warehouse_goods') . " WHERE goods_id " . db_create_in($goods_id);
    $GLOBALS['db']->query($sql);
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('warehouse_attr') . " WHERE goods_id " . db_create_in($goods_id);
    $GLOBALS['db']->query($sql);
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('warehouse_area_goods') . " WHERE goods_id " . db_create_in($goods_id);
    $GLOBALS['db']->query($sql);
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('warehouse_area_attr') . " WHERE goods_id " . db_create_in($goods_id);
    $GLOBALS['db']->query($sql);

    /* 删除相应虚拟商品记录 */
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('virtual_card') . " WHERE goods_id " . db_create_in($goods_id);
    if (!$GLOBALS['db']->query($sql, 'SILENT') && $GLOBALS['db']->errno() != 1146)
    {
        die($GLOBALS['db']->error());
    }

    /* 清除缓存 */
    clear_cache_files();
}

/**
 * 为某商品生成唯一的货号
 * @param   int     $goods_id   商品编号
 * @return  string  唯一的货号
 */
function generate_goods_sn($goods_id)
{
    $goods_sn = $GLOBALS['_CFG']['sn_prefix'] . str_repeat('0', 6 - strlen($goods_id)) . $goods_id;

    $sql = "SELECT goods_sn FROM " . $GLOBALS['ecs']->table('goods') .
            " WHERE goods_sn LIKE '" . mysql_like_quote($goods_sn) . "%' AND goods_id <> '$goods_id' " .
            " ORDER BY LENGTH(goods_sn) DESC";
    $sn_list = $GLOBALS['db']->getCol($sql);
    if (in_array($goods_sn, $sn_list))
    {
        $max = pow(10, strlen($sn_list[0]) - strlen($goods_sn) + 1) - 1;
        $new_sn = $goods_sn . mt_rand(0, $max);
        while (in_array($new_sn, $sn_list))
        {
            $new_sn = $goods_sn . mt_rand(0, $max);
        }
        $goods_sn = $new_sn;
    }

    return $goods_sn;
}

/**
 * 商品货号是否重复
 *
 * @param   string     $goods_sn        商品货号；请在传入本参数前对本参数进行SQl脚本过滤
 * @param   int        $goods_id        商品id；默认值为：0，没有商品id
 * @return  bool                        true，重复；false，不重复
 */
function check_goods_sn_exist($goods_sn, $goods_id = 0)
{
    $goods_sn = trim($goods_sn);
    $goods_id = intval($goods_id);
    if (strlen($goods_sn) == 0)
    {
        return true;    //重复
    }

    if (empty($goods_id))
    {
        $sql = "SELECT goods_id FROM " . $GLOBALS['ecs']->table('goods') ."
                WHERE goods_sn = '$goods_sn'";
    }
    else
    {
        $sql = "SELECT goods_id FROM " . $GLOBALS['ecs']->table('goods') ."
                WHERE goods_sn = '$goods_sn'
                AND goods_id <> '$goods_id'";
    }

    $res = $GLOBALS['db']->getOne($sql);

    if (empty($res))
    {
        return false;    //不重复
    }
    else
    {
        return true;    //重复
    }

}

/**
 * 取得通用属性和某分类的属性，以及某商品的属性值
 * @param   int     $cat_id     分类编号
 * @param   int     $goods_id   商品编号
 * @return  array   规格与属性列表
 */
function get_attr_list($cat_id, $goods_id = 0)
{
    if (empty($cat_id))
    {
        return array();
    }

    // 查询属性值及商品的属性值
    $sql = "SELECT a.attr_id, a.attr_name, a.attr_input_type, a.attr_type, a.attr_values, v.attr_value, v.attr_price, v.attr_sort, v.attr_checked ".
            "FROM " .$GLOBALS['ecs']->table('attribute'). " AS a ".
            "LEFT JOIN " .$GLOBALS['ecs']->table('goods_attr'). " AS v ".
            "ON v.attr_id = a.attr_id AND v.goods_id = '$goods_id' ".
            "WHERE a.cat_id = " . intval($cat_id) ." OR a.cat_id = 0 ".
            "ORDER BY a.sort_order, a.attr_id, v.goods_attr_id";

    $row = $GLOBALS['db']->GetAll($sql);

    return $row;
}

/**
 * 获取商品类型中包含规格的类型列表
 *
 * @access  public
 * @return  array
 */
function get_goods_type_specifications()
{
    // 查询
    $sql = "SELECT DISTINCT cat_id
            FROM " .$GLOBALS['ecs']->table('attribute'). "
            WHERE attr_type = 1";
    $row = $GLOBALS['db']->GetAll($sql);

    $return_arr = array();
    if (!empty($row))
    {
        foreach ($row as $value)
        {
            $return_arr[$value['cat_id']] = $value['cat_id'];
        }
    }
    return $return_arr;
}

/**
 * 获得指定商品相关的商品
 *
 * @access  public
 * @param   integer $goods_id
 * @return  array
 */
function get_linked_goods($goods_id)
{
    $sql = "SELECT lg.link_goods_id AS goods_id, g.goods_name, lg.is_double " .
            "FROM " . $GLOBALS['ecs']->table('link_goods') . " AS lg, " .
                $GLOBALS['ecs']->table('goods') . " AS g " .
            "WHERE lg.goods_id = '$goods_id' " .
            "AND lg.link_goods_id = g.goods_id ";
    if ($goods_id == 0)
    {
        $sql .= " AND lg.admin_id = '$_SESSION[admin_id]'";
    }
    $row = $GLOBALS['db']->getAll($sql);

    foreach ($row AS $key => $val)
    {
        $linked_type = $val['is_double'] == 0 ? "单向关联" : "双向关联";

        //$row[$key]['goods_name'] = $val['goods_name'] . " -- [$linked_type]";
		$row[$key]['goods_name'] = "<span class='blue'>[$linked_type] </span>" . $val['goods_name'];

        unset($row[$key]['is_double']);
    }

    return $row;
}

/**
 * 获得指定商品的配件
 *
 * @access  public
 * @param   integer $goods_id
* @return  array
 */
function get_group_goods($goods_id)
{
    $sql = "SELECT gg.id, gg.goods_id, gg.group_id, g.goods_name ,gg.goods_price,g.shop_price " .
            "FROM " . $GLOBALS['ecs']->table('group_goods') . " AS gg, " .
                $GLOBALS['ecs']->table('goods') . " AS g " .
            "WHERE gg.parent_id = '$goods_id' " .
            "AND gg.goods_id = g.goods_id ";
    if ($goods_id == 0)
    {
        $sql .= " AND gg.admin_id = '$_SESSION[admin_id]'";
    }
	$sql .= " order by gg.group_id asc, g.goods_id asc"; //by mike add
    $res = $GLOBALS['db']->getAll($sql);
    
    $group_goods = get_cfg_group_goods();
    
    $arr = array();
    foreach($res as $key=>$row){
        $arr[$key] = $row;
        if($group_goods){
            foreach($group_goods as $gkey=>$group){
                if($row['group_id'] == $gkey){
                    $arr[$key]['group_name'] = $group;
                }
            }
        } 
    }

    return $arr;
}

/**
 * 获得商品的关联文章
 *
 * @access  public
 * @param   integer $goods_id
 * @return  array
 */
function get_goods_articles($goods_id)
{
    $sql = "SELECT g.article_id, a.title " .
            "FROM " .$GLOBALS['ecs']->table('goods_article') . " AS g, " .
                $GLOBALS['ecs']->table('article') . " AS a " .
            "WHERE g.goods_id = '$goods_id' " .
            "AND g.article_id = a.article_id ";
    if ($goods_id == 0)
    {
        $sql .= " AND g.admin_id = '$_SESSION[admin_id]'";
    }
    $row = $GLOBALS['db']->getAll($sql);

    return $row;
}

/**
 * 获得商品列表
 *
 * @access  public
 * @params  integer $isdelete
 * @params  integer $real_goods
 * @params  integer $conditions
 * @return  array
 */
function goods_list($is_delete = 0, $real_goods = 1, $conditions = '', $review_status = 0, $real_division = 0)
{
    //ecmoban模板堂 --zhuo start
    $adminru = get_admin_ru_id();
    $ruCat = '';
    if($adminru['ru_id'] > 0){
            $ruCat = " and g.user_id = '" .$adminru['ru_id']. "' ";
    }
    //ecmoban模板堂 --zhuo end
	
    /* 过滤条件 */
    $param_str = '-' . $is_delete . '-' . $real_goods . '-' . $review_status;
    $result = get_filter($param_str);
    if ($result === false)
    {
        $day = getdate();
        $today = local_mktime(23, 59, 59, $day['mon'], $day['mday'], $day['year']);
        
        $filter['cat_id']           = empty($_REQUEST['cat_id']) ? 0 : intval($_REQUEST['cat_id']);
        $filter['intro_type']       = empty($_REQUEST['intro_type']) ? '' : trim($_REQUEST['intro_type']);
        $filter['is_promote']       = empty($_REQUEST['is_promote']) ? 0 : intval($_REQUEST['is_promote']);
        $filter['stock_warning']    = empty($_REQUEST['stock_warning']) ? 0 : intval($_REQUEST['stock_warning']);
        $filter['cat_type']         = isset($_REQUEST['cat_type']) && empty($_REQUEST['cat_type']) ? '' : addslashes($_REQUEST['cat_type']);
        $filter['brand_id']         = empty($_REQUEST['brand_id']) ? 0 : intval($_REQUEST['brand_id']);
        $filter['brand_keyword'] = empty($_REQUEST['brand_keyword']) ? '' : trim($_REQUEST['brand_keyword']);
        $filter['keyword']          = empty($_REQUEST['keyword']) ? '' : trim($_REQUEST['keyword']);
        $filter['suppliers_id'] = isset($_REQUEST['suppliers_id']) ? (empty($_REQUEST['suppliers_id']) ? '' : trim($_REQUEST['suppliers_id'])) : '';

        if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
        {
            $filter['keyword'] = json_str_iconv($filter['keyword']);
        }
        
        if(isset($_REQUEST['review_status'])){
            $filter['review_status']    = empty($_REQUEST['review_status']) ? 0 : intval($_REQUEST['review_status']); //ecmoban模板堂 --zhuo
        }else{
            $filter['review_status']    = $review_status;
        }
        
        $filter['sort_by']          = empty($_REQUEST['sort_by']) ? 'g.goods_id' : trim($_REQUEST['sort_by']);
        $filter['sort_order']       = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);
        $filter['extension_code']   = empty($_REQUEST['extension_code']) ? '' : trim($_REQUEST['extension_code']);
        $filter['is_delete']        = $is_delete;
        $filter['real_goods']       = $real_goods;
        
        $where = 1;
        if($filter['cat_type'] == 'seller'){
            $where .= $filter['cat_id'] > 0 ? " AND (" . get_children($filter['cat_id'], 0, 0, 'merchants_category', 'g.user_cat') . ")" : '';
        }
        
        if ($filter['brand_keyword'])
        {
            $filter['brand_id'] = $GLOBALS['db']->getAll("SELECT GROUP_CONCAT(brand_id) AS brand_id FROM ".$GLOBALS['ecs']->table('brand')." WHERE brand_name LIKE '%$brand_keyword%' ");
            $where .= " AND (g.brand_id = '" . db_create_in($filter['brand_id']) . "')";
        }
        
        if ($filter['brand_id']) 
        {
            $where .= " AND (g.brand_id = '" . $filter['brand_id'] . "')";
        }
        
        /* 上架下架 */
        if (isset($_REQUEST['is_on_sale']) && $_REQUEST['is_on_sale'] != -1) {
            $filter['is_on_sale'] = !empty($_REQUEST['is_on_sale']) ? $_REQUEST['is_on_sale'] : 0;
            $where .= " AND (g.is_on_sale = '" . $filter['is_on_sale'] . "')";
        }
        
        //管理员查询的权限 -- 店铺查询 start
        $filter['store_search'] = empty($_REQUEST['store_search']) ? 0 : intval($_REQUEST['store_search']);
        $filter['merchant_id'] = isset($_REQUEST['merchant_id']) ? intval($_REQUEST['merchant_id']) : 0;
        $filter['store_keyword'] = isset($_REQUEST['store_keyword']) ? trim($_REQUEST['store_keyword']) : '';
        
        $store_where = '';
        $store_search_where = '';
        if($filter['store_search'] !=0){
           if($adminru['ru_id'] == 0){ 
               
               if($_REQUEST['store_type']){
                    $store_search_where = "AND msi.shopNameSuffix = '" .$_REQUEST['store_type']. "'";
                }
               
                if($filter['store_search'] == 1){
                    $where .= " AND g.user_id = '" .$filter['merchant_id']. "' ";
                }elseif($filter['store_search'] == 2){
                    $store_where .= " AND msi.rz_shopName LIKE '%" . mysql_like_quote($filter['store_keyword']) . "%'";
                }elseif($filter['store_search'] == 3){
                    $store_where .= " AND msi.shoprz_brandName LIKE '%" . mysql_like_quote($filter['store_keyword']) . "%' " . $store_search_where;
                }
                
                if($filter['store_search'] > 1 && $filter['store_search'] != 4){
                    $where .= " AND (SELECT msi.user_id FROM " . $GLOBALS['ecs']->table('merchants_shop_information') .' as msi ' .  
                              " WHERE msi.user_id = g.user_id $store_where) > 0 ";
                }else{
                    if($filter['store_search'] == 4){
                        $where .= " AND g.user_id = 0";
                    }
                }
           }
        }
        //管理员查询的权限 -- 店铺查询 end
		
        /* 推荐类型 */
        switch ($filter['intro_type'])
        {
            case 'is_best':
                $where .= " AND g.is_best=1";
                break;
            case 'is_hot':
                $where .= ' AND g.is_hot=1';
                break;
            case 'is_new':
                $where .= ' AND g.is_new=1';
                break;
            case 'is_promote':
                $where .= " AND g.is_promote = 1 AND g.promote_price > 0 AND g.promote_start_date <= '$today' AND g.promote_end_date >= '$today'";
                break;
            case 'all_type';
                $where .= " AND (g.is_best=1 OR g.is_hot=1 OR g.is_new=1 OR (g.is_promote = 1 AND g.promote_price > 0 AND g.promote_start_date <= '" . $today . "' AND g.promote_end_date >= '" . $today . "'))";
        }

        /* 库存警告 */
        if ($filter['stock_warning'])
        {
            $where .= ' AND g.goods_number <= g.warn_number ';
        }

        /* 扩展 */
        if ($filter['extension_code'])
        {
            $where .= " AND g.extension_code='$filter[extension_code]'";
        }

        /* 关键字 */
        if (!empty($filter['keyword']))
        {
            $where .= " AND (g.goods_sn LIKE '%" . mysql_like_quote($filter['keyword']) . "%' OR g.goods_name LIKE '%" . mysql_like_quote($filter['keyword']) . "%' OR g.bar_code LIKE '%" . mysql_like_quote($filter['keyword']) . "%'" . ")";
            //ecmoban模板堂 --zhuo end        
	}

        if ($real_goods > -1 && $real_division == 0)
        {
            $where .= " AND g.is_real='$real_goods'";
        }

        /* 供货商 */
        if (!empty($filter['suppliers_id']))
        {
            $where .= " AND (g.suppliers_id = '" . $filter['suppliers_id'] . "')";
        }
		
	/* 审核商品状态 ecmoban模板堂 --zhuo */
        if ($filter['review_status'] > 0) {
            if ($filter['review_status'] == 3) {
                $where .= " AND (g.review_status >= '" . $filter['review_status'] . "')";
            } else {
                $where .= " AND (g.review_status = '" . $filter['review_status'] . "')";
            }
        } else {
            $where .= " AND (g.review_status > 0)";
        }

        $where .= $ruCat;
        $where .= $conditions;

        /* 起始页通过商品一览点击进入自营/商家商品判断条件 */
        if ($_REQUEST['self'] == 1) {
            $where .= " AND g.user_id = 0 ";
            $filter['self'] = 1;
        } elseif ($_REQUEST['merchants'] == 1) {
            $where .= " AND g.user_id > 0 ";
            $filter['merchants'] = 1;
        }
        
        if($filter['cat_type'] != 'seller' && $filter['cat_id']){
            $where .= " AND (" . get_children($filter['cat_id']) . " OR " .get_children($filter['cat_id'], 1). ")";
            $leftjoin = " LEFT JOIN " . $GLOBALS['ecs']->table('goods_cat') . " AS gc ON g.goods_id = gc.goods_id";
        }
        
        /* 记录总数 */
        if ($filter['cat_type'] != 'seller' && $filter['cat_id']) {
            $groupby = " GROUP BY g.goods_id";
            $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('goods') . " AS g " .
                    $leftjoin .
                    " WHERE $where AND g.is_delete='$is_delete'" . $groupby;
            $filter['record_count'] = count($GLOBALS['db']->getAll($sql));
        } else {
            $goods_where = " $where AND g.is_delete='$is_delete'";
            $sql = "SELECT SUM(CASE WHEN " .$goods_where. " THEN 1 ELSE 0 END) FROM " . $GLOBALS['ecs']->table('goods') . " AS g " .
                    $leftjoin .
                    " WHERE 1";
            $filter['record_count'] = $GLOBALS['db']->getOne($sql);
        }

        /* 分页大小 */
        $filter = page_and_size($filter);
        
        $select = "";
        if (file_exists(MOBILE_DRP)) {
            $select .= ", g.is_distribution";
        }

        $sql = "SELECT g.goods_id,g.goods_thumb, g.goods_name, g.user_id, g.brand_id, g.goods_type, g.goods_sn, g.shop_price, " .
                "g.is_on_sale, g.is_best, g.is_new, g.is_hot, g.sort_order, g.goods_number, g.integral, g.commission_rate, " .
                "g.is_promote, g.model_price, g.model_inventory, g.model_attr, g.review_status, g.review_content, g.store_best, " .
                "g.store_new , g.store_hot , g.is_real, g.is_shipping, g.stages, g.goods_thumb, " .
                "g.is_alone_sale, g.is_xiangou, g.promote_end_date, g.xiangou_end_date, g.bar_code, g.freight, g.tid " . $select .
                " FROM " . $GLOBALS['ecs']->table('goods') . " AS g " .
                $leftjoin . 
                " WHERE  $where AND g.is_delete = '$is_delete' GROUP BY g.goods_id" .
                " ORDER BY $filter[sort_by] $filter[sort_order] " .
                " LIMIT " . $filter['start'] . ",$filter[page_size]";
      
        $filter['keyword'] = stripslashes($filter['keyword']);
        set_filter($filter, $sql, $param_str);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }
    $row = $GLOBALS['db']->getAll($sql);
    
    //当前域名协议
    $http = $GLOBALS['ecs']->http();
	
    $count = count($row);
    for ($i = 0; $i < $count; $i++) {
        $row[$i]['user_name'] = get_shop_name($row[$i]['user_id'], 1);
        
        $brand = get_goods_brand_info($row[$i]['brand_id']);
        $row[$i]['brand_name'] = $brand['brand_name'];
        
        $sql = "SELECT ga.goods_attr_id FROM " .$GLOBALS['ecs']->table('goods_attr') ." AS ga,". 
                $GLOBALS['ecs']->table('attribute') ." AS a". 
                " WHERE ga.goods_id = '" .$row[$i]['goods_id']. "' AND ga.attr_id = a.attr_id AND a.attr_type <> 0";
        if($GLOBALS['db']->getOne($sql, true)){
            $row[$i]['is_attr'] = 1;
        }else{
            $row[$i]['is_attr'] = 0;
        }
        
        if($row[$i]['freight'] == 2){
            $row[$i]['transport'] = get_goods_transport_info($row[$i]['tid']);
        }
        
        //图片显示
        $row[$i]['goods_thumb'] = get_image_path($row[$i]['goods_id'], $row[$i]['goods_thumb'], true);

        //商品扩展信息
        $row[$i]['goods_extend'] = get_goods_extend($row[$i]['goods_id']);
    }

    return array('goods' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
}

/**
 * 检测商品是否有货品
 *
 * @access      public
 * @params      integer     $goods_id       商品id
 * @params      string      $conditions     sql条件，AND语句开头
 * @return      string number               -1，错误；1，存在；0，不存在
 */
function check_goods_product_exist($goods_id, $conditions = '')
{
    if (empty($goods_id))
    {
        return -1;  //$goods_id不能为空
    }

    $sql = "SELECT goods_id
            FROM " . $GLOBALS['ecs']->table('products') . "
            WHERE goods_id = '$goods_id'
            " . $conditions . "
            LIMIT 0, 1";
    $result = $GLOBALS['db']->getRow($sql);

    if (empty($result))
    {
        return 0;
    }

    return 1;
}

/**
 * 获得商品的货品总库存
 *
 * @access      public
 * @params      integer     $goods_id       商品id
 * @params      string      $conditions     sql条件，AND语句开头
 * @return      string number
 */
function product_number_count($goods_id, $conditions = '', $warehouse_id = 0)
{
	//判断商品类型 by wu
	$goods_model = $GLOBALS['db']->getOne(" SELECT model_price FROM ".$GLOBALS['ecs']->table("goods")." WHERE goods_id = '$goods_id' ");
	if($goods_model == 1){
		$table = "products_warehouse";
	}elseif($goods_model == 2){
		$table = "products_area";
	}else{
		$table = "products";
	}	
	
    if (empty($goods_id))
    {
        return -1;  //$goods_id不能为空
    }

    $sql = "SELECT product_number
            FROM " . $GLOBALS['ecs']->table($table) . "
            WHERE goods_id = '$goods_id' 
            " . $conditions;
    $nums = $GLOBALS['db']->getOne($sql);
    $nums = empty($nums) ? 0 : $nums;

    return $nums;
}

/**
 * 获得商品的规格属性值列表
 *
 * @access      public
 * @params      integer         $goods_id
 * @return      array
 */
function product_goods_attr_list($goods_id)
{
    if (empty($goods_id))
    {
        return array();  //$goods_id不能为空
    }

    $sql = "SELECT goods_attr_id, attr_value FROM " . $GLOBALS['ecs']->table('goods_attr') . " WHERE goods_id = '$goods_id'";
    $results = $GLOBALS['db']->getAll($sql);

    $return_arr = array();
    foreach ($results as $value)
    {
        $return_arr[$value['goods_attr_id']] = $value['attr_value'];
    }

    return $return_arr;
}

/**
 * 获得商品已添加的规格列表
 *
 * @access      public
 * @params      integer         $goods_id
 * @return      array
 */
function get_goods_specifications_list($goods_id)
{
    $where = "";
    $admin_id = get_admin_id();
    if (empty($goods_id))
    {
        if($admin_id){
            $where .= " AND admin_id = '$admin_id'";
        }else{
            return array();  //$goods_id不能为空
        }
    }

    $sql = "SELECT g.goods_attr_id, g.attr_value, g.attr_id, a.attr_name
            FROM " . $GLOBALS['ecs']->table('goods_attr') . " AS g
                LEFT JOIN " . $GLOBALS['ecs']->table('attribute') . " AS a
                    ON a.attr_id = g.attr_id
            WHERE goods_id = '$goods_id'
            AND a.attr_type = 1" .$where.
            " ORDER BY a.sort_order, a.attr_id, g.goods_attr_id";
    $results = $GLOBALS['db']->getAll($sql);

    return $results;
}

/**
 * 获得商品的货品列表
 *
 * @access  public
 * @params  integer $goods_id
 * @params  string  $conditions
 * @return  array
 */
function product_list($goods_id, $conditions = '')
{
    /* 过滤条件 */
    $param_str = '-' . $goods_id;
    $result = get_filter($param_str);
    if ($result === false)
    {
        $day = getdate();
        $today = local_mktime(23, 59, 59, $day['mon'], $day['mday'], $day['year']);

        $filter['goods_id']         = $goods_id;
        $filter['keyword']          = empty($_REQUEST['keyword']) ? '' : trim($_REQUEST['keyword']);
        $filter['stock_warning']    = empty($_REQUEST['stock_warning']) ? 0 : intval($_REQUEST['stock_warning']);

        if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
        {
            $filter['keyword'] = json_str_iconv($filter['keyword']);
        }
        $filter['sort_by']          = empty($_REQUEST['sort_by']) ? 'product_id' : trim($_REQUEST['sort_by']);
        $filter['sort_order']       = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);
        $filter['extension_code']   = empty($_REQUEST['extension_code']) ? '' : trim($_REQUEST['extension_code']);
        $filter['page_count'] = isset($filter['page_count']) ? $filter['page_count'] : 1;

        $where = '';

        /* 库存警告 */
        if ($filter['stock_warning'])
        {
            $where .= ' AND goods_number <= warn_number ';
        }

        /* 关键字 */
        if (!empty($filter['keyword']))
        {
            $where .= " AND (product_sn LIKE '%" . $filter['keyword'] . "%')";
        }

        $where .= $conditions;

        /* 记录总数 */
        $sql = "SELECT COUNT(*) FROM " .$GLOBALS['ecs']->table('products'). " AS p WHERE goods_id = $goods_id $where";
        $filter['record_count'] = $GLOBALS['db']->getOne($sql);

        $sql = "SELECT product_id, goods_id, goods_attr, product_sn, bar_code, product_price, product_number
                FROM " . $GLOBALS['ecs']->table('products') . " AS g
                WHERE goods_id = $goods_id $where
                ORDER BY $filter[sort_by] $filter[sort_order]";

        $filter['keyword'] = stripslashes($filter['keyword']);
        //set_filter($filter, $sql, $param_str);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }
    $row = $GLOBALS['db']->getAll($sql);

    /* 处理规格属性 */
    $goods_attr = product_goods_attr_list($goods_id);
    foreach ($row as $key => $value)
    {
        $_goods_attr_array = explode('|', $value['goods_attr']);
        if (is_array($_goods_attr_array))
        {
            $_temp = '';
            foreach ($_goods_attr_array as $_goods_attr_value)
            {
                 $_temp[] = $goods_attr[$_goods_attr_value];
            }
            $row[$key]['goods_attr'] = $_temp;
        }
    }

    return array('product' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
}

/**
 * 取货品信息
 *
 * @access  public
 * @param   int         $product_id     货品id
 * @param   int         $filed          字段
 * @param   int         $is_attr        属性组
 * @return  array
 */
function get_product_info($product_id, $filed = '', $goods_model = 0, $is_attr = 0)
{
    $return_array = array();

    if (empty($product_id))
    {
        return $return_array;
    }

    $filed = trim($filed);
    if (empty($filed))
    {
        $filed = '*';
    }
    
    if($goods_model == 1){
        $table = "products_warehouse";
    }elseif($goods_model == 2){
        $table = "products_area";
    }else{
        $table = "products";
    }

    $sql = "SELECT $filed FROM  " . $GLOBALS['ecs']->table($table) . " WHERE product_id = '$product_id'";
    $return_array = $GLOBALS['db']->getRow($sql);
    
    if($is_attr == 1){
        if($return_array['goods_attr']){
            $goods_attr_id = str_replace("|", ",", $return_array['goods_attr']);
            $return_array['goods_attr'] = get_product_attr_list($goods_attr_id, $return_array['goods_id'], $goods_model, $return_array['warehouse_id'], $return_array['area_id']);
        }
    }

    return $return_array;
}

function get_product_attr_list($goods_attr_id = 0, $goods_id = 0, $goods_model = 0, $warehouse_id = 0, $area_id = 0) {
    
    $leftJion = '';
    if($goods_model == 1){
        $where = " AND wa.goods_id = ga.goods_id AND warehouse_id = '$warehouse_id' ";
        $leftJion = ' LEFT JOIN ' . $GLOBALS['ecs']->table('warehouse_attr') . ' AS wa ON wa.goods_attr_id = ga.goods_attr_id ' . $where;
        $select = ", wa.attr_price AS attr_price, warehouse_id, wa.id";
    }elseif($goods_model == 2){
        $where = " AND waa.goods_id = ga.goods_id AND area_id = '$area_id' ";
        $leftJion = ' LEFT JOIN ' . $GLOBALS['ecs']->table('warehouse_area_attr') . ' AS waa ON waa.goods_attr_id = ga.goods_attr_id ' . $where;
        $select = ", waa.attr_price AS attr_price, area_id, waa.id";
    }else{
        $select = ", ga.attr_price AS attr_price";
    }

    $sql = "SELECT  ga.goods_attr_id, ga.attr_id, ga.attr_value $select FROM  " . $GLOBALS['ecs']->table('goods_attr') . " AS ga " .
            ' LEFT JOIN ' . $GLOBALS['ecs']->table('attribute') . ' AS a ON a.attr_id = ga.attr_id ' .
            $leftJion . 
            " WHERE ga.goods_attr_id IN($goods_attr_id) AND ga.goods_id = '$goods_id'" .
            ' ORDER BY a.sort_order, a.attr_id, ga.goods_attr_id';
    $res = $GLOBALS['db']->getAll($sql);
    
    return $res;
}

/**
 * 检查单个商品是否存在规格
 *
 * @param   int        $goods_id          商品id
 * @return  bool                          true，存在；false，不存在
 */
function check_goods_specifications_exist($goods_id)
{
    $goods_id = intval($goods_id);

    $sql = "SELECT COUNT(a.attr_id)
            FROM " .$GLOBALS['ecs']->table('attribute'). " AS a, " .$GLOBALS['ecs']->table('goods'). " AS g
            WHERE a.cat_id = g.goods_type
            AND g.goods_id = '$goods_id'";

    $count = $GLOBALS['db']->getOne($sql);

    if ($count > 0)
    {
        return true;    //存在
    }
    else
    {
        return false;    //不存在
    }
}

/**
 * 商品的货品规格是否存在
 *
 * @param   string     $goods_attr        商品的货品规格
 * @param   string     $goods_id          商品id
 * @param   int        $product_id        商品的货品id；默认值为：0，没有货品id
 * @return  bool                          true，重复；false，不重复
 */
function check_goods_attr_exist($goods_attr, $goods_id, $product_id = 0, $region_id = 0)
{
    //判断商品类型 by wu
    $where_products = "";
    $goods_model = $GLOBALS['db']->getOne(" SELECT model_price FROM ".$GLOBALS['ecs']->table("goods")." WHERE goods_id = '$goods_id' ");
    if($goods_model == 1){
            $table = "products_warehouse";
            $where_products .= " AND warehouse_id = '$region_id' ";
    }elseif($goods_model == 2){
            $table = "products_area";
            $where_products .= " AND area_id = '$region_id' ";
    }else{
            $table = "products";
    }
	
    $goods_id = intval($goods_id);
    if (strlen($goods_attr) == 0 || empty($goods_id))
    {
        return true;    //重复
    }

    if (empty($product_id))
    {
        $sql = "SELECT product_id FROM " . $GLOBALS['ecs']->table($table) ."
                WHERE goods_attr = '$goods_attr'
                AND goods_id = '$goods_id'".$where_products;
    }
    else
    {
        $sql = "SELECT product_id FROM " . $GLOBALS['ecs']->table($table) ."
                WHERE goods_attr = '$goods_attr'
                AND goods_id = '$goods_id'
                AND product_id <> '$product_id'".$where_products;
    }

    $res = $GLOBALS['db']->getOne($sql);

    if (empty($res))
    {
        return false;    //不重复
    }
    else
    {
        return true;    //重复
    }
}

/**
 * 商品的货品货号是否重复
 *
 * @param   string     $product_sn        商品的货品货号；请在传入本参数前对本参数进行SQl脚本过滤
 * @param   int        $product_id        商品的货品id；默认值为：0，没有货品id
 * @param   int        $goods_model       商品模式；0为默认，1为仓库，2为地区
 * @return  bool                          true，重复；false，不重复
 */
function check_product_sn_exist($product_sn, $product_id = 0, $ru_id = 0, $goods_model = 0) {

    //判断商品类型 by wu
    if ($goods_model == 1) {
        $table = "products_warehouse";
    } elseif ($goods_model == 2) {
        $table = "products_area";
    } else {
        $table = "products";
    }

    $product_sn = trim($product_sn);
    $product_id = intval($product_id);
    if (strlen($product_sn) == 0) {
        return true;    //重复
    }
    
    if (!empty($product_id)) {
        $sql = "SELECT g.user_id FROM " . $GLOBALS['ecs']->table($table) . " AS p, " .
                $GLOBALS['ecs']->table('goods') . " AS g" .
                " WHERE p.goods_id = g.goods_id AND p.product_id = '$product_id'";
        $ru_id = $GLOBALS['db']->getOne($sql, true);
    }else{
        $ru_id = 0;
    }

    $sql = "SELECT g.goods_id FROM " . $GLOBALS['ecs']->table('goods') . " AS g WHERE g.goods_sn='$product_sn' AND g.user_id = '$ru_id'";
    if ($GLOBALS['db']->getOne($sql)) {
        return true;    //重复
    }

    $where = " AND (SELECT g.user_id FROM " . $GLOBALS['ecs']->table('goods') . " AS g WHERE g.goods_id = p.goods_id LIMIT 1) = '$ru_id'";

    if (empty($product_id))
    {
        $sql = "SELECT p.product_id FROM " . $GLOBALS['ecs']->table($table)  ." AS p "."
                WHERE p.product_sn = '$product_sn'" . $where;
    }
    else
    {
        $sql = "SELECT p.product_id FROM " . $GLOBALS['ecs']->table($table) ." AS p "."
                WHERE p.product_sn = '$product_sn'
                AND p.product_id <> '$product_id'" . $where;
    }
    
    $res = $GLOBALS['db']->getOne($sql);

    if (empty($res)) {
        return false;    //不重复
    } else {
        return true;    //重复
    }
}

/**
 * 商品的货品货号是否重复
 *
 * @param   string     $product_bar_code        商品的货品货号；请在传入本参数前对本参数进行SQl脚本过滤
 * @param   int        $product_id        商品的货品id；默认值为：0，没有货品id
 * @param   int        $goods_model       商品模式；0为默认，1为仓库，2为地区
 * @return  bool                          true，重复；false，不重复
 */
function check_product_bar_code_exist($product_bar_code, $product_id = 0, $ru_id = 0, $goods_model = 0) {
    //判断商品类型 by wu
    if ($goods_model == 1) {
        $table = "products_warehouse";
    } elseif ($goods_model == 2) {
        $table = "products_area";
    } else {
        $table = "products";
    }

    $product_bar_code = trim($product_bar_code);
    $product_id = intval($product_id);
    if (strlen($product_bar_code) == 0) {
        return true;    //重复
    }
    
    if (!empty($product_id)) {
        $sql = "SELECT g.user_id FROM " . $GLOBALS['ecs']->table($table) . " AS p, " .
                $GLOBALS['ecs']->table('goods') . " AS g" .
                " WHERE p.goods_id = g.goods_id AND p.product_id = '$product_id'";
        $ru_id = $GLOBALS['db']->getOne($sql, true);
    }else{
        $ru_id = 0;
    }

    $sql = "SELECT g.goods_id FROM " . $GLOBALS['ecs']->table('goods') . " AS g WHERE g.bar_code = '$product_bar_code' AND g.user_id = '$ru_id'";
    if ($GLOBALS['db']->getOne($sql)) {
        return true;    //重复
    }

    $where = " AND (SELECT g.user_id FROM " . $GLOBALS['ecs']->table('goods') . " AS g WHERE g.goods_id = p.goods_id LIMIT 1) = '$ru_id'";

    if (empty($product_id))
    {
        $sql = "SELECT p.product_id FROM " . $GLOBALS['ecs']->table($table)  ." AS p "."
                WHERE p.bar_code = '$product_bar_code'" . $where;
    }
    else
    {
        $sql = "SELECT p.product_id FROM " . $GLOBALS['ecs']->table($table) ." AS p "."
                WHERE p.bar_code = '$product_bar_code'
                AND p.product_id <> '$product_id'" . $where;
    }
    
    $res = $GLOBALS['db']->getOne($sql);

    if (empty($res)) {
        return false;    //不重复
    } else {
        return true;    //重复
    }
}

/**
 * 格式化商品图片名称（按目录存储）
 *
 */
function reformat_image_name($type, $goods_id, $source_img, $position='')
{
    $rand_name = gmtime() . sprintf("%03d", mt_rand(1,999));
    $img_ext = substr($source_img, strrpos($source_img, '.'));
    $dir = 'images';
    if (defined('IMAGE_DIR'))
    {
        $dir = IMAGE_DIR;
    }
    $sub_dir = date('Ym', gmtime());
    if (!make_dir(ROOT_PATH.$dir.'/'.$sub_dir))
    {
        return false;
    }
    if (!make_dir(ROOT_PATH.$dir.'/'.$sub_dir.'/source_img'))
    {
        return false;
    }
    if (!make_dir(ROOT_PATH.$dir.'/'.$sub_dir.'/goods_img'))
    {
        return false;
    }
    if (!make_dir(ROOT_PATH.$dir.'/'.$sub_dir.'/thumb_img'))
    {
        return false;
    }
    switch($type)
    {
        case 'goods':
            $img_name = $goods_id . '_G_' . $rand_name;
            break;
        case 'goods_thumb':
            $img_name = $goods_id . '_thumb_G_' . $rand_name;
            break;
        case 'gallery':
            $img_name = $goods_id . '_P_' . $rand_name;
            break;
        case 'gallery_thumb':
            $img_name = $goods_id . '_thumb_P_' . $rand_name;
            break;
    }
	
    if (strpos($source_img, 'temp') !== false) {
        $ex_img = explode('temp', $source_img);
        $source_img = "temp" . $ex_img[1];
    } else {
        if (strpos($source_img, ROOT_PATH) !== false) {
            $source_img = !empty($source_img) ? str_replace(ROOT_PATH, '', $source_img) : '';
        }
    }
    if ($position == 'source')
    {
        if (move_image_file(ROOT_PATH.$source_img, ROOT_PATH.$dir.'/'.$sub_dir.'/source_img/'.$img_name.$img_ext))
        {
            return $dir.'/'.$sub_dir.'/source_img/'.$img_name.$img_ext;
        }
    }
    elseif ($position == 'thumb')
    {
        if (move_image_file(ROOT_PATH.$source_img, ROOT_PATH.$dir.'/'.$sub_dir.'/thumb_img/'.$img_name.$img_ext))
        {
            return $dir.'/'.$sub_dir.'/thumb_img/'.$img_name.$img_ext;
        }
    }
    else
    {
        if (move_image_file(ROOT_PATH.$source_img, ROOT_PATH.$dir.'/'.$sub_dir.'/goods_img/'.$img_name.$img_ext))
        {
            return $dir.'/'.$sub_dir.'/goods_img/'.$img_name.$img_ext;
        }
    }
    return false;
}

/**
 * 移动图片
 */
function move_image_file($source, $dest)
{
    if (@copy($source, $dest))
    {
        @unlink($source);
        return true;
    }
    return false;
}

//获取最近使用分类 by wu
function get_recently_used_category($admin_id = 0) {

    $sql = " SELECT recently_cat FROM " . $GLOBALS['ecs']->table("admin_user") . " WHERE user_id = '$admin_id' LIMIT 5 ";
    $recently_cat = $GLOBALS['db']->getOne($sql);
	if(empty($recently_cat)){
		$recently_used_category = array();
	}else{
		$cat_list = explode(',', $recently_cat);
		$cat_list = array_slice($cat_list,-5);//取最新的五条记录
		$recently_used_category = array();
		foreach ($cat_list as $key => $val) {
			$recently_used_category[$val] = get_every_category($val);
		}		
	}
    
    $GLOBALS['smarty']->assign('recently_used_category', $recently_used_category);

    return true;
}

/**
 * 类型商品数量 start
 */
function get_goods_type_list($status = 0, $extension_code = '', $delete = 0, $type = 0, $check_real = 1){
    
    $where = "1";
    if(is_array($status) && !empty($status)){
        $status = implode(",", $status);
        $where .= " AND review_status IN($status)";
    }else if($status == 1 || $status == 2){
        $where .= " AND review_status = '$status'";
    }else if($status == 3){
        $where .= " AND review_status > 2";
    }
    
    //上架
    if($type == 1)
    {
        $where .= " AND is_on_sale  = 1";
    }
    //下架
    else if($type == 2)
    {
        $where .= " AND is_on_sale  = 0";
    }
    
    if($delete){
        $where .= " AND is_delete = 1";
    }else{
        $where .= " AND is_delete = 0";
    }
    
    if (!empty($extension_code)) {
        if ($extension_code == 'virtual_card') {
            $where .= " AND extension_code = 'virtual_card'";
        } else if ($extension_code == 'ordinary') {
            $where .= " AND extension_code = ''";
        }
    }
	
    
    if($status && !$type && $check_real){
        if ($extension_code == 'virtual_card') {
            $where .= " AND is_real = 0";
        }else{
            $where .= " AND is_real = 1";
        }
    }

    $sql = "SELECT SUM(CASE WHEN $where THEN 1 ELSE 0 END) FROM".$GLOBALS['ecs']->table('goods');
//    $sql = "SELECT COUNT(*) FROM " .$GLOBALS['ecs']->table('goods'). " WHERE $where"; 

    return $GLOBALS['db']->getOne($sql);
}

/**
 * 类型商品数量 end
 */
function get_goods_type_number($act = ''){
    
    
    $arr['review_status'] = get_goods_type_list(array(1, 2), '', 0, 0, 0);
    $arr['ordinary'] = get_goods_type_list(3, 'ordinary');
    $arr['virtual_card'] = get_goods_type_list(3, 'virtual_card');
    $arr['delete'] = get_goods_type_list(0, '', 1);
    
    $arr['not_status'] = get_goods_type_list(1);
    $arr['not_pass'] = get_goods_type_list(2);
    
    $arr['is_sale'] = get_goods_type_list(3, '', 0, 1);
    $arr['on_sale'] = get_goods_type_list(3, '', 0, 2);
    
    return $arr;
}

/*
 * 一键同步OSS图片
 */
function get_img_file_list($is_ajax = 0, $file_dir){

    $page = !empty($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;  //默认第一页
    $page_size = isset($_REQUEST['page_size']) ? intval($_REQUEST['page_size']) : 15; //每页10条

    $dir = ROOT_PATH . IMAGE_DIR;
    $file_list = scandir($dir);

    $arr = array();
    if ($file_list) {
        foreach ($file_list as $key => $row) {
            if (!is_dir($row) && str_len($row) == 6 && !in_array($row, $file_dir)) {
                $arr[$key]['file_name'] = $dir ."/". $row;
                $arr[$key]['goods_img'] = $GLOBALS['ecs']->get_file_list($dir ."/". $row . "/goods_img");
                $arr[$key]['source_img'] = $GLOBALS['ecs']->get_file_list($dir ."/". $row . "/source_img");
                $arr[$key]['thumb_img'] = $GLOBALS['ecs']->get_file_list($dir ."/". $row . "/thumb_img");
            }
        }

        $arr = $GLOBALS['ecs']->page_array($page_size, $page, $arr);
    }
    
    return $arr;
}

/*
 * 一键同步OSS图片
 */
function get_goods_img_list($is_ajax = 0, $file_dir, $is_sel = 0) {
    
    $page = !empty($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;  //默认第一页
    $page_size = isset($_REQUEST['page_size']) ? intval($_REQUEST['page_size']) : 100; //每页10条

    $file_parent_dir1 = array('common', 'qrcode', 'upload');
    $file_parent_dir2 = array('File', 'Flash', 'Image', 'Image', 'Media');

    $images_file = read_static_cache('goods_images_file', DATA_DIR . '/sc_file/');
    
    if ($images_file === false) {
        
        $arr = get_img_merge_func($dir, $file_parent_dir1, $file_parent_dir2, $file_dir);
        
        if(empty($is_sel)){
            write_static_cache('goods_images_file', $arr, DATA_DIR . '/sc_file/');
        }
        
    } else {
        
        if($is_sel){
            $arr = get_img_merge_func($dir, $file_parent_dir1, $file_parent_dir2, $file_dir);
        }else{
            $arr = $images_file;
        }
    }
    
    $arr = $GLOBALS['ecs']->page_array($page_size, $page, $arr);
    return $arr;
}

/* 目录信息 */
function get_img_merge_func($dir, $file_parent_dir1, $file_parent_dir2, $file_dir){
    
    $arr = array();
    
    $dir = ROOT_PATH . IMAGE_DIR;
    $arr1 = get_img_file($dir, $file_parent_dir1, $file_dir);
    $arr2 = get_img_file($dir . "/upload", $file_parent_dir2, array(), 8);

    if ($arr1 && $arr2) {
        $arr = array_merge($arr1, $arr2);
    } elseif ($arr1) {
        $arr = $arr1;
    } elseif ($arr2) {
        $arr = $arr2;
    }
    
    return $arr;
}
/**
 * 
 * $dir 路径地址
 * $file_parent_dir 数组（排除目录）
 * $file_dir 数组（查询的目录）
 * $strlen 字符串长度
 */
function get_img_file($dir, $file_parent_dir, $file_dir, $strlen = 6) {

    $file_list = scandir($dir);
    $arr = array();
    if ($file_list) {
        foreach ($file_list as $key => $row) {
            if (!is_dir($row) && str_len($row) == $strlen && !in_array($row, $file_parent_dir)) {
                $arr[$key]['file_name'] = $dir . "/" . $row;

                if ($file_dir) {
                    foreach ($file_dir as $ikey => $irow) {
                        if (!is_dir($irow) && file_exists($arr[$key]['file_name'] . "/" . $irow)) {
                            $child = scandir($arr[$key]['file_name'] . "/" . $irow);
                            foreach ($child as $iikey => $iirow) {
                                if (!is_dir($iirow)) {
                                    $path = str_replace(ROOT_PATH, '', $arr[$key]['file_name']);
                                    $arr[$key][$irow]['img_list'][$iikey] = $path . "/" . $irow . "/" . $iirow;
                                }

                                if ($arr[$key][$irow]['img_list'][$iikey] == '.' || $arr[$key][$irow]['img_list'][$iikey] == '..') {
                                    unset($arr[$key][$irow]['img_list'][$iikey]);
                                }
                            }
                        }
                    }

                    unset($arr[$key]['file_name']);
                } else {

                    $path = str_replace(ROOT_PATH, '', $arr[$key]['file_name']);

                    $arr[$key] = scandir($arr[$key]['file_name']);
                    foreach ($arr[$key] as $ikey => $irow) {
                        if (!is_dir($irow)) {
                            $arr[$key][$ikey] = $path . "/" . $irow;
                        }

                        if ($arr[$key][$ikey] == '.' || $arr[$key][$ikey] == '..') {
                            unset($arr[$key][$ikey]);
                        }
                    }
                }
            }
        }
    }

    if ($arr) {
        $arr = arr_foreach($arr);
    }

    return $arr;
}

/**
 * 获得秒杀活动的商品
 *
 * @access  public
 * @param   integer $goods_id
* @return  array
 */
function get_add_seckill_goods($sec_id, $tb_id)
{	
	$filter['sec_id'] = $sec_id = empty($sec_id) ? $filter['sec_id'] : $sec_id;
	$filter['tb_id'] = $tb_id 	= empty($tb_id)  ? $filter['tb_id']  : $tb_id;
	
    $result = get_filter();
    if ($result === false)
    {
		$where = " where 1 ";
		$where .= " AND sg.sec_id = '$sec_id' AND sg.tb_id = '$tb_id' ";
		$sql = " SELECT COUNT(*) FROM ". $GLOBALS['ecs']->table('seckill_goods') . " AS sg LEFT JOIN " .
			   $GLOBALS['ecs']->table('goods') . " AS g ON sg.goods_id = g.goods_id  " .$where .
			   " ORDER BY sg.tb_id ASC, sg.goods_id ASC ";
        $filter['record_count'] = $GLOBALS['db']->getOne($sql);
        $filter = page_and_size($filter);

        /* 获活动数据 */
		$sql = " SELECT sg.id, sg.sec_id, sg.tb_id, sg.sec_num, sg.sec_limit, sg.sec_price, g.goods_name, g.shop_price FROM ".
			   $GLOBALS['ecs']->table('seckill_goods') . " AS sg LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON sg.goods_id = g.goods_id  " .$where .
			   " ORDER BY sg.tb_id ASC, sg.goods_id ASC LIMIT ". $filter['start'] .", " . $filter['page_size'];
        
        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }
    $row = $GLOBALS['db']->getAll($sql);

    foreach ($row AS $key => $val)
    {
		$row[$key]['shop_price'] = price_format($val['shop_price']);
    }
	
    $arr = array('seckill_goods' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
    return $arr;	
	
}

/*
*
*/
function insert_recently_used_category($cat_id, $admin_id){
	$sql = " SELECT recently_cat FROM ".$GLOBALS['ecs']->table('admin_user')." WHERE user_id = '$admin_id' ";
	$recently_cat = $GLOBALS['db']->getOne($sql);
	if($recently_cat){
		$arr = explode(',',$recently_cat);		
	}

	
	if(count($arr) >= 5){
		array_pop($arr);
	}

	if(empty($arr)){
		$str = $cat_id;
	}else{
		array_unshift($arr, $cat_id);
		$arr = array_unique($arr);
		$str = implode(',',$arr);		
	}

	$sql = "UPDATE " . $GLOBALS['ecs']->table('admin_user') .
		   " SET recently_cat = '$str' " .
		   "WHERE user_id = '$admin_id' ";
	$GLOBALS['db']->query($sql);
}
/**
 * 获得商品库商品列表
 *
 * @access  public
 * @params  integer $isdelete
 * @params  integer $real_goods
 * @params  integer $conditions
 * @return  array
 */
function lib_goods_list($real_goods = 1, $conditions = '', $review_status = 0, $real_division = 0)
{
    /* 过滤条件 */
    $param_str = '-' . $real_goods . '-' . $review_status;
    $result = get_filter($param_str);
	$adminru = get_admin_ru_id();
	
    if ($result === false)
    {
        $day = getdate();
        $today = local_mktime(23, 59, 59, $day['mon'], $day['mday'], $day['year']);
        
        $filter['cat_id']           = empty($_REQUEST['cat_id']) ? 0 : intval($_REQUEST['cat_id']);
        $filter['intro_type']       = empty($_REQUEST['intro_type']) ? '' : trim($_REQUEST['intro_type']);
        $filter['brand_id']         = empty($_REQUEST['brand_id']) ? 0 : intval($_REQUEST['brand_id']);
        $filter['brand_keyword'] = empty($_REQUEST['brand_keyword']) ? '' : trim($_REQUEST['brand_keyword']);
        $filter['cat_type']         = isset($_REQUEST['cat_type']) && empty($_REQUEST['cat_type']) ? '' : addslashes($_REQUEST['cat_type']);
        $filter['keyword']          = empty($_REQUEST['keyword']) ? '' : trim($_REQUEST['keyword']);
        
        if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
        {
            $filter['keyword'] = json_str_iconv($filter['keyword']);
        }
        
        if(isset($_REQUEST['review_status'])){
            $filter['review_status']    = empty($_REQUEST['review_status']) ? 0 : intval($_REQUEST['review_status']); //ecmoban模板堂 --zhuo
        }else{
            $filter['review_status']    = $review_status;
        }
        
        $filter['sort_by']          = empty($_REQUEST['sort_by']) ? 'g.goods_id' : trim($_REQUEST['sort_by']);
        $filter['sort_order']       = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);
        $filter['extension_code']   = empty($_REQUEST['extension_code']) ? '' : trim($_REQUEST['extension_code']);
        $filter['real_goods']       = $real_goods;
        
        $where = 1;

        if ($adminru['ru_id'] > 0) {
            $where .= "  AND g.is_on_sale = 1 ";
        }
        
        if ($filter['brand_keyword'])
        {
            $filter['brand_id'] = $GLOBALS['db']->getAll("SELECT GROUP_CONCAT(brand_id) AS brand_id FROM ".$GLOBALS['ecs']->table('brand')." WHERE brand_name LIKE '%$brand_keyword%' ");
            $where .= " AND (g.brand_id = '" . db_create_in($filter['brand_id']) . "')";
        }
        
        if ($filter['brand_id']) 
        {
            $where .= " AND (g.brand_id = '" . $filter['brand_id'] . "')";
        }

        /* 扩展 */
        if ($filter['extension_code'])
        {
            $where .= " AND g.extension_code='$filter[extension_code]'";
        }

        /* 关键字 */
        if (!empty($filter['keyword']))
        {
            $where .= " AND (g.goods_sn LIKE '%" . mysql_like_quote($filter['keyword']) . "%' OR g.goods_name LIKE '%" . mysql_like_quote($filter['keyword']) . "%'" . ")";
            //ecmoban模板堂 --zhuo end        
	}

        if ($real_goods > -1 && $real_division == 0)
        {
            $where .= " AND g.is_real='$real_goods'";
        }

        $where .= $ruCat;

        $where .= $conditions;

        $groupby = '';
        if($filter['cat_type'] != 'seller' && $filter['cat_id']){
            $where .= " AND (" . get_children($filter['cat_id']) . " OR " .get_children($filter['cat_id'], 1). ")";
            $select = "g.goods_id";
            $groupby = " GROUP BY g.goods_id";
        }else{
            $select = "COUNT(*)";
        }

        /* 记录总数 */
        $sql = "SELECT $select FROM " . $GLOBALS['ecs']->table('goods_lib') . " AS g " .
               " WHERE $where " . $groupby;
        
        if($filter['cat_type'] != 'seller' && $filter['cat_id']){
            $filter['record_count'] = count($GLOBALS['db']->getAll($sql));
        }else{
            $filter['record_count'] = $GLOBALS['db']->getOne($sql);
        }

        /* 分页大小 */
        $filter = page_and_size($filter);
		
        $sql =  " SELECT g.goods_id, g.lib_cat_id, g.goods_thumb, g.goods_name, g.brand_id, g.goods_type, g.goods_sn, g.shop_price, g.sort_order, g.is_real, g.bar_code, g.is_on_sale " .
                " FROM " . $GLOBALS['ecs']->table('goods_lib') . " AS g " .
                " WHERE  $where GROUP BY g.goods_id" .
                " ORDER BY $filter[sort_by] $filter[sort_order] " .
                " LIMIT " . $filter['start'] . ",$filter[page_size]";

        $filter['keyword'] = stripslashes($filter['keyword']);
        set_filter($filter, $sql, $param_str);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }
    $row = $GLOBALS['db']->getAll($sql);
    
    $count = count($row);
    for ($i = 0; $i < $count; $i++) {
		$row[$i]['lib_cat_name'] = lib_cat_name($row[$i]['lib_cat_id']);
        
        if($row[$i]['freight'] == 2){
            $row[$i]['transport'] = get_goods_transport_info($row[$i]['tid']);
        }
        
        //图片显示
        $row[$i]['goods_thumb'] = get_image_path($row[$i]['goods_id'], $row[$i]['goods_thumb'], true);
        
        //商品扩展信息
        $row[$i]['goods_extend'] = get_goods_extend($row[$i]['goods_id']);
    }

    return array('goods' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
}

/*
 * 指定分类ID返回分类名称
 */

function lib_cat_name($cat_id) {
    $sql = " SELECT cat_name FROM " . $GLOBALS['ecs']->table('goods_lib_cat') . " WHERE cat_id = '$cat_id' ";
    return $GLOBALS['db']->getOne($sql);
}

?>