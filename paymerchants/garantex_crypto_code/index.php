<?php
if( !defined( 'ABSPATH')){ exit(); }

/*
title: [en_US:]Garantex Crypto Code[:en_US][ru_RU:]Garantex Crypto Code[:ru_RU]
description: [en_US:]Garantex Crypto Code automatic payouts[:en_US][ru_RU:]авто выплаты Garantex Crypto Code[:ru_RU]
version: 2.4.0
*/

if(!class_exists('Ext_AutoPayut_Premiumbox')){ return; }

if(!class_exists('paymerchant_garantex_crypto_code')){
	class paymerchant_garantex_crypto_code extends Ext_AutoPayut_Premiumbox {
				private $currency_lists = array('USD','RUB');
		function __construct($file, $title)
		{
			parent::__construct($file, $title);	
			
			add_filter('list_user_notify',array($this,'user_mailtemp')); 
			add_filter('list_notify_tags_garantex_paycoupon',array($this,'mailtemp_tags_paycoupon'));
		}

		function get_map(){
			$map = array(
				'PRIVATE_KEY'  => array(
					'title' => '[en_US:]Private Key[:en_US][ru_RU:]Private Key[:ru_RU]',
					'view' => 'input',	
				),
				'UID'  => array(
					'title' => '[en_US:]UID[:en_US][ru_RU:]UID[:ru_RU]',
					'view' => 'input',
				),					
			);
			return $map;
		}
		
		function settings_list(){
			$arrs = array();
			$arrs[] = array('PRIVATE_KEY','UID');
			return $arrs;
		}
		
		function options($options, $data, $m_id, $place){
			
			$options = pn_array_unset($options, array('checkpay','note','resulturl','error_status','cronhash','enableip'));
			$options['bindlogin'] = array(
				'view' => 'select',
				'title' => __('Link coupon to users login','pn'),
				'options' => array('0' => __('No','pn'),'1' => __('Yes','pn')),
				'default' => intval(is_isset($data, 'bindlogin')),
				'name' => 'bindlogin',
				'work' => 'int',
			);	
            
			return $options; 
		}			


		function user_mailtemp($places_admin){
			$places_admin['garantex_paycoupon'] = sprintf(__('%s automatic payout','pn'), 'GcCode');
			return $places_admin;
		}

		function mailtemp_tags_paycoupon($tags){
			$tags['id'] = array(
				'title' => __('Coupon code','pn'),
				'start' => '[id]',
			);
			$tags['bid_id'] = array(
				'title' => __('Order ID','pn'),
				'start' => '[bid_id]',
			);
			return $tags;
		}	





		function get_reserve_lists($m_id, $m_defin){
			
			$currencies = $this->currency_lists;
			
			$purses = array();
			foreach($currencies as $currency){
				$purses[$m_id . '_' . strtolower($currency)] = strtoupper($currency);
			} 
			
			return $purses;
		}	

		function update_reserve($code, $m_id, $m_defin){ 
			$sum = 0;
			$purse = strtoupper(trim(str_replace($m_id . '_','',$code))); 
			if($purse){
				try {
					$class = new AP_Garantex_Crypto_Code($this->name, $m_id, is_isset($m_defin,'PRIVATE_KEY'), is_isset($m_defin, 'UID'));
					$res = $class->get_balance();
					if(is_array($res)){
						$rezerv = '-1';
						foreach($res as $pursename => $sum){
							$pursename = strtoupper($pursename);
							if($pursename == $purse){
								$rezerv = trim((string)$sum);
								break;
							}
						}
						if($rezerv != '-1'){
							$sum = $rezerv;
						}						
					} 
				}
				catch (Exception $e)
				{
					$this->logs($e->getMessage(), $m_id);		
				} 				
			}
			return $sum;
		}		

		function do_auto_payouts($error, $pay_error, $m_id, $item, $place, $direction_data, $paymerch_data, $unmetas, $modul_place, $direction, $test, $m_defin){
			//return;
			$item_id = $item->id;
			$trans_id = 0;			
			$coupon = '';			

			$bindlogin = intval(is_isset($paymerch_data, 'bindlogin'));
			
			$currency = mb_strtoupper($item->currency_code_get);
			$currency = str_replace('RUR','RUB',$currency);
					
			$enable = array('USD','RUB',);		
			if(!in_array($currency, $enable)){
				$error[] = __('Wrong currency code','pn'); 
			}						
				
			if($bindlogin == 1){
				$receiver = $item->account_get;
				$account = $item->user_email;
			} else {
				$receiver = '';
				$account = $item->account_get;
			}
				
			if (!is_email($account) or !is_email($item->account_get)) {
				$error[] = __('Client wallet type does not match with currency code','pn');
			}				
					
			$out_sum = $sum = is_paymerch_sum($item, $paymerch_data);
					
			$two = array('USD','RUR');
			if(in_array($currency, $two)){
				$sum = is_sum($sum, 8);
			} else {
				$sum = is_sum($sum);
			}
			$currency = strtolower($currency);
			if(count($error) == 0){

				$result = $this->set_ap_status($item, $test);				
				if($result){				
					
					try {
						$res = new AP_Garantex_Crypto_Code($this->name, $m_id, is_isset($m_defin,'PRIVATE_KEY'), is_isset($m_defin, 'UID'));
						$res = $res->make_voucher($sum, $currency, $receiver);
						if($res['error'] == 1){
							$error[] = __('Payout error','pn');
							$pay_error = 1;
						} else {
							$coupon = $res['coupon'];
							$trans_id = $res['trans_id'];
						}								 	
					}
					catch (Exception $e)
					{
						$error[] = $e->getMessage();
						$pay_error = 1;
					}

				} else {
					$error[] = 'Database error';
				}						
									
			}
					
			if(count($error) > 0){
						
				$this->reset_ap_status($error, $pay_error, $item, $place, $m_id, $test);
						
			} else {
						
				$bid_locale = $item->bid_locale;
				$now_locale = get_locale();
				set_locale($bid_locale);

				$notify_tags = array();
				$notify_tags['[id]'] = $coupon;
				$notify_tags['[bid_id]'] = $item_id;
				$notify_tags = apply_filters('notify_tags_garantex_paycoupon', $notify_tags);		

				$user_send_data = array(
					'user_email' => $item->account_get,
					);
				$user_send_data = apply_filters('user_send_data', $user_send_data, 'garantex_paycoupon', $item);
				$result_mail = apply_filters('premium_send_message', 0, 'garantex_paycoupon', $notify_tags, $user_send_data);									
						
				set_locale($now_locale);

				$coupon_data = array(
					'coupon' => $coupon,
				);
				do_action('merchant_create_coupon', $coupon_data, $item, 'garantex', $place);
				
				$params = array(
					'trans_out' => $trans_id,
					'out_sum' => $out_sum,
					'system' => 'user',
					'm_place' => $modul_place. ' ' .$m_id,
					'm_id' => $m_id,
					'm_defin' => $m_defin,
					'm_data' => $paymerch_data,
				);
				set_bid_status('success', $item_id, $params, $direction);	  					
				
				if($place == 'admin'){
					pn_display_mess(__('Automatic payout is done','pn'),__('Automatic payout is done','pn'),'true');
				}  
						
			}	
		}

	
	}
}

new paymerchant_garantex_crypto_code(__FILE__, 'Garantex Crypto Code');