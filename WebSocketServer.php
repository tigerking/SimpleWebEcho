<?

class WebSocketServer {

    private $socket;

    private $client;

    public $input;

    public $handshake = false;

    public $debug = false;

    public $verbose = false;

    public function __construct($socket) {
        $this->socket = $socket;
    }

    /**
     * Complete the WebSocket Upgrade request
     */
    private function doHandshake($buffer) {
        /* Fill some variables and split up the headers into an array */
        $resource = $code = null;
        preg_match('/GET (.*?) HTTP/', $buffer, $match) && $resource = $match[1];
        preg_match("/\r\n(.*?)\$/", $buffer, $match) && $code = $match[1];
        $headers = array();
        foreach(explode("\r\n", $buffer) as $line) {
            if (strpos($line, ': ') !== false) {
                list($key, $value) = explode(': ', $line);
                $headers[trim($key)] = trim($value);
            }
        }

        /* Using the supplied keys, build the md5 response code */
        $securityResponse = md5(
            pack('N', $this->handleSecurityKey($headers['Sec-WebSocket-Key1'])).
            pack('N', $this->handleSecurityKey($headers['Sec-WebSocket-Key2'])).
            $code,
            true
        );

        $upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
        	"Upgrade: WebSocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Origin: " . $headers['Origin'] . "\r\n" .
            "Sec-WebSocket-Location: ws://" . $headers['Host'] . $resource . "\r\n" .
        	"\r\n".$securityResponse;        

        fwrite($this->client, $upgrade.chr(0), strlen($upgrade.chr(0)));

        $this->handshake = true;
    }

    private function handleSecurityKey($key) {
        preg_match_all('/[0-9]/', $key, $number);
        preg_match_all('/ /', $key, $space);
        if ($number && $space) {
            return implode('', $number[0]) / count($space[0]);
        }
        return '';
    }

    public function disconnect() {
        stream_socket_shutdown ( $this->client, STREAM_SHUT_RDWR );
        $this->client = null;
    }

    /**
     * Accept the next client that connects to our listening socket and
     * store it for later use.  Then negotiate the WebSocket upgrade.
     */
    public function accept() {
        $this->client = stream_socket_accept($this->socket);
        stream_set_timeout($this->client, 0);

        $headers = $this->read ();

        /* Send the WebSocket upgrade request handshake */
        $this->doHandshake($headers);
    }

    /**
     * Read from the client and handle any errors
     */
    public function read() {
        $buffer = stream_socket_recvfrom ( $this->client, 1024 );
        $buffer = trim ( $buffer );
        $error = socket_last_error ();

        if ($error != 0) {
            switch ($error) {
                case 104 :
                    echo "ERROR 104\n";
                    $this->disconnect ();
                    break;
                case 110 :
                    echo "ERROR 110\n";
                    $this->disconnect();
                    break;
                default :
                    echo "stream_socket_recvfrom() failed: (error: $error) " . socket_strerror ( socket_last_error () ) . "-" . socket_last_error () . "\n";
                    $this->disconnect ();
                    $this->logout ();
                    break;
            }
        }

        return $buffer;
    }

    /**
     * Send the provided string to the connected client and handle any errors
     */
    public function write($string) {
        $string = filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
        $data = stream_socket_sendto ( $this->client, "\000" . $string . "\377" );
        if ($data == false || $data == - 1) {
            if ($this->server->verbose) {
                echo "stream_socket_sendto() failed: (data: ".var_dump($data).") -" . socket_strerror ( socket_last_error () ) . "\n";
            }
            $this->disconnect();
        }
    }

    public function getSocket() {
        return $this->socket;
    }

    public function getClient() {
        return $this->client;
    }
}