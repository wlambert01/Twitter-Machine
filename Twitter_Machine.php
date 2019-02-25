<?php
/**
 *        TWITTER MACHINE : by WILLIAM LAMBERT
 * 				MACHINE ID =  67
 *
 *        (Get all data of any valid twitter account + Twitter followers and followed people)
 *
 *
 *NOTE 1 HOW TO USE THE MACHINE :
 			* FIX THE PARAMETERS OF MACHINE
 * You have to call twitter_obj->view() function ,  and  choose the value of those parameters:
 * - $followerOPTION (bool) => if true retrieve followers ids
 * - $followingOPTION (bool) => if true retrieve following ids
 * - $followersMAX (int) => maximum number of followers, i.e : if the person's count of followers
 *   is bigger: just take is personnal data + following ids  BUT NOT FOLLOWERS ids
			* RUN THE MACHINE MANUALLY :
* Go in localhost/console/twitter/index.php and connect to Sage APP with any twitter account
			* STOP THE MACHINE MANUALLY:
 * IF Machine has finished to work : Destroy the connection by Logout at the bottom of html page
 * IF Machine is still running ; sudo apachectl restart on terminal and go to localhost/console/twitter/destroy.php  and disconnect
*NOTE 1 STEPS FOLLOWED BY THE MACHINE :
 * 1) Check with Curl if the person exists on TWITTER
 * 2) Take all the data of this person :
 * - Twitter ID 'twitter_userid'
 * - Twitter screen_name 'twitter_username'
 * - Twitter Location 'twitter_location'
 * - Twitter description 'twitter_desc'
 * - Twitter profile pic (https) 'twitter_photo_url'
 * - Twitter banner (https) 'twitter_banner_url'
 * - Twitter number of followers 'twitter_followers'
 * - Twitter number of following 'twitter_following'
 * - Twitter protected account (bool) 'twitter_protected'
 * 3) Add that data to the DB for proper user
 * 4) If the person is not 'PROTECTED' then:
 * -follow that person with SAGE TWITTER ACCOUNT
 * -retrieve all friends/followers_ids of this person
 * -ADD the data to user_twitter_friends table
 * -unfollow that person with SAGE TWITTER ACCOUNT
 *NOTE 2 : TWITTER API MAX REQUEST and proper SLEEP times
 * GET users/show : (for public data: ids,name,desc,loc..) UNLIMITED (no sleep)
 * GET followers/ids : (for friends ids) 1 per MINUTE (add sleep(60) each time you use it ! )
 * GET friends/ids : (for followers ids)  1 per MINUTE (add sleep(60) each time you use it ! )
 * POST friendship/create : to follow someone : 1000 per day and per APP (no sleep)
 * POST friendship/destroy : to unfollow someone : 1000 per day and per APP (no sleep)
 */
/* Update Machine table. */
/* Load required lib files. */
$machine_id='67';
$logfile = fopen("logTwitterFetch.txt", "a+") or die("Unable to open file!");
include_once "../common.php";
include_once "../utils.php";
global $clsDb;
global $logfile;
global $machine_id;
require_once('../library/twitter/oauth/twitteroauth.php');
/* Update Machine table. */
function updMachineStEndTime($machine_id)
{
    global $clsDb;
		global $logfile;
    $sql="select COALESCE(first_run_date,'') fd from machines where id=" .$machine_id;
    $chkMch = $clsDb->execute($sql);
    //print_r($chkMch);
    if($chkMch[0]["fd"] == "")
    {
        $sqlUpd = "update machines set first_run_date = NOW() where id = " .$machine_id;
        fwrite($logfile,"Update first_run_date machine query -----  ".$sqlUpd."\n");
        $clsDb->execute($sqlUpd,true);
    }
    $sqlUpd = "update machines set last_run_date = NOW() where id = " .$machine_id;
    fwrite($logfile,"Update last_run_date machine query -----  ".$sqlUpd."\n");
    $clsDb->execute($sqlUpd,true);
}
updMachineStEndTime($machine_id);
/*START MACHINE*/
session_start();
class StripeAPI{
	protected  $consumer_key	 = 'ypvIalttgcaWTbkfXp1tvfadU';
	protected  $consumer_secret	 = 'ottbn2oQxfKGyeGrFevIlClYVePDBsAA23sjJFOEOmiytCVKo5';
	/*to test on dev switch callback with this one :*/
	protected  $oauth_callback	 = 'https://www.sagefinder.com/console/tools/TwitterMachineCallback.php';
	/*protected  $oauth_callback	 = 'http://local-dev.sagefinder.com/console/tools/TwitterMachineCallback.php';*/
	function __construct() {
	 if(empty($_SESSION['status'])){
		 $this->login_twitter();
		 }
   }
function login_twitter(){
	if ($this->consumer_key === '' || $this->consumer_secret === '') {
  echo 'You need a consumer key and secret to test the sample code. Get one from <a href="https://twitter.com/apps">https://twitter.com/apps</a>';
 // exit;
}
/* Build an image link to start the redirect process. */
header('Location: TwitterFetchFriendsFollowers.php/?connect=twitter');
/*echo $content = '<a href="TwitterFetchFriendsFollowers/?connect=twitter"><img src="../library/twitter/images/newest.png" alt="Sign in with Twitter"/></a>';*/
if(isset($_GET['connect']) && $_GET['connect']=='twitter'){
			$connection = new TwitterOAuth($this->consumer_key, $this->consumer_secret);// Key and Sec
			$request_token = $connection->getRequestToken($this->oauth_callback);
			$_SESSION['oauth_token'] = $token = $request_token['oauth_token'];
			$_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];
			switch ($connection->http_code) {
			  case 200:    $url = $connection->getAuthorizeURL($token); // Redirect to authorize page.
				header('Location: ' . $url);
				break;
			  default:
				echo 'Could not connect to Twitter. Refresh the page or try again later.';
	}
  }
	}
