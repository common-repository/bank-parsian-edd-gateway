<?php
/**
	Plugin Name: Bank Parsian  EDD gateway-
	Version: 1.0
	Description:  این افزونه درگاه بانک پارسیان و شبکه پرداخت الکترونیک شاپرک را به افزونه فروش فایل EDD اضافه می‌کند.
	Plugin URI: http://sspr.ir/Parsian-iran-edd-gateway
	Author: sajad salehi chegeni
	Author URI: http://www.sspr.ir/
	License: GPLv2
	Tested up to: 4.4
**/

include "menu_setup.php";

if(class_exists("nusoap_base")== false)
{
    require_once('lib/nusoap.php');

}
	
/////---------------------------------------------------
function edd_bpeddg_rial ($formatted, $currency, $price) {

	return $price . ' ریال';
}
add_filter( 'edd_rial_currency_filter_after', 'edd_bpeddg_rial',10, 3 );
/////------------------------------------------------
function bpeddg_add_gateway ($gateways) {
	$gateways['Parsian'] = array('admin_label' => 'درگاه بانک پارسیان ', 'checkout_label' => 'بانک پارسیان ');
	return $gateways;
}
add_filter( 'edd_payment_gateways', 'bpeddg_add_gateway' );

function bpeddg_cc_form () {
	do_action( 'bpeddg_cc_form_action' );
}
add_filter( 'edd_Parsian_cc_form', 'bpeddg_cc_form' );

/////--------------------------------------------------

function bpeddg_send_data($purchase_data) 
{
session_start();
	//error_reporting(0);
	global $edd_options;
    
	$bpeddg_ ='https://pec.shaparak.ir/pecpaymentgateway/eshopservice.asmx?wsdl';

	$i=0;
	do 
    {
	$pec =new nusoap_client($bpeddg_,'wsdl');
        $soapProxy = $pec->getProxy();
		$i++;
	} 
    while($pec->getError() and $i<3);
    
    
	// Check for Connection error
	if ($pec->getError())
    {
		edd_set_error( 'pay_00', 'P00:خطایی در اتصال پیش آمد،مجدد تلاش کنید...' );
		edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
	}
	$payment_data = array( 
		'price' => $purchase_data['price'], 
		'date' => $purchase_data['date'], 
		'user_email' => $purchase_data['post_data']['edd_email'],
		'purchase_key' => $purchase_data['purchase_key'],
		'currency' => $edd_options['currency'],
		'downloads' => $purchase_data['downloads'],
		'cart_details' => $purchase_data['cart_details'],
		'user_info' => $purchase_data['user_info'],
		
		'status' => 'pending',
	);
	
	$payment = edd_insert_payment($payment_data);

    $post_url= "https://pec.shaparak.ir/pecpaymentgateway/?au=".$authority;

    
	if ($payment) 
    {
		$_SESSION['Parsian_payment'] = $payment;
		$callbackUrl= add_query_arg('order', 'Parsian', get_permalink($edd_options['success_page']));

		$orderId =intval(date("His").$payment+rand(1,3000));
		$amount = $purchase_data['price'];
		$localDate = date("Ymd");
		$localTime = date("His");
		$additionalData = "Purchase key: ".$purchase_data['purchase_key'];
		$authority =0;
		$status=1;
      $pin = $edd_options['pin'];
/////////////////PAY REQUEST PART/////////////////////////
	$parameters= array(
			'pin' => $pin,
			'amount' => $amount,
			'orderId' => $orderId,
			'callbackUrl' => $callbackUrl,
			'authority' => $authority,
			'status' => $status

		);
		// Call the SOAP method
		
		
		             
	$send=array($parameters) ;
		
	$res=$pec->call('PinPaymentRequest',$send);

		 $authority=$res['authority'];
		 $status=$res['status'];
			  
$_SESSION['authority']= $authority;

         $post_url= 'https://pec.shaparak.ir/pecpaymentgateway/?au='.$authority;
///************END of PAY REQUEST***************///
		if (($authority) and ($status==0)) 
        {
			// Successfull Pay Request
            
echo '<form id="ParsianPay" name="ParsianPay" method="post" action="'.$post_url .'">
      <input type="hidden" name="authority" value="'.$authority.'">
      <input type="hidden" name="RedirectURL" value="'.$callbackUrl.'">
      </form> 
     <script type="text/javascript">
     function setAction(element_id)   
     { 
    var frm = document.getElementById(element_id);
     if(frm)
        {
    frm.action = '."'https://pec.shaparak.ir/pecpaymentgateway/?au='".''.$authority.';  
               }
           } 
      setAction('."'ParsianPay'".');
   </script>       
<script type="text/javascript">document.ParsianPay.submit();</script>
                
			';
			exit();
  		}
        else
        {
			edd_update_payment_status($payment, 'failed');
			edd_insert_payment_note( $payment, 'Pec02:'.bpeddg_CheckStatus((int)$status) );
			edd_set_error( 'pec_02', ':Pec02'.bpeddg_CheckStatus((int)$status) );
			edd_send_back_to_checkout('?payment-mode='. $purchase_data['post_data']['edd-gateway']);
		}
	}
    else
    {
		edd_set_error( 'pec_01', 'P01:خطا در ایجاد پرداخت، لطفاً مجدداً تلاش کنید...' );
		edd_send_back_to_checkout('?payment-mode='.$purchase_data['post_data']['edd-gateway']);
	}
}
add_action('edd_gateway_Parsian', 'bpeddg_send_data');
/////----------------------------------------------------
function bpeddg_verify() 
{
session_start();	
 error_reporting(0);
	global $edd_options;
	$pin = $edd_options['pin'];
    		

	$bpeddg= 'https://pec.shaparak.ir/pecpaymentgateway/eshopservice.asmx?wsdl';
	if (isset($_GET['order'])
	and bpeddg_sanitize($_GET['order']) == 'Parsian'
	and $_SESSION['Parsian_payment'] == intval($_POST['orderId '])
	and isset($_REQUEST['rs']) 
	and isset($_REQUEST['au'])
    
		and $status==0)
	 {
		$payment = $_SESSION['Parsian_payment'];
	
	$status=bpeddg_sanitize($_REQUEST['rs']);		
	$authority=bpeddg_sanitize($_REQUEST['au']);
		//Connect to WebService
		$i=0;
		do {
			$pec = new nusoap_client($bpeddg,'wsdl');
			$i++;
		}while ( $pec->getError() and $i<5 );//Check for connection errors
		if ($pec->getError()){
			edd_set_error( 'ver_00', 'V00:تراکنش ناموفق بود.<br>اگر وجهی از حساب شما کسر شده باشد، تا پایان روز جاری به حساب شما باز خواهد گشت.' );
			edd_update_payment_status($_SESSION['Parsian_payment'], 'failed');
			edd_insert_payment_note( $_SESSION['Parsian_payment'], 'V00:'.'<pre>'.$pec->getError().'</pre>' );
			edd_send_back_to_checkout('?payment-mode=Parsian');
		}
	
		$parameters = array(
			'pin' => $pin,
			'authority' =>$authority,
	    	'status' => $status 
			
		);
//////////////////VERIFY REQUEST///////////////////////
	$do_publish = false;
		if (!edd_is_test_mode()) {
			// Call the SOAP method
			$Result_pec =$pec-> call('PinPaymentEnquiry',$parameters);
			$result_p=$Result_pec['status'];
			if ($result_p==0) {
				// Note: Successful Verify means complete successful sale was done.
				//SETTLE REQUEST
				$do_publish=true;
			}
			else 
			{
				//INQUIRY REQUEST
	 			$do_publish=false;
			  }
		    }
		else
		 {
			//in test mode
	edd_set_error('p_test','edd_is_test_mode' );
		edd_send_back_to_checkout('?payment-mode=Parsian');
		}
	
	
		if ($do_publish == true) {
			// Publish Payment
			$do_publish = false;
			edd_update_payment_status($payment, 'publish');
			edd_insert_payment_note( $payment, 'شماره تراکنش:'.$authority );
			echo "<script type='text/javascript'>alert('کد تراکنش خرید بانک : ".$authority."');</script>";
		}
	
		}

		
	
	else if (isset ($_GET['order']) 
	and bpeddg_sanitize($_GET['order']) == 'Parsian' 
	and isset ($_POST['orderId '])
    and $_SESSION['Parsian_payment'] == intval($_POST['orderId ']) 
	and $status!= '0')
	{
  		edd_update_payment_status($_SESSION['Parsian_payment'], 'failed');
		edd_insert_payment_note($_SESSION['Parsian_payment'], 'V02:'.CheckStatus($status) );
		edd_set_error($status, CheckStatus($status) );
		edd_send_back_to_checkout('?payment-mode=Parsian');
	}	
}


