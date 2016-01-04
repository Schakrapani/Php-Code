<?php

/*
 * Callstatus Action calss.
 * @author Sunil
 * To check the status of the call.
 */

class CallstatusAction extends CAction{

	public $response_array = array();
	public $curl_call_result;
	public $curl_call_http_code;
	public $curl_call_error_code;
	private $listingid;

	public function run(){
		try{
				
			Yii::app()->session->open();
			$controller = $this->getController();
			if(isset($_POST['listingid'])){
				$this->listingid = $_POST['listingid'];
			}

			if(isset($_POST['userid'])){

				$responseid = $_POST['sid'];
				$userid     = $_POST['userid'];
				$mobileno   = $_POST['mobileno'];

				$userRedis     = new UserRedis();
				$listingInfo   = $userRedis->getListingDetails($this->listingid,'sellerinfo');
				$listing_array = json_decode($listingInfo,TRUE);
				
				$userInfo    = $userRedis->getUserinfo($userid);
				$user_array  = json_decode($userInfo,TRUE);
				
				//$user_array['mobile'] = $mobileno;

				$exotel_url = "https://". Yii::app()->params['exotel_sid'] .":". Yii::app()->params['exotel_token'] ."@twilix.exotel.in/v1/Accounts/". Yii::app()->params['exotel_sid'] ."/Calls/".$responseid;

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $exotel_url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_FAILONERROR, 0);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

				$curl_call_result     = curl_exec($ch);
				$curl_call_error_code = curl_error($ch);
				$curl_call_http_code  = curl_getinfo($ch ,CURLINFO_HTTP_CODE);

				curl_close($ch);

				if ($curl_call_error_code) {

					$html = $controller->renderPartial('_call_disconnected_form',array(),TRUE);

					$response_array = array ('status'    => 'Failure',
										  'http_code' => $curl_call_http_code,
										  'error_code'=> $curl_call_error_code,
				                          'html'      => $html
					);

					return json_encode ( $response_array );
				}else{

					if($curl_call_http_code == '200'){

						$call_details = simplexml_load_string ( $curl_call_result );
						$call_details = json_encode($call_details);
						$call_details = json_decode($call_details,TRUE);

						$call_status = $call_details['Call']['Status'];
						$call_sid    = $call_details['Call']['Sid'];
						$call_from   = $call_details['Call']['From'];
						$call_to     = $call_details['Call']['To'];

						if(isset(Yii::app()->request->cookies['cookie_id'])){
							$cookieid  = Yii::app()->request->cookies['cookie_id'];
							$userRedis->setUserContacted($cookieid,$this->listingid);
						}

						$html = $controller->renderPartial('_call_disconnected_form',array('listing_array'=>$listing_array,'user'=>$user_array,'mobileno'=>$mobileno),TRUE);

						$response_array = array ('status'      => 'Sucess',
										  'call_Status' => $call_status,
										  'call_Sid'    => $call_sid,
										  'html'	    => $html,
										  'url'	        => $controller->createUrl('contactseller/sendemail'),
										  'listingid'   => $this->listingid,
										  'userid'      => $userid,
										  'mobileno'    => $mobileno		
						);

						echo json_encode($response_array);
					}
				}

			}

		}catch ( Exception $e ) {
			echo 'Caught exception: ', $e->getMessage (), "\n";
			exit();
		}

	}

}

?>