<?php

declare(strict_types=1);

/**
 * curl 函数.
 *
 * @param string $url     请求的地址
 * @param string $type    POST/GET/post/get
 * @param array  $data    要传输的数据
 * @param array  $ua      伪造ua ['CURLOPT_HTTPHEADER'=>'','CURLOPT_USERAGENT'=>'']
 * @param string $err_msg 可选的错误信息
 * @param bool   $header  是否获取相应头信息
 * @param int    $timeout 超时时间
 * @param array 证书信息
 * //依次为百度蜘蛛，google蜘蛛，360蜘蛛
 * $ua[0]=['CURLOPT_HTTPHEADER'=>'61.129.45.72','CURLOPT_USERAGENT'=>'Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)'];
 * $ua[1]=['CURLOPT_HTTPHEADER'=>'216.239.51.54','CURLOPT_USERAGENT'=>'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'];
 * $ua[2]=['CURLOPT_HTTPHEADER'=>'101.226.168.198','CURLOPT_USERAGENT'=>'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0)'];
 */
function curl($url, $type, $data = false, $ua = [], $err_msg = false, $header = false, $timeout = 20, $cert_info = [])
{
    $type = strtoupper($type);
    if ('GET' === $type && is_array($data)) {
        $data = http_build_query($data);
    }
    $option = [];
    if ('POST' === $type) {
        $option[CURLOPT_POST] = 1;
    }
    if ($data) {
        if ('POST' === $type) {
            $option[CURLOPT_POSTFIELDS] = $data;
        } elseif ('GET' === $type) {
            $url = str_contains($url, '?') ? $url.'&'.$data : $url.'?'.$data;
        }
    }
    $option[CURLOPT_URL] = $url;
    $option[CURLOPT_FOLLOWLOCATION] = true;
    $option[CURLOPT_MAXREDIRS] = 4;
    $option[CURLOPT_RETURNTRANSFER] = true;
    $option[CURLOPT_TIMEOUT] = $timeout;
    // 伪造UA 简单防止被防爬,提供多个ua
    if ($ua) {
        if ($ua['CURLOPT_HTTPHEADER']) {
            $option[CURLOPT_HTTPHEADER] = ['X-FORWARDED-FOR' => $ua['CURLOPT_HTTPHEADER'], 'CLIENT-IP' => $ua['CURLOPT_HTTPHEADER']];
        }
        if ($ua['CURLOPT_USERAGENT']) {
            $option[CURLOPT_USERAGENT] = $ua['CURLOPT_USERAGENT'];
        }
    }
    if ($header) {
        $option[CURLOPT_HEADER] = true;
    }
    // 设置证书信息
    if (!empty($cert_info) && !empty($cert_info['cert_file'])) {
        $option[CURLOPT_SSLCERT] = $cert_info['cert_file'];
        $option[CURLOPT_SSLCERTPASSWD] = $cert_info['cert_pass'];
        $option[CURLOPT_SSLCERTTYPE] = $cert_info['cert_type'];
    }
    // 设置CA
    if (!empty($cert_info['ca_file'])) {
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
    $curl_no = curl_errno($ch);
    $curl_err = curl_error($ch);
    curl_close($ch);
    // error_log
    if ($curl_no > 0) {
        if (false !== $err_msg) {
            $err_msg = '('.$curl_no.')'.$curl_err;
        }
    }
    if ($header) {
        // 分离header与body  两个换行符
        [$_header, $body] = explode("\r\n\r\n", $response, 2);
        $str = explode("\r\n", $_header);
        $header = [];
        foreach ($str as $key => $value) {
            if (0 === $key) {
                $header['Http-Status'] = $value;

                continue;
            }
            $tmp = explode(':', $value);
            $header[$tmp[0]] = trim($tmp[1]);
        }

        return ['header' => $header, 'body' => $body];
    }

    return $response;
}

/**
 * 获取请求ip.
 */
function ip()
{
    if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
        $ip = getenv('HTTP_CLIENT_IP');
    } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
        $ip = getenv('HTTP_X_FORWARDED_FOR');
    } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
        $ip = getenv('REMOTE_ADDR');
    } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    return preg_match('/[\d\.]{7,15}/', $ip, $matches) ? $matches[0] : '';
}

/**
 * 获取当前页面完整URL地址
 * @return string
 */
