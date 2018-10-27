<?php
namespace Common\Model;

use Common\Model\CommonModel;
use Common\Common\tool;
use Common\Common\redis;
use Think\Model;

class fdtaskModel extends CommonModel
{

    // 在线机器总数约
    private $line = 400;

    private $serverIpTwo = '';

    private $arroutOrdes = array();

    protected $_validate = array(
        array(
            'name',
            'require',
            '活动名不能为空！',
            1,
            'regex',
            CommonModel::MODEL_INSERT
        ),
        array(
            'tpurl',
            'require',
            '投票地址不能为空！',
            1,
            'regex',
            CommonModel::MODEL_INSERT
        ),
        array(
            'danjia',
            'require',
            '单价不能为空！',
            1,
            'regex',
            CommonModel::MODEL_INSERT
        )
    );

    // array(
    // 'imgfile',
    // 'require',
    // '示例图不能为空！',
    // 1,
    // 'regex',
    // CommonModel::MODEL_INSERT
    // )
    
    // 价格
    public function getDj()
    {
        return '           <option value="0.09">0.09</option>
          <option value="0.10">0.10</option>
            
              <option value="0.13">0.13</option>
              <option value="0.14">0.14</option>
                                     <option value="0.15">0.15</option>
                                     <option value="0.18">0.18</option>
                                     <option value="0.20">0.20</option>
                                    <option value="0.22">0.22</option>
                                    <option value="0.25">0.25</option>
                                     <option value="0.28">0.28</option>
                                     <option value="0.30">0.30</option>
                                    <option value="0.34">0.34</option>
                                    <option value="0.44">0.44</option>
            
                                    <option value="0.7<">0.7</option>
                                    <option value="0.85">0.85</option>
                                    <option value="1.2">1.2</option>
                                    <option value="1.3">1.3</option>
                                    <option value="1.65">1.65</option>
                                    <option value="2">2</option>
                                    <option value="2.4">2.4</option>
                                    <option value="3.5">3.5</option>
                                    <option value="4.3">4.3</option>';
    }

    protected $_auto = array(
        array(
            'create_time',
            'mGetDate',
            CommonModel::MODEL_INSERT,
            'callback'
        )
    );

    // 用于获取时间，格式为2012-02-03 12:12:12,注意,方法不能为private
    function mGetDate()
    {
        return date('Y-m-d H:i:s');
    }

    protected function _before_write(&$data)
    {
        parent::_before_write($data);
        
        if (! empty($data['user_pass']) && strlen($data['user_pass']) < 25) {
            $data['user_pass'] = sp_password($data['user_pass']);
        }
    }

    // 发布任务预处理
    public function setpost()
    {
        if (! empty($_POST['xs_namemp'])) {
            $_POST['xs_name'] = $_POST['xs_namemp'];
        }
        if (empty($_POST['fatime'])) {
            $_POST['fatime'] = date('Y-m-d H:i:s');
        }
        $_POST['task_sum'] = $_POST['num'] * $_POST['danjia'];
        $_POST['id'] = get_current_userid();
        return $_POST['task_sum'];
    }

    // 自动处理过期任务 这里改成投手点击后判断是否过期而中止
    public function setTimeOut()
    {
        // $id= get_current_userid();id='.$id.' and
        // if (empty(S('setTimeOut'))){
        // S('setTimeOut','true',5);
        // $map['task_buff'] = 3;
        // $map['endtime'] = date('Y-m-d H:i:s'); // 重置结束时间来判断什么时候结算
        // $this->where('task_buff=1 and now()>endtime and endtime<>0')->setField($map);
        // }
    }

    // 自动处理隐葳票数。因为可能投手平台计算不准
    public function setRunOut()
    {
        return false;
        // $id= get_current_userid();id='.$id.' and
        if (empty(S('setRunOut'))) {
            S('setRunOut', 'true', 5);
            $map['task_buff'] = 1;
            $map['_string'] = '(n_num+o_num)>=num';
            // $map['user_id'] = get_current_userid();
            $arr = $this->where($map)->select();
            if ($arr) {
                $ordesM = new ordersModel();
                foreach ($arr as $v) {
                    $mapx['task_id'] = $v['task_id'];
                    $mapx['buff'] = 1;
                    $int = $ordesM->where($mapx)->count();
                    $mapnew['task_id'] = $v['task_id'];
                    if (SLock($v['task_id'] . 'TASK_ID', 0, 5, 100)) { // 标识 ,超时,锁时,读时(毫秒)
                        $this->where($mapnew)->setField('o_num', $int);
                        SUnlock($v['task_id'] . 'TASK_ID');
                    }
                }
            }
        }
    }

