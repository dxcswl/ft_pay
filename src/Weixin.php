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

    public static function get_weixinWeb($config, $total_price, $order_id, $pay_id){

        $result_code = self::getWeixinUnifiedOrder($config, $total_price, $order_id, $pay_id,'JSAPI');
        if($result_code['code'] == 0){
            return $result_code;
        }else{
            $result = $result_code['data'];
        }
        if(isset($result['appid']) and isset($result['nonce_str']) and isset($result['mch_id']) and isset($result['prepay_id']) and isset($result['sign'])) {
            $data['appid'] = $result['appid'];
            $data['partnerid'] = $result['mch_id'];
            $data['prepayid'] = $result['prepay_id'];
            $data['package'] = 'prepay_id='.$result['prepay_id'];
            $data['noncestr'] = $result['nonce_str'];
            $data['timestamp'] = time();
            $data['sign'] = self::MakeSign(['appId'=>$data['appid'],'timeStamp'=>''.$data['timestamp'],'nonceStr'=>$data['noncestr'],'package'=>$data['package'],'signType'=>'MD5'], $config['key']);
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
            if($trade_type == "JSAPI"){
                $data['openid'] = $config['openid'];
            }elseif($trade_type == "NATIVE"){
                $data['product_id'] = $config['product_id'];
            }


            $data['sign'] = self::MakeSign($data, $config['key']);
            $xml = self::ToXml($data);
            /*if($trade_type == 'JSAPI'){
                var_dump($xml);exit();
            }*/
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

    /** $data 格式如下
     *  $data = array(
     * 'openid' //退款者openid
     * 'refundid' //退款申请ID
     * 'money' //退款金额
     * 'desc'  //退款描述
     * );
     *
     */
    public static function set_refund($data, $wxchat = [])
    {
        //判断有没有CA证书及支付信息
        if (empty($wxchat['api_cert']) || empty($wxchat['api_key']) || empty($wxchat['api_ca']) || empty($wxchat['appid']) || empty($wxchat['mchid'])) {
            return false;
//            $wxchat['appid'] = $this->appid;
//            $wxchat['mchid'] = $this->mchid;
//            $wxchat['api_cert'] = APP_PATH .$this->cacab['api_cert'];
//            $wxchat['api_key'] = APP_PATH .$this->cacab['api_key'];
//            $wxchat['api_ca'] = APP_PATH .$this->cacab['api_ca'];
        }else{

        }
        $webdata = array(
            'mch_appid' => $wxchat['appid'],
            'mchid' => $wxchat['mchid'],
            'nonce_str' => md5(time()),
            //'device_info' => '1000',
            'partner_trade_no' => $data['refundid'], //商户订单号，需要唯一
            'openid' => $data['openid'],
            'check_name' => 'NO_CHECK', //OPTION_CHECK不强制校验真实姓名, FORCE_CHECK：强制 NO_CHECK：
            //'re_user_name' => 'jorsh', //收款人用户姓名
            'amount' => $data['money'] * 100, //付款金额单位为分
            'desc' => empty($data['desc']) ? '提现' : $data['desc'],
            'spbill_create_ip' => self::refund_getip(),
        );
        foreach ($webdata as $k => $v) {
            $tarr[] = $k . '=' . $v;
        }
        sort($tarr);
        $sign = implode($tarr, '&');
        $sign .= '&key=' . $wxchat['signkey'];
        $webdata['sign'] = strtoupper(md5($sign));
        $wget = self::refund_array2xml($webdata);
        $res = self::refund_http_post('https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers', $wget, $wxchat);
        if (!$res) {
            return array('status' => 0, 'msg' => "Can't connect the server");
        }
        $content = simplexml_load_string($res, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (strval($content->return_code) == 'FAIL') {
            return array('status' => 0, 'msg' => strval($content->return_msg));
        }
        if (strval($content->result_code) == 'FAIL') {
            return array('status' => 0, 'msg' => strval($content->err_code), ':' . strval($content->err_code_des));
        }
        $rdata = array(
            'mch_appid' => strval($content->mch_appid),
            'mchid' => strval($content->mchid),
            'device_info' => strval($content->device_info),
            'nonce_str' => strval($content->nonce_str),
            'result_code' => strval($content->result_code),
            'partner_trade_no' => strval($content->partner_trade_no),
            'payment_no' => strval($content->payment_no),
            'payment_time' => strval($content->payment_time),
        );
        return array('status' => 1, 'msg' => '成功打款','data'=>$rdata);
    }

    public static function refund_getip()
    {
        static $ip = '';
        $ip = $_SERVER['REMOTE_ADDR'];
        if (isset($_SERVER['HTTP_CDN_SRC_IP'])) {
            $ip = $_SERVER['HTTP_CDN_SRC_IP'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP']) && preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) AND preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches)) {
            foreach ($matches[0] AS $xip) {
                if (!preg_match('#^(10|172\.16|192\.168)\.#', $xip)) {
                    $ip = $xip;
                    break;
                }
            }
        }
        return $ip;
    }

    /**
     * 将一个数组转换为 XML 结构的字符串
     * @param array $arr 要转换的数组
     * @param int $level 节点层级, 1 为 Root.
     * @return string XML 结构的字符串
     */
    public static function refund_array2xml($arr, $level = 1)
    {
        $s = $level == 1 ? "<xml>" : '';
        foreach ($arr as $tagname => $value) {
            if (is_numeric($tagname)) {
                $tagname = $value['TagName'];
                unset($value['TagName']);
            }
            if (!is_array($value)) {
                $s .= "<{$tagname}>" . (!is_numeric($value) ? '<![CDATA[' : '') . $value . (!is_numeric($value) ? ']]>' : '') . "</{$tagname}>";
            } else {
                $s .= "<{$tagname}>" . self::refund_array2xml($value, $level + 1) . "</{$tagname}>";
            }
        }
        $s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
        return $level == 1 ? $s . "</xml>" : $s;
    }

    public static function refund_http_post($url, $param, $wxchat)
    {
        $oCurl = curl_init();
        if (stripos($url, "https://") !== FALSE) {
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
        }
        if (is_string($param)) {
            $strPOST = $param;
        } else {
            $aPOST = array();
            foreach ($param as $key => $val) {
                $aPOST[] = $key . "=" . urlencode($val);
            }
            $strPOST = join("&", $aPOST);
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($oCurl, CURLOPT_POST, true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS, $strPOST);
        if ($wxchat) {
            curl_setopt($oCurl, CURLOPT_SSLCERT,   $wxchat['api_cert']);
            curl_setopt($oCurl, CURLOPT_SSLKEY,  $wxchat['api_key']);
            curl_setopt($oCurl, CURLOPT_CAINFO,  $wxchat['api_ca']);
        }
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if (intval($aStatus["http_code"]) == 200) {
            return $sContent;
        } else {
            return false;
        }
    }
}