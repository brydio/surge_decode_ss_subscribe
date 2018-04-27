<?php
/**
 * 解析SS/SSR并结合指定配置文件生成新的配置文件
 *  ！修改 $password为加密，留空默认不加密
 *  ！修改 $subscribeURL为自己的订阅url
 *  ！修改同目录custom目录文件自定义规则
 *  @author Brydio 2018-04-27
 */
header("Content-type: text/plain; charset=utf-8");
$password = "";
$subscribeURL = "";
$targetURL = "https://raw.githubusercontent.com/lhie1/Rules/master/Surge.conf";
$module = "http://omgib13x8.bkt.clouddn.com/SSEncrypt.module";


if (@$_REQUEST["p"] != $password) {
    echo '0';
    return;
}

function loadURL($url) {
    $ch = curl_init();
    $timeout = 5;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $contents = curl_exec($ch);
    curl_close($ch);
    return $contents;
}

$contents = loadURL($subscribeURL);
$targetContent = loadURL($targetURL);

$nodeLinkArr = array_filter(explode("\n", base64_decode($contents)));

class Node {
    public $isSSR = false;
    public $host = "";
    public $port = "";
    public $type = "";
    public $password = "";
    public $remarks = "";

    public function toSurgeConfigString() {
        return "$this->remarks = custom,$this->host,$this->port,$this->type,$this->password";
    }
}

function decodeBase64($str) {
    $str = str_replace("_", "/", $str);
    $str = str_replace("-", "+", $str);
    return base64_decode($str);
}

$list = array();
$proxyContent = '';
$proxyGroupContent = "[Proxy Group]\r\nPROXY = select";
foreach ($nodeLinkArr as $link) {
    $isSSR = strpos(strtolower($link), "ssr://") === 0;
    $link = decodeBase64(substr($link, strpos($link, "//") + 2));
    $split = array_filter(explode("/", $link));
    $plainA = array_filter(explode(":", $split[0]));
    $remarks = substr($split[1], strpos($split[1], "=") + 1);
    $remarks = substr($remarks, 0, strpos($remarks, "&group="));
    $remarks = decodeBase64($remarks);
    //$plainB = array_filter(explode(substr($split[1], 1), "&"));
    $node = new Node();
    $node->host = $plainA[0];
    $node->port = $plainA[1];
    $node->type = $plainA[3];
    $node->password = decodeBase64($plainA[5]);
    $node->remarks = $remarks;
    //var_dump($node);
    //echo $link;
    //echo "<br>";
    $proxyContent .= "\r\n" . $node->toSurgeConfigString() . ",$module\r\n";
    $proxyGroupContent .= ',' . $node->remarks;
}

$beforeContent = substr($targetContent, 0, strpos($targetContent, "[Proxy]") + 8);
$afterContent = substr($targetContent, strpos($targetContent, "[Rule]"));

$beforeContent = preg_replace("/external-controller-access.*/", "external-controller-access = zsf@0.0.0.0:6170", $beforeContent);
$beforeContent = preg_replace("/dns-server.*/", "dns-server = 114.114.114.114,114.114.115.115,8.8.8.8", $beforeContent);

$customFile = fopen("custom", "r");
$customContent = fread($customFile, filesize("custom"));
fclose($customFile);
$afterContent = str_replace("# Custom", $customContent, $afterContent);

print_r($beforeContent);
print_r($proxyContent);
print_r("\r\n");
print_r($proxyGroupContent);
print_r("\r\n\r\n");
print_r($afterContent);
?>