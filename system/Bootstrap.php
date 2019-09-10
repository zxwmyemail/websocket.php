<?php
namespace app\system;
/********************************************************************************************
 * 应用驱动类
 * @copyright   Copyright(c) 2015
 * @author      iProg
 * @version     1.0
 ********************************************************************************************/
use app\service\extend\Response;
use app\service\models\WssUtil;
use app\system\core\Config;
use app\system\library\Log;
use app\system\library\HttpCurl;
use app\system\library\BaseRedis;
use app\system\library\RedisPool;
use app\system\library\MysqlPool;

class Bootstrap {

    public static $_fd;             //websocket句柄
    public static $_wsIns;          //ws服务器对象
    public static $_reqParams;      //请求url参数
    public static $_routeParams;    //路由参数

    /*---------------------------------------------------------------------------------------
    | 自动类加载函数
    |----------------------------------------------------------------------------------------
    | @access      public
    | @param       string   $classname  类名
    ----------------------------------------------------------------------------------------*/
    public static function classLoader($classname) {     
        $filePath = str_replace(GLOBAL_NAMESPACE, BASE_PATH, $classname) . ".php";
        $filePath = str_replace('\\', DIRECTORY_SEPARATOR, $filePath);
        if (file_exists($filePath)) {
            require_once($filePath); 
        } else {
            error_log('[' . date('Y-m-d H:i:s') . '][ERROR] 加载 ' . $filePath . ' 类库不存在');
            throw new \Exception('class file not exists:' . $filePath, 404);
        } 
    }

    /*------------------------------------------------------------------------------------------
    | 注册自动加载类函数
    --------------------------------------------------------------------------------------------*/
    public static function registerAutoload($enable = true) {
        $enable ? spl_autoload_register('self::classLoader') : spl_autoload_unregister('self::classLoader');
    }

    /*-------------------------------------------------------------------------------------
    | 根据目前处于开发、测试还是生产模式，判断是否显示错误到页面
    --------------------------------------------------------------------------------------*/
    public static function isDisplayErrors() {
        error_reporting(E_ALL);
        switch (CUR_ENV) {
            case 'development':
                ini_set('display_errors', 1);
                break;
            case 'test':
                ini_set('display_errors', 1);
                break;
            case 'product':
                ini_set('display_errors', 0);
                break;
            default:
                exit('The application environment is not set correctly.');
                break;
        }
        date_default_timezone_set('Asia/Shanghai');
        ini_set('log_errors', 1); 
        ini_set('error_log', RUNTIME_PATH . DS . 'sys_log' . DS . date('Ymd').'.log');
    }

    /*---------------------------------------------------------------------------------------
    | websocket服务器进程
    |---------------------------------------------------------------------------------------*/
    public static function runServer() {
        // 初始化系统错误是否显示
        self::isDisplayErrors();

        // 类自动加载机制
        self::registerAutoload();
        
        // 创建ws实例
        // self::$_wsIns = new \Swoole\WebSocket\Server("0.0.0.0", $port, SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL);
        $config = Config::get('config');
        $ipAddr = $config['listen_ws_ip'];
        $port   = $config['listen_ws_port'];
        self::$_wsIns = new \Swoole\WebSocket\Server($ipAddr, $port);

        // 加入心跳包检测
        self::$_wsIns->set([
            'daemonize'                => 0,
            'heartbeat_idle_time'      => 600,
            'heartbeat_check_interval' => 60,
            'max_coroutine'            => 8000,
            'max_request'              => 10000,
            'http_parse_post'          => false,
            // 'log_file'                 => RUNTIME_PATH . DS . 'sys_log' . DS . 'websocket.log',
            // 'ssl_cert_file'            => '/etc/nginx/cert/1_www.01film.cn_bundle.crt',
            // 'ssl_key_file'             => '/etc/nginx/cert/2_www.01film.cn.key',
        ]);

        // 监听进程启动
        self::$_wsIns->on('workerstart', function($server, $worker_id) {
            // 初始化redis连接池
            $redisConf = Config::get('redis', 'master');
            self::$_wsIns->redis = RedisPool::getInstance($redisConf);
        });

        // 监听客户端建立链接
        self::$_wsIns->on('open', function($server, $request) {
            // echo "open from {$request->fd}\n";
        });

        // 监听客户端发送的消息
        self::$_wsIns->on('message', function($server, $request) {
            self::dispatchWs($server, $request);
        });

        // 监听客户端链接断开信息
        self::$_wsIns->on('close', function ($server, $fd) {
            self::dispatchClose($fd);
        });

        // 监听http请求，可以推送消息
        self::$_wsIns->on('request', function ($request, $response) {
            self::dispatchHttp($request, $response);
        });

        self::$_wsIns->start();
    }

