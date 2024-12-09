<?php

declare(strict_types=1);

namespace Unit\Message;

use PHPUnit\Framework\TestCase;
use Yiisoft\Queue\Redis\Message\Message;

class MessageTest extends TestCase
{
    public function testGetHandlerName(): void
    {
        $message = new Message('handler', 'data', []);
        $this->assertEquals('handler', $message->getHandlerName());
    }

    public function testGetData(): void
    {
        $message = new Message('handler', 'data', []);
        $this->assertEquals('data', $message->getData());
    }

    public function testGetMetadata(): void
    {
        $metadata = ['key' => 'value'];
        $message = new Message('handler', 'data', $metadata);
        $this->assertEquals($metadata, $message->getMetadata());

        $message = new Message('handler', 'data', $metadata, 2);
        $metadata['delay'] = 2;
        $this->assertEquals($metadata, $message->getMetadata());
    }

    public function testWithDelay(): void
    {
        $message = new Message('handler', 'data', []);
        $delayedMessage = $message->withDelay(5);

        $this->assertNotSame($message, $delayedMessage);
        $this->assertEquals(5, $delayedMessage->getMetadata()['delay']);
    }

    public function testFromData(): void
    {
        $message = Message::fromData('test-handler', ['data' => 'test-data'], ['delay' => 2]);
        self::assertEquals('test-handler', $message->getHandlerName());
        self::assertEquals(['data' => 'test-data'], $message->getData());
        self::assertEquals(['delay' => 2], $message->getMetadata());

        $message = Message::fromData('test-handler', ['data' => 'test-data'], ['delay' => '3']);
        self::assertEquals(['delay' => 3], $message->getMetadata());
    }
}
