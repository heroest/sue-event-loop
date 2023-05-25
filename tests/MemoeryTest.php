<?php

namespace Sue\EventLoop\Tests;

use Sue\EventLoop\Tests\BaseTest;

use function Sue\EventLoop\setInterval;
use function Sue\EventLoop\debounce;
use function Sue\EventLoop\cancelTimer;
use function Sue\EventLoop\loop;

class MemoryTest extends BaseTest
{
    public function testDebounce()
    {
        $loop = loop();
        $index = $count = 0;
        $callback = function () use (&$index) {
            $index++;
        };
        $memory_init = $memory_diff = null;
        setInterval(0.1, function ($timer) use ($callback, &$count, &$memory_diff, &$memory_init) {
            if (++$count >= 30) {
                cancelTimer($timer);
            }
            debounce(0.3, $callback);
            if (null === $memory_init) {
                $memory_init = self::getMemoryConsumed();
            } else {
                $memory_diff = (float) bcsub(self::getMemoryConsumed(), $memory_init, 2);
            }
        });
        $loop->run();
        $this->assertLessThanOrEqual(0.5, $memory_diff, "diff: {$memory_diff}");
    }

    private static function getMemoryConsumed()
    {
        $kb = bcdiv(memory_get_usage(), 1024, 2);
        return (float) $kb;
    }
}