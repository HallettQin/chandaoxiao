<?php

namespace App\Custom\Site\Controllers;

use App\Modules\Site\Controllers\IndexController;

class SendController extends IndexController
{
    public function actionTest()
    {
        // 短信发送测试
        $message = [
            'code' => '1234',
            'product' => 'sitename'
        ];
        $res = send_sms('18801828888', 'sms_signin', $message);
        if ($res !== true) {
            exit($res);
        };

        // 邮件发送测试
        $res = send_mail('xxx', 'wanglin@ecmoban.com', 'title', 'content');
        if ($res !== true) {
            exit($res);
        };
    }
}
