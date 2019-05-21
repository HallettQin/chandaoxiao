<?php

namespace App\Modules\Console\Controllers;

use App\Libraries\Image;
use App\Modules\Admin\Controllers\EditorSellerController;

class ApiSellerController extends EditorSellerController
{
    public function __construct()
    {
        parent::__construct();
        $this->init_params();
    }

    /**
     * 编辑控制台
     */
    public function actionIndex()
    {
        $sql = 'SELECT id, type , title , pic  FROM ' . $GLOBALS['ecs']->table('touch_page_view') . ' WHERE ru_id = 0 AND default = 1 ';
        $view = $GLOBALS['db']->getRow($sql);
        return $view;
    }

    /**
     * 公告
     */
    public function actionArticle()
    {
        if (IS_POST) {
            $cid = input('cat_id', 0, 'intval');
            ['cat_id' => $id];
            $num = input('num', 0);
            if ($num == 0) {
                $limit = [];
            } else {
                $limit = $num;
            }
            if ($cid == 0) {
                $where = [];
            } else {
                $where = ['cat_id' => $cid];
            }
            $article_msg = dao('article')->field('article_id,title,add_time')->where($where)->limit($num)->select();
            foreach ($article_msg as $key => $value) {
                $article_msg[$key]['title'] = $value['title'];
                $article_msg[$key]['url'] = url('article/index/detail', ['id' => $value['article_id']]);
                $article_msg[$key]['date'] = local_date('Y-m-d H:i:s', $value['add_time']);
            }
            $this->response(['error' => 0, 'article_msg' => $article_msg, 'cat_id' => $cid]);
        }
    }


    /**
     * 商品列表
     *
     */
    public function actionProduct()
    {
        if (IS_POST) {
            $number = input('number', 10);
            $user_id = input('ruid', 0, 'intval');
            $type = input('type');
            $warehouse_id = $this->region_id;
            $area_id = $this->area_info['region_id'];
            $where = " g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 ";
            switch ($type) {
                case "new":
                    $where .= ($user_id > 0) ? " AND  g.store_new = '1' " : " AND  g.is_new = '1' ";
                    break;
                case "best":
                    $where .= ($user_id > 0) ? " AND  g.store_best = '1' " : " AND  g.is_best = '1' ";
                    break;
                case "hot":
                    $where .= ($user_id > 0) ? " AND  g.store_hot = '1' " : " AND  g.is_hot = '1' ";
                    break;
                case "promote":
                    $where .= " AND  g.is_promote = '1' ";
                    break;
                default:
                    $where .= " AND 1 ";
            }
            $shop_price = "wg.warehouse_price, wg.warehouse_promote_price, wag.region_price, wag.region_promote_price, g.model_price, g.model_attr, ";
            $leftJoin = " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
            $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
            if ($GLOBALS['_CFG']['open_area_goods'] == 1) {
                $leftJoin .= " left join " . $GLOBALS['ecs']->table('link_area_goods') . " as lag on g.goods_id = lag.goods_id ";
                $where .= " AND lag.region_id = '$area_id' ";
            }
            $sql = 'SELECT g.goods_id, g.user_id, g.goods_name, g.goods_number, ' . $shop_price . ' g.goods_name_style, g.comments_number,g.sales_volume,g.market_price, g.is_new, g.is_best, g.is_hot,g.model_attr,' .
                ' IF(g.model_price < 1, g.goods_number, IF(g.model_price < 2, wg.region_number, wag.region_number)) AS goods_number, ' .
                ' IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, g.model_price, ' .
                "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS shop_price, " .
                "IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)) as promote_price, g.goods_type, " .
                'g.promote_start_date, g.promote_end_date, g.is_promote, g.goods_brief,g.goods_brief,g.product_price,g.product_promote_price, g.goods_thumb , g.goods_img, g.cat_id ' .
                'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
                $leftJoin .
                'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
                "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
                "WHERE $where ORDER BY g.sort_order ASC , g.goods_id DESC LIMIT $number";
            $goods_list = $GLOBALS['db']->getAll($sql);
            //修改图片路径
            $time = gmtime();

            foreach ($goods_list as $key => $val) {
                $product[$key]['title'] = $val['goods_name'];
                $product[$key]['stock'] = $val['goods_number'];
                $product[$key]['sale'] = $val['sales_volume'];
                if ($val['promote_price'] > 0) {
                    $promote_price = bargain_price($val['promote_price'], $val['promote_start_date'], $val['promote_end_date']);
                } else {
                    $promote_price = 0;
                }


                $price_info = get_goods_one_attr_price($val, $warehouse_id, $area_id, $promote_price);
                $val = !empty($val) ? array_merge($val, $price_info) : $val;
                $promote_price = $val['promote_price'];

                $product[$key]['marketPrice'] = $val['market_price'];
                $product[$key]['img'] = get_image_path($val['goods_img']);
                $product[$key]['url'] = url('goods/index/index', ['id' => $val['goods_id'], 'u' => $_SESSION['user_id']]);
                if ($time > $val['promote_start_date'] && $time < $val['promote_end_date'] && $val['is_promote'] == 1 && $val['model_price'] == 1) {
                    $product[$key]['price'] = price_format($val['warehouse_promote_price']);
                } elseif ($time > $val['promote_start_date'] && $time < $val['promote_end_date'] && $val['is_promote'] == 1 && $val['model_price'] == 0) {
                    $product[$key]['price'] = price_format($val['promote_price']);
                } else {
                    $product[$key]['price'] = price_format($val['shop_price']);
                }
                if (empty($val['promote_start_date']) || empty($val['promote_end_date'])) {
                    $product[$key]['price'] = price_format($val['shop_price']);
                }
            }
            $this->response(['error' => 0, 'product' => $product, 'type' => $type]);
        }
    }

