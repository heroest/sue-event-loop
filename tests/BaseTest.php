<?php

namespace Sue\EventLoop\Tests;

use PHPUnit\Framework\TestCase;

abstract class BaseTest extends TestCase
{
    /**
     * 解析promise
     *
     * @param \React\Promise\PromiseInterface|\React\Promise\Promise $promise
     * @return null|mixed;
     */
    protected static function unwrapSettledPromise($promise)
    {
        $result = null;
        $closure = function ($val) use (&$result) {
            $result = $val;
        };
        $promise->done($closure, $closure);
        return $result;
    }
}
