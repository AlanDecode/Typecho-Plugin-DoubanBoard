<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;?>

<?php
/**
 * 在博客上展示你的豆瓣书单与豆瓣影单
 *  
 * 
 * @package DoubanBoard
 * @author 熊猫小A
 * @version 0.3
 * @link https://imalan.cn
 */

define('DoubanBoard_Plugin_VERSION', '0.3');

class DoubanBoard_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Archive')->header = array('DoubanBoard_Plugin', 'header');
        Typecho_Plugin::factory('Widget_Archive')->footer = array('DoubanBoard_Plugin', 'footer');
        Helper::addRoute("route_DoubanBoard","/DoubanBoard","DoubanBoard_Action",'action');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        Helper::removeRoute("route_DoubanBoard");
    }
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form){
        echo '<b>使用方式</b><br>
        已读书单列表：&lt;div data-status=&quot;read&quot; class=&quot;douban-book-list doubanboard-list&quot;&gt;&lt;/div&gt;<br>
        在读书单列表：&lt;div data-status=&quot;reading&quot; class=&quot;douban-book-list doubanboard-list&quot;&gt;&lt;/div&gt;<br>
        想读书单列表：&lt;div data-status=&quot;wish&quot; class=&quot;douban-book-list doubanboard-list&quot;&gt;&lt;/div&gt;<br>
        已看电影列表：&lt;div id=&quot;douban-movie-list&quot; class=&quot;doubanboard-list&quot;&gt;&lt;/div&gt;<br>
        单部电影：&lt;div class=&quot;douban-single&quot; data-type=&quot;movie&quot; data-id=&quot;电影 ID&quot; data-rating=&quot;你的评分&quot;&gt;&lt;/div&gt;<br>
        单部书籍：&lt;div class=&quot;douban-single&quot; data-type=&quot;book&quot; data-id=&quot;书籍 ID&quot; data-rating=&quot;你的评分&quot;&gt;&lt;/div&gt;<br>
        更多介绍：<a href="https://blog.imalan.cn/archives/168/" target="_blank">Typecho-Plugin-DoubanBoard</a>';
        $ID = new Typecho_Widget_Helper_Form_Element_Text('ID', NULL, '', _t('豆瓣 ID'), _t('填写豆瓣ID'));
        $form->addInput($ID);
        $PageSize = new Typecho_Widget_Helper_Form_Element_Text('PageSize', NULL, '', _t('每次加载的数量'), _t('填写每次加载的数量，不填默认为 10。注意：豆瓣限制最多取得 100 条数据。'));
        $form->addInput($PageSize);
        $ValidTimeSpan = new Typecho_Widget_Helper_Form_Element_Text('ValidTimeSpan', NULL, '', _t('缓存过期时间'), _t('填写缓存过期时间，单位秒。默认 24 小时。'));
        $form->addInput($ValidTimeSpan);
        $loadJQ= new Typecho_Widget_Helper_Form_Element_Checkbox('loadJQ',  array('jq'=>_t('配置是否引入 JQuery：勾选则引入不勾选则不引入<br>')),array('jq'), _t('基本设置'));
        $form->addInput($loadJQ);
    }
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
    
    /**
     * 输出头部css
     * 
     * @access public
     * @return void
     */
    public static function header()
    {
        echo '<link rel="stylesheet" href="/usr/plugins/DoubanBoard/assets/DoubanBoard.0.3.css?v='.DoubanBoard_Plugin_VERSION.'" />';
        if (!empty(Helper::options()->plugin('DoubanBoard')->loadJQ) && in_array('jq', Helper::options()->plugin('DoubanBoard')->loadJQ))
        {
            echo '<script src="/usr/plugins/DoubanBoard/assets/jquery.min.js"></script>';
        }
    }  
    /**
     * 在底部输出所需 JS
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function footer()
    {
        echo '<script type="text/javascript" src="/usr/plugins/DoubanBoard/assets/DoubanBoard.0.3.js?v='.DoubanBoard_Plugin_VERSION.'"></script>';
    }
}
?>