    /*
    相册或图片
    */

    public function actionThumb()
    {
        if (IS_POST) {
            $type = input('type');
            $ru_id = input('ruid', 0, 'intval');
            $album_id = input('album_id', 1, 'intval');
            $pageSize = input('pageSize', 10, 'intval');
            $currentPage = input('currentPage', 1, 'intval');
            if ($type == 'thumb') {
                $pic = dao('gallery_album')->field('*')->where(['ru_id' => $ru_id])->order('add_time DESC')->select();
                foreach ($pic as $key => $value) {
                    $thumb[$key] = ['album_id' => $value['album_id'], 'name' => $value['album_mame']];
                    $thumb[$key]['tree'] = dao('gallery_album')->field('album_id, album_mame')->where(['ru_id' => $ru_id, 'parent_album_id' => $value['album_id']])->order('add_time DESC')->select();
                }
                $this->response(['error' => 0, 'thumb' => $thumb, 'totalPage' => $currentPage]);
            } elseif ($type == 'img') {
                if ($currentPage == 1) {
                    $current = 0;
                } else {
                    $current = ($currentPage - 1) * $pageSize;
                }
                $img = dao('pic_album')->field('pic_id ,pic_name, pic_file')->where(['album_id' => $album_id, 'ru_id' => $ru_id])->order('add_time DESC')->limit($current, $pageSize)->select();
                foreach ($img as $key => $value) {
                    $img[$key]['pic_file'] = get_image_path($value['pic_file']);
                }
                $total = dao('pic_album')->field('pic_id , pic_file')->where(['album_id' => $album_id])->count();
                $this->response(['error' => 0, 'img' => $img, 'total' => $total, 'totalPage' => $currentPage]);
            } else {
                $this->response(['error' => 1, 'msg' => '类型错误']);
            }
        }
    }

    /*
    营销、活动、分类、文章页面超链接
    */

