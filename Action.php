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
        $api='https://api.douban.com/v2/book/user/'.$UserID.'/collections?count=100';
        return json_decode(file_get_contents($api));
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
            $raw=file_get_contents($api);
            if($raw==null || $raw=="") break;
            $doc = new \HtmlParser\ParserDom($raw);
            $itemArray = $doc->find("div.item");
            foreach ($itemArray as $v) {
                $t = $v->find("li.title", 0);
                $movie_name = str_replace(array(" ", "　", "\t", "\n", "\r"),
                                          array("", "", "", "", ""),$t->getPlainText());
                $movie_img  = $v->find("div.pic a img", 0)->getAttr("src");
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
    public static function updateBookCacheAndReturn($UserID,$PageSize,$From,$ValidTimeSpan){
        if(!$UserID) return json_encode(array());
        $expired = self::__isCacheExpired(__DIR__.'/cache/book.json',$ValidTimeSpan);
        if($expired!=0){
            $raw=self::__getBookRawData($UserID);
            $data=array();
            foreach($raw->collections as $value){
                if($value->status!='read') continue; 
                $item=array("img"=>str_replace("/view/subject/m/public/","/lpic/",$value->book->image),
                "title"=>$value->book->title,
                "rating"=>$value->book->rating->average,
                "author"=>$value->book->author[0],
                "link"=>$value->book->alt,
                "summary"=>$value->book->summary);
                array_push($data,$item);
            }
            $file=fopen(__DIR__.'/cache/book.json',"w");
            fwrite($file,json_encode(array('time'=>time(),'data'=>$data)));
            fclose($file);
            return self::updateBookCacheAndReturn($UserID,$PageSize,$From,$ValidTimeSpan);
        }
        else{
            $data=json_decode(file_get_contents(__DIR__.'/cache/book.json'))->data;
            $total=count($data);
            //返回从 from 开始的 10 条记录
            if($From<0 || $From>$total-1) echo json_encode(array());
            else{
                $end=min($From+10,$total);
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
            //返回从 from 开始的 10 条记录
            if($From<0 || $From>$total-1) echo json_encode(array());
            else{
                $end=min($From+10,$total);
                $out=array();
                for ($index=$From; $index<$end; $index++) {
                    array_push($out,$data[$index]);
                }
                return json_encode($out);
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
        $From=$_GET['from'];
        if($_GET['type']=='book'){
            header("Content-type: application/json");
            echo DoubanAPI::updateBookCacheAndReturn($UserID,$PageSize,$From,$ValidTimeSpan);
        }elseif($_GET['type']=='movie'){
            header("Content-type: application/json");
            echo DoubanAPI::updateMovieCacheAndReturn($UserID,$PageSize,$From,$ValidTimeSpan);
        }elseif($_GET['type']=='singlebook'){
            header("Content-type: application/json");
            echo file_get_contents('https://api.douban.com/v2/book/'.$_GET['id']);
        }elseif($_GET['type']=='singlemovie'){
            header("Content-type: application/json");
            echo file_get_contents('https://api.douban.com/v2/movie/subject/'.$_GET['id']);
        }else{
            echo json_encode(array());
        }
    }
}
?>