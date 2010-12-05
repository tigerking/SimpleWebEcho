<?

class SimpleEchoServer extends WebSocketServer {

    public function __construct($socket) {
        parent::__construct($socket);
    }

    public function run() {
        if($this->getClient() == null) {
            $this->accept();
        }
        $this->input = $this->read();
        return true;
    }

    public function ekho() {
        $this->write($this->input);
    }
}