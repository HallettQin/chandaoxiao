<?php

namespace App\Repositories\Goods;

use App\Models\VolumePrice;
use App\Contracts\Repositories\Goods\VolumePriceRepositoryInterface;

class VolumePriceRepository implements VolumePriceRepositoryInterface
{
    public function allVolumes($goods_id, $price_type)
    {
        $res = VolumePrice::where('goods_id', $goods_id)
            ->where('price_type', $price_type)
            ->orderBy('volume_number')
            ->get()
            ->toArray();

        return $res;
    }
}
