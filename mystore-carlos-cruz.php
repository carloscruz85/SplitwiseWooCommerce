<?php

/**
* @package Dixp-mine
* @version 1.0
*/
/*
Plugin Name: My Splitwise Store
Plugin URI: https://github.com/carloscruz85/SplitwiseWooCommerce
Description: Plugin to extend WooCommerce to make payments with Splitwise API 
Author: Carlos Cruz
Version: 1.0
Author URI: carloscruz85.com
*/

//configuration

date_default_timezone_set('America/El_Salvador');
error_reporting(E_ALL);
ini_set('display_errors','On');


function register_my_session(){
    if( ! session_id() ) {
        session_start();
    }
}

add_action('init', 'register_my_session');

//add custom seettings
//after activate
register_activation_hook(__FILE__, 'my_plugin_activate');
add_action('admin_init', 'my_plugin_redirect');

function my_plugin_activate() {
    add_option( 'client_credentials_identifier', '0' );
    add_option( 'client_credentials_secret', '0' );
    add_option( 'callback_uri', '0' );
    add_option('my_plugin_do_activation_redirect', true);
}

function my_plugin_redirect() {
    if (get_option('my_plugin_do_activation_redirect', false)) {
        delete_option('my_plugin_do_activation_redirect');
        wp_redirect('admin.php?page=configuration_page');
    }
}




//create a page if no exist
$name_of_the_page = 'Splitwise Checkout Page';
if( get_page_by_title($name_of_the_page) == NULL ) {
    $my_post = array(
      'post_title'    => $name_of_the_page,
      'post_content'  => '',
      'post_status'   => 'publish',
      'guid'          => $name_of_the_page,
      'post_type'     => 'page',
      'post_content'  => '[mycheckout]'
    );
    $homepage_id =  wp_insert_post( $my_post );
}