    public function actionUrl()
    {
        if (IS_POST) {
            $type = input('type');
            $time = gmtime();
            $pageSize = input('pageSize', 10, 'intval');
            $currentPage = input('currentPage', 1, 'intval');
            if ($currentPage == 1) {
                $current = 0;
            } else {
                $current = ($currentPage - 1) * $pageSize;
            }
            if ($type == 'category') {
                $url = dao('category')->field('cat_id , cat_name , parent_id')->where(['parent_id' => 0, 'is_show' => 1])->select();

                foreach ($url as $key => $value) {
                    $category = dao('category')->field('cat_id , cat_name , parent_id')->where(['parent_id' => $value['cat_id'], 'is_show' => 1])->select();

                    foreach ($category as $key2 => $value2) {
                        $category2 = dao('category')->field('cat_id , cat_name , parent_id')->where(['parent_id' => $value2['cat_id'], 'is_show' => 1])->select();

                        foreach ($category2 as $key3 => $value3) {
                            $category2[$key3]['url'] = url('category/index/products', ['id' => $value3['cat_id']]);
                        }
                        $category[$key2] = ['cat_id' => $value['cat_id'], 'cat_name' => $value2['cat_name'], 'url' => url('category/index/products', ['id' => $value2['cat_id']]), 'parent_id' => $value2['parent_id'], 'child_tree' => $category2, 'total' => count($category2)];
                    }
                    $url[$key] = ['cat_id' => $value['cat_id'], 'cat_name' => $value['cat_name'], 'url' => url('category/index/products', ['cat_id' => $value['cat_id']]), 'parent_id' => $value['parent_id'], 'child_tree' => $category, 'total' => count($category)];
                }
                $this->response(['error' => 0, 'url' => $url, 'total' => count($url)]);
            } else {
                $this->response(['error' => 1, 'msg' => '类型错误']);
            }
        }
    }

    /*
    *显示分类
    * post: /index.php?m=console&c=apiseller&a=category
    * param: cat_id
    * return: $arr
    */
    public function actionCategory()
    {
        $url = dao('category')->field('cat_id , cat_name , parent_id')->where(['parent_id' => 0, 'is_show' => 1])->select();
        foreach ($url as $key => $value) {
            $category = dao('category')->field('cat_id , cat_name , parent_id')->where(['parent_id' => $value['cat_id'], 'is_show' => 1])->select();
            foreach ($category as $key2 => $value2) {
                $category2 = dao('category')->field('cat_id , cat_name , parent_id')->where(['parent_id' => $value2['cat_id'], 'is_show' => 1])->select();
                $category[$key2] = ['cat_id' => $value2['cat_id'], 'cat_name' => $value2['cat_name'], 'parent_id' => $value2['parent_id'], 'child_tree' => $category2];
            }
            $url[$key] = ['cat_id' => $value['cat_id'], 'cat_name' => $value['cat_name'], 'parent_id' => $value['parent_id'], 'child_tree' => $category];
        }
        $this->response(['error' => 0, 'url' => $url]);
    }

    /*
    *显示品牌
    * post: /index.php?m=console&c=apiseller&a=brand
    * return: $arr
    */
    public function actionBrand()
    {
        $brand = dao('brand')->field('brand_id, brand_name')->where(['is_show' => 1])->select();
        $this->response(['error' => 0, 'brand' => $brand]);
    }

    /*
    展厅街
    */
    public function actionStore()
    {
        if (IS_POST) {
            $number = input('number', 10);
            $childrenNumber = input('childrenNumber', 3, 'intval');
            $sql = "SELECT ms.shop_id,ms.user_id, ms.rz_shopName, ss.logo_thumb, ss.street_thumb " .
                " FROM " . $GLOBALS['ecs']->table('merchants_shop_information') . " AS ms " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('seller_shopinfo') . " AS ss " .
                " ON ms.user_id = ss.ru_id " .
                " limit  0, $number";
            $store = $GLOBALS['db']->getAll($sql);
            foreach ($store as $key => $value) {
                $sql = "SELECT goods_name, goods_thumb " .
                    " FROM " . $GLOBALS['ecs']->table('goods') .
                    " WHERE user_id = '" . $value['user_id'] . "' " .
                    " limit 0, $childrenNumber";
                $goods = $GLOBALS['db']->getAll($sql);
                foreach ($goods as $a => $val) {
                    $goods[$a]['goods_thumb'] = get_image_path($val['goods_thumb']);
                }
                $store[$key]['goods'] = $goods;
                $store[$key]['total'] = count($goods);
                $store[$key]['logo_thumb'] = get_image_path(ltrim($value['logo_thumb'], "../"));
                $store[$key]['street_thumb'] = get_image_path($value['street_thumb']);
            }
            $this->response(['error' => 0, 'store' => $store, 'page' => $currentPage, 'total' => count($store)]);
        }
    }

    /*
    *展厅街详情
    * post: /index.php?m=console&c=apiseller&a=store_in
    */

