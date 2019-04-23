<?php
// +----------------------------------------------------------------------
// | Future [ 追寻最初的梦想 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2010-2019 www.21514.com All rights reserved.
// +----------------------------------------------------------------------
// | Author:Future <dxcswl@163.com> QQ:84111804
// +----------------------------------------------------------------------

namespace ft_pay\org;

class Ali{

    /*
     * 微信客户端APP发起支付
     *
     */
    public function getAli($config, $money, $pay_id, $trade_type = 'APP') {
        $total_price = (double) $money;

        require_once(\think\facade\Env::get('app_path') . 'common/org/aop/AopSdk.php');
        require_once(\think\facade\Env::get('app_path') . 'common/org/aop/aop/AopClient.php');

        $this->aop = new \AopClient();
        $this->aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $this->aop->appId = $config['partner'];
        $this->aop->rsaPrivateKey = $config['private_key_path'];
        $this->aop->format = "json";
        $this->aop->charset = $config['input_charset'];
        $this->aop->signType = $config['sign_type'];
        $this->aop->encryptKey = '';
        $this->aop->alipayrsaPublicKey = $config['ali_public_key_path'];


        $data = ['body'=>$config['body'],'subject'=>$config['subject'],'out_trade_no'=>$pay_id,'timeout_express'=>'30m','total_amount'=>$total_price,'product_code'=>'QUICK_MSECURITY_PAY'];
        $request = new \AlipayTradeAppPayRequest();
        $request->setNotifyUrl($config['notify_url']);
        $request->setBizContent(json_encode($data, true));
        //这里和普通的接口调用不同，使用的是sdkExecute
        $response = $this->aop->sdkExecute($request);
        //parse_str($response,$order_array);
        if ($response) {
            return ['code' => 1, 'msg' => '成功', 'data' => ['order_strings' =>  $response]];
        } else {
            return ['code' => 0, 'msg' => '链接生成错误', 'data' => []];
        }

    }

    public function getNotify($config){
        require_once(\think\facade\Env::get('app_path') . 'common/org/aop/AopSdk.php');
        require_once(\think\facade\Env::get('app_path') . 'common/org/aop/aop/AopClient.php');
        $aop = new \AopClient;
        $aop->alipayrsaPublicKey = $config['ali_public_key_path'];
        $flag = $aop->rsaCheckV1($_POST, NULL, $config['sign_type']);

        if($_POST['trade_status'] != 'TRADE_SUCCESS'){
            return ['code' => 0, 'msg' => '支付失败', 'data' => []];
        }

        if($flag){
            $orderQuery['data']['total_fee'] = input('param.total_amount');
            $orderQuery['data']['transaction_id'] = input('param.trade_no');
            $orderQuery['data']['out_trade_no'] = input('param.out_trade_no');
            $orderQuery['data']['time_end'] = time();
            $orderQuery['data']['openid'] = input('param.out_trade_no');
            $orderQuery['data']['trade_type'] = 0;
            return ['code' => 1, 'msg' => '验证成功', 'data' => ['request'=>input('param.'),'query'=>$orderQuery]];
        }else{
            return ['code' => 0, 'msg' => '错误', 'data' => ['request'=>input('param.'),'query'=>'']];
        }
    }

}