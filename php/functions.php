<?php
/**
 * 这里是常用的一些PHP函数，来源与网上或者自己编写
 */

/**
 * curl 函数
 * @param string $url 请求的地址
 * @param string $type POST/GET/post/get
 * @param array $data 要传输的数据
 * @param array $ua 伪造ua ['CURLOPT_HTTPHEADER'=>'','CURLOPT_USERAGENT'=>'']
 * @param string $err_msg 可选的错误信息
 * @param boole $header 是否获取相应头信息
 * @param int $timeout 超时时间
 * @param array 证书信息
 * //依次为百度蜘蛛，google蜘蛛，360蜘蛛
$ua[0]=['CURLOPT_HTTPHEADER'=>'61.129.45.72','CURLOPT_USERAGENT'=>'Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)'];
$ua[1]=['CURLOPT_HTTPHEADER'=>'216.239.51.54','CURLOPT_USERAGENT'=>'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'];
$ua[2]=['CURLOPT_HTTPHEADER'=>'101.226.168.198','CURLOPT_USERAGENT'=>'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0)'];
 */
function curl($url, $type, $data = false,$ua=[], $err_msg = false, $header=false,$timeout = 20, $cert_info = [])
{
    $type = strtoupper($type);
    if ($type == 'GET' && is_array($data)) {
        $data = http_build_query($data);
    }
    $option = array();
    if ( $type == 'POST' ) {
        $option[CURLOPT_POST] = 1;
    }
    if ($data) {
        if ($type == 'POST') {
            $option[CURLOPT_POSTFIELDS] = $data;
        } elseif ($type == 'GET') {
            $url = strpos($url, '?') !== false ? $url.'&'.$data :  $url.'?'.$data;
        }
    }
    $option[CURLOPT_URL]            = $url;
    $option[CURLOPT_FOLLOWLOCATION] = TRUE;
    $option[CURLOPT_MAXREDIRS]      = 4;
    $option[CURLOPT_RETURNTRANSFER] = TRUE;
    $option[CURLOPT_TIMEOUT]        = $timeout;
    //伪造UA 简单防止被防爬,提供多个ua
    if($ua){
        if($ua['CURLOPT_HTTPHEADER']){
            $option[CURLOPT_HTTPHEADER] = ['X-FORWARDED-FOR'=>$ua['CURLOPT_HTTPHEADER'],'CLIENT-IP'=>$ua['CURLOPT_HTTPHEADER']];
        }
        if($ua['CURLOPT_USERAGENT']){
            $option[CURLOPT_USERAGENT] = $ua['CURLOPT_USERAGENT'];
        }
    }
    if($header){
        $option[CURLOPT_HEADER]        = TRUE;
    }
    //设置证书信息
    if(!empty($cert_info) && !empty($cert_info['cert_file'])) {
        $option[CURLOPT_SSLCERT]       = $cert_info['cert_file'];
        $option[CURLOPT_SSLCERTPASSWD] = $cert_info['cert_pass'];
        $option[CURLOPT_SSLCERTTYPE]   = $cert_info['cert_type'];
    }
    //设置CA
    if(!empty($cert_info['ca_file'])) {
        // 对认证证书来源的检查，0表示阻止对证书的合法性的检查。1需要设置CURLOPT_CAINFO
        $option[CURLOPT_SSL_VERIFYPEER] = 1;
        $option[CURLOPT_CAINFO] = $cert_info['ca_file'];
    } else {
        // 对认证证书来源的检查，0表示阻止对证书的合法性的检查。1需要设置CURLOPT_CAINFO
        $option[CURLOPT_SSL_VERIFYPEER] = 0;
    }
    $ch = curl_init();
    curl_setopt_array($ch, $option);
    $response = curl_exec($ch);
    $curl_no  = curl_errno($ch);
    $curl_err = curl_error($ch);
    curl_close($ch);
    // error_log
    if($curl_no > 0) {
        if($err_msg !== false) {
            $err_msg = '('.$curl_no.')'.$curl_err;
        }
    }
    if($header){
        //分离header与body  两个换行符
        list($_header, $body) = explode("\r\n\r\n", $response, 2);
        $str = explode("\r\n",$_header);
        $header = [];
        foreach ($str as $key=>$value) {
            if($key==0){
                $header['Http-Status'] = $value;
                continue;
            }
            $tmp = explode(':',$value);
            $header[$tmp[0]] = trim($tmp[1]);
        }
        return ['header'=>$header,'body'=>$body];
    }
    return $response;
}

/**
 * 获取请求ip
 */
function ip() {
	if(getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
		$ip = getenv('HTTP_CLIENT_IP');
	} elseif(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
		$ip = getenv('HTTP_X_FORWARDED_FOR');
	} elseif(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
		$ip = getenv('REMOTE_ADDR');
	} elseif(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
		$ip = $_SERVER['REMOTE_ADDR'];
	}
	return preg_match ( '/[\d\.]{7,15}/', $ip, $matches ) ? $matches [0] : '';
}