    public function actionStoreIn()
    {
        if (IS_POST) {
            $ru_id = input('ruid');
            $time = gmtime();
            $sql = "SELECT ms.shop_id, ms.user_id, ms.rz_shopName, ss.logo_thumb, ss.street_thumb " .
                " FROM " . $GLOBALS['ecs']->table('merchants_shop_information') . " AS ms " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('seller_shopinfo') . " AS ss " .
                " ON ms.user_id = ss.ru_id " .
                " WHERE ms.user_id = $ru_id ";
            $store = $GLOBALS['db']->getAll($sql);
            foreach ($store as $key => $value) {
                $sql = "SELECT goods_name, goods_thumb, shop_price " .
                    " FROM " . $GLOBALS['ecs']->table('goods') .
                    " WHERE user_id = '" . $value['user_id'] . "' ";
                $goods = $GLOBALS['db']->getAll($sql);
                $new = dao('goods')->where(['sotre_new' => 1, 'user_id' => $value['user_id']])->count();
                $promote = dao('goods')->where(['is_promote' => 1, 'user_id' => $value['user_id']])->count();
                $store[$key]['total'] = count($goods);
                $store[$key]['new'] = $new;
                $store[$key]['promote'] = $promote;
                $store[$key]['logo_thumb'] = get_image_path(ltrim($value['logo_thumb'], "../"));
                $store[$key]['street_thumb'] = get_image_path($value['street_thumb']);

                $sql = "SELECT count(user_id) as a FROM {pre}collect_store WHERE ru_id = " . $value['user_id'];
                $follow = $this->db->getOne($sql);
                $store[$key]['count_gaze'] = empty($follow) ? 0 : 1;
                $sql = "SELECT count(ru_id) as a FROM {pre}collect_store WHERE ru_id = " . $value['user_id'];
                $like_num = $this->db->getOne($sql);
                $store[$key]['like_num'] = empty($like_num) ? 0 : $like_num;
            }
            $this->response(['store' => $store]);
        }
    }

    /*
    *展厅街详情底部
    * post: /index.php?m=console&c=apiseller&a=store_down
    */

    public function actionStoreDown()
    {
        if (IS_POST) {
            $ru_id = input('ruid');
            $time = gmtime();
            $sql = "SELECT ms.shop_id, ms.user_id, ms.rz_shopName,ss.kf_qq, ss.kf_ww " .
                " FROM " . $GLOBALS['ecs']->table('merchants_shop_information') . " AS ms " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('seller_shopinfo') . " AS ss " .
                " ON ms.user_id = ss.ru_id  " .
                " WHERE ms.user_id = $ru_id  ";
            $store = $GLOBALS['db']->getAll($sql);
            foreach ($store as $key => $value) {
                $store[$key]['shop_category'] = get_user_store_category($value['user_id']);
                $store[$key]['shop_about'] = url('store/index/shop_about', ['ru_id' => $value['user_id']]);
            }
            $this->response(['store' => $store]);
        }
    }

    /*
    *红包
    * post: /index.php?m=console&c=apiseller&a=store_bonus
    */

    public function actionStoreBonus()
    {
        if (IS_POST) {
            $ru_id = input('ruid');
            $sql = "SELECT * FROM {pre}coupons WHERE (`cou_type` = 3 OR `cou_type` = 4 ) AND `cou_end_time` > $time AND (( instr(`cou_ok_user`, $_SESSION[user_rank]) ) or (`cou_ok_user`=0)) AND review_status = 3 AND ru_id='" . $ru_id . "' ";
            $info = $this->db->getAll($sql);
            foreach ($info as $key => $val) {
                $info[$key]['cou_man'] = intval($val['cou_man']);
                $info[$key]['cou_money'] = intval($val['cou_money']);
            }
            $bonus = $info;
            $this->response(['store' => $bonus]);
        }
    }

    /**
     * 关注展厅
     * post: /index.php?m=console&c=apiseller&a=add_collect
     */

    public function actionAddCollect()
    {
        $shopid = input('shopid', 0, 'intval');
        if (!empty($shopid) && $_SESSION['user_id'] > 0) {
            $status = $this->db->getRow('SELECT user_id, rec_id FROM {pre}collect_store WHERE ru_id = ' . $shopid . " AND user_id=" . $_SESSION['user_id']);
            if (count($status) > 0) {
                $this->db->query('DELETE FROM {pre}collect_store WHERE rec_id = ' . $status['rec_id']);
                die(json_encode(['error' => 2, 'msg' => L('cancel_attention')]));
            } else {
                $this->db->query("INSERT INTO {pre}collect_store (user_id, ru_id, add_time, is_attention) VALUES (" . $_SESSION['user_id'] . ",'$shopid'," . time() . ",1)");
                die(json_encode(['error' => 1, 'msg' => L('attentioned')]));
            }
        } else {
            die(json_encode(['error' => 0, 'msg' => L('please_login')]));
        }
    }

