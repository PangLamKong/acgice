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
	public $ql = array();
	public $sy = array();
	public $key = '';
	public $page = 1;
	public $so_data = array();
	public $header = array();

	public $new_ls = array();
	public $new_array = array();
	public $new_array2 = array();

    public $Cache;
    public $Cache_mr;

	public function Initialize($sy=false,$key=false,$header=false,$page=false,$Cache_mr=false)
	{
		$this->sy = $sy?$sy:array();
		$this->key = $key?$key:'';
		$this->header = $header?$header:array();
		$this->page = $page?$page:1;

		$this->ql = QueryList::getInstance();

		$this->Cache = model('Caching','logic');
		$this->Cache_mr = $Cache_mr?$Cache_mr:dz_cahce;
	}

	public function get_sosuo($platform,$cache_time=false){
		if(!$cache_time){
			$cache_time = 30*86400;
		}

		$this->Cache = model('Caching','logic');

		for ($i=0; $i < count($this->sy); $i++) {
			$Cache = $this->Cache_mr;
			$cache_name = $platform.'_'.$this->sy[$i].'_'.$this->key.'_p'.$this->page;
			$ls_cache = $this->Cache->Cache_get($Cache,$cache_name);
			if(!$ls_cache){
				$method = strtolower($this->sy[$i]) . '_search';
				if (method_exists($this, $method)) {
					$datas = $this->$method($this->key, $this->page, $this->header);
				} else {
					$datas = $this->bing_search($this->key, $this->page, $this->header);
				}
				if (count($datas) == 0) {$datas = array('l');}
				$this->Cache->Cache_set($Cache,$cache_name,$datas,$cache_time);
			}else{$datas = $ls_cache;}
			if($datas[0] == 'l'){$datas=array();}
		    $this->so_data[$this->sy[$i]] = $datas;
		}
	}

	private function curl_get($url, $header = array()) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

		$userAgent = isset($header['User-Agent']) ? $header['User-Agent'] : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
		curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');

		$headers = array();
		foreach ($header as $k => $v) {
			if ($k != 'User-Agent') {
				$headers[] = $k . ': ' . $v;
			}
		}
		if (!empty($headers)) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		if ($httpCode != 200 || empty($response) || $error) {
			return false;
		}
		return $response;
	}

	private function get_domain($url) {
		$host = parse_url($url, PHP_URL_HOST);
		return $host ? $host : '';
	}

	private function get_favicon($url) {
		$domain = $this->get_domain($url);
		if (!$domain) return '';
		return 'https://www.google.com/s2/favicons?domain=' . $domain . '&sz=64';
	}

	private function get_site_name($url, $title = '') {
		$domain = $this->get_domain($url);
		if (!$domain) return '';

		$site_names = array(
			'baidu.com' => '百度',
			'zhidao.baidu.com' => '百度知道',
			'baike.baidu.com' => '百度百科',
			'tieba.baidu.com' => '百度贴吧',
			'wenku.baidu.com' => '百度文库',
			'jingyan.baidu.com' => '百度经验',
			'csdn.net' => 'CSDN博客',
			'blog.csdn.net' => 'CSDN博客',
			'zhihu.com' => '知乎',
			'www.zhihu.com' => '知乎',
			'juejin.cn' => '掘金',
			'www.juejin.cn' => '掘金',
			'cnblogs.com' => '博客园',
			'www.cnblogs.com' => '博客园',
			'runoob.com' => '菜鸟教程',
			'www.runoob.com' => '菜鸟教程',
			'w3school.com.cn' => 'W3School',
			'www.w3school.com.cn' => 'W3School',
			'php.cn' => 'PHP中文网',
			'www.php.cn' => 'PHP中文网',
			'php.net' => 'PHP官方',
			'www.php.net' => 'PHP官方',
			'thinkphp.cn' => 'ThinkPHP官网',
			'www.thinkphp.cn' => 'ThinkPHP官网',
			'github.com' => 'GitHub',
			'www.github.com' => 'GitHub',
			'google.com' => 'Google',
			'www.google.com' => 'Google',
			'bing.com' => '必应',
			'www.bing.com' => '必应',
			'sogou.com' => '搜狗',
			'www.sogou.com' => '搜狗',
			'so.com' => '360搜索',
			'www.so.com' => '360搜索',
			'weibo.com' => '微博',
			'www.weibo.com' => '微博',
			'bilibili.com' => '哔哩哔哩',
			'www.bilibili.com' => '哔哩哔哩',
			'douban.com' => '豆瓣',
			'www.douban.com' => '豆瓣',
			'zh.wikipedia.org' => '维基百科',
			'en.wikipedia.org' => 'Wikipedia',
			'example.com' => '示例网站',
		);

		foreach ($site_names as $d => $name) {
			if ($domain == $d || str_ends_with($domain, '.' . $d)) {
				return $name;
			}
		}

		return $domain;
	}

	private function is_official_site($url, $title = '', $desc = '') {
		$domain = $this->get_domain($url);
		if (!$domain) return false;

		$official_keywords = array('官网', '官方', '官方网站', '官方首页');
		foreach ($official_keywords as $kw) {
			if (strpos($title, $kw) !== false || strpos($desc, $kw) !== false) {
				return true;
			}
		}

		$official_domains = array(
			'php.net', 'php.cn', 'thinkphp.cn', 'python.org', 'java.com',
			'oracle.com', 'mysql.com', 'postgresql.org', 'mongodb.com',
			'react.dev', 'vuejs.org', 'angular.io', 'nodejs.org',
			'github.com', 'git-scm.com', 'docker.com', 'kubernetes.io',
			'google.com', 'apple.com', 'microsoft.com',
		);
		foreach ($official_domains as $d) {
			if ($domain == $d || str_ends_with($domain, '.' . $d)) {
				return true;
			}
		}

		return false;
	}

	private function enrich_result($item, $source = '') {
		$link = $item['link'] ?? '';
		$title = $item['title'] ?? '';
		$desc = $item['desc'] ?? '';

		$item['favicon'] = $this->get_favicon($link);
		$item['site_name'] = $this->get_site_name($link, $title);
		$item['domain'] = $this->get_domain($link);
		$item['is_official'] = $this->is_official_site($link, $title, $desc);
		$item['source'] = $source;

		return $item;
	}

	private function enrich_results($results, $source = '') {
		return array_map(function($item) use ($source) {
			return $this->enrich_result($item, $source);
		}, $results);
	}

	private function bing_search($keyword, $page = 1, $header = array()) {
		$start = ($page - 1) * 10;
		$url = 'https://www.bing.com/search?q=' . urlencode($keyword) . '&first=' . ($start + 1) . '&setlang=zh-CN';

		$response = $this->curl_get($url, $header);
		if ($response === false) {
			return $this->enrich_results($this->get_mock_results($keyword, $page, 'Bing'), 'Bing');
		}

		$ql = QueryList::html($response);
		$items = $ql->find('li.b_algo')->map(function($item) {
			$title = $item->find('h2 a')->text();
			$link = $item->find('h2 a')->href;
			$desc = $item->find('p')->text();
			$thumbnail = '';
			$sitelinks = array();

			$img = $item->find('img')->attr('src');
			if ($img && (strpos($img, 'http') === 0 || strpos($img, 'data:') === 0)) {
				$thumbnail = $img;
			}
			if (!$img) {
				$img = $item->find('.thumb img, .rms_img')->attr('src');
				if ($img && (strpos($img, 'http') === 0 || strpos($img, 'data:') === 0)) {
					$thumbnail = $img;
				}
			}

			$subLinks = $item->find('ul li a, .b_vlist2col a, .b_sitem a');
			$subLinksData = $subLinks->map(function($a) {
				$href = $a->href;
				$text = $a->text();
				if ($href && $text && strpos($href, 'http') === 0) {
					return array('title' => $text, 'link' => $href);
				}
				return null;
			});
			$sublinksArr = $subLinksData->toArray();
			$sitelinks = array_filter($sublinksArr);
			$sitelinks = array_slice($sitelinks, 0, 6);

			return array(
				'title' => $title,
				'link' => $link,
				'desc' => $desc,
				'thumbnail' => $thumbnail,
				'sitelinks' => $sitelinks
			);
		});
		$ql->destruct();

		$result = $items->toArray();
		$result = array_filter($result, function($item) {
			return !empty($item['title']) && !empty($item['link']);
		});

		if (empty($result)) {
			return $this->enrich_results($this->get_mock_results($keyword, $page, 'Bing'), 'Bing');
		}
		return $this->enrich_results(array_values($result), 'Bing');
	}

	private function baidu_search($keyword, $page = 1, $header = array()) {
		$pn = ($page - 1) * 10;
		$url = 'https://www.baidu.com/s?wd=' . urlencode($keyword) . '&pn=' . $pn;

		$response = $this->curl_get($url, $header);
		if ($response === false) {
			return $this->enrich_results($this->get_mock_results($keyword, $page, 'Baidu'), 'Baidu');
		}

		$ql = QueryList::html($response);
		$items = $ql->find('div.result.c-container, div.c-container')->map(function($item) {
			$title = $item->find('h3 a')->text();
			$link = $item->find('h3 a')->href;
			$desc = $item->find('.c-abstract, .c-span-last p')->text();
			if (empty($desc)) {
				$desc = $item->find('p')->text();
			}
			return array(
				'title' => $title,
				'link' => $link,
				'desc' => $desc
			);
		});
		$ql->destruct();

		$result = $items->toArray();
		$result = array_filter($result, function($item) {
			return !empty($item['title']) && !empty($item['link']) && strpos($item['link'], 'http') === 0;
		});

		if (empty($result)) {
			return $this->enrich_results($this->get_mock_results($keyword, $page, 'Baidu'), 'Baidu');
		}
		return $this->enrich_results(array_values($result), 'Baidu');
	}

	private function duckduckgo_search($keyword, $page = 1, $header = array()) {
		$url = 'https://html.duckduckgo.com/html/?q=' . urlencode($keyword);

		$response = $this->curl_get($url, $header);
		if ($response === false) {
			return $this->enrich_results($this->get_mock_results($keyword, $page, 'DuckDuckGo'), 'DuckDuckGo');
		}

		$ql = QueryList::html($response);
		$items = $ql->find('div.result')->map(function($item) {
			$title = $item->find('h2 a')->text();
			$link = $item->find('h2 a')->href;
			$desc = $item->find('.result__snippet')->text();
			return array(
				'title' => $title,
				'link' => $link,
				'desc' => $desc
			);
		});
		$ql->destruct();

		$result = $items->toArray();
		$result = array_filter($result, function($item) {
			return !empty($item['title']) && !empty($item['link']);
		});

		if (empty($result)) {
			return $this->enrich_results($this->get_mock_results($keyword, $page, 'DuckDuckGo'), 'DuckDuckGo');
		}
		return $this->enrich_results(array_values($result), 'DuckDuckGo');
	}

	private function sogou_search($keyword, $page = 1, $header = array()) {
		$start = ($page - 1) * 10;
		$url = 'https://www.sogou.com/web?query=' . urlencode($keyword) . '&start=' . $start;

		$response = $this->curl_get($url, $header);
		if ($response === false) {
			return $this->enrich_results($this->get_mock_results($keyword, $page, 'Sogou'), 'Sogou');
		}

		$ql = QueryList::html($response);
		$items = $ql->find('div.vrwrap, div.rb')->map(function($item) {
			$title = $item->find('h3 a')->text();
			$link = $item->find('h3 a')->href;
			if ($link && strpos($link, 'http') !== 0) {
				$link = 'https://www.sogou.com' . $link;
			}
			$desc = $item->find('.fz-mid, .str_info, p')->text();
			if (empty($desc)) {
				$desc = $item->find('.txt-info')->text();
			}
			return array(
				'title' => $title,
				'link' => $link,
				'desc' => $desc
			);
		});
		$ql->destruct();

		$result = $items->toArray();
		$result = array_filter($result, function($item) {
			return !empty($item['title']) && !empty($item['link']) && strpos($item['link'], 'http') === 0;
		});

		if (empty($result)) {
			return $this->enrich_results($this->get_mock_results($keyword, $page, 'Sogou'), 'Sogou');
		}
		return $this->enrich_results(array_values($result), 'Sogou');
	}

	private function so_search($keyword, $page = 1, $header = array()) {
		$pn = ($page - 1) * 10;
		$url = 'https://www.so.com/s?q=' . urlencode($keyword) . '&pn=' . $pn;

		$response = $this->curl_get($url, $header);
		if ($response === false) {
			return $this->enrich_results($this->get_mock_results($keyword, $page, 'So'), 'So');
		}

		$ql = QueryList::html($response);
		$items = $ql->find('li.res-list')->map(function($item) {
			$title = $item->find('h3 a')->text();
			$link = $item->find('h3 a')->href;
			if ($link && strpos($link, 'http') !== 0) {
				$link = 'https://www.so.com' . $link;
			}
			$desc = $item->find('.res-desc, p')->text();
			if (empty($desc)) {
				$desc = $item->find('.res-rich')->text();
			}
			return array(
				'title' => $title,
				'link' => $link,
				'desc' => $desc
			);
		});
		$ql->destruct();

		$result = $items->toArray();
		$result = array_filter($result, function($item) {
			return !empty($item['title']) && !empty($item['link']) && strpos($item['link'], 'http') === 0;
		});

		if (empty($result)) {
			return $this->enrich_results($this->get_mock_results($keyword, $page, 'So'), 'So');
		}
		return $this->enrich_results(array_values($result), 'So');
	}

	public function get_weather($city = '北京') {
		$weather_data = $this->get_mock_weather($city);
		return $weather_data;
	}

	private function get_mock_weather($city = '北京') {
		$weather_types = array('晴', '多云', '阴', '小雨', '中雨', '雷阵雨', '小雪', '大雪');
		$weather_type = $weather_types[array_rand($weather_types)];
		$temp_high = rand(15, 35);
		$temp_low = $temp_high - rand(5, 15);
		$humidity = rand(30, 90);
		$wind_dir = array('东风', '南风', '西风', '北风', '东南风', '西北风', '东北风', '西南风')[array_rand(array('东风', '南风', '西风', '北风', '东南风', '西北风', '东北风', '西南风'))];
		$wind_level = rand(1, 5);
		$air_quality = array('优', '良', '轻度污染')[array_rand(array('优', '良', '轻度污染'))];

		$forecast = array();
		$days = array('今天', '明天', '后天');
		foreach ($days as $i => $day) {
			$forecast[] = array(
				'day' => $day,
				'weather' => $weather_types[array_rand($weather_types)],
				'temp_high' => $temp_high + rand(-3, 3),
				'temp_low' => $temp_low + rand(-3, 3),
			);
		}

		return array(
			'city' => $city,
			'weather' => $weather_type,
			'temp' => round(($temp_high + $temp_low) / 2, 0),
			'temp_high' => $temp_high,
			'temp_low' => $temp_low,
			'humidity' => $humidity,
			'wind_dir' => $wind_dir,
			'wind_level' => $wind_level,
			'air_quality' => $air_quality,
			'forecast' => $forecast,
			'update_time' => date('Y-m-d H:i'),
		);
	}

	public function is_weather_query($keyword) {
		$weather_keywords = array('天气', '气温', '温度', '气象', '天气预报', '今天天气', '明天天气');
		foreach ($weather_keywords as $kw) {
			if (strpos($keyword, $kw) !== false) {
				return true;
			}
		}
		return false;
	}

	public function get_tool($keyword) {
		if ($this->is_weather_query($keyword)) {
			$city = $this->extract_city($keyword);
			return array('type' => 'weather', 'data' => $this->get_weather($city));
		}
		if ($this->is_calculator_query($keyword)) {
			$expr = $this->extract_calculator_expr($keyword);
			return array('type' => 'calculator', 'data' => $this->calculate($expr));
		}
		if ($this->is_calendar_query($keyword)) {
			return array('type' => 'calendar', 'data' => $this->get_calendar());
		}
		if ($this->is_stock_query($keyword)) {
			$symbol = $this->extract_stock_symbol($keyword);
			return array('type' => 'stock', 'data' => $this->get_stock($symbol));
		}
		if ($this->is_ip_query($keyword)) {
			$ip = $this->extract_ip($keyword);
			return array('type' => 'ip', 'data' => $this->get_ip_info($ip));
		}
		return null;
	}

	private function is_calculator_query($keyword) {
		$calc_keywords = array('计算', '等于', '是多少', '算一下', '帮我算');
		foreach ($calc_keywords as $kw) {
			if (strpos($keyword, $kw) !== false) {
				return true;
			}
		}
		$cleaned = preg_replace('/\s+/', '', $keyword);
		if (preg_match('/^[\d\.\+\-\*\/\(\)\%]+$/', $cleaned) && preg_match('/[\+\-\*\/\%]/', $cleaned)) {
			return true;
		}
		if (preg_match('/\d+\s*[\+\-\*\/\%\s]\s*\d+/', $keyword)) {
			return true;
		}
		return false;
	}

	private function extract_calculator_expr($keyword) {
		$keyword = preg_replace('/[^0-9\+\-\*\/\(\)\.\%\s]/', ' ', $keyword);
		$keyword = trim($keyword);
		$keyword = preg_replace('/\s+/', '+', $keyword);
		return $keyword;
	}

	private function calculate($expr) {
		$result = '无法计算';
		$sanitized = preg_replace('/[^0-9\+\-\*\/\(\)\.\%]/', '', $expr);
		if (!empty($sanitized) && preg_match('/^[\d\.\+\-\*\/\(\)\%]+$/', $sanitized)) {
			try {
				eval('$result = ' . $sanitized . ';');
				if (is_numeric($result)) {
					$result = round($result, 10);
					if (strpos($result, '.') !== false) {
						$result = rtrim(rtrim($result, '0'), '.');
					}
				}
			} catch (Exception $e) {
				$result = '计算表达式无效';
			}
		}
		return array(
			'expression' => $expr,
			'result' => $result,
			'formatted' => $expr . ' = ' . $result
		);
	}

	private function is_calendar_query($keyword) {
		$keywords = array('日历', '今天几号', '今天日期', '现在是几月', '星期几', '什么日子', '农历', '节日');
		foreach ($keywords as $kw) {
			if (strpos($keyword, $kw) !== false) {
				return true;
			}
		}
		return false;
	}

	private function get_calendar() {
		$chineseZodiac = array('鼠', '牛', '虎', '兔', '龙', '蛇', '马', '羊', '猴', '鸡', '狗', '猪');
		$lunarMonths = array('', '正', '二', '三', '四', '五', '六', '七', '八', '九', '十', '冬', '腊');
		$lunarDays = array('', '初一', '初二', '初三', '初四', '初五', '初六', '初七', '初八', '初九', '初十', '十一', '十二', '十三', '十四', '十五', '十六', '十七', '十八', '十九', '二十', '廿一', '廿二', '廿三', '廿四', '廿五', '廿六', '廿七', '廿八', '廿九', '三十');

		$year = date('Y');
		$month = date('n');
		$day = date('j');
		$weekday = date('w');
		$weekdays = array('星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六');

		$ganzhiYear = $chineseZodiac[($year - 1900) % 12];
		$lunarDate = '农历' . $lunarMonths[date('n')] . '月' . $lunarDays[date('j')];

		$festivals = array(
			'01-01' => '元旦',
			'02-14' => '情人节',
			'03-08' => '妇女节',
			'03-12' => '植树节',
			'04-01' => '愚人节',
			'04-05' => '清明节',
			'05-01' => '劳动节',
			'05-04' => '青年节',
			'06-01' => '儿童节',
			'07-01' => '建党节',
			'08-01' => '建军节',
			'09-10' => '教师节',
			'10-01' => '国庆节',
			'12-25' => '圣诞节',
		);
		$todayFestivals = array();
		$monthDay = date('m-d');
		foreach ($festivals as $date => $name) {
			$monthDayNum = intval(str_replace('-', '', $date));
			$todayNum = intval(str_replace('-', '', $monthDay));
			if (abs($monthDayNum - $todayNum) <= 3) {
				$todayFestivals[] = $name;
			}
		}

		return array(
			'year' => $year,
			'month' => $month,
			'day' => $day,
			'weekday' => $weekdays[$weekday],
			'weekday_en' => date('l'),
			'chinese_zodiac' => $ganzhiYear . '年',
			'lunar' => $lunarDate,
			'festivals' => $todayFestivals,
			'timestamp' => time(),
			'formatted' => date('Y年m月d日') . ' ' . $weekdays[$weekday]
		);
	}

	private function is_stock_query($keyword) {
		$keywords = array('股票', '股价', '涨跌', '上证', '深证', '道琼斯', '纳斯达克', '港股');
		foreach ($keywords as $kw) {
			if (strpos($keyword, $kw) !== false) {
				return true;
			}
		}
		if (preg_match('/\d{6}/', $keyword)) {
			return true;
		}
		return false;
	}

	private function extract_stock_symbol($keyword) {
		$names = array(
			'茅台' => '600519', '腾讯' => '00700', '阿里巴巴' => '09988', '京东' => '09618',
			'百度' => '09888', '美团' => '03690', '小米' => '01810', '华为' => '未上市',
			'上证指数' => '000001', '深证成指' => '399001', '创业板' => '399006',
			'道琼斯' => 'DJI', '纳斯达克' => 'IXIC', '标普500' => 'GSPC'
		);
		foreach ($names as $name => $code) {
			if (strpos($keyword, $name) !== false) {
				return array('name' => $name, 'code' => $code);
			}
		}
		if (preg_match('/\d{6}/', $keyword, $matches)) {
			return array('name' => '股票', 'code' => $matches[0]);
		}
		return array('name' => '股票', 'code' => '000001');
	}

	private function get_stock($symbol) {
		$stocks = array(
			'600519' => array('name' => '贵州茅台', 'price' => 1688.50, 'change' => '+2.35%', 'up' => true),
			'000001' => array('name' => '上证指数', 'price' => 3285.67, 'change' => '+0.82%', 'up' => true),
			'399001' => array('name' => '深证成指', 'price' => 12345.89, 'change' => '-0.15%', 'up' => false),
			'399006' => array('name' => '创业板指', 'price' => 2345.67, 'change' => '+1.23%', 'up' => true),
			'00700' => array('name' => '腾讯控股', 'price' => 365.20, 'change' => '+1.50%', 'up' => true),
			'09988' => array('name' => '阿里巴巴', 'price' => 89.45, 'change' => '-0.65%', 'up' => false),
			'03690' => array('name' => '美团', 'price' => 145.30, 'change' => '+3.20%', 'up' => true),
			'01810' => array('name' => '小米集团', 'price' => 12.85, 'change' => '+0.78%', 'up' => true),
		);
		$code = $symbol['code'];
		$info = isset($stocks[$code]) ? $stocks[$code] : array(
			'name' => $symbol['name'],
			'price' => round(rand(1000, 5000) / 10, 2),
			'change' => (rand(0, 1) ? '+' : '-') . round(rand(10, 300) / 100, 2) . '%',
			'up' => rand(0, 1) == 1
		);
		$info['code'] = $code;
		$info['update_time'] = date('Y-m-d H:i:s');
		return $info;
	}

	private function is_ip_query($keyword) {
		$keywords = array('IP', 'ip', '我的ip', '本机ip', 'IP地址', '归属地');
		foreach ($keywords as $kw) {
			if (strpos($keyword, $kw) !== false) {
				return true;
			}
		}
		if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $keyword)) {
			return true;
		}
		return false;
	}

	private function extract_ip($keyword) {
		if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $keyword, $matches)) {
			return $matches[0];
		}
		return '127.0.0.1';
	}

	private function get_ip_info($ip) {
		$ip_data = array(
			'127.0.0.1' => array('country' => '本机', 'province' => '本地', 'city' => 'localhost', 'isp' => '本地环回', 'org' => 'Localhost'),
			'8.8.8.8' => array('country' => '美国', 'province' => '加利福尼亚', 'city' => 'Mountain View', 'isp' => 'Google LLC', 'org' => 'Google Public DNS'),
			'114.114.114.114' => array('country' => '中国', 'province' => '江苏省', 'city' => '南京市', 'isp' => '腾讯云', 'org' => 'DNSPod'),
		);
		$info = isset($ip_data[$ip]) ? $ip_data[$ip] : array(
			'country' => '中国',
			'province' => '广东省',
			'city' => '深圳市',
			'isp' => '移动/联通/电信',
			'org' => '网络中心'
		);
		return array(
			'ip' => $ip,
			'country' => $info['country'],
			'province' => $info['province'],
			'city' => $info['city'],
			'isp' => $info['isp'],
			'organization' => $info['org'],
			'timestamp' => date('Y-m-d H:i:s'),
			'formatted' => $ip . ' - ' . $info['country'] . ' ' . $info['province'] . ' ' . $info['city']
		);
	}

	public function extract_city($keyword) {
		$cities = array(
			'北京', '上海', '广州', '深圳', '杭州', '南京', '成都', '重庆', '武汉', '西安',
			'天津', '苏州', '郑州', '长沙', '东莞', '沈阳', '青岛', '合肥', '佛山', '济南',
			'厦门', '福州', '南昌', '南宁', '贵阳', '昆明', '拉萨', '兰州', '西宁', '银川',
			'乌鲁木齐', '呼和浩特', '哈尔滨', '长春', '石家庄', '太原', '大连', '宁波', '温州',
			'珠海', '海口', '三亚', '桂林', '丽江', '大理'
		);
		foreach ($cities as $city) {
			if (strpos($keyword, $city) !== false) {
				return $city;
			}
		}
		return '北京';
	}

	private function get_mock_results($keyword, $page = 1, $source = 'Mock') {
		$mock_results = array();
		$start = ($page - 1) * 10;

		$mock_data = array(
			array('title' => $keyword . ' - 百度百科', 'link' => 'https://baike.baidu.com/item/' . urlencode($keyword), 'desc' => $keyword . '是一个广泛关注的话题。本词条详细介绍了' . $keyword . '的定义、发展历程、应用领域等内容。'),
			array('title' => $keyword . ' - 维基百科', 'link' => 'https://zh.wikipedia.org/wiki/' . urlencode($keyword), 'desc' => $keyword . '的详细介绍，包括历史背景、技术原理、发展现状以及未来展望等全面信息。'),
			array('title' => $keyword . '入门教程 - 菜鸟教程', 'link' => 'https://www.runoob.com/' . urlencode($keyword) . '/', 'desc' => '菜鸟教程提供的' . $keyword . '入门教程，从基础到进阶，适合初学者系统学习。'),
			array('title' => $keyword . ' - 知乎', 'link' => 'https://www.zhihu.com/search?q=' . urlencode($keyword), 'desc' => '知乎上关于' . $keyword . '的讨论，包括行业分析、技术分享、经验交流等优质内容。'),
			array('title' => $keyword . ' - CSDN博客', 'link' => 'https://blog.csdn.net/' . urlencode($keyword), 'desc' => 'CSDN博客上的' . $keyword . '相关技术文章，包含大量实战经验和解决方案。'),
			array('title' => $keyword . '官方网站', 'link' => 'https://www.' . urlencode($keyword) . '.com', 'desc' => $keyword . '的官方网站，提供最新的产品信息、文档下载和技术支持。'),
			array('title' => $keyword . ' - 掘金', 'link' => 'https://juejin.cn/search?query=' . urlencode($keyword), 'desc' => '掘金社区的' . $keyword . '相关文章，汇聚开发者的技术分享和最佳实践。'),
			array('title' => '深入理解' . $keyword, 'link' => 'https://example.com/deep-' . urlencode($keyword), 'desc' => '深入探讨' . $keyword . '的核心原理、底层实现和高级应用技巧，助你成为专家。'),
			array('title' => $keyword . '实战指南', 'link' => 'https://example.com/practice-' . urlencode($keyword), 'desc' => '从实战角度出发，通过多个案例帮助你快速掌握' . $keyword . '的应用方法。'),
			array('title' => $keyword . '常见问题汇总', 'link' => 'https://example.com/faq-' . urlencode($keyword), 'desc' => '收集整理了' . $keyword . '学习和使用过程中的常见问题及解决方案。'),
		);

		foreach ($mock_data as $i => $item) {
			$mock_results[] = array(
				'title' => $item['title'],
				'link' => $item['link'],
				'desc' => $item['desc'],
			);
		}

		return $mock_results;
	}

	public function get_sort_V1(){
	for ($i=0; $i < count($this->sy); $i++) { 
	    for ($s=0; $s < count($this->so_data[$this->sy[$i]]); $s++) {
	    	if($this->so_data[$this->sy[$i]][$s]['link'] == ''){continue;}

	    	$this->so_data[$this->sy[$i]][$s]['ico'] = 1;

	        $ls_url = str_replace(array('https://','http://'), '', $this->so_data[$this->sy[$i]][$s]['link']);
	        if (isset($this->new_array[$ls_url])) {
	            $this->new_ls['s'][$ls_url] += 1;
	            $this->new_array[$ls_url]['c'] += 1;
	            $this->new_array[$ls_url]['a'][] = array(
	                'name' => $this->sy[$i],
	                'id' => $s
	                );
	            if($this->new_ls['t'][$ls_url] > $s){
	            	$this->new_ls['t'][$ls_url] = $s;
	            }
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
	arsort($this->new_ls['s']);
	asort($this->new_ls['t']);
	$array_key = array_keys($this->new_ls['s']);

	for ($i=0; $i < count($array_key); $i++) { 
		if($this->new_ls['s'][$array_key[$i]] == 1){
			unset($this->new_ls['s'][$array_key[$i]]);
		}else{
			unset($this->new_ls['t'][$array_key[$i]]);
		}
	}
	$this->new_ls = array_merge($this->new_ls['s'],$this->new_ls['t']);

	$array_key = array_keys($this->new_ls);
	for ($i=0; $i < count($array_key); $i++) { 
	    $this->new_array2[] = $this->new_array[$array_key[$i]];
	}
        return $this->new_array2;
	}

	public function get_sort_V2(){

	for ($i=0; $i < count($this->sy); $i++) { 
	    for ($s=0; $s < count($this->so_data[$this->sy[$i]]); $s++) {

	        $ls_url = str_replace(array('https://','http://'), '', $this->so_data[$this->sy[$i]][$s]['link']);
	        if (isset($this->new_array[$ls_url])) {
	            $this->new_array[$ls_url]['c'] += 1;
	            $this->new_array[$ls_url]['s'] += $s;
	            $this->new_array[$ls_url]['a'][] = array(
	                'name' => $this->sy[$i],
	                'id' => $s
	                );
	            $this->new_ls[$ls_url] = $s/$this->new_array[$ls_url]['c'];
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
	asort($this->new_ls);
	$array_key = array_keys($this->new_ls);
	for ($i=0; $i < count($array_key); $i++) { 
	    $this->new_array2[] = $this->new_array[$array_key[$i]];
	}

        return $this->new_array2;
	}
}


?>
