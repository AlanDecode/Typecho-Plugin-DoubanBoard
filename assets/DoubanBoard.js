// RAW.js
// Author: 熊猫小A
// Link: https://imalan.cn

console.log(`%c DoubanBoard 0.1 %c https://blog.imalan.cn/archives/168/`, `color: #fadfa3; background: #23b7e5; padding:5px 0;`, `background: #1c2b36; padding:5px 0;`);


var curBooks=0;
var curMovies=0;
DoubanBoard = {
    initDoubanBoard : function(){
        $("#douban-book-list").after(`<div class="douban-loadmore" id="loadMoreBooks" onclick="DoubanBoard.loadBooks();">加载更多</div>`);
        $("#douban-movie-list").after(`<div class="douban-loadmore" id="loadMoreMovies" onclick="DoubanBoard.loadMovies();">加载更多</div>`);
        curBooks=0;
        curMovies=0;
        DoubanBoard.loadBooks();
        DoubanBoard.loadMovies();
        DoubanBoard.loadSingleBoard();
    },

    loadSingleBoard : function(){
        if($(".douban-single").length<1) return;
        $.each($(".douban-single"),function(i,item){
            if($(item).attr("data-type")=="book"){
                var api="/index.php/DoubanBoard?type=singlebook&id="+$(item).attr("data-id");
                $.getJSON(api,function(result){
                    var myrating=parseFloat($(item).attr("data-rating"));
                    var title=result.title;
                    var rating=parseFloat(result.rating.average);
                    var time=result.pubdate;
                    var author=result.author[0];
                    var publisher=result.publisher;
                    var img=result.image.replace(/\/view\/subject\/m\/public\//,"/lpic/");
                    var url=result.alt;
                    var summary=result.summary;
                    var posi_rating=30*(5-Math.floor(rating/2));
                    if((rating-posi_rating*2)>=0.5) posi_rating=posi_rating-15;
                    var posi_myrating=30*(5-Math.floor(myrating/2));
                    if((myrating-Math.floor(myrating))>=0.5) posi_myrating=posi_myrating-15;
                    var html=`<div class="douban-single-img" style="background-image:url(`+img+`)"></div>
                            <div class="douban-single-info">
                                <a target="_blank" class="douban-single-title" href="`+url+`">`+title+`</a><br>
                                <span class="douban-single-meta">`+author+` / `+time+` / `+publisher+`</span><br>
                                豆瓣评分：<span class="douban-single-rating" style="background-position:0 -`+posi_rating+`px"></span> `+rating+`<br>
                                我的评分：<span class="douban-single-rating" style="background-position:0 -`+posi_myrating+`px"></span> `+myrating+`<br>
                                <span class="douban-single-summary">`+summary+`</span>
                            </div>`;
                    $(item).html(html);
                });
            }else{
                var api="/index.php/DoubanBoard?type=singlemovie&id="+$(item).attr("data-id");
                $.getJSON(api,function(result){
                    var myrating=parseFloat($(item).attr("data-rating"));
                    var title=result.title;
                    var rating=parseFloat(result.rating.average);
                    var time=result.year;
                    var author="[导演]"+result.directors[0].name;
                    var casts="";
                    $.each(result.casts,function(i,item){
                        casts = casts + " / " + item.name;
                    });
                    var img=result.images.medium.replace(/webp/,"jpg");
                    var url=result.alt;
                    var summary=result.summary;
                    var posi_rating=30*(5-Math.floor(rating/2));
                    if((rating-posi_rating*2)>=0.5) posi_rating=posi_rating-15;
                    var posi_myrating=30*(5-Math.floor(myrating/2));
                    if((myrating-Math.floor(myrating))>=0.5) posi_myrating=posi_myrating-15;
                    var html=`<div class="douban-single-img" style="background-image:url(`+img+`)"></div>
                            <div class="douban-single-info">
                                <a target="_blank" class="douban-single-title" href="`+url+`">`+title+`</a><br>
                                <span class="douban-single-meta">` + author +" / "+ time + casts+`</span><br>
                                豆瓣评分：<span class="douban-single-rating" style="background-position:0 -`+posi_rating+`px"></span> `+rating+`<br>
                                我的评分：<span class="douban-single-rating" style="background-position:0 -`+posi_myrating+`px"></span> `+myrating+`<br>
                                <span class="douban-single-summary">`+summary+`</span>
                            </div>`;
                    $(item).html(html);
                });
            }
        })
    },

    loadBooks : function(){
        if($("#douban-book-list").length < 1) return;
        $("#loadMoreBooks").html("加载中...");
        $.getJSON("/index.php/DoubanBoard?type=book&from="+String(curBooks),function(result){
            $("#loadMoreBooks").html("加载更多");
            if(result.length<10){
                $("#loadMoreBooks").html("没有啦");
            }
            $.each(result,function(i,item){
                var html=`<div id="doubanboard-book-item-`+String(curBooks)+`" class="doubanboard-item">
                            <div class="doubanboard-thumb" style="background-image:url(`+item.img+`)"></div>
                            <div title="点击显示详情" class="doubanboard-title">`+item.title+`</div>
                            <div class="doubanboard-info">
                                <p class="doubanboard-info-basic">
                                书名：`+item.title+`<br>
                                评分：`+item.rating+`<br>
                                作者：`+item.author+`<br>
                                链接：<a target="_blank" href="`+item.link+`">豆瓣阅读</a><br>
                                简介：<br>
                                </p>
                                <p class="doubanboard-info-summary">
                                    `+item.summary+`
                                </p>
                            </div>
                        </div>`;
                $("#douban-book-list").append(html);
                curBooks++;
            });
        });    
    },

    loadMovies : function(){
        if($("#douban-movie-list").length < 1) return;
        $("#loadMoreMovies").html("加载中...");
        $.getJSON("/index.php/DoubanBoard?type=movie&from="+String(curMovies),function(result){
            $("#loadMoreMovies").html("加载更多");
            if(result.length<10){
                $("#loadMoreMovies").html("没有啦");
            }
            $.each(result,function(i,item){
                var html=`<div id="doubanboard-movie-item-`+String(curMovies)+`" class="doubanboard-item">
                            <div class="doubanboard-thumb" style="background-image:url(`+item.img+`)"></div>
                            <div class="doubanboard-title"><a href="`+item.url+`" target="_blank">`+item.name+`</a></div>
                        </div>`;
                $("#douban-movie-list").append(html);
                curMovies++;
            });
        });    
    }
}

$(document).ready(function(){
    DoubanBoard.initDoubanBoard();
})

$(document).on('pjax:end', function() {
    DoubanBoard.initDoubanBoard();
})

$(document).click(function(e){
    var target=e.target;
    console.log(target);
    $(".doubanboard-item").removeClass("doubanboard-info-show");
    $(".doubanboard-item").each(function(){
        if($(target).parent()[0]==$(this)[0] || $(target)==$(this)[0]){
            $(this).addClass("doubanboard-info-show");
        }
    })
})