    /**
     * 显示页面
     * param: $id 页面ID
     * post: /index.php?m=console&c=apiseller&a=view
     * param: $type 页面类型
     * return:
     */

    public function actionView()
    {
        if (IS_POST) {
            $default = input('default');
            $id = input('id');
            $type = input('type');
            $topic_id = input('topic_id');
            $ru_id = input('ruid', 0, 'intval');
            $number = input('number', 10);
            $page_id = input('page_id', 0, 'intval');
            if ($id) {
                $view = dao('touch_page_view')->field('type, thumb_pic, data, default')->where(['id' => $id])->order('update_at DESC')->find();
            } elseif ($topic_id) {
                $view = dao('touch_page_view')->field('type, thumb_pic, data, default')->where(['id' => $topic_id, 'type' => 'topic'])->find();
            } elseif ($default < 2) {
                if ($number == 0) {
                    $view = dao('touch_page_view')->field('id , type ,  title ,  pic ,thumb_pic , default ')->where(['default' => $find, 'ru_id' => $ru_id, 'page_id' => $page_id])->order('update_at DESC')->select();
                } elseif ($number > 0) {
                    $view = dao('touch_page_view')->field('id , type ,  title ,  pic ,thumb_pic , default ')->where(['default' => $default, 'ru_id' => $ru_id, 'page_id' => $page_id])->order('update_at DESC')->limit($number)->find();
                }
            } elseif ($default == 3) {
                if ($number == 0) {
                    $view = dao('touch_page_view')->field('id , type ,  title ,  pic ,thumb_pic , default ')->where(['ru_id' => $ru_id])->order('update_at DESC')->select();
                } elseif ($number > 0) {
                    $view = dao('touch_page_view')->field('id , type ,  title ,  pic ,thumb_pic , default ')->where(['ru_id' => $ru_id])->order('update_at DESC')->limit($number)->select();
                }
            } else {
                $view = dao('touch_page_view')->field('id , type ,  title , data,  pic ,thumb_pic , default ')->where(['ru_id' => $ru_id, 'type' => $type])->order('update_at DESC')->select();
            }
            $this->response(['error' => 0, 'view' => $view]);
        }
    }

    /**
     * 保存模块配置
     * post: /index.php?m=console&c=api&a=default
     * param $type  页面类型
     * param $id   页面ID
     * param $ru_id   商家ID
     * return int $id   返回默认页面ID
     */
    public function actionDefault()
    {
        if (IS_POST) {
            $type = input('type');
            $id = input('id');
            $ru_id = input('ruid');
            if ($ru_id) {
                $index = dao('touch_page_view')->field('id')->where(['ru_id' => $ru_id, 'type' => $type])->find();
                $this->response(['index' => $index]);
            } else {
                $index = dao('touch_page_view')->field('id')->where(['ru_id' => '0', 'type' => 'index'])->find();
                $this->response(['index' => $index]);
            }
        }
    }

