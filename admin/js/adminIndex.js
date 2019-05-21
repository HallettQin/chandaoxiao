$(function(){
	//logo点击跳转到首页 
	$(".admin-logo a").on("click",function(){
		var url = $(this).data('url');
		var param = $(this).data('param');
		
		$(".admin-main").addClass("start_home");
		$(".admincj_nav").find(".item").eq(0).show();
		$(".admincj_nav").find(".sub-menu").hide();
		$(".module-menu").find("li").removeClass("active");
		
		openItem(param);
	});
	
	//顶部管理员信息展开
	function adminSetup(){
		var hoverTimer, outTimer;
		$('#admin-manager-btn,.manager-menu,.admincp-map').mouseenter(function(){
			clearTimeout(outTimer);
			hoverTimer = setTimeout(function(){
				$('.manager-menu').show();
				$('#admin-manager-btn i').removeClass().addClass("arrow-close");
			},200);
		});
		
		$('#admin-manager-btn,.manager-menu,.admincp-map').mouseleave(function(){
			clearTimeout(hoverTimer);
			outTimer = setTimeout(function(){
				$('.manager-menu').hide();
				$('#admin-manager-btn i').removeClass().addClass("arrow");
			},100);	
		});
	}
	adminSetup();
	
	function loadEach(){
		$('.admincj_nav').find('div[id^="adminNavTabs_"]').each(function(){
			var $this = $(this);
			
			var name = $this.attr("id").replace("adminNavTabs_","");
			
			$this.find('.item > .tit > a').each(function(i){
				$(this).parent().next().css('top', (-68)*i + 'px');
				$(this).click(function(){
					var type = $(this).parents(".item").data("type");
					if(type == "home"){
						var url = $(this).data('url');
						var param = $(this).data('param');
						
						$(".admin-main").addClass("start_home");
						$(".admincj_nav").find(".item").eq(0).addClass("current").siblings().removeClass("current");
						$(".admincj_nav").find(".item").eq(0).show();
						$(".module-menu").find("li").removeClass("active");
						$this.find('.sub-menu').hide();
						openItem(param,1);
					}else{
						var url = '';
						$this.find('.sub-menu').hide();
						$this.find('.item').removeClass('current');
						if(name == "menushopping"){
							//商品 默认三级分类链接到第二个 商品列表
							var param = $(this).parent().next().find('a:first').data('param');
							var data_str = param.split('|');
							if($(this).parents('.item').index() == 0 && data_str[1] == "001_goods_setting"){
								$(this).parents('.item').eq(1).addClass('current');
								$(this).parent().next().find('a').eq(1).click();
								url = $(this).parent().next().find('a').eq(1).data('url');
							}else{
								$(this).parents('.item:first').addClass('current');
								$(this).parent().next().find('a:first').click();
								url = $(this).parent().next().find('a:first').data('url');
							}							
						}else{
							$(this).parents('.item:first').addClass('current');
							$(this).parent().next().find('a:first').click();
							url = $(this).parent().next().find('a:first').data('url');
						}
						$(".admin-main").removeClass("start_home");
						//loadUrl(url);
					}
				});
			});
		});
	}
	loadEach();
	
	//右侧二级导航选择切换
	$(".sub-menu li a").on("click",function(){
		var param = $(this).data("param");
		var url = $(this).data("url");
		if(param != null){
			loadUrl(url);
			openItem(param);
		}
	});
	
	//顶部导航栏菜单切换
	$(".module-menu li").on("click",function(){
		var modules = $(this).data("param");
		var items = $("#adminNavTabs_"+ modules).find(".item");
		var first_item = items.first();
		var default_a = "";
		
		items.find('.sub-menu').hide();
		$(this).addClass("active").siblings().removeClass("active");
		$(".admin-main").removeClass("start_home");
		$("#adminNavTabs_" + modules).show().siblings().hide();
		items.removeClass("current");
		first_item.addClass('current');
		
		if(modules == "menushopping"){
			var param = first_item.find('li').find("a").data("param");
			var data_str = param.split('|');
			
			if(data_str[1] == "001_goods_setting"){
				default_a = first_item.find('li').eq(1).find("a");
			}else{
				default_a = first_item.find('li').eq(0).find("a");
			}
		}else{
			default_a = first_item.find('li').eq(0).find('a:first');
		}

		default_a.click();
		
		//var url = default_a.data("url");
		//loadUrl(url);
	});
	
	//后台提示
	$(document).on("click","#msg_Container .msg_content a",function(){
		var param = $(this).data("param");
		var url = $(this).data("url");
		loadUrl(url);
		openItem(param);
	});
	
	$(".foldsider").click(function(){
		var leftdiv = $(".admin-main");
		if(leftdiv.hasClass("fold")){
			leftdiv.removeClass("fold");
			$(this).find("i.icon").removeClass("icon-indent-right").addClass("icon-indent-left");
			leftdiv.find(".current").children(".sub-menu").show();
			
			loadEach();
		}else{
			leftdiv.addClass("fold");
			$(this).find("i.icon").removeClass("icon-indent-left").addClass("icon-indent-right");
			leftdiv.find(".sub-menu").hide();
			leftdiv.find(".sub-menu").css("top","0px");
		}
	});
	
	var foldHoverTimer, foldOutTimer,foldHoverTimer2;
	$(document).on("mouseenter",".fold .tit",function(){
		var $this = $(this);
		var items = $this.parents(".item");
		
		var length = items.find(".sub-menu").find("li").length;
		items.parent().find(".item:gt(5)").find(".sub-menu").css("top",-((40*length)-68));
		$this.next().show();
		items.addClass("current");
		items.siblings(".item").removeClass("current");
	});
	
	$(document).on("mouseleave",".fold .tit",function(){
		var $this = $(this);
		clearTimeout(foldHoverTimer);
		foldOutTimer = setTimeout(function(){
			$this.next().hide();
		});
	});
	
	$(document).on("mouseenter",".fold .sub-menu",function(){
		clearTimeout(foldOutTimer);
		var $this = $(this);
		foldHoverTimer2 = setTimeout(function(){
			$this.show();
		});
	});
	
	$(document).on("mouseleave",".fold .sub-menu",function(){
		var $this = $(this);
		$this.hide();
	});
	
	//没有cookie默认选择起始页
	if ($.cookie('dscActionParam') == null) {
        $('.admin-logo').find('a').click();
    } else {
        openItem($.cookie('dscActionParam'));
    }

	//顶部布局换色设置
	var bgColorSelectorColors = [{ c: '#981767', cName: '' }, { c: '#AD116B', cName: '' }, { c: '#B61944', cName: '' }, { c: '#AA1815', cName: '' }, { c: '#C4182D', cName: '' }, { c: '#D74641', cName: '' }, { c: '#ED6E4D', cName: '' }, { c: '#D78A67', cName: '' }, { c: '#F5A675', cName: '' }, { c: '#F8C888', cName: '' }, { c: '#F9D39B', cName: '' }, { c: '#F8DB87', cName: '' }, { c: '#FFD839', cName: '' }, { c: '#F9D12C', cName: '' }, { c: '#FABB3D', cName: '' }, { c: '#F8CB3C', cName: '' }, { c: '#F4E47E', cName: '' }, { c: '#F4ED87', cName: '' }, { c: '#DFE05E', cName: '' }, { c: '#CDCA5B', cName: '' }, { c: '#A8C03D', cName: '' }, { c: '#73A833', cName: '' }, { c: '#468E33', cName: '' }, { c: '#5CB147', cName: '' }, { c: '#6BB979', cName: '' }, { c: '#8EC89C', cName: '' }, { c: '#9AD0B9', cName: '' }, { c: '#97D3E3', cName: '' }, { c: '#7CCCEE', cName: '' }, { c: '#5AC3EC', cName: '' }, { c: '#16B8D8', cName: '' }, { c: '#49B4D6', cName: '' }, { c: '#6DB4E4', cName: '' }, { c: '#8DC2EA', cName: '' }, { c: '#BDB8DC', cName: '' }, { c: '#8381BD', cName: '' }, { c: '#7B6FB0', cName: '' }, { c: '#AA86BC', cName: '' }, { c: '#AA7AB3', cName: '' }, { c: '#935EA2', cName: '' }, { c: '#9D559C', cName: '' }, { c: '#C95C9D', cName: '' }, { c: '#DC75AB', cName: '' }, { c: '#EE7DAE', cName: '' }, { c: '#E6A5CA', cName: '' }, { c: '#EA94BE', cName: '' }, { c: '#D63F7D', cName: '' }, { c: '#C1374A', cName: '' }, { c: '#AB3255', cName: '' }, { c: '#A51263', cName: '' }, { c: '#7F285D', cName: ''}];
	$("#trace_show").click(function(){
		$("div.bgSelector").toggle(300, function() {
			if ($(this).html() == '') {
				$(this).sColor({
					colors: bgColorSelectorColors,  // 必填，所有颜色 c:色号（必填） cName:颜色名称（可空）
					colorsWidth: '50px',  // 必填，颜色的高度
					colorsHeight: '31px',  // 必填，颜色的高度
					curTop: '0', // 可选，颜色选择对象高偏移，默认0
					curImg: 'images/cur.png',  //必填，颜色选择对象图片路径
					form: 'drag', // 可选，切换方式，drag或click，默认drag
					keyEvent: true,  // 可选，开启键盘控制，默认true
					prevColor: true, // 可选，开启切换页面后背景色是上一页面所选背景色，如不填则换页后背景色是defaultItem，默认false
					defaultItem: ($.cookie('bgColorSelectorPosition') != null) ? $.cookie('bgColorSelectorPosition') : 22  // 可选，第几个颜色的索引作为初始颜色，默认第1个颜色
				});
			}
		});//切换显示
	});
	if ($.cookie('bgColorSelectorPosition') != null) {
		$('body').css('background-color', bgColorSelectorColors[$.cookie('bgColorSelectorPosition')].c);
	} else {
		$('body').css('background-color', bgColorSelectorColors[30].c);
	}

	//上传管理员头像
	$("#_pic").change(function(){
		var actionUrl = "index.php?act=upload_store_img";
		$("#fileForm").ajaxSubmit({
			type: "POST",
			dataType: "json",
			url: actionUrl,
			data: { "action": "TemporaryImage" },
			success: function (data) {
				if (data.error == "0") {
					alert(data.massege);
				} else if (data.error == "1") {
					$(".avatar img").attr("src", data.content);
				}
			},
			async: true
		});
	});

	/*  @author-bylu 添加快捷菜单 start  */
	$('.admincp-map-nav li').click(function(){
		var i = $(this).index();
		$(this).addClass('selected');
		$(this).siblings().removeClass('selected');
		$('.admincp-map-div').eq(i).show();
		$('.admincp-map-div').eq(i).siblings('.admincp-map-div').hide();
	});

	$('.admincp-map-div dd i').click(function(){
		var auth_name = $(this).prev('a').text();
		var auth_href = $(this).prev('a').attr('href');
		if(!$(this).parent('dd').hasClass('selected')){

			if($('.admincp-map-div dd.selected').length >=10){
				alert('最多只允许添加10个快捷菜单!');return false;
			}

			$(this).parent('dd').addClass('selected');
			$('.quick_link ul').append('<li class="tl"><a href="'+auth_href+'" data-url="'+auth_href+'" data-param="" target="workspace">'+auth_name+'</a></li>')

			$.post('index.php?act=auth_menu',{'type':'add','auth_name':auth_name,'auth_href':auth_href});

		}else{
			$(this).parent('dd').removeClass('selected');
			$('.quick_link ul li').each(function(k,v){
				if(auth_name == $(v).text()){
					$(v).remove();
				}
			});
			$.post('index.php?act=auth_menu',{'type':'del','auth_name':auth_name,'auth_href':auth_href});
		}
	});

	$('.add_nav,.sitemap').click(function(){
		$('#allMenu').show();
	});
        
	//消息通知
	function message(){
		var hoverTimer, outTimer;
		$('.msg,#msg_Container').mouseenter(function(){
			clearTimeout(outTimer);
			hoverTimer = setTimeout(function(){
				$('#msg_Container').show();
			},200);
		});
		
		$('.msg,#msg_Container').mouseleave(function(){
			clearTimeout(hoverTimer);
			outTimer = setTimeout(function(){
				$('#msg_Container').hide();
			},100);	
		});
	}
	message();
    Ajax.call('index.php?is_ajax=1&act=check_order','', function(data){
		var wait_orders = data.wait_orders ? data.wait_orders :0;
        var new_orders = data.new_orders ? data.new_orders :0;
        var new_paid = data.new_paid ? data.new_paid :0;
        var user_account = data.user_account ? data.user_account :0;//未处理充值提现申请
        var seller_account = data.seller_account ? data.seller_account :0;//未处理商家实名认证
        var shop_account = data.shop_account ? data.shop_account :0;//未审核商家
        var shopinfo_account = data.shopinfo_account ? data.shopinfo_account :0;//未审核店铺信息
		var no_paid = data.no_paid ? data.no_paid :0;//待发货订单
		var no_change = data.no_change ? data.no_change :0;//待处理退换货订单
		var wait_cash = data.wait_cash ? data.wait_cash :0;//待审核商家提现
		var advance_date = data.advance_date ? data.advance_date :0;//即将过期广告
		var goods_report = data.goods_report ? data.goods_report :0;//未处理商品投诉
		var complaint = data.complaint ? data.complaint :0;//未完成交易投诉

        var total = parseInt(new_orders)+parseInt(new_paid)+parseInt(user_account)+parseInt(shop_account)+parseInt(shopinfo_account)+parseInt(no_paid)+parseInt(no_change)+parseInt(wait_cash)+parseInt(advance_date)+parseInt(goods_report)+parseInt(complaint)+parseInt(seller_account);

		//有新订单才显示订单提示数目
		if(total >0 && total<100){
			$('.msg').after('<s id="total">'+total+'</s>');
		}else if(total>99){
			$('.msg').after('<s><img src="images/gduo.png"></s>');
		} 

        if(total != 0){
            //待发货订单
			if(no_paid>0 && no_paid<100){
                $("*[ectype='orderMsg']").append('<p><a href="javascript:void(0);" data-url="order.php?act=list&composite_status=101&source=start" data-param="menushopping|02_order_list" target="workspace" class="message" >待发货订单</a> <span class="tiptool">（<em id="no_paid">'+no_paid+'</em>）</span></p>')
            }else if(no_paid>99){
				$("*[ectype='orderMsg']").append('<p><a href="javascript:void(0);" data-url="order.php?act=list&composite_status=101&source=start" data-param="menushopping|02_order_list" target="workspace" class="message" >待发货订单</a><span class="tiptool">（<em id="no_paid">99+</em>）</span></p>')
			}else{
				$("*[ectype='orderMsg']").append('<p><a href="javascript:void(0);" data-url="order.php?act=list" data-param="menushopping|02_order_list" target="workspace" class="message" >待发货订单</a> <span class="tiptool">（<em id="no_paid">'+no_paid+'</em>）</span></p>')
			}
			//待处理退换货订单
			if(no_change>0 && no_change<100){
                $("*[ectype='orderMsg']").append('<p><a href="javascript:void(0);" data-url="order.php?act=return_list&composite_status=105" data-param="menushopping|12_back_apply" target="workspace" class="message" >待处理退换货订单</a> <span class="tiptool">（<em id="no_change">'+no_change+'</em>）</span></p>')
            }else if(no_change>99){
				$("*[ectype='orderMsg']").append('<p><a href="javascript:void(0);" data-url="order.php?act=return_list&composite_status=105" data-param="menushopping|12_back_apply" class="message" >待处理退换货订单</a><span class="tiptool">（<em id="no_change">99+</em>）</span></p>')
			}else{
				$("*[ectype='orderMsg']").append('<p><a href="javascript:void(0);" data-url="order.php?act=return_list" data-param="menushopping|12_back_apply" target="workspace" class="message" >待处理退换货订单</a> <span class="tiptool">（<em id="no_change">'+no_change+'</em>）</span></p>')
			}
			
			//新订单
			if(new_orders>0 && new_orders<100){
                $("*[ectype='orderMsg']").append('<p><a href="javascript:void(0);" data-url="order.php?act=list" data-param="menushopping|02_order_list" target="workspace" class="message" >您有新订单</a> <span class="tiptool">（<em id="new_orders">'+new_orders+'</em>）</span></p>')
            }else if(new_orders>99){
				$("*[ectype='orderMsg']").append('<p><a href="javascript:void(0);" data-url="order.php?act=list" data-param="menushopping|02_order_list" target="workspace" class="message" >您有新订单</a><span class="tiptool">（<em id="new_orders">99+</em>）</span></p>')
			}else{
				$("*[ectype='orderMsg']").append('<p><a href="javascript:void(0);" data-url="order.php?act=list" data-param="menushopping|02_order_list" target="workspace" class="message" >您有新订单</a> <span class="tiptool">（<em id="new_orders">'+new_orders+'</em>）</span></p>')
			}
			if(new_paid>0 && new_paid<100){
                $("*[ectype='orderMsg']").append('<p><a href="javascript:void(0);" data-url="order.php?act=list" data-param="menushopping|02_order_list" target="workspace" target="workspace" class="message">您有新付款的订单</a> <span class="tiptool">（<em id="new_paid">'+new_paid+'</em>）</span></p>')
            }else if(new_paid>99){
				$("*[ectype='orderMsg']").append('<p><a href="javascript:void(0);" data-url="order.php?act=list" data-param="menushopping|02_order_list" target="workspace" class="message" >您有新订单</a><span class="tiptool">（<em id="new_paid">99+</em>）</span></p>')
			}else{
				$("*[ectype='orderMsg']").append('<p><a href="javascript:void(0);" data-url="order.php?act=list" data-param="menushopping|02_order_list" target="workspace" target="workspace" class="message">您有新付款的订单</a> <span class="tiptool">（<em id="new_paid">'+new_paid+'</em>）</span></p>')
			}
			
			//待审核入驻商
			if(shop_account > 0  && shop_account<100){
                $("*[ectype='sellerMsg']").append('<p><a href="javascript:void(0);" data-url="merchants_users_list.php?act=list&check=1" data-param="menushopping|02_merchants_users_list" target="workspace" class="message">未审核商家</a> <span class="tiptool">（<em id="shop_account">'+shop_account+'</em>）</span></p>')
            }else if(shop_account > 99){
                $("*[ectype='sellerMsg']").append('<p><a href="javascript:void(0);" data-url="merchants_users_list.php?act=list&check=1" data-param="menushopping|02_merchants_users_list" class="message">未审核商家</a><span class="tiptool">（<em id="shop_account">99+</em>）</span></p>')
            }else{
				$("*[ectype='sellerMsg']").append('<p><a href="javascript:void(0);" data-url="merchants_users_list.php?act=list" data-param="menushopping|02_merchants_users_list" target="workspace" class="message">未审核商家</a> <span class="tiptool">（<em id="shop_account">'+shop_account+'</em>）</span></p>')
			}
			//待审核商家提现
			if(wait_cash > 0  && wait_cash<100){
                $("*[ectype='sellerMsg']").append('<p><a href="javascript:void(0);" data-url="merchants_account.php?act=list&act_type=account_log&handler=2&rawals=1" data-param="menushopping|12_seller_account" target="workspace" class="message">待审核商家提现</a> <span class="tiptool">（<em id="wait_cash">'+wait_cash+'</em>）</span></p>')
            }else if(wait_cash > 99){
                $("*[ectype='sellerMsg']").append('<p><a href="javascript:void(0);" data-url="merchants_account.php?act=list&act_type=account_log&handler=2&rawals=1" data-param="menushopping|12_seller_account" target="workspace" class="message">待审核商家提现</a><span class="tiptool">（<em id="wait_cash">99+</em>）</span></p>')
            }else{
				$("*[ectype='sellerMsg']").append('<p><a href="javascript:void(0);" data-url="merchants_account.php?act=list&act_type=account_log&handler=2&rawals=1" data-param="menushopping|12_seller_account" target="workspace" class="message">待审核商家提现</a> <span class="tiptool">（<em id="wait_cash">'+wait_cash+'</em>）</span></p>')
			}
			
			if(user_account > 0  && user_account<100){
                $("*[ectype='sellerMsg']").append('<p><a href="javascript:void(0);" data-url="user_real.php?act=list&review_status=0&user_type=0" data-param="menuplatform|16_users_real" target="workspace" class="message">会员实名认证</a> <span class="tiptool">（<em id="user_account">'+user_account+'</em>）</span></p>')
            }else if(user_account > 99){
                $("*[ectype='sellerMsg']").append('<p><a href="javascript:void(0);" data-url="user_real.php?act=list&review_status=0&user_type=0" data-param="menushopping|16_users_real" target="workspace" class="message">会员实名认证</a><span class="tiptool">（<em id="user_account">99+</em>）</span></p>')
            }
            if(seller_account > 0  && seller_account<100){
                $("*[ectype='sellerMsg']").append('<p><a href="javascript:void(0);" data-url="user_real.php?act=list&review_status=0&user_type=1" data-param="menushopping|16_users_real" target="workspace" class="message">商家实名认证</a> <span class="tiptool">（<em id="seller_account">'+seller_account+'</em>）</span></p>')
            }else if(shop_account > 99){
                $("*[ectype='sellerMsg']").append('<p><a href="javascript:void(0);" data-url="user_real.php?act=list&review_status=0&user_type=1" data-param="menushopping|16_users_real" target="workspace" class="message">商家实名认证</a><span class="tiptool">（<em id="seller_account">99+</em>）</span></p>')
            }
//			seller_account
			//待审核店铺信息
			if(shopinfo_account > 0  && shopinfo_account<100){
                $("*[ectype='sellerMsg']").append('<p><a href="javascript:void(0);" data-url="merchants_users_list.php?act=list&shopinfo_check=1" data-param="menushopping|02_merchants_users_list" target="workspace" class="message">未审核店铺信息</a> <span class="tiptool">（<em id="shopinfo_account">'+shopinfo_account+'</em>）</span></p>')
            }else if(shopinfo_account > 99){
                $("*[ectype='sellerMsg']").append('<p><a href="javascript:void(0);" data-url="merchants_users_list.php?act=list&shopinfo_check=1" data-param="menushopping|02_merchants_users_list" class="message">未审核店铺信息</a><span class="tiptool">（<em id="shopinfo_account">99+</em>）</span></p>')
            }else{
				$("*[ectype='sellerMsg']").append('<p><a href="javascript:void(0);" data-url="merchants_users_list.php?act=list" data-param="menushopping|02_merchants_users_list" target="workspace" class="message">未审核店铺信息</a> <span class="tiptool">（<em id="shopinfo_account">'+shopinfo_account+'</em>）</span></p>')
			}			
			
			//广告位到期
			if(advance_date > 0  && advance_date<100){
                $("*[ectype='advMsg']").append('<p><a href="javascript:void(0);" data-url="ads.php?act=list&advance_date=1" data-param="menuplatform|ad_list" target="workspace" class="message">广告位即将到期</a> <span class="tiptool">（<em id="advance_date">'+advance_date+'</em>）</span></p>')
            }else if(advance_date > 99){
                $("*[ectype='advMsg']").append('<p><a href="javascript:void(0);" data-url="ads.php?act=list&advance_date=1" data-param="menushopping|ad_list" target="workspace" class="message">广告即将位到期</a><span class="tiptool">（<em id="advance_date">99+</em>）</span></p>')
            }else{
				$("*[ectype='advMsg']").append('<p><a href="javascript:void(0);" data-url="ads.php?act=list" data-param="menuplatform|ad_list" target="workspace" class="message">广告位即将到期</a> <span class="tiptool">（<em id="advance_date">0</em>）</span></p>');
			}
           
            if(goods_report > 0 || complaint > 0){
               $("*[ectype='cServiceDiv']").show();
               if(goods_report > 0 && goods_report < 100){
                   $("*[ectype='cService']").append('<p><a href="javascript:void(0);" data-url="goods_report.php?act=list&handle_type=6" data-param="menushopping|goods_report" target="workspace" class="message">商品举报</a> <span class="tiptool">（<em id="goods_report">'+goods_report+'</em>）</span></p>')
               }else if(goods_report > 99){
                   $("*[ectype='cService']").append('<p><a href="javascript:void(0);" data-url="goods_report.php?act=list&handle_type=6" data-param="menushopping|goods_report" target="workspace" class="message">商品举报</a><span class="tiptool">（<em id="goods_report">99+</em>）</span></p>')
               }
               if(complaint > 0 && complaint < 100){
                   $("*[ectype='cService']").append('<p><a href="javascript:void(0);" data-url="complaint.php?act=list&handle_type=5" data-param="menushopping|goods_report" target="workspace" class="message">交易投诉</a> <span class="tiptool">（<em id="complaint">'+complaint+'</em>）</span></p>')
               }else if(complaint > 99){
                   $("*[ectype='cService']").append('<p><a href="javascript:void(0);" data-url="complaint.php?act=list&handle_type=5" data-param="menushopping|13_complaint" target="workspace" class="message">交易投诉</a><span class="tiptool">（<em id="complaint">99+</em>）</span></p>')
               }
            }
			/*if(no_paid<0 && no_change<0 && new_orders<0 &&new_paid<0){
				$("*[ectype='orderMsg']").append('<div class="no_msg">暂无消息!</div>');
			}
			
			if(shop_account<0 && wait_cash<0 && user_account<0){
				$("*[ectype='sellerMsg']").append('<div class="no_msg">暂无消息!</div>');
			}*/
			
        }else{
			$('#msg_Container').append('<div class="no_msg">暂无消息！</div>')
        }
    }, 'GET', 'JSON');
});

