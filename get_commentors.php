<?

$conn=mysql_connect("10.0.0.13","xxxxx","xxxxx");
if(!mysql_select_db("soundcloud",$conn)) {
}

$num = 216846956;
$num = 216824623;
$ctx = stream_context_create(array(
    'http' => array(
        'timeout' => 3
    )
));
for ($a = $num ; $a-- ; $a > 0) {
    print "tracknum: $a\n";
    $url="https://api.soundcloud.com/app/v2/tracks/$a/comments?filter_replies=1&embed_avatars=0&limit=2000&offset=0&linked_partitioning=1&client_id=02gUJC0hH2ct1EGOcYXQIzRFU91c72Ea&app_version=810b564";
    $body=file_get_contents($url, 0, $ctx);
    $blurb = json_decode($body);
    foreach ($blurb->collection as $v) {
        $username = $v->user->username;
        $avatar_url = $v->user->avatar_url;
        $url = $v->user->permalink_url;
        $id = $v->user->id;
        print "$id, $username, $url, $avatar_url\n";
        $result = mysql_query("insert into users values ('$id','$url','$avatar_url','$username')");
    }
}
?>
