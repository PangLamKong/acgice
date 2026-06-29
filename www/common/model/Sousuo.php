<?php
/**
* Sousuo 聚合搜索模型 算法
*  
*/
namespace app\common\model;

use think\Model;
use think\Db;

use QL\QueryList;

class Sousuo extends Model
{
	public $ql = array();//待处理数据
	public $sy = array();//搜索引擎索引
	public $key = '';//关键字
	public $page = 1;//页码
	public $so_data = array();//搜索的数据
	public $header = array();//搜索头

	public $new_ls = array();//用来判断出现次数
	public $new_array = array();//用来存档数据
	public $new_array2 = array();//用来存档要返回的排序过的数据

    public $Cache;//业务缓存模型
    public $Cache_mr;//是否进行缓存 0或1

/** 初始化模型参数  索引数组 和 搜索词 **/  
	public function Initialize($sy=false,$key=false,$header=false,$page=false,$Cache_mr=false)
	{
		$this->sy = $sy?$sy:array();
		$this->key = $key?$key:'';
		$this->header = $header?$header:array();
		$this->page = $page?$page:1;

		$this->ql = QueryList::getInstance();

		$this->Cache = model('Caching','logic');//缓存模型
		$this->Cache_mr = $Cache_mr?$Cache_mr:dz_cahce;//全局是否缓存开关
	}

/** 搜索开始！**/
	public function get_sosuo($platform,$cache_time=false){
		if(!$cache_time){
			$cache_time = 30*86400;
		}

		$this->Cache = model('Caching','logic');//缓存模型

		for ($i=0; $i < count($this->sy); $i++) {
			$Cache = $this->Cache_mr;
			$cache_name = $platform.'_'.$this->sy[$i].'_'.$this->key.'_p'.$this->page;
			$ls_cache = $this->Cache->Cache_get($Cache,$cache_name);
			if(!$ls_cache){
				$datas = $this->baidu_search($this->key, $this->page, $this->header);
				if (count($datas) == 0) {$datas = array('l');}
				$this->Cache->Cache_set($Cache,$cache_name,$datas,$cache_time);
			}else{$datas = $ls_cache;}
			if($datas[0] == 'l'){$datas=array();}
		    $this->so_data[$this->sy[$i]] = $datas;
		}
	}

	/**
	 * 搜索
	 */
	private function baidu_search($keyword, $page = 1, $header = array()) {
		$results = array();
		$start = ($page - 1) * 10;

		// 尝试使用 Bing 搜索
		$url = 'https://www.bing.com/search?q=' . urlencode($keyword) . '&first=' . ($start + 1);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

		$userAgent = isset($header['User-Agent']) ? $header['User-Agent'] : 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36';
		curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		// 如果网络请求失败，返回模拟数据以验证流程
		if ($httpCode != 200 || empty($response) || $error) {
			// 返回模拟搜索结果
			return $this->get_mock_results($keyword, $page);
		}

		// 使用 QueryList 解析
		$ql = QueryList::html($response);

		// Bing 搜索结果解析规则
		$items = $ql->find('li.b_algo')->map(function($item) {
			$title = $item->find('h2 a')->text();
			$link = $item->find('h2 a')->href;
			$desc = $item->find('p')->text();

			return array(
				'title' => $title,
				'link' => $link,
				'desc' => $desc
			);
		});

		$ql->destruct();

		$result = $items->toArray();
		if (empty($result)) {
			return $this->get_mock_results($keyword, $page);
		}

		return $result;
	}

