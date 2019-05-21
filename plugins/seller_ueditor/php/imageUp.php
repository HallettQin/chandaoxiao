<?php

require 'config.php';
if (!$enable)
    die("{'url':'','title':'','original':'','state':'没有上传权限'}"); //权限验证
    
//上传配置
$config = array(
    "savePath" => $root_path_relative . IMAGE_DIR . '/upload/',
    "maxSize" => 3000, //单位KB
    "allowFiles" => array(".gif", ".png", ".jpg", ".jpeg", ".bmp")
);

//上传图片框中的描述表单名称，
$title = htmlspecialchars($_POST['pictitle'], ENT_QUOTES);

//获取存储目录
if (isset($_GET['fetch'])) {
    header('Content-Type: text/javascript');
    //echo 'updateSavePath('. json_encode($imgSavePathConfig) .');';
    echo 'updateSavePath(["upload"]);';
    return;
}

//生成上传实例对象并完成上传
$up = new Uploader("upfile", $config);

/**
 * 得到上传文件所对应的各个参数,数组结构
 * array(
 *     "originalName" => "",   //原始文件名
 *     "name" => "",           //新文件名
 *     "url" => "",            //返回的地址
 *     "size" => "",           //文件大小
 *     "type" => "" ,          //文件类型
 *     "state" => ""           //上传状态，上传成功时必须返回"SUCCESS"
 * )
 */
$info = $up->getFileInfo();
//处理文件路径
$info["url"] = str_replace($root_path_relative, $root_path, $info["url"]);

if ($info["url"] && substr($info["url"], 0, 1) != "/") {
    $info["url"] = "/" . $info["url"];
}

//OSS文件存储ecmoban模板堂 --zhuo start
if ($GLOBALS['_CFG']['open_oss'] == 1) {
    if ($info["url"]) {
        $dir_url = explode(IMAGE_DIR, $info["url"]);
        if (count($dir_url) == 2) {
            $desc_image = IMAGE_DIR . $dir_url[1];
            $urlip = get_ip_url($GLOBALS['ecs']->get_domain(), 1);
            $url_site = $urlip . $dir_url[0];
        } else {
            $desc_image = IMAGE_DIR . $dir_url;
            $url_site = get_ip_url($GLOBALS['ecs']->get_domain());
        }

        $bucket_info = get_bucket_info();
        $url = $url_site . "oss.php?act=upload";

        $Http = new Http();
        $post_data = array(
            'bucket' => $bucket_info['bucket'],
            'keyid' => $bucket_info['keyid'],
            'keysecret' => $bucket_info['keysecret'],
            'is_cname' => $bucket_info['is_cname'],
            'endpoint' => $bucket_info['outside_site'],
            'object' => array($desc_image)
        );

        $Http->doPost($url, $post_data);
        
        if (!empty($info["url"]) && (strpos($info["url"], 'http://') === false && strpos($info["url"], 'https://') === false)) {
            $info["url"] = $bucket_info['endpoint'] . $info["url"];
            $info["url"] = str_replace("//" . IMAGE_DIR, "/" . IMAGE_DIR, $info["url"]);
            
            $dir = explode("/", ROOT_PATH);
            $web_dir = "/" . $dir[3] . "/";

            if (isset($dir[3]) && $dir[3]) {
                if ($web_dir && $web_dir != '/') {
                    $info["url"] = str_replace($web_dir, "", $info["url"]);
                }
            }
        }
    }
}
//OSS文件存储ecmoban模板堂 --zhuo end

/**
 * 向浏览器返回数据json数据
 * {
 *   'url'      :'a.jpg',   //保存后的文件路径
 *   'title'    :'hello',   //文件描述，对图片来说在前端会添加到title属性上
 *   'original' :'b.jpg',   //原始文件名
 *   'state'    :'SUCCESS'  //上传状态，成功时返回SUCCESS,其他任何值将原样返回至图片上传框中
 * }
 */
echo "{'url':'" . $info["url"] . "','title':'" . $title . "','original':'" . $info["originalName"] . "','state':'" . $info["state"] . "'}";
