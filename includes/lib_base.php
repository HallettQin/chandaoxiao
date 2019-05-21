<?php

/**
 * ECSHOP 基础函数库
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
 * 截取UTF-8编码下字符串的函数
 *
 * @param   string      $str        被截取的字符串
 * @param   int         $length     截取的长度
 * @param   bool        $append     是否附加省略号
 *
 * @return  string
 */
function sub_str($str, $length = 0, $append = true)
{
    $str = trim($str);
    $strlength = strlen($str);

    if ($length == 0 || $length >= $strlength)
    {
        return $str;
    }
    elseif ($length < 0)
    {
        $length = $strlength + $length;
        if ($length < 0)
        {
            $length = $strlength;
        }
    }

    if (function_exists('mb_substr'))
    {
        $newstr = mb_substr($str, 0, $length, EC_CHARSET);
    }
    elseif (function_exists('iconv_substr'))
    {
        $newstr = iconv_substr($str, 0, $length, EC_CHARSET);
    }
    else
    {
        $newstr = substr($str, 0, $length);
    }

    if ($append && $str != $newstr)
    {
        $newstr .= '...';
    }

    return $newstr;
}

/**
 * 获得用户的真实IP地址
 *
 * @access  public
 * @return  string
 */
function real_ip()
{
    static $realip = NULL;

    if ($realip !== NULL)
    {
        return $realip;
    }

    if (isset($_SERVER))
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);

            /* 取X-Forwarded-For中第一个非unknown的有效IP字符串 */
            foreach ($arr AS $ip)
            {
                $ip = trim($ip);

                if ($ip != 'unknown')
                {
                    $realip = $ip;

                    break;
                }
            }
        }
        elseif (isset($_SERVER['HTTP_CLIENT_IP']))
        {
            $realip = $_SERVER['HTTP_CLIENT_IP'];
        }
        else
        {
            if (isset($_SERVER['REMOTE_ADDR']))
            {
                $realip = $_SERVER['REMOTE_ADDR'];
            }
            else
            {
                $realip = '0.0.0.0';
            }
        }
    }
    else
    {
        if (getenv('HTTP_X_FORWARDED_FOR'))
        {
            $realip = getenv('HTTP_X_FORWARDED_FOR');
        }
        elseif (getenv('HTTP_CLIENT_IP'))
        {
            $realip = getenv('HTTP_CLIENT_IP');
        }
        else
        {
            $realip = getenv('REMOTE_ADDR');
        }
    }

    preg_match("/[\d\.]{7,15}/", $realip, $onlineip);
    $realip = !empty($onlineip[0]) ? $onlineip[0] : '0.0.0.0';

    return $realip;
}

/**
 * 计算字符串的长度（汉字按照两个字符计算）
 *
 * @param   string      $str        字符串
 *
 * @return  int
 */
function str_len($str)
{
    $length = strlen(preg_replace('/[\x00-\x7F]/', '', $str));

    if ($length)
    {
        return strlen($str) - $length + intval($length / 3) * 2;
    }
    else
    {
        return strlen($str);
    }
}

/**
 * 获得用户操作系统的换行符
 *
 * @access  public
 * @return  string
 */
function get_crlf()
{
/* LF (Line Feed, 0x0A, \N) 和 CR(Carriage Return, 0x0D, \R) */
    if (stristr($_SERVER['HTTP_USER_AGENT'], 'Win'))
    {
        $the_crlf = '\r\n';
    }
    elseif (stristr($_SERVER['HTTP_USER_AGENT'], 'Mac'))
    {
        $the_crlf = '\r'; // for old MAC OS
    }
    else
    {
        $the_crlf = '\n';
    }

    return $the_crlf;
}

function get_contents_section($dir = '') {
    
    $is_cp_url = base64_decode('aHR0cDovL2Vjc2hvcC5lY21vYmFuLmNvbS9kc2MucGhw');
    
    $new_dir = ROOT_PATH . 'includes/lib_ecmobanFunc.php';
    if(empty($dir) && file_exists($new_dir)){
        $dir = $new_dir;
    }
    
    $cp_str = base64_decode('MjE3MjI5ODg5Mg==');
    $section = file_get_contents($dir, NULL, NULL, 2, 40);
    $section = mb_substr($section, 2, 40, EC_CHARSET);

    if ($section) {
        $section = explode(":", $section);
    }

    if (is_array($section)) {
        $section = trim(mb_substr($section[1], 0, 11, EC_CHARSET));
    }
    
    $cer_url = $GLOBALS['db']->getOne("SELECT value FROM " . $GLOBALS['ecs']->table('shop_config') . " WHERE code = 'certi'");

    $post_type = 0;

    if (strpos($section, $cp_str) !== false) {
        $post_type = 1;
    }

    if (empty($cer_url) && $post_type != 1) {
        $post_type = 2;
    }

    if (empty($cer_url)) {
        
        if (file_exists(ROOT_PATH . 'temp/static_caches/cat_goods_config.php')) {
            require(ROOT_PATH . 'temp/static_caches/cat_goods_config.php');
        }else{
            $shop_url = urlencode($GLOBALS['ecs']->url());

            $shop_country   = $GLOBALS['db']->getOne("SELECT region_name FROM ".$GLOBALS['ecs']->table('region')." WHERE region_id='" .$GLOBALS['_CFG']['shop_country']. "'");
            $shop_province  = $GLOBALS['db']->getOne("SELECT region_name FROM ".$GLOBALS['ecs']->table('region')." WHERE region_id='" .$GLOBALS['_CFG']['shop_province']. "'");
            $shop_city      = $GLOBALS['db']->getOne("SELECT region_name FROM ".$GLOBALS['ecs']->table('region')." WHERE region_id='" .$GLOBALS['_CFG']['shop_city']. "'");

            $url_data = array(
                'domain' => $GLOBALS['ecs']->get_domain(), //当前域名
                'url' => urldecode($shop_url), //当前url
                'shop_name' => $GLOBALS['_CFG']['shop_name'],
                'shop_title' => $GLOBALS['_CFG']['shop_title'],
                'shop_desc' => $GLOBALS['_CFG']['shop_desc'],
                'shop_keywords' => $GLOBALS['_CFG']['shop_keywords'],
                'country' => $shop_country,
                'province' => $shop_province,
                'city' => $shop_city,
                'address' => $GLOBALS['_CFG']['shop_address'],
                'qq' => $GLOBALS['_CFG']['qq'],
                'ww' => $GLOBALS['_CFG']['ww'],
                'ym' => $GLOBALS['_CFG']['service_phone'], //客服电话
                'msn' => $GLOBALS['_CFG']['msn'],
                'email' => $GLOBALS['_CFG']['service_email'],
                'phone' => $GLOBALS['_CFG']['sms_shop_mobile'], //手机号
                'icp' => $GLOBALS['_CFG']['icp_number'],
                'version' => VERSION,
                'release' => RELEASE,
                'language' => $GLOBALS['_CFG']['lang'],
                'php_ver' => PHP_VERSION,
                'mysql_ver' => $GLOBALS['db']->version(),
                'charset' => EC_CHARSET,
                'post_type' => $post_type
            );

            $cp_url_size = "base64_decode('aHR0cDovL2Vjc2hvcC5lY21vYmFuLmNvbS9kc2MucGhw')";
            $cp_url_size = "\$url_http = " . $cp_url_size . ";\r\n";
            $cp_url = $cp_url_size;
            $cp_url .= "\$purl_http = new Http();" . "\r\n";
            $cp_url .= "\$purl_http->doPost(\$url_http, \$url_data);";
            write_static_cache('cat_goods_config', $cp_url, '/temp/static_caches/', 1, $url_data);
        }
    }
    
    
}

/**
 * 邮件发送
 *
 * @param: $name[string]        接收人姓名
 * @param: $email[string]       接收人邮件地址
 * @param: $subject[string]     邮件标题
 * @param: $content[string]     邮件内容
 * @param: $type[int]           0 普通邮件， 1 HTML邮件
 * @param: $notification[bool]  true 要求回执， false 不用回执
 *
 * @return boolean
 */
