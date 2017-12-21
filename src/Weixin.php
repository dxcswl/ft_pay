<?php
// +----------------------------------------------------------------------
// | Future [ 追寻最初的梦想 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2010-2016 http://www.21514.com All rights reserved.
// +----------------------------------------------------------------------
// | Author:封疆 <dxcswl@163.com> QQ:84111804
// +----------------------------------------------------------------------
namespace ft_pay;

class Weixin {

    public $wxpay_config = [];

    public static function getWeixinNotify() {
        require_once 'org\weixin\WxPay.Notify.php';
        $WxPayNotify = new \ft_pay\org\weixin\WxPayNotify();
        $WxPayNotify->Handle(false);
        $ret = $WxPayNotify->GetReturn_code();
        if($ret != "FAIL") {
            $order_id = $WxPayNotify->getData('out_trade_no');
            return ['code' => 1, 'msg' => '成功', 'data' => $order_id];
        } else {
            return ['code' => 99, 'msg' => '回调授权报错: ' . $WxPayNotify->GetReturn_msg(), 'data' => $_POST];
        }
    }

    /*
     * 微信支付app发起处理
     *
     */

    public static function getWeixinApp($config, $total_price, $order_id, $pay_id) {
        $result_code = self::getWeixinUnifiedOrder($config, $total_price, $order_id, $pay_id);
        if($result_code['code'] == 0){
            return $result_code;
        }else{
            $result = $result_code['data'];
        }
        if(isset($result['appid']) and isset($result['nonce_str']) and isset($result['mch_id']) and isset($result['prepay_id']) and isset($result['sign'])) {
            $data['appid'] = $result['appid'];
            $data['partnerid'] = $result['mch_id'];
            $data['prepayid'] = $result['prepay_id'];
            $data['package'] = 'Sign=WXPay';
            $data['noncestr'] = $result['nonce_str'];
            $data['timestamp'] = time();
            $data['sign'] = self::MakeSign($data, $config['key']);
            $data['sign_original'] = $result['sign'];
            return ['code' => 1, 'msg' => 'OK', 'data' => $data];
        } else {
            if(isset($result['return_msg'])) {
                $result['msg'] = $result['return_msg'];
            }
            return ['code' => 99, 'msg' => $result['msg'], 'data' => []];
        }
    }

    private static function getWeixinUnifiedOrder($config, $total_price, $order_id, $pay_id, $trade_type = 'APP') {
        $ret = ['code' => 99, 'msg' => '未知', 'data' => []];
        //检测必填参数
        if(!$pay_id) {
            return ['code' => 0, 'msg' => '缺少统一支付接口必填参数out_trade_no', 'data' => []];
        } else if(!$config['body']) {
            return ['code' => 0, 'msg' => '缺少统一支付接口必填参数body', 'data' => []];
        } else if(!$total_price) {
            return ['code' => 0, 'msg' => '缺少统一支付接口必填参数total_fee', 'data' => []];
        } elseif($trade_type == "JSAPI" && !$config['openid']) {
            return ['code' => 0, 'msg' => '统一支付接口中，缺少必填参数openid！trade_type为JSAPI时，openid为必填参数', 'data' => []];
        } elseif($trade_type == "NATIVE" && !$config['product_id']) {
            return ['code' => 0, 'msg' => '统一支付接口中，缺少必填参数product_id！trade_type为JSAPI时，product_id为必填参数！', 'data' => []];
        } else {
            $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
            $data = [
                'appid' => $config['appid'],
                'mch_id' => $config['mchid'],
                'nonce_str' => self::getNonceStr(),
                'body' => $config['body'],
                'out_trade_no' => $pay_id,
                'total_fee' => $total_price,
                'spbill_create_ip' => $_SERVER['REMOTE_ADDR'],
                'notify_url' => $config['notify_url'],
                'trade_type' => $trade_type,
            ];
            $data['sign'] = self::MakeSign($data, $config['key']);
            $xml = self::ToXml($data);
            if($xml == false){
                return ['code' => 0, 'msg' => '统一支付(发送)xml数据异常', 'data' => []];
            }else{
                $response = self::postXmlCurl($xml, $url, false, 6);
                $result = self::FromXml($response);
                if($result == false){
                    return ['code' => 0, 'msg' => '统一支付(获取)xml数据异常', 'data' => []];
                }else{
                    $response = self::postXmlCurl($xml, $url, false, 6);
                    //验证签名
                    $check_sign = self::check_sign($response,$config['key']);
                    if($check_sign['code'] == 1){
                        $result = $check_sign['data'];
                        if(!$result){
                            return ['code' => 0, 'msg' => '统一支付(获取)数组数据异常', 'data' => $result];
                        }else{
                            return ['code' => 1, 'msg' => '签名成功', 'data' => $result];
                        }
                    }else{
                        return $check_sign;
                    }
                }
            }
            //self::reportCostTime($url, self::getMillisecond(), $result, $config);//上报请求花费时间
        }
        return $ret;
    }