    // 自动处理隐葳票数。因为可能投手平台计算不准
    public function setRunOutAll()
    {
        return 0;
        // $id= get_current_userid();id='.$id.' and
        if (empty(S('setRunOutAll'))) {
            S('setRunOutAll', 'true', 5);
            $map['task_buff'] = 1;
            $map['_string'] = '(n_num+o_num)>=num'; // 隐数加完数=总数。去计算是否订单过期了没有去除隐数。重点是最后几票可能会卡隐票所以这里做下处理
            $arr = $this->where($map)->select();
            if ($arr) {
                $ordesM = new ordersModel();
                foreach ($arr as $v) {
                    $mapx['task_id'] = $v['task_id'];
                    $mapx['buff'] = 1;
                    $int = $ordesM->where($mapx)->count();
                    $mapnew['task_id'] = $v['task_id'];
                    if (SLock($v['task_id'] . 'TASK_ID', 0, 5, 100)) { // 标识 ,超时,锁时,读时(毫秒)
                        $this->where($mapnew)->setField('o_num', $int);
                        SUnlock($v['task_id'] . 'TASK_ID');
                    }
                }
            }
        }
    }

    // 自动处理执行成功的任务
    public function setTimeOver()
    {
        // $id= get_current_userid();id='.$id.' and
        // if (empty(S('setTimeOver'))){
        // S('setTimeOver','true',5);
        // $map['task_buff'] = 4;
        // $this->where('task_buff =1 and num=n_num')->setField($map);
        // }
    }

    // 自动结算所有用户的中止任务
    public function setEndOut()
    {
        return false;
    }

    // 改用定时调度实现
    public function EndOutTask()
    {
        // 超过2天的自动中止
        if (empty(S('setEndOutEnd'))) {
            S('setEndOutEnd', 'true_one', 60);
            $where = array();
            $where['task_buff'] = 1;
            $logtime = date('Y-m-d H:i:s', time() - 48 * 60 * 60); // and id=' . get_current_userid()
            $where['_string'] = " `startime` < '$logtime'";
            $mapx['endtime'] = date('Y-m-d H:i:s');
            $mapx['task_buff'] = 2;
            $mapx['beizhu'] = '超过二天的单子自动停止';
            $arrData = $this->where($where)->setField($mapx);
            foreach ($arrData as $v) {
                $where = array();
                $where['task_id'] = $v['task_id'];
                $this->where($where)->setField($mapx);
            }
        }
        $mapx = array();
        $mapx['task_buff'] = 4; // 查出来
        $verEndArr = $this->where('task_buff =1 and (num=n_num or num<n_num)')->select();
        if (! empty($verEndArr)) {
            foreach ($verEndArr as $v) {
                $mapx['endtime'] = date('Y-m-d H:i:s');
                $where = array();
                $this->where('task_buff =1 and (num=n_num or num<n_num) and task_id=' . $v['task_id'])->setField($mapx);
            }
        }
        // 查找任务到期或中止的且过了十分钟
        if (empty(S('setEndOut'))) {
            S('setEndOut', 'true', 5);
            $logtime = date('Y-m-d H:i:s', time() - 10 * 60); // and id=' . get_current_userid()
            $taskData = $this->where('(task_buff=3 or task_buff=2) and (other_buff=2 or other_buff=0 or other_buff=5) and zztask_buff<>7 and \'' . $logtime . '\'>endtime')->select();
            // 过期的且时间达到的数据进行处理
            
            foreach ($taskData as $value) {
                // 更新任务状态
                $nmap['task_buff'] = 5;
                $where = array();
                $where['task_id'] = $value['task_id'];
                $where['_string'] = 'task_buff=3 or task_buff=2';
                $re = $this->where($where)->setField($nmap);
                if ($re) {
                    // 如果票数为负，，那就是零票
                    if ($value['n_num'] < 0) {
                        $value['n_num'] = 0;
                    }
                    // 退回金额给发布者
                    $backSum = ($value['num'] - $value['n_num']) * $value['danjia'];
                    if ($backSum < 0) {
                        return 0;
                    }
                    $str = '';
                    // 打折单子。。按打折款退
                    if ($value['dlsk'] == 1) {
                        $backSum *= 0.8;
                        $str = '凌晨单子统一打八折';
                    }
                    $userM = new UsersModel();
                    $userM->where('id=' . $value['id'])->setInc('coin', $backSum);
                    // 加入事件
                    $event = new EventModel();
                    if ($value['task_buff'] == 2) {
                        $data = array(
                            $value['id'],
                            1,
                            $backSum,
                            0,
                            0,
                            '编号ID：' . $value['task_id'] . '用户中止退票' . $str
                        );
                        $event->eventIo(2, $data);
                    } else {
                        $data = array(
                            $value['id'],
                            1,
                            $backSum,
                            0,
                            0,
                            '编号ID：' . $value['task_id'] . '活动过期退票' . $str
                        );
                        $event->eventIo(2, $data);
                    }
                }
            }
        }
    }

