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

<?php if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
?>

<?php
require 'ParserDom.php';

function curl_file_get_contents($_url)
{
    $ch = curl_init();

    $cookie = 'bid=Km3ZGpkEE00; ap_v=0,6.0; _pk_ses.100001.3ac3=*; __utma=30149280.1672442383.1554254872.1554254872.1554254872.1; __utmc=30149280; __utmz=30149280.1554254872.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none); __utmt_douban=1; __utma=81379588.1887771065.1554254872.1554254872.1554254872.1; __utmc=81379588; __utmz=81379588.1554254872.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none); __utmt=1; ll="108288"; _pk_id.100001.3ac3=88bbbc1a1f571a42.1554254872.1.1554254939.1554254872.; __utmb=30149280.7.10.1554254872; __utmb=81379588.7.10.1554254872';
    curl_setopt($ch, CURLOPT_URL, $_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36');

    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
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
    private static function __getBookRawData($UserID)
    {
        $api = 'https://api.douban.com/v2/book/user/' . $UserID . '/collections?apikey=0b2bdeda43b5688921839c8ecb20399b&count=100';
        return json_decode(curl_file_get_contents($api), true);
    }

    /**
     * 从豆瓣网页解析影单数据
     *
     * @access  private
     * @param   string    $UserID     豆瓣ID
     * @return  array     返回格式化 array
     */
    private static function __getMovieRawData($UserID)
    {
        $api = 'https://movie.douban.com/people/' . $UserID . '/collect';
        $data = array();
        while ($api != null) {
            $raw = curl_file_get_contents($api);
            if ($raw == null || $raw == "") {
                break;
            }

            $doc = new \HtmlParser\ParserDom($raw);
            $itemArray = $doc->find("div.item");
            foreach ($itemArray as $v) {
                $t = $v->find("li.title", 0);
                $movie_name = str_replace(array(" ", "　", "\t", "\n", "\r"),
                    array("", "", "", "", ""), $t->getPlainText());
                $movie_img = $v->find("div.pic a img", 0)->getAttr("src");

                // 使用 wp 接口解决防盗链
                $movie_img = 'https://i0.wp.com/' . str_replace(array('http://', 'https://'), '', $movie_img);

                $movie_url = $t->find("a", 0)->getAttr("href");
                $data[] = array("name" => $movie_name, "img" => $movie_img, "url" => $movie_url);
            }
            $url = $doc->find("span.next a", 0);
            if ($url) {
                $api = "https://movie.douban.com" . $url->getAttr("href");
            } else {
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
    private static function __getSingleRawData($API, $Type)
    {
        $raw = json_decode(curl_file_get_contents($API), true);
        $data = array('title' => $raw['title'], 'rating' => strval($raw['rating']['average']), 'summary' => $raw['summary'], 'url' => $raw['alt']);
        if ($Type == 'book') {
            $data['img'] = str_replace("/view/subject/m/public/", "/lpic/", $raw['image']);
            $data['meta'] = $raw['author'][0] . ' / ' . $raw['pubdate'] . ' / ' . $raw['publisher'];
        } else {
            $data['img'] = str_replace("webp", "jpg", $raw['images']['medium']);
            $meta = '[导演]' . $raw['directors'][0]['name'] . ' / ' . $raw['year'];
            foreach ($raw['casts'] as $cast) {
                $meta .= ' / ' . $cast['name'];
            }
            $data['meta'] = $meta;
        }
        $data['img'] = 'https://i0.wp.com/' . str_replace(array('http://', 'https://'), '', $data['img']);
        return $data;
    }

    /**
     * 检查缓存是否过期
     *
     * @access  private
     * @param   string    $FilePath           缓存路径
     * @param   int       $ValidTimeSpan      有效时间，Unix 时间戳，s
     * @return  int       正常数据: 未过期; 1:已过期; -1：无缓存或缓存无效
     */
    private static function __isCacheExpired($FilePath, $ValidTimeSpan)
    {
        if (!file_exists($FilePath)) {
            return -1;
        }

        $content = json_decode(file_get_contents($FilePath), true);
        if (!array_key_exists('time', $content) || $content['time'] < 1) {
            return -1;
        }

        if (time() - $content['time'] > $ValidTimeSpan) {
            return 1;
        }

        return $content;
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
    public static function updateBookCacheAndReturn($UserID, $PageSize, $From, $ValidTimeSpan, $status)
    {
        if (!$UserID) {
            return json_encode(array());
        }

        $cache = self::__isCacheExpired(__DIR__ . '/cache/book.json', $ValidTimeSpan);

        if ($cache == -1 || $cache == 1) {
            // 缓存无效或者过期，重新请求，重新写入
            $raw = self::__getBookRawData($UserID);
            $data_read = array();
            $data_reading = array();
            $data_wish = array();
            foreach ($raw['collections'] as $value) {
                $item = array("img" => str_replace("/view/subject/m/public/", "/lpic/", $value['book']['image']),
                    "title" => $value['book']['title'],
                    "rating" => $value['book']['rating']['average'],
                    "author" => $value['book']['author'][0],
                    "link" => $value['book']['alt'],
                    "summary" => $value['book']['summary']);

                $item['img'] = 'https://i0.wp.com/' . str_replace(array('http://', 'https://'), '', $item['img']);

                if ($value['status'] == 'read') {
                    array_push($data_read, $item);
                } elseif ($value['status'] == 'reading') {
                    array_push($data_reading, $item);
                } elseif ($value['status'] == 'wish') {
                    array_push($data_wish, $item);
                }
            }

            $cache = array('time' => time(),
                'data' => array('read' => $data_read, 'reading' => $data_reading, 'wish' => $data_wish));

            file_put_contents(__DIR__ . '/cache/book.json', json_encode($cache));
        }

        $data = $cache['data'][$status];
        $total = count($data);

        if ($From < 0 || $From > $total - 1) {
            echo json_encode(array());
        } else {
            $end = min($From + $PageSize, $total);
            $out = array();
            for ($index = $From; $index < $end; $index++) {
                array_push($out, $data[$index]);
            }
            return json_encode($out);
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
    public static function updateMovieCacheAndReturn($UserID, $PageSize, $From, $ValidTimeSpan)
    {
        if (!$UserID) {
            return json_encode(array());
        }

        $cache = self::__isCacheExpired(__DIR__ . '/cache/movie.json', $ValidTimeSpan);

        if ($cache == -1 || $cache == 1) {
            // 缓存无效或者过期，重新请求，重新写入
            $raw = self::__getMovieRawData($UserID);
            $cache = array('time' => time(), 'data' => $raw);
            file_put_contents(__DIR__ . '/cache/movie.json', json_encode($cache));
        }

        $data = $cache['data'];
        $total = count($data);
        if ($From < 0 || $From > $total - 1) {
            echo json_encode(array());
        } else {
            $end = min($From + $PageSize, $total);
            $out = array();
            for ($index = $From; $index < $end; $index++) {
                array_push($out, $data[$index]);
            }
            return json_encode($out);
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
    public static function updateSingleCacheAndReturn($ID, $Type, $ValidTimeSpan)
    {
        if (!$ID || !$Type) {
            return json_encode(array());
        }

        $cache = array();
        $FilePath = __DIR__ . '/cache/single.json';
        if (file_exists($FilePath)) {
            $cache = json_decode(file_get_contents($FilePath), true);
        }

        $needUpdate = false;

        // 新建字段
        if(!array_key_exists($Type, $cache)) {
            $cache[$Type] = array();
            $needUpdate = true;
        }

        // 新建字段
        if (!array_key_exists($ID, $cache[$Type])) {
            $cache[$Type][$ID] = array('time' => 1, 'data' => array());
            $needUpdate = true;
        }

        // 刷新数据
        if (time() - $cache[$Type][$ID]['time'] > $ValidTimeSpan) {
            $needUpdate = true;
            if ($Type == 'book') {
                $cache[$Type][$ID]['data'] = self::__getSingleRawData('https://api.douban.com/v2/book/' . $ID . '?apikey=0b2bdeda43b5688921839c8ecb20399b', 'book');
            } else {
                $cache[$Type][$ID]['data'] = self::__getSingleRawData('https://api.douban.com/v2/movie/subject/' . $ID . '?apikey=0b2bdeda43b5688921839c8ecb20399b', 'movie');
            }
        }

        if($needUpdate)
            file_put_contents($FilePath, json_encode($cache));
        
        return json_encode($cache[$Type][$ID]['data']);
    }
}

class DoubanBoard_Action extends Widget_Abstract_Contents implements Widget_Interface_Do
{

    /**
     * 解析 URL，返回对应数据
     *
     * @access  public
     */
    public function action()
    {
        $options = Helper::options()->plugin('DoubanBoard');
        $UserID = $options->ID;
        $PageSize = $options->PageSize ? $options->PageSize : 10;
        $ValidTimeSpan = $options->ValidTimeSpan ? $options->ValidTimeSpan : 60 * 60 * 24;
        $From = 0;
        if (array_key_exists('from', $_GET)) {
            $From = $_GET['from'];
        }
        if ($_GET['type'] == 'book') {
            header("Content-type: application/json");
            $status = $_GET['status'] ? $_GET['status'] : 'read';
            echo DoubanAPI::updateBookCacheAndReturn($UserID, $PageSize, $From, $ValidTimeSpan, $status);
        } elseif ($_GET['type'] == 'movie') {
            header("Content-type: application/json");
            echo DoubanAPI::updateMovieCacheAndReturn($UserID, $PageSize, $From, $ValidTimeSpan);
        } elseif ($_GET['type'] == 'singlebook') {
            header("Content-type: application/json");
            echo DoubanAPI::updateSingleCacheAndReturn($_GET['id'], 'book', $ValidTimeSpan);
        } elseif ($_GET['type'] == 'singlemovie') {
            header("Content-type: application/json");
            echo DoubanAPI::updateSingleCacheAndReturn($_GET['id'], 'movie', $ValidTimeSpan);
        } else {
            echo json_encode(array());
        }
    }
}
?>