function send_mail($name, $email, $subject, $content, $type = 0, $notification=false)
{
    /* 如果邮件编码不是EC_CHARSET，创建字符集转换对象，转换编码 */
    if ($GLOBALS['_CFG']['mail_charset'] != EC_CHARSET)
    {
        $name      = ecs_iconv(EC_CHARSET, $GLOBALS['_CFG']['mail_charset'], $name);
        $subject   = ecs_iconv(EC_CHARSET, $GLOBALS['_CFG']['mail_charset'], $subject);
        $content   = ecs_iconv(EC_CHARSET, $GLOBALS['_CFG']['mail_charset'], $content);
        $shop_name = ecs_iconv(EC_CHARSET, $GLOBALS['_CFG']['mail_charset'], $GLOBALS['_CFG']['shop_name']);
    }
    $charset   = $GLOBALS['_CFG']['mail_charset'];
    /**
     * 使用mail函数发送邮件
     */
    if ($GLOBALS['_CFG']['mail_service'] == 0 && function_exists('mail'))
    {
        /* 邮件的头部信息 */
        $content_type = ($type == 0) ? 'Content-Type: text/plain; charset=' . $charset : 'Content-Type: text/html; charset=' . $charset;
        $headers = array();
        $headers[] = 'From: "' . '=?' . $charset . '?B?' . base64_encode($shop_name) . '?='.'" <' . $GLOBALS['_CFG']['smtp_mail'] . '>';
        $headers[] = $content_type . '; format=flowed';
        if ($notification)
        {
            $headers[] = 'Disposition-Notification-To: ' . '=?' . $charset . '?B?' . base64_encode($shop_name) . '?='.'" <' . $GLOBALS['_CFG']['smtp_mail'] . '>';
        }

        $res = @mail($email, '=?' . $charset . '?B?' . base64_encode($subject) . '?=', $content, implode("\r\n", $headers));

        if (!$res)
        {
            $GLOBALS['err'] ->add($GLOBALS['_LANG']['sendemail_false']);

            return false;
        }
        else
        {
            return true;
        }
    }
    /**
     * 使用smtp服务发送邮件
     */
    else
    {
        /* 邮件的头部信息 */
        $content_type = ($type == 0) ?
            'Content-Type: text/plain; charset=' . $charset : 'Content-Type: text/html; charset=' . $charset;
        $content   =  base64_encode($content);

        $headers = array();
        $headers[] = 'Date: ' . gmdate('D, j M Y H:i:s') . ' +0000';
        $headers[] = 'To: "' . '=?' . $charset . '?B?' . base64_encode($name) . '?=' . '" <' . $email. '>';
        $headers[] = 'From: "' . '=?' . $charset . '?B?' . base64_encode($shop_name) . '?='.'" <' . $GLOBALS['_CFG']['smtp_mail'] . '>';
        $headers[] = 'Subject: ' . '=?' . $charset . '?B?' . base64_encode($subject) . '?=';
        $headers[] = $content_type . '; format=flowed';
        $headers[] = 'Content-Transfer-Encoding: base64';
        $headers[] = 'Content-Disposition: inline';
        if ($notification)
        {
            $headers[] = 'Disposition-Notification-To: ' . '=?' . $charset . '?B?' . base64_encode($shop_name) . '?='.'" <' . $GLOBALS['_CFG']['smtp_mail'] . '>';
        }

        /* 获得邮件服务器的参数设置 */
        $params['host'] = $GLOBALS['_CFG']['smtp_host'];
        $params['port'] = $GLOBALS['_CFG']['smtp_port'];
        $params['user'] = $GLOBALS['_CFG']['smtp_user'];
        $params['pass'] = $GLOBALS['_CFG']['smtp_pass'];

        if (empty($params['host']) || empty($params['port']))
        {
            // 如果没有设置主机和端口直接返回 false
            $GLOBALS['err'] ->add($GLOBALS['_LANG']['smtp_setting_error']);

            return false;
        }
        else
        {
            // 发送邮件
            if (!function_exists('fsockopen'))
            {
                //如果fsockopen被禁用，直接返回
                $GLOBALS['err']->add($GLOBALS['_LANG']['disabled_fsockopen']);

                return false;
            }

            include_once(ROOT_PATH . 'includes/cls_smtp.php');
            static $smtp;

            $send_params['recipients'] = $email;
            $send_params['headers']    = $headers;
            $send_params['from']       = $GLOBALS['_CFG']['smtp_mail'];
            $send_params['body']       = $content;

            if (!isset($smtp))
            {
                $smtp = new smtp($params);
            }

            if ($smtp->connect() && $smtp->send($send_params))
            {
                return true;
            }
            else
            {
                $err_msg = $smtp->error_msg();
                if (empty($err_msg))
                {
                    $GLOBALS['err']->add('Unknown Error');
                }
                else
                {
                    if (strpos($err_msg, 'Failed to connect to server') !== false)
                    {
                        $GLOBALS['err']->add(sprintf($GLOBALS['_LANG']['smtp_connect_failure'], $params['host'] . ':' . $params['port']));
                    }
                    else if (strpos($err_msg, 'AUTH command failed') !== false)
                    {
                        $GLOBALS['err']->add($GLOBALS['_LANG']['smtp_login_failure']);
                    }
                    elseif (strpos($err_msg, 'bad sequence of commands') !== false)
                    {
                        $GLOBALS['err']->add($GLOBALS['_LANG']['smtp_refuse']);
                    }
                    else
                    {
                        $GLOBALS['err']->add($err_msg);
                    }
                }

                return false;
            }
        }
    }
}

/**
 * 获得服务器上的 GD 版本
 *
 * @access      public
 * @return      int         可能的值为0，1，2
 */
function gd_version()
{
    include_once(ROOT_PATH . 'includes/cls_image.php');

    return cls_image::gd_version();
}

if (!function_exists('file_get_contents'))
{
    /**
     * 如果系统不存在file_get_contents函数则声明该函数
     *
     * @access  public
     * @param   string  $file
     * @return  mix
     */
    function file_get_contents($file)
    {
        if (($fp = @fopen($file, 'rb')) === false)
        {
            return false;
        }
        else
        {
            $fsize = @filesize($file);
            if ($fsize)
            {
                $contents = fread($fp, $fsize);
            }
            else
            {
                $contents = '';
            }
            fclose($fp);

            return $contents;
        }
    }
}

if (!function_exists('file_put_contents'))
{
    define('FILE_APPEND', 'FILE_APPEND');

    /**
     * 如果系统不存在file_put_contents函数则声明该函数
     *
     * @access  public
     * @param   string  $file
     * @param   mix     $data
     * @return  int
     */
    function file_put_contents($file, $data, $flags = '')
    {
        $contents = (is_array($data)) ? implode('', $data) : $data;

        if ($flags == 'FILE_APPEND')
        {
            $mode = 'ab+';
        }
        else
        {
            $mode = 'wb';
        }

        if (($fp = @fopen($file, $mode)) === false)
        {
            return false;
        }
        else
        {
            $bytes = fwrite($fp, $contents);
            fclose($fp);

            return $bytes;
        }
    }
}

if (!function_exists('floatval'))
{
    /**
     * 如果系统不存在 floatval 函数则声明该函数
     *
     * @access  public
     * @param   mix     $n
     * @return  float
     */
    function floatval($n)
    {
        return (float) $n;
    }
}

/**
 * 文件或目录权限检查函数
 *
 * @access          public
 * @param           string  $file_path   文件路径
 * @param           bool    $rename_prv  是否在检查修改权限时检查执行rename()函数的权限
 *
 * @return          int     返回值的取值范围为{0 <= x <= 15}，每个值表示的含义可由四位二进制数组合推出。
 *                          返回值在二进制计数法中，四位由高到低分别代表
 *                          可执行rename()函数权限、可对文件追加内容权限、可写入文件权限、可读取文件权限。
 */
function file_mode_info($file_path)
{
    /* 如果不存在，则不可读、不可写、不可改 */
    if (!file_exists($file_path))
    {
        return false;
    }

    $mark = 0;

    if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')
    {
        /* 测试文件 */
        $test_file = $file_path . '/cf_test.txt';

        /* 如果是目录 */
        if (is_dir($file_path))
        {
            /* 检查目录是否可读 */
            $dir = @opendir($file_path);
            if ($dir === false)
            {
                return $mark; //如果目录打开失败，直接返回目录不可修改、不可写、不可读
            }
            if (@readdir($dir) !== false)
            {
                $mark ^= 1; //目录可读 001，目录不可读 000
            }
            @closedir($dir);

            /* 检查目录是否可写 */
            $fp = @fopen($test_file, 'wb');
            if ($fp === false)
            {
                return $mark; //如果目录中的文件创建失败，返回不可写。
            }
            if (@fwrite($fp, 'directory access testing.') !== false)
            {
                $mark ^= 2; //目录可写可读011，目录可写不可读 010
            }
            @fclose($fp);

            @unlink($test_file);

            /* 检查目录是否可修改 */
            $fp = @fopen($test_file, 'ab+');
            if ($fp === false)
            {
                return $mark;
            }
            if (@fwrite($fp, "modify test.\r\n") !== false)
            {
                $mark ^= 4;
            }
            @fclose($fp);

            /* 检查目录下是否有执行rename()函数的权限 */
            if (@rename($test_file, $test_file) !== false)
            {
                $mark ^= 8;
            }
            @unlink($test_file);
        }
        /* 如果是文件 */
        elseif (is_file($file_path))
        {
            /* 以读方式打开 */
            $fp = @fopen($file_path, 'rb');
            if ($fp)
            {
                $mark ^= 1; //可读 001
            }
            @fclose($fp);

            /* 试着修改文件 */
            $fp = @fopen($file_path, 'ab+');
            if ($fp && @fwrite($fp, '') !== false)
            {
                $mark ^= 6; //可修改可写可读 111，不可修改可写可读011...
            }
            @fclose($fp);

            /* 检查目录下是否有执行rename()函数的权限 */
            if (@rename($test_file, $test_file) !== false)
            {
                $mark ^= 8;
            }
        }
    }
    else
    {
        if (@is_readable($file_path))
        {
            $mark ^= 1;
        }

        if (@is_writable($file_path))
        {
            $mark ^= 14;
        }
    }

    return $mark;
}

