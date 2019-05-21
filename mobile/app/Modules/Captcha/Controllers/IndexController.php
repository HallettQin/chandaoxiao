<?php

namespace App\Modules\Captcha\Controllers;

use Think\Verify;
use App\Modules\Base\Controllers\FrontendController;

class IndexController extends FrontendController
{

    /**
     * 验证码
     */
    public function actionIndex()
    {
        $params = [
            'fontSize' => 14, // 验证码字体大小
            'length' => 4, // 验证码位数
            'useNoise' => false, // 关闭验证码杂点
            'fontttf' => '4.ttf',
            'bg' => [255, 255, 255]
        ];
        $verify = new Verify($params);
        $verify->entry();
    }
}