function mycheckout_func( $atts ){
    require 'risan/vendor/autoload.php';

    // Create an instance of Risan\OAuth1\OAuth1 class.
    $signer = new Risan\OAuth1\Signature\HmacSha1Signer();
    $oauth1 = Risan\OAuth1\OAuth1Factory::create([
        'client_credentials_identifier' => get_option( 'client_credentials_identifier' ),
        'client_credentials_secret'     => get_option( 'client_credentials_secret' ),
        'temporary_credentials_uri'     => 'https://secure.splitwise.com/oauth/request_token',
        'authorization_uri'             => 'https://secure.splitwise.com/oauth/authorize',
        'token_credentials_uri'         => 'https://secure.splitwise.com/oauth/access_token',
        'callback_uri'                  =>  get_option( 'callback_uri' )
    ],$signer);


    if (isset($_SESSION['token_credentials'])) {
        // Get back the previosuly obtain token credentials (step 3).
        $tokenCredentials = unserialize($_SESSION['token_credentials']);
        $oauth1->setTokenCredentials($tokenCredentials);

        // STEP 4: Retrieve the user's tweets.
        // It will return the Psr\Http\Message\ResponseInterface instance.

        $urlTesting ='https://secure.splitwise.com/api/v3.0/test';
        $testInfo = $oauth1->request('GET', $urlTesting);
        $testArray = json_decode($testInfo->getBody()->getContents(), true);

        //create expense
        $urlToCreateAExpense = 'https://secure.splitwise.com/api/v3.0/create_expense';

        $headers_to_create_a_expense = array(
          'oauth_consumer_key'      => '3CeLgTGDwrq4VYmzfpP8P3PwbL7npd4U30F23pue',
          'oauth_token'             => $testArray['token']['secret'],
          'Authorization'           => 'Bearer '.$testArray['token']['secret'],
          // 'Content-Type'            => 'application/json; charset=utf-8'
          'Content-Type'            => 'application/x-www-form-urlencoded'
        );

        $descriptionToExpense = '';
        $totalToExpense = 0;
        global $woocommerce;
         $items = $woocommerce->cart->get_cart();
             foreach( $items as $item => $values ) {
                 $_product =  wc_get_product( $values['data']->get_id()); 
                 // echo "<b>".$_product->get_title().'</b>  <br> Quantity: '.$values['quantity'].'<br>'; 
                 $price = get_post_meta($values['product_id'] , '_price', true)*$values['quantity'];
                 // echo "  Price: ".$price."<br>";
                 $descriptionToExpense  .= $values['quantity'].' '.$_product->get_title().' $'.$price.' , ';
                    $totalToExpense += $price;
             }
             $descriptionToExpense .= 'TOTAL: $'.$totalToExpense;
             // print_r($descriptionToExpense);

        $parametersToCreateAExpense = array(
            'cost'                  => $totalToExpense,
            'description'           => $descriptionToExpense,
            'payment'               => '0',
            'users__0__user_id'     => '15586971',
            'users__0__paid_share'  => $totalToExpense,
            'users__0__owed_share'  => '0.0',
            'users__1__user_id'     => $testArray['token']['user_id'],
            'users__1__paid_share'  => '0.0',
            'users__1__owed_share'  => $totalToExpense
        );


        

        $responseRequest = $oauth1->request('POST', $urlToCreateAExpense,[
            'form_params' => $parametersToCreateAExpense
        ]
);

        $res = json_decode($responseRequest->getBody()->getContents(), true);

        if(isset($res['expenses'][0]['id'])){

            // echo 'Contenido del responseRequest<pre>';
            //     print_r($res);
            // echo '</pre>';  
            echo '<h2>'.$descriptionToExpense.'</h2>';
            echo '<h1>Thank you, Your purchase has been processed successfully.</h1>';
            $woocommerce->cart->empty_cart();
            // unset($_SESSION['token_credentials']);
        }

 


        // Convert the response to array and display it.
    } elseif (isset($_GET['oauth_token']) && isset($_GET['oauth_verifier'])) {

        // Get back the previosuly generated temporary credentials (step 1).
        // if(isset($_SESSION['temporaryCredentials'])){
            $temporaryCredentials = unserialize($_SESSION['temporary_credentials']);
            unset($_SESSION['temporary_credentials']);

            // STEP 3: Obtain the token credentials (also known as access token).
            // echo 'step 3 <br>';
            $tokenCredentials = $oauth1->requestTokenCredentials($temporaryCredentials, $_GET['oauth_token'], $_GET['oauth_verifier']);

            // Store the token credentials in session for later use.
            // echo 'token_credentials have';
            $_SESSION['token_credentials'] = serialize($tokenCredentials);
            // this basically just redirecting to the current page so that the query string is removed.
            header('Location: ' . (string) $oauth1->getConfig()->getCallbackUri());
            exit();


    } else {
        // STEP 1: Obtain a temporary credentials (also known as the request token)
        echo 'step 1 <br>';
        $temporaryCredentials = $oauth1->requestTemporaryCredentials();

        // Store the temporary credentials in session so we can use it on step 3.
        $_SESSION['temporary_credentials'] = serialize($temporaryCredentials);

        // STEP 2: Generate and redirect user to authorization URI.
        $authorizationUri = $oauth1->buildAuthorizationUri($temporaryCredentials);
        header("Location: {$authorizationUri}");
        exit();
    }

}

add_shortcode( 'mycheckout', 'mycheckout_func' );


add_action( 'admin_menu', 'mbti_result' );
function mbti_result(){
    add_menu_page('My Splitwise Store Settings', 'My Splitwise Store Settings', 'read', 'configuration_page', 'configuration_page','dashicons-admin-settings');
}

function configuration_page(){

    if(isset($_POST['client_credentials_identifier']) && isset($_POST['client_credentials_secret']) && isset($_POST['callback_uri'])){

        if(update_option( 'client_credentials_identifier', $_POST['client_credentials_identifier'] ) || update_option( 'client_credentials_secret', $_POST['client_credentials_secret'] ) || update_option( 'callback_uri', $_POST['callback_uri'] ))
        echo '<h1>Credentials Saved</h1>';
    else echo '';
    }else{
        ?>
        <h2>Please fill the setting data form</h2>
        <a href="https://secure.splitwise.com/oauth_clients">Get data from splitwise</a><hr>
        <form action="<?php echo get_site_url().'/wp-admin/admin.php?page=configuration_page';?>" method="POST">
        <label for="">client_credentials_identifier</label>: 
        <input type="text" name="client_credentials_identifier" value="<?php echo get_option( 'client_credentials_identifier' ) ?>"><br>

        <label for="">client_credentials_secret</label>: 
        <input type="text" name="client_credentials_secret" value="<?php echo get_option( 'client_credentials_secret' ) ?>"><br>


        <label for="">callback_uri</label>: 
        <input type="text" name="callback_uri" value="<?php echo get_option( 'callback_uri' ) ?>"><br>
        <input type="submit" value="Save">
        </form>
        <?php
    }
}
?>