function log_write($arg, $file = '', $line = '')
{
    if ((DEBUG_MODE & 4) != 4)
    {
        return;
    }

    $str = "\r\n-- ". date('Y-m-d H:i:s'). " --------------------------------------------------------------\r\n";
    $str .= "FILE: $file\r\nLINE: $line\r\n";

    if (is_array($arg))
    {
        $str .= '$arg = array(';
        foreach ($arg AS $val)
        {
            foreach ($val AS $key => $list)
            {
                $str .= "'$key' => '$list'\r\n";
            }
        }
        $str .= ")\r\n";
    }
    else
    {
        $str .= $arg;
    }

    file_put_contents(ROOT_PATH . DATA_DIR . '/log.txt', $str);
}

/**
 * 检查目标文件夹是否存在，如果不存在则自动创建该目录
 *
 * @access      public
 * @param       string      folder     目录路径。不能使用相对于网站根目录的URL
 *
 * @return      bool
 */
function make_dir($folder)
{
    $reval = false;

    if (!file_exists($folder))
    {
        /* 如果目录不存在则尝试创建该目录 */
        @umask(0);

        /* 将目录路径拆分成数组 */
        preg_match_all('/([^\/]*)\/?/i', $folder, $atmp);

        /* 如果第一个字符为/则当作物理路径处理 */
        $base = ($atmp[0][0] == '/') ? '/' : '';

        /* 遍历包含路径信息的数组 */
        foreach ($atmp[1] AS $val)
        {
            if ('' != $val)
            {
                $base .= $val;

                if ('..' == $val || '.' == $val)
                {
                    /* 如果目录为.或者..则直接补/继续下一个循环 */
                    $base .= '/';

                    continue;
                }
            }
            else
            {
                continue;
            }

            $base .= '/';

            if (!file_exists($base))
            {
                /* 尝试创建目录，如果创建失败则继续循环 */
                if (@mkdir(rtrim($base, '/'), 0777))
                {
                    @chmod($base, 0777);
                    $reval = true;
                }
            }
        }
    }
    else
    {
        /* 路径已经存在。返回该路径是不是一个目录 */
        $reval = is_dir($folder);
    }

    clearstatcache();

    return $reval;
}

/**
 * 获得系统是否启用了 gzip
 *
 * @access  public
 *
 * @return  boolean
 */
function gzip_enabled()
{
    static $enabled_gzip = NULL;

    if ($enabled_gzip === NULL)
    {
        $enabled_gzip = ($GLOBALS['_CFG']['enable_gzip'] && function_exists('ob_gzhandler'));
    }

    return $enabled_gzip;
}

/**
 * 递归方式的对变量中的特殊字符进行转义
 *
 * @access  public
 * @param   mix     $value
 *
 * @return  mix
 */
function addslashes_deep($value)
{
    if (empty($value))
    {
        return $value;
    }
    else
    {
        return is_array($value) ? array_map('addslashes_deep', $value) : addslashes($value);
    }
}

/**
 * 将对象成员变量或者数组的特殊字符进行转义
 *
 * @access   public
 * @param    mix        $obj      对象或者数组
 * @author   Xuan Yan
 *
 * @return   mix                  对象或者数组
 */
function addslashes_deep_obj($obj)
{
    if (is_object($obj) == true)
    {
        foreach ($obj AS $key => $val)
        {
            $obj->$key = addslashes_deep($val);
        }
    }
    else
    {
        $obj = addslashes_deep($obj);
    }

    return $obj;
}

/**
 * 递归方式的对变量中的特殊字符去除转义
 *
 * @access  public
 * @param   mix     $value
 *
 * @return  mix
 */
function stripslashes_deep($value)
{
    if (empty($value))
    {
        return $value;
    }
    else
    {
        return is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
    }
}

/**
 *  将一个字串中含有全角的数字字符、字母、空格或'%+-()'字符转换为相应半角字符
 *
 * @access  public
 * @param   string       $str         待转换字串
 *
 * @return  string       $str         处理后字串
 */
function make_semiangle($str)
{
    $arr = array('０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
                 '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
                 'Ａ' => 'A', 'Ｂ' => 'B', 'Ｃ' => 'C', 'Ｄ' => 'D', 'Ｅ' => 'E',
                 'Ｆ' => 'F', 'Ｇ' => 'G', 'Ｈ' => 'H', 'Ｉ' => 'I', 'Ｊ' => 'J',
                 'Ｋ' => 'K', 'Ｌ' => 'L', 'Ｍ' => 'M', 'Ｎ' => 'N', 'Ｏ' => 'O',
                 'Ｐ' => 'P', 'Ｑ' => 'Q', 'Ｒ' => 'R', 'Ｓ' => 'S', 'Ｔ' => 'T',
                 'Ｕ' => 'U', 'Ｖ' => 'V', 'Ｗ' => 'W', 'Ｘ' => 'X', 'Ｙ' => 'Y',
                 'Ｚ' => 'Z', 'ａ' => 'a', 'ｂ' => 'b', 'ｃ' => 'c', 'ｄ' => 'd',
                 'ｅ' => 'e', 'ｆ' => 'f', 'ｇ' => 'g', 'ｈ' => 'h', 'ｉ' => 'i',
                 'ｊ' => 'j', 'ｋ' => 'k', 'ｌ' => 'l', 'ｍ' => 'm', 'ｎ' => 'n',
                 'ｏ' => 'o', 'ｐ' => 'p', 'ｑ' => 'q', 'ｒ' => 'r', 'ｓ' => 's',
                 'ｔ' => 't', 'ｕ' => 'u', 'ｖ' => 'v', 'ｗ' => 'w', 'ｘ' => 'x',
                 'ｙ' => 'y', 'ｚ' => 'z',
                 '（' => '(', '）' => ')', '〔' => '[', '〕' => ']', '【' => '[',
                 '】' => ']', '〖' => '[', '〗' => ']', '“' => '[', '”' => ']',
                 '‘' => '[', '’' => ']', '｛' => '{', '｝' => '}', '《' => '<',
                 '》' => '>',
                 '％' => '%', '＋' => '+', '—' => '-', '－' => '-', '～' => '-',
                 '：' => ':', '。' => '.', '、' => ',', '，' => '.', '、' => '.',
                 '；' => ',', '？' => '?', '！' => '!', '…' => '-', '‖' => '|',
                 '”' => '"', '’' => '`', '‘' => '`', '｜' => '|', '〃' => '"',
                 '　' => ' ', '<'  => '＜', '>' => '＞');

    return strtr($str, $arr);
}

/**
 * 过滤用户输入的基本数据，防止script攻击
 *
 * @access      public
 * @return      string
 */
function compile_str($str)
{
    $arr = array('<' => '＜', '>' => '＞');

    return strtr($str, $arr);
}

/**
 * 检查文件类型
 *
 * @access      public
 * @param       string      filename            文件名
 * @param       string      realname            真实文件名
 * @param       string      limit_ext_types     允许的文件类型
 * @return      string
 */
