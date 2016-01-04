<?php

/*
 * UserLogin Action Class.
 * @author Sunil.
 */

class LoginAction extends CAction{

	private $form_type;
	private $html;
	private $html_ano;
	private $seller_type;
	private $currentDateTime;
	private $newDateTime;
	private $listingid;
	private $sms_type    = 'OTP';
	public  $json_array;

	public function run(){

		$controller  = $this->getController();
		$model       = new LoginForm();
		$userredis   = new UserRedis();

		try{
			if(isset($_POST['UserLogin'])){
				$model->attributes  = $_POST['UserLogin'];

				if($model->validate()){

					$user=UserRegistration::model()->find('LOWER(user_email)=?',array(strtolower($model->user_email)));

					$userInfo  = $userredis->getUserinfo( trim( $user->uid ) );
					$user_array = json_decode($userInfo,TRUE);

					Yii::app()->session->open();

					Yii::app()->session['userid']  = $user->uid;
					Yii::app()->session['username']  = $user->user_name;
					Yii::app()->session['mobile']  = $user->user_mobile;
					Yii::app()->session['email']  = $user->user_email;

					if(isset($_POST['UserLogin']['form_name'])){

						$state = 'mob_notchanged';
						$this->seller_type = '';
						$this->listingid   = '';
						$mobileno = '';
						$mob_sta  = '';

						if($_POST['UserLogin']['form_name'] == 'register_user'){
							$html = $controller->renderPartial('/layouts/auth/_user_logout_form',array(),TRUE);
							$html_ano = "";
						}else if($_POST['UserLogin']['form_name'] == 'contact_user'){
							$html     = $controller->renderPartial('/contactseller/_user_mobile_confirm_form',array('listingid'=>$_POST['listingid']),TRUE);
							$html_ano = $controller->renderPartial('/layouts/auth/_user_logout_form',array(),TRUE);
						}else if($_POST['UserLogin']['form_name'] == 'mobile_change'){
														
							/*Yii::import('application.controllers.contact_seller.UpdateusermobileAction');
							 $mobileno_changed = 'mobile_changed';
							 $obj =new UpdateusermobileAction($_POST['userid'],$_POST['mobileno']);
							 $result = $obj->run();*/
							
							$userRedis  = new UserRedis();	
							$userInfo   = $userRedis->getUserinfo($_POST['userid']);
							$user_array = json_decode($userInfo,TRUE);
							
							$useridArray=UserRegistration::model()->findAll(array(
										'select'=>'uid',
										'condition'=>'user_mobile=:user_mobile',
										'params'=>array(':user_mobile'=>$_POST['mobileno']),
							));

							$user_array['mobile_status'] = '1';
							$user_array['mobile']        = $_POST['mobileno'];

							$userRedis->setUserinfo($_POST['userid'], json_encode($user_array));
							UserRegistration::model()->updateByPk($_POST['userid'],array('user_mobile'=>$_POST['mobileno']));
							UserRegistration::model()->updateByPk($_POST['userid'],array('user_mobile_status'=>'1'));

							if(!empty($useridArray)){

								foreach ($useridArray as $key=>$value){
									if($value['uid'] != '' && $_POST['userid'] != $value['uid']){
										$userRinfo  = $userRedis->getUserinfo($value['uid']);
										$userRarray = json_decode($userRinfo,TRUE);
										$userRarray['mobile_status'] = '0';
										$userRedis->setUserinfo($value['uid'], json_encode($userRarray));
										UserRegistration::model()->updateByPk($value['uid'],array('user_mobile_status'=>'0'));
									}
								}

							}

							$html =$controller->renderPartial('/layouts/auth/_user_logout_form',array(),TRUE);
							
							$json_array = array('status'	=> 'sucess',
									             'html'	=> $html,
							);
							echo json_encode($json_array);
							exit();
						}
					}

					if(isset($_POST['result_user_mobile'])){
						$state   = 'mob_changed';
						$mob_sta = 'notverified';

						if(isset($_POST['listingid'])){
							$this->listingid = $_POST['listingid'];

							$this->currentDateTime = date('H:i');
							$this->newDateTime     = date('h:i A', strtotime($this->currentDateTime));

							if($this->currentDateTime >= Yii::app()->params['starttime'] && $this->currentDateTime <= Yii::app()->params['endtime']){
								$this->seller_type = 'view';
							}else{
								if($userredis->getListingDetails($this->listingid, 'status')){
									$this->seller_type = 'view';
								}else{
									$this->seller_type = 'call';
								}
							}

						}

						if($_POST['result_user_mobile'] != $user->user_mobile){
							$mobileno = $_POST['result_user_mobile'];
							$user_array['mobile'] = $mobileno;
							$sms_result   = Yii::app()->sms->send_message($mobileno,$this->sms_type,$user->uid);
							$html = $controller->renderPartial('/contactseller/_otp_form',array('seller_type'=>$this->seller_type,'user_array'=>$user_array),TRUE);
							$html_ano = $controller->renderPartial('/layouts/auth/_user_logout_form',array(),TRUE);
						}

					}
					$json_array = array('status'	=>'sucess',
										 'state'    => $state, 	
                                         'message'	=> 'User Details Verified Sucessfully',
                                         'html'     => $html,
										 'html_ano' => $html_ano,
										 'listingid'=> $this->listingid,
										 'userid'   => $user->uid,
					                     'mobile_number'=> $mobileno,
										 'mob_sta'      => $mob_sta,
										 'url'		=> $controller->createUrl('contactseller/mobilenumberverification')	
					);

					echo json_encode($json_array);
				}else{
					$_error           = $model->getErrors();
					$_error['status'] = 'error';
					echo json_encode($_error);
				}

			}
		}catch ( Exception $e ) {
			echo 'Caught exception: ', $e->getMessage (), "\n";
			exit();
		}

	}

}

?>