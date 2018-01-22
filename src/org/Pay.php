<?php
// +----------------------------------------------------------------------
// | Future [ 追寻最初的梦想 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2010-2016 http://www.21514.com All rights reserved.
// +----------------------------------------------------------------------
// | Author:封疆 <dxcswl@163.com> QQ:84111804
// +----------------------------------------------------------------------
namespace ft;

class Pay
{
    /**
     *增加支付状态
     */
    protected function SetPayid($uid,$order_id,$money,$operator)
    {
        $param['order_id'] = $order_id;
        $param['uid'] = $uid;
        $param['trade_time'] = time();
        $param['money'] = $money;
        $param['operator'] = $operator;
        $param['status']  = 0;
        $param['mass']  = $money * 10;
        $id = \think\Db::name('pay_record')->insertGetId($param);
        if($id)
        {
            return $id;
        }else{
            return 0;
        }
    }

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


	/*
	 * 微信支付app发起处理
	 *
	 */

	public function getAppWeixinApp(){
        $money = input('param.money');
        $uid = input('param.uid');
        $GetLegal = $this->GetLegal($money);
        if($GetLegal){
            return $GetLegal;
        }

        $order_id = md5($uid.uniqid());
        $id = $this->SetPayid($uid,$order_id,$money,'wecha');
        if(!$id){
            return $this->set_return(99,'订单生成错误');
        }
        $total_price = (int) ($money*100);
        \think\Loader::import('common.org.Wxpay.NativePay','',".php");//扫码支付
        $notify = new \NativePay();
        //方式二
        $input = new \WxPayUnifiedOrder();
        $input->SetBody("樱桃充值");
        //$input->SetAttach($order_id);
        $input->SetOut_trade_no($order_id);
        $input->SetTotal_fee($total_price);
        //$input->SetTime_start(date("YmdHis"));
        //$input->SetTime_expire(date("YmdHis", time() + 600));
        //$input->SetGoods_tag("在线支付");
        $input->SetNotify_url($this->api_url.'/api/pay/set_wechat_notify');
        $input->SetTrade_type("APP");
        //$input->SetProduct_id($order_id);
        $result = $notify->GetPayApp($input);
        if(isset($result['appid']) && isset($result['nonce_str']) && isset($result['mch_id']) && isset($result['prepay_id']) && isset($result['sign'])){
            $data['nonce_str'] = $result['nonce_str'];
            $data['prepay_id'] = $result['prepay_id'];
            $data['sign'] = $result['sign'];
            $data['appid'] = $result['appid'];
            $data['partnerid'] = $result['mch_id'];
            $data['timestamp'] = time();
            $data['spbill_create_ip'] = '171.113.60.93';
            return $this->set_return(1,$data);
        }else{
            return $this->set_return(99,'请求错误');
        }
    }

