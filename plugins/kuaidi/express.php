<?php

/**
 *  Express.class.php           快递查询类
 *
 * @copyright			widuu
 * @license			http://www.widuu.com
 * @lastmodify			2013-6-19
 */
class Express {

    private $expressname = array(); //封装了快递名称

    function __construct() {
        $this->expressname = $this->expressname();
    }

    /*
     * 采集网页内容的方法
     */

    private function getcontent($url) {
        if (function_exists("file_get_contents")) {
            $file_contents = file_get_contents($url);
        } else {
            $ch = curl_init();
            $timeout = 5;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $file_contents = curl_exec($ch);
            curl_close($ch);
        }
        return $file_contents;
    }

    /*
     * 获取对应名称和对应传值的方法
     */

    private function expressname() {
        
        $exp_http = $this->exp_http();
        $site_dir = $exp_http . "www.kuaidi100.com/";
        $result = $this->getcontent($site_dir);
        preg_match_all("/data\-code\=\"(?P<name>\w+)\"\>\<span\>(?P<title>.*)\<\/span>/iU", $result, $data);
        $name = array();
        foreach ($data['title'] as $k => $v) {
            $name[$v] = $data['name'][$k];
        }
        return $name;
    }

    /*
     * 解析object成数组的方法
     * @param $json 输入的object数组
     * return $data 数组
     */

    private function json_array($json) {
        if ($json) {
            foreach ((array) $json as $k => $v) {
                $data[$k] = !is_string($v) ? $this->json_array($v) : $v;
            }
            return $data;
        }
    }

    /*
     * 返回$data array      快递数组
     * @param $name         快递名称
     * 支持输入的快递名称如下
     * (申通-EMS-顺丰-圆通-中通-如风达-韵达-天天-汇通-全峰-德邦-宅急送-安信达-包裹平邮-邦送物流
     * DHL快递-大田物流-德邦物流-EMS国内-EMS国际-E邮宝-凡客配送-国通快递-挂号信-共速达-国际小包
     * 汇通快递-华宇物流-汇强快递-佳吉快运-佳怡物流-加拿大邮政-快捷速递-龙邦速递-联邦快递-联昊通
     * 能达速递-如风达-瑞典邮政-全一快递-全峰快递-全日通-申通快递-顺丰快递-速尔快递-TNT快递-天天快递
     * 天地华宇-UPS快递-新邦物流-新蛋物流-香港邮政-圆通快递-韵达快递-邮政包裹-优速快递-中通快递)
     * 中铁快运-宅急送-中邮物流
     * @param $order        快递的单号
     * $data['ischeck'] ==1   已经签收
     * $data['data']        快递实时查询的状态 array
     */

    public function getorder($name, $order, $kuaidi100key) {
        
        $keywords = '';
        $keywords .= $name;
        
        $exp_http = $this->exp_http();
        $site_dir = $exp_http . "www.kuaidi100.com/query?type={$keywords}&postid={$order}";
        $site_dir_api = "http://api.kuaidi100.com/api?id={$kuaidi100key}&com={$keywords}&nu={$order}&show=0&muti=1&order=desc";

        $result = $this->getcontent($site_dir);
        $result = json_decode($result);

        if ($result->status == 201) {
            $result = $this->getcontent($site_dir_api);
            $result = json_decode($result);
        }
        $data = $this->json_array($result);

        return $data;
    }
    
    /**
     * 获得 DSC 当前环境的 HTTP 协议方式
     *
     * @access  public
     *
     * @return  void
     */
    public function exp_http()
    {
        if (isset($_SERVER['HTTPS'])) 
        {
            return (isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) != 'off')) ? 'https://' : 'http://';
        } 
        else if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) 
        {
            $proto_http = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']);

            if (strpos($proto_http, 'https') !== false) {
                $proto_http = 'https://';
            } else {
                $proto_http = 'http://';
            }

            return $proto_http;
        } 
        else 
        {
            return 'http://';
        }
    }
}

$express_name = trim($_POST['com']);
$express_no = trim($_POST['nu']);

include_once("../../data/kuaidi_key.php");
include_once("kuaidi_config.php");
$express = new Express();
$result = $express->getorder($postcom, $express_no, $kuaidi100key);
$express_info = '<table style="border:1px; solid #90BFFF; width:100%;border-collapse:collapse;border-spacing:0; float:left;">';
if ($result['status'] == 1 || $result['status'] == 200) {
    $data = array_reverse($result['data']);
    foreach ($data as $key => $val) {
        $express_info .= '<tr style="height:20px;">';
        $express_info .= "<td style='text-align:right;width:140px;'>$val[time]</td>";
        $express_info .= "<td>&nbsp;&nbsp;|&nbsp;&nbsp;</td>";
        $express_info .= "<td style='text-align:left;'>$val[context]</td>";
        $express_info .= '</tr>';
    }
    $express_info .= '</table>';
} else {
    
    $exp_http = $express->exp_http();
    $site_dir = $exp_http ."www.kuaidi100.com/chaxun?com={$postcom}&nu={$express_no}"; 
    $express_info = '<span style="font-size:14px;">很抱歉，暂时无法查询此订单信息！请尝试跳转到网页查询</span>&nbsp;&nbsp;&nbsp;<a href="' . $site_dir . '" target="_blank"><span style="color:red;">点击跳转</span></a>'; //liu 改
}

echo $express_info;
exit;
?>