<?php

namespace Sue\EventLoop\Tests;

use ErrorException;
use Sue\EventLoop\Exceptions\PromiseCancelledException;

use function Sue\EventLoop\loop;
use function Sue\EventLoop\cancelTimer;
use function Sue\EventLoop\setInterval;
use function Sue\EventLoop\setTimeout;
use function Sue\EventLoop\nextTick;
use function Sue\EventLoop\debounce;
use function Sue\EventLoop\throttle;


class FunctionTest extends BaseTest
{
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
            1 / 0;
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

    public function testSetIntervalWithError()
    {
        $loop = loop();
        setInterval(0, function () {
            1 / 0;
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
            1 / 0;
        });
        $loop->run();
        $exception = self::unwrapSettledPromise($promise);
        $this->assertEquals(get_class($exception), ErrorException::class);
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

    public function testDebounce()
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
            debounce(0.3, $callback);
        });
        $loop->run();
        $this->assertGreaterThanOrEqual(5, $count, 'interval tick at least 5 times');
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
}