function getUrl(): string
{
    $sys_protocal = isset($_SERVER['SERVER_PORT']) && '443' === $_SERVER['SERVER_PORT'] ? 'https://' : 'http://';
    $php_self = $_SERVER['PHP_SELF'] ? safeReplace($_SERVER['PHP_SELF']) : safeReplace($_SERVER['SCRIPT_NAME']);
    $path_info = isset($_SERVER['PATH_INFO']) ? safeReplace($_SERVER['PATH_INFO']) : '';
    $relate_url = isset($_SERVER['REQUEST_URI']) ? safeReplace($_SERVER['REQUEST_URI']) : $php_self.(isset($_SERVER['QUERY_STRING']) ? '?'.safeReplace($_SERVER['QUERY_STRING']) : $path_info);

    return $sys_protocal.($_SERVER['HTTP_HOST'] ?? '').$relate_url;
}

/**
 * 安全过滤函数
 *
 * @param string $string 要过滤的字符串
 * @return string
 */
function safeReplace(string $string): string
{
    $string = str_replace('%20','',$string);
    $string = str_replace('%27','',$string);
    $string = str_replace('%2527','',$string);
    $string = str_replace('*','',$string);
    $string = str_replace('"','"',$string);
    $string = str_replace("'",'',$string);
    $string = str_replace('"','',$string);
    $string = str_replace(';','',$string);
    $string = str_replace('<','<',$string);
    $string = str_replace('>','>',$string);
    $string = str_replace("{",'',$string);
    $string = str_replace('}','',$string);
    $string = str_replace('\\','',$string);

    return $string;
}

/**
 * 字符串加密、解密函数.
 *
 * @param mixed $string
 *
 * @param string $operation ENCODE为加密，DECODE为解密，可选参数，默认为ENCODE，
 * @param string $key 密钥：数字、字母、下划线
 * @param int $expiry 过期时间
 * @return string
 */
function cryptograph(string $string, string $operation = 'ENCODE', string $key = '', int $expiry = 0): string
{
    $ckey_length = 4;
    $key = md5('' !== $key ? $key : 'WtFFiJrDVRcDUrwRW9W3');
    $keya = md5(substr($key, 0, 16));
    $keyb = md5(substr($key, 16, 16));
    $keyc = $ckey_length ? ('DECODE' === $operation ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';

    $cryptkey = $keya.md5($keya.$keyc);
    $key_length = strlen($cryptkey);

    $string = 'DECODE' === $operation ? base64_decode(strtr(substr($string, $ckey_length), '-_', '+/'), true) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
    $string_length = strlen($string);

    $result = '';
    $box = range(0, 255);

    $rndkey = [];
    for ($i = 0; $i <= 255; ++$i) {
        $rndkey[$i] = ord($cryptkey[$i % $key_length]);
    }

    for ($j = $i = 0; $i < 256; ++$i) {
        $j = ($j + $box[$i] + $rndkey[$i]) % 256;
        $tmp = $box[$i];
        $box[$i] = $box[$j];
        $box[$j] = $tmp;
    }

    for ($a = $j = $i = 0; $i < $string_length; ++$i) {
        $a = ($a + 1) % 256;
        $j = ($j + $box[$a]) % 256;
        $tmp = $box[$a];
        $box[$a] = $box[$j];
        $box[$j] = $tmp;
        $result .= chr(ord($string[$i]) ^ $box[($box[$a] + $box[$j]) % 256]);
    }

    if ('DECODE' === $operation) {
        if ((0 === substr($result, 0, 10) || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) === substr(md5(substr($result, 26).$keyb), 0, 16)) {
            return substr($result, 26);
        }

        return '';
    }

    return $keyc.rtrim(strtr(base64_encode($result), '+/', '-_'), '=');
}

/**
 * 获取精确到微妙的时间戳.
 * @return float
 */
function getMicroTime(): float
{
    [$usec, $sec] = explode(' ', microtime());

    return (float) $usec + (float) $sec;
}

/**
 * 时间戳转换字符串时间.
 *
 * @param int $n 时间
 */
function dateformat(int $n): string
{
    $hours = floor($n / 3600);
    $minite = floor($n % 3600 / 60);
    $secend = floor($n % 3600 % 60);
    $minite = $minite < 10 ? '0'.$minite : $minite;
    $secend = $secend < 10 ? '0'.$secend : $secend;

    if ($n >= 3600 && $n < 86400) {
        return $hours.':'.$minite.':'.$secend;
    }

    if ($n >= 86400) {
        $day = floor($n / 3600);
        $hours = floor($n % 86400 / 3600);

        return $day.'天,'.$hours.':'.$minite.':'.$secend;
    }

    return $minite.':'.$secend;
}

/**
 * 判断是否是闰年.
 *
 * @param int $year 年份
 *
 * @return bool
 */
function isLeapYear(int $year = 0): bool
{
    $time = $year > 0 ? mktime(20, 20, 20, 4, 20, $year) : time();

    return date('L', $time);
}

/**
 * 获取客户端请求方式，如果传值了则返回true or false，否则返回具体的请求方式.
 * @param string $type 请求方式 isGet isPost isAjax isAjaxGet isAjaxPost
 * @return bool|string
 */
function getRequestMethod(string $type = '')
{
    if (!isset($_SERVER['REQUEST_METHOD'])) {
        return false;
    }
    if ($type && !in_array($type, ['isGet', 'isPost', 'isAjax', 'isAjaxGet', 'isAjaxPost'], true)) {
        return false;
    }
    $originalMethod = strtolower($_SERVER['REQUEST_METHOD']);
    $method = [
        'post' => 'isPost',
        'get' => 'isGet',
        $originalMethod => 'is'.ucfirst($originalMethod),
    ];
    $requestMethod = $method[$originalMethod] ?? $originalMethod;
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 'xmlhttprequest' === strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        $requestMethod = 'isAjax'.ucfirst($originalMethod);
    }

    return $type ? $type === $requestMethod : $requestMethod;
}

