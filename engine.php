<?

include(__DIR__."/WebSocketServer.php");
include(__DIR__."/SimpleEchoServer.php");

/** No timing out on us  **/
set_time_limit(0);

/**
 * Create the listening socket and server object
 */
$socket = stream_socket_server("tcp://0.0.0.0:1043", $errno, $errstr) or die("stream_socket_server() has failed\n");
$server = new SimpleEchoServer($socket);

/**
 * Accept the first connection and echo back whatever input they give us
 */
$server->accept();
while($server->run()) {
    $server->ekho();
}