<?php
/** 
* Action.php
* 
* 获取、更新缓存，返回书单、影单
* 
* @author      熊猫小A | AlanDecode
* @version     0.1
*/ 
?>

<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;?>

<?php
require('ParserDom.php');

function curl_file_get_contents($_url){
    $myCurl = curl_init($_url);
    //不验证证书
    curl_setopt($myCurl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($myCurl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($myCurl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($myCurl, CURLOPT_REFERER, 'https://www.douban.com');
    curl_setopt($myCurl,  CURLOPT_HEADER, false);
    //获取
    $content = curl_exec($myCurl);
    //关闭
    curl_close($myCurl);
    return $content;
}

class DoubanAPI
{   
    /**
     * 从豆瓣接口获取书单数据
     * 
     * @access  private
     * @param   string    $UserID     豆瓣ID
     * @return  array     返回 JSON 解码后的 array
     */
    private static function __getBookRawData($UserID){
        $api='https://api.douban.com/v2/book/user/'.$UserID.'/collections?apikey=0b2bdeda43b5688921839c8ecb20399b&count=100';
        return json_decode(curl_file_get_contents($api));
    }

    /**
     * 从豆瓣网页解析影单数据
     * 
     * @access  private
     * @param   string    $UserID     豆瓣ID
     * @return  array     返回格式化 array
     */
    private static function __getMovieRawData($UserID){
        $api='https://movie.douban.com/people/'.$UserID.'/collect';
        $data=array();
        while($api!=null){
            $raw=curl_file_get_contents($api);
            if($raw==null || $raw=="") break;
            $doc = new \HtmlParser\ParserDom($raw);
            $itemArray = $doc->find("div.item");
            foreach ($itemArray as $v) {
                $t = $v->find("li.title", 0);
                $movie_name = str_replace(array(" ", "　", "\t", "\n", "\r"),
                                          array("", "", "", "", ""),$t->getPlainText());
                $movie_img  = $v->find("div.pic a img", 0)->getAttr("src");

                // 使用 wp 接口解决防盗链
                $movie_img = 'https://i0.wp.com/'.str_replace(array('http://', 'https://'), '', $movie_img);

                $movie_url  = $t->find("a", 0)->getAttr("href");
                $data[] = array("name" => $movie_name, "img" => $movie_img, "url" => $movie_url);
            }
            $url = $doc->find("span.next a", 0);
            if ($url) {
                $api = "https://movie.douban.com" .$url->getAttr("href");
            }else{
                $api = null;
            }
        }
        return $data;
    }

    /**
     * 从接口解析电影、书籍数据，并返回
     * 
     * @access  private
     * @param   string    $UserID     豆瓣ID
     * @return  array     返回格式化 array
     */
    private static function __getSingleRawData($API,$Type){
        $raw=json_decode(curl_file_get_contents($API));
        $data=array('title'=>$raw->title,'rating'=>strval($raw->rating->average),'summary'=>$raw->summary,'url'=>$raw->alt);
        if($Type=='book'){
            $data['img']=str_replace("/view/subject/m/public/","/lpic/",$raw->image);
            $data['meta']=$raw->author[0].' / '.$raw->pubdate.' / '.$raw->publisher;
        }else{
            $data['img']=str_replace("webp","jpg",$raw->images->medium);
            $meta='[导演]'.$raw->directors[0]->name.' / '.$raw->year;
            foreach ($raw->casts as $cast) {
                $meta .= ' / '.$cast->name;
            }
            $data['meta']=$meta;
        }
        $data['img'] = 'https://i0.wp.com/'.str_replace(array('http://', 'https://'), '', $data['img']);
        return $data;
    }


    /**
     * 检查缓存是否过期
     * 
     * @access  private
     * @param   string    $FilePath           缓存路径
     * @param   int       $ValidTimeSpan      有效时间，Unix 时间戳，s
     * @return  int       0: 未过期; 1:已过期; -1：无缓存或缓存无效
     */
    private static function __isCacheExpired($FilePath,$ValidTimeSpan){
        $file=fopen($FilePath,"r");
        if(!$file) return -1;
        $content=json_decode(fread($file,filesize($FilePath)));
        fclose($file);
        if(!$content->time || $content->time<1) return -1;
        if(time()-$content->time > $ValidTimeSpan) return 1;
        return 0; 
    }

    /**
     * 从本地读取缓存信息，若不存在则创建，若过期则更新。并返回格式化 JSON
     * 
     * @access  public 
     * @param   string    $UserID             豆瓣ID
     * @param   int       $PageSize           分页大小
     * @param   int       $From               开始位置
     * @param   int       $ValidTimeSpan      有效时间，Unix 时间戳，s
     * @return  json      返回格式化书单
     */  
    public static function updateBookCacheAndReturn($UserID,$PageSize,$From,$ValidTimeSpan,$status){
        if(!$UserID) return json_encode(array());
        $expired = self::__isCacheExpired(__DIR__.'/cache/book.json',$ValidTimeSpan);
        if($expired!=0){
            $raw=self::__getBookRawData($UserID);
            $data_read=array();
            $data_reading=array();
            $data_wish=array();
            foreach($raw->collections as $value){
                $item=array("img"=>str_replace("/view/subject/m/public/","/lpic/",$value->book->image),
                "title"=>$value->book->title,
                "rating"=>$value->book->rating->average,
                "author"=>$value->book->author[0],
                "link"=>$value->book->alt,
                "summary"=>$value->book->summary);

                $item['img'] = 'https://i0.wp.com/'.str_replace(array('http://', 'https://'), '', $item['img']);
                
                if($value->status=='read'){
                    array_push($data_read,$item);
                }elseif($value->status=='reading'){
                    array_push($data_reading,$item);
                }elseif($value->status=='wish'){
                    array_push($data_wish,$item);
                }
            }
            $file=fopen(__DIR__.'/cache/book.json',"w");
            fwrite($file,json_encode(array('time'=>time(),'data'=>array('read'=>$data_read,'reading'=>$data_reading,'wish'=>$data_wish))));
            fclose($file);
            return self::updateBookCacheAndReturn($UserID,$PageSize,$From,$ValidTimeSpan,$status);
        }
        else{
            $data=json_decode(file_get_contents(__DIR__.'/cache/book.json'))->data->$status;
            $total=count($data);
            if($From<0 || $From>$total-1) echo json_encode(array());
            else{
                $end=min($From+$PageSize,$total);
                $out=array();
                for ($index=$From; $index<$end; $index++) {
                    array_push($out,$data[$index]);
                }
                return json_encode($out);
            }
        }
    }


    /**
     * 从本地读取缓存信息，若不存在则创建，若过期则更新。并返回格式化 JSON
     * 
     * @access  public 
     * @param   string    $UserID             豆瓣ID
     * @param   int       $PageSize           分页大小
     * @param   int       $From               开始位置
     * @param   int       $ValidTimeSpan      有效时间，Unix 时间戳，s
     * @return  json      返回格式化影单
     */  
    public static function updateMovieCacheAndReturn($UserID,$PageSize,$From,$ValidTimeSpan){
        if(!$UserID) return json_encode(array());
        $expired = self::__isCacheExpired(__DIR__.'/cache/movie.json',$ValidTimeSpan);
        if($expired!=0){
            $data=self::__getMovieRawData($UserID);
            $file=fopen(__DIR__.'/cache/movie.json',"w");
            fwrite($file,json_encode(array('time'=>time(),'data'=>$data)));
            fclose($file);
            return self::updateMovieCacheAndReturn($UserID,$PageSize,$From,$ValidTimeSpan);
        }else{
            $data=json_decode(file_get_contents(__DIR__.'/cache/movie.json'))->data;
            $total=count($data);
            if($From<0 || $From>$total-1) echo json_encode(array());
            else{
                $end=min($From+$PageSize,$total);
                $out=array();
                for ($index=$From; $index<$end; $index++) {
                    array_push($out,$data[$index]);
                }
                return json_encode($out);
            }
        }
    }

     /**
     * 从本地读取缓存信息，若不存在则创建，若过期则更新（单条）。并返回格式化 JSON
     * 
     * @access  public 
     * @param   string    $ID                 书籍或者电影 ID
     * @param   int       $Type               指明是书籍还是电影
     * @param   int       $ValidTimeSpan      有效时间，Unix 时间戳，s
     * @return  json      返回格式化数据
     */  
    public static function updateSingleCacheAndReturn($ID,$Type,$ValidTimeSpan){
        if(!$ID || !$Type) return json_encode(array());
        $FilePath=__DIR__.'/cache/single.json';
        if(!is_file($FilePath)){
            $file=fopen($FilePath,"w+");
            fwrite($file,json_encode(array('book'=>array(),'movie'=>array())));
            fclose($file);
            return updateSingleCacheAndReturn($ID,$Type,$ValidTimeSpan);
        }
        $file=fopen($FilePath,"r");
        $data=json_decode(fread($file,filesize($FilePath)));
        fclose($file);
        if($Type=='book'){
            if($data->book && array_key_exists($ID,(array)$data->book) && time()-$data->book->$ID->time<$ValidTimeSpan){
                return json_encode($data->book->$ID->data);
            }
            else{
                $content=self::__getSingleRawData('https://api.douban.com/v2/book/'.$ID.'?apikey=0b2bdeda43b5688921839c8ecb20399b','book');
                $data->book=(array)$data->book;
                $data->book[$ID]=array('time'=>time(),'data'=>$content);
                $file=fopen($FilePath,"w");
                fwrite($file,json_encode($data));
                fclose($file);
                return json_encode($content);
            }
        }else{
            if($data->movie && array_key_exists($ID,(array)$data->movie) && time()-$data->movie->$ID->time<$ValidTimeSpan){
                return json_encode($data->movie->$ID->data);
            }
            else{
                $content=self::__getSingleRawData('https://api.douban.com/v2/movie/subject/'.$ID.'?apikey=0b2bdeda43b5688921839c8ecb20399b','movie');
                $data->movie=(array)$data->movie;
                $data->movie[$ID]=array('time'=>time(),'data'=>$content);
                $file=fopen($FilePath,"w");
                fwrite($file,json_encode($data));
                fclose($file);
                return json_encode($content);
            }
        }
    }
}

class DoubanBoard_Action extends Widget_Abstract_Contents implements Widget_Interface_Do {
    
    /**
     * 解析 URL，返回对应数据
     * 
     * @access  public
     */
    public function action(){
        $options = Helper::options()->plugin('DoubanBoard');    
        $UserID=$options->ID;
        $PageSize=$options->PageSize ? $options->PageSize : 10;
        $ValidTimeSpan=$options->ValidTimeSpan ? $options->ValidTimeSpan : 60*60*24;
        $From = 0;
        if(array_key_exists('from', $_GET)) {
            $From = $_GET['from'];
        }
        if($_GET['type']=='book'){
            header("Content-type: application/json");
            $status=$_GET['status'] ? $_GET['status'] : 'read';
            echo DoubanAPI::updateBookCacheAndReturn($UserID,$PageSize,$From,$ValidTimeSpan,$status);
        }elseif($_GET['type']=='movie'){
            header("Content-type: application/json");
            echo DoubanAPI::updateMovieCacheAndReturn($UserID,$PageSize,$From,$ValidTimeSpan);
        }elseif($_GET['type']=='singlebook'){
            header("Content-type: application/json");
            echo DoubanAPI::updateSingleCacheAndReturn($_GET['id'],'book',$ValidTimeSpan);
        }elseif($_GET['type']=='singlemovie'){
            header("Content-type: application/json");
            echo DoubanAPI::updateSingleCacheAndReturn($_GET['id'],'movie',$ValidTimeSpan);
        }elseif($_GET['type']=='forceRefresh'){
            /** 忽略超时 */
            if (function_exists('ignore_user_abort')) {
                ignore_user_abort(true);
            }
            DoubanAPI::updateBookCacheAndReturn($UserID,$PageSize,$From,10,'read');
            DoubanAPI::updateMovieCacheAndReturn($UserID,$PageSize,$From,10);
        }else{
            echo json_encode(array());
        }
    }
}
?>