<?php

namespace PhpStanHub\Tests\Web;

use PhpStanHub\Web\StatusHandler;
use PHPUnit\Framework\TestCase;
use Ratchet\ConnectionInterface;

class StatusHandlerTest extends TestCase
{
    private StatusHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new StatusHandler();
    }

    public function testOnOpenAddsConnectionToClients(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $this->handler->onOpen($connection);

        // Test broadcast works with the added connection
        $connection->expects($this->once())
            ->method('send')
            ->with('test message');

        $this->handler->broadcast('test message');
    }

    public function testOnCloseRemovesConnection(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $this->handler->onOpen($connection);

        // Connection should receive message
        $connection->expects($this->once())
            ->method('send')
            ->with('test message 1');

        $this->handler->broadcast('test message 1');

        // Close connection
        $this->handler->onClose($connection);

        // Connection should not receive message after being closed
        $connection->expects($this->never())
            ->method('send');

        $this->handler->broadcast('test message 2');
    }

    public function testOnErrorClosesConnection(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $exception = new \Exception('Test error');

        $connection->expects($this->once())
            ->method('close');

        $this->handler->onError($connection, $exception);
    }

    public function testBroadcastSendsToAllConnections(): void
    {
        $connection1 = $this->createMock(ConnectionInterface::class);
        $connection2 = $this->createMock(ConnectionInterface::class);
        $connection3 = $this->createMock(ConnectionInterface::class);

        $this->handler->onOpen($connection1);
        $this->handler->onOpen($connection2);
        $this->handler->onOpen($connection3);

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

        $this->handler->broadcast($message);
    }

    public function testBroadcastWithNoConnections(): void
    {
        // Should not throw exception
        $this->handler->broadcast('test message');

        $this->assertTrue(true); // If we get here, test passed
    }

    public function testOnMessageDoesNothing(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        // Should not throw exception
        $this->handler->onMessage($connection, 'test message');

        $this->assertTrue(true); // If we get here, test passed
    }

    public function testMultipleConnectionsCanBeAddedAndRemoved(): void
    {
        $connection1 = $this->createMock(ConnectionInterface::class);
        $connection2 = $this->createMock(ConnectionInterface::class);

        $this->handler->onOpen($connection1);
        $this->handler->onOpen($connection2);

        // Both should receive first message
        $connection1->expects($this->once())
            ->method('send')
            ->with('message 1');
        $connection2->expects($this->once())
            ->method('send')
            ->with('message 1');

        $this->handler->broadcast('message 1');

        // Remove connection1
        $this->handler->onClose($connection1);

        // Create new mocks for second broadcast
        $connection2New = $this->createMock(ConnectionInterface::class);
        $connection2New->expects($this->once())
            ->method('send')
            ->with('message 2');

        // Re-add connection2 for the test
        $this->handler->onClose($connection2);
        $this->handler->onOpen($connection2New);

        $this->handler->broadcast('message 2');
    }

    public function testBroadcastWithJsonData(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $this->handler->onOpen($connection);

        $jsonData = json_encode([
            'status' => 'running',
            'errors' => [],
            'totals' => ['errors' => 0]
        ]);

        $connection->expects($this->once())
            ->method('send')
            ->with($jsonData);

        $this->handler->broadcast($jsonData);
    }

    public function testBroadcastWithEmptyString(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $this->handler->onOpen($connection);

        $connection->expects($this->once())
            ->method('send')
            ->with('');

        $this->handler->broadcast('');
    }

    public function testOnOpenWithMultipleCallsForSameConnection(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $this->handler->onOpen($connection);
        $this->handler->onOpen($connection); // Adding same connection twice

        // SplObjectStorage doesn't allow duplicates, so connection should receive message only once
        $connection->expects($this->once())
            ->method('send')
            ->with('test');

        $this->handler->broadcast('test');
    }

    public function testOnCloseWithNonExistentConnection(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        // Should not throw exception
        $this->handler->onClose($connection);

        $this->assertTrue(true);
    }

    public function testBroadcastWithLargePayload(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $this->handler->onOpen($connection);

        // Create large payload
        $largeData = str_repeat('test data ', 10000);

        $connection->expects($this->once())
            ->method('send')
            ->with($largeData);

        $this->handler->broadcast($largeData);
    }

    public function testMultipleBroadcastsInSequence(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $this->handler->onOpen($connection);

        $messages = [];
        $connection->expects($this->exactly(3))
            ->method('send')
            ->willReturnCallback(function ($message) use (&$messages) {
                $messages[] = $message;
            });

        $this->handler->broadcast('message 1');
        $this->handler->broadcast('message 2');
        $this->handler->broadcast('message 3');

        $this->assertSame(['message 1', 'message 2', 'message 3'], $messages);
    }

    public function testConnectionLifecycle(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        // Open connection
        $this->handler->onOpen($connection);

        // Send message
        $connection->expects($this->once())
            ->method('send');
        $this->handler->broadcast('test');

        // Receive message (should do nothing)
        $this->handler->onMessage($connection, 'client message');

        // Error occurs
        $connection->expects($this->once())
            ->method('close');
        $this->handler->onError($connection, new \Exception('test'));
    }
}
