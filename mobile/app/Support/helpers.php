<?php

use App\Extensions\Util;

/**
 * 获取输入数据 支持默认值和过滤
 *
 * @param $name
 * @param string $default
 * @param null $filter
 * @param null $datas
 *
 * @return mixed
 */
function input($name, $default = '', $filter = null, $datas = null)
{
    return I($name, $default, $filter, $datas);
}

/**
 * 实例化模型类
 *
 * @param string $name
 *
 * @return bool
 */
function model($name = '')
{
    $class = '\\App\\Models\\' . $name;
    if (class_exists($class)) {
        return new $class;
    }
    return false;
}

/**
 * 实例化一个没有模型文件的Model
 *
 * @param string $name Model名称 支持指定基础模型 例如 MongoModel:User
 * @param string $tablePrefix 表前缀
 * @param mixed $connection 数据库连接信息
 *
 * @return object
 */
function dao($name = '', $tablePrefix = '', $connection = '')
{
    return M($name, $tablePrefix, $connection);
}

/**
 * Url生成
 *
 * @param string $url
 * @param string $vars
 * @param bool $suffix
 * @param bool $domain
 *
 * @return string
 */
function url($url = '', $vars = '', $suffix = true, $domain = false)
{
    $routes = C('URL_ROUTE_RULES');
    $rule = array_search($url, $routes);
    if ($rule !== false && $domain === false && C('url_model') == 2) {
        $rule = str_replace('\\/', '/', $rule);
        trims($rule, ['/^', '$/', '$']);
        $rule = explode('/', $rule);
        $string = '';
        foreach ($rule as $item) {
            if (0 === strpos($item, '[:')) {
                $item = substr($item, 1, -1);
            }
            if (0 === strpos($item, ':')) { // 动态变量获取
                if ($pos = strpos($item, '^')) {
                    $var = substr($item, 1, $pos - 1);
                } elseif (strpos($item, '\\')) {
                    $var = substr($item, 1, -2);
                } else {
                    $var = substr($item, 1);
                }
            }
            $string .= '/' . (($var === null) ? $item : $vars[$var]);
            if (isset($vars[$var])) {
                unset($vars[$var]);
            }
        }
        return U($string) . (empty($vars) ? '' : '?' . http_build_query($vars, '', '&'));
    }
    return U($url, $vars, $suffix, $domain);
}

/**
 * 返回json格式数据
 *
 * @param array $data
 *
 * @return string
 */
function json($data = [])
{
    return json_encode($data, PHP_VERSION >= '5.4.0' ? JSON_UNESCAPED_UNICODE : 0);
}

/**
 * 清除多个字符
 *
 * @param $value
 *
 * @param string $charlist
 */
function trims(&$value, $charlist = '')
{
    if (is_string($charlist)) {
        $charlist = [$charlist];
    }
    foreach ($charlist as $char) {
        $value = trim($value, $char);
    }
}

/**
 * 将指定的字符串转换成 驼峰式命名
 * Translates a string with underscores
 * into camel case (e.g. first_name -> firstName)
 *
 * @param string $str String in underscore format
 * @param bool $capitalise_first_char If true, capitalise the first char in $str
 * @return string $str translated into camel caps
 */
function camel_cases($str, $capitalise_first_char = false)
{
    if ($capitalise_first_char) {
        $str[0] = strtoupper($str[0]);
    }
    $func = create_function('$c', 'return strtoupper($c[1]);');
    return preg_replace_callback('/_([a-z])/', $func, $str);
}

/**
 * 将指定的字符串转换成 蛇形命名
 * Translates a camel case string into a string with
 * underscores (e.g. firstName -> first_name)
 *
 * @param string $str String in camel case format
 * @return string $str Translated into underscore format
 */
function snake_cases($str)
{
    $str[0] = strtolower($str[0]);
    $func = create_function('$c', 'return "_" . strtolower($c[1]);');
    return preg_replace_callback('/([A-Z])/', $func, $str);
}

/**
 * 检查是否是微信浏览器访问
 *
 * @return bool
 */
function is_wechat_browser()
{
    $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    if (strpos($user_agent, 'micromessenger') === false) {
        return false;
    } else {
        return true;
    }
}

/**
 * 根据数组生成常量定义
 *
 * @param $array
 * @param bool $check
 *
 * @return string
 */
