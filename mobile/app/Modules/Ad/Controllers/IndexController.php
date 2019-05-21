<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/29
 * Time: 0:23
 */
namespace App\Modules\Ad\Controllers;

use App\Modules\Base\Controllers\FrontendController;

class IndexController extends FrontendController
{
    public function __construct()
    {
        parent::__construct();
        L(require(LANG_PATH . C('shop.lang') . '/user.php'));
    }

    public function actionIndex()
    {
        $this->purchasers_priv();

        $aid = I('get.id');
        if (!$aid) {
            show_message('参数错误', '', '', 'error');
        }

        $position = dao('touch_ad_position')->where(['position_model' => 'seller_banner_'.$aid])->find();
        if (!$position) {
            show_message('参数错误', '', '', 'error');
        }

        $ads = dao('touch_ad')->where(['position_id' => $position['position_id']])->select();
        $this->assign('position', $position);
        $this->assign('ads', $ads);
        $this->assign('page_title', $position['position_name']);
        $this->display();
    }

    public function actionRecommend() {
        $mode =  I('get.mode', 'trim');
        if (!$mode) {
            return;
        }
        $position = dao('touch_ad_position')->where(['position_model' => 'category_recommend_'.$mode])->find();
        if (!$position) {
            $this->ajaxReturn('无内容');
        }

        $ads = dao('touch_ad')->where(['position_id' => $position['position_id']])->select();

        $_ads = [];
        foreach ($ads as $k => $ad) {
            if (!strpos($ad[ad_code],'www')) {
                $ads[$k]['ad_code'] = "../data/afficheimg/".$ad[ad_code];
            }
        }

        exit(json_encode(['ads'=>$ads]));
    }

    public function actionBrand() {
        $mode =  I('get.mode', 'trim');
        if (!$mode) {
            return;
        }
        $position = dao('touch_ad_position')->where(['position_model' => 'category_brand_'.$mode])->find();
        if (!$position) {
            $this->ajaxReturn('无内容');
        }

        $ads = dao('touch_ad')->where(['position_id' => $position['position_id']])->select();

        $_ads = [];
        foreach ($ads as $k => $ad) {
            if (!strpos($ad[ad_code],'www')) {
                $ads[$k]['ad_code'] = "../data/afficheimg/".$ad[ad_code];
            }
        }

        exit(json_encode(['ads'=>$ads]));
    }
}