<?php
namespace App\Repositories\Order;

use App\Models\OrderInvoice;
use App\Contracts\Repositories\Order\OrderInvoiceRepositoryInterface;

class OrderInvoiceRepository implements OrderInvoiceRepositoryInterface
{
    /**
     * 查找中用户的订单发票
     * @param $orderId
     * @return array
     */
    public function find($userid)
    {
        $order = OrderInvoice::where('user_id', $userid)
            ->first();
        if ($order == null) {
            return [];
        }
        return $order;
    }

    /**
     * 更新订单中用户的发票
     * @param int $id
     * @param array $args
     * @return array
     */
    public function updateInvoice($id, array $args)
    {
        $model = OrderInvoice::where('user_id', $args['user_id'])
            ->where('invoice_id', $id)
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
     * 添加增值发票
     * @param $args
     * @return int
     */
    public function addInvoice($args)
    {
        $model = new OrderInvoice();
        foreach ($args as $k => $v) {
            $model->$k = $v;
        }
        $model->save();
        return $model->invoice_id;
    }
}
