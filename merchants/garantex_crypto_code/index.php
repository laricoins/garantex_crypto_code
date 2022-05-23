<?php
if( !defined( 'ABSPATH')){ exit(); }

/*
title: [en_US:]Garantex Crypto Code[:en_US][ru_RU:]Garantex Crypto Code[:ru_RU]
description: [en_US:]Garantex Crypto Code merchant[:en_US][ru_RU:]мерчант Garantex Crypto Code[:ru_RU]
version: 2.4.0
*/

if(!class_exists('Ext_Merchant_Premiumbox')){ return; }

if(!class_exists('merchant_garantex_crypto_code')){
	class merchant_garantex_crypto_code extends Ext_Merchant_Premiumbox {

		function __construct($file, $title)
		{
			parent::__construct($file, $title, 1);
			
			add_filter('list_user_notify',array($this,'user_mailtemp'));
			add_filter('list_admin_notify',array($this,'admin_mailtemp'));
			add_filter('list_notify_tags_generate_address1_garantexcrypto',array($this,'mailtemp_tags'));
			add_filter('list_notify_tags_generate_address2_garantexcrypto',array($this,'mailtemp_tags'));
			
			add_filter('bcc_keys',array($this,'set_keys'));
			add_filter('qr_keys',array($this,'set_keys'));
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
		
		function user_mailtemp($places_admin){
			$places_admin['generate_address1_garantexcrypto'] = sprintf(__('Address generation for %s','pn'), 'Garantex Crypto');
			return $places_admin;
		}

		function admin_mailtemp($places_admin){
			$places_admin['generate_address2_garantexcrypto'] = sprintf(__('Address generation for %s','pn'), 'Garantex Crypto');
			return $places_admin;
		}

		function mailtemp_tags($tags){
			
			$tags['bid_id'] = array(
				'title' => __('Order ID','pn'),
				'start' => '[bid_id]',
			);
			$tags['address'] = array(
				'title' => __('Address','pn'),
				'start' => '[address]',
			);
			$tags['sum'] = array(
				'title' => __('Amount','pn'),
				'start' => '[sum]',
			);
			$tags['dest_tag'] = array(
				'title' => __('Destination tag','pn'),
				'start' => '[dest_tag]',
			);			
			
			return $tags;
		}		

		function options($options, $data, $m_id, $place){ 
		
			$m_defin = $this->get_file_data($m_id);
			
			$options = pn_array_unset($options, array('check_api','note','enableip','resulturl','help_resulturl'));
			
			$options['create_error'] = array(
				'view' => 'select',
				'title' => __('Leave the application in error, in case of api error','pn'),
				'options' => array('0'=>__('No','pn'), '1'=>__('Yes','pn')),
				'default' => is_isset($data, 'create_error'),
				'name' => 'create_error',
				'work' => 'int',
			);

			$options['need_confirm'] = array(
				'view' => 'input',
				'title' => __('Required number of transaction confirmations','pn'),
				'default' => is_isset($data, 'need_confirm'),
				'name' => 'need_confirm',
				'work' => 'int',
			);
			$options['need_confirm_warning'] = array(
				'view' => 'warning',
				'default' => __('(Recommended!) Set the value to 0 so that the order is considered paid only after receiving the required number of confirmations on the stock! <br /> (NOT recommended!) If you set a value other than 0, the exchanger will change the status of the order to "Paid" according to this setting, regardless of the transaction status that is displayed in the exchanges payment history.','pn'),
			);			
			
			$text = '
			<div><strong>Cron:</strong> <a href="'. get_mlink($m_id.'_cron' . chash_url($m_id)) .'" target="_blank">'. get_mlink($m_id.'_cron' . chash_url($m_id)) .'</a></div>			
			';		
			
			$options['text'] = array(
				'view' => 'textfield',
				'title' => '',
				'default' => $text,
			);			
			
			return $options;	
		}			

		function merch_type(){
			return 'address'; 
		}	

		function init_merchant($m_id, $pay_sum, $direction) {
			global $wpdb, $bids_data;
			
			$script = get_mscript($m_id);
			if($script and $script == $this->name){
				$m_defin = $this->get_file_data($m_id);
				$m_data = get_merch_data($m_id);

				$item_id = $bids_data->id;		
				$currency = strtoupper($bids_data->currency_code_give);
					
				$dest_tag = pn_strip_input($bids_data->dest_tag);	
				$to_account = pn_strip_input($bids_data->to_account);
				if(!$to_account){
					$en_create = $this->enable_linkcreate($m_id, $m_data, $m_defin, $bids_data, $direction);
					if ($en_create) {
					
						$show_error = intval(is_isset($m_data, 'show_error'));
						
						if($currency == 'USDT' or $currency == 'BTC' or $currency == 'ETH'){
							$currency_id_give = $bids_data->currency_id_give;
							$currency_data = $wpdb->get_row("SELECT * FROM ". $wpdb->prefix ."currency WHERE id='$currency_id_give'");
							if(isset($currency_data->id)){
								$xml_value = mb_strtoupper(is_xml_value($currency_data->xml_value));
								if($xml_value == 'USDT'){
									$currency = 'USDT-OMNI';
								} elseif($xml_value == 'USDTERC' or $xml_value == 'USDTERC20'){ 
									$currency = 'USDT';
								} elseif($xml_value == 'USDTTRC' or $xml_value == 'USDTTRC20'){	
									$currency = 'USDT-TRON';
								} elseif($xml_value == 'USDTBEP' or $xml_value == 'USDTBEP20'){	
									$currency = 'USDT-BSC';
								} elseif($xml_value == 'BTCBEP20'){	
									$currency = 'BTC-BSC';
								} elseif($xml_value == 'ETHBEP20'){	
									$currency = 'ETH-BSC';								
								}
							}
						}
						
						try {
							$class = new Garantex_Crypto_Code($this->name, $m_id, is_isset($m_defin,'PRIVATE_KEY'), is_isset($m_defin, 'UID'));
							$result = $class->create_address($currency);
							if(isset($result['address'])){ 
								$to_account = pn_strip_input(is_isset($result, 'address'));
								$dest_tag = pn_strip_input(is_isset($result, 'memo'));
							} else {
								$this->linkcreate_error($m_id, $m_data, $m_defin, $bids_data, $direction);
								if($show_error and current_user_can('administrator')){
									print_r($result);
								}	
							}
						} catch (Exception $e) { 
							$this->logs($e->getMessage());
							$this->linkcreate_error($m_id, $m_data, $m_defin, $bids_data, $direction);
							if($show_error and current_user_can('administrator')){
								die($e->getMessage());
							}		
						}
						
						if($to_account){
							
							$arr = array();
							$arr['to_account'] = $to_account;
							$arr['dest_tag'] = $dest_tag;
							$bids_data = update_bid_tb_array($item_id, $arr, $bids_data);
							
							$notify_tags = array();
							$notify_tags['[bid_id]'] = $item_id;
							$notify_tags['[address]'] = $to_account;
							$notify_tags['[sum]'] = $pay_sum;
							$notify_tags['[dest_tag]'] = $dest_tag;
							$notify_tags = apply_filters('notify_tags_generate_address_garantexcrypto', $notify_tags);		

							$admin_locale = get_admin_lang();
							$now_locale = get_locale();
							set_locale($admin_locale);

							$user_send_data = array();
							$result_mail = apply_filters('premium_send_message', 0, 'generate_address2_garantexcrypto', $notify_tags, $user_send_data); 
							
							set_locale($now_locale);

							$user_send_data = array(
								'user_email' => $bids_data->user_email,
							);	
							$user_send_data = apply_filters('user_send_data', $user_send_data, 'generate_address1_garantexcrypto', $bids_data);	
							$result_mail = apply_filters('premium_send_message', 0, 'generate_address1_garantexcrypto', $notify_tags, $user_send_data);					
							
						}
					}
				}
			}					
		} 

		function confirm_count($m_id, $m_defin, $m_data) {
			return intval(is_isset($m_data, 'need_confirm'));
		}

		function cron($m_id, $m_defin, $m_data){
			global $wpdb;

			$show_error = intval(is_isset($m_data, 'show_error'));	
			$need_confirm = intval(is_isset($m_data, 'need_confirm'));
			
			try {
				$class = new Garantex_Crypto_Code($this->name, $m_id, is_isset($m_defin,'PRIVATE_KEY'), is_isset($m_defin, 'UID'));
				$orders = $class->get_history_deposits(100);

				if(is_array($orders) and count($orders) > 0){
					$items = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."exchange_bids WHERE status IN('new','coldpay','techpay') AND m_in = '$m_id'");
					foreach($items as $item){
						$id = $item->id;
						$trans_in = pn_strip_input(is_isset($item,'trans_in'));
						$to_account = pn_strip_input(is_isset($item,'to_account'));
						$dest_tag = pn_strip_input(is_isset($item,'dest_tag'));
						
						foreach($orders as $order_key => $order){
							$res_address = trim(is_isset($order,'address'));
							$currency_arr = explode('-', strtoupper($order['currency']));
							$currency = strtoupper($currency_arr[0]);
							$memo = pn_strip_input(is_isset($order,'memo'));
							if(!$memo or $memo == $dest_tag){
								if(
									$res_address and $res_address == $to_account or
									$res_address and strtoupper($res_address) == strtoupper($to_account) and $currency == 'USDT'
								){
									$res_status = $order['state'];
									$res_txid = $order['txid'];
									$confirmations = 0;
									if(isset($order['confirmations'], $order['confirmations'])){
										$confirmations = intval($order['confirmations']);
									}
									
									$realpay_st = array('done','accepted','succeed');
									$coldpay_st = array('processing','confirming','submitted');
									$coldpay_st_need = array('processing','confirming','submitted');
									
									$data = get_data_merchant_for_id($id, $item);
									
									$now_status = '';
									if(in_array($res_status, $realpay_st)){
										$now_status = 'realpay';
									}
									if(in_array($res_status, $coldpay_st)){
										$now_status = 'coldpay';
									}				
									if(in_array($res_status, $coldpay_st_need) and $confirmations >= $need_confirm and $need_confirm > 0){
										$now_status = 'realpay';
									}
									
									do_action('merchant_confirm_count', $id, $confirmations, $data['bids_data'], $data['direction_data'], $need_confirm, $this->name);
									
									if($now_status){
											
										$in_sum = $order['amount'];
										$in_sum = is_sum($in_sum, 12);
										$err = $data['err'];
										$status = $data['status'];
										$bid_m_id = $data['m_id'];
										$bid_m_script = $data['m_script'];  
											
										$bid_currency = strtoupper($data['currency']);
											
										$pay_purse = is_pay_purse('', $m_data, $bid_m_id);
												
										$bid_sum = is_sum($data['pay_sum'], 12);	
										$bid_corr_sum = apply_filters('merchant_bid_sum', $bid_sum, $bid_m_id);
											
										$invalid_ctype = intval(is_isset($m_data, 'invalid_ctype'));
										$invalid_minsum = intval(is_isset($m_data, 'invalid_minsum'));
										$invalid_maxsum = intval(is_isset($m_data, 'invalid_maxsum'));
										$invalid_check = intval(is_isset($m_data, 'check'));								
											
										if(!check_trans_in($bid_m_id, $res_txid, $id)){									
											if($err == 0 and $bid_m_id and $bid_m_id == $m_id and $bid_m_script and $bid_m_script == $this->name){
												if($bid_currency == $currency or $invalid_ctype > 0){
													if($in_sum >= $bid_corr_sum or $invalid_minsum > 0){
														
														unset($orders[$order_key]);
														
														$params = array( 
															'pay_purse' => $pay_purse,
															'trans_in' => $res_txid,
															'sum' => $in_sum,
															'bid_sum' => $bid_sum,
															'bid_status' => array('new','techpay','coldpay'),
															'bid_corr_sum' => $bid_corr_sum,
															'currency' => $currency,
															'bid_currency' => $bid_currency,
															'invalid_ctype' => $invalid_ctype,
															'invalid_minsum' => $invalid_minsum,
															'invalid_maxsum' => $invalid_maxsum,
															'invalid_check' => $invalid_check,
															'm_place' => $bid_m_id.'_cron',
															'm_id' => $m_id,
															'm_data' => $m_data,
															'm_defin' => $m_defin,
														);
														set_bid_status($now_status, $id, $params, $data['direction_data']); 

														break;
														
													} else {
														$this->logs($id . ' The payment amount is less than the provisions', $m_id);
													}
												} else {
													$this->logs($id.' In the application the wrong status', $m_id);
												}										
											} else {
												$this->logs($id . ' bid error', $m_id);
											}
										} else {
											$this->logs($id . ' Error check trans in!', $m_id);
										}
									}	
								}
							}
						}
					}
				}
			}
			catch (Exception $e)
			{
				$this->logs($e->getMessage(), $m_id);
				if($show_error and current_user_can('administrator')){
					die($e->getMessage());
				}
			}	
		}
	}
}

new merchant_garantex_crypto_code(__FILE__, 'Garantex Crypto Code');