function openItem(param,home){
	//若cookie值不存在，则跳出iframe框架
	if(!$.cookie('dscActionParam')){
		top.location.href = location.href;
		//top.location = self.location
		//window.location.reload();
	}
	
	if(param == "index|main"){
		$(".admin-main").addClass("start_home");
	}else{
		$(".admin-main").removeClass("start_home");
	}
	
	var $this = $('div[id^="adminNavTabs_"]').find('a[data-param="' + param + '"]');
	var url = $this.data('url');
	data_str = param.split('|');
	
	if(home == 0){
		$this.parents('.admin-main').removeClass('start_home');
	}
	
	if($this.parents(".admin-main").hasClass("fold")){
		$this.parents('.sub-menu').hide();
	}else{
		$this.parents('.sub-menu').show();
	}
	$this.parents('.item').addClass('current').siblings().removeClass('current');
	$this.parents('li').addClass('curr').siblings().removeClass('curr');
	$this.parents('div[id^="adminNavTabs_"]').show().siblings().hide();
	
	$('li[data-param="' + data_str[0] + '"]').addClass('active');
	
	$.cookie('dscActionParam', data_str[0] + '|' + data_str[1] , { expires: 1 ,path:'/'});
	
	if(param == 'index|main')
	{
		url = 'index.php?act=main';
		loadUrl(url);
	}
}

function loadUrl(url){
	$.cookie('dscUrl', url , { expires: 1 ,path:'/'});

	$('.admin-main-right iframe[name="workspace"]').attr('src','dialog.php?act=getload_url');
	setTimeout(function(){
		$('.admin-main-right iframe[name="workspace"]').attr('src', url);
		/* 检查订单 */
  		startCheckOrder();
		
		/* 检查账单 */
  		startCheckBill();
	},300);
}