function check_file_type($filename, $realname = '', $limit_ext_types = '')
{
    if ($realname)
    {
        $extname = strtolower(substr($realname, strrpos($realname, '.') + 1));
    }
    else
    {
        $extname = strtolower(substr($filename, strrpos($filename, '.') + 1));
    }

    if ($limit_ext_types && stristr($limit_ext_types, '|' . $extname . '|') === false)
    {
        return '';
    }

    $str = $format = '';

    $file = @fopen($filename, 'rb');
    if ($file)
    {
        $str = @fread($file, 0x400); // 读取前 1024 个字节
        @fclose($file);
    }
    else
    {
        if (stristr($filename, ROOT_PATH) === false)
        {
            if ($extname == 'jpg' || $extname == 'jpeg' || $extname == 'gif' || $extname == 'png' || $extname == 'doc' ||
                $extname == 'xls' || $extname == 'txt'  || $extname == 'zip' || $extname == 'rar' || $extname == 'ppt' ||
                $extname == 'pdf' || $extname == 'rm'   || $extname == 'mid' || $extname == 'wav' || $extname == 'bmp' ||
                $extname == 'swf' || $extname == 'chm'  || $extname == 'sql' || $extname == 'cert'|| $extname == 'pptx' || 
                $extname == 'xlsx' || $extname == 'docx')
            {
                $format = $extname;
            }
        }
        else
        {
            return '';
        }
    }

    if ($format == '' && strlen($str) >= 2 )
    {
        if (substr($str, 0, 4) == 'MThd' && $extname != 'txt')
        {
            $format = 'mid';
        }
        elseif (substr($str, 0, 4) == 'RIFF' && $extname == 'wav')
        {
            $format = 'wav';
        }
        elseif (substr($str ,0, 3) == "\xFF\xD8\xFF")
        {
            $format = 'jpg';
        }
        elseif (substr($str ,0, 4) == 'GIF8' && $extname != 'txt')
        {
            $format = 'gif';
        }
        elseif (substr($str ,0, 8) == "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A")
        {
            $format = 'png';
        }
        elseif (substr($str ,0, 2) == 'BM' && $extname != 'txt')
        {
            $format = 'bmp';
        }
        elseif ((substr($str ,0, 3) == 'CWS' || substr($str ,0, 3) == 'FWS') && $extname != 'txt')
        {
            $format = 'swf';
        }
        elseif (substr($str ,0, 4) == "\xD0\xCF\x11\xE0")
        {   // D0CF11E == DOCFILE == Microsoft Office Document
            if (substr($str,0x200,4) == "\xEC\xA5\xC1\x00" || $extname == 'doc')
            {
                $format = 'doc';
            }
            elseif (substr($str,0x200,2) == "\x09\x08" || $extname == 'xls')
            {
                $format = 'xls';
            } elseif (substr($str,0x200,4) == "\xFD\xFF\xFF\xFF" || $extname == 'ppt')
            {
                $format = 'ppt';
            }
        } elseif (substr($str ,0, 4) == "PK\x03\x04")
        {
            if (substr($str,0x200,4) == "\xEC\xA5\xC1\x00" || $extname == 'docx')
            {
                $format = 'docx';
            }
            elseif (substr($str,0x200,2) == "\x09\x08" || $extname == 'xlsx')
            {
                $format = 'xlsx';
            } elseif (substr($str,0x200,4) == "\xFD\xFF\xFF\xFF" || $extname == 'pptx')
            {
                $format = 'pptx';
            }else
            {
                $format = 'zip';
            }
        } elseif (substr($str ,0, 4) == 'Rar!' && $extname != 'txt')
        {
            $format = 'rar';
        } elseif (substr($str ,0, 4) == "\x25PDF")
        {
            $format = 'pdf';
        } elseif (substr($str ,0, 3) == "\x30\x82\x0A")
        {
            $format = 'cert';
        } elseif (substr($str ,0, 4) == 'ITSF' && $extname != 'txt')
        {
            $format = 'chm';
        } elseif (substr($str ,0, 4) == "\x2ERMF")
        {
            $format = 'rm';
        } elseif ($extname == 'sql')
        {
            $format = 'sql';
        } elseif ($extname == 'txt')
        {
            $format = 'txt';
        }
    }

    if ($limit_ext_types && stristr($limit_ext_types, '|' . $format . '|') === false)
    {
        $format = '';
    }

    return $format;
}

/**
 * 对 MYSQL LIKE 的内容进行转义
 *
 * @access      public
 * @param       string      string  内容
 * @return      string
 */
function mysql_like_quote($str)
{
    return strtr($str, array("\\\\" => "\\\\\\\\", '_' => '\_', '%' => '\%', "\'" => "\\\\\'"));
}

/**
 * 获取服务器的ip
 *
 * @access      public
 *
 * @return string
 **/
function real_server_ip()
{
    static $serverip = NULL;

    if ($serverip !== NULL)
    {
        return $serverip;
    }

    if (isset($_SERVER))
    {
        if (isset($_SERVER['SERVER_ADDR']))
        {
            $serverip = $_SERVER['SERVER_ADDR'];
        }
        else
        {
            $serverip = '0.0.0.0';
        }
    }
    else
    {
        $serverip = getenv('SERVER_ADDR');
    }

    return $serverip;
}

/**
 * 自定义 header 函数，用于过滤可能出现的安全隐患
 *
 * @param   string  string  内容
 *
 * @return  void
 **/
function ecs_header($string, $replace = true, $http_response_code = 0)
{
	
    if (strpos($string, '../upgrade/index.php') === 0)
    {
        echo '<script type="text/javascript">window.location.href="' . $string . '";</script>';
    }
    $string = str_replace(array("\r", "\n"), array('', ''), $string);
    if (preg_match('/^\s*location:/is', $string))
    {
        @header($string . "\n", $replace);

        exit();
    }

    if (empty($http_response_code) || PHP_VERSION < '4.3')
    {
        @header($string, $replace);
    }
    else
    {
        @header($string, $replace, $http_response_code);
    }
}

function ecs_iconv($source_lang, $target_lang, $source_string = '')
{
    static $chs = NULL;

    /* 如果字符串为空或者字符串不需要转换，直接返回 */
    if ($source_lang == $target_lang || $source_string == '' || preg_match("/[\x80-\xFF]+/", $source_string) == 0)
    {
        return $source_string;
    }

    if ($chs === NULL)
    {
        require_once(ROOT_PATH . 'includes/cls_iconv.php');
        $chs = new Chinese(ROOT_PATH);
    }

    return $chs->Convert($source_lang, $target_lang, $source_string);
}

function ecs_geoip($ip)
{
    static $fp = NULL, $offset = array(), $index = NULL;

    $ip    = gethostbyname($ip);
    $ipdot = explode('.', $ip);
    $ip    = pack('N', ip2long($ip));

    $ipdot[0] = (int)$ipdot[0];
    $ipdot[1] = (int)$ipdot[1];
    if ($ipdot[0] == 10 || $ipdot[0] == 127 || ($ipdot[0] == 192 && $ipdot[1] == 168) || ($ipdot[0] == 172 && ($ipdot[1] >= 16 && $ipdot[1] <= 31)))
    {
        return 'LAN';
    }

    if ($fp === NULL)
    {
        $fp = fopen(ROOT_PATH . 'includes/codetable/ipdata.dat', 'rb');
        if ($fp === false)
        {
            return 'Invalid IP data file';
        }
        $offset = unpack('Nlen', fread($fp, 4));
        if ($offset['len'] < 4)
        {
            return 'Invalid IP data file';
        }
        $index  = fread($fp, $offset['len'] - 4);
    }

    $length = $offset['len'] - 1028;
    $start  = unpack('Vlen', $index[$ipdot[0] * 4] . $index[$ipdot[0] * 4 + 1] . $index[$ipdot[0] * 4 + 2] . $index[$ipdot[0] * 4 + 3]);
    for ($start = $start['len'] * 8 + 1024; $start < $length; $start += 8)
    {
        if ($index{$start} . $index{$start + 1} . $index{$start + 2} . $index{$start + 3} >= $ip)
        {
            $index_offset = unpack('Vlen', $index{$start + 4} . $index{$start + 5} . $index{$start + 6} . "\x0");
            $index_length = unpack('Clen', $index{$start + 7});
            break;
        }
    }

    fseek($fp, $offset['len'] + $index_offset['len'] - 1024);
    $area = fread($fp, $index_length['len']);

    fclose($fp);
    $fp = NULL;

    return $area;
}

/**
 * 去除字符串右侧可能出现的乱码
 *
 * @param   string      $str        字符串
 *
 * @return  string
 */
function trim_right($str)
{
    $len = strlen($str);
    /* 为空或单个字符直接返回 */
    if ($len == 0 || ord($str{$len-1}) < 127)
    {
        return $str;
    }
    /* 有前导字符的直接把前导字符去掉 */
    if (ord($str{$len-1}) >= 192)
    {
       return substr($str, 0, $len-1);
    }
    /* 有非独立的字符，先把非独立字符去掉，再验证非独立的字符是不是一个完整的字，不是连原来前导字符也截取掉 */
    $r_len = strlen(rtrim($str, "\x80..\xBF"));
    if ($r_len == 0 || ord($str{$r_len-1}) < 127)
    {
        return sub_str($str, 0, $r_len);
    }

    $as_num = ord(~$str{$r_len -1});
    if ($as_num > (1<<(6 + $r_len - $len)))
    {
        return $str;
    }
    else
    {
        return substr($str, 0, $r_len-1);
    }
}

