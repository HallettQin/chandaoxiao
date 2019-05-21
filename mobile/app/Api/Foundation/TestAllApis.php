<?php

namespace App\Api\Foundation;

use App\Extensions\Http;

class TestAllApis
{
    private $apis;   //api列表

    private $gateway;   // 网关

    private $logger;  //  log  对象

    public function __construct()
    {
        $this->gateway = 'http://10.10.10.145/dsc/mobile/?app=api';

        $this->setApis();   //初始化
    }

    /**
     * 测试接口
     */
    public function test()
    {
        $post = $this->apis;   //
        //
        $result = [];
        $log = ApiLogger::init('api', 'debug');

        foreach ($post as $v) {
            if (empty($v) || empty($v['method'])) {
                continue;
            }
            if (($res = $this->doTest($v)) === true) {
                //通过
                $result[$v['method']] = [
                    'code'=>'success'
                ];
                $log->info('接口信息：'.$v['method'].' msg:'.$res['msg']);
                $log->notice('接口提醒：'.$v['method'].' msg:'.$res['msg']);
            } else {
                $result[$v['method']] = [
                    'code'=>'fail',
                    'msg' => $res['msg']
                ];
                $log->error('接口错误：'.$v['method'].' msg:'.$res['msg']);
                $log->debug(['wer'=>'werwrwr']);
            }
        }
        return $result;
    }

    /**
     * 测试方法
     * @param $postData
     * @return bool
     */
    private function doTest($postData)
    {
        $response = Http::doPost($this->gateway, $postData);
        $response = json_decode($response);

        if (is_object($response)) {
            $response = (array)$response;
        }

        $code = (string)$response['code'];

        if ('0' === $code) {
            return true;
        } else {
            return $response;
        }
    }

    /**
     * 添加接口
     * @param array $post
     */
    public function addApis(array $post)
    {
        //in_array    todo

        foreach ($this->apis as $v) {
            if ($v['method'] == $post['method']) {
                return false;
            }
        }

        //add
        $this->apis[] = $post;
    }

    /**
     * 获取全部接口
     * @return array
     */
    public function getApis()
    {
        return $this->apis;
    }

    /**
     * 获取接口地址
     * @return string
     */
    public function getGateway()
    {
        return $this->gateway;
    }

    /**
     * 设置api列表 （初始化使用）
     */
    private function setApis()
    {
        $this->apis = [
            [
                'method' => 'ecapi.shop.get',
                'id' => '1'
            ],
            [
                'method' => 'ecapi.category.list'
            ],
            [
                'method' => 'ecapi.category.get',
                'id' => '1'
            ],
            [
                'method' => 'ecapi.brand.list'
            ],
            [
                'method' => 'ecapi.brand.get',
                'id' => '1'
            ]
        ];
    }
}