    // 设置某字段值
    public function setTabVal($where, $tab, $val)
    {
        $id = get_current_userid();
        $this->where($where . ' and id=' . $id)->setField($tab, $val);
    }

    // 随机取一个任务
    public function getonetask($userID, $province, $web = 0, $int = 88)
    {
        // 设置登陆用户过期活动
        $this->setTimeOut();
        $this->setTimeOver(); // 处理执行成功的任务
                              // $map['task_buff'] = 1;
                              // // 取用户省
                              // $map['province'] = array(
                              // array(
                              // 'like',
                              // "%$province%"
                              // ),
                              // '',
                              // 'or'
                              // );
                              // $id = $userID;
                              // $map['_string'] = "task_id NOT IN ( SELECT task_id FROM cmf_orders where user_id=$id)";
        $sql = '';
        if ($int == 3) {
            $strtype = "(`typeid`=2 or `typeid`=3)";
        } else {
            $strtype = "`typeid`=$int";
        }
        if ($web) {
            if ($int != 88) {
                
                $sql = "SELECT * FROM `cmf_fdtask` WHERE (web=0 or web=$web) and $strtype and `display`=0 AND `task_buff` = 1 AND `num` >o_num+n_num AND ( `province` LIKE '%$province%' OR `province` = '' ) AND (zz_id=0 or zz_id NOT IN ( SELECT zz_id FROM cmf_orders where user_id=$userID)) AND ( task_id NOT IN ( SELECT task_id FROM cmf_orders where user_id=$userID) ) ORDER BY `fatime` DESC";
            } else {
                $sql = "SELECT * FROM `cmf_fdtask` WHERE (web=0 or web=$web) and `display`=0 AND `task_buff` = 1 AND `num` >o_num+n_num AND ( `province` LIKE '%$province%' OR `province` = '' ) AND (zz_id=0 or zz_id NOT IN ( SELECT zz_id FROM cmf_orders where user_id=$userID)) AND ( task_id NOT IN ( SELECT task_id FROM cmf_orders where user_id=$userID) ) ORDER BY `fatime` DESC";
            }
        } else {
            if ($int != 88) {
                $sql = "SELECT * FROM `cmf_fdtask` WHERE web=0 and $strtype and `display`=0 AND `task_buff` = 1 AND `num` >o_num+n_num AND ( `province` LIKE '%$province%' OR `province` = '' ) AND (zz_id=0 or zz_id NOT IN ( SELECT zz_id FROM cmf_orders where user_id=$userID)) AND ( task_id NOT IN ( SELECT task_id FROM cmf_orders where user_id=$userID) ) ORDER BY `fatime` DESC";
            } else {
                $sql = "SELECT * FROM `cmf_fdtask` WHERE web=0 and `display`=0 AND `task_buff` = 1 AND `num` >o_num+n_num AND ( `province` LIKE '%$province%' OR `province` = '' ) AND (zz_id=0 or zz_id NOT IN ( SELECT zz_id FROM cmf_orders where user_id=$userID)) AND ( task_id NOT IN ( SELECT task_id FROM cmf_orders where user_id=$userID) ) ORDER BY `fatime` DESC";
            }
        }
        $arr = $this->query($sql);
        if ($arr) {
            // 判断是否放弃的任务
            $taskidArr = S(task . $userID);
            // tool::writappstr('error', var_export($taskidArr,true)."\r\n");
            foreach ($arr as $key => $taskData) {
                // 判断是否限速取xzno 完成票数，时间/5 看达标没。没有就通过
                if (! gettimeout($taskData['startime'], $taskData['xznum'] + $taskData['o_num'], $taskData['xzno']) || $taskData['xzno'] == 0) {
                    // return $taskData;
                } else {
                    unset($arr[$key]);
                }
                // 查找
                if (S('mptask' . $userID) == 'true') {
                    if (strpos($taskData['tpurl'], '://mp.weixin.qq.com/') !== false) {
                        unset($arr[$key]);
                    }
                }
                if (in_array($taskData['task_id'], $taskidArr)) {
                    unset($arr[$key]);
                }
            }
            
            if (empty($arr)) {
                return false;
            }
            
            return $arr[key($arr)];
        }
        return $arr;
    }

