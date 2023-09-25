<?php

namespace Sue\EventLoop\Tests;

use Exception;
use SplFileObject;
use React\EventLoop\StreamSelectLoop;
use React\Promise\Promise;
use React\Promise\Deferred;
use React\Promise\Timer\TimeoutException;
use Sue\EventLoop\Exceptions\PromiseCancelledException;

use function React\Promise\reject;
use function React\Promise\resolve;
use function Sue\EventLoop\loop;
use function Sue\EventLoop\cancelTimer;
use function Sue\EventLoop\setInterval;
use function Sue\EventLoop\setTimeout;
use function Sue\EventLoop\nextTick;
use function Sue\EventLoop\debounce;
use function Sue\EventLoop\throttle;
use function Sue\EventLoop\isLoopRunning;
use function Sue\EventLoop\await;

class FunctionTest extends BaseTest
{
    public function testLoopWithOther()
    {
        $select_loop = new StreamSelectLoop();
        $loop = loop($select_loop);
        $this->assertTrue($select_loop === $loop);
    }

    public function testLoop()
    {
        $loop = loop();
        $this->assertTrue($loop instanceof \React\EventLoop\LoopInterface, 'building eventloop');

        $other_loop = loop();
        $this->assertSame($loop, $other_loop, 'unique eventloop');

        $time_start = $time_end = time();
        $loop->addTimer(1, function () use (&$time_end) {
            $time_end = time();
        });
        $this->assertTrue($time_end === $time_start, 'eventloop before run');
        $loop->run();
        $time_used = $time_end - $time_start;
        $this->assertTrue($time_used >= 1, 'event loop with one second timer');
    }

    public function testIsLoopRunning()
    {
        loop()->stop();
        $this->assertFalse(isLoopRunning());
        $bool = false;
        setTimeout(0, function() use (&$bool) {
            $bool = isLoopRunning();
        });
        loop()->run();
        $this->assertTrue($bool);
    }

    public function testSetTimeout()
    {
        $loop = loop();
        $time_start = $time_end = time();
        setTimeout(1, function () use (&$time_end) {
            $time_end = time();
            return $time_end;
        });
        $this->assertTrue($time_end === $time_start, 'eventloop before run');
        $loop->run();
        $time_used = $time_end - $time_start;
        $this->assertTrue($time_used >= 1, 'event loop with setTimeout(1)');
    }

    public function testSetTimeoutWithError()
    {
        $loop = loop();
        $time_start = $time_end = time();
        setTimeout(0, function () {
            new SplFileObject(); //故意的
        });
        setTimeout(1, function () use (&$time_end) {
            $time_end = time();
        });
        $loop->run();
        $time_used = $time_end - $time_start;
        $this->assertTrue($time_used >= 1, 'loop lasts for 1 second');
    }

    public function testSetTimeoutWithRecoverable()
    {
        $loop = loop();
        $time_start = $time_end = time();
        setTimeout(0, function () {
            $arr = [1];
            $arr[2];
        });
        setTimeout(1, function () use (&$time_end) {
            $time_end = time();
        });
        $loop->run();
        $time_used = $time_end - $time_start;
        $this->assertTrue($time_used >= 1, 'loop lasts for 1 second');
    }

    public function testSetIntervalWithError()
    {
        $loop = loop();
        setInterval(0, function () {
            new SplFileObject(); //故意的
        });
        setTimeout(1, function () {
        });
        $st = time();
        $loop->run();
        $time_used = time() - $st;
        $this->assertGreaterThanOrEqual(1, $time_used, 'loop lasts for 1 second');
    }

    public function testSetTimeoutWithException()
    {
        $loop = loop();
        $time_start = $time_end = time();
        setTimeout(0, function () {
            throw new Exception('error');
        });
        setTimeout(1, function () use (&$time_end) {
            $time_end = time();
        });
        $loop->run();
        $time_used = $time_end - $time_start;
        $this->assertTrue($time_used >= 1, 'loop lasts for 1 second');
    }

    public function testSetTimeoutWithCancel()
    {
        $loop = loop();
        $value = false;
        $timer = setTimeout(0.1, function () use (&$value) {
            $value = time();
        });
        cancelTimer($timer);
        $st = time();
        $loop->run();
        $this->assertEquals(time(), $st, 'loop exit immediately');
        $this->assertFalse($value, 'after run');
    }

    public function testSetInterval()
    {
        $loop = loop();
        $index = 0;
        $st = time();
        setInterval(0.1, function ($timer) use ($st, &$index) {
            if (time() - $st > 1) {
                cancelTimer($timer);
            }
            $index++;
        });
        $this->assertEquals($index, 0, 'before run');
        $loop->run();
        $this->assertGreaterThanOrEqual(10, $index);
    }

