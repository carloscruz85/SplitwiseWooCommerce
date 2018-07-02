<?php

// Includes the Composer autoload file.
require 'vendor/autoload.php';

// Start the session.
session_start();

// Create an instance of Risan\OAuth1\OAuth1 class.
$oauth1 = Risan\OAuth1\OAuth1Factory::create([
    'client_credentials_identifier' => '3CeLgTGDwrq4VYmzfpP8P3PwbL7npd4U30F23pue',
    'client_credentials_secret' => 'cdnLoyZlJI7LK8ZwZ3y3qowIesOWaYJJExOsxWJF',
    'temporary_credentials_uri' => 'https://secure.splitwise.com/oauth/request_token',
    'authorization_uri' => 'https://secure.splitwise.com/oauth/authorize',
    'token_credentials_uri' => 'https://secure.splitwise.com/oauth/access_token',
    'callback_uri' => 'http://localhost/oauth1_1/credentials.php',
]);


if (isset($_SESSION['token_credentials'])) {
    // Get back the previosuly obtain token credentials (step 3).
    $tokenCredentials = unserialize($_SESSION['token_credentials']);
    $oauth1->setTokenCredentials($tokenCredentials);

    // STEP 4: Retrieve the user's tweets.
    // It will return the Psr\Http\Message\ResponseInterface instance.
    $response = $oauth1->request('GET', 'https://api.twitter.com/1.1/statuses/user_timeline.json');

    // Convert the response to array and display it.
    var_dump(json_decode($response->getBody()->getContents(), true));
} elseif (isset($_GET['oauth_token']) && isset($_GET['oauth_verifier'])) {
    // Get back the previosuly generated temporary credentials (step 1).
    $temporaryCredentials = unserialize($_SESSION['temporary_credentials']);
    unset($_SESSION['temporary_credentials']);

    // STEP 3: Obtain the token credentials (also known as access token).
    $tokenCredentials = $oauth1->requestTokenCredentials($temporaryCredentials, $_GET['oauth_token'], $_GET['oauth_verifier']);

    // Store the token credentials in session for later use.
    $_SESSION['token_credentials'] = serialize($tokenCredentials);

    // this basically just redirecting to the current page so that the query string is removed.
    header('Location: ' . (string) $oauth1->getConfig()->getCallbackUri());
    exit();
} else {
    // STEP 1: Obtain a temporary credentials (also known as the request token)
    $temporaryCredentials = $oauth1->requestTemporaryCredentials();

    // Store the temporary credentials in session so we can use it on step 3.
    $_SESSION['temporary_credentials'] = serialize($temporaryCredentials);

    // STEP 2: Generate and redirect user to authorization URI.
    $authorizationUri = $oauth1->buildAuthorizationUri($temporaryCredentials);
    header("Location: {$authorizationUri}");
    exit();
}