function array_define($array, $check = true)
{
    $content = "\n";
    foreach ($array as $key => $val) {
        $key = strtoupper($key);
        if ($check) {
            $content .= 'defined(\'' . $key . '\') or ';
        }
        if (is_int($val) || is_float($val)) {
            $content .= "define('" . $key . "'," . $val . ');';
        } elseif (is_bool($val)) {
            $val = ($val) ? 'true' : 'false';
            $content .= "define('" . $key . "'," . $val . ');';
        } elseif (is_string($val)) {
            $content .= "define('" . $key . "','" . addslashes($val) . "');";
        }
        $content .= "\n";
    }
    return $content;
}

/**
 * 二维数组排序
 *
 * @param array $array 排序的数组
 * @param string $key 排序主键
 * @param string $type 排序类型 asc|desc
 * @param bool $reset 是否返回原始主键
 *
 * @return mixed
 */
function array_order($array, $key, $type = 'asc', $reset = false)
{
    if (empty($array) || !is_array($array)) {
        return $array;
    }
    foreach ($array as $k => $v) {
        $keysvalue[$k] = $v[$key];
    }
    if ($type == 'asc') {
        asort($keysvalue);
    } else {
        arsort($keysvalue);
    }
    $i = 0;
    foreach ($keysvalue as $k => $v) {
        $i++;
        if ($reset) {
            $new_array[$k] = $array[$k];
        } else {
            $new_array[$i] = $array[$k];
        }
    }
    return $new_array;
}

/**
 * 获取文件或文件大小
 *
 * @param $directoty
 *
 * @return int|mixed
 */
function dir_size($directoty)
{
    $dir_size = 0;
    if ($dir_handle = @opendir($directoty)) {
        while ($filename = readdir($dir_handle)) {
            $subFile = $directoty . DIRECTORY_SEPARATOR . $filename;
            if ($filename == '.' || $filename == '..') {
                continue;
            } elseif (is_dir($subFile)) {
                $dir_size += dir_size($subFile);
            } elseif (is_file($subFile)) {
                $dir_size += filesize($subFile);
            }
        }
        closedir($dir_handle);
    }
    return ($dir_size);
}

/**
 * 复制目录
 *
 * @param $sourceDir
 * @param $aimDir
 *
 * @return bool
 */
function copy_dir($sourceDir, $aimDir)
{
    $succeed = true;
    if (!file_exists($aimDir)) {
        if (!mkdir($aimDir, 0777)) {
            return false;
        }
    }
    $objDir = opendir($sourceDir);
    while (false !== ($fileName = readdir($objDir))) {
        if (($fileName != ".") && ($fileName != "..")) {
            if (!is_dir("$sourceDir/$fileName")) {
                if (!copy("$sourceDir/$fileName", "$aimDir/$fileName")) {
                    $succeed = false;
                    break;
                }
            } else {
                copy_dir("$sourceDir/$fileName", "$aimDir/$fileName");
            }
        }
    }
    closedir($objDir);
    return $succeed;
}

/**
 * 遍历删除目录和目录下所有文件
 *
 * @param $dir
 */
function del_dir($dir)
{
    Util::delDir($dir);
}

/**
 * html代码输入
 *
 * @param $str
 *
 * @return string
 */
function html_in($str)
{
    $str = htmlspecialchars($str);
    if (!get_magic_quotes_gpc()) {
        $str = addslashes($str);
    }
    return $str;
}

/**
 * html代码输出
 *
 * @param $str
 *
 * @return string
 */
function html_out($str)
{
    if (function_exists('htmlspecialchars_decode')) {
        $str = htmlspecialchars_decode($str);
    } else {
        $str = html_entity_decode($str);
    }
    $str = stripslashes($str);
    return $str;
}

/**
 * 生成唯一数字
 *
 * @return string
 */
