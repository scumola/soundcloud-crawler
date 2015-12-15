#!/usr/bin/php
<?php
include('config.php');

$cluster = new CouchbaseCluster('http://swebb-admin.dishonline.com:8091');
$bucket = $cluster->openBucket('soundcloud');

$my_conn=mysql_connect($mysql_host,$mysql_user,$mysql_passwd);
if(!mysql_select_db("soundcloud",$my_conn)){
    $error_string = "ERROR: can't connect to the DB\n";
    print ("$error_string");
    exit(1);
}

$result = mysql_query("select * from users limit 2000");
while ($row = mysql_fetch_assoc($result)) {
     $id = $row['id'];
     $username = $row['username'];
     $url = $row['url'];
     $avatar_url = $row['avatar_url'];
     $followers = $row['followers'];
     $followings = $row['followings'];
     print ("$id\n");
     $res = $bucket->upsert($id, array('id'=>$id, 'username'=>$username, 'url'=>$url, 'avatar_url'=>$avatar_url,'followers'=>$followers,'followings'=>$followings));
}
?>
