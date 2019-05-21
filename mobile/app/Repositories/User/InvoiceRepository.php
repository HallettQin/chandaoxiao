<?php

namespace App\Repositories\User;

use App\Models\UsersVatInvoicesInfo;
use App\Contracts\Repositories\User\InvoiceRepositoryInterface;

class InvoiceRepository implements InvoiceRepositoryInterface
{
    /**
     * 添加增值发票
     * @param $args
     * @return int
     */
    public function addInvoice($args)
    {
        $model = new UsersVatInvoicesInfo();
        foreach ($args as $k => $v) {
            $model->$k = $v;
        }
        $model->save();
        return $model->id;
    }

    /**
     * 编辑增值发票
     * @param int $id
     * @param array $args
     * @return array
     */
    public function updateInvoice($id, array $args)
    {
        $model = UsersVatInvoicesInfo::where('user_id', $args['user_id'])
            ->where('id', $id)
            ->first();

        if ($model === null) {
            return [];
        }

        foreach ($args as $k => $v) {
            $model->$k = $v;
        }
        return $model->save();
    }

    /**
     * 删除增值发票
     * @param $id
     * @param $uid
     */
    public function deleteInvoice($id, $uid)
    {
        return UsersVatInvoicesInfo::where('user_id', $uid)
            ->where('id', $id)
            ->delete();
    }

    /**
     * 增值发票详情
     * @param $uid
     */
    public function find($uid)
    {
        $invoice = UsersVatInvoicesInfo::where('user_id', $uid)
            ->first();
        return $invoice;
    }
}
