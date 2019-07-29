<?php
/*******************************************************************************************
 * 公用方法类
 * @copyright   Copyright(c) 2015
 * @author      iProg
 * @version     1.0
 *******************************************************************************************/
namespace app\system\core;

use app\library\BaseRedis;
use app\library\HashRedis;
use app\library\BasePDO;

trait Common {

    private $_hashRedis;
    private $_redis;
    private $_mysql;

    /*---------------------------------------------------------------------------------------
    | 获取数据库实例
    |---------------------------------------------------------------------------------------*/
    public function getDB($name = 'mysql', $whichDB = 'default'){
        switch ($name) {
            case 'mysql':
                if (empty($this->_mysql)) {
                    $conf = Config::get('database', $whichCache);
                    $this->_mysql = new BasePDO($conf);
                }
                return $this->_mysql;
                break;
            default:
                echo '参数错误';exit();
                break;
        }
    }
    
    /*---------------------------------------------------------------------------------------
    | 获取缓存实例
    |----------------------------------------------------------------------------------------
    | @access  final   public
    | @param   string  $name          缓存名字：redis、hashRedis
    | @param   string  $whichCache    哪一个缓存：
    |                                 如果获取redis的master实例：$whichCache = 'master';
    ----------------------------------------------------------------------------------------*/
    public function getCache($name = 'redis', $whichCache = 'master'){
        switch ($name) {
            case 'redis':
                if (empty($this->_redis)) {
                    $conf = Config::get('redis', $whichCache);
                    $this->_redis = new BaseRedis($conf);
                }
                return $this->_redis;
                break;
            case 'hashRedis':
                if (empty($this->_hashRedis)) {
                    $hashRedisConf = Config::get('hashRedis');
                    $this->_hashRedis = new HashRedis($hashRedisConf);
                }
                return $this->_hashRedis;
                break;
            default:
                echo '参数错误';exit();
                break;
        }
    }
        
}

?>