    /**
     * 保存模块配置
     * post: /index.php?m=console&c=api&a=save
     * param:
     * return:
     */
    public function actionSave()
    {
        $admin_id = $_SESSION['seller_id'];
        $authority = dao('admin_user')->where(['user_id' => $admin_id])->count();
        if ($authority == 1) {
            if (IS_POST) {
                $id = dao('admin_user')->where(['user_id' => $admin_id])->getField('ru_id');
                $time = gmtime();
                if ($id) {
                    $num = dao('touch_page_view')->field('id, data , pic')->where(['ru_id' => $id])->select();
                    if (count($num) == 1) {
                        $pic = !empty($_POST['pic']) ? $_POST['pic'] : $num[0]['pic'];
                        $keep = ['data' => !empty($_POST['data']) ? $_POST['data'] : $num[0]['data'], 'pic' => $pic, 'update_at' => $time,];
                        dao('touch_page_view')->data($keep)->where(['id' => $num[0]['id']])->save();
                        $page = dao('touch_page_view')->field('id ,ru_id,  type ,title, pic , default')->where(['id' => $id])->find();
                        $this->response(['error' => 0, 'page' => $page, 'msg' => '修改完成']);
                    } else {
                        $pic = !empty($_POST['pic']) ? $_POST['pic'] : '';
                        $keep = ['ru_id' => $id, 'type' => 'store', 'title' => '展厅首页', 'data' => !empty($_POST['data']) ? $_POST['data'] : '', 'pic' => $pic, 'update_at' => $time, 'default' => 1,];
                        dao('touch_page_view')->data($keep)->add();
                        $page = dao('touch_page_view')->field('id ,ru_id,  type ,title, pic , default')->where(['id' => $id])->find();
                        $this->response(['error' => 0, 'page' => $page, 'msg' => '修改完成']);
                    }
                } else {
                    $this->response(['error' => 1, 'msg' => '提交错误']);
                }
            }
        } else {
            $this->response(['error' => 1, 'msg' => '没有权限']);
        }
    }

    /**
     * 创建商家相册
     * post: /index.php?m=console&c=apiseller&a=make_gallery
     * param: $ru_id  商家ID
     * return:
     */

    public function actionMakeGallery()
    {
        if (IS_POST) {
            $ru_id = input('ruid');
            $time = gmtime();
            $store = $ru_id + 100;
            $keep = [
                'album_id' => $store,
                'ru_id' => $ru_id,
                'album_mame' => '商家展厅相册',
                'sort_order' => 50,
                'add_time' => $time,
            ];
            dao('gallery_album')->data($keep)->add();
            $this->response(['error' => 0, 'album_id' => $store, 'msg' => '保存完成']);
        }
    }

    /**
     * 上传图片
     * post: /index.php?m=console&c=api&a=upload
     * param: $album_id  数组ID
     * param: $ru_id  商家ID
     * return:
     */
    public function actionUpload()
    {
        $admin_id = $_SESSION['seller_id'];
        $authority = dao('admin_user')->where(['user_id' => $admin_id])->count();
        if ($authority == 1) {
            $album_id = $_POST['album_id'];
            $ru_id = input('ruid', 0, 'intval');

            $thumb_path = dirname(ROOT_PATH) . '/data/gallery_album/thumb_img/';
            $goods_path = dirname(ROOT_PATH) . '/data/gallery_album/goods_img/';
            if ($_FILES['file']) {
                $res = $this->upload('data/gallery_album/original_img/');
                if ($res['error'] == 0) {
                    $path = dirname(ROOT_PATH) . '/' . $res['url']['file']['url'];
                    $image = new Image();

                    $img_thumb = $image->make_thumb($path, C('shop.thumb_width'), C('shop.thumb_height'), $thumb_path);
                    $goods_img = $image->make_thumb($path, C('shop.image_width'), C('shop.image_height'), $goods_path);
                    // 建立相册
                    if (!empty($album_id)) {
                        // 保存图片到数据库
                        $data = [
                            'pic_name' => $res['url']['file']['savename'],
                            'album_id' => $album_id,
                            'pic_file' => $res['url']['file']['url'],
                            'pic_thumb' => !empty($img_thumb) ? $img_thumb : '',
                            'pic_size' => $res['url']['file']['size'],
                            'pic_spec' => '',
                            'ru_id' => $ru_id,
                            'add_time' => gmtime(),
                        ];
                        $this->db->table('pic_album')->add($data);

                        $this->response(['error' => 0, 'pic' => $data['pic_file']]);
                    }
                } else {
                    // return false;
                    $this->response(['error' => 1, 'msg' => $res['message']]);
                }
            }
        } else {
            $this->response(['error' => 1, 'msg' => '没有权限']);
        }
    }

