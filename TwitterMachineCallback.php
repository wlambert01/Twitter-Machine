<?php
session_start();
require_once('TwitterFetchFriendsFollowers.php');
global $twitter_obj;
if (isset($_REQUEST['oauth_token']) && $_SESSION['oauth_token'] !== $_REQUEST['oauth_token']) {
  $_SESSION['oauth_status'] = 'oldtoken';
  header('Location: ./TwitterMachineDestroy.php?1');
}
$connection = $twitter_obj->twitter_callback();