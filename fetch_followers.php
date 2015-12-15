#!/usr/bin/php
<?
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
    crawl($id);

    $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
}

$in_ch->basic_qos(0,1,false);
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


function crawl($account_id) {
#	$cb_cluster = new CouchbaseCluster('http://10.0.0.77:8091');
#	$cb_bucket = $cb_cluster->openBucket('soundcloud');
	$m = new Memcached();
	$m->addServer('10.0.0.77', 11211);

	$conn=mysql_connect("10.0.0.13","xxxxx","xxxxx");
	if(!mysql_select_db("soundcloud",$conn)) {
	}
	$ctx = stream_context_create(array(
	    'http' => array(
		'timeout' => 3
	    )
	));
	$page_size = 199;
	$done = 0;
	$a = 0;
	while ($done == 0) {
	    $url="https://api.soundcloud.com/users/$account_id/followers?keepBlocked=true&limit=$page_size&offset=$a&linked_partitioning=1&client_id=02gUJC0hH2ct1EGOcYXQIzRFU91c72Ea&app_version=ef35081";
	    $body=file_get_contents($url, 0, $ctx);
	    $blurb = json_decode($body);
	    $num = 0;
	    foreach ($blurb->collection as $v) {
		$username = $v->username;
		$avatar_url = $v->avatar_url;
		$purl = $v->permalink_url;
		$followers = $v->followers_count;
		$followings = $v->followings_count;
		$id = $v->id;
#		$res = $cb_bucket->upsert($username, array('id'=>$id, 'username'=>$username, 'url'=>$purl, 'avatar_url'=>$avatar_url,'followers'=>$followers,'followings'=>$followings));
		print "$id, $username, $purl, $avatar_url, $followers\n";

		# MC
		if (!($mcid = $m->get($id))) {
		    if ($m->getResultCode() == Memcached::RES_NOTFOUND) {
			# key doesn't exist
			$m->set($id, $username);
			$result = mysql_query("insert into users values ('$id','$purl','$avatar_url','$username','$followers','$followings','0','0')");
			if (!$result) {
				$result = mysql_query("update users set followers='$followers' where id = '$id'");
			}
		    } else {
		    }
		}
		$num = $num + 1;
	    }
	    $a=$a+$page_size;
	    print "offset: $a\n";
	    if ($num == 0) {
		$done = 1;
	    }
	}
	$result = mysql_query("update users set crawled_followers='1' where id = '$account_id'");


	$page_size = 199;
	$done = 0;
	$a = 0;
	while ($done == 0) {
	    $url="https://api.soundcloud.com/users/$account_id/followings?keepBlocked=true&limit=$page_size&offset=$a&linked_partitioning=1&client_id=02gUJC0hH2ct1EGOcYXQIzRFU91c72Ea&app_version=ef35081";
	    $body=file_get_contents($url, 0, $ctx);
	    $blurb = json_decode($body);
	    $num = 0;
	    foreach ($blurb->collection as $v) {
		$username = $v->username;
		$avatar_url = $v->avatar_url;
		$url = $v->permalink_url;
		$followers = $v->followers_count;
		$followings = $v->followings_count;
		$id = $v->id;
#		$cb_res = $cb_bucket->upsert($username, array('id'=>$id, 'username'=>$username, 'url'=>$url, 'avatar_url'=>$avatar_url,'followers'=>$followers,'followings'=>$followings));
		print "$id, $username, $url, $avatar_url, $followers\n";
		# MC
		if (!($mcid = $m->get($id))) {
		    if ($m->getResultCode() == Memcached::RES_NOTFOUND) {
			# key doesn't exist
			$m->set($id, $username);
			$result = mysql_query("insert into users values ('$id','$url','$avatar_url','$username','$followers','$followings','0','0')");
			if (!$result) {
				$result = mysql_query("update users set followings='$followings' where id = '$id'");
			}
		    } else {
		    }
		}
		$num = $num + 1;
	    }
	    $a=$a+$page_size;
	    print "offset: $a\n";
	    if ($num == 0) {
		$done = 1;
	    }
	}
	$result = mysql_query("update users set crawled_followings='1' where id = '$account_id'");
	mysql_close();
#	$m->quit();
}
?>
