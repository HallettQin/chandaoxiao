<?php

namespace App\Services;

use App\Repositories\Shop\ShopRepository;
use App\Contracts\Services\ShopServiceInterface;

/**
 * Class ShopService
 * @package App\Services
 */
class ShopService implements ShopServiceInterface
{
    /**
     * @var ShopRepository
     */
    private $shopRepository;

    /**
     * ShopService constructor.
     * @param ShopRepository $shopRepository
     */
    public function __construct(ShopRepository $shopRepository)
    {
        $this->shopRepository = $shopRepository;
    }

    /**
     * 获取店铺名
     * @param $ruId
     * @return string
     */
    public function getShopName($ruId)
    {
        //店铺名称
        $shopInfo = $this->shopRepository->findBY('ru_id', $ruId);

        if (count($shopInfo) > 0) {
            $shopInfo = $shopInfo[0];
            if ($shopInfo['shopname_audit'] == 1) {
                if ($ruId > 0) {
                    $shopName = $shopInfo['brandName'] . $shopInfo['shopNameSuffix'];
                } else {
                    $shopName = $shopInfo['shop_name'];
                }
            } else {
                $shopName = $shopInfo['rz_shopName'];
            }
        } else {
            $shopName = "";
        }
        return $shopName;
    }

    public function create()
    {
        // TODO: Implement create() method.
    }

    public function get()
    {
        // TODO: Implement get() method.
    }

    public function update()
    {
        // TODO: Implement update() method.
    }

    public function getStatus()
    {
        // TODO: Implement getStatus() method.
    }

    public function search()
    {
        // TODO: Implement search() method.
    }

    public function createAddress()
    {
        // TODO: Implement createAddress() method.
    }

    public function getAddress()
    {
        // TODO: Implement getAddress() method.
    }

    public function updateAddress()
    {
        // TODO: Implement updateAddress() method.
    }

    public function deleteAddress()
    {
        // TODO: Implement deleteAddress() method.
    }

    public function searchAddress()
    {
        // TODO: Implement searchAddress() method.
    }

    public function createStore()
    {
        // TODO: Implement createStore() method.
    }

    public function getStore()
    {
        // TODO: Implement getStore() method.
    }

    public function updateStore()
    {
        // TODO: Implement updateStore() method.
    }

    public function deleteStore()
    {
        // TODO: Implement deleteStore() method.
    }

    public function searchStore()
    {
        // TODO: Implement searchStore() method.
    }

    public function storeSetting()
    {
        // TODO: Implement storeSetting() method.
    }

    public function getStoreGoods()
    {
        // TODO: Implement getStoreGoods() method.
    }

    public function updateStoreGoods()
    {
        // TODO: Implement updateStoreGoods() method.
    }

    public function uploadMaterials()
    {
        // TODO: Implement uploadMaterials() method.
    }

}
