<?php

namespace App\Repositories\Payment;

use App\Models\Payment;
use App\Contracts\Repositories\Payment\PaymentRepositoryInterface;

class PaymentRepository implements PaymentRepositoryInterface
{

    /**
     * 获取支付方式列表
     * @return mixed
     */
    public function paymentList()
    {
        $payment = Payment::select('pay_id', 'pay_code', 'pay_name', 'pay_fee', 'pay_desc', 'pay_config', 'is_cod')
            ->where('enabled', 1)
            ->get()
            ->toArray();

        return $payment;
    }
}
