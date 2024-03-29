<?php
// +----------------------------------------------------------------------
// | Easemob for PHP [ WE CAN DO IT JUST THINK IT ]
// | 该版本由校内秘密，后台成员小李飞刀同学，经过半天的整理，方便大家使用。校内秘密，是一款校园内的匿名交友APP
// +----------------------------------------------------------------------
// | Copyright (c) 2014 校内秘密  www.xiaoneimimi.com
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: limx <limx@xiaoneimimi.com>
// +----------------------------------------------------------------------
/**
 * 环信-服务器端REST API
 * @author    limx <limx@xiaoneimimi.com>
 */


namespace app\components;
 
 
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;


class EasemobClient extends Component {
	public $client_id;
	public $client_secret;
	public $org_name;
	public $app_name;
	public $url;
	public $api_url;
	
	protected $dbConfigCollection;

	/**
	 * 初始化参数
	 *
	 * @param array $options   
	 * @param $options['client_id']    	
	 * @param $options['client_secret'] 
	 * @param $options['org_name']    	
	 * @param $options['app_name']   		
	 */
	public function __construct($config=[]) {
		// $this->client_id = isset ( $config ['client_id'] ) ? $config ['client_id'] : '';
		// $this->client_secret = isset ( $config ['client_secret'] ) ? $config ['client_secret'] : '';
		// $this->org_name = isset ( $config ['org_name'] ) ? $config ['org_name'] : '';
		// $this->app_name = isset ( $config ['app_name'] ) ? $config ['app_name'] : '';
		parent::__construct($config);
	}

    public function init()
    {
        parent::init();
		if (! empty ( $this->org_name ) && ! empty ( $this->app_name ) && ! empty ( $this->api_url )) {
			$this->url = $this->api_url . $this->org_name . '/' . $this->app_name . '/';
		}
		$m = new \MongoClient();
		$this->dbConfigCollection = $m->selectCollection('local','config');
    }

	/**
	 * 开放注册模式
	 *
	 * @param $options['username'] 用户名        	
	 * @param $options['password'] 密码        	
	 */
	public function openRegister($options) {
		$url = $this->url . "users";
		$result = $this->postCurl ( $url, $options);
		return json_decode($result,true);
	}
	
	/**
	 * 授权注册模式 || 批量注册
	 *
	 * @param $options['username'] 用户名        	
	 * @param $options['password'] 密码
	 *        	批量注册传二维数组
	 */
	public function accreditRegister($options) {
		$url = $this->url . "users";
		$access_token = $this->getToken ();
		$header [] = 'Authorization: Bearer ' . $access_token;
		$result = $this->postCurl ( $url, $options, $header );
		return json_decode($result,true);
	}
	
	/**
	 * 获取指定用户详情
	 *
	 * @param $username 用户名        	
	 */
	public function userDetails($username) {
		$url = $this->url . "users/" . $username;
		$access_token = $this->getToken ();
		$header [] = 'Authorization: Bearer ' . $access_token;
		$result = $this->postCurl ( $url, '', $header, $type = 'GET' );
		return json_decode($result,true);
	}
	
	/**
	 * 重置用户密码
	 *
	 * @param $options['username'] 用户名        	
	 * @param $options['password'] 密码        	
	 * @param $options['newpassword'] 新密码        	
	 */
	public function editPassword($options) {
		$url = $this->url . "users/" . $options ['username'] . "/password";
		$access_token = $this->getToken ();
		$header [] = 'Authorization: Bearer ' . $access_token;
		$result = $this->postCurl ( $url, $options, $header, $type = 'PUT');
		return json_decode($result,true);
	}
	/**
	 * 删除用户
	 *
	 * @param $username 用户名        	
	 */
	public function deleteUser($username) {
		$url = $this->url . "users/" . $username;
		$access_token = $this->getToken ();
		$header [] = 'Authorization: Bearer ' . $access_token;
		$result = $this->postCurl ( $url, '', $header, $type = 'DELETE' );
		return json_decode($result,true);
	}
	
