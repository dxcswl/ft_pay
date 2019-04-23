<?php
// +----------------------------------------------------------------------
// | Future [ 追寻最初的梦想 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2010-2019 www.21514.com All rights reserved.
// +----------------------------------------------------------------------
// | Author:Future <dxcswl@163.com> QQ:84111804
// +----------------------------------------------------------------------

namespace ft_pay;
use ft_pay\org\Ipt;
use ft_pay\org\Weixin;
use ft_pay\org\Ali;

class Pay
{
    /*
     *  去苹果服务器二次验证代码
     *
     * @param $receipt 苹果返回数据
     * @param bool $isSandbox false 正式环境  ture 沙箱
     * @return array|mixed
     */
    public static function getIapNotify($receipt = '', $isSandbox = false) {
        if(isset($receipt) && !$receipt) {
            return ['code' => 0, 'msg' => '参数必须存在', 'data' => []];
        }
        $ipt = new Ipt($receipt, $isSandbox = false);
        return $ipt->getIapNotify();

    }


    /*
    * 微信支付app发起处理
     $config = [
        'body' => '充值',
        'notify_url' => '/api/pay/set_wechat_notify',
        'return_url' => '',
        'appid' => '', //绑定支付的APPID
        'mchid' => '', //商户号
        'key' => '', //商户支付密钥
        'appsecret' => '', //公众帐号secert（仅JSAPI支付的时候需要配置， 登录公众平台，进入开发者中心可设置），
        //下列参数可以使不用
        'SSLCERT_PATH' => '../cert/apiclient_cert.pem', //设置商户证书路径
        'SSLKEY_PATH' => '../cert/apiclient_key.pem', //证书路径,注意应该填写绝对路径（仅退款、撤销订单时需要，可登录商户平台下载)
        'CURL_PROXY_HOST' => '0.0.0.0', //只有需要代理的时候才设置，不需要代理，请设置为0.0.0.0和0
        'CURL_PROXY_PORT' => 0, //代理端口
        'REPORT_LEVENL' => 1, //接口调用上报等级，默认紧错误上报  0.关闭上报; 1.仅错误出错上报; 2.全量上报
    ]
     */

    public static function getWeixinApp($config, $money=0 , $pay_id=0){
        if(isset($money) && !$money) {
            return ['code' => 0, 'msg' => '参数必须存在：money', 'data' => []];
        }
        if(isset($pay_id) && !$pay_id) {
            return ['code' => 0, 'msg' => '参数必须存在：pay_id', 'data' => []];
        }

        $weixin = new Weixin();
        $total_price = (int) ($money*100);
        return $weixin->getWeixin($config, $total_price , $pay_id,'APP');
    }

    public static function getWeixinWeb($config, $money = 0 , $pay_id = 0){
        if(isset($money) && !$money) {
            return ['code' => 0, 'msg' => '参数必须存在：money', 'data' => []];
        }
        if(isset($pay_id) && !$pay_id) {
            return ['code' => 0, 'msg' => '参数必须存在：pay_id', 'data' => []];
        }

        $weixin = new Weixin();
        $total_price = (int) ($money*100);
        return $weixin->getWeixin($config, $total_price , $pay_id,'JSAPI');
    }

    /*
    * 微信异步返回
    */

    public static function getWeixinNotify($config) {
        $weixin = new Weixin();
        $ret = $weixin->getNotify($config);
        if($ret['code'] != 1) {
            return $ret;
        }
        if(isset($ret['data']['out_trade_no']) && $ret['data']['out_trade_no']) {
            return ['code' => 1, 'msg' => '订单处理成功', 'data' => ['pay_id'=>$ret['data']['out_trade_no']]];
        }else{
            return ['code' => 0, 'msg' => '没有获取订单号', 'data' => []];
        }
    }

    /*
    * 微信支付app发起处理
     $config = ['partner' => '',//合作者身份ID
        'key' => '',
        'sign_type' => 'RSA',//MD5 签名方式 不需修改
        'private_key_path' => '',//商户的私钥（后缀是.pen）文件相对路径
        'ali_public_key_path' => '',//支付宝公钥（后缀是.pen）文件相对路径
        'input_charset' => 'utf-8',//字符编码格式 目前支持 gbk 或 utf-8
        'cacert' => getcwd() . '\\cacert.pem',//ca证书路径地址，用于curl中ssl校验
        'transport' => 'http',//访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
    ]
     */

    public static function getAliApp($config, $money = 0, $pay_id = 0){
        if(isset($money) && !$money) {
            return ['code' => 0, 'msg' => '参数必须存在：money', 'data' => []];
        }
        if(isset($pay_id) && !$pay_id) {
            return ['code' => 0, 'msg' => '参数必须存在：pay_id', 'data' => []];
        }

        $ali = new Ali();
        return $ali->getAli($config, $money , $pay_id,'APP');
    }

    /*
     * 处理完成之后需要返回   echo   "fail"; echo   "success";
     */
    public static function getAliNotify($config, $money , $pay_id){
        $ali = new Ali();
        $ret = $ali->getNotify($config);
        if($ret['code'] != 1) {
            return $ret;
        }
        if(isset($ret['data']['out_trade_no']) && $ret['data']['out_trade_no']) {
            return ['code' => 1, 'msg' => '订单处理成功', 'data' => ['pay_id'=>$ret['data']['out_trade_no']]];
        }else{
            return ['code' => 0, 'msg' => '没有获取订单号', 'data' => []];
        }
    }

}
?>