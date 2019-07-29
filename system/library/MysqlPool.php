<?php
namespace app\system\library;
/********************************************************************************************
 swoole的mysql的连接池
*********************************************************************************************/

class MysqlPool
{
    private static $_instance;    //本类实例
    private $pool;
    private $config;              //redis链接配置
    private $count;               //当前连接池计数
    
    private function __construct($config) {
        $this->config = $config;
        $this->pool = new \Swoole\Coroutine\Channel($config['pool_min_size'] + 1);
        for ($i = 0; $i < $config['pool_min_size']; $i++) {
            $mysql = new BasePDO($config);
            if ($mysql->_isConnectOk) {
                $this->count++;
                $this->pool->push($mysql);
            } else {
                throw new \RuntimeException("Failed to connect mysql server");
            }
        }
    }

    /*-------------------------------------------------------------------------------------- 
    | 私有化克隆机制
    --------------------------------------------------------------------------------------*/
    private function __clone() {}

    /*--------------------------------------------------------------------------------------
    | 获取mysql单例
    |---------------------------------------------------------------------------------------
    | @param  array    $config
    |
    | @return object
    --------------------------------------------------------------------------------------*/
    public static function getInstance($config = []) {
        if (empty(self::$_instance)) {
            if (empty($config))
                throw new \RuntimeException("Redis config is empty");

            self::$_instance = new self($config);
        }
        return self::$_instance;
    }

    /*-------------------------------------------------------------------------------------- 
    | 获取连接池中mysql连接
    --------------------------------------------------------------------------------------*/
    public function get()
    {
        $mysql = null;
        if ($this->pool->isEmpty()) {
            //连接数没达到最大，新建连接入池
            if ($this->count < $this->config['pool_max_size']) { 
                $this->count++;
                $mysql = new BasePDO($this->config);
                $this->pool->push($mysql);
            } else {
                //pool_wait_time为出队的最大的等待时间
                $mysql = $this->pool->pop($this->config['pool_wait_time']);
            }
        } else {
            $mysql = $this->pool->pop($this->config['pool_wait_time']);
        }
        return $mysql;
    }

    /*-------------------------------------------------------------------------------------- 
    | 使用完mysql后，将mysql归还到连接池中
    --------------------------------------------------------------------------------------*/
    public function back($obj) {
        if ($obj) {
            $this->pool->push($obj);
        }
    }

}
