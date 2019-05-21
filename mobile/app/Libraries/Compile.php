<?php

namespace App\Libraries;

use App\Extensions\Http;
use Illuminate\Filesystem\Filesystem;

class Compile
{
    public static $savePath = '';

    /**
     * 初始化
     */
    public static function init()
    {
        self::$savePath = ROOT_PATH . 'storage/app/diy';
        if (!is_dir(self::$savePath)) {
            $fs = new Filesystem();
            $fs->makeDirectory(self::$savePath);
        }
    }

    /**
     * 保存可视化编辑的配置数据
     * @param string $file
     * @param array $data
     */
    public static function setModule($file = 'index', $data = [])
    {
        self::init();
        if (!empty($data)) {
            $data = '<?php exit("no access");' . serialize($data);
            file_put_contents(self::$savePath . '/' . $file . '.php', $data);
        }
    }

    /**
     * 获取可视化配置的数据
     * @param string $file
     * @param bool $unserialize
     * @return bool|mixed
     */
    public static function getModule($file = 'index', $unserialize = true)
    {
        self::init();
        $filePath = self::$savePath . '/' . $file . '.php';
        if (is_file($filePath)) {
            $data = file_get_contents($filePath);
            $data = str_replace('<?php exit("no access");', '', $data);
            return $unserialize ? unserialize($data) : $data;
        }
        return false;
    }

    /**
     * 清空模块
     * @param string $file
     * @return bool
     */
    public static function cleanModule($file = 'index')
    {
        self::init();
        $filePath = self::$savePath . '/' . $file . '.php';
        if (is_file($filePath)) {
            return unlink($filePath);
        }
        return true;
    }
    
    /**
     * 处理默认数据图片路径
     */
    public static function replace_img($data)
    {
        $data = str_replace(['http://localhost/'], '/', $data);
        return str_replace(['/ecmoban0309/', '/dscmall/'], rtrim(dirname(__URL__), '/') . '/', $data);
    }

    /**
     * 默认初始化数据
     * @return array
     */
    public static function initModule()
    {
        $data = [];
        $data= unserialize(str_replace('<?php exit("no access");', '', file_get_contents(ROOT_PATH.'storage/app/diy/default.php')));
        foreach ($data as $key => $value) {
            $data[$key]['moreLink'] = self::replace_img($value["moreLink"]);
            $data[$key]['icon'] = self::replace_img($value["icon"]);
            if (isset($value['data']["icon"])) {
                $data[$key]['data']['icon'] = self::replace_img($value['data']["icon"]);
            }
            if (isset($value['data']["moreLink"])) {
                $data[$key]['data']['moreLink'] = self::replace_img($value['data']["moreLink"]);
            }
            foreach ($value['data']['imgList'] as $ke => $val) {
                if (isset($val["img"])) {
                    $data[$key]['data']['imgList'][$ke]["img"] = self::replace_img($val["img"]);
                }
                if (isset($val["link"])) {
                    $data[$key]['data']['imgList'][$ke]["link"] = self::replace_img($val["link"]);
                }
            }
            foreach ($value['data']['contList'] as $ke => $val) {
                if (isset($val["url"])) {
                    $data[$key]['data']['contList'][$ke]["url"] = self::replace_img($val["url"]);
                }
            }
        }
        self::setModule('index', $data);
        return $data;
    }

    /**
     * 返回商品列表
     * @param string $param
     * @return array
     */
    public static function goodsList($param = [])
    {
        $data = [
            'id' => 0,
            'brand' => 0,
            'intro' => '',
            'price_min' => 0,
            'price_max' => 0,
            'filter_attr' => 0,
            'sort' => 'goods_id',
            'order' => 'desc',
            'keyword' => '',
            'isself' => 0,
            'hasgoods' => 0,
            'promotion' => 0,
            'page' => 1,
            'type' => 1,
            'size' => 10,
            C('VAR_AJAX_SUBMIT') => 1
        ];
        $data = array_merge($data, $param);
        $cache_id = md5(serialize($data));
        $list = S($cache_id);
        if ($list === false) {
            $url = url('category/index/products', $data, false, true);
            $res = Http::doGet($url);
            if ($res) {
                $data = json_decode($res, 1);
                $list = empty($data['list']) ? false : $data['list'];
                S($cache_id, $list, 600);
            }
        }
        return $list;
    }
}
