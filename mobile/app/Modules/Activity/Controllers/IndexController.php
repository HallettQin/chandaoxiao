<?php

namespace App\Modules\Activity\Controllers;

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
        /* 取得用户等级 */
        $user_rank_list = [];
        $user_rank_list[0] = L('not_user');
        $sql = "SELECT rank_id, rank_name FROM " . $this->ecs->table('user_rank');
        $res = $this->db->query($sql);
        foreach ($res as $row) {
            $user_rank_list[$row['rank_id']] = $row['rank_name'];
        }
        $sql = "SELECT * FROM " . $this->ecs->table('favourable_activity') . " WHERE review_status = 3 " . " ORDER BY `sort_order` ASC,`end_time` DESC";
        $res = $this->db->query($sql);
        $list = [];
        foreach ($res as $row) {
            $row['activity_thumb'] = get_image_path($row['activity_thumb']);
            $row['start_time'] = local_date('Y-m-d H:i', $row['start_time']);
            $row['end_time'] = local_date('Y-m-d H:i', $row['end_time']);
            $row['url'] = url('activity/index/detail', ['id' => $row['act_id']]);

            //优惠方式
            $row['actType'] = $row['act_type']; //优惠方式
            switch ($row['act_type']) {
                case 0:
                    $row['act_type'] = L('fat_goods');
                    $row['activity_name'] = '消费满' . $row['min_amount']. '享受赠品';
                    break;
                case 1:
                    $row['act_type'] = L('fat_price');
                    $row['act_type_ext'] .= L('unit_yuan');
                    $row['activity_name'] = '消费满' . $row['min_amount']. '现金减免' . $row['act_type_ext'];
                    break;
                case 2:
                    $row['act_type'] = L('fat_discount');
                    $row['act_type_ext'] .= "%";
                    $row['activity_name'] = '消费满' . $row['min_amount']. '享受折扣' . $row['act_type_ext'] / 10 . '折';
                    break;
            }
            $list[$row['actType']]['activity_list'][] = $row;
        }

        $this->assign('list', $list);
        $this->assign('page_title', L('act_index'));
        $this->display();
    }

    /**
     * 优惠活动 - 活动商品列表
     */
    public function actionDetail()
    {
        $id = I('id', 0, 'intval');
        if (empty($id)) {
            $this->redirect('/');
        }
        /* 取得用户等级 */
        $user_rank_list = [];
        $user_rank_list[0] = L('not_user');
        $sql = "SELECT rank_id, rank_name FROM " . $this->ecs->table('user_rank');
        $res = $this->db->query($sql);
        foreach ($res as $row) {
            $user_rank_list[$row['rank_id']] = $row['rank_name'];
        }
        $row = $this->db->getRow("SELECT * FROM " . $this->ecs->table('favourable_activity') . " WHERE review_status = 3 and act_id = " . $id);
        //享受优惠会员等级
        $user_rank = explode(',', $row['user_rank']);
        $row['user_rank'] = [];
        foreach ($user_rank as $val) {
            if (isset($user_rank_list[$val])) {
                $row['user_rank'][] = $user_rank_list[$val];
            }
        }
        $row['start_time'] = local_date('Y-m-d H:i', $row['start_time']);
        $row['end_time'] = local_date('Y-m-d H:i', $row['end_time']);
        $row['activity_thumb'] = get_image_path($row['activity_thumb']);

        if ($row['userFav_type']) {
            $row['shop_name'] = L('shop_name'); //商家名称
        } else {
            $row['shop_name'] = get_shop_name($row['user_id'], 1); //店铺名称;
            $row['shop_url'] = url('store/index/shop_info', ['id' => $row['user_id']]);
        }

        $row['act_range_type'] = $row['act_range']; //优惠范围
        //优惠范围类型、内容
        if ($row['act_range'] != FAR_ALL && !empty($row['act_range_ext'])) {
            if ($row['act_range'] == FAR_CATEGORY) {
                $row['act_range'] = L('far_category');
                $sql = "SELECT cat_id AS id, cat_name AS name FROM " . $this->ecs->table('category') .
                    " WHERE cat_id " . db_create_in($row['act_range_ext']);
            } elseif ($row['act_range'] == FAR_BRAND) {
                $row['act_range'] = L('far_brand');
                $sql = "SELECT brand_id AS id, brand_name AS name FROM " . $this->ecs->table('brand') .
                    " WHERE brand_id " . db_create_in($row['act_range_ext']);
            } else {
                $row['act_range'] = L('far_goods');
                $sql = "SELECT goods_id AS id, goods_name AS name FROM " . $this->ecs->table('goods') .
                    " WHERE goods_id " . db_create_in($row['act_range_ext']);
            }
            $act_range_ext = $this->db->getAll($sql);
            $row['act_range_ext'] = $act_range_ext;
        } else {
            $row['act_range'] = L('far_all');
        }

        //优惠方式
        $row['actType'] = $row['act_type']; //优惠方式

        switch ($row['act_type']) {
            case 0:
                $row['act_type'] = L('fat_goods');
                $row['gift'] = unserialize($row['gift']);
                if (is_array($row['gift'])) {
                    foreach ($row['gift'] as $k => $v) {
                        $goods_thumb = $this->db->getOne("SELECT goods_thumb FROM " . $this->ecs->table('goods') . " WHERE goods_id = '" . $v['id'] . "'");
                        $row['gift'][$k]['thumb'] = get_image_path($goods_thumb);
                        $row['gift'][$k]['price'] = price_format($v['price'], false);
                        $row['gift'][$k]['url'] = build_uri('goods', ['gid' => $v['id'], 'u' => $_SESSION['user_id']]);
                    }
                }
                $row['activity_name'] = '消费满' . $row['min_amount']. '享受赠品';
                break;
            case 1:
                $row['act_type'] = L('fat_price');
                $row['act_type_ext'] .= L('unit_yuan');
                $row['activity_name'] = '消费满' . $row['min_amount']. '现金减免' . $row['act_type_ext'];
                $row['gift'] = [];

                break;
            case 2:
                $row['act_type'] = L('fat_discount');
                $row['act_type_ext'] .= "%";
                $row['activity_name'] = '消费满' . $row['min_amount']. '享受折扣' . $row['act_type_ext'] / 10 . '折';
                $row['gift'] = [];
                break;
        }

        // 微信JSSDK分享
        $share_data = [
            'title' => $row['act_name'],
            'desc' => $row['activity_name'],
            'link' => '',
            'img' => $row['activity_thumb'],
        ];
        $this->assign('share_data', $this->get_wechat_share_content($share_data));

        $this->assign('info', $row);
        $this->assign('page_title', $row['act_name']);
        $this->display();
    }


    public function actionGoods()
    {
        if (IS_AJAX) {
            $id = I('get.id', 0, 'intval');
            $page = I('post.page', 1, 'intval');
            $size = I('post.size', 4, 'intval');
            if (!empty($id)) {
                $province_id = isset($_COOKIE['province']) ? $_COOKIE['province'] : 0;
                $area_info = get_area_info($province_id);
                $area_id = $area_info['region_id'];

                $where = "regionId = '$province_id'";
                $date = ['parent_id'];
                $region_id = get_table_date('region_warehouse', $where, $date, 2);
                if (isset($_COOKIE['region_id']) && !empty($_COOKIE['region_id'])) {
                    $region_id = $_COOKIE['region_id'];
                }
                $row = $this->db->getRow("SELECT * FROM " . $GLOBALS['ecs']->table('favourable_activity') . " WHERE review_status = 3 and act_id = " . $id);
                //优惠范围类型、内容
                $goods_list = [];
                $cat_rang = '';
                $brand_rang = '';
                $goods_rang = '';
                if ($row['act_range'] != FAR_ALL && !empty($row['act_range_ext'])) {
                    if ($row['act_range'] == FAR_CATEGORY) {
                        $cat_str = '';
                        $cat_rows = explode(',', $row['act_range_ext']);
                        if ($cat_rows) {
                            foreach ($cat_rows as $v) {
                                $cat_children = array_unique(array_merge([$v], array_keys(cat_list($v, 0))));
                                if ($cat_children) {
                                    //只有1级分类，不需要去重
                                    $cat_str .= implode(',', $cat_children) . ',';
                                }
                            }
                        }
                        if ($cat_str) {
                            $cat_str = substr($cat_str, 0, -1);
                        }
                        $cat_rang = $cat_str;
                    } elseif ($row['act_range'] == FAR_BRAND) {
                        $brand_rang = $row['act_range_ext'];
                    } else {
                        $goods_rang = $row['act_range_ext'];
                    }
                }
                $goods_list = get_activity_goods($cat_rang, $brand_rang, $goods_rang, $region_id, $area_id, $page, $size);

                exit(json_encode(['list' => $goods_list['list'], 'totalPage' => $goods_list['totalpage']]));
            }
        }
    }
}
