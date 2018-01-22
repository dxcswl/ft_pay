<?php
// +----------------------------------------------------------------------
// | Future [ 追寻最初的梦想 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2010-2016 http://www.21514.com All rights reserved.
// +----------------------------------------------------------------------
// | Author:封疆 <dxcswl@163.com> QQ:84111804
// +----------------------------------------------------------------------
namespace ft_pay;

class Ipt
{
    /*
     *  去苹果服务器二次验证代码
     */
    public static function getIapReceiptData($receipt, $isSandbox = false) {
        $iap_err_msg = [
            21000 => 'App Store不能读取你提供的JSON对象',
            21002 => 'receipt-data域的数据有问题',
            21003 => 'receipt无法通过验证',
            21004 => '提供的shared secret不匹配你账号中的shared secret',
            21005 => 'receipt服务器当前不可用',
            21006 => 'receipt合法，但是订阅已过期。服务器接收到这个状态码时，receipt数据仍然会解码并一起发送',
            21007 => 'receipt是Sandbox receipt，但却发送至生产系统的验证服务',
            21008 => 'receipt是生产receipt，但却发送至Sandbox环境的验证服务',
        ];


        if ($isSandbox) {
            $endpoint = 'https://sandbox.itunes.apple.com/verifyReceipt';//沙箱地址
        } else {
            $endpoint = 'https://buy.itunes.apple.com/verifyReceipt';//真实运营地址
        }
        $postData = json_encode(
            array('receipt-data' => $receipt)
        );
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);  //这两行一定要加，不加会报SSL 错误
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        //$errmsg   = curl_error($ch);
        curl_close($ch);

        if ($errno != 0) {//curl请求有错误
            return [
                'errNo' => 1,
                'errMsg' => '请求超时，请稍后重试',
            ];
        }else{
            $data = json_decode($response, true);
            if (!is_array($data)) {
                return [
                    'errNo' => 2,
                    'errMsg' => '苹果返回数据有误，请稍后重试',
                ];
            }

            if(isset($iap_err_msg[$data['status']]) && $iap_err_msg[$data['status']]){
                return [
                    'errNo' => 4,
                    'errMsg' => '购买失败!code: '.$iap_err_msg[$data['status']],
                ];
            }

            //判断购买时候成功
            if (!isset($data['status']) || $data['status'] != 0) {
                return [
                    'errNo' => 3,
                    'errMsg' => '购买失败',
                ];
            }
            //返回产品的信息
            $order = $data['receipt'];
            $order['errNo'] = 0;
            return $order;
        }
    }
}