	/**
	 * 批量删除用户
	 * 描述：删除某个app下指定数量的环信账号。上述url可一次删除300个用户,数值可以修改 建议这个数值在100-500之间，不要过大
	 *
	 * @param $limit="300" 默认为300条        	
	 * @param $ql 删除条件
	 *        	如ql=order+by+created+desc 按照创建时间来排序(降序)
	 */
	public function batchDeleteUser($limit = "300", $ql = '') {
		$url = $this->url . "users?limit=" . $limit;
		if (! empty ( $ql )) {
			$url = $this->url . "users?ql=" . $ql . "&limit=" . $limit;
		}
		$access_token = $this->getToken ();
		$header [] = 'Authorization: Bearer ' . $access_token;
		$result = $this->postCurl ( $url, '', $header, $type = 'DELETE' );
		return json_decode($result,true);
	}
	
	/**
	 * 给一个用户添加一个好友
	 *
	 * @param
	 *        	$owner_username
	 * @param
	 *        	$friend_username
	 */
	public function addFriend($owner_username, $friend_username) {
		$url = $this->url . "users/" . $owner_username . "/contacts/users/" . $friend_username;
		$access_token = $this->getToken ();
		$header [] = 'Authorization: Bearer ' . $access_token;
		$result = $this->postCurl ( $url, '', $header );
		return json_decode($result,true);
	}
	/**
	 * 删除好友
	 *
	 * @param
	 *        	$owner_username
	 * @param
	 *        	$friend_username
	 */
	public function deleteFriend($owner_username, $friend_username) {
		$url = $this->url . "users/" . $owner_username . "/contacts/users/" . $friend_username;
		$access_token = $this->getToken ();
		$header [] = 'Authorization: Bearer ' . $access_token;
		$result = $this->postCurl ( $url, '', $header, $type = "DELETE" );
	}
	/**
	 * 查看用户的好友
	 *
	 * @param
	 *        	$owner_username
	 */
	public function showFriend($owner_username) {
		$url = $this->url . "users/" . $owner_username . "/contacts/users/";
		$access_token = $this->getToken ();
		$header [] = 'Authorization: Bearer ' . $access_token;
		$result = $this->postCurl ( $url, '', $header, $type = "GET" );
		return json_decode($result,true);
	}
	// +----------------------------------------------------------------------
	// | 聊天相关的方法
	// +----------------------------------------------------------------------
	/**
	 * 查看用户是否在线
	 *
	 * @param
	 *        	$username
	 */
	public function isOnline($username) {
		$url = $this->url . "users/" . $username . "/status";
		$access_token = $this->getToken ();
		$header [] = 'Authorization: Bearer ' . $access_token;
		$result = $this->postCurl ( $url, '', $header, $type = "GET" );
		return json_decode($result,true);
	}
	/**
	 * 发送消息
	 *
	 * @param string $from_user
	 *        	发送方用户名
	 * @param array $username
	 *        	array('1','2')
	 * @param string $target_type
	 *        	默认为：users 描述：给一个或者多个用户(users)或者群组发送消息(chatgroups)
	 * @param string $content        	
	 * @param array $ext
	 *        	自定义参数
	 */
	public function yy_hxSend($from, $target, $msg, $target_type, $ext = null) {
		$option ['target_type'] = $target_type;
		$option ['target'] = $target;
		$option ['msg'] = $msg;
		$option ['from'] = $from;

		if($ext != null) {
			$option ['ext'] = $ext;
		}
		
		$url = $this->url . "messages";
		$access_token = $this->getToken ();
		$header [] = 'Authorization: Bearer ' . $access_token;
		$result = $this->postCurl ( $url, $option, $header );
		return json_decode($result,true);
	}