    /*---------------------------------------------------------------------------------------
    | 处理请求，分发ws请求到控制层
    |---------------------------------------------------------------------------------------*/
    public static function dispatchWs($wsIns, $request) { 
        // 处理请求参数
        $data = json_decode($request->data, true);
        self::$_routeParams = isset($data['route']) ? $data['route'] : '';
        self::$_reqParams = isset($data['request']) ? $data['request'] : []; 
        self::$_fd = $request->fd;

        if (empty(self::$_routeParams)) {
            error_log('dispatchWs request params error: ' . $request->data);
            $retMsg = Response::json(Response::ERROR_ROUTE_PARAMS);
            if (self::$_wsIns->isEstablished($request->fd)) {
                self::$_wsIns->push($request->fd, $retMsg);
            }
        } else {
            //导向控制层
            self::routeToCtrl();
        } 
    }

    /*---------------------------------------------------------------------------------------
    | 处理请求，分发http请求到控制层
    |---------------------------------------------------------------------------------------*/
    public static function dispatchHttp($request, $response) {
        $wsIns = self::$_wsIns;
        $data = json_decode($request->rawContent(), true);
        self::$_routeParams = isset($data['route']) ? $data['route'] : '';
        self::$_reqParams = isset($data['request']) ? $data['request'] : []; 
        if (empty(self::$_routeParams)) {
            error_log('dispatchHttp request params error: ' . $request->rawContent());
            $retMsg = Response::json(Response::ERROR_ROUTE_PARAMS);
            $response->end($retMsg);
        } else {
            $request = self::$_reqParams;
            if (self::$_routeParams == 'http@getBattleStatus') {
                $isBattle = 0;
                $redis = $wsIns->redis->get();
                if (isset($request['openid'])) {
                    $player = $redis->get($request['openid']); 
                    $player = $player ? json_decode($player, true) : [];
                    if ($player) {
                        $diff = time() - $player['startTime'];
                        if ($player['isFighting'] == 1 && $diff < $player['totalTime']) {
                            $isBattle = 1;
                        }
                    }
                }
                $wsIns->redis->back($redis);
                $retMsg = Response::json(Response::SUCCESS, ['isBattle' => $isBattle]);
                $response->end($retMsg);
            } else {
                go(function(){
                    self::routeToCtrl();
                });
                $retMsg = Response::json(Response::SUCCESS);
                $response->end($retMsg);
            } 
        }
    }

    /*---------------------------------------------------------------------------------------
    | 处理请求，分发ws请求到控制层
    |---------------------------------------------------------------------------------------*/
    public static function dispatchClose($fd) { 
        $redis = self::$_wsIns->redis->get();
        $openid = $redis->get(md5($fd)); 
        $player = $redis->get($openid); 

        if (!$player) {
            self::$_wsIns->redis->back($redis);
            return;
        }

        $player = json_decode($player, true);

        // 推送离线消息给对战所有方
        WssUtil::publishBattleInfo($redis, 'offline', $player['opponent'], [
            'openid'    => $player['openid'],
            'nickname'  => $player['nickname'],
            'avatarUrl' => $player['avatarUrl'],
            'msg'       => '你的对手【' . $player['nickname'] . '】离开了游戏' 
        ]);

        self::$_wsIns->redis->back($redis);
    }

    /*---------------------------------------------------------------------------------------
    | 订阅redis发布进程：
    | 在对websocket使用nginx进行负载均衡的时候，可以使用redis的订阅发布来进行消息通讯
    |----------------------------------------------------------------------------------------
    | @access      public
    ----------------------------------------------------------------------------------------*/
    public static function subscribe() {
        // 设置socket不超时
        ini_set('default_socket_timeout', -1);

        // 类自动加载机制
        self::registerAutoload();

        $redisConf = Config::get('redis', 'master');
        $baseIns = new BaseRedis($redisConf);
        $redis = $baseIns->getRedis();
        $channel = array_values($redisConf['sub_channel_name']);

        $redis->subscribe($channel, function($redis, $chan, $msg) {
            $config = Config::get('config');
            $url = '127.0.0.1:' . $config['listen_ws_port'];
            HttpCurl::post($url, $msg); 
        });
    }

