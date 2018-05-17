<?php
/**
 * @todo 用beanbun采集国内保健品评论数据
 * @author RC
 * @date 2018-07-01
 */
define('BASE_PATH',str_replace('\\','/',realpath(dirname(__DIR__).'/'))."/");
use Beanbun\Beanbun;
use Beanbun\Lib\Db;
require_once(BASE_PATH . '/vendor/autoload.php');
//定义全局变量
global $redis,$url_data;
//end

//实例化redis 采集数据先存放redis后期再处理
$redis = new Redis();
$redis->connect('127.0.0.1','6379');
//end

//实例化采集参数
$beanbun = new Beanbun;

//读取待采集的数据
$file = fopen('./jd_comments_pid.csv','r');
$url_data = [];
while ($data = fgetcsv($file)) {
	if($data[0]){
		$url_data[]=['https://sclub.jd.com/comment/productPageComments.action?callback=fetchJSON_comment98vv'.mt_rand(1,199).'&productId='.$data[2].'&score=0&sortType=5&page=0&pageSize=10&isShadowSku=0&rid=0&fold=1',
					[
						'timeout' => 10,
						'headers' => [	
										'accept-encoding'=>'gzip, deflate, br',
										'accept-language'=>'zh-CN,zh;q=0.9',
										'referer' => $data[0],
            						 ],
        ]];
	}
}
fclose($file);
//end

//评论采集标准url
$base_url = 'https://sclub.jd.com/comment/productPageComments.action?score=0&sortType=5&pageSize=10&isShadowSku=0&fold=1&callback=fetchJSON_comment98vv';
//end

//参数配置
$beanbun->name = 'jd_comments_guonei';
$beanbun->count = 10;
$beanbun->seed = $url_data;
$beanbun->logFile = __DIR__ . '/jd_comments_access.log';
//end

//启用redis作为队列服务
$beanbun->setQueue('memory', [
    'host' => '127.0.0.1',
     'port' => '2217'
]);


$beanbun->beforeDownloadPage = function ($beanbun) {
    // 在爬取前设置请求的 headers 等
	$beanbun->interval = mt_rand(1,4);
};

$beanbun->afterDownloadPage = function ($beanbun) {
    //使用全局变量数据进行操作
    global $redis;
    $current_url_info = parse_url($beanbun->url); //当前url的包含的信息
    parse_str($current_url_info['query'],$current_url_info_params);//当前url的参数信息 callback，productId等
    //抓取数据的前置长度
    $pre_length = strlen($current_url_info_params['callback'].'(');
    $_data = substr($beanbun->page,$pre_length,-2);
    //判断数据是否为空
    $_data = json_decode(iconv('GBK','UTF-8',$_data),true);
    if($_data['comments']){
    	$redis->set('s4_'.$current_url_info_params['productId'].'_'.$current_url_info_params['page'], json_encode($_data['comments']));
    	//翻页抓取，直到抓完
    	$current_url_info_params['page']=$current_url_info_params['page']+1;
    	$current_url_info_params['callback']='fetchJSON_comment98vv'.mt_rand(1,129);
    	$beanbun->queue()->add('https://sclub.jd.com/comment/productPageComments.action?'.http_build_query($current_url_info_params));
    }else{
    	Seaslog::info('产品id为：'.$current_url_info_params['productId'].'已经采集完毕，共采集：'.$current_url_info_params['page'].'页');
    }
    // 把刚刚爬取的地址标记为已经爬取
    $beanbun->queue()->queued($beanbun->queue);
};
// 不需要框架来发现新的网址，
$beanbun->discoverUrl = function () {};

// 关闭爬虫进程时写一条日志，记录进程关闭成功
$beanbun->stopWorker = function($beanbun) {
    $beanbun->log("beanbun worker id {$beanbun->id} stop success.");
};
$beanbun->start();


?>