    /**
     * 删除模块配置
     * post: /index.php?m=console&c=api&a=del
     * param:
     * return:
     */
    public function actionDel()
    {
        $admin_id = $_SESSION['seller_id'];
        $authority = dao('admin_user')->where(['user_id' => $admin_id])->count();
        if ($authority == 1) {
            if (IS_POST) {
                if (isset($_POST['id'])) {
                    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('touch_page_view') .
                        " WHERE  id = '" . $_POST['id'] . "'";
                    if ($GLOBALS['db']->query($sql) == 1) {
                        $this->response(['error' => 0, 'msg' => '删除完成']);
                    } else {
                        $this->response(['error' => 1, 'msg' => '操作错误']);
                    }
                    return $GLOBALS['db']->query($sql);
                }
            }
        } else {
            $this->response(['error' => 1, 'msg' => '没有权限']);
        }
    }

    /*
     *搜索
     * post: /index.php?m=console&c=apiseller&a=search
     */
    public function actionSearch()
    {
        if (IS_POST) {
            $kwords = I('request.keyword');
            $cat = input('cat_id', 0, 'intval');
            $brand = input('brand_id', 0, 'intval');
            $user_id = dao('admin_user')->where(['user_id' => $_SESSION['seller_id']])->getField('ru_id');
            $warehouse_id = $this->region_id;
            $area_id = $this->area_info['region_id'];
            $pageSize = input('pageSize', 10, 'intval');
            $currentPage = input('currentPage', 1, 'intval');
            if ($currentPage == 1) {
                $current = 0;
            } else {
                $current = ($currentPage - 1) * $pageSize;
            }
            $keywords .= ' AND ';

            $val = mysql_like_quote(trim($kwords));
            $keywords .= "(goods_name LIKE '%$val%' OR goods_sn LIKE '%$val%' OR keywords LIKE '%$val%')";

            $where = "g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 AND g.user_id = $user_id ";
            if ($cat > 0) {
                $where .= " AND g.cat_id = $cat ";
            }
            if ($brand > 0) {
                $where .= " AND g.brand_id = $brand ";
            }
            if ($keywords) {
                $where .= " AND (( 1 " . $keywords . " ) ) ";
            }
            $wherenum = "  LIMIT $current , $pageSize ";
            $leftJoin = '';

            $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
            $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
            $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_shop_information') . " as msi on msi.user_id = g.user_id ";

            $sql = 'SELECT g.goods_id, g.user_id, g.goods_name, g.goods_name_style, g.comments_number,g.sales_volume,g.market_price, g.is_new, g.is_best, g.is_hot, g.promote_start_date, g.promote_end_date, g.is_promote, g.goods_brief, g.goods_thumb , g.goods_img, msi.self_run ' .
                'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
                $leftJoin .
                'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
                "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
                "WHERE $where $wherenum ";
            $total_query = $GLOBALS['db']->query($sql);
            $sql = 'SELECT g.goods_id ' .
                'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
                $leftJoin .
                'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
                "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
                "WHERE $where ";
            $number = $GLOBALS['db']->query($sql);

            foreach ($total_query as $key => $val) {
                $total_query[$key]['goods_thumb'] = get_image_path($val['goods_thumb']);
                $total_query[$key]['goods_img'] = get_image_path($val['goods_img']);
            }
            exit(json_encode(['list' => $total_query, 'total' => count($number)]));
        }
    }

    /*
     *商品列表
     * post: /index.php?m=console&c=apiseller&a=goods_list
     */
    public function actionGoodsList()
    {
        if (IS_POST) {
            $goods_id = input('goods_id');
            $pageSize = input('pageSize', 10, 'intval');
            $user_id = input('user_id', 10, 'intval');
            $currentPage = input('currentPage', 1, 'intval');
            if ($currentPage == 1) {
                $current = 0;
            } else {
                $current = ($currentPage - 1) * $pageSize;
            }
            $wherenum = "  LIMIT $current , $pageSize ";
            $sql = "SELECT * from ". $GLOBALS['ecs']->table('goods') .
                    " where user_id = " .$user_id. " AND goods_id in (".$goods_id.") $wherenum ";
            $goodslist = $GLOBALS['db']->getAll($sql);
            foreach ($goodslist as $key => $val) {
                $goodslist[$key]['goods_img'] = get_image_path($val['goods_img']);
            }
            $this->response([ 'goodslist' => $goodslist]);
        }
    }


