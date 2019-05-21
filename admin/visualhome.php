<?php

/**
 * 商创首页可视化
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
require(ROOT_PATH . '/includes/lib_visual.php');

admin_priv('visualhome');

//首页模板列表
if($_REQUEST['act'] == 'list'){
     /* 获得可用的模版 */
    $available_templates = array();
    /*默认模板*/
    $dir = ROOT_PATH . 'data/home_Templates/'.$GLOBALS['_CFG']['template']. '/';
    if(file_exists($dir)){
         $template_dir        = @opendir($dir);
        while ($file = readdir($template_dir))
        {
            if ($file != '.' && $file != '..' && $file != '.svn' && $file != 'index.htm')
            {
                $available_templates[] = get_seller_template_info($file,0,$GLOBALS['_CFG']['template']);
            }
        }
            $available_templates = get_array_sort($available_templates, 'sort');
        @closedir($template_dir);
    }
    /* 获取默认模板 */
    $sql = "SELECT value FROM" . $GLOBALS['ecs']->table('shop_config') . " WHERE code= 'hometheme' AND store_range = '".$GLOBALS['_CFG']['template']."'";
    $default_tem = $GLOBALS['db']->getOne($sql);
    $smarty->assign('default_tem', $default_tem);
    $smarty->assign('available_templates',$available_templates);
    $smarty->assign('full_page', 1);
    $smarty->display('visualhome_list.dwt');
}
elseif($_REQUEST['act'] == 'visual'){
    
    $des = ROOT_PATH . 'data/home_Templates/'.$GLOBALS['_CFG']['template'];
    $code = isset($_REQUEST['code']) && !empty($_REQUEST['code']) ? trim($_REQUEST['code']) : '';
    
    if (empty($code)) {
        $sql = "SELECT value FROM" . $GLOBALS['ecs']->table('shop_config') . " WHERE code= 'hometheme' AND store_range = '".$GLOBALS['_CFG']['template']."'";
        $code = $GLOBALS['db']->getOne($sql, true);
    }
    
    /**
     * 首页可视化
     * 下载OSS模板文件
     */
    get_down_hometemplates($code);
    
    if(!file_exists($des."/".$code."/nav_html.php") && !file_exists($des."/".$code."/temp/nav_html.php")){
        /* 获取导航数据 */
        $sql = "SELECT id, name, ifshow, vieworder, opennew, url, type".
                   " FROM ".$GLOBALS['ecs']->table('nav')."WHERE type = 'middle'";
        $navigator = $db->getAll($sql);
        $smarty->assign('navigator',$navigator);
    }
    $filename = '';
    $is_temp = 0;
    //如果存在缓存文件  ，调用缓存文件
    if(file_exists($des."/".$code."/temp/pc_page.php")){
        $filename = $des."/".$code."/temp/pc_page.php";
        $is_temp = 1;
    }else{
        $filename = $des. "/" .  $code.'/pc_page.php';
    }
    $arr['tem'] = $code;
    $arr['out'] = get_html_file($filename);
    
    $replace_data = array(
        'http://localhost/ecmoban_dsc2.0.5_20170518/',
        'http://localhost/ecmoban_dsc2.2.6_20170727/',
        'http://localhost/ecmoban_dsc2.3/'
    );
    $arr['out'] = str_replace($replace_data, $ecs->url(), $arr['out']);

    $content = getleft_attr("content",0,$arr['tem'],$GLOBALS['_CFG']['template']);
    $bonusadv = getleft_attr("bonusadv",0,$arr['tem'],$GLOBALS['_CFG']['template']);
    $bonusadv['img_file'] = get_image_path(0, $bonusadv['img_file'], true,'','',true);
    $smarty->assign('content',$content);
    $smarty->assign('bonusadv',$bonusadv);
    $smarty->assign('pc_page',$arr);
    $smarty->assign('is_temp',$is_temp);
    $smarty->assign("shop_name",$_CFG['shop_name']);
    $smarty->assign("home","home");
	$smarty->assign('vis_section',"vis_home");
    $smarty->display('visualhome.dwt');
}
//生成缓存
elseif($_REQUEST['act'] == 'file_put_visual'){
    require(ROOT_PATH . '/includes/cls_json.php');
    $json = new JSON;
    $result = array( 'suffix' => '','error' => '');
    
    $temp = isset($_REQUEST['temp'])  ? intval(($_REQUEST['temp']))  : 0;
    /*后台缓存内容*/
    $content = isset($_REQUEST['content'])  ? unescape($_REQUEST['content'])  : '';
    $content = !empty($content) ? stripslashes($content) : '';
    /*前台缓存内容*/
    $content_html = isset($_REQUEST['content_html'])  ? unescape($_REQUEST['content_html'])  : '';
    $content_html = !empty($content_html) ? stripslashes($content_html) : '';
    
    $des = ROOT_PATH . 'data/home_Templates/'.$GLOBALS['_CFG']['template'];
    
    $suffix = !empty($_REQUEST['suffix'])  ? addslashes($_REQUEST['suffix']) : get_new_dirName(0,$des);
    $pc_page_name = "pc_page.php";
    if($temp == 1){
        $pc_html_name = "nav_html.php";
    }
    elseif($temp == 2){
        $pc_html_name = "topBanner.php";
    }
    else{
        $pc_html_name = "pc_html.php";
    }

    $create_html = create_html($content_html,$adminru['ru_id'],$pc_html_name,$suffix,3);
    $create = create_html($content,$adminru['ru_id'],$pc_page_name,$suffix,3);
    $result['error'] = 0;
    $result['suffix'] = $suffix;

    die(json_encode($result));
}
//修改模板信息
elseif($_REQUEST['act'] == 'edit_information'){
    require(ROOT_PATH . '/includes/cls_json.php');
    $json = new JSON;
    $result = array( 'suffix' => '','error' => '');
    $allow_file_types = '|GIF|JPG|PNG|';
    include_once(ROOT_PATH . '/includes/cls_image.php');
    $image = new cls_image($_CFG['bgcolor']);
    $check = !empty($_REQUEST['check'])  ?  intval($_REQUEST['check']) : 0;
    $tem = isset($_REQUEST['tem'])  ?  addslashes($_REQUEST['tem']) : '';
    $name = isset($_REQUEST['name'])  ?   "tpl name：".addslashes($_REQUEST['name']) : 'tpl name：';
    $version = isset($_REQUEST['version'])  ?   "version：".addslashes($_REQUEST['version']) : 'version：';
    $author = isset($_REQUEST['author'])  ?   "author：".addslashes($_REQUEST['author']) : 'author：';
    $author_url = isset($_REQUEST['author_url'])  ?   "author_uri：".$_REQUEST['author_url'] : 'author_uri：';
    $description = isset($_REQUEST['description'])  ?   "description：".addslashes($_REQUEST['description']) : 'description：';
    
    //商家默认模板数据
    $template_type = !empty($_REQUEST['template_type']) ? trim($_REQUEST['template_type']) : '';
    $temp_id = !empty($_REQUEST['temp_id'])  ?  intval($_REQUEST['temp_id']) : 0;
    $temp_mode = !empty($_REQUEST['temp_mode'])  ?  intval($_REQUEST['temp_mode']) : 0;
    $temp_cost = !empty($_REQUEST['temp_cost'])  ?  trim($_REQUEST['temp_cost']) : 0;
    $temp_cost = floatval($temp_cost);
    if($template_type == 'seller'){
        $des = ROOT_PATH . 'data/seller_templates/seller_tem';
    }else{
        $des = ROOT_PATH . 'data/home_Templates/'.$GLOBALS['_CFG']['template'];
    }

    if($tem == ''){
        $tem = get_new_dirName(0,$des);
        $code_dir = $des.'/' . $tem ;
        if (!is_dir($code_dir)) {
            make_dir($code_dir);
        }
    }
    $file_url = '';
    $format  = array('png', 'gif', 'jpg');
    $file_dir = $des.'/' . $tem ;
    if (!is_dir($file_dir)) {
        make_dir($file_dir);
    }
    if ((isset($_FILES['ten_file']['error']) && $_FILES['ten_file']['error'] == 0) || (!isset($_FILES['ten_file']['error']) && isset($_FILES['ten_file']['tmp_name']) && $_FILES['ten_file']['tmp_name'] != 'none'))
    {
        //检查文件格式
        if (!check_file_type($_FILES['ten_file']['tmp_name'], $_FILES['ten_file']['name'], $allow_file_types))
        {
            $result['error'] = 1;
            $result['message'] = "图片格式不正确";
            die(json_encode($result));
        }
        
        if ($_FILES['ten_file']['name']) {
            $ext_cover = explode('.', $_FILES['ten_file']['name']);
            $ext_cover = array_pop($ext_cover);
        } else {
            $ext_cover = "";
        }
        
        $file_name = $file_dir . "/screenshot". '.' . $ext_cover;//头部显示图片
        if (move_upload_file($_FILES['ten_file']['tmp_name'], $file_name)) {
            $file_url = $file_name;
        }

    }
    if ($file_url == '')
    {
        $file_url = $_POST['textfile'];
    }
    if ((isset($_FILES['big_file']['error']) && $_FILES['big_file']['error'] == 0) || (!isset($_FILES['big_file']['error']) && isset($_FILES['big_file']['tmp_name']) && $_FILES['big_file']['tmp_name'] != 'none'))
    {
        //检查文件格式
        if (!check_file_type($_FILES['big_file']['tmp_name'], $_FILES['big_file']['name'], $allow_file_types))
        {
            $result['error'] = 1;
            $result['message'] = "图片格式不正确";
            die(json_encode($result));
        }
        
        if ($_FILES['big_file']['name']) {
            $ext_big = explode('.', $_FILES['big_file']['name']);
            $ext_big = array_pop($ext_big);
        } else {
            $ext_big = "";
        }

        $file_name = $file_dir . "/template". '.' . $ext_big;//头部显示图片
        if (move_upload_file($_FILES['big_file']['tmp_name'], $file_name)) {
            $big_file = $file_name;
        }
    }
    $template_dir_img = @opendir($file_dir);
    while ($file = readdir($template_dir_img)) {
        foreach ($format AS $val) {
            if ($val != $ext_cover && $ext_cover != '') {
                /* 删除同名其他后缀名的模板封面 */
                if (file_exists($file_dir . '/screenshot.' . $val)) {
                    @unlink($file_dir . '/screenshot.' . $val);
                }
            }
            if ($val != $ext_big && $ext_big != '') {
                /* 删除同名其他后缀名的模板大图 */
                if (file_exists($file_dir . '/template.' . $val)) {
                    @unlink($file_dir . '/template.' . $val);
                }
            }
        }
    }
    @closedir($template_dir_img);
    $end = "------tpl_info------------";
    $tab = "\n";
    
    $html = $end.$tab.$name.$tab."tpl url：".$file_url.$tab.$description.$tab.$version.$tab.$author.$tab.$author_url.$tab.$end;
    
    $html = write_static_file_cache('tpl_info', iconv("UTF-8", "GB2312", $html), 'txt', $file_dir . '/');
    
    if ($html === false) {
        $result['error'] = 1;
        $result['message'] =$file_dir . "/tpl_info.txt没有写入权限，请修改权限";
    }else{
//        首页可视化列表页
         if($check == 1 && $template_type != 'seller'){
            $seller_dir = ROOT_PATH.'data/home_Templates/'.$GLOBALS['_CFG']['template'].'/' ;//模板目录
            $template_dir = @opendir($seller_dir);
            while ($file = readdir($template_dir)) {
                if ($file != '.' && $file != '..' && $file != '.svn' && $file != 'index.htm') {
                    $available_templates[] = get_seller_template_info($file, 0 ,$GLOBALS['_CFG']['template']);
                }
            }
            $available_templates = get_array_sort($available_templates, 'sort');
            @closedir($template_dir);
            $smarty->assign('available_templates', $available_templates);
            /* 获取店铺正在使用的模板名称 */
           /* 获取默认模板 */
            $sql = "SELECT value FROM" . $GLOBALS['ecs']->table('shop_config') . " WHERE code= 'hometheme' AND store_range = '".$GLOBALS['_CFG']['template']."'";
            $default_tem = $GLOBALS['db']->getOne($sql);
            $smarty->assign('default_tem', $default_tem);
            $smarty->assign('temp', 'homeTemplates');
            $result['content'] = $GLOBALS['smarty']->fetch('library/dialog.lbi');
        }
        //商家模板
        elseif($template_type == 'seller'){
            if($temp_id > 0){
                $sql = "UPDATE".$ecs->table('template_mall')."SET temp_mode = '$temp_mode',temp_cost='$temp_cost' WHERE temp_id = '$temp_id' AND temp_code = '$tem'";
            }else{
                $time = gmtime();
                $sql = "INSERT INTO".$ecs->table('template_mall')."(`temp_mode`,`temp_cost`,`temp_code`,`add_time`) VALUES('$temp_mode','$temp_cost','$tem','$time')";
            }
            $db->query($sql);
        }
        $result['error'] = 0;
    }
     die(json_encode($result));
}
/*删除模板*/
elseif($_REQUEST['act'] == 'removeTemplate')
{
      require(ROOT_PATH . '/includes/cls_json.php');
    $json = new JSON;
    $result = array('error' => '','content' => '','url'=>'');
    $code = isset($_REQUEST['code'])  ? addslashes($_REQUEST['code']) : '';
    
    $template_type = !empty($_REQUEST['template_type']) ? trim($_REQUEST['template_type']) : '';
    $temp_id = !empty($_REQUEST['temp_id'])  ?  intval($_REQUEST['temp_id']) : 0;
    $theme = $GLOBALS['_CFG']['template'];
    
    /* 获取默认模板 */
    $sql = "SELECT value FROM" . $GLOBALS['ecs']->table('shop_config') . " WHERE code= 'hometheme'AND store_range = '".$GLOBALS['_CFG']['template']."'";
    $default_tem = $GLOBALS['db']->getOne($sql);
    //使用中的模板不能删除
    if($default_tem == $code && $template_type != 'seller'){
        $result['error'] = 1;
        $result['content'] = "该模板正在使用中，不能删除！欲删除请先更改模板！";
    }else{
        if($template_type == 'seller'){
            $dir = ROOT_PATH . 'data/seller_templates/seller_tem'.'/' . $code ;//模板目录
            $theme = '';
        }else{
            $dir = ROOT_PATH.'data/home_Templates/'.$GLOBALS['_CFG']['template'].'/' . $code ;//模板目录
        }
        
        $rmdir = del_DirAndFile($dir);
        if ($rmdir == true) {
            //删除模板对应的左侧信息
            $sql = "DELETE FROM".$ecs->table('templates_left')."WHERE seller_templates = '$code' AND theme = '$theme'";
            $db->query($sql);
            $result['error'] = 0;
            
            if($template_type == 'seller'){
                $sql = "DELETE FROM".$ecs->table('template_mall')."WHERE temp_code = '$code' AND temp_id = '$temp_id'";
                $db->query($sql);
            }else{
                /* 模板列表 */
                $seller_dir = ROOT_PATH.'data/home_Templates/'.$GLOBALS['_CFG']['template'].'/' ;//模板目录
                $template_dir = @opendir($seller_dir);
                while ($file = readdir($template_dir)) {
                    if ($file != '.' && $file != '..' && $file != '.svn' && $file != 'index.htm') {
                        $available_templates[] = get_seller_template_info($file, 0 ,$GLOBALS['_CFG']['template']);
                    }
                }
                $available_templates = get_array_sort($available_templates, 'sort');
                @closedir($template_dir);
                $smarty->assign('available_templates', $available_templates);
                /* 获取店铺正在使用的模板名称 */

                $smarty->assign('default_tem', $default_tem);
                $smarty->assign('temp', 'homeTemplates');
                $result['content'] = $GLOBALS['smarty']->fetch('library/dialog.lbi');
            }
        } else {
            $result['error'] = 1;
            $result['content'] = "系统出错，请重试！";
        }
    }
    die(json_encode($result));
}
//启用模板
elseif($_REQUEST['act'] == 'setupTemplate'){
    require(ROOT_PATH . '/includes/cls_json.php');
    $json = new JSON;
    $result = array('error' => '','content' => '','url'=>'');
    $code = isset($_REQUEST['code'])  ? trim($_REQUEST['code']) : '';
    $dir = ROOT_PATH.'data/home_Templates/'.$GLOBALS['_CFG']['template'].'/'.$code ;//模板目录
    //判断模板是否存在
    if(file_exists($dir) && $code){
        $sql = "UPDATE".$ecs->table('shop_config')."SET value='$code',store_range = '".$GLOBALS['_CFG']['template']."'  WHERE code = 'hometheme'";
        $db->query($sql);
        $result['error'] = 0;
    }else{
        $result['error'] = 1;
        $result['message'] = "改模板不存在，请检查";
    }
    die(json_encode($result));
}
//导出模板
elseif($_REQUEST['act'] == 'export_tem'){
    $checkboxes = !empty($_REQUEST['checkboxes'])  ?  $_REQUEST['checkboxes'] : array();
    $template_type = !empty($_REQUEST['template_type']) ? trim($_REQUEST['template_type']) : '';
    if(!empty($checkboxes)){
         include_once('includes/cls_phpzip.php');
        $zip = new PHPZip;
        if($template_type == 'seller'){
            $dir = ROOT_PATH . 'data/seller_templates/seller_tem'.'/';//模板目录
        }else{
            $dir = ROOT_PATH.'data/home_Templates/'.$GLOBALS['_CFG']['template'].'/';
        }
            $dir_zip = $dir;
            $file_mune = array();
            foreach($checkboxes as $v){
                if($v){
                    $addfiletozip = $zip->get_filelist($dir_zip.$v);//获取所有目标文件
                    foreach($addfiletozip as $k=>$val){
                        if($v){
                            $addfiletozip[$k] = $v."/".$val;
                        }
                    }
                    $file_mune=array_merge($file_mune,$addfiletozip);
                }
            }
            /*写入压缩文件*/
            foreach($file_mune as $v){
                if(file_exists($dir."/".$v)){
                     $zip->add_file(file_get_contents($dir."/".$v),$v);
                }
            }
          
        //下面是输出下载;
        header ( "Cache-Control: max-age=0" );
        header ( "Content-Description: File Transfer" );
        header("Content-Disposition: attachment; filename=templates_list.zip"); 
        header ( "Content-Type: application/zip" ); 
        header ( "Content-Transfer-Encoding: binary" ); //二进制
        header("Content-Type: application/unknown");

        die($zip->file());
    }else{
        $link[0]['text'] = "返回列表";
        $link[0]['href'] = 'visualhome.php?act=list';
        sys_msg("请选择导出的模板", 1, $link);
    }
}
//删除头部广告
elseif($_REQUEST['act'] == 'model_delete'){
    require(ROOT_PATH . '/includes/cls_json.php');
    $json = new JSON;
    $result = array('error' => '','message' => '');
    
    $code = isset($_REQUEST['suffix'])  ? trim($_REQUEST['suffix']) : '';
    $dir = ROOT_PATH.'data/home_Templates/'.$GLOBALS['_CFG']['template'].'/'.$code ;//模板目录
    
    if(empty($code) && file_exists($dir)){
        $result['error'] = 1;
        $result['message'] = "改模板不存在，请刷新重试";
    }else{
        if(file_exists($dir."/topBanner.php")){
            unlink($dir."/topBanner.php");
        }
        $result['error'] = 0;
    }
    die(json_encode($result));
}
//发布
elseif($_REQUEST['act'] == 'downloadModal')
{
    require(ROOT_PATH . '/includes/cls_json.php');
    $json = new JSON;
    $result = array('error' => '','message' => '');
    $code = isset($_REQUEST['suffix'])  ? trim($_REQUEST['suffix']) : '';
    $adminpath = isset($_REQUEST['adminpath'])  ? trim($_REQUEST['adminpath']) : '';
    $new = isset($_REQUEST['new'])  ? intval($_REQUEST['new']) : 0;//CMS频道标识
    if($new == 0){
        if($adminpath == 'admin'){
            $dir = ROOT_PATH . 'data/seller_templates/seller_tem' . "/" . $code.'/temp';//原模板目录
            $file = ROOT_PATH . 'data/seller_templates/seller_tem' . "/" . $code;//目标模板目录
        }else{
            $dir = ROOT_PATH.'data/home_Templates/'.$GLOBALS['_CFG']['template']."/".$code.'/temp';//原模板目录
            $file = ROOT_PATH.'data/home_Templates/'.$GLOBALS['_CFG']['template'].'/'.$code ;//目标模板目录
        }
    }else{
        $dir = ROOT_PATH.'data/cms_Templates/'.$GLOBALS['_CFG']['template'].'/temp';//原模板目录
        $file = ROOT_PATH.'data/cms_Templates/'.$GLOBALS['_CFG']['template'] ;//目标模板目录
    }
    
   
    if(!empty($code) || $new == 1)
    {
        //新建目录
        if (!is_dir($dir)) {
            make_dir($dir);
        }
        
        recurse_copy($dir,$file,1);//移动缓存文件
        del_DirAndFile($dir);//删除缓存文件
        $result['error'] = 0;
    }
    
    /* 存入OSS start */
    if (!isset($GLOBALS['_CFG']['open_oss'])) {
        $sql = "SELECT value FROM " . $GLOBALS['ecs']->table('shop_config') . " WHERE code = 'open_oss'";
        $is_oss = $GLOBALS['db']->getOne($sql, true);
    }else{
        $is_oss = $GLOBALS['_CFG']['open_oss'];
    }

    if (!isset($GLOBALS['_CFG']['server_model'])) {
        $sql = 'SELECT value FROM ' . $GLOBALS['ecs']->table('shop_config') . " WHERE code = 'server_model'";
        $server_model = $GLOBALS['db']->getOne($sql, true);
    } else {
        $server_model = $GLOBALS['_CFG']['server_model'];
    }
    
    if ($is_oss && $server_model && $new == 0) {
        
        if($adminpath == 'admin'){
            $dir = ROOT_PATH . 'data/seller_templates/seller_tem' . "/" . $code.'/';
            $path = 'data/seller_templates/seller_tem' . "/" . $code.'/';//目标模板目录
            $unlink = ROOT_PATH . 'data/sc_file/sellertemplates/seller_tem'. '/' . $code . ".php";
        }else{
            $dir = ROOT_PATH . "data/home_Templates/" . $GLOBALS['_CFG']['template'] . "/" .$code. "/";
            $path = "data/home_Templates/" .$GLOBALS['_CFG']['template']. "/" .$code. "/";
            $unlink = ROOT_PATH . 'data/sc_file/hometemplates/' . $code . ".php";
        }
        
        $file_list = get_recursive_file_oss($dir, $path, true);
        
        get_oss_add_file($file_list);
        
        dsc_unlink($unlink);
        
        $id_data = read_static_cache('urlip_list', '/data/sc_file/');

        if ($pin_region_list !== false) {
            del_visual_templates($id_data, $code);
        }
    }
    /* 存入OSS end */

    die(json_encode($result));
}
//还原
elseif($_REQUEST['act'] == 'backmodal'){
     require(ROOT_PATH . '/includes/cls_json.php');
    $json = new JSON;
    $result = array('error' => '','message' => '');
    $code = isset($_REQUEST['suffix'])  ? trim($_REQUEST['suffix']) : '';
    $new = isset($_REQUEST['new'])  ? intval($_REQUEST['new']) : 0;//CMS频道标识
    if($new == 1){
        $dir = ROOT_PATH.'data/cms_Templates/'.$GLOBALS['_CFG']['template'].'/temp';//原模板目录
    }else{
        $dir = ROOT_PATH.'data/home_Templates/'.$GLOBALS['_CFG']['template']."/".$code.'/temp';//原模板目录
    }
    if(!empty($code) || $new == 1)
    {
        del_DirAndFile($dir);//删除缓存文件
        $result['error'] = 0;
    }
    die(json_encode($result));
}
//上传首页弹出广告
elseif($_REQUEST['act'] == 'bonusAdv'){
    require(ROOT_PATH . '/includes/cls_json.php');
    include_once(ROOT_PATH . '/includes/cls_image.php');
    $image = new cls_image($_CFG['bgcolor']);
    $json = new JSON;
    $result = array('error' => '','message' => '');

    $suffix = isset($_REQUEST['suffix'])  ? trim($_REQUEST['suffix']) : '';
    $adv_url = !empty($_REQUEST['adv_url'])  ?  trim($_REQUEST['adv_url']) : '';
     /* 允许上传的文件类型 */
    $allow_file_types = '|GIF|JPG|PNG|';
    //初始化数据
    $oss_img_url = '';
    $bgtype = 'bonusadv';
    $theme = $GLOBALS['_CFG']['template'];
    
    if ($_FILES['advfile']) 
    {
         if ((isset($_FILES['advfile']['error']) && $_FILES['advfile']['error'] == 0) || (!isset($_FILES['advfile']['error']) && $_FILES['advfile']['tmp_name'] != 'none')) 
        {
             if (!check_file_type($_FILES['advfile']['tmp_name'], $_FILES['advfile']['name'], $allow_file_types)) 
            {
                $result['error'] = 1;
                $result['prompt'] = "请上传正确格式图片（$allow_file_types）";
                die(json_encode($result));
            } 
            else 
            {
                $ext_name = explode('.', $_FILES['advfile']['name']);
                $ext = array_pop($ext_name);
                $file_dir = '../data/home_Templates/'.$GLOBALS['_CFG']['template']."/".$suffix."/images/bonusadv";
                if (!is_dir($file_dir)) {
                    make_dir($file_dir);
                }
                
                $file_name = $file_dir . "/bonusadv_".gmtime()."." . $ext;
                if (move_upload_file($_FILES['advfile']['tmp_name'], $file_name)) {
                    //oss上传  需要的时候打开
                    $oss_img_url = str_replace("../", "", $file_name);
                    get_oss_add_file(array($oss_img_url));
                }
            }
        }
    }
    $sql = "SELECT id ,img_file FROM".$ecs->table('templates_left')." WHERE ru_id = 0 AND seller_templates = '$suffix' AND type = '$bgtype' AND theme = '$theme' LIMIT 1";
    $templates_left = $db->getRow($sql);
    if($templates_left['id'] > 0)
    {
       $fileurl = '';
        if($oss_img_url != '')
        {
             if ($templates_left['img_file'] != '')
            {
                @unlink("../".$templates_left['img_file']);
                get_oss_del_file(array($templates_left['img_file']));
            }
            $fileurl = ",img_file = '$oss_img_url'";
        }
        $sql = "UPDATE".$ecs->table('templates_left')." SET fileurl = '$adv_url' $fileurl WHERE ru_id = 0 AND seller_templates = '$suffix' AND id='".$templates_left['id']."' AND type = '$bgtype' AND theme = '$theme'";
        $db->query($sql);
    }
    else
    {
        $sql = "INSERT INTO".$ecs->table('templates_left')." (`ru_id`,`seller_templates`,`img_file`,`type`,`theme`,`fileurl`) VALUES (0,'$suffix','$oss_img_url','$bgtype','$theme','$adv_url')";
        $db->query($sql);
    } 
    $result['file'] = "";
    if(!empty($oss_img_url)){
        $result['file'] = get_image_path(0, $oss_img_url, true);
    }
    
    die(json_encode($result));
}
//删除弹出广告
elseif($_REQUEST['act'] == 'delete_adv'){
    require(ROOT_PATH . '/includes/cls_json.php');
    $json = new JSON;
    $result = array('error' => '','message' => '');
    
    $suffix = isset($_REQUEST['suffix'])  ? trim($_REQUEST['suffix']) : '';
    
    $bgtype = 'bonusadv';
    $theme = $GLOBALS['_CFG']['template'];
    
    $sql = "SELECT id ,img_file FROM".$ecs->table('templates_left')." WHERE ru_id = 0 AND seller_templates = '$suffix' AND type = '$bgtype' AND theme = '$theme' LIMIT 1";
    $templates_left = $db->getRow($sql);
     if ($templates_left['img_file'] != '')
    {
        @unlink("../".$templates_left['img_file']);
        get_oss_del_file(array($templates_left['img_file']));
    }
    $sql = "DELETE FROM".$GLOBALS['ecs']->table("templates_left")."WHERE ru_id = 0 AND seller_templates = '$suffix' AND type = '$bgtype' AND theme = '$theme'";
    $db->query($sql);
    die(json_encode($result));
}