/**
 * 概率函数 如：传入['a'=>80,'b'=>20] 根据设置的概率 返回a或者b.
 * @param array $proArr 概率数组
 * @return int|string
 * @throws Exception
 */
function getRand(array $proArr)
{
    $result = '';
    // 概率数组的总概率精度
    $proSum = array_sum($proArr);
    // 概率数组循环
    foreach ($proArr as $key => $proCur) {
        // 抽取随机数
        $randNum = random_int(1, $proSum);
        if ($randNum <= $proCur) {
            $result = $key;

            break;
        }
        $proSum -= $proCur;
    }
    unset($proArr);

    return $result;
}

/**
 * 遍历文件夹下的所有文件
 * @param string $dir 文件夹路径
 * @return array
 */
function readAllDir(string $dir): array
{
    $result = [];
    $handle = opendir($dir);
    if ($handle) {
        while (($file = readdir($handle)) !== false) {
            if ('.' !== $file && '..' !== $file) {
                $cur_path = $dir.DIRECTORY_SEPARATOR.$file;
                if (is_dir($cur_path)) {
                    $result['dir'][$cur_path] = readAllDir($cur_path);
                } else {
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
 * @param string $filename 文件名
 * @return array|false|string|string[]
 */
function getExtension(string $filename)
{
    $ext = substr($filename, strrpos($filename, '.'));

    return str_replace('.', '', $ext);
}

/**
 * 格式化文件大小.
 * @param string $filePath 文件路径
 * @return false|string
 */
function getFormatSize(string $filePath)
{
    if (!is_file($filePath)) {
        return false;
    }
    $size = filesize($filePath);
    $sizes = [' Bytes', ' KB', ' MB', ' GB', ' TB', ' PB', ' EB', ' ZB', ' YB'];
    if (0 === $size) {
        return 'n/a';
    }

    return round($size / 1024 ** ($i = floor(log($size, 1024))), 2).$sizes[$i];
}

/**
 * 获取随机字符串.
 *
 * @param int $length 获取字符串的长度
 * @param array $type ['all' 数字字母大小写;'num',数字;'upper',大写;'lower',小写]
 *
 * @return string 返回字符串
 * @throws Exception
 */
function getRandStr(int $length = 6, array $type = ['all' => true]): string
{
    $str = '';
    $strPol = '';
    if (isset($type['all']) && $type['all']) {
        $strPol .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
    } else {
        if (isset($type['upper']) && $type['upper']) {
            $strPol .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }
        if (isset($type['num']) && $type['num']) {
            $strPol .= '0123456789';
        }
        if (isset($type['lower']) && $type['lower']) {
            $strPol .= 'abcdefghijklmnopqrstuvwxyz';
        }
    }
    $max = strlen($strPol) - 1;
    for ($i = 0; $i < $length; ++$i) {
        // rand($min,$max)生成介于min和max两个数之间的一个随机整数
        $str .= $strPol[random_int(0, $max)];
    }

    return $str;
}

/**
 * 读取文件最后几行.
 * @param string $file
 * @param int $num
 * @return array
 */
function tail(string $file, int $num): array
{
    $fp = fopen($file, 'r');
    $pos = -2;
    $eof = '';
    $head = false;   // 当总行数小于Num时，判断是否到第一行了
    $lines = [];
    while ($num > 0) {
        while ("\n" !== $eof) {
            if (0 === fseek($fp, $pos, SEEK_END)) {    // fseek成功返回0，失败返回-1
                $eof = fgetc($fp);
                --$pos;
            } else {                               // 当到达第一行，行首时，设置$pos失败
                fseek($fp, 0, SEEK_SET);
                $head = true;                   // 到达文件头部，开关打开

                break;
            }
        }
        array_unshift($lines, fgets($fp));
        if ($head) {
            break;
        }                 // 这一句，只能放上一句后，因为到文件头后，把第一行读取出来再跳出整个循环
        $eof = '';
        --$num;
    }
    fclose($fp);

    return $lines;
}

/**
 * 大文件下载.
 *
 * @param string $filePath      文件路径
 * @param string $fancyName     下载时显示的文件名
 * @param bool   $forceDownload 是否强制下载
 * @param int    $speedLimit    下载速度限制
 * @param string $contentType   告知浏览器下载的文件类型
 */
function downloadFile(string $filePath, string $fancyName = '', bool $forceDownload = true, int $speedLimit = 0, string $contentType = ''): bool
{
    if (!is_readable($filePath)) {
        header('HTTP/1.1 404 Not Found');

        return false;
    }
    $fileStat = stat($filePath);
    $lastModified = $fileStat['mtime'];

    $md5 = md5($fileStat['mtime'].'='.$fileStat['ino'].'='.$fileStat['size']);
    $etag = '"'.$md5.'-'.crc32($md5).'"';

    header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastModified).' GMT');
    header("ETag: {$etag}");

    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $lastModified) {
        header('HTTP/1.1 304 Not Modified');

        return true;
    }

    if (isset($_SERVER['HTTP_IF_UNMODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_UNMODIFIED_SINCE']) < $lastModified) {
        header('HTTP/1.1 304 Not Modified');

        return true;
    }

    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
        header('HTTP/1.1 304 Not Modified');

        return true;
    }

    if ('' === $fancyName) {
        $fancyName = basename($filePath);
    }

    if ('' === $contentType) {
        $contentType = 'application/octet-stream';
    }

    $fileSize = $fileStat['size'];

    $contentLength = $fileSize;
    $isPartial = false;

    if (isset($_SERVER['HTTP_RANGE'])) {
        if (preg_match('/^bytes=(\d*)-(\d*)$/', $_SERVER['HTTP_RANGE'], $matches)) {
            $startPos = $matches[1];
            $endPos = $matches[2];

            if ('' === $startPos && '' === $endPos) {
                return false;
            }

            if ('' === $startPos) {
                $startPos = $fileSize - $endPos;
                $endPos = $fileSize - 1;
            } elseif ('' === $endPos) {
                $endPos = $fileSize - 1;
            }

            $startPos = max($startPos, 0);
            $endPos = min($endPos, $fileSize - 1);

            $length = $endPos - $startPos + 1;

            if ($length < 0) {
                return false;
            }

            $contentLength = $length;
            $isPartial = true;
        }
    }

    // send headers
    if ($isPartial) {
        header('HTTP/1.1 206 Partial Content');
        header("Content-Range: bytes {$startPos}-{$endPos}/{$fileSize}");
    } else {
        header('HTTP/1.1 200 OK');
        $startPos = 0;
        $endPos = $contentLength - 1;
    }

    header('Pragma: cache');
    header('Cache-Control: public, must-revalidate, max-age=0');
    header('Accept-Ranges: bytes');
    header('Content-type: '.$contentType);
    header('Content-Length: '.$contentLength);

    if ($forceDownload) {
        header('Content-Disposition: attachment; filename="'.rawurlencode($fancyName).'"');
    }

    header('Content-Transfer-Encoding: binary');

    $bufferSize = 2048;

    if (0 !== $speedLimit) {
        $packetTime = floor($bufferSize * 1000000 / $speedLimit);
    }

    $bytesSent = 0;
    $fp = fopen($filePath, 'r');
    fseek($fp, $startPos);
    while ($bytesSent < $contentLength && !feof($fp) && 0 === connection_status()) {
        if (0 !== $speedLimit) {
            [$usec, $sec] = explode(' ', microtime());
            $outputTimeStart = ((float) $usec + (float) $sec);
        }

        $readBufferSize = min($contentLength - $bytesSent, $bufferSize);
        $buffer = fread($fp, $readBufferSize);

        echo $buffer;

        ob_flush();
        flush();

        $bytesSent += $readBufferSize;

        if (0 !== $speedLimit) {
            [$usec, $sec] = explode(' ', microtime());
            $outputTimeEnd = ((float) $usec + (float) $sec);

            $useTime = ($outputTimeEnd - $outputTimeStart) * 1000000;
            $sleepTime = round($packetTime - $useTime);
            if ($sleepTime > 0) {
                usleep((int)$sleepTime);
            }
        }
    }

    return true;
}

/**
 * 文件上传
 * @param string $uploadPath 上传目录
 * @param string $newFileName 上传后的文件名, 不包含扩展名
 * @param array $allowedTypes 允许上传的文件类型, 例如: ['jpg', 'png', 'gif'],为空表示受默认类型限制
 * @param int $maxSize 上传文件大小, 0表示受默认大小限制
 * @return array
 * @example code: 0 ok,
 * 1 上传文件大小超过了php.ini中upload_max_filesize选项限制的值,
 * 2 上传文件大小超过了HTML表单中MAX_FILE_SIZE选项指定的值,
 * 3 文件只有部分被上传,
 * 4 没有文件被上传,
 * 6 找不到临时文件夹,
 * 7 文件写入失败,
 * 8 上传文件被PHP扩展程序中断,
 * 12 未知错误
 * -1 上传目录不存在或不可写,
 * -2 上传文件类型不允许,
 * -3 上传文件大小超过限制,
 * -9 上传文件移动失败
 */
function upload(string $uploadPath, string $newFileName = '', array $allowedTypes = [], int $maxSize = 0): array
{
    // 文件上传目录是否可写
    $result = ['code' => 0, 'msg' => 'ok'];
    if (!is_writable($uploadPath)) {
        // 上传目录不存在或不可写 code: -1
        return ['code' => -1, 'msg' => 'The file upload directory cannot be written'];
    }

    // 默认允许上传的文件类型
    $defaultAllowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'xls', 'xlsx', 'ppt', 'pptx', 'md'];

    // 默认允许上传的文件大小，单位为字节
    $defaultMaxSize = 1024 * 1024;

    // 检查上传文件是否存在错误
    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        // 上传文件出错
        switch ($_FILES['file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
                // 上传文件大小超过了php.ini中upload_max_filesize选项限制的值 code: 1
                $result['code'] = 1;
                $result['msg'] = 'The uploaded file exceeds the upload_max_filesize directive in php.ini.';

                break;
            case UPLOAD_ERR_FORM_SIZE:
                // 上传文件大小超过了HTML表单中MAX_FILE_SIZE选项指定的值 code: 2
                $result['code'] = 2;
                $result['msg'] = 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.';

                break;
            case UPLOAD_ERR_PARTIAL:
                // 文件只有部分被上传 code: 3
                $result['code'] = 3;
                $result['msg'] = 'The uploaded file was only partially uploaded.';

                break;
            case UPLOAD_ERR_NO_FILE:
                // 没有文件被上传 code: 4
                $result['code'] = 4;
                $result['msg'] = 'No file was uploaded.';

                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                // 找不到临时文件夹 code: 6
                $result['code'] = 6;
                $result['msg'] = 'Missing a temporary folder. Introduced in PHP 4.3.10 and PHP 5.0.3.';

                break;
            case UPLOAD_ERR_CANT_WRITE:
                // 文件写入失败 code: 7
                $result['code'] = 7;
                $result['msg'] = 'Failed to write file to disk. Introduced in PHP 5.1.0.';

                break;
            case UPLOAD_ERR_EXTENSION:
                // 上传文件被PHP扩展程序中断 code: 8
                $result['code'] = 8;
                $result['msg'] = 'File upload stopped by extension. Introduced in PHP 5.2.0.';

                break;
            default:
                // 未知错误 code: 12
                $result['code'] = 12;
                $result['msg'] = 'Unknown error';

                break;
        }

        return $result;
    }

    // 检查上传文件类型是否允许
    $fileType = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
    if ((!empty($allowedTypes) && !in_array($fileType, $allowedTypes)) || (empty($allowedTypes) && !in_array($fileType, $defaultAllowedTypes))) {
        return ['code' => -2, 'msg' => 'The file type is not allowed'];
    }

    // 检查上传文件大小是否超过限制
    if (($maxSize && $_FILES['file']['size'] > $maxSize) || (!$maxSize && $_FILES['file']['size'] > $defaultMaxSize)) {
        return ['code' => -3, 'msg' => 'The file size exceeds the limit'];
    }

    // 生成新的文件名
    $newFileName = $newFileName === '' ?  uniqid() . '.' . $fileType : $newFileName . '.' . $fileType;

    // 移动上传文件到指定目录
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploadPath . $newFileName)) {
        return ['code' => -9, 'msg' => 'The file upload failed'];
    }
    return $result;
}
