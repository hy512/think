<?php
namespace User\Controller;

use Common\Controller\HomebaseController;
use Common\Model\WeChatModel;
use Common\Model\fdtaskModel;
use Common\Model\WxautoModel;

class WeChatController extends HomebaseController
{
    private $task;
    private $wechat;

    public function __construct()
    {
        parent::__construct();
        $task = new fdtaskModel();
        // $wechat =
        $this->wechat = new WeChatModel("adb", "dfd");
    }

    // 前端下单页面
    public function index()
    {
        $this->check_login();
		$this->display("index");
    }

    // 下单提交
    public function checkout()
    {
        $this->check_login();
		$user = sp_get_current_user();
		$error = ["status"=> 0, "messages" => []];
		if (!isset($_POST["name"], $_POST["tpurl"], $_POST["num"], $_POST["jianjie"])) {
			array_push($error["messages"], "请填写完整信息 !");
		}
		if (!strlen($_POST["name"])){
			array_push($error["messages"], "活动名称未输入 !");
		} 
		if ( substr($_POST["tpurl"], 0, 4) !== "http") {
			array_push($error["messages"], "下单地址格式错误，应为 http 开头 !");
		}
		if (!(intVal($_POST["num"]) == $_POST["num"] && $_POST["num"] > 0 ))  {
			array_push($error["messages"], "下单数量应为大于 0 的整数!");
		}
		
		if (count($error["messages"]) > 0) {
			exit(json_encode($error));
		}
		
		// 生成订单
		D("fdtask")->add([
			"name"=>$_POST["name"], 
			"typeid"=>1810,
			"tpurl"=>$_POST["tpurl"],
			"xznum"=> 0,
			"num" => $_POST["num"],
			"n_num"=>0,
			"no_num"=>0,
			"jianjie" => $_POST["jianjie"],
			"fattime"=>time(),
			"startime"=>time(),
			"overtime"=>time(),
		]);

		exit(json_encode(["status"=> 1, "message"=>"下单成功 !"]));
		// var_dump(D("fdtask")->select());
		// var_dump(new \Think\Model("fdtask", "cmf_").select());
		// new \Think\Model("fdtask") ;
		// var_dump(M("fdtask"));
		// new \Think\Model();
    }

    // 生成微信开放平台访问链接
    public function weChatUrl()
    {
        $this->check_login();
        $userId = sp_get_current_userid();
        $host = $_SERVER["HTTP_HOST"];
        $path = str_replace("\\", "/", substr(__NAMESPACE__, 0, strpos(__NAMESPACE__, "Controller")));
        $url = $this->wechat->generateVisitLink("http://{$host}/{$path}WeChat/getUrl", $userId);
        exit(json_encode(["url" => $url, "status" => 1]));
    }

    // 开放平台定向接口
    public function getUrl()
    {

    }
}
