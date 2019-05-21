<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/29
 * Time: 2:06
 */
namespace App\Modules\Rank\Controllers;

use app\models\Groupbuy;
use App\Modules\Base\Controllers\FrontendController;
use Illuminate\Database\Eloquent\Model;

class IndexController extends FrontendController
{
    public function __construct()
    {
        parent::__construct();
        L(require(LANG_PATH . C('shop.lang') . '/user.php'));
    }

    //每日新品
    public function actionNew()
    {
        $this->purchasers_priv();
        //模式
        $mode = isset($_GET['mode'])?trim($_GET['mode']):'groupbuy';
        $this->assign('mode', $mode);

        //获取所有一级分类
        $category = S('category0');
        if (!$category) {
            $category = get_child_tree(0);
            S('category0', $category);
        }

        $this->assign('category', $category);

        $this->assign('page_title', '每日上新');
        $this->display();
    }

    //ajax 获取每日新品
    public function actionAjaxnew() {
        if (IS_AJAX || true) {
            $cat_id = I('id', 0, 'intval');
            if (empty($cat_id)) {
                exit(json_encode(['code' => 1, 'message' => '请选择分类']));
            }

            $mode = I('mode');
            if (!in_array($mode, ['groupbuy', 'presale', 'wholesale', 'sample'])) {
                exit(json_encode(['code' => 1, 'message' => '请选择商品模式']));
            }

            $goods = get_new10($cat_id, $mode);
            exit(json_encode(['lists' => $goods]));
        }
    }

    //销售排行
    public function actionTopsale() {
        $this->purchasers_priv();

        //模式
        $mode = isset($_GET['mode'])?trim($_GET['mode']):'groupbuy';
        $this->assign('mode', $mode);

        //获取所有一级分类
        $category = S('category0');
        if (!$category) {
            $category = get_child_tree(0);
            S('category0', $category);
        }

        $this->assign('category', $category);

        $this->assign('page_title', '每日单品零售商交易量排行');
        $this->display();
    }

    //ajax 获取销售排行
    public function actionAjaxsale()
    {
        if (IS_AJAX) {
            $cat_id = I('id', 0, 'intval');
            if (empty($cat_id)) {
                exit(json_encode(['code' => 1, 'message' => '请选择分类']));
            }

            $mode = I('mode');
            if (!in_array($mode, ['groupbuy', 'presale', 'wholesale', 'sample'])) {
                exit(json_encode(['code' => 1, 'message' => '请选择商品模式']));
            }

            $goods = get_top10($cat_id, $mode);
            foreach ($goods as $k => $good) {
                $goods[$k]['url'] = build_uri($mode, ['gbid' => $good['act_id'],'id'=>$good['act_id']]);;
            }

            exit(json_encode(['lists' => $goods]));
        }
    }

            //优质商家
    public function actionTopstore() {
         //获取所有一级分类
        $category = S('category0');
        if (!$category) {
            $category = get_child_tree(0);
            S('category0', $category);
        }

        $this->assign('category', $category);

        $this->assign('page_title', '甄选优质生产制造商品牌排行');
        $this->display();
    }

    //ajax 获取销售排行
    public function actionAjaxstore()
    {
        if (IS_AJAX) {
            $cat_id = I('id', 0, 'intval');
            if (empty($cat_id)) {
                exit(json_encode(['code' => 1, 'message' => '请选择分类']));
            }

            $store_shop_list = get_store_list_top10($cat_id, 'sales_volume');
            exit(json_encode(['shop_list' => $store_shop_list['shop_list']]));
        }
    }
}
