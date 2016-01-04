<?php

/*
 * @author Sunil.
 * Emailverification Action calss.
 * Verified the user email status in DB and Redis Cache.
 * And update the password if not set.
 */

class EmailverificationAction extends CAction{

	private $_activationcode;
	private $_userid;
	public  $response_array = array();

	public function run(){

		$controller = $this->getController();

		try{
			if(isset($_GET['activation_code'])){

				$this->_activationcode = trim($_GET['activation_code']);

				$userArray=UserRegistration::model()->find(array(
				   									   'select'=>'*',
													   'condition'=>'user_activation_code=:user_activation_code',
													   'params'=>array(':user_activation_code'=>$this->_activationcode),
				));

				$this->_userid = $userArray['uid'];

				if($userArray['user_email_status'] == '0'){

					$userRedis  = new UserRedis();
					$userInfo   = $userRedis->getUserinfo($this->_userid);
					$userRArray = json_decode($userInfo,TRUE);

					$userRArray['email_status'] = '1';
					$userRedis->setUserinfo($this->_userid, json_encode($userRArray));

					$emailverified = UserRegistration::model()->updateByPk($this->_userid,array('user_email_status'=>'1'));
						
					if($emailverified != '1'){
						$response_array = array('status'  => 'failure',
					     'message' => 'Email Status Not Update Successfully.'
						);
						echo json_encode($response_array);
						exit();
					}
						
				}
					
				if($userArray['user_pass'] == ''){
					echo $controller->renderPartial('/layouts/auth/_user_email_verify_form',array('set_password'=>'yes','userid'=>$this->_userid),TRUE);
				}else if(isset($_GET['forgot_password'])){
					echo $controller->renderPartial('/layouts/auth/_user_email_verify_form',array('set_password'=>'yes','userid'=>$this->_userid),TRUE);
				}else{
					echo $controller->renderPartial('/layouts/auth/_user_email_verify_form',array('userid'=>$this->_userid),TRUE);
				}

			}

		}catch ( Exception $e ) {
			echo 'Caught exception: ', $e->getMessage (), "\n";
			exit();
		}
	}
}

?>