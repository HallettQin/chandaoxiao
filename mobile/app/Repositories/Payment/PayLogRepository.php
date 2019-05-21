<?php

namespace App\Repositories\Payment;

use App\Models\PayLog;
use App\Contracts\Repositories\Payment\PayLogRepositoryInterface;

class PayLogRepository implements PayLogRepositoryInterface
{

    /**
     * 将支付LOG插入数据表
     *
     * @access  public
     * @param   integer $id 订单编号
     * @param   float $amount 订单金额
     * @param   integer $type 支付类型
     * @param   integer $is_paid 是否已支付
     *
     * @return  int
     */
    public function insert_pay_log($id, $amount, $type = PAY_SURPLUS, $is_paid = 0)
    {
        $payLog = new PayLog();
        $payLog->order_id = $id;
        $payLog->order_amount = $amount;
        $payLog->order_type = $type;
        $payLog->is_paid = $is_paid;

        $payLog->save();

        return $payLog->log_id;
    }
}