/**
 * 获取当前页面完整URL地址
 */
function getUrl() {
	$sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
	$php_self = $_SERVER['PHP_SELF'] ? safe_replace($_SERVER['PHP_SELF']) : safe_replace($_SERVER['SCRIPT_NAME']);
	$path_info = isset($_SERVER['PATH_INFO']) ? safe_replace($_SERVER['PATH_INFO']) : '';
	$relate_url = isset($_SERVER['REQUEST_URI']) ? safe_replace($_SERVER['REQUEST_URI']) : $php_self.(isset($_SERVER['QUERY_STRING']) ? '?'.safe_replace($_SERVER['QUERY_STRING']) : $path_info);
	return $sys_protocal.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '').$relate_url;
}

/**
* 字符串加密、解密函数
* @param	string	$txt		字符串
* @param	string	$operation	ENCODE为加密，DECODE为解密，可选参数，默认为ENCODE，
* @param	string	$key		密钥：数字、字母、下划线
* @param	string	$expiry		过期时间
* @return	string
*/
function auth($string, $operation = 'ENCODE', $key = '', $expiry = 0) {
	$ckey_length = 4;
	$key = md5($key != '' ? $key : 'WtFFiJrDVRcDUrwRW9W3';
	$keya = md5(substr($key, 0, 16));
	$keyb = md5(substr($key, 16, 16));
	$keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';

	$cryptkey = $keya.md5($keya.$keyc);
	$key_length = strlen($cryptkey);

	$string = $operation == 'DECODE' ? base64_decode(strtr(substr($string, $ckey_length), '-_', '+/')) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
	$string_length = strlen($string);

	$result = '';
	$box = range(0, 255);

	$rndkey = array();
	for($i = 0; $i <= 255; $i++) {
		$rndkey[$i] = ord($cryptkey[$i % $key_length]);
	}

	for($j = $i = 0; $i < 256; $i++) {
		$j = ($j + $box[$i] + $rndkey[$i]) % 256;
		$tmp = $box[$i];
		$box[$i] = $box[$j];
		$box[$j] = $tmp;
	}

	for($a = $j = $i = 0; $i < $string_length; $i++) {
		$a = ($a + 1) % 256;
		$j = ($j + $box[$a]) % 256;
		$tmp = $box[$a];
		$box[$a] = $box[$j];
		$box[$j] = $tmp;
		$result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
	}

	if($operation == 'DECODE') {
		if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
			return substr($result, 26);
		} else {
			return '';
		}
	} else {
		return $keyc.rtrim(strtr(base64_encode($result), '+/', '-_'), '=');
	}
}

/**
 * 文件下载
 * @param $filepath 文件路径
 * @param $filename 文件名称
 */
function fileDown($filepath, $filename = '') {
	if(!$filename) $filename = basename($filepath);
	if(is_ie()) $filename = rawurlencode($filename);
	$filetype = fileext($filename);
	$filesize = sprintf("%u", filesize($filepath));
	if(ob_get_length() !== false) @ob_end_clean();
	header('Pragma: public');
	header('Last-Modified: '.gmdate('D, d M Y H:i:s') . ' GMT');
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Cache-Control: pre-check=0, post-check=0, max-age=0');
	header('Content-Transfer-Encoding: binary');
	header('Content-Encoding: none');
	header('Content-type: '.$filetype);
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	header('Content-length: '.$filesize);
	readfile($filepath);
	exit;
}

/**
 * 获取精确到微妙的时间戳
 */
function getmicrotime() {
	list($usec, $sec) = explode(" ",microtime());
	return ((float)$usec + (float)$sec);
}

/**
 * 把INT时间转换字符串时间
 * @param $n INT时间
 */
 function dataformat($n) {
	$hours = floor($n/3600);
	$minite	= floor($n%3600/60);
	$secend = floor($n%3600%60);
	$minite = $minite < 10 ? "0".$minite : $minite;
	$secend = $secend < 10 ? "0".$secend : $secend;
	if($n >= 86400){
		$day = floor($n/3600);
		$hours = floor($n%86400/3600);
		return $day.'天,'.$hours.":".$minite.":".$secend;
	}elseif($n >= 3600 && $n < 86400){
		return $hours.":".$minite.":".$secend;
	}else{
		return $minite.":".$secend;
	}
}

/**
 * 判断是否是闰年
 * @param  boolean $year [description]
 * @return boolean       [description]
 */
function isLeapYear($year=false){
	$time = $year ? mktime(20,20,20,4,20,$year) : time();
	return date('L',$time);
}

/**
 * 获取客户端请求方式，如果传值了则返回true or false，否则返回具体的请求方式
 * @param  boolean $type [description]
 * @return [type]        [description]
 */
