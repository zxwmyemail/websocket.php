ubuntu搭建swoole环境（php7.3）

第一步：安装php7.3
sudo apt-get update
sudo apt-get install python-software-properties software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt-get update
sudo apt-cache search php7*
sudo apt-get install php7.3 php7.3-dev  php7.3-fpm  php7.3-bcmath php7.3-cli php7.3-gd  php7.3-imap php7.3-mysql php7.3-odbc  php7.3-opcache php7.3-pgsql php7.3-sqlite3 php7.3-zip php7.3-curl php7.3-json php7.3-mbstring php7.3-xml  php7.3-xmlrpc php7.3-intl 

第二步：redis扩展安装
sudo wget http://pecl.php.net/get/redis-4.0.2.tgz
sudo /usr/bin/phpize
sudo ./configure
sudo make
sudo make install

Ubuntu添加redis.so扩展，
cd /etc/php/7.3/mods-available/
sudo vi redis.ini   添加如下内容：extension=redis.so
sudo ln -s /etc/php/7.3/mods-available/redis.ini  /etc/php/7.3/fpm/conf.d/20-redis.ini   创建软连接
sudo ln -s /etc/php/7.3/mods-available/redis.ini  /etc/php/7.3/cli/conf.d/20-redis.ini   创建软连接
sudo service php7.3-fpm restart   重启php-fpm

第三步：安装swoole并添加swoole.so扩展，
sudo apt install php-pear
sudo pecl install swoole
cd /etc/php/7.3/mods-available/
sudo vi swoole.ini   添加如下内容：extension=swoole.ini   
sudo ln -s /etc/php/7.3/mods-available/swoole.ini     /etc/php/7.3/fpm/conf.d/20-swoole.ini   创建软连接
sudo ln -s /etc/php/7.3/mods-available/swoole.ini     /etc/php/7.3/cli/conf.d/20-swoole.ini   创建软连接
sudo service php7.3-fpm restart   重启php-fpm


第四部：安装nginx和ssl证书，然后，nginx反向代理wss（微信小游戏必须wss），配置中添加如下：
sudo apt-get install nginx

http段添加：
upstream websocket {
    server 127.0.0.1:13146;
}

server段添加：
location /mog {
    proxy_pass http://websocket;
    proxy_http_version 1.1;
    proxy_connect_timeout 4s;
    proxy_read_timeout 480s;
    proxy_send_timeout 12s;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header X-Real-IP $remote_addr;
}

然后客户端使用  wss://域名/mog   即可访问


--------------------------------------------------------------------------------------------------------------------------
接口文档：
websocket请求数据示例（json字符串）
{
	"route": "userCtrl@register",                 // 路由，由@分割，@前为控制器类名称，@后为控制器方法
	"request": {                                  // 请求参数
		"openid": "ddkdkfjke=dferkdfkdkf",
	    "nickname": "张三",
	}
}


websocket返回数据示例（json字符串）
{
	"retcode": 0,                                 // 返回消息类型码
	"message": "消息描述"                          // 消息类型码描述
	"reqtime": "2019-07-14 12:00:00"              // 请求时间
	"data": {                                     // 返回的数据
		"openid": "ddkdkfjke=dferkdfkdkf",
	    "nickname": "张三",
	}
}


redis中玩家数据保存json格式，openid作为键，其它信息为其值：
"oxfddfdfdfdfdfd": {
	"fd": 1,                                       // 该用户所在服务器的连接句柄
	"openid": "oxfddfdfdfdfdfd",                   // 用户openid
	"nickname": "张三",                            // 用户昵称
	"avatarUrl": "http://dfdfdfd ",                // 用户头像地址
	"channel": "172.19.95.178:13146",              // 用户所在服务器的redis订阅频道
	"isFighting": 1,                               // 是否正在参与对战，0待匹配, 1正在对战, 2对战完毕，3房间已撤销
	"startTime": 12369855555,                      // 对战开始时间
	"endTime"  : 1369855555,                       // 对战结束时间
	"totalTime": 600,                              // 对战倒计时总时间
	"opponent" : ["oxf2323ddfdfdfdfdfd"],          // 对战的对方openid数组
	"foundElem":[1,2,3,4,5],                       // 对战中自己找到的元素
}



------------------------------------------------------------------------------------------------------------------------------
websocket负载均衡下，消息发送，使用redis的发布订阅来进行，比如有3台websocket服务器：
a服务器：本地订阅通道为a，还有一个订阅通道为all
b服务器：本地订阅通道为b，还有一个订阅通道为all
c服务器：本地订阅通道为c，还有一个订阅通道为all

当a服务器上的玩家p1需要向b上的玩家p2发送消息时:
a服务器收到p1消息后，先到redis中查询p2所在服务器的本地通道名称为b，然后向b通道发送一条消息；
b服务器订阅了b通道，所以b服务器收到消息，然后把消息转发到b服务器上的websocket服务器，在找到b玩家的连接句柄fd，把消息发送到p2玩家；
消息发送完成；

如果是广播消息，则a服务器向all通道发送消息，然后所有服务器都订阅了all消息，所以所有服务器收到消息后，转发给websocket服务器，进行全网广播；


注意，需要开启订阅进程： sub.sh start

------------------------------------------------------------------------------------------------------------------------------
玩家匹配机制：
开启玩家匹配进程: match.sh start
利用redis的set集合，进行随机匹配操作！
玩家匹配时，向redis的set集合中放入待匹配的openid，然后后台匹配进程，不断检测，满足匹配条件，直接匹配，然后推送匹配信息给玩家；




{"route": "userCtrl@register", "request": {"openid": "1","nickname": "张三","avatarUrl": "http://www.baidu.com"}}
{"route": "userCtrl@register", "request": {"openid": "2","nickname": "李四","avatarUrl": "http://www.baidu.com"}}

{"route":"userCtrl@broadcast","request":{"name":"李四","msg":"我在测试广播，你收到了吗？"}}
{"route":"userCtrl@chat","request":{"from":{"openid":"2","nickname":"张三","avatarUrl":"http://www.baidu.com"},"msg":"helloworld!"}}

{"route": "matchCtrl@start", "request": {"openid": "1","stageId":1}}
{"route": "matchCtrl@start", "request": {"openid": "2","stageId":1}}

{"route": "serverCtrl@findElem", "request": {"openid": "2","findElem": [1,2,3], "opponent": ["oxf2323ddfdfdfdfdfd"]}}

{"route":"roomCtrl@create","request":{"openid":"1","stageId":1}}

{"route": "roomCtrl@joinByInvite", "request": {"openid": "2", "inviter": "1"}}

{"route": "roomCtrl@cancel", "request": {"openid": "1"}}

{"route": "http@getBattleStatus", "request": {"openid": "2"}}

