<?php

/*
 * Call Action calss.
 * @author Sunil.
 * Initiate the call between buyer and seller.
 * And also sending email to buyer and seller. once the call is Initiated.
 */

class CallAction extends CAction{

	private $json_array = array();
	private $seller;
	private $buyer;
	private $listingid;
	private $_emailtype = 'contact_buyer';
	private $_tobuyeremail;
	private $_toselleremail;
	private $_userid;

	public function run(){
		try{
			Yii::app()->session->open();
			$userRedis = new UserRedis();
			
			$controller = $this->getController();
			if(isset($_POST['listingid'])){
				$this->listingid = $_POST['listingid'];
			}
			
			if(isset(Yii::app()->session['userid'])){
				$this->_userid       = Yii::app()->session['userid'];
			}else{
				$this->_userid       = $_POST['userid'];
			}
			
			if(isset($_POST['mobileno']) && $_POST['mobileno'] != ''){
				$this->buyer         = $_POST['mobileno'];
			}
			
			$userInfo    = $userRedis->getUserinfo($this->_userid);
			$listingInfo = $userRedis->getListingDetails($this->listingid,'sellerinfo');

			$user_array    = json_decode($userInfo,TRUE);
			$listing_array = json_decode($listingInfo,TRUE);
			
			$this->_tobuyeremail = $user_array['email_id'];

			/*$seller     = $listing_array['phoneNo'];
			$this->_toselleremail  = $listing_array['email'];*/
			
			$seller     = '08122219504';
			$this->_toselleremail  = 'balji.s87@gmail.com';

			$call_output = Yii::app()->call->call_seller($seller,$this->buyer);
			$call_result = json_decode($call_output,TRUE);
			
			$user_array['mobile'] = $this->buyer;

			if($call_result['status'] == 'Success'){

				if($call_result['call_status'] == 'in-progress'){

					/*
					 * Need to do log here for sending emails.
					 */

					$send_email_buyer  = Yii::app()->email->email_send($this->_emailtype,$this->_tobuyeremail,$this->listingid,$user_array['user_name']);
					$this->_emailtype  = 'contact_seller';
					$send_email_seller = Yii::app()->email->email_send($this->_emailtype,$this->_toselleremail,$this->_userid,$user_array['user_name']);


					$html = $controller->renderPartial('_call_in_progress_form',array('listing'=>$listing_array,'user'=>$user_array),TRUE);

					$json_array = array('status'      =>'sucess',
                                             'html'        => $html,
                                             'responsesid' => $call_result['call_ressid'],
                                             'userid'      => $this->_userid,
                                             'listingid'   => $this->listingid,
											 'mobileno'      => $this->buyer, 
                                             'url'         => $controller->createUrl('contactseller/callstatus')
					);

					echo json_encode($json_array);
				}

			}else if($call_result['status'] == 'Failure'){

				$html = $controller->renderPartial('_call_failed_form',array('listing_array'=>$listing_array,'user'=>$user_array),TRUE);

				$json_array = array('status'       =>'failure',
                                         'html'         => $html,
                                         'userid'       => $this->_userid,
                                         'http_code'    => $call_result['http_code'],
										 'mobileno'      => $this->buyer, 
                                         'error_code'   => $call_result['error_code'] 
				);

				echo json_encode($json_array);

			}

		}catch ( Exception $e ) {
			echo 'Caught exception: ', $e->getMessage (), "\n";
			exit();
		}

	}

}

?>