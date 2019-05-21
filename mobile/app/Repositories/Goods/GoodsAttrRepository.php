<?php

namespace App\Repositories\Goods;

use App\Models\Goods;
use App\Models\GoodsAttr;
use App\Repositories\ShopConfig\ShopConfigRepository;
use App\Contracts\Repositories\Goods\GoodsAttrRepositoryInterface;

class GoodsAttrRepository implements GoodsAttrRepositoryInterface
{
    private $shopConfigRepository;

    public function __construct(ShopConfigRepository $shopConfigRepository)
    {
        $this->shopConfigRepository = $shopConfigRepository;
    }

    /**
     * 查询商品属性
     * @param $goods_id
     */
    public function goodsAttr($goods_id)
    {
        $res = GoodsAttr::from('goods_attr as g')
            ->select('*')
            ->join('attribute as a', 'a.attr_id', '=', 'g.attr_id')
            ->where('g.goods_id', $goods_id)
            ->orderby('a.sort_order')
            ->orderby('g.attr_sort', 'ASC')
            ->get();

        if ($res == null) {
            return [];
        }

        return $res->toArray();
    }

    /**
     * 商品属性组
     * 单个
     */
    public function attrGroup($goods_id)
    {
        $model = GoodsAttr::from('goods_type as gt')
            ->select('attr_group')
            ->join('goods as g', 'gt.cat_id', '=', 'g.goods_type')
            ->where('g.goods_id', $goods_id)
            ->first();

        if ($model == null) {
            return [];
        }

        return $model->attr_group;
    }

    /**
     * 商品属性  名称查询
     * @param $attrId  商品属性ID
     * @return mixed
     */
    public function getAttrNameById($attrId)
    {
        $goodsAttr = GoodsAttr::select('attribute.attr_name', 'goods_attr.attr_value');

        if (is_array($attrId)) {
            $goodsAttr = $goodsAttr->wherein('goods_attr_id', $attrId)
                ->leftjoin('attribute', 'attribute.attr_id', '=', 'goods_attr.attr_id')
                ->get();
        } elseif (is_int($attrId)) {
            $goodsAttr = $goodsAttr->where('goods_attr_id', $attrId)
                ->leftjoin('attribute', 'attribute.attr_id', '=', 'goods_attr.attr_id')
                ->first();
        }

        if ($goodsAttr == null) {
            return [];
        }
        return $goodsAttr->toArray();
    }
}