    /**
     *
     * 产生随机字符串，不长于32位
     * @param int $length
     * @return 产生的随机字符串
     */
    private static function getNonceStr($length = 32) {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }


    /**
     * 生成签名
     * @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
     */
    public static function MakeSign($values, $KEY) {
        //签名步骤一：按字典序排序参数
        ksort($values);
        $string = self::ToUrlParams($values);
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=" . $KEY;
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }


    /**
     * 格式化参数格式化成url参数
     */
    public static function ToUrlParams($values) {
        $buff = "";
        foreach($values as $k => $v) {
            if($k != "sign" && $v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    /**
     * 输出xml字符
     * @throws \Exception
     **/
    public static function ToXml($values) {
        if(!is_array($values) || count($values) <= 0) {
            return false;
        }

        $xml = "<xml>";
        foreach($values as $key => $val) {
            if(is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     * 获取毫秒级别的时间戳
     */
    private static function getMillisecond() {
        //获取毫秒的时间戳
        $time = explode(" ", microtime());
        $time = $time[1] . ($time[0] * 1000);
        $time2 = explode(".", $time);
        $time = $time2[0];
        return $time;
    }

    /**
     * 以post方式提交xml到对应的接口url
     *
     * @param string $xml 需要post的xml数据
     * @param string $url url
     * @param bool $useCert 是否需要证书，默认不需要
     * @param int $second url执行超时时间，默认30s
     * @throws \Exception
     */
    private static function postXmlCurl($xml, $url, $useCert = false, $second = 30) {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);

        $wxpay_config = config('base.wx_pay');
        //如果有配置代理这里就设置代理
        if($wxpay_config['CURL_PROXY_HOST'] != "0.0.0.0" && $wxpay_config['CURL_PROXY_PORT'] != 0) {
            curl_setopt($ch, CURLOPT_PROXY, WxPayConfig::CURL_PROXY_HOST);
            curl_setopt($ch, CURLOPT_PROXYPORT, WxPayConfig::CURL_PROXY_PORT);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);//严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, false);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if($useCert == true) {
            //设置证书
            //使用证书：cert 与 key 分别属于两个.pem文件
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLCERT, WxPayConfig::SSLCERT_PATH);
            curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLKEY, WxPayConfig::SSLKEY_PATH);
        }
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            //throw new \Exception("curl出错，错误码:$error");
            return '<xml> <return_code>error</return_code><return_msg>curl出错，错误码:' . $error . '</return_msg></xml>';
        }
    }

    /**
     * 将xml转为array
     * @param string $xml
     * @throws \Exception
     */
    public static function FromXml($xml) {
        if(!$xml) {
            return false;
        }
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }


    /**
     *
     * 上报数据， 上报的时候将屏蔽所有异常流程
     * @param string $usrl
     * @param int $startTimeStamp
     * @param array $data
     */
    private static function reportCostTime($url, $startTimeStamp, $data, $config) {
        //如果不需要上报数据
        if($config['REPORT_LEVENL'] == 0) {
            return;
        }
        //如果仅失败上报
        if($config['REPORT_LEVENL'] == 1 && array_key_exists("return_code", $data) && $data["return_code"] == "SUCCESS" && array_key_exists("result_code", $data) && $data["result_code"] == "SUCCESS") {
            return;
        }

        //上报逻辑
        $endTimeStamp = self::getMillisecond();
        $objInput = new WxPayReport();
        $objInput->SetInterface_url($url);
        $objInput->SetExecute_time_($endTimeStamp - $startTimeStamp);
        //返回状态码
        if(array_key_exists("return_code", $data)) {
            $objInput->SetReturn_code($data["return_code"]);
        }
        //返回信息
        if(array_key_exists("return_msg", $data)) {
            $objInput->SetReturn_msg($data["return_msg"]);
        }
        //业务结果
        if(array_key_exists("result_code", $data)) {
            $objInput->SetResult_code($data["result_code"]);
        }
        //错误代码
        if(array_key_exists("err_code", $data)) {
            $objInput->SetErr_code($data["err_code"]);
        }
        //错误代码描述
        if(array_key_exists("err_code_des", $data)) {
            $objInput->SetErr_code_des($data["err_code_des"]);
        }
        //商户订单号
        if(array_key_exists("out_trade_no", $data)) {
            $objInput->SetOut_trade_no($data["out_trade_no"]);
        }
        //设备号
        if(array_key_exists("device_info", $data)) {
            $objInput->SetDevice_info($data["device_info"]);
        }

        try {
            self::report($objInput);
        } catch(\Exception $e) {
            //不做任何处理
        }
    }


    /**
     *
     * 支付结果通用通知
     * @param function $callback
     * 直接回调函数使用方法: notify(you_function);
     * 回调类成员函数方法:notify(array($this, you_function));
     * $callback  原型为：function function_name($data){}
     */
    public static function notify($config)
    {
        //获取通知的数据
        $xml = file_get_contents('php://input');
        //如果返回成功则验证签名
        try {
            //验证签名
            $check_sign = self::check_sign($xml,$config['key']);
            if($check_sign['code'] == 1){
                $values = $check_sign['data'];
                //查询订单，判断订单真实性
                return self::orderQuery($values["transaction_id"],$config);
            }else{
                return $check_sign;
            }

        } catch (\Exception $e){
            return ['code' => 0, 'msg' => $e->getMessage(), 'data' => []];
        }
    }



    public static function check_sign($xml,$key){
        $values = self::FromXml($xml);
        if(!$values){
            return ['code' => 0, 'msg' => 'xml数据异常', 'data' => []];
        }

        if($values['return_code'] != 'SUCCESS'){
            return ['code' => 0, 'msg' => '错误状态', 'data' => $values];
        }
        //检测签名
        if(!array_key_exists('sign',$values)){
            return ['code' => 0, 'msg' => '签名中不包含:sign字段', 'data' => []];
        }

        if($values['sign'] != self::MakeSign($values, $key)){
            return ['code' => 0, 'msg' => '签名错误,比对key错误', 'data' => []];
        }else{
            return ['code' => 1, 'msg' => '验证签名成功', 'data' => $values];
        }
    }

    /**
     *
     * 查询订单，WxPayOrderQuery中out_trade_no、transaction_id至少填一个
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param WxPayOrderQuery $inputObj
     * @param int $timeOut
     * @throws WxPayException
     * @return 成功时返回，其他抛异常
     */
    public static function orderQuery($transaction_id, $config)
    {
        $url = "https://api.mch.weixin.qq.com/pay/orderquery";
        //检测必填参数
        if(!$transaction_id) {
            return ['code' => 0, 'msg' => '订单查询接口中，out_trade_no、transaction_id至少填一个', 'data' => []];
        }

        $data = [
            'appid' => $config['appid'],
            'mch_id' => $config['mchid'],
            'nonce_str' => self::getNonceStr(),
            'transaction_id' => $transaction_id,
        ];
        $data['sign'] = self::MakeSign($data, $config['key']);
        $xml = self::ToXml($data);
        if($xml == false){
            return ['code' => 0, 'msg' => '查询订单(发送)xml数据异常', 'data' => $result];
        }else{
            $response = self::postXmlCurl($xml, $url, false, 6);
            //验证签名
            $check_sign = self::check_sign($response,$config['key']);
            if($check_sign['code'] == 1){
                $result = $check_sign['data'];
                if(!$result){
                    return ['code' => 0, 'msg' => '数组数据异常', 'data' => $result];
                }else{
                    return ['code' => 1, 'msg' => '签名成功', 'data' => $result];
                }
            }else{
                return $check_sign;
            }
        }
    }
}