Changelog
---------------------------------------------

## 1.2.0 (2023-09-25)

* 【修改】`\Sue\EventLoop\loop($loop)`方法新增一个loop参数，可以指定EventLoop的驱动

* 【新增】`\Sue\EventLoop\await($promise)`方法，可以启动一个临时eventloop来处理一个promise

* 【新增】`\Sue\EventLoop\isLoopRunning`方法，可以检测当前eventloop运行与否