    public function testSetIntervalWithException()
    {
        $loop = loop();
        setInterval(0, function () {
            throw new Exception('error');
        });
        setTimeout(1, function () {
        });
        $st = time();
        $loop->run();
        $time_used = time() - $st;
        $this->assertGreaterThanOrEqual(1, $time_used, 'loop lasts for 1 second');
    }

    public function testSetIntervalWithCancel()
    {
        $loop = loop();
        $value = false;
        $timer = setInterval(0.1, function () use (&$value) {
            $value = time();
        });
        cancelTimer($timer);
        $st = time();
        $loop->run();
        $this->assertEquals(time(), $st, 'loop exit immediately');
        $this->assertFalse($value, 'after run');
    }

    public function testSetIntervalWithCancelAfterRan()
    {
        $loop = loop();
        $time_begin = time();
        setInterval(0.1, function ($timer) use ($time_begin) {
            if (time() - $time_begin > 1) {
                cancelTimer($timer);
            }
        });
        $st = time();
        $loop->run();
        $time_used = time() - $st;
        $this->assertGreaterThanOrEqual(1, $time_used, 'loop lasts for 1 second');
    }

    public function testSetIntervalWithCancelExternalAfterRan()
    {
        $loop = loop();
        $executed = false;
        $timer = setInterval(0.1, function () use (&$executed) {
            $executed = true;
        });
        setTimeout(1, function () use ($timer) {
            cancelTimer($timer);
        });
        $st = time();
        $loop->run();
        $time_used = time() - $st;
        $this->assertTrue($executed);
        $this->assertGreaterThanOrEqual(1, $time_used, 'loop lasts for 1 second');
    }

    public function testNextTick()
    {
        $loop = loop();
        setTimeout(1, function () {
            //empty
        });
        nextTick(function () use ($loop) {
            $loop->stop();
        });
        $st = time();
        $loop->run();
        $this->assertEquals(time(), $st, 'loop exit immediately');
    }

    public function testNextTickWithError()
    {
        $loop = loop();
        $promise = nextTick(function () {
            throw new Exception('error');
        });
        $loop->run();
        $exception = self::unwrapSettledPromise($promise);
        $this->assertNotNull($exception);
    }

    public function testNextTickWithPromiseCancel()
    {
        $loop = loop();
        setTimeout(1, function () {
            //empty
        });
        $promise = nextTick(function () use ($loop) {
            $loop->stop();
        });
        /** @var null|PromiseCancelledException $exception */
        $exception = null;
        $promise->otherwise(function ($error) use (&$exception) {
            $exception = $error;
        });
        $promise->cancel();
        $st = time();
        $loop->run();
        $this->assertGreaterThanOrEqual(1, time() - $st, 'loopStop.nextTick was cancelled');
        $this->assertEquals(
            $exception,
            new PromiseCancelledException("nextTick() was cancelled")
        );
    }

    public function testThrottle()
    {
        $loop = loop();
        $index = $count = 0;
        $callback = function () use (&$index) {
            $index++;
        };
        setInterval(0.1, function ($timer) use ($callback, &$count) {
            if (++$count >= 5) {
                cancelTimer($timer);
            }
            throttle(0.3, $callback);
        });
        $loop->run();
        $this->assertEquals(5, $count, 'interval tick 5 times');
        $this->assertEquals(2, $index, 'only twice');
    }

    public function testThrottleWithPromiseCancel()
    {
        $loop = loop();
        $index = $count = 0;
        $callback = function () use (&$index) {
            $index++;
        };
        /** @var PromiseCancelledException|null $exception */
        $exception = null;
        setInterval(0.1, function ($timer) use ($callback, &$count, &$exception) {
            if (++$count >= 5) {
                cancelTimer($timer);
            }
            $promise = throttle(0.3, $callback);
            $promise->cancel();
            $exception = self::unwrapSettledPromise($promise);
        });
        $loop->run();
        $this->assertEquals(5, $count, 'interval tick 5 times');
        $this->assertEquals(0, $index, 'throttle promise cancelled');
        $this->assertEquals(
            $exception,
            new PromiseCancelledException("throttleById() was cancelled")
        );
    }

    public function testThrottleAfterPromiseResolved()
    {
        $loop = loop();
        $index = $count = 0;
        $p1 = $p2 = null;
        $callback = function () use (&$index) {
            $index++;
        };
        setInterval(0.1, function ($timer) use ($callback, &$count, &$p1, &$p2) {
            if (++$count >= 5) {
                cancelTimer($timer);
            }

            $p1 = throttle(0.3, $callback);
            static $undone = true;
            if ($undone) {
                $p1->done(function () use ($callback, &$p2) {
                    $p2 = throttle(0.1, $callback);
                });
                $undone = false;
            }
        });
        $loop->run();
        $this->assertEquals(5, $count, 'interval tick 5 times');
        $this->assertEquals(3, $index);
    }

