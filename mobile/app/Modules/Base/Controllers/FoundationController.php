<?php

namespace App\Modules\Base\Controllers;

use Think\Cache;
use Think\Think;
use Raven_Client;
use Think\Upload;
use Raven_ErrorHandler;
use App\Extensions\Page;
use App\Libraries\Mysql;
use Org\Net\Http as ThinkHttp;
use Think\Upload\Driver\Alioss;
use App\Services\IpBasedLocation;
use Think\Controller\RestController;
use Illuminate\Filesystem\Filesystem;

abstract class FoundationController extends RestController
{
    protected $model = null;
    protected $cache = null;
    protected $fs = null;
    protected $pager = '';

    public function __construct()
    {
        parent::__construct();
        define('__HOST__', (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']);
        define('__URL__', __HOST__ . __ROOT__);
        define('__PC__', C('TMPL_PARSE_STRING.__PC__'));
        define('__STATIC__', C('TMPL_PARSE_STRING.__STATIC__'));
        define('__PUBLIC__', C('TMPL_PARSE_STRING.__PUBLIC__'));
        define('__TPL__', C('TMPL_PARSE_STRING.__TPL__'));
        define('MODULE_BASE_PATH', APP_PATH . MODULE_NAME . '/');

        $this->fs = new Filesystem();
        $this->model = new Mysql();
        $GLOBALS['cache'] = $this->cache = Cache::getInstance();
        $GLOBALS['smarty'] = Think::instance('Think\View');
    }

    /**
     * 根据IP地址获取城市名称
     * @param string $ip
     * @return mixed
     */
    protected function getApiCityName($ip = '')
    {
        $ip = empty($ip) ? get_client_ip() : $ip;
        $data = [
            'ip' => $ip
        ];
        new IpBasedLocation($data);
        return $data['city'];
    }

    protected function load_helper($files = [], $type = 'base')
    {
        if (!is_array($files)) {
            $files = [
                $files
            ];
        }
        $base_path = $type == 'app' ? MODULE_BASE_PATH : BASE_PATH;
        foreach ($files as $vo) {
            $helper = $base_path . 'Helpers/' . $vo . '_helper.php';
            if (file_exists($helper)) {
                require_once $helper;
            }
        }
    }

    // 获取分页查询limit
    protected function pageLimit($url, $num = 10)
    {
        $url = str_replace(urlencode('{page}'), '{page}', $url);
        $page = isset($this->pager['obj']) && is_object($this->pager ['obj']) ? $this->pager ['obj'] : new Page();
        $cur_page = $page->getCurPage($url);
        $limit_start = ($cur_page - 1) * $num;
        $limit = $limit_start . ',' . $num;
        $this->pager = [
            'obj' => $page,
            'url' => $url,
            'num' => $num,
            'cur_page' => $cur_page,
            'limit' => $limit
        ];
        return $limit;
    }

    // 分页结果显示
    protected function pageShow($count)
    {
        return $this->pager ['obj']->show($this->pager ['url'], $count, $this->pager ['num']);
    }

    /**
     * 上传文件
     * @param string $savePath 保存目录
     * @param bool $hasOne 返回一维数组
     * @param int $size 文件上传大小限制
     * @param bool $thumb 是否生成缩略图
     * @param bool $autoSub 是否生成子目录
     * @param array $subName 生成子目录名称规则 201710
     * @return array
     */
    protected function upload($savePath = '', $hasOne = false, $size = 2, $thumb = false, $autoSub = false)
    {
        $config = [
            'maxSize' => $size * 1024 * 1024, // 2MB
            'rootPath' => C('UPLOAD_PATH'),
            'savePath' => rtrim($savePath, '/') . '/', //保存路径
            'exts' => ['jpg', 'gif', 'png', 'jpeg', 'bmp', 'mp3', 'amr', 'mp4'],
            'autoSub' => $autoSub,
            'thumb' => $thumb,
            'subName' => ['date', 'Ym']
        ];
        $aliossConfig = $this->getBucketInfo();
        if (C('shop.open_oss') == 1 && $aliossConfig !== false) {
            $up = new Upload($config, 'Alioss', $aliossConfig);
        } else {
            $up = new Upload($config);
        }
        // 上传文件
        $result = $up->upload();
        if (!$result) {
            // 上传错误提示错误信息
            return [
                'error' => 1,
                'message' => $up->getError()
            ];
        } else {
            // 上传成功 获取上传文件信息
            $res = [
                'error' => 0
            ];
            if ($hasOne) {
                $info = reset($result);
                $res['url'] = $info['savepath'] . $info['savename'];
            } else {
                foreach ($result as $k => $v) {
                    $result[$k]['url'] = $v['savepath'] . $v['savename'];
                }
                $res['url'] = $result;
            }
            return $res;
        }
    }

    /**
     * 移除文件（支持阿里云OSS）
     * @param string $file
     * @return bool
     */
    protected function remove($file = '')
    {
        if (empty($file) || in_array($file, ['/', '\\'])) {
            return false;
        }
        $config = $this->getBucketInfo();
        if (C('shop.open_oss') == 1 && $config !== false) {
            $client = new Alioss($config);
            if ($client->delete($file)) {
                return true;
            }
        } else {
            $file = is_file(ROOT_PATH . $file) ? ROOT_PATH . $file : dirname(ROOT_PATH) . '/' . $file;
            if (is_file($file)) {
                $this->fs->delete($file);
                return true;
            }
        }
        return false;
    }

    /**
     * 附件镜像到阿里云OSS
     * @param string $file 绝对路径下的文件
     * @param string $savepath 保存到OSS的文件目录
     * @return bool|mixed
     */
    protected function ossMirror($file = '', $savepath = '')
    {
        $data = [
            'savepath' => rtrim($savepath, '/') . '/',
            'savename' => basename($file),
            'tmp_name' => $file,
        ];
        $config = $this->getBucketInfo();
        if ($config !== false) {
            $client = new Alioss($config);
            $client->save($data);
            return $data['url'];
        }
        return false;
    }

    /**
     * 下载远程图片
     * @param $url http://
     * @param $path data/attached/
     * @return
     */
    protected function downloadFiles($url, $path = '')
    {
        $path = empty($path) ? '' : rtrim($path, '/') . '/';

        $dir = dirname(ROOT_PATH) . '/' . $path;
        if (!file_exists($dir) && !empty($path)) {
            make_dir($dir, 0777);
        }

        if (strtolower(substr($url, 0, 4)) == 'http' && !empty($path)) {
            $filepath = $dir . basename($url);
            if (!file_exists($filepath)) {
                ThinkHttp::curlDownload($url, $filepath);
            }
        }
    }

    /**
     * 同步上传图片到OSS
     * @param $imglist  图片列表 如 array('0'=>'1.jpg', '1'=>'2.png')
     * @param $path 本地路径 如 data/attached/
     * @param $is_delete 是否要删除本地图片
     * @return
     */
    protected function BatchUploadOss($imglist, $path = '', $is_delete = false)
    {
        // 开启OSS
        if (C('shop.open_oss') == 1) {
            foreach ($imglist as $k => $filename) {
                $image_name = $this->ossMirror(dirname(ROOT_PATH) .'/'. $path . $filename, $path);
                if ($is_delete == true) {
                    $this->remove($image_name); // 删除本地
                }
            }
            return isset($image_name) ? true : false;
        }
    }

    /**
     * 同步下载OSS图片到本地
     * @param $imglist 图片列表 如 array('0'=>'1.jpg', '1'=>'2.png')
     * @param $path 本地路径 如 data/attached/
     * @return
     */
    protected function BatchDownloadOss($imglist, $path)
    {
        // 开启OSS
        if (C('shop.open_oss') == 1) {
            $bucket_info = get_bucket_info();
            $bucket_info['endpoint'] = empty($bucket_info['endpoint']) ? $bucket_info['outside_site'] : $bucket_info['endpoint'];
            $http = rtrim($bucket_info['endpoint'], '/') . '/';
            foreach ($imglist as $k => $filename) {
                $url = $http . $path . $filename;

                $this->downloadFiles($url, $path);
            }
            return true;
        }
    }

    /**
     * 获取 Bucket 配置
     * @return array
     */
    private function getBucketInfo()
    {
        // 获取配置
        $condition = [
            'is_use' => 1
        ];
        $res = $this->model->table('oss_configure')->cache(true)->where($condition)->find();
        if (empty($res)) {
            return false;
        }
        // 优化endpoint
        $regional = substr($res['regional'], 0, 2);
        $endpoint = rtrim(str_replace(['http://', 'https://'], '', strtolower($res['endpoint'])), '/');
        if ($regional == 'us' || $regional == 'ap') {
            $res['endpoint'] = $res['is_cname'] == 1 ? $endpoint : "oss-" . $res['regional'] . ".aliyuncs.com";
            $res['outside_site'] = "http://" . $res['bucket'] . ".oss-" . $res['regional'] . ".aliyuncs.com";
            $res['inside_site'] = "http://" . $res['bucket'] . ".oss-" . $res['regional'] . "-internal.aliyuncs.com";
        } else {
            $res['endpoint'] = $res['is_cname'] == 1 ? $endpoint : "oss-cn-" . $res['regional'] . ".aliyuncs.com";
            $res['outside_site'] = "http://" . $res['bucket'] . ".oss-cn-" . $res['regional'] . ".aliyuncs.com";
            $res['inside_site'] = "http://" . $res['bucket'] . ".oss-cn-" . $res['regional'] . "-internal.aliyuncs.com";
        }
        // 返回配置
        return [
            'bucket' => $res['bucket'],
            'accessKeyId' => $res['keyid'], // 您从OSS获得的AccessKeyId
            'accessKeySecret' => $res['keysecret'], // 您从OSS获得的AccessKeySecret
            'endpoint' => $res['endpoint'], // 您选定的OSS数据中心访问域名
            'isCName' => (boolean)$res['is_cname']
        ];
    }

    /**
     * 反馈异常信息
     *
     * @param $e
     */
    protected function sentry($e, $type = 0)
    {
        $client = new Raven_Client('https://ae2118aa1c3149c5bba492ed9abaf43f:2e4b9be6f4d9495eb3f0a44f28484893@sentry.io/106949');
        $error_handler = new Raven_ErrorHandler($client);
        $error_handler->registerExceptionHandler();
        $error_handler->registerErrorHandler();
        $error_handler->registerShutdownFunction();
        if ($type) {
            $client->captureMessage($e);
        } else {
            $client->captureException($e);
        }
    }
}
