<?php

function url()
{
}

//获取OSS Bucket信息
function get_bucket_info()
{
    $shopconfig = app('App\Repositories\ShopConfig\ShopConfigRepository');
    $res = $shopconfig->getOssConfig();

    if ($res) {
        $regional = substr($res['regional'], 0, 2);
        if ($regional == 'us' || $regional == 'ap') {
            $res['outside_site'] = "https://" . $res['bucket'] . ".oss-" . $res['regional'] . ".aliyuncs.com";
            $res['inside_site'] = "https://" . $res['bucket'] . ".oss-" . $res['regional'] . "-internal.aliyuncs.com";
        } else {
            $res['outside_site'] = "https://" . $res['bucket'] . ".oss-cn-" . $res['regional'] . ".aliyuncs.com";
            $res['inside_site'] = "https://" . $res['bucket'] . ".oss-cn-" . $res['regional'] . "-internal.aliyuncs.com";
        }
        $res['endpoint'] = str_replace('http://', 'https://', $res['endpoint']);
    }

    return $res;
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
if (!function_exists("get_image_path")) {
    function get_image_path($image = '', $path = '')
    {
        $rootPath = app('request')->root();
        $rootPath = dirname(dirname($rootPath)) . '/';
        $no_picture = $rootPath . 'mobile/public/img/no_image.jpg';

        if (strtolower(substr($image, 0, 4)) == 'http') {
            $url = $image;
        } else {
            $shopconfig = app('App\Repositories\ShopConfig\ShopConfigRepository');
            $open_oss = $shopconfig->getShopConfigByCode('open_oss');
            if ($open_oss == 1) {
                $bucket_info = get_bucket_info();
                $bucket_info['endpoint'] = empty($bucket_info['endpoint']) ? $bucket_info['outside_site'] : $bucket_info['endpoint'];
                $url = empty($image) ? $no_picture : rtrim($bucket_info['endpoint'], '/') . '/' . $path . $image;
            } else {
                $path = empty($path) ? '' : rtrim($path, '/') . '/';
                $img_path = $path . $image;
                if (empty($image)) {
                    $url = $no_picture;
                } else {
                    $url = $rootPath . $img_path;
                }
            }
        }

        return $url;
    }
}


/**
 * 格式化商品价格
 *
 * @access  public
 * @param   float $price 商品价格
 * @return  string
 */
if (!function_exists('price_format')) {
    function price_format($price, $change_price = true)
    {
        $shopconfig = app('App\Repositories\ShopConfig\ShopConfigRepository');
        $priceFormat = $shopconfig->getShopConfigByCode('price_format');
        $currencyFormat = strip_tags($shopconfig->getShopConfigByCode('currency_format'));

        if ($price === '') {
            $price = 0;
        }
        if ($change_price && defined('ECS_ADMIN') === false) {
            switch ($priceFormat) {
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

        return sprintf($currencyFormat, $price);
    }
}


/**
 *  将一个字串中含有全角的数字字符、字母、空格或'%+-()'字符转换为相应半角字符
 *
 * @access  public
 * @param   string $str 待转换字串
 *
 * @return  string       $str         处理后字串
 */
if (!function_exists('make_semiangle')) {
    function make_semiangle($str)
    {
        $arr = ['０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
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
            '　' => ' '];

        return strtr($str, $arr);
    }
}

/**
 *  生成一个用户自定义时区日期的GMT时间戳
 *
 * @access  public
 * @param   int $hour
 * @param   int $minute
 * @param   int $second
 * @param   int $month
 * @param   int $day
 * @param   int $year
 *
 * @return void
 */
if (!function_exists('local_mktime()')) {
    function local_mktime($hour = null, $minute = null, $second = null, $month = null, $day = null, $year = null)
    {
        $shopconfig = app('App\Repositories\ShopConfig\ShopConfigRepository');
        $timezone = $shopconfig->getShopConfigByCode('timezone');
        /**
         * $time = mktime($hour, $minute, $second, $month, $day, $year) - date('Z') + (date('Z') - $timezone * 3600)
         * 先用mktime生成时间戳，再减去date('Z')转换为GMT时间，然后修正为用户自定义时间。以下是化简后结果
         **/
        $time = mktime($hour, $minute, $second, $month, $day, $year) - $timezone * 3600;

        return $time;
    }
}

/**
 * 获得用户所在时区指定的日期和时间信息
 *
 * @param   $timestamp  integer     该时间戳必须是一个服务器本地的时间戳
 *
 * @return  array
 */
if (!function_exists('local_getdate()')) {
    function local_getdate($timestamp = null)
    {
        $shopconfig = app('App\Repositories\ShopConfig\ShopConfigRepository');
        $timezone = $shopconfig->getShopConfigByCode('timezone');

        /* 如果时间戳为空，则获得服务器的当前时间 */
        if ($timestamp === null) {
            $timestamp = time();
        }

        $gmt = $timestamp - date('Z');       // 得到该时间的格林威治时间
        $local_time = $gmt + ($timezone * 3600);    // 转换为用户所在时区的时间戳

        return getdate($local_time);
    }
}

/**
 * 获得用户所在时区指定的时间戳
 *
 * @param   $timestamp  integer     该时间戳必须是一个服务器本地的时间戳
 *
 * @return  array
 */
if (!function_exists('local_gettime()')) {
    function local_gettime($timestamp = null)
    {
        $tmp = local_getdate($timestamp);
        return $tmp[0];
    }
}

/**
 * 获得当前格林威治时间的时间戳
 *
 * @return  integer
 */
if (!function_exists('gmtime()')) {
    function gmtime()
    {
        return (time() - date('Z'));
    }
}

/**
 * 将GMT时间戳格式化为用户自定义时区日期
 *
 * @param  string $format
 * @param  integer $time 该参数必须是一个GMT的时间戳
 *
 * @return  string
 */
if (!function_exists('local_date()')) {
    function local_date($format, $time = null)
    {
        $shopconfig = app('App\Repositories\ShopConfig\ShopConfigRepository');
        $timezone = $shopconfig->getShopConfigByCode('timezone');

        if ($time === null) {
            $time = gmtime();
        } elseif ($time <= 0) {
            return '';
        }

        $time += ($timezone * 3600);

        return date($format, $time);
    }
}

/**
 * 浏览器友好的变量输出
 * @param mixed $var 变量
 * @param boolean $echo 是否输出 默认为True 如果为false 则返回输出字符串
 * @param string $label 标签 默认为空
 * @param boolean $strict 是否严谨 默认为true
 * @return void|string
 */
function dump($var)
{
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
}