    // 计算时间与票数发布时间，现在的票速。限速数
    function gettimeout($fatime, $nowNum, $xzno)
    {
        $xzint = (time() - strtotime($fatime)) / 60;
        if ($xzno > 0) {
            if ($nowNum > ($xzno * $xzint)) {
                return true;
            }
        }
        return false;
    }

    function getAlltask($all_int)
    {
        // 判断有多少任务。。进行每个任务限接取
        // $all_int = count($arr);
        if ($all_int < 8) {
            $tp_num = $this->line;
        } elseif ($all_int < 15) {
            $tp_num = (int) $this->line / 2;
        } elseif ($all_int < 21) {
            $tp_num = (int) $this->line / 4;
        } elseif ($all_int < 43) {
            $tp_num = (int) $this->line / 8;
        } elseif ($all_int < 63) {
            $tp_num = (int) $this->line / 12;
        } elseif ($all_int < 83) {
            $tp_num = (int) $this->line / 16;
        } elseif ($all_int < 103) {
            $tp_num = (int) $this->line / 20;
        } else {
            $tp_num = (int) $this->line / 24;
        }
        return $tp_num;
    }

    function getTaslSal($datax, $table_name, $isTure)
    {
        $userID = $datax['id'];
        $province = $datax['city'];
        $wx_type = $datax['wx_type'];
        if (empty($userID)) {
            return false;
        }
        $strEx = '';
        if ($wx_type == 1) {
            $strEx = ' AND web=1 ';
        }
        $mpture = S('mpautotask' . $userID);
        if ($mpture == 'trueEx') { // 排除官方MP任务AND ( `city`='$province' OR `city` = '' )ORDER BY typeid DESC,`startime` DESC
            
            $sql = "SELECT * FROM `cmf_fdtask` WHERE `task_buff` = 1  AND  typeid>2222 AND `display`=1 AND other_buff<>1 $strEx AND `num` >o_num+n_num AND `startime`<now() ORDER BY `startime` DESC LIMIT 100 ";
        } else {
            
            $sql = "SELECT * FROM `cmf_fdtask` WHERE `task_buff` = 1  AND  typeid>2221 AND `display`=1 AND other_buff<>1 $strEx AND  `num` >o_num+n_num AND `startime`<now() ORDER BY `startime` DESC LIMIT 100 "; // `typeid` ASC,
        }
        $webM = new webcofModel();
        $web = $webM->getweb();
        $arr = S($web . $mpture . $wx_type . 'autoTask_taskData');
        if (! is_array($arr)) { // 不存在了处理下一般要重置
            $arr = $this->query($sql);
            $isS = false;
            S($web . $mpture . $wx_type . 'autoTask_taskData', $arr, 1);
        }
        return $arr;
    }

