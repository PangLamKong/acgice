<?php
namespace app\index\controller;

use think\Exception;
use think\Controller;
use think\Url;
use think\Cache;


use app\common\model\Sousuo;//聚合搜索模型
use app\common\model\Keys;//Key模型



//首页版块控制器

class Index  extends Controller
{
    public function _empty($name){
        //空操作返回404
         return _404();
    }
    public function _initialize()
    {

        if(isset($_GET['lang'])){
            cookie('acgice_lang',$_GET['lang'], 7*86400);
        }

    }

    // 首页
    public function index()
    {
        global $app_config;
        $platform = 'pc';
        $q = isset($_GET['q']) ? $_GET['q'] : '';
        $state = isset($_GET['state']) ? intval($_GET['state']) : 0;
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $l = isset($_GET['l']) ? $_GET['l'] : 'web';

        $q = security_string($q);
        $state = security_number($state);

        if($q == ''){
            //首页
            $data = array();
            header("Cache-Control: public, max-age=3600");
            $html_data = [
               'STATIC_PATH' => CSS_URL,
               'platform' => $platform,
               'app_config' => $app_config,
               'data' => $data,
               'l' => $l,
               'key' => '',
               'page' => 1,
            ];
            return view('pc/home',$html_data);
        }

        // 处理搜索
        return $this->do_search($q, $page, $state, $l);
    }

    // 执行搜索
    private function do_search($q, $page, $state, $l)
    {
        global $app_config;

        if($page < 1){
            $page = 1;
        }

        $key = $q;

        $Key = new Keys;
        $key = $Key->Key($key);
        if($Key->get_sl()){
            return $Key->get_sl();
        }

        $sy = array();
        $cache_time = false;

        $platform = 'pc';
        $app_config['headers']['User-Agent'] = $app_config['User-Agent']['wz_pc'];

        switch ($l) {
            case 'video':
                $sy[] = 'Bing';
                $cache_time = 3600;
                break;
            default:
                $l='web';
                $sy[] = 'Bing';
                $sy[] = 'Baidu';
                $sy[] = 'DuckDuckGo';
                $sy[] = 'Sogou';
                $sy[] = 'So';
                break;
        }

        $data = array();
        if($state !== 0){
            $Sosuo = new Sousuo;
            $Sosuo->Initialize($sy,$key,$app_config['headers'],$page);
            $Sosuo->get_sosuo($platform,$cache_time);
            $data = $Sosuo->get_sort_V2();
        }

        $html_data = [
           'app_config' => $app_config,
           'STATIC_PATH' => CSS_URL,
           'data' => $data,
           'key' => $key,
           'page' => $page,
           'l' => $l,
        ];
        if($state !== 0){
            return view('pc/data_s',$html_data);
        }else{
            return view('pc/data',$html_data);
        }
    }

    // 优雅的 ( ﹁ ﹁ ) ~→ 弱鸡代码
    public function index_function($q='',$page=1,$state=0,$l='web')
    {
        global $app_config;
        $platform = is_platform();
        $platform = 'pc';//默认全PC
        $q = security_string($q);
        $state = security_number($state);

        if($q == ''){
            //首页
$data = array();

            header("Cache-Control: public, max-age=3600");//可以让页面进行缓存 全部缓存 缓存10分钟
            $html_data = [
               'STATIC_PATH' => CSS_URL,//CSS目录.
               'platform' => $platform,
               'app_config' => $app_config,
               'data' => $data,
               'l' => $l
            ];
            return view('pc/home',$html_data);
        }

        $page = security_number($page);
        if($page < 1){
            $page = 1;
        }

        $key = $q;//搜索词

        $Key = new Keys;//Keys 命令序列
        $key = $Key->Key($key);
        if($Key->get_sl()){
            return $Key->get_sl();
        }

        //Cache::clear(); //关闭缓存

        $sy = array();//搜索渠道
        $cache_time = false;//缓存时间

        if($platform == 'pc'){
            $app_config['headers']['User-Agent'] = $app_config['User-Agent']['wz_pc'];//PC伪装访问蜘蛛

        }else{
            $app_config['headers']['User-Agent'] = $app_config['User-Agent']['wz_wap'];//WAP伪装访问蜘蛛
        }


switch ($l) {
    case 'video':
        //视频搜索
        $sy[] = 'Bing';
        $cache_time = 3600;
        break;
    default:
        # 默认就是WEB 网页 缓存用默认的
        $l='web';
        $platform = 'pc';
        $sy[] = 'Bing';
        $sy[] = 'Baidu';
        $sy[] = 'DuckDuckGo';
        $sy[] = 'Sogou';
        $sy[] = 'So';
        break;
}

        
        $data = array();
        $tool_data = null;
if($state !== 0){
        $Sosuo = new Sousuo;

        $tool_data = $Sosuo->get_tool($key);
        if ($tool_data) {
            $data = array();
        } else {
            $Sosuo->Initialize($sy,$key,$app_config['headers'],$page);
            $Sosuo->get_sosuo($platform,$cache_time);
            $data = $Sosuo->get_sort_V2();
        }
}


        $html_data = [
           'app_config' => $app_config,
           'STATIC_PATH' => CSS_URL,
           'data' => $data,
           'key' => $key,
           'page' => $page,
           'l' => $l,
           'tool' => $tool_data,
        ];
        if($state !== 0){
            return view('pc/data_s',$html_data);
        }else{
            return view('pc/data',$html_data);
        }
    }
}

