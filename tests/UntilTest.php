<?php

namespace Sue\EventLoop\Tests;

use Sue\EventLoop;
use Sue\EventLoop\Tests\Classes\Demo;

class UtilTest extends BaseTest
{
    public function testCallableIdByClosure()
    {
        $closure = function () {
            return 'foo';
        };

        $another_closure = function () {
            return 'bar';
        };

        $id1 = EventLoop\fetchCallableUniqueId($closure);
        $id2 = EventLoop\fetchCallableUniqueId($closure);
        $id3 = EventLoop\fetchCallableUniqueId($another_closure);
        $this->assertEquals($id1, $id2, 'The same id should be returned for the same closure.');
        $this->assertNotEquals($id1, $id3, 'The same id should not be returned for the different closure.');
    }

    public function testWithString()
    {
        $id1 = EventLoop\fetchCallableUniqueId('intval');
        $id2 = EventLoop\fetchCallableUniqueId('intval');
        $id3 = EventLoop\fetchCallableUniqueId('floatval');
        $this->assertEquals($id1, $id2, 'The same id should be returned for the same function name.');
        $this->assertNotEquals($id1, $id3, 'The same id should not be returned for the different function name.');
    }

    public function testWithObjectMethod()
    {
        $obj = new Demo();
        $id1 = EventLoop\fetchCallableUniqueId([$obj, 'foo']);
        $id2 = EventLoop\fetchCallableUniqueId([$obj, 'foo']);
        $id3 = EventLoop\fetchCallableUniqueId([$obj, 'bar']);
        $this->assertEquals($id1, $id2, 'The same id should be returned for the same object method.');
        $this->assertNotEquals($id1, $id3, 'The same id should not be returned for the different object method.');
    }

    public function testWithObjectStaticMethod()
    {
        $id1 = EventLoop\fetchCallableUniqueId([Demo::class, 'qux']);
        $id2 = EventLoop\fetchCallableUniqueId([Demo::class, 'qux']);
        $this->assertEquals($id1, $id2, 'The same id should be returned for the same object static method.');
    }
}
