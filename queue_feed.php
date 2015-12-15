#!/usr/bin/php
<?php
ini_set('memory_limit', '-1');
require_once('amqp.inc');
include('config.php');

$limit = 200000;

$exchange = 'soundcloud_queue';
$queue = 'users';

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

$result = mysql_query("select * from users where crawled_followers = '0' limit 10000000");
while ($row = mysql_fetch_assoc($result)) {
    $id = $row['id'];
#    $task = array('id' => $id);
#    $msg_body = json_encode($task);
#    $msg = new AMQPMessage($msg_body, array('content_type' => 'text/plain', 'delivery_mode' => 2));
#    $ch->basic_publish($msg, $exchange);
    $task = array('id' => $id);
    $msg_body = json_encode($task);
    $msg = new AMQPMessage($msg_body, array('content_type' => 'text/plain', 'delivery_mode' => 2));
    $ch->basic_publish($msg, $exchange);
    print ("$msg_body\n");
#     array_push($nums, $id);
#     print ("$id\n");
}

$ch->close();
$conn->close();
exit(1);

$nums = array_unique($nums);

for ($i = 0; $i < count($nums); ++$i) {
#    print $nums[$i];
    $task = array('id' => $nums[$i]);
    $msg_body = json_encode($task);
    $msg = new AMQPMessage($msg_body, array('content_type' => 'text/plain', 'delivery_mode' => 2));
    $ch->basic_publish($msg, $exchange);
    print ("$msg_body\n");
}

$ch->close();
$conn->close();
?>