	/**
	 * 获取app中所有的群组
	 */
	public function chatGroups() {
		$url = $this->url . "chatgroups";
		$access_token = $this->getToken ();
		$header [] = 'Authorization: Bearer ' . $access_token;
		$result = $this->postCurl ( $url, '', $header, $type = "GET" );
		return json_decode($result,true);
	}
	/**
	 * 创建群组
	 *
	 * @param $option['groupname'] //群组名称,
	 *        	此属性为必须的
	 * @param $option['desc'] //群组描述,
	 *        	此属性为必须的
	 * @param $option['public'] //是否是公开群,
	 *        	此属性为必须的 true or false
	 * @param $option['approval'] //加入公开群是否需要批准,
	 *        	没有这个属性的话默认是true, 此属性为可选的
	 * @param $option['owner'] //群组的管理员,
	 *        	此属性为必须的
	 * @param $option['members'] //群组成员,此属性为可选的        	
	 */
	public function createGroups($option) {
		$url = $this->url . "chatgroups";
		$access_token = $this->getToken ();
		$header [] = 'Authorization: Bearer ' . $access_token;
		$result = $this->postCurl ( $url, $option, $header );
		return json_decode($result,true);
	}
	/**
	 * 获取群组详情
	 *
	 * @param
	 *        	$group_id
	 */
	public function chatGroupsDetails($group_id) {
		$url = $this->url . "chatgroups/" . $group_id;
		$access_token = $this->getToken ();
		$header [] = 'Authorization: Bearer ' . $access_token;
		$result = $this->postCurl ( $url, '', $header, $type = "GET" );
		return json_decode($result,true);
	}
	/**
	 * 删除群组
	 *
	 * @param
	 *        	$group_id
	 */
	public function deleteGroups($group_id) {
		$url = $this->url . "chatgroups/" . $group_id;
		$access_token = $this->getToken ();
		$header [] = 'Authorization: Bearer ' . $access_token;
		$result = $this->postCurl ( $url, '', $header, $type = "DELETE" );
		return json_decode($result,true);
	}
	/**
	 * 获取群组成员
	 *
	 * @param
	 *        	$group_id
	 */
	public function groupsUser($group_id) {
		$url = $this->url . "chatgroups/" . $group_id . "/users";
		$access_token = $this->getToken ();
		$header [] = 'Authorization: Bearer ' . $access_token;
		$result = $this->postCurl ( $url, '', $header, $type = "GET" );
		return json_decode($result,true);
	}
	/**
	 * 群组添加成员
	 *
	 * @param
	 *        	$group_id
	 * @param
	 *        	$username
	 */
	public function addGroupsUser($group_id, $username) {
		$url = $this->url . "chatgroups/" . $group_id . "/users/" . $username;
		$access_token = $this->getToken ();
		$header [] = 'Authorization: Bearer ' . $access_token;
		$result = $this->postCurl ( $url, '', $header, $type = "POST" );
		return json_decode($result,true);
	}
	/**
	 * 群组删除成员
	 *
	 * @param
	 *        	$group_id
	 * @param
	 *        	$username
	 */
	public function delGroupsUser($group_id, $username) {
		$url = $this->url . "chatgroups/" . $group_id . "/users/" . $username;
		$access_token = $this->getToken ();
		$header [] = 'Authorization: Bearer ' . $access_token;
		$result = $this->postCurl ( $url, '', $header, $type = "DELETE" );
		return json_decode($result,true);
	}
	/**
	 * 聊天消息记录
	 *
	 * @param $ql 查询条件如：$ql
	 *        	= "select+*+where+from='" . $uid . "'+or+to='". $uid ."'+order+by+timestamp+desc&limit=" . $limit . $cursor;
	 *        	默认为order by timestamp desc
	 * @param $cursor 分页参数
	 *        	默认为空
	 * @param $limit 条数
	 *        	默认20
	 */
	public function chatRecord($ql = '', $cursor = '', $limit = 20) {
		$ql = ! empty ( $ql ) ? "ql=" . $ql : "" ;
		$cursor = ! empty ( $cursor ) ? "&cursor=" . $cursor : '';
		$url = $this->url . "chatmessages?" . $ql . "&limit=" . $limit . $cursor;
		$access_token = $this->getToken ();
		$header [] = 'Authorization: Bearer ' . $access_token;
		//var_dump($header);
		$result = $this->postCurl ( $url, '', $header, $type = "GET " );
		return json_decode($result,true);
	}
	/**
	 * 获取Token
	 */
	public function getToken() {
		$option ['grant_type'] = "client_credentials";
		$option ['client_id'] = $this->client_id;
		$option ['client_secret'] = $this->client_secret;
		$url = $this->url . "token";
		//var_dump($url);
		$config = $this->dbConfigCollection->findOne(['url'=>$this->url]);
		//var_dump($config);
		if($config!=null) {
			if(isset($config['expires_in']) && ($config['expires_in'])<time() ) {
				$result = $this->postCurl ( $url, $option);
				$result = json_decode($result,true);
				$result ['expires_in'] = $result ['expires_in'] + time ();
				//$new_data = ['access_token'=>$result ['access_token'],'expires_in'=>['expires_in']];
				$new_data = ['$set'=>['access_token'=>$result['access_token'], 'expires_in'=>$result['expires_in']]];
				$this->dbConfigCollection->update(['url'=>$this->url],$new_data);
				return $result ['access_token'];
			} 
			return $config ['access_token'];
		} else {
			$result = $this->postCurl ( $url, $option );
			$result = json_decode($result,true);
			//var_dump($result);
			$result ['expires_in'] = $result ['expires_in'] + time ();
			$new_data = ['url'=>$this->url,'access_token'=>$result ['access_token'],'expires_in'=>$result['expires_in']];
			$this->dbConfigCollection->insert($new_data);
			return $result ['access_token'];
		}
	}

