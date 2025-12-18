<?php

namespace PhpStanHub\Tests\Web;

use Exception;
use PhpStanHub\Web\StatusHandler;
use PHPUnit\Framework\TestCase;
use Ratchet\ConnectionInterface;

class StatusHandlerTest extends TestCase
{
    private StatusHandler $statusHandler;

    protected function setUp(): void
    {
        $this->statusHandler = new StatusHandler();
    }

    public function testOnOpenAddsConnectionToClients(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $this->statusHandler->onOpen($connection);

        // Test broadcast works with the added connection
        $connection->expects($this->once())
            ->method('send')
            ->with('test message');

        $this->statusHandler->broadcast('test message');
    }

    public function testOnCloseRemovesConnection(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $this->statusHandler->onOpen($connection);

        // Connection should receive message
        $connection->expects($this->once())
            ->method('send')
            ->with('test message 1');

        $this->statusHandler->broadcast('test message 1');

        // Close connection
        $this->statusHandler->onClose($connection);

        // Connection should not receive message after being closed
        $connection->expects($this->never())
            ->method('send');

        $this->statusHandler->broadcast('test message 2');
    }

    public function testOnErrorClosesConnection(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $exception = new Exception('Test error');

        $connection->expects($this->once())
            ->method('close');

        $this->statusHandler->onError($connection, $exception);
    }

    public function testBroadcastSendsToAllConnections(): void
    {
        $connection1 = $this->createMock(ConnectionInterface::class);
        $connection2 = $this->createMock(ConnectionInterface::class);
        $connection3 = $this->createMock(ConnectionInterface::class);

        $this->statusHandler->onOpen($connection1);
        $this->statusHandler->onOpen($connection2);
        $this->statusHandler->onOpen($connection3);

        $message = 'broadcast test';

        $connection1->expects($this->once())
            ->method('send')
            ->with($message);

        $connection2->expects($this->once())
            ->method('send')
            ->with($message);

        $connection3->expects($this->once())
            ->method('send')
            ->with($message);

        $this->statusHandler->broadcast($message);
    }

    public function testBroadcastWithNoConnections(): void
    {
        // Should not throw exception
        $this->statusHandler->broadcast('test message');

        $this->assertTrue(true); // If we get here, test passed
    }

    public function testOnMessageDoesNothing(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        // Should not throw exception
        $this->statusHandler->onMessage($connection, 'test message');

        $this->assertTrue(true); // If we get here, test passed
    }

    public function testMultipleConnectionsCanBeAddedAndRemoved(): void
    {
        $connection1 = $this->createMock(ConnectionInterface::class);
        $connection2 = $this->createMock(ConnectionInterface::class);

        $this->statusHandler->onOpen($connection1);
        $this->statusHandler->onOpen($connection2);

        // Both should receive first message
        $connection1->expects($this->once())
            ->method('send')
            ->with('message 1');
        $connection2->expects($this->once())
            ->method('send')
            ->with('message 1');

        $this->statusHandler->broadcast('message 1');

        // Remove connection1
        $this->statusHandler->onClose($connection1);

        // Create new mocks for second broadcast
        $connection2New = $this->createMock(ConnectionInterface::class);
        $connection2New->expects($this->once())
            ->method('send')
            ->with('message 2');

        // Re-add connection2 for the test
        $this->statusHandler->onClose($connection2);
        $this->statusHandler->onOpen($connection2New);

        $this->statusHandler->broadcast('message 2');
    }

    public function testBroadcastWithJsonData(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $this->statusHandler->onOpen($connection);

        $jsonData = json_encode([
            'status' => 'running',
            'errors' => [],
            'totals' => ['errors' => 0],
        ]);

        $connection->expects($this->once())
            ->method('send')
            ->with($jsonData);

        $this->statusHandler->broadcast($jsonData);
    }

    public function testBroadcastWithEmptyString(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $this->statusHandler->onOpen($connection);

        $connection->expects($this->once())
            ->method('send')
            ->with('');

        $this->statusHandler->broadcast('');
    }

    public function testOnOpenWithMultipleCallsForSameConnection(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $this->statusHandler->onOpen($connection);
        $this->statusHandler->onOpen($connection); // Adding same connection twice

        // SplObjectStorage doesn't allow duplicates, so connection should receive message only once
        $connection->expects($this->once())
            ->method('send')
            ->with('test');

        $this->statusHandler->broadcast('test');
    }

    public function testOnCloseWithNonExistentConnection(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        // Should not throw exception
        $this->statusHandler->onClose($connection);

        $this->assertTrue(true);
    }

    public function testBroadcastWithLargePayload(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $this->statusHandler->onOpen($connection);

        // Create large payload
        $largeData = str_repeat('test data ', 10000);

        $connection->expects($this->once())
            ->method('send')
            ->with($largeData);

        $this->statusHandler->broadcast($largeData);
    }

    public function testMultipleBroadcastsInSequence(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $this->statusHandler->onOpen($connection);

        $messages = [];
        $connection->expects($this->exactly(3))
            ->method('send')
            ->willReturnCallback(function ($message) use (&$messages) {
                $messages[] = $message;
            });

        $this->statusHandler->broadcast('message 1');
        $this->statusHandler->broadcast('message 2');
        $this->statusHandler->broadcast('message 3');

        $this->assertSame(['message 1', 'message 2', 'message 3'], $messages);
    }

    public function testConnectionLifecycle(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        // Open connection
        $this->statusHandler->onOpen($connection);

        // Send message
        $connection->expects($this->once())
            ->method('send');
        $this->statusHandler->broadcast('test');

        // Receive message (should do nothing)
        $this->statusHandler->onMessage($connection, 'client message');

        // Error occurs
        $connection->expects($this->once())
            ->method('close');
        $this->statusHandler->onError($connection, new Exception('test'));
    }
}