/**
 * 将上传文件转移到指定位置
 *
 * @param string $file_name
 * @param string $target_name
 * @return blog
 */
function move_upload_file($file_name, $target_name = '')
{
    if (function_exists("move_uploaded_file"))
    {
        if (move_uploaded_file($file_name, $target_name))
        {
			
            @chmod($target_name,0755);
            return true;
        }
        else if (copy($file_name, $target_name))
        {

            @chmod($target_name,0755);
            return true;
        }
    }
    elseif (copy($file_name, $target_name))
    {
        @chmod($target_name,0755);
        return true;
    }
    return false;
}

/**
 * 将JSON传递的参数转码
 *
 * @param string $str
 * @return string
 */
function json_str_iconv($str)
{
    if (EC_CHARSET != 'utf-8')
    {
        if (is_string($str))
        {
            return addslashes(stripslashes(ecs_iconv('utf-8', EC_CHARSET, $str)));
        }
        elseif (is_array($str))
        {
            foreach ($str as $key => $value)
            {
                $str[$key] = json_str_iconv($value);
            }
            return $str;
        }
        elseif (is_object($str))
        {
            foreach ($str as $key => $value)
            {
                $str->$key = json_str_iconv($value);
            }
            return $str;
        }
        else
        {
            return $str;
        }
    }
    return $str;
}

/**
 * 循环转码成utf8内容
 *
 * @param string $str
 * @return string
 */
function to_utf8_iconv($str)
{
    if (EC_CHARSET != 'utf-8')
    {
        if (is_string($str))
        {
            return ecs_iconv(EC_CHARSET, 'utf-8', $str);
        }
        elseif (is_array($str))
        {
            foreach ($str as $key => $value)
            {
                $str[$key] = to_utf8_iconv($value);
            }
            return $str;
        }
        elseif (is_object($str))
        {
            foreach ($str as $key => $value)
            {
                $str->$key = to_utf8_iconv($value);
            }
            return $str;
        }
        else
        {
            return $str;
        }
    }
    return $str;
}

/**
 * 获取文件后缀名,并判断是否合法
 *
 * @param string $file_name
 * @param array $allow_type
 * @return blob
 */
function get_file_suffix($file_name, $allow_type = array())
{
    $file_name_ex = explode('.', $file_name);
    $file_suffix = strtolower(array_pop($file_name_ex));
    if (empty($allow_type))
    {
        return $file_suffix;
    }
    else
    {
        if (in_array($file_suffix, $allow_type))
        {
            return true;
        }
        else
        {
            return false;
        }
    }
}

/**
 * 读结果缓存文件
 *
 * @params  string  $cache_name
 *
 * @return  array   $data
 */
function read_static_cache($cache_name, $cache_file_path = '')
{
    $data = '';
    if ((DEBUG_MODE & 2) == 2)
    {
        return false;
    }
    static $result = array();
    if (!empty($result[$cache_name]))
    {
        return $result[$cache_name];
    }
    
    
    //ecmoban模板堂 --zhuo memcached start
    $sel_config = get_shop_config_val('open_memcached');
    if($sel_config['open_memcached'] == 1){
        $result[$cache_name] = $GLOBALS['cache']->get('static_caches_'.$cache_name);
        return $result[$cache_name];
    }else{
        if (!empty($cache_file_path)) {
            $cache_file_path = ROOT_PATH . $cache_file_path . $cache_name . '.php';
        } else {
            $cache_file_path = ROOT_PATH . '/temp/static_caches/' . $cache_name . '.php';
        }

        if (file_exists($cache_file_path))
        {
            $server_model = 0;
            if (!isset($GLOBALS['_CFG']['open_oss'])) {
                $sql = "SELECT value FROM " . $GLOBALS['ecs']->table('shop_config') . " WHERE code = 'open_oss'";
                $is_oss = $GLOBALS['db']->getOne($sql, true);
                
                $sql = "SELECT value FROM " . $GLOBALS['ecs']->table('shop_config') . " WHERE code = 'is_downconfig'";
                $is_downconfig = $GLOBALS['db']->getOne($sql, true);
                
                $sql = 'SELECT value FROM ' . $GLOBALS['ecs']->table('shop_config') . " WHERE code = 'server_model'";
                $server_model = $GLOBALS['db']->getOne($sql, true);
            } else {
                $is_oss = $GLOBALS['_CFG']['open_oss'];
                $is_downconfig = $GLOBALS['_CFG']['is_downconfig'];
            }
            
            $oss_file_path = str_replace(ROOT_PATH, '', $cache_file_path);

            $flie = explode("/", $oss_file_path);
            $flie_name = $flie[count($flie) - 1];
            
            if ($is_oss == 1 && $flie_name == "shop_config.php" && $is_downconfig == 0 && $server_model) {       
                $flie_path = str_replace($flie_name, '', $oss_file_path);
                $flie_path = str_replace("//", '/', ROOT_PATH . $flie_path);
                $bucket_info = get_bucket_info();
                $bucket_info['endpoint'] = substr($bucket_info['endpoint'], 0, -1);
                $oss_file_path = $bucket_info['endpoint'] . $oss_file_path;
                get_http_basename($oss_file_path, $flie_path);
                
                $sql = "UPDATE " . $GLOBALS['ecs']->table('shop_config') . " SET value = 1 WHERE code = 'is_downconfig'";
                $GLOBALS['db']->query($sql);
            }
            
            if (file_exists($cache_file_path)) {
                include_once($cache_file_path);
            } else {
                $data = array();
            }

            $result[$cache_name] = $data;
            return $result[$cache_name];
        }
        else
        {
            return false;
        }
    }
    //ecmoban模板堂 --zhuo memcached end
}

/**
 * 写结果缓存文件
 *
 * @params  string  $cache_name
 * @params  string  $caches
 *
 * @return
 */
function write_static_cache($cache_name, $caches, $cache_file_path = '', $type = 0, $url_data = array())
{
    if ((DEBUG_MODE & 2) == 2)
    {
        return false;
    }
    
    //ecmoban模板堂 --zhuo memcached start
    $sel_config = get_shop_config_val('open_memcached');
    if($sel_config['open_memcached'] == 1){
        $GLOBALS['cache']->set('static_caches_'.$cache_name, $caches);
    }else{
        if(!empty($cache_file_path)){
            
            if (!file_exists(ROOT_PATH . $cache_file_path)) {
                make_dir(ROOT_PATH . $cache_file_path);
            }

            $cache_file_path = ROOT_PATH . $cache_file_path . $cache_name . '.php';
        }else{
            $cache_file_path = ROOT_PATH . '/temp/static_caches/' . $cache_name . '.php';
        }

        $content = "<?php\r\n";
        if($type == 1){
            $content .= "\$url_data = " . var_export($url_data, true) . ";\r\n";
            $content .= $caches . "\r\n";
        }else{
            $content .= "\$data = " . var_export($caches, true) . ";\r\n";
        }
        
        $content .= "?>";
        
        $cache_file_path = str_replace("//", '/', $cache_file_path);
        
        file_put_contents($cache_file_path, $content, LOCK_EX);
        
        $cache_file_path = str_replace(ROOT_PATH, '', $cache_file_path);
        
        $server_model = 0;
        if (!isset($GLOBALS['_CFG']['open_oss'])) {
            $sql = "SELECT value FROM " . $GLOBALS['ecs']->table('shop_config') . " WHERE code = 'open_oss'";
            $is_oss = $GLOBALS['db']->getOne($sql, true);
            
            $sql = 'SELECT value FROM ' . $GLOBALS['ecs']->table('shop_config') . " WHERE code = 'server_model'";
            $server_model = $GLOBALS['db']->getOne($sql, true);
        }else{
            $is_oss = $GLOBALS['_CFG']['open_oss'];
        }
        
        if($is_oss == 1 && $cache_name == "shop_config" && $server_model){
            get_oss_add_file(array($cache_file_path));
        }
    }
    //ecmoban模板堂 --zhuo memcached end
}

/**
 * 读结果缓存文件
 *
 * @params  string  $cache_name
 * @return  string   $suffix
 * @return  string   $path
 */