add_action('init', 'bpeddg_verify');
/////-----------------------------------------------
function bpeddg_add_settings ($settings) {
	$Parsian_settings = array (
		array (
			'id'		=>	'Parsian_settings',
			'name'		=>	'<strong>پيکربندي درگاه بانک پارسیان</strong><br>(در حالت آزمایشی این قسمت را تکمیل نکنید)',
			'desc'		=>	'پيکربندي درگاه بانک پارسیان ایران با تنظيمات فروشگاه',
			'type'		=>	'header'
		),
		array (
			'id'		=>	'pin',
			'name'		=>	'پین یا کد پذیرنده   ',
			'desc'		=>	'',
			'type'		=>	'text',
			'size'		=>	'medium'
		),
	
		
	);
	return array_merge( $settings, $Parsian_settings );
}
add_filter('edd_settings_gateways', 'bpeddg_add_settings');
/////-------------------------------------------------


function bpeddg_CheckStatus($status) {
$tmess="شرح خطا: ";
	switch($status){
	
		case '0' :
			break;
		
		case '20' :
		case '22' :
			$tmess="پين فروشنده درست نميباشد.";
			break;
			
		case '30' :
			$tmess="عمليات قبلا با موفقيت انجام شده است.";
			break;
			
		case '34' :
			$tmess="شماره تراکنش فروشنده درست نميباشد.";
			break;
		
		DEFAULT :
			$tmess="خطاي نامشخص [st:$status]";
			
	}	
	
return $status.': '.$tmess;
	
}

function bpeddg_cleanInput($input) {
 
  $search = array(
    '@<script[^>]*?>.*?</script>@si',   // Strip out javascript
    '@<[\/\!]*?[^<>]*?>@si',            // Strip out HTML tags
    '@<style[^>]*?>.*?</style>@siU',    // Strip style tags properly
    '@<![\s\S]*?--[ \t\n\r]*>@'         // Strip multi-line comments
  );
 
    $output = preg_replace($search, '', $input);
    return $output;
  }
?>
<?php
function bpeddg_sanitize($input) {
    if (is_array($input)) {
        foreach($input as $var=>$val) {
            $output[$var] = bpeddg_sanitize($val);
        }
    }
    else {
        if (get_magic_quotes_gpc()) {
            $input = stripslashes($input);
        }
        $input  = bpeddg_cleanInput($input);
        $output = mysql_real_escape_string($input);
    }
    return $output;
}
?>