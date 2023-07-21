<?php

namespace Sue\EventLoop;

use Closure;
use ReflectionFunction;
use React\EventLoop\Factory;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Promise\Promise;
use Sue\EventLoop\Exceptions\PromiseCancelledException;

/**
 * 获取eventloop唯一实例
 *
 * @return \React\EventLoop\LoopInterface
 * ```
 */
function loop()
{
    static $loop = null;
    if (null === $loop) {
        $loop = Factory::create();
        Loop::set($loop);
    }
    return $loop;
}

/**
 * 添加一个一次性timer
 *
 * @param float $interval 延迟时间（秒）
 * @param callable $callback 回调方法
 * @param mixed ...$params 参数
 * @return TimerInterface
 */
function setTimeout($interval, callable $callback, ...$params)
{
    $interval = (float) $interval;

    return loop()->addTimer(
        $interval,
        function () use ($callback, $params) {
            try {
                call_user_func_array($callback, $params);
            } catch (\Throwable $e) {
            } catch (\Exception $e) {
            }
        }
    );
}

/**
 * 添加一个反复执行的timer
 *
 * @param float $interval 延迟时间（秒）
 * @param callable $callback 回调方法
 * @param mixed ...$params 回调方法需要的参数，最后一个参数是\React\EventLoop\TimerInterface
 * @return TimerInterface
 */
function setInterval($interval, callable $callback, ...$params)
{
    $interval = (float) $interval;

    return loop()->addPeriodicTimer(
        $interval,
        function (TimerInterface $timer) use ($callback, $params) {
            try {
                $params[] = $timer;
                call_user_func_array($callback, $params);
            } catch (\Throwable $e) {
                cancelTimer($timer);
            } catch (\Exception $e) {
                cancelTimer($timer);
            }
        }
    );
}

/**
 * 解除一个timer
 *
 * @param \React\EventLoop\TimerInterface $timer
 * @return void
 */
function cancelTimer(TimerInterface $timer)
{
    loop()->cancelTimer($timer);
}

/**
 * 注册一个下一次eventloop tick时执行的timer
 *
 * @param callable $callback
 * @param mixed ...$params
 * @return Promise|PromiseInterface
 */
function nextTick(callable $callback, ...$params)
{
    $loop = loop();
    $runnable = true;
    $deferred = new Deferred(function ($_, $reject) use (&$runnable) {
        $runnable = false;
        $reject(new PromiseCancelledException('nextTick() was cancelled'));
    });
    $callback = function () use ($deferred, $callback, $params, &$runnable) {
        if (!$runnable) {
            return;
        }

        try {
            $deferred->resolve(call_user_func_array($callback, $params));
        } catch (\Throwable $e) {
            $deferred->reject($e);
        } catch (\Exception $e) {
            $deferred->reject($e);
        }
    };
    $loop->futureTick($callback);
    return $deferred->promise();
}

/**
 * 节流
 *
 * @param string $id
 * @param float $timeout 延迟时间（秒）
 * @param callable $callable
 * @return Promise|PromiseInterface
 */
function throttleById($id, $timeout, callable $callable)
{
     /** @var Promise[] $promises */
     static $promises = [];

     $id = (string) $id;
     $timeout = (float) $timeout;
 
     if (isset($promises[$id])) {
         return $promises[$id];
     }
 
     $deferred = new Deferred(function ($_, $reject) {
         $reject(new PromiseCancelledException("throttleById() was cancelled"));
     });
     $handler = function (callable $callable, Deferred $deferred) {
         try {
             $deferred->resolve(call_user_func($callable));
         } catch (\Throwable $e) {
             $deferred->reject($e);
         } catch (\Exception $e) {
             $deferred->reject($e);
         }
     };
 
     $timer = setTimeout($timeout, $handler, $callable, $deferred);
     /** @var Promise $promise */
     $promise = $deferred->promise();
     return $promises[$id] = $promise->always(function () use ($timer, $id, &$promises) {
         unset($promises[$id]);
         cancelTimer($timer);
     });
}

/**
 * 节流 (在N秒内的相同操作会只会执行一次)
 *
 * @param float $timeout 延迟时间
 * @param callable $callable
 * @return Promise|PromiseInterface
 */
function throttle($timeout, callable $callable)
{
    $id = fetchCallableUniqueId($callable);
    return throttleById($id, $timeout, $callable);
}

/**
 * 根据id防抖
 *
 * @param string $id
 * @param float $timeout
 * @param callable $callable
 * @return Promise|PromiseInterface
 */
function debounceById($id, $timeout, callable $callable)
{
    static $storage = [];

    $id = (string) $id;
    $timeout = (float) $timeout;

    if (isset($storage[$id])) {
        list($deferred, $promise, $timer) = $storage[$id];
        cancelTimer($timer);
    } else {
        $deferred = new \React\Promise\Deferred(function ($_, $reject) {
            $reject(new PromiseCancelledException("debounceById() was cancelled"));
        });
        /** @var Promise $promise */
        $promise = $deferred->promise();
        $promise = $promise->always(function () use ($id, &$storage) {
            $timer = end($storage[$id]);
            unset($storage[$id]);
            cancelTimer($timer);
        });
    }

    $handler = function (callable $callable) use ($deferred) {
        try {
            $deferred->resolve(call_user_func($callable));
        } catch (\Throwable $e) {
            $deferred->reject($e);
        } catch (\Exception $e) {
            $deferred->reject($e);
        }
    };
    $timer = setTimeout($timeout, $handler, $callable);
    $storage[$id] = [$deferred, $promise, $timer];
    return $promise;
}

/**
 * 防抖（在N秒内每次相同的请求会重新延迟N秒后再执行）
 *
 * @param float $timeout 延迟时间（秒）
 * @param callable $callable
 * @return Promise|PromiseInterface
 */
function debounce($timeout, callable $callable)
{
    $id = fetchCallableUniqueId($callable);
    return debounceById($id, $timeout, $callable);
}

/**
 * 执行方法（封装ErrorHandler)
 *
 * @param callable $callable
 * @param mixed ...$params
 * @return mixed
 * @deprecated 不在封装errorHandler
 */
function call(callable $callable, ...$params)
{
    return call_user_func_array($callable, $params);
}

/**
 * 解析callable在程序中的唯一id(长度64 hash)
 *
 * @param callable $callable
 * @return string
 */
function fetchCallableUniqueId(callable $callable)
{
    switch (true) {
        case $callable instanceof Closure:
            $ref = new ReflectionFunction($callable);
            $items = [
                $ref->getFileName(),
                $ref->getStartLine(),
                $ref->getEndLine()
            ];
            return hash('sha256', implode('|', $items));

        case is_string($callable):
            /** @var string $callable */
            return hash('sha256', $callable);

        case is_array($callable):
            list($obj, $method) = $callable;
            if (is_object($obj)) {
                $items = [get_class($obj), spl_object_hash($obj), $method];
            } else {
                $items = [strval($obj), $method];
            }
            return hash('sha256', implode('@', $items));

        default:
            return hash('sha256', uniqid(microtime(true), true));
    }
}
