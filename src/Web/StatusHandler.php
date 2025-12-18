<?php

namespace PhpStanHub\Web;

use SplObjectStorage;
use Exception;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

class StatusHandler implements MessageComponentInterface
{
    /** @var SplObjectStorage<ConnectionInterface, null> */
    private readonly SplObjectStorage $clients;

    public function __construct()
    {
        $this->clients = new SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
    }

    /**
     * @param string $msg
     */
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        // For now, we don't need to handle incoming messages
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, Exception $e): void
    {
        $conn->close();
    }

    /**
     * @param string $data
     */
    public function broadcast($data): void
    {
        foreach ($this->clients as $client) {
            $client->send($data);
        }
    }
}
