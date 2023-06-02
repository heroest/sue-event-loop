<?php

namespace Sue\EventLoop\Tests;

use Closure;
use Exception;
use PHPUnit\Framework\TestCase;

use function Sue\EventLoop\call;

class ErrorTest extends TestCase
{
    public function testNotice()
    {
        $exception = $this->call(function () {
            $arr = [];
            $arr[0];
        });
        $this->assertNull($exception);
    }

    public function testUserNotice()
    {
        $exception = $this->call(function () {
            trigger_error('user-error', E_USER_NOTICE);
        });
        $this->assertNull($exception);
    }

    public function testDeprecated()
    {
        if (PHP_VERSION_ID < 70400) {
            $this->markTestSkipped('php74+ only');
            return;
        }

        $exception = $this->call(function () {
            $a = 123;
            is_real($a);
        });
        $this->assertNull($exception);
    }

    public function testUserDeprecated()
    {
        $exception = $this->call(function () {
            trigger_error('user_deprecated', E_USER_DEPRECATED);
        });
        $this->assertNull($exception);
    }

    /**
     * call
     *
     * @param Closure $callback
     * @return null|Exception
     */
    private function call(Closure $callback)
    {
        $exception = null;
        try {
            call($callback);
        } catch (Exception $e) {
            $exception = $e;
        }
        return $exception;
    }
}
