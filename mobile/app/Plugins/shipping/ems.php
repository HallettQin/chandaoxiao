<?php

use App\Http\Proxy\ShippingProxy;

/**
 * 邮政快递包裹费用计算方式
 * ====================================================================================
 * 500g及500g以内                             20元
 * -------------------------------------------------------------------------------------
 * 续重每500克或其零数                        6元/9元/15元(按分区不同收费不同，具体分区方式，请寄件人拨打电话或到当地邮局营业窗口咨询，客服电话11185。)
 * -------------------------------------------------------------------------------------
 *
 */
class ems
{
    /**
     * 配置信息
     */
    public $configure;

    /**
     * 构造函数
     *
     * @param: $configure[array]    配送方式的参数的数组
     *
     * @return null
     */
    public function __construct($cfg = [])
    {
        foreach ($cfg as $key => $val) {
            $this->configure[$val['name']] = $val['value'];
        }
    }

    /**
     * 计算订单的配送费用的函数
     *
     * @param   float $goods_weight 商品重量
     * @param   float $goods_amount 商品金额
     * @param   float $goods_number 商品件数
     * @return  decimal
     */
    public function calculate($goods_weight, $goods_amount, $goods_number)
    {
        if ($this->configure['free_money'] > 0 && $goods_amount >= $this->configure['free_money']) {
            return 0;
        } else {
            $fee = $this->configure['base_fee'];
            $this->configure['fee_compute_mode'] = !empty($this->configure['fee_compute_mode']) ? $this->configure['fee_compute_mode'] : 'by_weight';

            if ($this->configure['fee_compute_mode'] == 'by_number') {
                $fee = $goods_number * $this->configure['item_fee'];
            } else {
                if ($goods_weight > 0.5) {
                    $fee += (ceil(($goods_weight - 0.5) / 0.5)) * $this->configure['step_fee'];
                }
            }
            return $fee;
        }
    }

    /**
     * 查询发货状态
     *
     * @access  public
     * @param   string $invoice_sn 发货单号
     * @return  string
     */
    public function query($invoice_sn)
    {
        $str = '<a class="btn-default-new tracking-btn" href="https://m.kuaidi100.com/result.jsp?nu=' . $invoice_sn . '">订单跟踪</a>';
        return $str;
    }

    /**
     * 查询快递API
     *
     * @param string $invoice_sn
     * @return mixed
     */
    public function api($invoice_sn = '')
    {
        $proxy = new ShippingProxy();
        return $proxy->getExpress('ems', $invoice_sn);
    }
}