    /*---------------------------------------------------------------------------------------
    | 玩家匹配进程：
    | 玩家匹配使用的是redis的set集合，没有使用队列
    |----------------------------------------------------------------------------------------
    | @access      public
    ----------------------------------------------------------------------------------------*/
    public static function matchPlayer() {
        // 设置socket不超时
        ini_set('default_socket_timeout', -1);

        // 类自动加载机制
        self::registerAutoload();

        $systemConf = Config::get('config');
        $redisConf  = Config::get('redis', 'master');
        $matchPoolName   = $redisConf['player_match_pool'];
        $matchPoolNum    = $redisConf['player_match_pool_num'];
        $onlinePlayerNum = $systemConf['online_player_num'];
        $totalTime       = $systemConf['online_count_down'];
        $expireTime      = $systemConf['redis_expire_time'];

        $baseIns = new BaseRedis($redisConf);
        $redis = $baseIns->getRedis();
        while (true) {
            for ($i = 1; $i <= $matchPoolNum; $i++) { 
                $matchPoolName = $matchPoolName . '_' . $i;
                $onlineNum = $redis->sCard($matchPoolName);
                if ($onlineNum < $onlinePlayerNum) {
                    sleep(1);
                    continue;
                }

                $player = [];
                for ($j=0; $j < $onlinePlayerNum; $j++) { 
                    $player[] = $redis->sPop($matchPoolName);
                }

                $result = HttpCurl::post($systemConf['stage_elem_url'], [
                    'stage_id' => $i
                ]);
                $result = json_decode($result, true);
                $stageInfo = [];
                if ($result && isset($result['success']) && $result['success'] == 1) {
                    $stageInfo = $result['data'];
                }
                unset($result);

                $battleInfo = [];
                $playerInfo = $redis->mGet($player);
                $startTime  = time();
                foreach ($playerInfo as $info) {
                    if ($info) {
                        $info = json_decode($info, true);
                        $info['isFighting'] = 1;
                        $info['stageId']    = $i;
                        $info['startTime']  = 0;
                        $info['totalTime']  = isset($stageInfo['counting']) ? (int)$stageInfo['counting'] : 600;
                        $info['opponent']   = array_values(array_diff($player, [$info['openid']]));
                        $info['foundElem']  = [];
                        $battleInfo[$info['openid']] = $info;
                        $redis->setex($info['openid'], $expireTime, json_encode($info));
                    }
                }

                // 向每个玩家所在服务器的订阅频道发送对战消息，以便找到该玩家，并向玩家推送对战消息
                foreach ($battleInfo as $openid => $info) {
                    $redis->publish($info['channel'], json_encode([
                        'route' => 'serverCtrl@push',
                        'request' => [
                            'fd'   => $info['fd'],
                            'type' => 'startBattle',
                            'data' => [
                                'stageId'      => $i,
                                'stageMessage' => isset($stageInfo['stage_message']) ? $stageInfo['stage_message'] : [],
                                'counting'     => isset($stageInfo['counting']) ? (int)$stageInfo['counting'] : 600,
                                'battleInfo'   => $battleInfo,
                            ]
                        ]
                    ]));
                }
            }
        }
    }

    /*---------------------------------------------------------------------------------------
    | 根据URL分发到Controller
    ---------------------------------------------------------------------------------------*/
    public static function routeToCtrl() {   
        $route  = explode('@', self::$_routeParams);
        $ctrl   = ucfirst($route[0]);
        $action = $route[1];

        try {
            if (!preg_match('/^[A-Za-z](\w|\.)*$/', $ctrl)) {
                throw new \Exception('PHP Error: controller not exists:' . $ctrl, 404);
            }
            $controller = "app\service\controllers\\" . $ctrl;
            $controllerObj = new $controller;
            $controllerObj->websocket = self::$_wsIns;
            $controllerObj->request   = self::$_reqParams;
            $controllerObj->route     = self::$_routeParams;
            if (!empty(self::$_fd)) {
                $controllerObj->myFd = self::$_fd;
            }
            $controllerObj->$action();
            unset($controllerObj);
        } catch (\Throwable $e) {
            error_log('PHP Error:  ' . $e->getMessage() . ' in ' . $e->getFile(). ' on line ' . $e->getLine());
            $retMsg = Response::json(Response::ERROR_ROUTE_PARAMS);
            if (self::$_wsIns->isEstablished(self::$_fd)) {
                self::$_wsIns->push(self::$_fd, $retMsg);
            }
        }
    }
    
}