	public function refreshToken() {
		$option ['grant_type'] = "client_credentials";
		$option ['client_id'] = $this->client_id;
		$option ['client_secret'] = $this->client_secret;
		$url = $this->url . "token";
		$result = $this->postCurl ( $url, $option);
		$result = json_decode($result,true);
		$result ['expires_in'] = $result ['expires_in'] + time ();
		//$new_data = ['access_token'=>$result ['access_token'],'expires_in'=>['expires_in']];
		$new_data = ['$set'=>['access_token'=>$result['access_token'], 'expires_in'=>$result['expires_in']]];
		$this->dbConfigCollection->update(['url'=>$this->url],$new_data);
		return $result ['access_token'];
	}
	
	/**
	 * CURL Post
	 */
	private function postCurl($url, $option, $header = [], $type = 'POST') {
		$curl = curl_init (); // 启动一个CURL会话
		//curl_setopt ( $curl, CURLOPT_PROXY, "http://10.169.95.174:3388");
		curl_setopt ( $curl, CURLOPT_URL, $url ); // 要访问的地址
		curl_setopt ( $curl, CURLOPT_SSL_VERIFYPEER, FALSE ); // 对认证证书来源的检查
		curl_setopt ( $curl, CURLOPT_SSL_VERIFYHOST, FALSE ); // 从证书中检查SSL加密算法是否存在
		curl_setopt ( $curl, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0)' ); // 模拟用户使用的浏览器
		if (! empty ( $option )) {
			$options = json_encode ( $option );
			curl_setopt ( $curl, CURLOPT_POSTFIELDS, $options ); // Post提交的数据包
		}
		curl_setopt ( $curl, CURLOPT_TIMEOUT, 30 ); // 设置超时限制防止死循环
		curl_setopt ( $curl, CURLOPT_HTTPHEADER, $header ); // 设置HTTP头
		curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, 1 ); // 获取的信息以文件流的形式返回
		curl_setopt ( $curl, CURLOPT_CUSTOMREQUEST, $type );
		$result = curl_exec ( $curl ); // 执行操作
		//$res = object_array ( json_decode ( $result ) );
		//$res ['status'] = curl_getinfo ( $curl, CURLINFO_HTTP_CODE );
		//pre ( $res );
		curl_close ( $curl ); // 关闭CURL会话
		if($result==null) {
			return json_encode(['error'=>'network error.']);
		}
		$rlt_j = json_decode($result,true);
		if(isset($rlt_j['error']) && $rlt_j['error']=='auth_invalid') {
		//	$this->refreshToken();
		}
		//var_dump($result);
		return $result;
	}
}