    public function testDebounce()
    {
        $loop = loop();
        $index = $count = 0;
        $promises = [];
        $callback = function () use (&$index) {
            $index++;
        };
        setInterval(0.1, function ($timer) use ($callback, &$count, &$promises) {
            if (++$count >= 5) {
                cancelTimer($timer);
            }
            $promises[] = debounce(0.3, $callback);
        });
        $loop->run();
        $this->assertGreaterThanOrEqual(5, $count, 'interval tick at least 5 times');
        $this->assertCount(5, $promises);
        $this->assertEquals($promises[0], $promises[1]);
        $this->assertEquals(1, $index, 'only once');
    }

    public function testDebounceWithPromiseCancel()
    {
        $loop = loop();
        $index = $count = 0;
        $callback = function () use (&$index) {
            $index++;
        };
        /** @var PromiseCancelledException|null $exception */
        $exception = null;
        setInterval(0.1, function ($timer) use ($callback, &$count, &$exception) {
            if (++$count >= 5) {
                cancelTimer($timer);
            }
            $promise = debounce(0.3, $callback);
            $promise->cancel();
            $exception = self::unwrapSettledPromise($promise);
        });
        $loop->run();
        $this->assertGreaterThanOrEqual(5, $count, 'interval tick at least 5 times');
        $this->assertEquals(0, $index, 'promise cancelled');
        $this->assertEquals(
            $exception,
            new PromiseCancelledException("debounceById() was cancelled")
        );
    }

    public function testDebounceAfterPromiseResolved()
    {
        $loop = loop();
        $p1 = $p2 = null;
        $count = 0;
        $callback = function () use (&$count) {
            $count++;
        };
        $p1 = debounce(0.1, $callback);
        $p1->done(function () use ($callback, &$p2) {
            $p2 = debounce(0.1, $callback);
        });
        $loop->run();
        $this->assertNotNull($p1);
        $this->assertNotNull($p2);
        $this->assertEquals(2, $count);
        $this->assertTrue($p1 !== $p2);
    }

    public function testAwait()
    {
        loop()->stop();
        $deferred = new Deferred();
        setTimeout(0.5, function () use ($deferred) {
            $deferred->resolve('foo');
        });
        $stopped = false;
        setTimeout(1, function () use (&$stopped) {
            $stopped = true;
            loop()->stop();
        });
        $st = microtime(true);
        $result = await($deferred->promise());
        $time_used = bcsub(microtime(true), $st, 4);
        $this->assertEquals($result, 'foo');
        $this->assertFalse($stopped);
        $this->assertGreaterThanOrEqual(0.5, $time_used, 'loop lasts for 0.5 seconds');
    }

    public function testAwaitWithError()
    {
        $exception = null;
        setTimeout(0.2, function () use (&$exception) {
            try {
                await(new Promise(static function () {}, null));
            } catch (\Throwable $e) {
                $exception = $e;
            } catch (\Exception $e) {
                $exception = $e;
            }
        });
        $st = microtime(true);
        loop()->run();
        $time_used = bcsub(microtime(true), $st, 4);
        $this->assertNotNull($exception);
        $this->assertEquals($exception, new \BadMethodCallException('await can only be called in a stopped loop'));
        $this->assertGreaterThanOrEqual(0.2, $time_used, 'loop lasts for 0.2 seconds');
    }

    public function testAwaitWithTimeout()
    {
        $promise = new Promise(static function () {}, null);
        $st = microtime(true);
        $exception = null;
        try {
            await($promise, 0.5);
        } catch (\Throwable $e) {
            $exception = $e;
        } catch (\Exception $e) {
            $exception = $e;
        }
        
        $time_used = bcsub(microtime(true), $st, 4);
        $this->assertGreaterThanOrEqual(0.5, $time_used, 'loop lasts for 0.5 seconds');
        $this->assertTrue($exception instanceof TimeoutException);
    }

    public function testAwaitResolvedPromise()
    {
        $promise = resolve(true);
        $st = microtime(true);
        await($promise);
        $time_used = bcsub(microtime(true), $st, 4);
        $this->assertLessThanOrEqual(0.1, $time_used);
    }

    public function testAwaitRejectedPromise()
    {
        $promise = reject(new \Exception('foo'));
        $st = microtime(true);
        $exception = null;
        try {
            await($promise);
        } catch (\Throwable $e) {
            $exception = $e;
        } catch (\Exception $e) {
            $exception = $e;
        }
        $time_used = bcsub(microtime(true), $st, 4);
        $this->assertLessThanOrEqual(0.1, $time_used);
        $this->assertTrue($exception instanceof \Exception);
    }
}
