<?php

use App\Http\Proxy\ShippingProxy;

/**
 * 天天快递插件
 *
 */
class tiantian
{
    /**
     * 配置信息参数
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
            @$fee = $this->configure['base_fee'];
            $this->configure['fee_compute_mode'] = !empty($this->configure['fee_compute_mode']) ? $this->configure['fee_compute_mode'] : 'by_weight';

            if ($this->configure['fee_compute_mode'] == 'by_number') {
                $fee = $goods_number * $this->configure['item_fee'];
            } else {
                if ($goods_weight > 1) {
                    $fee += (ceil(($goods_weight - 1))) * $this->configure['step_fee'];
                }
            }

            return $fee;
        }
    }


    /**
     * 查询快递状态
     *
     * @access  public
     * @param   string $invoice_sn 发货单号
     * @return  string  查询窗口的链接地址
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
        return $proxy->getExpress('tiantian', $invoice_sn);
    }
}
