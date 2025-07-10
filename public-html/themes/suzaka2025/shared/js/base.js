//userAgent
var ua = navigator.userAgent;

//userAgent デバイス判定
var _ua = (function(u){
  return {
    isTablet:(u.indexOf("windows") != -1 && u.indexOf("touch") != -1 && u.indexOf("tablet pc") == -1)
      || u.indexOf("ipad") != -1
      || (u.indexOf("android") != -1 && u.indexOf("mobile") == -1)
      || (u.indexOf("firefox") != -1 && u.indexOf("tablet") != -1)
      || u.indexOf("kindle") != -1
      || u.indexOf("silk") != -1
      || u.indexOf("playbook") != -1,
    isMobile:(u.indexOf("windows") != -1 && u.indexOf("phone") != -1)
      || u.indexOf("iphone") != -1
      || u.indexOf("ipod") != -1
      || (u.indexOf("android") != -1 && u.indexOf("mobile") != -1)
      || (u.indexOf("firefox") != -1 && u.indexOf("mobile") != -1)
      || u.indexOf("blackberry") != -1
  }
})(window.navigator.userAgent.toLowerCase());

//IE・Edge判定
// var isEdge = false;
// var userAgent = window.navigator.userAgent.toLowerCase();
// if( userAgent.match(/(msie|MSIE)/) || userAgent.match(/(T|t)rident/) ) {
//   var isIE = true;
//   var ieVersion = userAgent.match(/((msie|MSIE)\s|rv:)([\d\.]+)/)[3];
//   ieVersion = parseInt(ieVersion);
// } else {
//   var isIE = false;
//   if(userAgent.indexOf("edge" )!= -1){
//     isEdge = true;
//   }
// }


(function($){
  $(function(){
    //スマホメニュー
    spmenu();

    /* iOS リンクのダブルタップバグ */
    if(/iPhone|iPad|iPod/.test(ua)) {
      $('body').addClass("_ios");
    }

    /* PCのとき 電話番号 無効 */
    if(!_ua.isMobile){
      $('.-telink').on('click', function(){
        return false;
      });
    }

    /* スムーズスクロール */
    if(location.href.indexOf("file")==0){
      var url = "file://" + location.hostname + location.pathname + location.search;
    }else if(location.href.indexOf("https")==0){
      var url = "https://" + location.hostname + location.pathname + location.search;
    }else{
      var url = "http://" + location.hostname + location.pathname + location.search;
    }
    $("a[href*=#],area[href*=#]").on('click', function() {
      let delayTime = 50;
      const this_cont = this;
      if($('.c-menuBtn').hasClass('-open')) delayTime = 500;
      setTimeout(function() {
        var id = this_cont.href.substring(this_cont.href.indexOf('#'),this_cont.href.length);
        var hrefUrl = this_cont.href.split(id);
        if(String(hrefUrl[0]) == String(url)) {
          var hash = this_cont.hash;
          if(!hash || hash == "#") {
            return false;
          }else if(id == '#inbox') {
            $('html, body').animate({scrollTop: 0}, 1000, "easeOutQuart");
            return false;
          }else if(id != "#"){
            $('html, body').animate({scrollTop: $(hash).offset().top - $('#site-header').innerHeight()}, 1000, "easeOutQuart");
            return false;
          }
        }
      },delayTime);
    });

    //header mv以下で固定
    // $(window).on('scroll', function(){
    //   const scrollTop = $(window).scrollTop();
    //   const mvHeight = $('.l-main-visual').innerHeight();
    //   if(mvHeight < scrollTop){
    //     $('.l-header').removeClass('-static');
    //   } else {
    //     $('.l-header').addClass('-static');
    //   }
    // });

    // ScrollTrigger
    var triggerOptions = { // option
      once: true,
      offset: {
        viewport: { y: 0.05 }
      },
      toggle: {
        class: { in: '-visible', out: '-invisible' },
        callback: {
          in: function(){

          }
        }
      }
    };
    // 起動
    var trigger = new ScrollTrigger.default();
    trigger.add('[data-trigger]', triggerOptions);
  });

  //スマホメニュー
  let menuState = false;
  let menuPos;

  function spmenu() {
    let $menu = $('#sp-nav'),
        $menuBtn = $('#js-menuButton');

    // open
    $menuBtn.on('click', function(){
      // $menu.stop().fadeToggle(300);
      $menu.toggleClass("-open");
      $('#site-header').toggleClass("-open");
      $(this).toggleClass("-open");
      if(menuState == false) { // スクロールを無効
        menuPos = $(window).scrollTop();
        $('body').addClass('-fixed').css({'top': -menuPos});
        menuState = true;
      } else {
        $('body').removeClass('-fixed').css({'top': 0});
        window.scrollTo(0, menuPos );
        menuState = false;
      }
      $("a[href*=#],area[href*=#]").on('click', function() {
        $menu.removeClass("-open");
        $('#site-header').removeClass("-open");
        $menuBtn.removeClass("-open");
        $('body').removeClass('-fixed').css({'top': 0});
        menuState = false;
        return false;
      });
    });

    $('#sp-nav-close').on('click', function() {
      setTimeout(function () {
        // $menu.stop().fadeOut(300);
        $menu.removeClass("-open");
        $('#site-header').removeClass("-open");
        $menuBtn.removeClass('-open');
        $('body').removeClass('-fixed').css({'top': 0});
        window.scrollTo(0, menuPos );
        menuState = false;
      }, 100);
    });

    $(window).on('resize', function () {
      let windowW = $(window).width();
      if($menu.length && windowW>=1160 && menuState!=false) {
        // $menu.hide();
        $menu.removeClass("-open");
        $('#site-header').removeClass("-open");
        $menuBtn.removeClass("-open");
        $('body').removeClass('-fixed').css({'top': 0});
        window.scrollTo(0,menuPos);
        menuState = false;
      }
    });
  }
  // on Load event
  $(window).on('load', function(){
    //checkWindowSize();
  });

})(jQuery);

document.addEventListener('DOMContentLoaded', function(){
  new ScrollHint('.js-scrollable', {
    applyToParents: true,
    remainingTime: 3000,
    i18n: {
      scrollable: 'スクロールできます'
    }
  });
});