function twitter_callback(){
$connection = new TwitterOAuth($this->consumer_key, $this->consumer_secret, $_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);
$access_token = $connection->getAccessToken($_REQUEST['oauth_verifier']);
$_SESSION['access_token'] = $access_token;
if (200 == $connection->http_code) {
  echo $_SESSION['status'] = 'verified';
   header('Location: ./TwitterFetchFriendsFollowers.php?connected');
} else {
 header('Location: ./TwitterMachineDestroy.php?2');
}
}
function  view($followingOPTION=false,$followerOPTION=false,$followersMAX=10000){
	global $machine_id;
	global $clsDb;
	global $logfile;
	if (empty($_SESSION['access_token']) || empty($_SESSION['access_token']['oauth_token']) || empty($_SESSION['access_token']['oauth_token_secret'])) {
    header('Location: ./TwitterMachineDestroy.php?3');
}
$access_token = $_SESSION['access_token'];
$connection = new TwitterOAuth($this->consumer_key, $this->consumer_secret, $access_token['oauth_token'], $access_token['oauth_token_secret']);
/* If method is set change API call made. Test is called by default. */
$content = $connection->get('account/verify_credentials');
$sql = "SELECT id,twitter_username from user where twitter_user_status='new' and twitter_username<>'';";
$names = $clsDb->execute ($sql);
if (empty ($names)) {
writetolog( "all done\n",true);
die ;
}
if (!is_null($names))
{
for ($i = 0; $i <1; $i++)
{
	writetolog("running for -- ID " . $names[$i]["id"] . " -- " ."USERNAME " .$names[$i]["twitter_username"]."\n");
$url='https://twitter.com/'.$names[$i]["twitter_username"];
$curlDATA="";
$headerDATA=array();
$postDATA="";
$name_scrap="";
$protected="";
$id_scrap="";
$name_scrap="";
$twitter_image_url="";
$twitter_banner_url="";
$twitter_location="";
$twitter_desctext="";
$curl=getDataFromURL($url, $headerDATA, $postDATA = "", $curlDATA);
/*print_r($curlDATA);*/
/*die;*/
if ($curlDATA['http_code']!='404') {
$limitReached='';
$contentSHOW=$connection->get('users/show', array('screen_name' => $names[$i]["twitter_username"]));
if (isset($contentSHOW->errors))
{
	$EXACTerror=((($contentSHOW->errors)['0'])->code);
	$limitReached=($EXACTerror=='89' ? 'true' : '' );
}
if ($limitReached=='true')
{writetolog("  ---  REACH DAYLY LIMIT API CALLS . WAIT FOR 24 HOURS UNTIL RERUNNING.   ----  \n ",true);
	die;
}
/*print_r($contentSHOW);
die;*/
/*here expired token can happen*/
$err1=(isset($contentSHOW->errors));
$err2=(strpos($names[$i]["twitter_username"], '@') !== false);
$err3=(strpos($names[$i]["twitter_username"], 'http')!==false) ;
$errorsTwitter=$err1||$err2||$err3;
/*echo "err1= ".$err1." , err2=".$err2." , err3=".$err3." ,err=".$errorsTwitter;
die;*/
if ($errorsTwitter!=true)
{
$name_scrap=$contentSHOW->screen_name;
$id_scrap=$contentSHOW->id;
$twitter_followers=$contentSHOW->followers_count;
$twitter_following=$contentSHOW->friends_count;
$protected=$contentSHOW->protected;
if (!empty($contentSHOW->profile_image_url_https)){
$twitter_image_url=$contentSHOW->profile_image_url_https;}
if (isset($contentSHOW->{'profile_banner_url'})) {
$twitter_banner_url=$contentSHOW->profile_banner_url;}
if (!empty($contentSHOW->location)){
$twitter_location=$contentSHOW->location;}
if (!empty($contentSHOW->description)){
$twitter_desctext=str_ireplace("'", "''", $contentSHOW->description);}
writetolog("fetch data -- ID ". $names[$i]["id"] . " -- " ."USERNAME ". $names[$i]["twitter_username"] . " -- " . "FOLLOWER " . $twitter_followers." -- "."FOLLOWING " .$twitter_following."\n");
/*INSERT NEW DATA ON SQL USERS TABLES*/
$sql = "update user set twitter_userid='" . $id_scrap . "',twitter_followers='" . $twitter_followers. "',twitter_following='" . $twitter_following ."',updated_at=NOW()";
$sql.= ($twitter_image_url!='' ? ",twitter_photo_url='" . $twitter_image_url : "" );
$sql.= ($twitter_banner_url!='' ? "',twitter_banner_url='" . $twitter_banner_url : "") ;
$sql.= ($twitter_location!='' ? "',twitter_location='" . $twitter_location : "") ;
$sql.= ($twitter_desctext!='' ? "',twitter_desc='" . $twitter_desctext : "");
$sql.= ($protected!=1 ? "',twitter_user_status='new'" : "',twitter_user_status='protected'");
$sql.=" where id='" . $names[$i]["id"]."'";
$clsDb->execute($sql, true);
$txt=($protected!=1 ? "" : "user data updated with status protected \n");
writetolog($txt);
}
}
if (($curlDATA['http_code']=='404') || ($errorsTwitter==true)) {
	$sql = "update user set twitter_user_status='notvalid";
	$sql.="',updated_at=NOW()" ;
	$sql.=" where id='" . $names[$i]["id"]."'";
	$clsDb->execute($sql, true);
	writetolog("page not found or Twitter account suspended \n ");
	writetolog("user data updated with status notvalid \n ");
}
/*FOLLOWERS PART: GET ALL IDS OF ONE PERSON'S FOLLOWERS*/
if (($followingOPTION==true || $followerOPTION==true) && $protected!=1 && $curlDATA['http_code']!='404' && $errorsTwitter!=true)
{
$content2 = $connection->post('friendships/create', array('screen_name' => $names[$i]["twitter_username"]));
$loop_friends=True;
$loop_followers=True;
if ($twitter_followers<$followersMAX and $followerOPTION==true)
{
$contentFOLLOWERS=$connection->get('followers/ids', array('screen_name' => $names[$i]["twitter_username"]));
	sleep(60);
while ($loop_followers==True)
{
	$k=0;
	$list_followers_ids=array();
	for ($j = 0; $j < sizeof($contentFOLLOWERS->ids); $j++)
	{
		$list_followers_ids[$k]["twitter_userid"]=$contentFOLLOWERS->ids[$j];
		$list_followers_ids[$k]["user_id"]=$names[$i]['id'];
		$list_followers_ids[$k]["twitter_friend_type"]='follower';
		$list_followers_ids[$k]["machine_id"]=$machine_id;
		$k=$k+1;
		if ($k==2000){
		$out=createInserts("user_twitter_friends",$list_followers_ids,false);
		$k=0;
		$list_followers_ids=array();
		}
	}
	$nextnodeSTR=$contentFOLLOWERS->next_cursor_str;
	$out=createInserts("user_twitter_friends",$list_followers_ids,false);
	if ($nextnodeSTR=='0'){
		$loop_followers=False;
	}
	else {
		$contentFOLLOWERS=$connection->get('followers/ids', array('screen_name' => $names[$i]["twitter_username"],'cursor'=>$nextnodeSTR));
			sleep(60);
		// code...
	}
}
writetolog("follower done \n");
}
if ($followingOPTION==true) {
$countcursor=0;
$contentFRIENDS=$connection->get('friends/ids', array('screen_name' => $names[$i]["twitter_username"]));
sleep(60);
while ($loop_friends==True)
{
  
	$k=0;
	$list_friends_ids=array();
	//echo "------------".sizeof($contentFRIENDS->ids);
	for ($j = 0; $j < sizeof($contentFRIENDS->ids); $j++)
	{
		$list_friends_ids[$k]["twitter_userid"]=$contentFRIENDS->ids[$j];
		$list_friends_ids[$k]["user_id"]=$names[$i]['id'];
		$list_friends_ids[$k]["twitter_friend_type"]='following';
		$list_friends_ids[$k]["machine_id"]=$machine_id;
		$k=$k+1;
		if ($k==2000){
		$out=createInserts("user_twitter_friends",$list_friends_ids,false);
		$k=0;
		$list_friends_ids=array();
		}
	}
	//insert followers id records
	$out=createInserts("user_twitter_friends",$list_friends_ids,false);
	//find next cursor to continuer with other followers
	$nextnodeSTR=$contentFRIENDS->next_cursor_str;
	if (($nextnodeSTR=='0') || ($countcursor>=2)) {
		$loop_friends=False;
	}
	else {
		$contentFRIENDS=$connection->get('friends/ids', array('screen_name' => $names[$i]["twitter_username"],'cursor'=>$nextnodeSTR));
    $countcursor=$countcursor+1;
			sleep(60);
		// code...
	}
}
writetolog("following done \n");
}
/*$content3 = $connection->post('friendships/destroy', array('screen_name' => $names[$i]["twitter_username"]));*/
$sql = "update user set twitter_user_status='updated'";
$sql.=" where id='" . $names[$i]["id"]."'";
$clsDb->execute($sql, true);
$txt="user data updated with status updated \n";
writetolog($txt);
}
writetolog("done for -- ID " . $names[$i]["id"] . " -- USERNAME " . $names[$i]["twitter_username"]." \n",true);
}
}
fclose($logfile);
?>



<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<title>Twitter Login</title>
<link rel="stylesheet" href="../library/twitter/style.css">
</head>

<body style="width: 1200px;" >

<table>
<tr class='even'><td>Done !</td><td></td><td><a href="https://www.sagefinder.com/console/tools/TwitterFetchFriendsFollowers.php"> Rerun   </a></td></tr>
<tr><td style="vertical-align:top ;">Images of the last user</td><td><img src="<?php   echo $twitter_image_url;  ?>" ></td><td><img src="<?php   echo $twitter_banner_url; ?>" style="width:700px ;"></td></tr>
  <!--<tr><td style="vertical-align:top ;">Banner of the last user</td><td></td><td><img src="</*?php   echo $twitter_banner_url; */?>" style="width:1000px ;"> </td></tr>-->
	<tr><td>Location</td><td></td><td><?php  echo $twitter_location;  ?></td></tr>
	<tr><td>Description Text</td><td></td><td><?php /* echo $twitter_desctext; */ ?></td></tr>
	<tr><td>Location</td><td></td><td><?php  echo $twitter_location;  ?></td></tr>
<tr><td>Twitter count of friends</td><td></td><td><?php   echo $twitter_following;  ?></td></tr>
<tr><td> Twitter count of followers</td><td></td><td><?php   echo $twitter_followers;  ?></td></tr>

<!--<tr><td> DATA PERSON </td><td></td><td><?php /* print_r($contentSHOW); */ ?></td></tr> -->

<tr class='even'><td></td><td></td><td><a href="./TwitterMachineDestroy.php">Logout from app</a></td></tr>
<tr class='even'><td></td><td></td><td><a href="http://localhost/console/home.php">Sage Home</a></td></tr>
</table>
</body>
</html>

<script type="text/javascript">
window.onload=function(){
setTimeout(function(){
    window.location.href = 'https://www.sagefinder.com/console/tools/TwitterFetchFriendsFollowers.php';
	} , 5000);}
</script>

<?php
}
}
global $twitter_obj;
/*&& isset($_REQUEST['connected'])*/
if (isset($_SESSION['status'])  ){
$twitter_obj = New StripeAPI();
$maxfollowers=10000;
$maxfollowing=19990;
$followerOPT=False;
$followingOPT=True;
$twitter_obj->view($followingOPT,$followerOPT,$maxfollowers);
}else{
	$twitter_obj = New StripeAPI();
}
function writetolog($val,$blnLine=false){
	global $logfile;
	fwrite($logfile,$val);
	echo $val;
	echo "<br>";
	if($blnLine){
		$val = "--------------------------------------------------------------------------------------- \n";
		fwrite($logfile,$val);
		echo $val;
		echo "<br>";
	}
}