<?php

namespace App\Repositories\ShopConfig;

use App\Models\ShopConfig;
use App\Models\OssConfigure;
use Illuminate\Support\Facades\Cache;
use App\Contracts\Repositories\ShopConfig\ShopConfigRepositoryInterface;

/**
 * Class ShopConfigRepository
 * @package App\Repositories\shopconfig
 */
class ShopConfigRepository implements ShopConfigRepositoryInterface
{

    /**
     * 查询商店配置
     * @return mixed
     */
    public function getShopConfig()
    {
        $shopConfig = Cache::get('shop_config');

        if (empty($shopConfig)) {
            $shopConfig = ShopConfig::get()
                ->toArray();

            Cache::put('shop_config', $shopConfig, 60);
        }

        return $shopConfig;
    }

    /**
     * 根据code获取配置
     * @param $code
     * @return mixed
     */
    public function getShopConfigByCode($code)
    {
        $shopConfig = $this->getShopConfig();
        foreach ($shopConfig as $v) {
            if ($v['code'] == $code) {
                return $v['value'];
            }
        }
    }

    public function getOssConfig()
    {
        return OssConfigure::where('is_use', 1)->first()->toArray();
    }
}
