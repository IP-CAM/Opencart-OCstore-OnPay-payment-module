﻿<?php
/**
 * e-mail: mbpresta@rambler.ru
 */
class ControllerPaymentOnPay extends Controller {
	protected function index() {
		$this->data['button_confirm'] = $this->language->get('button_confirm');

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

		$this->data['action'] = 'http://secure.onpay.ru/pay/' . $this->config->get('onpay_merchant') . "?" . $this->config->get('onpay_add_params');

		$pric = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], FALSE);
		$pr = number_format($pric, 1, '.', '');
		$this->data['order_amount'] = $pric;
		if ($order_info['currency_code'] == 'RUB') {
			$cc = 'RUR';
			$this->data['order_currency'] = 'RUR';
		} else {
			$this->data['order_currency'] = $order_info['currency_code'];
			$cc = $order_info['currency_code'];
		}
		$this->data['pay_mode'] = 'fix';
		$this->data['pay_for'] = $this->session->data['order_id'];
		$id_o = $this->session->data['order_id'];
		$this->data['url_success'] = HTTP_SERVER . 'index.php?route=checkout/success';
		$this->data['ap_cancelurl'] = $this->url->link('checkout/checkout', '', 'SSL');
		$onpay_security = $this->config->get('onpay_security');
		$cr = strtoupper(md5("fix;$pr;$cc;$id_o;yes;$onpay_security"));
		$this->data['md5'] = $cr;

		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/onpay.tpl')) {
			$this->template = $this->config->get('config_template') . '/template/payment/onpay.tpl';
		} else {
			$this->template = 'default/template/payment/onpay.tpl';
		}

		$this->render();
	}
	
	//Cramac
	public function confirm() {

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		if(!$order_info) return;
		
		$order_id = $this->session->data['order_id'];
		
		if( $order_info['order_status_id'] == 0) {
			$this->model_checkout_order->confirm($order_id, $this->config->get('onpay_order_status_progress_id'), 'ONPAY');
			return;
		}
		
		if( $order_info['order_status_id'] != $this->config->get('onpay_order_status_progress_id')) {
			$this->model_checkout_order->update($order_id, $this->config->get('onpay_order_status_progress_id'),'ONPAY',TRUE);
		}

   	}
	//Cramac
		
		
//функция выдает ответ для сервиса onpay в формате XML на чек запрос
	private function answer($type, $code, $pay_for, $order_amount, $order_currency, $text, $private_code) {
		$md5 = strtoupper(md5("$type;$pay_for;$order_amount;$order_currency;$code;" . $private_code));
		return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
		"<result>\n".
		"<code>$code</code>\n".
		"<pay_for>$pay_for</pay_for>\n".
		"<comment>$text</comment>\n".
		"<md5>$md5</md5>\n".
		"</result>";
	}

//функция выдает ответ для сервиса onpay в формате XML на pay запрос
	private function answerpay($type, $code, $pay_for, $order_amount, $order_currency, $text, $onpay_id, $private_code) {
		$md5 = strtoupper(md5("$type;$pay_for;$onpay_id;$pay_for;$order_amount;$order_currency;$code;" . $private_code));
		return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
		"<result>\n".
		"<code>$code</code>\n".
		"<comment>$text</comment>\n".
		"<onpay_id>$onpay_id</onpay_id>\n".
		"<pay_for>$pay_for</pay_for>\n".
		"<order_id>$pay_for</order_id>\n".
		"<md5>$md5</md5>\n".
		"</result>";
	}

	public function callback() {
		if (isset($this->request->post['type'])) {
			$type = $this->request->post['type'];
			$order_amount = $this->request->post['order_amount'];
//			$amount = $this->request->post['amount'];
			$order_currency = $this->request->post['order_currency'];
			$md5 = $this->request->post['md5'];
			$pay_for = $this->request->post['pay_for'];
			if (isset($this->request->post['onpay_id'])) {
				$onpay_id = $this->request->post['onpay_id'];
			}
			$onpay_security = $this->config->get('onpay_security');

			if ($type == 'check') {
				$result = $this->answer($type, 0, $pay_for, $order_amount, $order_currency, 'OK', $onpay_security);
				echo $result;
				return;
			}

			if ($type == 'pay') {

			}
			
			$crc = strtoupper(md5("pay;$pay_for;$onpay_id;$order_amount;$order_currency;$onpay_security"));
			

			if ($crc == $md5) {
				$this->load->model('checkout/order');
				$order_info = $this->model_checkout_order->getOrder($this->request->post['pay_for']);

				if (!$order_info) {
					echo 'ERROR:  No this order!';
					return 0;
				}

				if (number_format($this->request->post['paid_amount']) >= number_format($this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], FALSE))) {
					$this->model_checkout_order->update($this->request->post['pay_for'], $this->config->get('onpay_order_status_id'),'ONPAY',TRUE);			
					echo $this->answerpay($type, 0, $pay_for, $order_amount, $order_currency, 'OK', $onpay_id, $onpay_security);

				} else {
					echo 'ERROR:  Amount filed!';
				}
			}
		}
	}
}

?>