#!/usr/bin/php
<?php
$debug = 0;

require_once('amqp.inc');
include('config.php');

$in_exchange = 'soundcloud_queue';
$in_queue = 'users';
$consumer_tag = 'consumer';
$response="";

$conn = new AMQPConnection($rmq_HOST, $rmq_PORT, $rmq_USER, $rmq_PASS, $rmq_VHOST);

$in_ch = $conn->channel();
$in_ch->queue_declare($in_queue, false, true, false, false);
$in_ch->exchange_declare($in_exchange, 'direct', false, true, false);
$in_ch->queue_bind($in_queue, $in_exchange);

function process_message($msg) {
    global $debug;
    global $useragent;
    $json = $msg->body;
    $ob = json_decode($json);

    $id = $ob->id;
    print ("ID: $id\n");

    $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
}

$in_ch->basic_qos(0,200,false);
$in_ch->basic_consume($in_queue, $consumer_tag, false, false, false, false, 'process_message');

function shutdown($ch, $conn) {
    global $in_ch;
    global $conn;
    $in_ch->close();
    $conn->close();
}

register_shutdown_function('shutdown', $in_ch, $conn);

// Loop as long as the channel has callbacks registered
while(count($in_ch->callbacks)) {
    $in_ch->wait();
}
?>