function unique_number()
{
    return date('Ymd') . substr(implode(null, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
}

/**
 * 生成随机字符串
 *
 * @return string
 */
function random_str()
{
    $year_code = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
    $order_sn = $year_code[intval(date('Y')) - 2010] .
        strtoupper(dechex(date('m'))) . date('d') .
        substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('d', rand(0, 99));
    return $order_sn;
}

/**
 * 数据签名认证
 *
 * @param $data
 *
 * @return string
 */
function data_auth_sign($data)
{
    //数据类型检测
    if (!is_array($data)) {
        $data = (array)$data;
    }
    ksort($data); //排序
    $code = http_build_query($data); //url编码并生成query字符串
    $sign = sha1($code); //生成签名
    return $sign;
}

/**
 * 写入日志文件
 *
 * @param string $word
 * @param string $type
 */
function logResult($word = '', $type = 'app')
{
    $word = is_array($word) ? var_export($word, true) : $word;
    $suffix = '_' . substr(md5(__DIR__), 0, 6);
    $fp = fopen(ROOT_PATH . 'storage/logs/' . $type . $suffix . '.log', "a");
    flock($fp, LOCK_EX);
    fwrite($fp, "执行日期：" . date("Y-m-d H:i:s", time()) . "\n" . $word . "\n");
    flock($fp, LOCK_UN);
    fclose($fp);
}

/**
 * Get the path to a versioned Elixir file.
 *
 * @param  string $file
 * @param  boolean $absolute_path = true 绝对路径
 * @return string
 */
function elixir($file, $absolute_path = false)
{
    return ($absolute_path == true ? __HOST__ : '') . __TPL__ . '/' . ltrim($file, '/');
}

/**
 * 静态资源
 *
 * @param string $type 资源类型
 * @param string $module 资源所属模块
 *
 * @return string
 */
function global_assets($type = 'css', $module = 'app', $mode = 0)
{
    $assets = C('ASSETS');
    $gulps = ['dist' => 'public/'];

    if (APP_DEBUG || $mode) {
        $resources = './public/';
        $paths = [];
        foreach ($assets as $key => $item) {
            foreach ($item as $vo) {
                if (substr($vo, -3) == '.js') {
                    $paths[$key]['js'][] = '<script src="' . __PUBLIC__ . '/' . $vo . '?v=' . time() . '"></script>';
                    $gulps[$key]['js'][] = $resources . $vo;
                } elseif (substr($vo, -4) == '.css') {
                    $paths[$key]['css'][] = '<link href="' . __PUBLIC__ . '/' . $vo . '?v=' . time() . '" rel="stylesheet" type="text/css" />';
                    $gulps[$key]['css'][] = $resources . $vo;
                }
            }
        }
        file_put_contents(ROOT_PATH . 'storage/webpack.config.js', 'module.exports = ' . json_encode($gulps));
    } else {
        $paths[$module] = [
            'css' => ['<link href="' . elixir('css/' . $module . '.min.css') . '?v=' . RELEASE . '" rel="stylesheet" type="text/css" />'],
            'js' => ['<script src="' . elixir('js/' . $module . '.min.js') . '?v=' . RELEASE . '"></script>']
        ];
    }

    return isset($paths[$module][$type]) ? implode("\n", $paths[$module][$type]) . "\n" : '';
}

/**
 * 生成可视化编辑器
 *
 * @param $input_name 输入框名称
 * @param string $input_value 输入框值
 * @param int $width 编辑器宽度
 * @param int $height 编辑器高度
 *
 * @return string
 */
function create_editor($input_name, $input_value = '', $width = 600, $height = 260)
{
    static $ueditor_created = false;
    $editor = '';
    if (!$ueditor_created) {
        $ueditor_created = true;
        $editor .= '<script type="text/javascript" src="' . __PUBLIC__ . '/vendor/editor/ueditor.config.js"></script>';
        $editor .= '<script type="text/javascript" src="' . __PUBLIC__ . '/vendor/editor/ueditor.all.min.js"></script>';
    }
    $editor .= '<script id="ue_' . $input_name . '" name="' . $input_name . '" type="text/plain" style="width:' . $width . 'px;height:' . $height . 'px;">' . htmlspecialchars_decode($input_value) . '</script>';
    $editor .= '<script type="text/javascript">var ue_' . $input_name . ' = UE.getEditor("ue_' . $input_name . '");</script>';
    return $editor;
}

/**
 * 输出给定变量并结束脚本运行
 * @param $var
 * @param bool $echo
 * @param null $label
 * @param bool $strict
 */
function dd($var, $echo = true, $label = null, $strict = true)
{
    dump($var, $echo, $label, $strict);
    die();
}

/**
 * 数组转换
 * 三维数组转换成二维数组
 */
function get_three_to_two_array($list = [])
{
    $new_list = [];
    if ($list) {
        foreach ($list as $lkey => $lrow) {
            foreach ($lrow as $ckey => $crow) {
                $new_list[] = $crow;
            }
        }
    }

    return $new_list;
}

/**
 * 是否为移动设备
 * @return mixed
 */
function is_mobile_device()
{
    $detect = new \Mobile_Detect();
    return $detect->isMobile();
}

/**
 * 按设备自动跳转
 * @param string $url
 */
function uaredirect($url = '')
{
    if (!is_mobile_device()) {
        redirect($url);
    }
}


function escape($str) {
    return addcslashes($str, "\x00..\x1f\"\\");
}

//function ecs_default() {
//
//}