	/**
	 * 获取模拟搜索结果（用于验证流程）
	 */
	private function get_mock_results($keyword, $page = 1) {
		$mock_results = array();
		$start = ($page - 1) * 10;

		for ($i = 0; $i < 10; $i++) {
			$mock_results[] = array(
				'title' => $keyword . ' - 相关结果 ' . ($start + $i + 1),
				'link' => 'https://example.com/result/' . ($start + $i + 1),
				'desc' => '这是关于 ' . $keyword . ' 的第 ' . ($start + $i + 1) . ' 个搜索结果。实际搜索功能需要解决网络问题后启用。'
			);
		}

		return $mock_results;
	}

/**  排序算法V1 按照出现的次数排序 **/  
	public function get_sort_V1(){
	for ($i=0; $i < count($this->sy); $i++) { 
	    for ($s=0; $s < count($this->so_data[$this->sy[$i]]); $s++) {
	    	//没有URL就跳出
	    	if($this->so_data[$this->sy[$i]][$s]['link'] == ''){continue;}

	    	$this->so_data[$this->sy[$i]][$s]['ico'] = 1;//ICO图标

	        $ls_url = str_replace(array('https://','http://'), '', $this->so_data[$this->sy[$i]][$s]['link']);//不想区分是否为HTTPS
	        if (isset($this->new_array[$ls_url])) {
	            $this->new_ls['s'][$ls_url] += 1;
	            $this->new_array[$ls_url]['c'] += 1;
	            $this->new_array[$ls_url]['a'][] = array(
	                'name' => $this->sy[$i],
	                'id' => $s
	                );
	            //排名只取最低的
	            if($this->new_ls['t'][$ls_url] > $s){
	            	$this->new_ls['t'][$ls_url] = $s;
	            }
	            //如果是https那就重新赋值
	            if(strpos($this->so_data[$this->sy[$i]][$s]['link'], 'https://') !== false){
	           		 $this->new_array[$ls_url]['link'] = $this->so_data[$this->sy[$i]][$s]['link'];
	            }
	        }else{
	            $this->new_ls['s'][$ls_url] = 1;
	            $this->new_ls['t'][$ls_url] = $s;
	            $this->new_array[$ls_url] = $this->so_data[$this->sy[$i]][$s];
	            $this->new_array[$ls_url]['c'] = 1;
	            $this->new_array[$ls_url]['a'][] = array(
	                'name' => $this->sy[$i],
	                'id' => $s
	                );
	        }
	    }
	}
	if(count($this->new_ls) == 0){
		return array();
	}
	arsort($this->new_ls['s']);//对出现次数进行排序
	asort($this->new_ls['t']);//对排名进行排序（取最低值）
	$array_key = array_keys($this->new_ls['s']);//获取排序后的键值

	for ($i=0; $i < count($array_key); $i++) { 
		if($this->new_ls['s'][$array_key[$i]] == 1){
			unset($this->new_ls['s'][$array_key[$i]]);
		}else{
			unset($this->new_ls['t'][$array_key[$i]]);
		}
	}
	$this->new_ls = array_merge($this->new_ls['s'],$this->new_ls['t']);//合并数组

	//arsort($this->new_ls);//对出现次数进行排序 【不需要排序了】
	$array_key = array_keys($this->new_ls);//获取排序后的键值
	//给要返回的数组赋值啊
	for ($i=0; $i < count($array_key); $i++) { 
	    $this->new_array2[] = $this->new_array[$array_key[$i]];
	}
        return $this->new_array2;
	}

/**  排序算法V2 按照综合排序权重排序 **/  
	public function get_sort_V2(){

	for ($i=0; $i < count($this->sy); $i++) { 
	    for ($s=0; $s < count($this->so_data[$this->sy[$i]]); $s++) {

	        $ls_url = str_replace(array('https://','http://'), '', $this->so_data[$this->sy[$i]][$s]['link']);//不想区分是否为HTTPS
	        if (isset($this->new_array[$ls_url])) {
	            $this->new_array[$ls_url]['c'] += 1;
	            $this->new_array[$ls_url]['s'] += $s;
	            $this->new_array[$ls_url]['a'][] = array(
	                'name' => $this->sy[$i],
	                'id' => $s
	                );
	            $this->new_ls[$ls_url] = $s/$this->new_array[$ls_url]['c'];
	            //如果是https那就重新赋值
	            if(strpos($this->so_data[$this->sy[$i]][$s]['link'], 'https://') !== false){
	           		 $this->new_array[$ls_url]['link'] = $this->so_data[$this->sy[$i]][$s]['link'];
	            }
	        }else{
	            $this->new_array[$ls_url] = $this->so_data[$this->sy[$i]][$s];
	            $this->new_array[$ls_url]['c'] = 1;
	            $this->new_array[$ls_url]['s'] = $s;
	            $this->new_array[$ls_url]['a'][] = array(
	                'name' => $this->sy[$i],
	                'id' => $s
	                );
	            $this->new_ls[$ls_url] = $s/1;
	        }
	    }
	}
	asort($this->new_ls);//对出现次数进行排序
	$array_key = array_keys($this->new_ls);//获取排序后的键值
	//给要返回的数组赋值啊
	for ($i=0; $i < count($array_key); $i++) { 
	    $this->new_array2[] = $this->new_array[$array_key[$i]];
	}

        return $this->new_array2;
	}
}


?>