    public function RunOutTimeNum_out($num = 1, $key = 'rumTimeOut', $timeOut = 5)
    {
        $key .= 'task';
        $redis = redis::getRedis();
        $check = $redis->exists($key);
        if ($check) {
            $count = $redis->get($key);
            if ($count > $num) {
                $outtime = $redis->pttl($key);
                if ($outtime == - 1) {
                    $redis->del($key);
                }
                return true;
            }
        }
        return false;
    }

    public function RunOutTimeNum($num = 1, $key = 'rumTimeOut', $timeOut = 5)
    {
        $key .= 'task';
        $redis = redis::getRedis();
        
        $check = $redis->exists($key);
        if ($check) {
            $count = $redis->incr($key);
            // $count = $redis->get($key);
            if ($count > $num) {
                $outtime = $redis->pttl($key);
                if ($outtime == - 1) {
                    $redis->del($key);
                }
                return true;
            }
        } else {
            $count = $redis->incr($key);
            // 限制时间
            if ($count == 1) {
                $redis->expire($key, $timeOut);
            }
        }
        
        return false;
    }

    // 随机取一个任务
    public function getonetaskWxAuto($datax, $table_name, $isTure = true)
    {
        $wxautuM = new WxautoModel();
        $arr = $this->getTaslSal($datax, $table_name, $isTure);
        
        $taskDataTMp = $arr[0];
        unset($arr[0]);
        // 计算优质等级就是比率
        foreach ($arr as $key => $value) {
            $arr[$key]['key'] = $value['no_num'] / $value['n_num'];
        }
        // 排序。。让没有做任务的优先
        foreach ($arr as $key => $value) {
            $rating[$key] = $value['key'];
            $ratingEx[$key] = $value['o_num'];
        }
        array_multisort($rating, SORT_ASC, $ratingEx, SORT_ASC, $arr);
        
        array_unshift($arr, $taskDataTMp); // 新单总是最快的,将$taskDataTMp插入$arr数组中
        
        if ($arr) {
            $all_int = count($arr);
            $tp_num = $this->getAlltask($all_int);
            
            $timeOut = 2; // 接取超时。。任务越少。越时越大
            
            foreach ($arr as $key => $taskData) {
                
                // 判断哪个平台哪个帐号跑阅读
//                 if ($wxautuM->webNum == 6) {
//                     if ($taskData['typeid'] == 8890 || $taskData['typeid'] == 8891) {
//                         if ($datax['user_sx'] != 505421) {
//                             continue;
//                         }
//                     }
//                 }
                // 如果这任务接取过。。就跳过
                $tmp_is = S($datax['id'] . 'autoTask' . $taskData['task_id']);
                if ($tmp_is == 'is_true') {
                    continue;
                }
                
                // 有些要跳过,先判断是不是限制活动
                $data_tiao = S('is_tiao_task' . $taskData['task_id']);
                if (isset($data_tiao['task_id']) && $data_tiao['task_id'] == $taskData['task_id']) {
                    if (S('is_tiao_task' . $taskData['task_id'] . $data_tiao['key']) == S('is_tiao_id' . $datax['id'] . $data_tiao['key'])) {
                        
                        continue;
                    }
                }
                // 有限速的加上判断缓存判断
                if ($taskData['xzno'] < 60 && $taskData['xzno'] != 0) {
                    // 判断是不是接取数太高了
                    if ($this->RunOutTimeNum($taskData['xzno'], $taskData['task_id'], 20)) {
                        continue; // 太高跳过
                    }
                } else {
                    // 如果还有不足多少票。接取减速
                    $tmpIntx = $taskData['num'] - $taskData['n_num'];
                    if ($tmpIntx < 50) { // 还有50票以内
                        if ($tmpIntx > 25) { // 超过25票
                            if ($this->RunOutTimeNum(20, $taskData['task_id'], 5)) { //
                                continue;
                            }
                        } else {
                            if ($this->RunOutTimeNum(10, $taskData['task_id'], 10)) {
                                continue;
                            }
                        }
                    } else {
                        if ($tmpIntx < 100) { // 少于100票
                            if ($this->RunOutTimeNum(30, $taskData['task_id'], 5)) {
                                // 太高跳过
                                continue;
                            }
                        } else { // 超过一百票
                            if ($tmpIntx < 200) { // 少于100票
                                if ($this->RunOutTimeNum(50, $taskData['task_id'], 5)) {
                                    continue; // 太高跳过
                                }
                            } else {
                                if ($tmpIntx > ($tp_num * 3)) {
                                    $tmpIntx = $tp_num * 3;
                                } else {
                                    if ($tmpIntx > ($tp_num * 2)) {
                                        $tmpIntx = $tp_num * 2;
                                    }
                                }
                                if ($this->RunOutTimeNum($tmpIntx / 2, $taskData['task_id'], 2)) {
                                    continue; // 太高跳过
                                }
                            }
                        }
                    }
                    // 接取数只能是还需求票数的一半才行.要不然太快了
                    if ($tmpIntx / 2 < $taskData['o_num']) {
                        // 太高跳过
                        continue;
                    }
                }
                // 判断是否限速取xzno 完成票数，时间/5 看达标没。没有就通过
                if ($this->gettimeout($taskData['startime'], $taskData['xznum'] + $taskData['o_num'], $taskData['xzno']) && $taskData['xzno'] != 0) {
                    continue;
                }
                
                // 判断是否做过
                if ($taskData['zz_id'] > 1) {
                    $sql = 'SELECT zz_id,task_id FROM cmf_' . $table_name . ' where user_id=' . $datax['id'] . ' and  (zz_id=' . $taskData['zz_id'] . ' or task_id=' . $taskData['task_id'] . ')';
                    $re = $this->query($sql);
                    if ($re) {
                        S($datax['id'] . 'autoTask' . $taskData['task_id'], 'is_true', 24 * 60 * 60);
                        continue;
                    }
                } else {
                    $sql = 'SELECT zz_id,task_id FROM cmf_' . $table_name . ' where user_id=' . $datax['id'] . ' and   task_id=' . $taskData['task_id'];
                    $re = $this->query($sql);
                    if ($re) {
                        S($datax['id'] . 'autoTask' . $taskData['task_id'], 'is_true', 24 * 60 * 60);
                        continue;
                    }
                }
                
                // 这里就可以对这个任务下单了
                $dataGTask = $wxautuM->gettask($taskData['task_id'], $datax['id'], 1, $table_name, $timeOut);
                if ($dataGTask['status'] == 1) {
                    S($datax['id'] . 'autoTask' . $taskData['task_id'], 'is_true', 24 * 60 * 60);
                    $taskData['orders_id'] = $dataGTask['orders_id'];
                    S($datax['user_login'] . 'task', $taskData, 105);
                    return $taskData;
                }
            }
        }
        
        return false;
    }

