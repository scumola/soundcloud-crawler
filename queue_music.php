#!/usr/bin/php
<?php
require_once('amqp.inc');
include('config.php');

$limit = 1000;

$exchange = 'soundcloud_music';
$queue = 'music';

# Insert into rabbitmq
$conn = new AMQPConnection($rmq_HOST, $rmq_PORT, $rmq_USER, $rmq_PASS, $rmq_VHOST);
$ch = $conn->channel();
$ch->queue_declare($queue, false, true, false, false);
$ch->exchange_declare($exchange, 'direct', false, true, false);
$ch->queue_bind($queue, $exchange);

$my_conn=mysql_connect($mysql_host,$mysql_user,$mysql_passwd);
if(!mysql_select_db("soundcloud",$my_conn)){
    $error_string = "ERROR: can't connect to the DB\n";
    print ("$error_string");
    exit(1);
}

$nums = array();

$result = mysql_query("select * from users where not exists (select id from crawled where users.id = crawled.id) limit $limit");
while ($row = mysql_fetch_assoc($result)) {
    $id = $row['id'];
    $url = $row['url'];
    $aurl = $row['avatar_url'];
    $followers = $row['followers'];
    $followings = $row['followings'];
    $username = $row['username'];
    $task = array(
	'id' => $id,
	'url' => $url,
	'username' => $username,
	'followers' => $followers,
	'followings' => $followings,
	'avatar_url' => $aurl
	);
    $msg_body = json_encode($task);
    $msg = new AMQPMessage($msg_body, array('content_type' => 'text/plain', 'delivery_mode' => 2));
    $ch->basic_publish($msg, $exchange);
    array_push($nums, $id);
    print ("$id - $url\n");
}

$ch->close();
$conn->close();
?>
