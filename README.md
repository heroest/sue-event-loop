## EventLoop组件
提供基于ReactPHP提供全局唯一的EventLoop

## What is ReactPHP?
[ReactPHP](https://reactphp.org/)是一款基于PHP的事件驱动的组件。核心是提供EventLoop，然后提供基于EventLoop上的各种组件，比方说I/O处理，定时器等。

**Table of Contents**

* [Methods](#methods)
    * [Sue\EventLoop\loop()](#loop)
    * [Sue\EventLoop\setTimeout()](#settimeout)
    * [Sue\EventLoop\setInterval()](#setinterval)
    * [Sue\EventLoop\cancelTimer()](#canceltimer)
    * [Sue\EventLoop\nextTick](#nexttick)
    * [Sue\EventLoop\throttle](#throttle)
    * [Sue\EventLoop\throttleById](#throttlebyId)
    * [Sue\EventLoop\debounce](#debounce)
    * [Sue\EventLoop\debounceById](#debouncebyId)
* [Install](#install)
* [Tests](#tests)
* [License](#license)

#### loop
获取全局唯一eventLoop对象
```php
use Sue\EventLoop\loop;

$loop = loop();

//loop启动后程序就会阻塞在这
//如果loop上没有任何待处理的callback, loop则自动退出
$loop->run();

//loop停止运行
$loop->stop(); 
```

#### setTimeout
`setTimeout`方法可以在eventloop上添加一个一次性的timer
```php
use Sue\EventLoop\setTimeout;
use Sue\EventLoop\loop;
use Sue\EventLoop\cancelTimer;

 //延迟5秒执行
setTimeout(5, function () {
    echo "hello world from 5 seconds ago";
});
loop()->run();

//提前终止执行
$timer = setTimeout(5, function () {
    echo "hello world from 5 seconds ago";
});
cancelTimer($timer);
loop()->run();

```

## setInterval
`setInterval`方法可以在eventloop上添加一个可以重复执行的timer

```php
use React\EventLoop\TimerInterface;

use function Sue\EventLoop\setInterval;
use function Sue\EventLoop\cancelTimer;

//每60秒执行一次
setInterval(60, function () {
    echo "one minute has been passed\n";
});

// 中止运行
$timer = setInterval(1, function (string $name, int $age, TimerInterface $timer) {
    if ($some_condition) {
        cancelTimer($timer);
    }
}, 'foo', 18);
loop()->run();
```

## cancelTimer
`cancelTimer`可以取消一个已经注册的timer对象

```php
use React\EventLoop\TimerInterface;

use function Sue\EventLoop\setInterval;
use function Sue\EventLoop\cancelTimer;

$timer = setInterval(1, function (TimerInterface $timer) {
        echo "working...\n";
});
if ($some_condition) {
    cancelTimer($timer);
}
```

## nextTick
`nextTick`可以在eventloop上注册一个在下一轮tick时执行的回调
对比`setTimeout(0, $callback)`, `nextTick($callback)`有更高优先级

```php
use RuntimeException;
use Sue\EventLoop\Exceptions\PromiseCancelledException;

use function Sue\EventLoop\nextTick;

$promise = nextTick(function () {
    return "hello world";
});
$promise->then(function (string $content) {
    //handler
});

//异常处理
$promise = nextTick(function () {
    throw new RuntimeException('boom');
});
$promise->then(null, function (RuntimeException $e) {
    echo "error: " . $e;
});

//中止执行
$promise = nextTick(function () {
    return "hello world";
});
if ($some_condition) {
    $promise->cancel();
}
$promise->otherwise(function (PromiseCancelledException $exception) {
    //exception
});
```

## throttle
`throttle`（节流）可以在eventloop上创建一个一次性的timer，但是会在N秒内同样的操作只会执行一次

```php
use Sue\EventLoop\loop;
use Sue\EventLoop\throttle;
use Sue\EventLoop\setInterval;

$callback = function () {
    echo "hello world\n";
};
setInterval(1, function () use ($callback) {
    static $count = 10;
    while ($count--) {
        echo "tick\n";
        throttle(3, $callback);
    }
});
loop()->run();

/** expect output
tick
tick
tick
hello world
tick
tick
tick
hello world
tick
tick
tick
hello world
tick
**/

//如果想提前手动中止
$promise = throttle(3, $callback);
if ($some_condition) {
    $promise->cancel();
}
$promise->otherwise(function (PromiseCancelledException $exception) {
    //exception
});
```
#### throttleById
`throttleById`方法同`throttle`，除了需要自行设置唯一id值，`throttle`是由callable的性质来计算唯一id

#### debounce
`debounce`(防抖)可以在eventloop上注册一个一次性timer, 但是在N秒内每次同样的操作都会延迟N秒后再执行
```php
use Sue\EventLoop\loop;
use Sue\EventLoop\debounce;
use Sue\EventLoop\setInterval;

$callback = function () {
    echo "hello world\n";
};
debounce(1, function () use ($callback) {
    static $count = 10;
    while ($count--) {
        echo "tick\n";
        throttle(3, $callback);
    }
});
loop()->run();
/** expect output
tick
tick
tick
tick
tick
tick
tick
tick
tick
tick
hello world
**/

//如果想提前中止
$promise = debounce(1, $callback);
if ($some_condition) {
    $promise->cancel();
}
$promise->otherwise(function (PromiseCancelledException $exception) {
    //exception
});
```

#### debounceById
`debounceById`方法同`debounce`，除了需要自行设置唯一id值

## install
```bash
$ composer require sue/event-loop
```

## tests
git clone项目后执行

```bash
$ composer install
$ ./vendor/bin/phpunit
```

## License

The MIT License (MIT)

Copyright (c) 2023 Donghai Zhang

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is furnished
to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.