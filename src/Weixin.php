<?php
// +----------------------------------------------------------------------
// | Future [ 追寻最初的梦想 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2010-2016 http://www.21514.com All rights reserved.
// +----------------------------------------------------------------------
// | Author:封疆 <dxcswl@163.com> QQ:84111804
// +----------------------------------------------------------------------
namespace ft_pay;

class Weixin
{
    /*
     * 微信支付app发起处理
     *
     */

    public static function getWeixinApp($config,$total_price,$order_id,$pay_id){

        $notify = new \NativePay();
        //方式二
        $input = new \ft_pay\WxPayUnifiedOrder();
        $input->SetBody($config['body']);
        //$input->SetAttach($order_id);
        $input->SetOut_trade_no($order_id);
        $input->SetTotal_fee($total_price);
        //$input->SetTime_start(date("YmdHis"));
        //$input->SetTime_expire(date("YmdHis", time() + 600));
        //$input->SetGoods_tag("在线支付");
        $input->SetNotify_url($config['notify_url']);
        $input->SetTrade_type("APP");
        //$input->SetProduct_id($order_id);
        $result = $notify->GetPayApp($input);
        if(isset($result['appid']) and isset($result['nonce_str']) and isset($result['mch_id']) and isset($result['prepay_id']) and isset($result['sign'])){
            $data['nonce_str'] = $result['nonce_str'];
            $data['prepay_id'] = $result['prepay_id'];
            $data['sign'] = $result['sign'];
            $data['appid'] = $result['appid'];
            $data['partnerid'] = $result['mch_id'];
            $data['timestamp'] = time();
            $data['spbill_create_ip'] = '171.113.60.93';
            return ['code' => 1, 'msg' => 'OK', 'data' => $data];
        }else{
            return ['code' => 99, 'msg' => '请求错误', 'data' => []];
        }
    }

}