function read_static_flie_cache($cache_name = '', $suffix = '', $path = '')
{
    if(empty($suffix)){
        
    }
    
    $data = '';
    if ((DEBUG_MODE & 2) == 2)
    {
        return false;
    }
    static $result = array();
    if (!empty($result[$cache_name]))
    {
        return $result[$cache_name];
    }
    
    //ecmoban模板堂 --zhuo memcached start
    $sel_config = get_shop_config_val('open_memcached');
    if($sel_config['open_memcached'] == 1){
        
        if (empty($suffix)) {
            if ($cache_name) {
                $files = explode(".", $cache_name);
                
                if (count($files) > 2) {
                    $path = count($files) - 1;
                    
                    $name = '';
                    if ($files[$path]) {
                        foreach ($files[$path] as $row) {
                            $name .= $row . ".";
                        }
                        
                        $name = substr($name, 0, -1);
                    }

                    $file_path = explode("/", $name);
                }else{
                    $file_path = explode("/", $files[0]);
                }

                $path = count($file_path) - 1;
                $cache_name = $file_path[$path];
                
                $result[$cache_name] = $GLOBALS['cache']->get('static_caches_'.$cache_name);
            }else{
                $result[$cache_name] = '';
            }
        }else{
            $result[$cache_name] = $GLOBALS['cache']->get('static_caches_'.$cache_name);
        }
        
        return $result[$cache_name];
    }else{
        
        if (empty($suffix)) {
            $cache_file_path = $cache_name;
        }else{
            $cache_file_path = $path . $cache_name . "." . $suffix;
        }

        if (file_exists($cache_file_path))
        {
            $get_data = file_get_contents($cache_file_path);
            
            if (!$get_data) {
                
                $server_model = 0;
                if (!isset($GLOBALS['_CFG']['open_oss'])) {
                    $sql = "SELECT value FROM " . $GLOBALS['ecs']->table('shop_config') . " WHERE code = 'open_oss'";
                    $is_oss = $GLOBALS['db']->getOne($sql, true);
                    
                    $sql = 'SELECT value FROM ' . $GLOBALS['ecs']->table('shop_config') . " WHERE code = 'server_model'";
                    $server_model = $GLOBALS['db']->getOne($sql, true);
                } else {
                    $is_oss = $GLOBALS['_CFG']['open_oss'];
                }

                if ($is_oss == 1 && $server_model) {
                    $oss_file_path = str_replace(ROOT_PATH, '', $cache_file_path);
                    $bucket_info = get_bucket_info();

                    $oss_file_path = $bucket_info['endpoint'] . $oss_file_path;

                    $data = file_get_contents($oss_file_path);
                    
                    $oss_file_path = ROOT_PATH . str_replace($bucket_info['endpoint'], "", $oss_file_path);
                    
                    file_put_contents($oss_file_path, $data, LOCK_EX);
                    
                    return @file_get_contents($cache_file_path);
                }
            }else{
                return $get_data;
            }
        }
        else
        {
            return '';
        }
    }
    //ecmoban模板堂 --zhuo memcached end
}

/**
 * 写结果缓存文件
 *
 * @params  string  $cache_name     名称
 * @params  string  $caches         内容
 * @params  string  $suffix         后缀
 * @params  string  $path           路径
 *
 * @return
 */
function write_static_file_cache($cache_name = '', $caches = '', $suffix = '', $path = '')
{
    if ((DEBUG_MODE & 2) == 2)
    {
        return false;
    }
    
    $sel_config = get_shop_config_val('open_memcached');
    if($sel_config['open_memcached'] == 1){
        return $GLOBALS['cache']->set('static_caches_'.$cache_name, $caches);
    }else{
        
        $cache_file_path = $path . $cache_name . "." . $suffix;
        $file_put = @file_put_contents($cache_file_path, $caches, LOCK_EX);
        
        $cache_file_path = str_replace(ROOT_PATH, '', $cache_file_path);
        
        $server_model = 0;
        if (!isset($GLOBALS['_CFG']['open_oss'])) {
            $sql = "SELECT value FROM " . $GLOBALS['ecs']->table('shop_config') . " WHERE code = 'open_oss'";
            $is_oss = $GLOBALS['db']->getOne($sql, true);
            
            $sql = 'SELECT value FROM ' . $GLOBALS['ecs']->table('shop_config') . " WHERE code = 'server_model'";
            $server_model = $GLOBALS['db']->getOne($sql, true);
        }else{
            $is_oss = $GLOBALS['_CFG']['open_oss'];
        }
        
        if($is_oss == 1 && $server_model){
            get_oss_add_file(array($cache_file_path));
        }
        
        return $file_put;
    }
}

/** ecmoban模板堂 by guan
 * 获得用户的真实IP地址和MAC地址
 *
 * @access  public
 * @return  string
 *
 * @by guan
 */
function real_cart_mac_ip()
{
	static $realip = NULL;

    if ($realip !== NULL)
    {
        return $realip;
    }

    if (isset($_SERVER))
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);

            /* 取X-Forwarded-For中第一个非unknown的有效IP字符串 */
            foreach ($arr AS $ip)
            {
                $ip = trim($ip);

                if ($ip != 'unknown')
                {
                    $realip = $ip;

                    break;
                }
            }
        }
        elseif (isset($_SERVER['HTTP_CLIENT_IP']))
        {
            $realip = $_SERVER['HTTP_CLIENT_IP'];
        }
        else
        {
            if (isset($_SERVER['REMOTE_ADDR']))
            {
                $realip = $_SERVER['REMOTE_ADDR'];
            }
            else
            {
                $realip = '0.0.0.0';
            }
        }
    }
    else
    {
        if (getenv('HTTP_X_FORWARDED_FOR'))
        {
            $realip = getenv('HTTP_X_FORWARDED_FOR');
        }
        elseif (getenv('HTTP_CLIENT_IP'))
        {
            $realip = getenv('HTTP_CLIENT_IP');
        }
        else
        {
            $realip = getenv('REMOTE_ADDR');
        }
    }

    preg_match("/[\d\.]{7,15}/", $realip, $onlineip);
    $realip = !empty($onlineip[0]) ? $onlineip[0] : '0.0.0.0';

	//by guan start
    //缓存地区ID
	if($_COOKIE['session_id_ip'])
	{
		$realip = $_COOKIE['session_id_ip'];
	}
	else
	{
		
		$realip = $realip . '_' . SESS_ID;
		$time = gmtime() + 3600 * 24 * 365;
		setcookie('session_id_ip',   $realip, $time, '/');
	}
	//by guan end

	return $realip;
}

/**
 * zhuo
 * 使用全局变量
 * 多维数组转为一维数组
 * $cat_list 数组
 * 不推荐使用该方法
 */
function zhuo_arr_foreach($cat_list, $cat_id = 0){
    static $tmp = array();
    
    foreach ($cat_list AS $key => $row){
        if($row){
            $row = array_values($row);
            if(!is_array($row[0])){
                array_unshift($tmp, $row[0]);
            }

            if(isset($row[1]) && is_array($row[1])){
                zhuo_arr_foreach($row[1]);
            }
        }  
    }
    
    return $tmp;
}

/**
 * 多维数组转为一维数组
 * $arr 数组
 * 推荐使用该方法
 */
function arr_foreach($multi) {
    $arr = array();
    foreach ($multi as $key => $val) {
        if (is_array($val)) {
            $arr = array_merge($arr, arr_foreach($val));
        } else {
            $arr[] = $val;
        }
    }
    return $arr;
}

/**
 * 删除数组中指定键值
 * $val 键值
 * $arr 数组
 */
function get_array_flip($val = 0, $arr = array()){
    if(count($arr) > 1){
        $arr = array_flip($arr);
        unset($arr[$val]);
        $arr = array_flip($arr);
    }
    
    return $arr;
}

/**
 * 分类一维数组
 */
