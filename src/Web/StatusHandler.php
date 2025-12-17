<?php

namespace PhpStanHub\Web;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

class StatusHandler implements MessageComponentInterface
{
    private $clients;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        // For now, we don't need to handle incoming messages
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $conn->close();
    }

    public function broadcast($data)
    {
        foreach ($this->clients as $client) {
            $client->send($data);
        }
    }
}
