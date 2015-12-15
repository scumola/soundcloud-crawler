#!/usr/bin/php
<?php
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

while($f = fgets(STDIN)){
#    echo "line: $f";
    $id = chop($f);
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

?>
