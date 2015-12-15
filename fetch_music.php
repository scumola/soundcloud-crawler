#!/usr/bin/php
<?
require_once('amqp.inc');
include('config.php');
$in_exchange = 'soundcloud_music';
$in_queue = 'music';
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
    $url = $ob->url;
    $aurl = $ob->avatar_url;
    print ("*** FETCHING id: $id, $url\n");
    mkdir("data/$id");
    mkdir("data/$id/uploads");
    file_put_contents("data/$id/profile.json","$json\n");
    get_meta($id,$aurl);
    crawl($id,$url);
    system("nice tar -cjvf data/$id.tar.bz2 data/$id");
    system("rm -rf data/$id");
    system("scp data/$id.tar.bz2 steve@10.0.0.77:/temp/soundcloud");
    system("rm -rf data/$id.tar.bz2");
    print ("*** DONE id: $id, $url\n");
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

while(count($in_ch->callbacks)) {
    $in_ch->wait();
}


function crawl($id,$url) {
	$conn=mysql_connect("10.0.0.13","xxxxx","xxxxx");
	if(!mysql_select_db("soundcloud",$conn)) {
	}

	system("/opt/python3.3/bin/scdl -l $url -t --path data/$id/uploads --addtofile --onlymp3");
#	mkdir("data/$id/favorites");
#	system("/opt/python3.3/bin/scdl -l $url -f --path data/$id/favorites --addtofile --onlymp3");
#	mkdir("data/$id/playlists");
#	system("/opt/python3.3/bin/scdl -l $url -p --path data/$id/playlists --addtofile --onlymp3");

	$result = mysql_query("insert into crawled values ('$id')");
	mysql_close();
}


function get_meta($id,$aurl) {
	system("curl -s 'https://api.soundcloud.com/users/$id?client_id=02gUJC0hH2ct1EGOcYXQIzRFU91c72Ea&app_version=fb374fe' > data/$id/$id.json");
	system("cd data/$id ; curl -s -O '$aurl' ; cd ../..");
	system("curl -s 'http://api.soundcloud.com/users/$id/tracks?keepBlocked=false&limit=2000&offset=0&linked_partitioning=1&client_id=02gUJC0hH2ct1EGOcYXQIzRFU91c72Ea&app_version=fb374fe' > data/$id/tracks.json");
	$ob = json_decode(file_get_contents("data/$id/tracks.json"));
	foreach ($ob->collection as $v) {
		$title = $v->title;
		$fid = $v->id;
		$artwork_url = $v->artwork_url;
		$waveform_url = $v->waveform_url;
		print "MetaData Fetch for: $fid, $title\n";
		system ("curl -s 'https://api.soundcloud.com/app/v2/tracks/$fid/comments?filter_replies=1&embed_avatars=0&limit=2000&offset=0&linked_partitioning=1&client_id=02gUJC0hH2ct1EGOcYXQIzRFU91c72Ea&app_version=fb374fe' > data/$id/uploads/$fid-comments.json");
		system ("cd data/$id/uploads ; curl -s -O '$artwork_url' ; curl -s -O '$waveform_url' ; cd ../../.. ");
	}
}
?>
