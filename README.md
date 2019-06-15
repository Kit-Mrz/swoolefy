# swoolefy
```
  ______                                _           _ _ _ _
 /  ____|                              | |         |  _ _ _|  _   _
|  (__     __      __   ___     ___    | |   ___   | |       | | | |
 \___  \   \ \ /\ / /  / _ \   / _ \   | |  / _ \  | |_ _ _  | | | |
 ____)  |   \ V  V /  | (_) | | (_) |  | | | ___/  |  _ _ _| | |_| |
|_____ /     \_/\_/    \___/   \___/   |_|  \___|  | |        \__, |
                                                   |_|           | |
                                                              __ / |
                                                             |_ _ /
```                                                            
swoolefy是一个基于swoole实现的轻量级高性能的常驻内存型的协程级应用服务框架，
高度封装了http，websocket，udp服务器，以及基于tcp实现可扩展的rpc服务，
同时支持composer包方式安装部署项目。基于实用，swoolefy抽象Event事件处理类，
实现与底层的回调的解耦，支持协程调度，同步|异步调用，全局事件注册，心跳检查，异步任务，多进程(池)，连接池等，
内置view、log、session、mysql、redis、mongodb等常用组件等。     

目前swoolefy4.2+版本完全支持swoole4.2.13+的协程，推荐使用swoole4.3.+.

### 实现的功能特性
- [x] 架手脚一键创建项目           
- [x] 路由与调度，MVC三层，多级配置      
- [x] 支持composer的PSR-4规范，实现PSR-3的日志接口     
- [x] 支持自定义注册命名空间，快速部署项目，简单易用      
- [x] 支持httpServer
- [x] 支持websocketServer,udpServer
- [x] 支持基于tcp实现的rpc服务，开放式的系统接口，可自定义协议数据格式，并提供rpc-client协程组件
- [x] 支持容器，组件IOC    
- [x] 支持全局日志   
- [x] 支持协程单例注册
- [x] 支持mysql协程组件，redis协程组件，mongodb组件，提供基于tp改造的swoolefy-orm协程mysql组件
- [x] 支持mysql的协程连接池，redis协程池
- [x] 异步务管理TaskManager，定时器管理TickManager，内存表管理TableManager  
- [x] 自定义进程管理ProcessManager，进程池管理PoolsManger
- [x] 支持底层异常错误的所有日志捕捉
- [x] 支持自定义进程的redis，rabitmq，kafka的订阅发布，消息队列等     
- [x] 支持crontab      
- [x] 支持热更新       
- [x] 支持定时的系统信息采集，并以订阅发布，udp等方式收集至存贮端    
- [x] 命令行形式高度封装启动|停止控制的脚本，简单命令即可管理整个框架 
- [ ] 分布式服务注册（zk，etcd）

### 常用组件
| 组件名称 | 安装 | 说明 |
| ------ | ------ | ------ |
| swoolefy-orm | composer require bingcool/swoolefy-orm:1.2.* | 基于tp-orm实现的适配swoolefy的mysql协程组件 |
| predis | composer require predis/predis:1.1.1 | swoolefy基于predis组件实现容器封装，使用redis操作需要安装此组件 |
| mongodb | composer require mongodb/mongodb:1.3 | mongodb组件，需要使用mongodb必须安装此组件 |
| rpc-client | composer require bingcool/rpc-client:dev-master | swoolefy的rpc客户端，当与rpc服务端通信时，需要安装此组件，支持在php-fpm中使用 |
| cron-expression | composer require dragonmantank/cron-expression | crontal组件，需要使用定时任务时必须安装此组件 |
  
### 定义组件，开放式组件接口，闭包回调实现创建组件过程，return对象即可
```
// 在应用层配置文件中
components => [
    // 例如创建phpredis扩展连接实例
    'redis' => function($com_name) { // 定义组件名，闭包回调实现创建组件过程，return对象即可
         $redis = new \Redis();
         $redis->connect('127.0.0.1', 6379, 3);
         return $redis;   
    },

    // predis组件的redis实例
    'predis' => function($name) {
        $parameters = [
            'scheme' => 'tcp',
            'host'   => '127.0.0.1',
            'port'   => 6379,
            'password' => '123456'
        ];
        $predis = new \Swoolefy\Core\Cache\Predis();
        $predis->setConfig($parameters);
        return $predis;
    },

    // swoole的原生协程redis客户端
    'CRedis' => function($name) {
        $CRedis = new \Swoolefy\Core\Cache\RedisCoroutine();
        $CRedis->setHost('127.0.0.1')->setPort('6379')->setPassword('123456');
        return $CRedis;
    },
    
    // 例如创建原生的mysql的PDO组件实例
    'mysql' => function($com_name) {
        $dbh = new PDO('mysql:host=127.0.0.1;port=6379;dbname=swoolefy', 'root', '123456');
        return $dbh;
    }
     // swoole原生的mysql客户端
    'CMysql' => function($name) {
        $config = [
            'host' => '127.0.0.1',
            'user' => 'bingcool',
            'password' => '123456',
            'database' => 'bingcool',
            'port'    => '3306',
            'timeout' => 5,
            'charset' => 'utf8',
            'strict_type' => false, //开启严格模式，返回的字段将自动转为数字类型
            'fetch_more' => true, //开启fetch模式, 可与pdo一样使用fetch/fetchAll逐行或获取全部结果集(4.0版本以上)
        ];

        $cdb = new Swoolefy\Core\Db\MysqlCoroutine();
        $cdb->setConfig($config);
        return $cdb;
    },
    // 其他的组件都可以通过闭包回调创建
    // 数组配置型log组件
    'log' => [
        'class' => \Swoolefy\Tool\Log::class,
        'channel' => 'application',
        'logFilePath' => rtrim(LOG_PATH,'/').'/runtime.log'
    ],
    // 或者log组件利用闭包回调创建
    'log' => function($name) {
        $channel= 'application';
        $logFilePath = rtrim(LOG_PATH,'/').'/runtime.log';
        $log = new \Swoolefy\Tool\Log($channel, $logFilePath);
        return $log;
    },
]

```
### 使用组件
```
use Swoolefy\Core\Application;
class TestController extends BController {
    public function test() {
        // 组件就是配置回调中定义的组件，这个过程会发生协程调度
        $redis = Application::getApp()->redis;
        //或者
        // $redis = Application::getApp()->get('redis');
        $redis->set('name', swoolefy);

        // predis组件，这个过程会发生协程调度
        $predis = Application::getApp()->predis;
        //或者
        // $predis = Application::getApp()->get('predis');
        $predis->set('predis','this is a predis instance');
        $predis->get('predis');
        
        // PDO实例，这个过程会发生协程调度
        $mysql = Application::getApp()->mysql;
        // 或者
        // $mysql = Application::getApp()->get('mysql');
        // 添加一条数据
        $sql = "INSERT INTO `user` (`login` ,`password`) VALUES (:login, :password)"; 
        $stmt = $dbh->prepare($sql); 
        $stmt->execute(array(':login'=>'kevin2',':password'=>'123456789'));  
        echo $dbh->lastinsertid();
    }
}

```
     
### 开发文档手册

文档:[开发文档](https://www.kancloud.cn/bingcoolhuang/php-swoole-swoolefy/587501)     
swoolefy官方QQ群：735672669，欢迎加入！    

### License
MIT   
Copyright (c) 2017-2019 zengbing huang    