	public function get_ali_param(){
		import('common.org.Alipay.mapi.alipay','','.config.php');
		import('common.org.Alipay.mapi.lib.alipay_core','','.function.php');
		import('common.org.Alipay.mapi.lib.alipay_notify','','.class.php');
		//import('common.org.Alipay.mapi.lib.alipay_rsa','','.function.php');

		$money = input('param.money');
		$GetLegal = $this->GetLegal($money);
		if($GetLegal){
			return $GetLegal;
		}
		$uid = input('param.uid');

		$order_id = md5($uid.uniqid());
		$id = $this->SetPayid($uid,$order_id,$money,'ali');
		if(!$id){
			return $this->set_return(99,'订单生成错误');
		}
		$total_price = (int) $money;


		$alipay_config = new \alaipay_config();


		$order_info =  'partner=' ."\"". trim($alipay_config->alipay_config['partner']) . "\"";
		$order_info .=  '&seller_id=' ."\"". trim($alipay_config->alipay_config['partner']) . "\"";
		$order_info .=  '&out_trade_no=' ."\"". $order_id . "\"" ;
		$order_info .=  '&subject=' ."\"樱桃充值\"";
		$order_info .=  '&body=' ."\"樱桃充值..\"";
		$order_info .=  '&total_fee=' ."\"". $total_price ."\"";
		$order_info .=  '&notify_url=' ."\"". $this->api_url.'/api/pay/set_ali_notify' ."\"";
		$order_info .=  '&it_b_pay=' . "\"" . '30m' . "\"";
		$order_info .=  '&service=' . "\"" . 'mobile.securitypay.pay' . "\"";
		$order_info .=  '&payment_type=' . "\"1\"" ;
		$order_info .=  '&return_url=' . "\"http://m.alipay.com\"";
		$order_info .=  '&_input_charset=' . "\"utf-8\"";

		$sign  =  urlencode(rsaSign($order_info,$alipay_config->alipay_config['private_key_path']));
		$sign_type =  '&sign_type=' ."\"". $alipay_config->alipay_config['sign_type'] . "\"";
		$result['order_param'] = $order_info . '&sign=' ."\"" . $sign ."\"" . $sign_type;
		$result['order_id']    = $order_id;
		return $this->set_return(1,$result);
		/*
		import('common.org.Aop.AopSdk');
		import('common.org.Aop.aop.AopClient');
		$aop = new \AopClient();

		$aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
		$aop->appId = '2088911504428620';
		$aop->rsaPrivateKey = 'MIICXAIBAAKBgQCx/CE3J/S+DPh+ws6S9hVEz0d0eJPK0EVvm2eoTR9q+ubgNN7ie6xp1oEa2nAkb2d3uylfHPpMeodRKVF4Gl5YC2Ic9qi2N2zlP9wDCWtTPM+oUQDG9kuYftJDOLB7b3U2XEK/XD92t2C56vJ//HJgoam+0FvsorRF77q0R2YCDwIDAQABAoGAT4QD1t9r8QhccE1Z+sAkCmTMWJWR+ZcInm8AZWlnMuU7Bkm4ldiI05P4g+W5Gh4HTK96MTsB++71y2W5Nv4YzV9Ci1bNGLcUnnivQXiCuDl76qJBUZlriQSpdASCNHLjk5hoi1P12MARDzqWZq26lmLCH6vTCblNkKxidvwF1VkCQQDr2sWiVu/fjJeqfq0ZoC4UYhvoa2CIEJ+kh9shxZ2q+Xrwz01oOs+OA/0U2rWx7dRAQ/e/bwglSfdawnT5v8B1AkEAwS/4nfq2KFXESDkuGn62SfmyTRyuKg5t1G/o/qt8OXz6aqL32uER0K3N80plIwMwEFvQpj+0tDDIrRd8Bb6n8wJAD/Bn/NGdQmFQ+p+2+Q1fL9d1hV6EVo2xDEB2KbEeN6jGizGnTIz06+cPGnKxZsXo2zL8sj5BsatvAP41Q4+W5QJBAJF+HGJ2J+v2s+2kyrj/hy/tUsBKgkyAM20Tn0j1Q4hUPJBFDh+U9ALSctHwzHxy8SbQzzH1tpUiTHA3yJrW/MsCQDPPO4VSxkT1g2Zxs59TR5Oin8CsAwzgPTsEtlWX6IFme4ixC6mhkjZerO2IdkJhApTKfqO1WhiXxzT5t/oHDMo=';
		$aop->alipayrsaPublicKey='MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDDI6d306Q8fIfCOaTXyiUeJHkrIvYISRcc73s3vF1ZT7XN8RNPwJxo8pWaJMmvyTn9N4HQ632qJBVHf8sxHi/fEsraprwCtzvzQETrNRwVxLO5jVmRGi60j8Ue1efIlzPXV9je9mkjzOmdssymZkh2QhUrCmZYI/FCEa3/cNMW0QIDAQAB';
		$aop->apiVersion = '1.0';
		$aop->postCharset='UTF-8';
		$aop->format='json';
		$aop->signType='RSA';
		$request = new \AlipayTradeWapPayRequest();
		$data = [
			'body' => '樱桃充值...',
			'subject' => '樱桃充值',
			'out_trade_no' => $order_id,
			'timeout_express' => '90m',
			'total_amount' => $total_price,
			'product_code' => 'QUICK_WAP_WAY',
		];
		$request->setBizContent(json_encode($data, true));
		$result = $aop->sdkExecute ( $request);


		return $this->set_return(1,['order_id'=>$order_id,'order_param'=>$result]);
		*/

	}

	public function set_ali_notify(){
		hxSetTxt('支付宝支付回调开始:'.json_encode($_POST, TRUE));
		import('common.org.Alipay.mapi.lib.alipay_core','','.function.php');
		import('common.org.Alipay.mapi.lib.alipay_notify','','.class.php');
		//import('common.org.Alipay.mapi.lib.alipay_rsa','','.function.php');
		import('common.org.Alipay.mapi.alipay','','.config.php');

		$alipay_config = new \alaipay_config();
		$alipayNotify = new \AlipayNotify($alipay_config->alipay_config);
		$verify_result = $alipayNotify->verifyNotify();
		if($verify_result)
		{
			$out_trade_no = input('post.out_trade_no'); //商户订单号
			$trade_no     = input('post.trade_no');//支付宝交易号
			$trade_status = input('post.trade_status');//交易状态
			$money        = input('post.total_fee');//金额
			$GetLegal = $this->GetLegal($money);
			if($GetLegal){
				return $GetLegal;
			}

			if (($trade_status == 'TRADE_SUCCESS') || ($trade_status == 'TRADE_FINISHED'))
			{
				$pay_find = $this->getPayFind(['order_id'=>$out_trade_no]);
				if($pay_find){
					$pay_last = $this->SetPayLast($pay_find,$trade_no,'ali');
					if($pay_last == -2){
						hxSetTxt('用户钻石没有操作成功：'.json_encode($pay_last, TRUE));
					}
				}else{
					hxSetTxt('没有找到order_id：'.$out_trade_no);
				}
			}
			echo   "success";
		}
		else
		{
			echo   "fail";
		}
	}

