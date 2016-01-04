<?php

/*
 * Mobilenumberverification Action calss.
 * @author Sunil
 */

class MobilenumberverificationAction extends CAction{

	public $json_array = array();
	public $listingid;
	private $seller_type;
	private $currentDateTime;
	private $newDateTime;


	public function run(){

		try{

			$controller = $this->getController();
			$userRedis  = new UserRedis();

			if(isset($_POST['listingid'])){
				$this->listingid = $_POST['listingid'];

				$this->currentDateTime = date('H:i');
				$this->newDateTime     = date('h:i A', strtotime($this->currentDateTime));

				if($this->currentDateTime >= Yii::app()->params['starttime'] || $this->currentDateTime <= Yii::app()->params['endtime']){
					$this->seller_type = 'view';
				}else{
					if($userRedis->getListingDetails($this->listingid, 'status')){
						$this->seller_type = 'view';
					}else{
						$this->seller_type = 'call';
					}
				}

				$listingInfo   = $userRedis->getListingDetails($this->listingid,'sellerinfo');
				$listing_array = json_decode($listingInfo,TRUE);
			}

			if(isset($_POST['userid'])){

				$userInfo  = $userRedis->getUserinfo( trim( $_POST['userid'] ) );
				$user_array = json_decode($userInfo,TRUE);
				$userid   = $user_array['uid'];

				if(isset($_POST['mobileno'])){

					if($_POST['mobileno'] != $user_array['mobile']){
						$mobileno = $_POST['mobileno'];
					}else{
						$mobileno = $user_array['mobile'];
					}

				}


				if($mobileno != ''){

					$user_deatail = array('userid'		=>$user_array['uid'],
									   'user_email'		=>$user_array['email_id'],
									   'user_mobile'	=>$mobileno,
									   'user_username'	=>$user_array['user_name']
					);

					if(isset($_POST['otp_number'])){

						$_otpValue     = $userRedis->getOtpinfo($userid ,$mobileno);
							
						if(isset($_otpValue) && $_otpValue != ''){

							if($_POST['otp_number'] == $_otpValue){

								if(isset($_POST['user_registration'])){
									$html = $controller->renderPartial('/layouts/auth/_mobile_verified_form',array(),TRUE);
								}

								if($this->seller_type == 'call'){
									$html = $controller->renderPartial('_connect_seller_form',$user_deatail,TRUE);
								}else if($this->seller_type == 'view'){
									if(isset(Yii::app()->request->cookies['cookie_id'])){
										$cookieid  = Yii::app()->request->cookies['cookie_id'];
										$userRedis->setUserContacted($cookieid,$this->listingid);
									}
									$html = $controller->renderPartial('_view_contact_details_form',array('listing_array'=>$listing_array,'user'=>$user_array,'mobileno'=>$mobileno),TRUE);
								}

								if($_POST['mobileno'] == $user_array['mobile']){

									Yii::app()->session->open();
									Yii::app()->session['userid']   = $user_array['uid'];
									Yii::app()->session['username'] = $user_array['user_name'];
									Yii::app()->session['mobile']   = $user_array['mobile'];
									Yii::app()->session['email']    = $user_array['email_id'];

									$useridArray=UserRegistration::model()->findAll(array(
										'select'=>'uid',
										'condition'=>'user_mobile=:user_mobile',
										'params'=>array(':user_mobile'=>$mobileno),
									));

									$user_array['mobile_status'] = '1';
									$userRedis->setUserinfo($userid, json_encode($user_array));
									$updatemobilestatus = UserRegistration::model()->updateByPk($userid,array('user_mobile_status'=>'1'));

									if(!empty($useridArray)){

										foreach ($useridArray as $key=>$value){
											if($value['uid'] != '' && $userid != $value['uid']){
												$userRinfo  = $userRedis->getUserinfo($value['uid']);
												$userRarray = json_decode($userRinfo,TRUE);
												$userRarray['mobile_status'] = '0';
												$userRedis->setUserinfo($value['uid'], json_encode($userRarray));
												UserRegistration::model()->updateByPk($value['uid'],array('user_mobile_status'=>'0'));
											}
										}

									}

									$html_ano = $controller->renderPartial('/layouts/auth/_user_logout_form',array(),TRUE);
									$state    = "loggedin";
								}else{
									$html_ano = "";
									$state    = "notloggedin";
								}


								$json_array = array('status'   => 'sucess',
													 'state'	=> $state, 
											         'html'     => $html,
												     'html_ano' => $html_ano, 	
											         'url'      => $controller->createUrl('contactseller/call'),
												     'userid'   => $userid,
												     'listingid'=> $this->listingid,
												     'seller_type' => $this->seller_type,
													 'mobile_number'=> $mobileno,
											         'message'  => Yii::t('app','site_otpverified')
								);

								echo json_encode($json_array);
							}else{

								$json_array = array('status'  => 'failure',
											 'message' => Yii::t('app','site_wrongotp')   
								);

								echo json_encode($json_array);
							}
						}else{

							$json_array = array('status'  => 'failure',
										 'message' => Yii::t('app','site_validotp')   
							);
							echo json_encode($json_array);
						}
					}else{

						$_misscallvalue = $userRedis->getMissedcallverification($mobileno);

						if($_misscallvalue != '' && $_misscallvalue == '1'){

							if(isset($_POST['user_registration'])){
								$html = $controller->renderPartial('/layouts/auth/_mobile_verified_form',array(),TRUE);
							}

							if($this->seller_type == 'call'){
								$html = $controller->renderPartial('_connect_seller_form',$user_deatail,TRUE);
							}else if($this->seller_type == 'view'){
								if(isset(Yii::app()->request->cookies['cookie_id'])){
									$cookieid  = Yii::app()->request->cookies['cookie_id'];
									$userRedis->setUserContacted($cookieid,$this->listingid);
								}
								$html = $controller->renderPartial('_view_contact_details_form',array('listing_array'=>$listing_array,'user'=>$user_array,'mobileno'=>$mobileno),TRUE);
							}

							if($_POST['mobileno'] == $user_array['mobile']){
								Yii::app()->session->open();
								Yii::app()->session['userid']   = $user_array['uid'];
								Yii::app()->session['username'] = $user_array['user_name'];
								Yii::app()->session['mobile']   = $user_array['mobile'];
								Yii::app()->session['email']    = $user_array['email_id'];
									
								$user_array['mobile_status'] = '1';
								$userRedis->setUserinfo($userid, json_encode($user_array));
								$updatemobilestatus = UserRegistration::model()->updateByPk($userid,array('user_mobile_status'=>'1'));

								if(!empty($useridArray)){
									foreach ($useridArray as $key=>$value){
										if($value['uid'] != '' && $userid != $value['uid']){
											$userRinfo  = $userRedis->getUserinfo($value['uid']);
											$userRarray = json_decode($userRinfo,TRUE);
											$userRarray['mobile_status'] = '0';
											$userRedis->setUserinfo($value['uid'], json_encode($userRarray));
											UserRegistration::model()->updateByPk($value['uid'],array('user_mobile_status'=>'0'));
										}
									}

								}
								$html_ano = $controller->renderPartial('/layouts/auth/_user_logout_form',array(),TRUE);
								$state    = "loggedin";
							}else{
								$html_ano = "";
								$state    = "notloggedin";
							}

							$json_array = array('status'  => 'sucess',
												 'state'	=> $state,
											     'html'    => $html,
												 'html_ano'=> $html_ano, 	
										         'message' => Yii::t('app','site_missedcallverification'),
											     'userid'  => $userid,
												 'listingid'=> $this->listingid,
							                     'seller_type' => $this->seller_type,
												 'mobile_number'=> $mobileno,	
											     'url'     => $controller->createUrl('contactseller/call')
							);

							echo json_encode($json_array);

						}else if($_misscallvalue == ''){

							$json_array = array('status'  => 'failure',
										         'message' => Yii::t('app','site_missedcallnotverified')   
							);
							echo json_encode($json_array);

						}

					}
				}

			}

		}catch ( Exception $e ) {
			Yii::app()->session->destroy();
			echo 'Caught exception: ', $e->getMessage (), "\n";
			exit();
		}

	}
}

?>