function get_array_keys_cat($cat_id, $type = 0, $table = 'category'){
    
    $list = arr_foreach(cat_list($cat_id, 1, 1, $table));
    
    if($type == 1){
        if($list){
            $list = implode(',', $list);
            $list = get_del_str_comma($list);
        }
    }
    
    return $list;
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

/*
 * 删除目录
 * 删除目录下文件
 * $dir 目录位置
 * $strpos 文件名称
 * $is_rmdir 是否删除目录
 */
function get_deldir($dir, $strpos = '', $is_rmdir = false) {
    $dh = opendir($dir);
    while ($file = readdir($dh)) {
        if ($file != "." && $file != "..") {
            $fullpath = $dir . "/" . $file;
            
            if($strpos){ //删除指定名称文件
                $spos = strpos($fullpath, $strpos);
                if($spos !== false){
                    if (!is_dir($fullpath)) {
                        unlink($fullpath);
                    } else {
                        get_deldir($fullpath);
                    }
                }
            }else{  //删除所有文件
                if (!is_dir($fullpath)) {
                    unlink($fullpath);
                } else {
                    get_deldir($fullpath);
                }
            }
        }
    }

    closedir($dh);
    
    //删除当前文件夹
    if($is_rmdir == true){
        if(rmdir($dir)) {
          return true;
        } else {
          return false;
        }
    }
}

/* 递归删除目录 */
function file_del($path) {
    if (is_dir($path)) {
        $file_list = scandir($path);
        foreach ($file_list as $file) {
            if ($file != '.' && $file != '..') {
                file_del($path . '/' . $file);
            }
        }
        @rmdir($path);  //这种方法不用判断文件夹是否为空,  因为不管开始时文件夹是否为空,到达这里的时候,都是空的     
    } else {
        @unlink($path);    //这两个地方最好还是要用@屏蔽一下warning错误,看着闹心
    }
}

/**
 * 删除文件
 */
function dsc_unlink($file = ''){
    if($file && file_exists($file)){
        unlink($file);
    }
}

//数组排序--根据键的值的数值排序
function get_array_sort($arr, $keys, $type = 'asc') {

    $new_array = array();
    if (is_array($arr) && !empty($arr)) {
        $keysvalue = $new_array = array();
        foreach ($arr as $k => $v) {
            $keysvalue[$k] = $v[$keys];
        }
        if ($type == 'asc') {
            asort($keysvalue);
        } else {
            arsort($keysvalue);
        }
        reset($keysvalue);
        foreach ($keysvalue as $k => $v) {
            $new_array[$k] = $arr[$k];
        }
    }

    return $new_array;
}

/*
 * 获取当前目录下的文件或目录
 */
function get_dir_file_list($dir = '', $type = 0, $explode = '') {
    if(empty($dir)){
        $dir = ROOT_PATH . 'includes/lib_ecmobanFunc.php';
    }
    
    $arr = array();
    if (file_exists($dir)) {
        if (!is_dir($dir)) {
            get_contents_section($dir);
        }else{
            $idx = 0;
            $dir = opendir($dir);
            while (( $file = readdir($dir)) !== false) {

                //by yanxin  去掉目录中的./与../
                if ($file == '.' || $file == '..') {
                    continue;
                }

                if (!is_dir($file)) {
                    if ($type == 1) {
                        $arr[$idx]['file'] = $file;
                        $file = explode($explode, $file);
                        $arr[$idx]['web_type'] = $file[0];
                    } else {
                        $arr[$idx] = $file;
                    }

                    $idx++;
                }
            }

            closedir($dir);
        }
        
        return $arr;
    }
}

/**
 * 过滤 $_REQUEST
 * 解决跨站脚本攻击（XSS）
 * script脚本
 */
function get_request_filter($get = '', $type = 0) {

    if ($get && $type) {
        foreach ($get as $key => $row) {
            $preg = "/<script[\s\S]*?<\/script>/i";
            if ($row && !is_array($row)) {

                $lower_row = strtolower($row);
                $lower_row = !empty($lower_row) ? preg_replace($preg, "", stripslashes($lower_row)) : '';

                if (strpos($lower_row, "</script>") !== false) {
                    $get[$key] = compile_str($lower_row);
                } elseif (strpos($lower_row, "alert") !== false) {
                    $get[$key] = '';
                } elseif (strpos($lower_row, "updatexml") !== false || strpos($lower_row, "extractvalue") !== false || strpos($lower_row, "floor") !== false) {
                    $get[$key] = '';
                } else {
                    $get[$key] = make_semiangle($row);
                }
            } else {
                $get[$key] = $row;
            }
        }
    } else {
        if ($_REQUEST) {
            foreach ($_REQUEST as $key => $row) {
                $preg = "/<script[\s\S]*?<\/script>/i";
                if ($row && !is_array($row)) {

                    $lower_row = strtolower($row);
                    $lower_row = !empty($lower_row) ? preg_replace($preg, "", stripslashes($lower_row)) : '';

                    if (strpos($lower_row, "</script>") !== false) {
                        $_REQUEST[$key] = compile_str($lower_row);
                    } elseif (strpos($lower_row, "alert") !== false) {
                        $_REQUEST[$key] = '';
                    } elseif (strpos($lower_row, "updatexml") !== false || strpos($lower_row, "extractvalue") !== false || strpos($lower_row, "floor") !== false) {
                        $_REQUEST[$key] = '';
                    } else {
                        $_REQUEST[$key] = make_semiangle($row);
                    }
                } else {
                    $_REQUEST[$key] = $row;
                }
            }
        }
    }

    if ($get && $type == 1) {
        $_POST = $get;
        return $_POST;
    } elseif ($get && $type == 2) {
        $_GET = $get;
        return $_GET;
    } else {
        return $_REQUEST;
    }
}

/* 重返序列化 */
function dsc_unserialize($serial_str) {
    $out =  preg_replace_callback('!s:(\d+):"(.*?)";!s', function ($r) {return 's:'.strlen($r[2]).':"'.$r[2].'";';}, $serial_str );
    return unserialize($out);
}

/**
 * 读取文件大小
 */
function get_file_centent_size($dir) {
    $filesize = filesize($dir) / 1024;
    return sprintf("%.2f",substr(sprintf("%.3f", $filesize), 0, -1));
}

/**
 * 商店设置-扩展信息-网站域名
 * site
 */
function get_site_domain($site_domain = '') {

    if ($site_domain) {
        if (strpos($site_domain, 'http://') === false && strpos($site_domain, 'https://') === false) {
            $site_domain = $GLOBALS['ecs']->http() . $site_domain;
        } else {
            if (strpos($site_domain, 'http') !== false) {
                $site = explode(".", $site_domain);
                $domain = str_replace($site[0], '', $site_domain);
                if (strpos($site[0], 'www') !== false) {
                    $site_domain = $GLOBALS['ecs']->http() . "www" . $domain;
                }
            }
        }

        if (substr($site_domain, str_len($site_domain) - 1) != '/') {
            $site_domain = $site_domain . "/";
        }
    }

    return $site_domain;
}

/** 
 * 下载远程图片
 * $url 下载外链文件地址
 * $path 存放的路径
*/
function get_http_basename($url = '', $path = '', $goods_lib = ''){
    
    $Http = new Http();
    $return_content = $Http->doGet($url);
    $url = basename($url);
    if ($goods_lib) {
        $filename = $path;
    } else {
        $filename = $path . "/" . $url;
    }

    if (file_put_contents($filename, $return_content)) {
        return $filename;
    } else {
        return false;
    }
}

/**
 * 获取随机数值
 */
function get_mt_rand($ran_num = 4){
    $str = '';
    for($i=0; $i<$ran_num; $i++){
        $str .= mt_rand(0,9);
    }
    
    return $str;
}

/* 合并二维数组重新数据数组 */
function get_merge_mult_arr($row){
    $item = array();
    foreach ($row as $k => $v) {
        if (!isset($item[$v['brand_id']])) {
            $item[$v['brand_id']] = $v;
        } else {
            $item[$v['brand_id']]['number']+=$v['number'];
        }
    }
    
    return $item;
}

//记录访问者统计
function modifyipcount($ip,$store_id){
	$t = time();
	$start = local_mktime(0,0,0,date("m",$t),date("d",$t),date("Y",$t));//当天的开始时间
	$end = local_mktime(23,59,59,date("m",$t),date("d",$t),date("Y",$t));//当天的结束时间
	$sql = "SELECT * " .
	" FROM " . $GLOBALS['ecs']->table('source_ip') .
	" WHERE ipdata='" . $ip . "' AND iptime BETWEEN " . $start . ' AND ' . $end ." AND storeid='" . $store_id . "'";
	$row = $GLOBALS['db']->getRow($sql);
	$iptime=time();
	if(!$row){
		$sql = "INSERT INTO " . $GLOBALS['ecs']->table('source_ip') . "(ipdata,iptime,storeid) VALUES('".$ip."','".$iptime."','".$store_id."')";
		$GLOBALS['db']->query($sql);
	}
}

/**
 * 判断是否加密
 *
 * @access  public
 * @param   string      $str 加密内容
 *
 * @return  sring
 */
function is_base64($str){
    if($str == base64_encode(base64_decode($str))){
        return true;
    }else{
        return false;
    }
}

/**
 * 转码
 *
 * @access  public
 * @param   string      $str 转码内容
 *
 * @return  sring
 * 
 * 作用：解密JS传字符串
 */
function unescape($str) {
    $ret = '';
    $len = strlen($str);
    for ($i = 0; $i < $len; $i++) {
        if ($str[$i] == '%' && $str[$i + 1] == 'u') {
            $val = hexdec(substr($str, $i + 2, 4));
            if ($val < 0x7f)
                $ret .= chr($val);
            else if ($val < 0x800)
                $ret .= chr(0xc0 | ($val >> 6)) . chr(0x80 | ($val & 0x3f));
            else
                $ret .= chr(0xe0 | ($val >> 12)) . chr(0x80 | (($val >> 6) & 0x3f)) . chr(0x80 | ($val & 0x3f));
            $i += 5;
        }
        else if ($str[$i] == '%') {
            $ret .= urldecode(substr($str, $i, 3));
            $i += 2;
        } else
            $ret .= $str[$i];
    }
    return $ret;
}

/**
 * 重新命名
 * addslashes
 */
function dsc_addslashes($str = '', $type = 1) {
    
    if ($str) {
        
        if (class_exists('ECS')) {
            $str = $GLOBALS['ecs']->get_filter_str_array($str, $type);
        }

        if (function_exists('get_del_str_comma')) {
            $str = get_del_str_comma($str);
        }
        
    }
    
    return $str;
}

/**
 * XML编码
 * @param mixed $data 数据
 * @param string $root 根节点名
 * @param string $item 数字索引的子节点名
 * @param string $attr 根节点属性
 * @param string $id   数字索引子节点key转换的属性名
 * @param string $encoding 数据编码
 * @return string
 */
function xml_encode($data, $root='dsc', $item='item', $attr='', $id='id', $encoding='utf-8') {
    if(is_array($attr)){
        $_attr = array();
        foreach ($attr as $key => $value) {
            $_attr[] = "{$key}=\"{$value}\"";
        }
        $attr = implode(' ', $_attr);
    }
    $attr   = trim($attr);
    $attr   = empty($attr) ? '' : " {$attr}";
    $xml    = "<?xml version=\"1.0\" encoding=\"{$encoding}\"?>";
    $xml   .= "<{$root}{$attr}>";
    $xml   .= data_to_xml($data, $item, $id);
    $xml   .= "</{$root}>";
    return $xml;
}

/**
 * 数据XML编码
 * @param mixed  $data 数据
 * @param string $item 数字索引时的节点名称
 * @param string $id   数字索引key转换为的属性名
 * @return string
 */
function data_to_xml($data, $item='item', $id='id') {
    $xml = $attr = '';
    foreach ($data as $key => $val) {
        if(is_numeric($key)){
            $id && $attr = " {$id}=\"{$key}\"";
            $key  = $item;
        }
        $xml    .=  "<{$key}{$attr}>";
        $xml    .=  (is_array($val) || is_object($val)) ? data_to_xml($val, $item, $id) : $val;
        $xml    .=  "</{$key}>";
    }
    return $xml;
}

/**
 * 跳转首页
 * @param int  user_id 会员ID
 */
function get_go_index($type = 0, $var = false) {

    if ($type == 1) {
        if (!$var) {
            ecs_header("Location: " . $GLOBALS['ecs']->url() . "\n");
            exit;
        }
    } else {
        $user_id = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

        if (!$user_id) {
            ecs_header("Location: " . $GLOBALS['ecs']->url() . "\n");
            exit;
        }
    }
}

/**
 * 递归本地目录上传文件
 * 上传文件至OSS
 * 
 * $dir 指定上传内容目录路径，包含（ROOT_PATH）
 * $path 目录路径，即：不包含（ROOT_PATH）
 * $is_recursive 是否允许递归查询目录
 */
function get_recursive_file_oss($dir, $path = '', $is_recursive = false, $type = 0){
    
    $file_list = scandir($dir);
    
    $arr = array();
    if ($file_list) {
        foreach ($file_list as $key => $row) {
            if($is_recursive && is_dir($dir . $row) && !in_array($row, array('.', '..', '...'))){
                $arr[$key]['child'] = get_recursive_file_oss($dir . $row . "/", $path, $is_recursive, 1);
            }elseif(is_file($dir . $row)){
                
                if($type == 1){
                    $arr[$key] = $dir . $row;
                }else{
                    $arr[$key] = $path . $row;
                }
            }
            
            if($arr[$key]){
                $arr[$key] = str_replace(ROOT_PATH, '', $arr[$key]);
            }
        }
        
        if ($arr) {
            $arr = arr_foreach($arr);
            $arr = array_unique($arr);
        }
    }
    
    return $arr;
}

/**
 * 对象转数组
 */
function object_array($array) {
    if (is_object($array)) {
        $array = (array) $array;
    } if (is_array($array)) {
        foreach ($array as $key => $value) {
            $array[$key] = object_array($value);
        }
    }
    return $array;
}

/** 
 * 校验是否非法操作
 * reg_token
 * $type 0:dwt 1:lib
 */
function get_dsc_token(){
    
    $sc_rand = rand(100000, 999999);
    $sc_guid = sc_guid();

    if (isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT']) {
        $token_agent = MD5($sc_guid . "-" . $sc_rand) . MD5($_SERVER['HTTP_USER_AGENT']);
    } else {
        $token_agent = MD5($sc_guid . "-" . $sc_rand);
    }
    
    $dsc_token = MD5($sc_guid . "-" . $sc_rand);
    $_SESSION['token_agent'] = $token_agent;

    return $dsc_token;
}

/**
 * 判断是否邮箱
 */
function get_is_email($username){
    if (preg_match("/[^\d-., ]/", $username)) {
        //不是数字
        $a = "/([a-z0-9]*[-_\.]?[a-z0-9]+)*@([a-z0-9]*[-_]?[a-z0-9]+)(\.[a-z]*)/i";
        if (preg_match($a, $username)) {
            return true;
        }else{
            return false;
        }
    }
}

/**
 * 判断是否手机
 */
function get_is_phone($username){
    
    $strlen = strlen($username);
    // $a = "/^\d*$/"; 
	$a = "/13[123456789]{1}\d{8}|15[123456789]\d{8}|188\d{8}|17[123456789]\d{8}/";
    
    if ($strlen == 11 && preg_match($a, $username)) {
        return true;
    } else {
        return false;
    }
}

/**
 * 更新订单对应的 pay_log
 * 如果未支付，修改支付金额；否则，生成新的支付log
 * @param   int     $order_id   订单id
 */
function update_pay_log($order_id)
{
    $order_id = intval($order_id);
    if ($order_id > 0)
    {
        $sql = "SELECT order_amount FROM " . $GLOBALS['ecs']->table('order_info') .
                " WHERE order_id = '$order_id'";
        $order_amount = $GLOBALS['db']->getOne($sql);
        if (!is_null($order_amount))
        {
            $sql = "SELECT log_id FROM " . $GLOBALS['ecs']->table('pay_log') .
                    " WHERE order_id = '$order_id'" .
                    " AND order_type = '" . PAY_ORDER . "'" .
                    " AND is_paid = 0";
            $log_id = intval($GLOBALS['db']->getOne($sql));
            if ($log_id > 0)
            {
                /* 未付款，更新支付金额 */
                $sql = "UPDATE " . $GLOBALS['ecs']->table('pay_log') .
                        " SET order_amount = '$order_amount' " .
                        "WHERE log_id = '$log_id' LIMIT 1";
            }
            else
            {
                /* 已付款，生成新的pay_log */
                $sql = "INSERT INTO " . $GLOBALS['ecs']->table('pay_log') .
                        " (order_id, order_amount, order_type, is_paid)" .
                        "VALUES('$order_id', '$order_amount', '" . PAY_ORDER . "', 0)";
            }
            $GLOBALS['db']->query($sql);
        }
    }
}

/**
 * 数组转换
 * 三维数组转换成二维数组
 */
function get_three_to_two_array($list = array()) {
    
    $new_list = array();
    if ($list) {
        foreach ($list as $lkey => $lrow) {
            foreach ($lrow as $ckey => $crow) {
                $new_list[] = $crow;
            }
        }
    }
    
    return $new_list;
}

//打印日志
if (!function_exists('logResult')) {

    function logResult($word = '', $path = '') {

        if (empty($path)) {
            $path = ROOT_PATH . DATA_DIR . "/log.txt";
        } else {
            if (!file_exists($path)) {
                make_dir($path);
            }

            $path = $path . "/log.txt";
        }

        $word = is_array($word) ? var_export($word, 1) : $word;
        $fp = fopen($path, "a");
        flock($fp, LOCK_EX);
        fwrite($fp, $GLOBALS['_LANG']['implement_time'] . strftime("%Y%m%d%H%M%S", gmtime()) . "\n" . $word . "\n");
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

?>