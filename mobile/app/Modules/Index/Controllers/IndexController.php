<?php

namespace App\Modules\Index\Controllers;

use App\Extensions\Http;
use App\Libraries\Compile;
use App\Libraries\Image;
use App\Modules\Base\Controllers\FrontendController;

class IndexController extends FrontendController
{
    public function __construct()
    {
        parent::__construct();
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: X-HTTP-Method-Override, Content-Type, x-requested-with, Authorization');
        $files = [
            'article'
        ];
        $this->load_helper($files);
    }

    /**
     * 首页信息
     * post: /index.php?m=index
     * param: null
     * return: module
     */
    public function actionIndex()
    {
        uaredirect(__PC__ . '/');
        if (!$_SESSION['user_id']) {
            $this->redirect(url('welcome'));
        }
        if (IS_POST) {
            $preview = input('preview', 0);
            if ($preview) {
                $module = Compile::getModule('preview');
            } else {
                $module = Compile::getModule();
            }
            if ($module === false) {
                $module = Compile::initModule();
            }
            $this->response(['error' => 0, 'data' => $module ? $module : '']);
        }
        /**
         * 首页Banner广告图
         */
        $banner_ads = S('banner_ads');
        if ($banner_ads === false) {
            $position = dao('touch_ad_position')->where(['position_model' => 'index_ad'])->find();
            if ($position) {
                $banner_ads = dao('touch_ad')->where(['position_id' => $position['position_id']])->select();
                S('banner_ads', $banner_ads, 600);
            }
        }
        $this->assign('banner_ads', $banner_ads);

        //首页分类图标
        $index_category_ads = S('index_category_ads');
        if ($index_category_ads === false) {
            $position = dao('touch_ad_position')->where(['position_model' => 'index_category'])->find();
            if ($position) {
                $index_category_ads = dao('touch_ad')->where(['position_id' => $position['position_id']])->select();
                S('index_category_ads', $index_category_ads, 600);
            }
        }

        $this->assign('index_category_ads', $index_category_ads);

        //Vip专区
        $vip_ads = S('vip_ads');
        if (!$vip_ads) {
            $vip_ads = dao('touch_ad')->where(['ad_name' => '首页Vip专区'])->find();
            S('vip_ads', $vip_ads, 600);
        }

        $this->assign('vip_ads', $vip_ads);

        /**
         * 首页弹出广告
         */
        $popup_ad = S('popup_ad');
        if ($popup_ad === false) {
            $position = dao('touch_ad_position')->where(['position_model' => 'popup_ad'])->find();
            if ($position) {
                $popup_ad = dao('touch_ad')->where(['position_id' => $position['position_id']])->find();
                S('popup_ad', $popup_ad, 600);
            }
        }
        $this->assign('popup_ad', $popup_ad);


        //首页推荐左侧广告
        $recommend_left_ads = S('recommend_left_ads');
        if ($recommend_left_ads === false) {
            $position = dao('touch_ad_position')->where(['position_model' => 'index_recommend_left'])->find();
            if ($position) {
                $recommend_left_ads = dao('touch_ad')->where(['position_id' => $position['position_id']])->find();
                S('recommend_left_ads', $recommend_left_ads, 600);
            }

        }
        $this->assign('recommend_left_ads', $recommend_left_ads);

        //首页推荐右侧广告
        $recommend_right_ads = S('recommend_right_ads');
        if ($recommend_right_ads === false) {
            $position = dao('touch_ad_position')->where(['position_model' => 'index_recommend_right'])->find();
            if ($position) {
                $recommend_right_ads = dao('touch_ad')->where(['position_id' => $position['position_id']])->limit(2)->select();
                S('recommend_right_ads', $recommend_right_ads, 600);
            }

        }
        $this->assign('recommend_right_ads', $recommend_right_ads);


        //获取所有楼层
        $floors = get_child_tree(0);
        foreach ($floors as $k => $floor) {
            $position = dao('touch_ad_position')->where(['position_model' => 'index_floor_'.$floor['id']])->find();
            if ($position) {
                $floor['lists'] = dao('touch_ad')->where(['position_id' => $position['position_id']])->select();
            }
            $floors[$k] = $floor;
        }


        $this->assign('floors', $floors);

        //公告
        $cat_id = 1001;
        $count  = get_article_count($cat_id, '');
        $artciles_list = get_cat_articles($cat_id, 1, $count);
        $this->assign('artciles_list', $artciles_list);

        /**
         * 微信分享
         */
        $pc_tempalate = dao('shop_config')->where(['code' => 'template', 'type' => 'hidden'])->getField('value');
        $share_data = [
            'title' => C('shop.shop_title'),
            'desc' => C('shop.shop_desc'),
            'link' => '',
            'img' => '/themes/' . $pc_tempalate . '/images/logo.gif',
        ];
        $this->assign('nav', 'index');
        $this->assign('share_data', $this->get_wechat_share_content($share_data));
        $this->assign('page_title', C('shop.shop_name'));
        $this->assign('description', C('shop.shop_desc'));
        $this->assign('keywords', C('shop.shop_keywords'));
        $this->display();
    }

