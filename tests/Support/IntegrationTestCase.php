<?php

declare(strict_types=1);

namespace Yiisoft\Queue\Redis\Tests\Support;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Yiisoft\Injector\Injector;
use Yiisoft\Queue\Adapter\AdapterInterface;
use Yiisoft\Queue\Cli\LoopInterface;
use Yiisoft\Queue\Cli\SignalLoop;
use Yiisoft\Queue\Message\JsonMessageSerializer;
use Yiisoft\Queue\Message\MessageInterface;
use Yiisoft\Queue\Middleware\CallableFactory;
use Yiisoft\Queue\Middleware\Consume\ConsumeMiddlewareDispatcher;
use Yiisoft\Queue\Middleware\Consume\MiddlewareFactoryConsume;
use Yiisoft\Queue\Middleware\FailureHandling\FailureMiddlewareDispatcher;
use Yiisoft\Queue\Middleware\FailureHandling\MiddlewareFactoryFailure;
use Yiisoft\Queue\Middleware\Push\MiddlewareFactoryPush;
use Yiisoft\Queue\Middleware\Push\PushMiddlewareDispatcher;
use Yiisoft\Queue\Queue;
use Yiisoft\Queue\Redis\Adapter;
use Yiisoft\Queue\Redis\QueueProvider;
use Yiisoft\Queue\Worker\Worker;
use Yiisoft\Queue\Worker\WorkerInterface;
use Yiisoft\Test\Support\Container\SimpleContainer;

/**
 * Test case for unit tests
 */
abstract class IntegrationTestCase extends TestCase
{
    protected Queue|null $queue = null;
    protected ?WorkerInterface $worker = null;
    protected ?ContainerInterface $container = null;
    protected ?AdapterInterface $adapter = null;
    protected ?LoopInterface $loop = null;
    public ?QueueProvider $queueProvider = null;

    protected function setUp(): void
    {
        (new FileHelper())->clear();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        (new FileHelper())->clear();
        parent::tearDown();
    }

    protected function getQueue(): Queue
    {
        return $this->queue ??= new Queue(
            $this->getWorker(),
            $this->getLoop(),
            new NullLogger(),
            $this->getPushMiddlewareDispatcher()
        );
    }

    protected function getWorker(): WorkerInterface
    {
        return $this->worker ??= new Worker(
            $this->getMessageHandlers(),
            new NullLogger(),
            new Injector($this->getContainer()),
            $this->getContainer(),
            $this->getConsumeMiddlewareDispatcher(),
            $this->getFailureMiddlewareDispatcher(),
        );
    }

    protected function getMessageHandlers(): array
    {
        return [
            'ext-simple' => [new ExtendedSimpleMessageHandler(new FileHelper()), 'handle'],
            'exception-listen' => static function (MessageInterface $message) {
                $data = $message->getData();
                if (null !== $data) {
                    throw new \RuntimeException((string) $data['payload']['time']);
                }
            },
        ];
    }

    protected function getContainer(): ContainerInterface
    {
        return $this->container ??= new SimpleContainer($this->getContainerDefinitions());
    }

    protected function getContainerDefinitions(): array
    {
        return [];
    }

    protected function getConsumeMiddlewareDispatcher(): ConsumeMiddlewareDispatcher
    {
        return new ConsumeMiddlewareDispatcher(
            new MiddlewareFactoryConsume(
                $this->getContainer(),
                new CallableFactory($this->getContainer()),
            ),
        );
    }

    protected function getFailureMiddlewareDispatcher(): FailureMiddlewareDispatcher
    {
        return new FailureMiddlewareDispatcher(
            new MiddlewareFactoryFailure(
                $this->getContainer(),
                new CallableFactory($this->getContainer()),
            ),
            [],
        );
    }

    protected function getAdapter(): AdapterInterface
    {
        return $this->adapter ??= new Adapter(
            $this->getQueueProvider(),
            new JsonMessageSerializer(),
            $this->getLoop(),
        );
    }

    protected function getLoop(): LoopInterface
    {
        return $this->loop ??= new SignalLoop();
    }

    protected function getPushMiddlewareDispatcher(): PushMiddlewareDispatcher
    {
        return new PushMiddlewareDispatcher(
            new MiddlewareFactoryPush(
                $this->getContainer(),
                new CallableFactory($this->getContainer()),
            ),
        );
    }

    protected function getQueueProvider(): QueueProvider
    {
        return $this->queueProvider ??= new QueueProvider(
            $this->createConnection(),
        );
    }

    protected function createConnection(): \Redis
    {
        $redis = new \Redis();
        $redis->connect('redis');
        return $redis;
    }
}