    public function dygettask($wxordesM, $user , $type)
    {
        $wxordesMx = 'cmf_' . $wxordesM;
        $wxautuM = new WxautoModel();
        //获取zz_id
//         $wxorders = $this->query('select ' . $wxordesMx . '.zz_id from ' . $wxordesMx . ' where ' . $wxordesMx . '.user_id = ' . $user['id']);
//         foreach ($wxorders as $key){
//             $zzid[] .= $key['zz_id']; 
//         }
        if($type == 'douyin'){
            $sql = 'select * from cmf_fdtask where cmf_fdtask.zz_id not in (select ' . $wxordesMx . '.zz_id from ' . $wxordesMx . ' where ' . $wxordesMx . '.user_id = ' . $user['id'] . ') and cmf_fdtask.task_buff = 1 AND cmf_fdtask.typeid>1500 AND cmf_fdtask.typeid<1505 AND cmf_fdtask.display=1 ORDER BY startime DESC LIMIT 100 '; // M('fdtask')->where();
        }else if($type == 'weixin'){
            $sql = 'select * from cmf_fdtask where cmf_fdtask.zz_id not in (select ' . $wxordesMx . '.zz_id from ' . $wxordesMx . ' where ' . $wxordesMx . '.user_id = ' . $user['id'] . ') and cmf_fdtask.task_buff = 1 AND cmf_fdtask.typeid = 1212 AND cmf_fdtask.display=1 ORDER BY startime DESC LIMIT 100 ';//GROUP BY cmf_fdtask.zz_id
        }
        $model = new Model();
        $dytask = M('fdtask')->query($sql); // 找出了可取任务
        if (count($dytask) < 1) {
            $data['status'] = 0;
            $data['msg'] = '暂无可领取的任务';
            return $data;
        }
        $taskDataTMp = $dytask[0];
        unset($dytask[0]);
        // 计算优质等级就是比率
        foreach ($dytask as $key => $value) {
            $dytask[$key]['key'] = $value['no_num'] / $value['n_num'];
        }
        array_unshift($dytask, $taskDataTMp);
        if ($dytask) {
            $all_int = count($dytask);
            $tp_num = $this->getAlltask($all_int);
            $timeOut = 2; // 接取超时。。任务越少。越时越大
            foreach ($dytask as $key => $taskData) {
                
                // 如果这任务接取过。。就跳过
//                 S($user . 'autoTask' . $taskData['task_id'] , 'true');
                $tmp_is = S($user['id'] . 'autoTask' . $taskData['task_id']);
                if ($tmp_is == 'is_true') {
                    continue;
                }
                
                // 有限速的加上判断缓存判断
                if ($taskData['xzno'] < 60 && $taskData['xzno'] != 0) {
                    // 判断是不是接取数太高了
                    if ($this->RunOutTimeNum($taskData['xzno'], $taskData['task_id'], 20)) {
                        continue; // 太高跳过
                    }
                } else {
                    // 如果还有不足多少票。接取减速
                    $tmpIntx = $taskData['num'] - $taskData['n_num'];
                    if ($tmpIntx < 50) { // 还有50票以内
                        if ($tmpIntx > 25) { // 超过25票
                            if ($this->RunOutTimeNum(25, $taskData['task_id'], 5)) { //
                                continue;
                            }
                        } else {
                            if ($this->RunOutTimeNum(10, $taskData['task_id'], 10)) {
                                continue;
                            }
                        }
                    } else {
                        if ($tmpIntx < 100) { // 少于100票
                            if ($this->RunOutTimeNum(20, $taskData['task_id'], 5)) {
                                // 太高跳过
                                continue;
                            }
                        } else { // 超过一百票
                            if ($tmpIntx < 200) { // 少于200票
                                if ($this->RunOutTimeNum(50, $taskData['task_id'], 5)) {
                                    // 太高跳过
                                    continue;
                                }
                            } else {
                                if ($tmpIntx > ($tp_num * 3)) {
                                    $tmpIntx = $tp_num * 3;
                                } else {
                                    if ($tmpIntx > ($tp_num * 2)) {
                                        $tmpIntx = $tp_num * 2;
                                    }
                                }
                                if ($this->RunOutTimeNum($tmpIntx / 2, $taskData['task_id'], 2)) {
                                    // 太高跳过
                                    continue;
                                }
                            }
                        }
                    }
                    // 接取数只能是还需求票数的一半才行.要不然太快了
//                     if ($tmpIntx / 2 < $taskData['o_num']) {
//                         // 太高跳过
//                         continue;
//                     }
                }
                // 判断是否限速取xzno 完成票数，时间/5 看达标没。没有就通过
//                 if ($this->gettimeout($taskData['startime'], $taskData['xznum'] + $taskData['o_num'], $taskData['xzno']) && $taskData['xzno'] != 0) {
//                     continue;
//                 }
                $dataGTask = $wxautuM->dygettask($taskData['task_id'], $user['id'], 1, $wxordesM, $timeOut);
                if ($dataGTask['status'] == 1) {
                    S($user['id'] . 'autoTask' . $taskData['task_id'], 'is_true', 130);
                    $taskData['orders_id'] = $dataGTask['orders_id'];
//                     S($user['id'] . 'task', $taskData, 105);
                    $data['status'] = 1;
                    $data['task'] =$taskData;
                    return $data;
                }
                return $dataGTask;
            }
            return $data;
        }
        return false;
    }
}
// 排序。。MP先做。三方先做。阅读最后
// foreach ($arr as $key => $value) {
// $rating[$key] = $value['typeid'];
// }
// if ($isTure) {
// array_multisort($rating, SORT_ASC, $arr);
// } else {
// array_multisort($rating, SORT_DESC, $arr);
// }