    //欢迎页
    public function actionWelcome() {
        uaredirect(__PC__ . '/');

        if ($_SESSION['user_id']) {
            $this->redirect(url('index'));
        }

        /**
         * 首页Banner广告图
         */
        $home_banner_ads = S('home_banner_ads');
        if ($home_banner_ads === false) {
            $position = dao('touch_ad_position')->where(['position_model' => 'home_banner'])->find();
            if ($position) {
                $home_banner_ads = dao('touch_ad')->where(['position_id' => $position['position_id']])->select();
                S('home_banner_ads', $home_banner_ads, 600);
            }
        }
        $this->assign('home_banner_ads', $home_banner_ads);

        $this->assign('page_title', C('shop.shop_name'));
        $this->assign('description', C('shop.shop_desc'));
        $this->assign('keywords', C('shop.shop_keywords'));
        $this->display();
    }

    //注册提示
    public function actionTips() {
        $this->display();
    }

    /**
     * 头部APP广告位
     */
    public function actionAppNav()
    {
        $app = C('shop.wap_index_pro') ? 1 : 0;
        $this->response(['error' => 0, 'data' => $app]);
    }

    /**
     * 站内快讯
     */
    public function actionNotice()
    {
        $condition = [
            'is_open' => 1,
            'cat_id' => 12
        ];
        $list = $this->db->table('article')->field('article_id, title, author, add_time, file_url, open_type')
            ->where($condition)->order('article_type DESC, article_id DESC')->limit(5)->select();
        $res = [];
        foreach ($list as $key => $vo) {
            $res[$key]['text'] = $vo['title'];
            $res[$key]['url'] = build_uri('article', ['aid' => $vo['article_id']]);
        }
        $this->response(['error' => 0, 'data' => $res]);
    }

    /**
     * 返回商品列表
     * post: /index.php?m=admin&c=editor&a=goods
     * param:
     * return:
     */
    public function actionGoods()
    {
        $number = input('post.number', 10);
        $condition = [
            'intro' => input('post.type', '')
        ];
        $list = $this->getGoodsList($condition, $number);
        $res = [];
        $endtime = gmtime(); // time() + 7 * 24 * 3600;
        foreach ($list as $key => $vo) {
            $res[$key]['desc'] = $vo['name']; // 描述
            $res[$key]['sale'] = $vo["sales_volume"]; // 销量
            $res[$key]['stock'] = $vo['goods_number']; // 库存
            if ($vo['promote_price']) {
                $res[$key]['price'] = min($vo['promote_price'], $vo['shop_price']);
            } else {
                $res[$key]['price'] = $vo['shop_price'];
            }
            $res[$key]['marketPrice'] = $vo["market_price"]; // 市场价
            $res[$key]['img'] = $vo['goods_thumb']; // 图片地址
            $res[$key]['link'] = $vo['url']; // 图片链接
            $endtime = $vo['promote_end_date'] > $endtime ? $vo['promote_end_date'] : $endtime;
        }
        $this->response(['error' => 0, 'data' => $res, 'endtime' => date('Y-m-d H:i:s', $endtime)]);
    }

    public function actionSpa()
    {
        $this->display();
    }

    /**
     * 返回商品列表
     * @param string $param
     * @return array
     */
    private function getGoodsList($param = [], $size = 10)
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
            'size' => $size,
            C('VAR_AJAX_SUBMIT') => 1
        ];

        $data = array_merge($data, $param);
        $cache_id = md5(serialize($data));
        $list = S($cache_id);
        if ($list === false) {
            $url = url('category/index/products', $data, false, true);
            $res = Http::doGet($url);
            if ($res === false) {
                $res = file_get_contents($url);
            }
            if ($res) {
                $data = json_decode($res, 1);
                $list = empty($data['list']) ? false : $data['list'];
                S($cache_id, $list, 600);
            }
        }
        return $list;
    }
}