	public function get_wechat_param(){
		$money = input('param.money');
		$uid = input('param.uid');
		$GetLegal = $this->GetLegal($money);
		if($GetLegal){
			return $GetLegal;
		}

		$order_id = md5($uid.uniqid());
		$id = $this->SetPayid($uid,$order_id,$money,'wecha');
		if(!$id){
			return $this->set_return(99,'订单生成错误');
		}
		$total_price = (int) ($money*100);
		\think\Loader::import('common.org.Wxpay.NativePay','',".php");//扫码支付
		$notify = new \NativePay();
		//方式二
		$input = new \WxPayUnifiedOrder();
		$input->SetBody("樱桃充值");
		//$input->SetAttach($order_id);
		$input->SetOut_trade_no($order_id);
		$input->SetTotal_fee($total_price);
		//$input->SetTime_start(date("YmdHis"));
		//$input->SetTime_expire(date("YmdHis", time() + 600));
		//$input->SetGoods_tag("在线支付");
		$input->SetNotify_url($this->api_url.'/api/pay/set_wechat_notify');
		$input->SetTrade_type("APP");
		//$input->SetProduct_id($order_id);
		$result = $notify->GetPayApp($input);
		if(isset($result['appid']) && isset($result['nonce_str']) && isset($result['mch_id']) && isset($result['prepay_id']) && isset($result['sign'])){
			$data['nonce_str'] = $result['nonce_str'];
			$data['prepay_id'] = $result['prepay_id'];
			$data['sign'] = $result['sign'];
			$data['appid'] = $result['appid'];
			$data['partnerid'] = $result['mch_id'];
			$data['timestamp'] = time();
			$data['spbill_create_ip'] = '171.113.60.93';
			return $this->set_return(1,$data);
		}else{
			return $this->set_return(99,'请求错误');
		}
	}


	//微信异步返回
	function set_wechat_notify() {
		hxSetTxt('微信支付回调开始:'.file_get_contents("php://input"));
		\think\Loader::import('common.org.Wxpay.Notify','',".php");
		$WxPayNotify = new \WxPayNotify();
		$WxPayNotify->Handle(FALSE);
		$ret = $WxPayNotify->GetReturn_code();
		if($ret != "FAIL"){
			$order_id = $WxPayNotify->getData('out_trade_no');
			if($order_id){
				$pay_find = $this->getPayFind(['order_id'=>$order_id]);
				if($pay_find){
					$pay_last = $this->SetPayLast($pay_find,$WxPayNotify->getData('transaction_id'),'wecha');
					if($pay_last == -2){
						hxSetTxt('用户钻石没有操作成功：'.json_encode($pay_last, TRUE));
					}
				}else{
					hxSetTxt('没有找到order_id：'.$order_id);
				}
			}else{
				hxSetTxt('没有获取订单号'.json_encode($_POST, TRUE));
			}

		}else{
			hxSetTxt('回调授权报错'.json_encode($_POST, TRUE));
		}
	}



	protected function getPayFind($where) {
		$pay = \think\Db::name('pay_record')->where($where)->order('trade_time desc')->find();
		return $pay;
	}

	protected function SetPayLast($pay_find,$trade_no,$operator = 'iap'){
		if(isset($pay_find['status']) && $pay_find['status'] == 0){
			$uid_attribute = set_user_attribute_account($pay_find['uid'],['mass'=>$pay_find['mass'],'score'=>$pay_find['money']]);
			if ($uid_attribute['status'] != 1) {
				return -2;
			}else{
				$param['trade_id']   = $trade_no;
				$param['trade_time'] = time();
				$param['status']     = 2;
				\think\Db::name('pay_record')->where(['id'=>$pay_find['id']])->update($param);
				return 1;
			}
		}else{
			return -1;
		}
	}



	private function GetLegal($money){
		/*if($money < 6 or $money > 9998){
			return $this->set_return(99,'非法访问');
		}*/
		return false;
	}


}
?>