function getRequestMethod($type=false){
	if(!isset($_SERVER['REQUEST_METHOD'])) return false;
	if($type && !in_array($type,['isGet','isPost','isAjax','isAjaxGet','isAjaxPost'])) return false;
	if(strtolower($_SERVER['REQUEST_METHOD']) == 'post'){
		$requestMethod = 'isPost';
	}elseif(strtolower($_SERVER['REQUEST_METHOD']) == 'get'){
		$requestMethod = 'isGet';
	}
	if(isset($_SERVER["HTTP_X_REQUESTED_WITH"]) && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"])=="xmlhttprequest"){
		$requestMethod = 'isAjax'.$requestMethod;
	}
	return $type ? $type==$requestMethod : $requestMethod;
}

/**
 * [getRand 概率函数] 如：传入['a'=>80,'b'=>20] 根据设置的概率 返回a或者b
 * @param  [type] $proArr [description]
 * @return [type]         [description]
 */
function getRand($proArr) { 
    $result = ''; 
    //概率数组的总概率精度 
    $proSum = array_sum($proArr); 
    //概率数组循环 
    foreach ($proArr as $key => $proCur) { 
        $randNum = mt_rand(1, $proSum);             //抽取随机数
        if ($randNum <= $proCur) { 
            $result = $key;                         //得出结果
            break; 
        } else { 
            $proSum -= $proCur;
        } 
    } 
    unset ($proArr); 
    return $result; 
}

/**
 * [readAllDir 遍历文件夹下的所有文件]
 * @param  [type] $dir [description]
 * @return [type]      [description]
 */
function readAllDir ( $dir ){
    $result = array();
    $handle = opendir($dir);
    if ( $handle )
    {
        while ( ( $file = readdir ( $handle ) ) !== false )
        {
            if ( $file != '.' && $file != '..')
            {
                $cur_path = $dir . DIRECTORY_SEPARATOR . $file;
                if ( is_dir ( $cur_path ) )
                {
                    $result['dir'][$cur_path] = readAllDir ( $cur_path );
                }
                else
                {
                    $result[] = $file;
                }
            }
        }
        closedir($handle);
    }
    return $result;
}

/**
 * 获取文件名后缀
 * @param  [type] $filename [description]
 * @return [type]           [description]
 */
function getExtension($filename){ 
	$myext = substr($filename, strrpos($filename, '.')); 
	return str_replace('.','',$myext); 
}

/**
 * 格式化文件大小
 * @param  [type] $filename [文件路径+文件名]
 * @return [type]           [description]
 */
function getFormatSize($filename) {
	if(!is_file($filename)) return false;
	$size = filesize($filename);
    $sizes = array(" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB"); 
    if ($size == 0) {
        return('n/a');
    } else { 
      return (round($size/pow(1024, ($i = floor(log($size, 1024)))), 2) . $sizes[$i]);  
    }
}

/**
 * [getRandStr 获取随机字符串]
 * @param  integer $length  [获取字符串的长度]
 * @param  array   $type    ['all' 数字字母大小写;'num',数字;'upper',大写;'lower',小写]
 * @return string           [返回字符串]
 */
function getRandStr($length=6,$type=['all'=>true]){
	$str = null;
	$strPol = '';
	if(isset($type['all'])&&$type['all']){
		$strPol .= "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
	}else{
		if(isset($type['upper'])&&$type['upper']){
			$strPol .='ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		}
		if(isset($type['num'])&&$type['num']){
			$strPol .='0123456789';
		}
		if(isset($type['lower'])&&$type['lower']){
			$strPol .='abcdefghijklmnopqrstuvwxyz';
		}
	}
	$max = strlen($strPol)-1;
	for($i=0;$i<$length;$i++){
		$str.=$strPol[rand(0,$max)];//rand($min,$max)生成介于min和max两个数之间的一个随机整数
	}
	return $str;
}
/**
*@todo 读取文件最后几行
*/
function tail($file,$num){
    $fp = fopen($file,"r");  
    $pos = -2;  
    $eof = "";  
    $head = false;   //当总行数小于Num时，判断是否到第一行了  
    $lines = array();  
    while($num>0){  
        while($eof != "\n"){  
            if(fseek($fp, $pos, SEEK_END)==0){    //fseek成功返回0，失败返回-1  
                $eof = fgetc($fp);  
                $pos--;  
            }else{                               //当到达第一行，行首时，设置$pos失败  
                fseek($fp,0,SEEK_SET);  
                $head = true;                   //到达文件头部，开关打开  
                break;  
            }  
              
        }  
        array_unshift($lines,fgets($fp));  
        if($head){ break; }                 //这一句，只能放上一句后，因为到文件头后，把第一行读取出来再跳出整个循环  
        $eof = "";  
        $num--;  
    }  
    fclose($fp);  
    return $lines;  
}
?>
