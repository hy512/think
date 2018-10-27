<?php

namespace Common\Model;


class WeChatModel 
{
    // 应用标识符与应用密钥
    // 用来组合访问链接和向微信请求用户信息
    private $appid;
    private $secret;

    private $url = [
        "authorize" => "https://open.weixin.qq.com/connect/oauth2/authorize",
        "access_token" => "https://api.weixin.qq.com/sns/oauth2/access_token",
        "refresh_token" => "https://api.weixin.qq.com/sns/oauth2/refresh_token",
        "auth" => "https://api.weixin.qq.com/sns/auth",
        "userinfo" => "https://api.weixin.qq.com/sns/userinfo",
    ];

    public function __construct($appid, $secret, $data = [])
    {
        // parent::__construct($data);

        $this->appid = $appid;
        $this->secret = $secret;
    }

    // 用来组合一条用户访问链接。
    // $url 接收请求的服务器地址, 当用户访问此方法生成的地址时，会定向到 $url 表示的地址，并带有 code 和 state 两个参数
    //      如 https://www.baidu.com/more/index.html
    // $state 自定义内容
    // 也可以只传递一个参数，带字符串 key 的数组，包含了所有参数和值。
    public function generateVisitLink($url, $state = "")
    {
        $defArgs = [
            "appid" => $this->appid,
            "scope" => "snsapi_base",
            "state" => $state,
            "response_type" => "code",
            "redirect_uri" => $url,
        ];

        if (is_array($url)) {
            return "" . $this->url['authorize'] . "?"
            . $this->arrayToQueryStr(array_merge($defArgs, $url)) . "#wechat_redirect";
        } else if (is_string($url)) {
            return "" . $this->url['authorize'] . "?"
            . $this->arrayToQueryStr($defArgs) . "#wechat_redirect";
        }
        return false;
    }

    // 在 redirect_uri 定义的服务器收到请求后，
    // 将 code 请求参数作为此方法的参数，将返回请求微信 access_token 的结果。
    // 方法的返回值为数组
    /*  正确请求结果示例
    {
    "access_token":"ACCESS_TOKEN",
    "expires_in":7200,
    "refresh_token":"REFRESH_TOKEN",
    "openid":"OPENID",
    "scope":"SCOPE"
    }
     *//* 错误请求结果
    {"errcode":40029,"errmsg":"invalid code"} */
    // 每个 code 只能被使用一次
    public function getAccessToken($code)
    {
        $args = [
            "code" => $code,
            "appid" => $this->appid,
            "secret" => $this->secret,
            "grant_type" => "authorization_code",
        ];
        $url = "" . $this->url["access_token"] . "?" . $this->arrayToQueryStr($args);
        return json_decode($this->execRequest($url), true);
    }

    // 获取用户的信息, $lang 为地区语言，默认为中文。
    public function getUserInfo($access_token, $openid, $lang = "zh_CN")
    {
        $args = [
            "access_token" => $access_token,
            "openid" => $openid,
            "lang" => $lang,
        ];
        $url = "" . $this->url["userinfo"] . "?" . $this->arrayToQueryStr($args);
        return json_decode($this->execRequest($url), true);
    }

    // 刷新 access_token 的有效期
    /* 返回示例
    { "access_token":"ACCESS_TOKEN",
    "expires_in":7200,
    "refresh_token":"REFRESH_TOKEN",
    "openid":"OPENID",
    "scope":"SCOPE" }
    {"errcode":40029,"errmsg":"invalid code"}
     */
    public function refreshAccessToken($refresh_token)
    {
        $args = [
            "appid" => $this->appid,
            "grant_type" => "refresh_token",
            "refresh_token" => $refresh_token,
        ];
        $url = "" . $this->url["refresh_token"] . "?" . $this->arrayToQueryStr($args);
        return json_decode($this->execRequest($url), true);
    }

    // 判断 access_token 是否有效
    // 方法的返回值为数组
    /* 请求示例:
    { "errcode":0,"errmsg":"ok"} 有效
    { "errcode":40003,"errmsg":"invalid openid"} 无效
     */
    public function verifyAccessToken($access_token, $openid)
    {
        $args = [
            "access_token" => $access_token,
            "openid" => $openid,
        ];
        $url = "" . $this->url["auth"] . "?" . $this->arrayToQueryStr($args);
        return json_decode($this->execRequest($url), true);
    }

    private function execRequest($url)
    {
        $ch = curl_init($url);
        if (!$ch) {
            return false;
        }
        curl_setopt_array($ch, array(
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
        ));
        $resStr = curl_exec($ch);
        curl_close($ch);

        return $resStr;
    }

    // 将键值对数组转换为查询字符串
    // [ "a" => "b", "c" => "d" ] 转换为 a=b&c=d
    private function arrayToQueryStr($ary)
    {
        if (!is_array($ary)) {
            return false;
        }
        $str = "";
        foreach ($ary as $k => $v) {
            $str = $str . urlencode($k) . "=" . urlencode($v) . "&";
        }
        return substr($str, 0, strlen($str) - 1);
    }
}