    /**
     * 初始化参数
     */
    private function init_params()
    {
        #需要查询的IP start
        if (!isset($_COOKIE['province'])) {
            $area_array = get_ip_area_name();

            if ($area_array['county_level'] == 2) {
                $date = ['region_id', 'parent_id', 'region_name'];
                $where = "region_name = '" . $area_array['area_name'] . "' AND region_type = 2";
                $city_info = get_table_date('region', $where, $date, 1);

                $date = ['region_id', 'region_name'];
                $where = "region_id = '" . $city_info[0]['parent_id'] . "'";
                $province_info = get_table_date('region', $where, $date);

                $where = "parent_id = '" . $city_info[0]['region_id'] . "' order by region_id asc limit 0, 1";
                $district_info = get_table_date('region', $where, $date, 1);
            } elseif ($area_array['county_level'] == 1) {
                $area_name = $area_array['area_name'];

                $date = ['region_id', 'region_name'];
                $where = "region_name = '$area_name'";
                $province_info = get_table_date('region', $where, $date);

                $where = "parent_id = '" . $province_info['region_id'] . "' order by region_id asc limit 0, 1";
                $city_info = get_table_date('region', $where, $date, 1);

                $where = "parent_id = '" . $city_info[0]['region_id'] . "' order by region_id asc limit 0, 1";
                $district_info = get_table_date('region', $where, $date, 1);
            }
        }
        #需要查询的IP end
        $order_area = get_user_order_area($this->user_id);
        $user_area = get_user_area_reg($this->user_id); //2014-02-25

        if ($order_area['province'] && $this->user_id > 0) {
            $this->province_id = $order_area['province'];
            $this->city_id = $order_area['city'];
            $this->district_id = $order_area['district'];
        } else {
            //省
            if ($user_area['province'] > 0) {
                $this->province_id = $user_area['province'];
                cookie('province', $user_area['province']);
                $this->region_id = get_province_id_warehouse($this->province_id);
            } else {
                $sql = "select region_name from " . $this->ecs->table('region_warehouse') . " where regionId = '" . $province_info['region_id'] . "'";
                $warehouse_name = $this->db->getOne($sql);

                $this->province_id = $province_info['region_id'];
                $cangku_name = $warehouse_name;
                $this->region_id = get_warehouse_name_id(0, $cangku_name);
            }
            //市
            if ($user_area['city'] > 0) {
                $this->city_id = $user_area['city'];
                cookie('city', $user_area['city']);
            } else {
                $this->city_id = $city_info[0]['region_id'];
            }
            //区
            if ($user_area['district'] > 0) {
                $this->district_id = $user_area['district'];
                cookie('district', $user_area['district']);
            } else {
                $this->district_id = $district_info[0]['region_id'];
            }
        }

        $this->province_id = isset($_COOKIE['province']) ? $_COOKIE['province'] : $this->province_id;

        $child_num = get_region_child_num($this->province_id);
        if ($child_num > 0) {
            $this->city_id = isset($_COOKIE['city']) ? $_COOKIE['city'] : $this->city_id;
        } else {
            $this->city_id = '';
        }

        $child_num = get_region_child_num($this->city_id);
        if ($child_num > 0) {
            $this->district_id = isset($_COOKIE['district']) ? $_COOKIE['district'] : $this->district_id;
        } else {
            $this->district_id = '';
        }

        $this->region_id = !isset($_COOKIE['region_id']) ? $this->region_id : $_COOKIE['region_id'];
        $goods_warehouse = get_warehouse_goods_region($this->province_id); //查询用户选择的配送地址所属仓库
        if ($goods_warehouse) {
            $this->regionId = $goods_warehouse['region_id'];
            if ($_COOKIE['region_id'] && $_COOKIE['regionid']) {
                $gw = 0;
            } else {
                $gw = 1;
            }
        }
        if ($gw) {
            $this->region_id = $this->regionId;
            cookie('area_region', $this->region_id);
        }

        cookie('goodsId', $this->goods_id);

        $sellerInfo = get_seller_info_area();
        if (empty($this->province_id)) {
            $this->province_id = $sellerInfo['province'];
            $this->city_id = $sellerInfo['city'];
            $this->district_id = 0;

            cookie('province', $this->province_id);
            cookie('city', $this->city_id);
            cookie('district', $this->district_id);

            $goods_warehouse = get_warehouse_goods_region($this->province_id);
            $this->region_id = $goods_warehouse['region_id'];
        }
        //ecmoban模板堂 --zhuo end 仓库
        $this->area_info = get_area_info($this->province_id);
    }
}
