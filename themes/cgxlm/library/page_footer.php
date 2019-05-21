<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<div class="footer-new">
    <div class="footer-new-top">
    	<div class="w w1200">
            <div class="service-list">
                <div class="service-item">
                    <i class="f-icon f-icon-qi"></i>
                    <span>{$lang.7_days_return}</span>
                </div>
                <div class="service-item">
                    <i class="f-icon f-icon-zheng"></i>
                    <span>{$lang.Authentic_guarantee}</span>
                </div>
                <div class="service-item">
                    <i class="f-icon f-icon-hao"></i>
                    <span>{$lang.Rave_reviews}</span>
                </div>
                <div class="service-item">
                    <i class="f-icon f-icon-shan"></i>
                    <span>{$lang.Lightning_delivery}</span>
                </div>
                <div class="service-item">
                    <i class="f-icon f-icon-quan"></i>
                    <span>{$lang.Authority_of_honor}</span>
                </div>
            </div>
            <div class="contact">
                <div class="contact-item contact-item-first"><i class="f-icon f-icon-tel"></i><span>{$service_phone}</span></div>
                <div class="contact-item">
                	{if $kf_im_switch}
                    <a id="IM" IM_type="dsc" onclick="openWin(this)" href="javascript:;" class="btn-ctn"><i class="f-icon f-icon-kefu"></i><span>咨询客服</span></a>
                    {else}
                        {if $basic_info.kf_type eq 1}
                        <a href="http://www.taobao.com/webww/ww.php?ver=3&touid={$basic_info.kf_ww}&siteid=cntaobao&status=1&charset=utf-8" class="btn-ctn" target="_blank" rel="nofollow"><i class="f-icon f-icon-kefu"></i><span>咨询客服</span></a>
                        {else}
                        <a href="http://wpa.qq.com/msgrd?v=3&uin={$basic_info.kf_qq}&site=qq&menu=yes" class="btn-ctn" target="_blank" rel="nofollow"><i class="f-icon f-icon-kefu"></i><span>咨询客服</span></a>
                        {/if}
                    {/if}
                </div>
            </div>
        </div>
    </div>
    <div class="footer-new-con">
    	<div class="fnc-warp">
            <div class="help-list">
                <!-- {foreach from=$helps item=help_cat name=no} -->
                {if $smarty.foreach.no.iteration < 6}
                <div class="help-item">
                    <h3>{$help_cat.cat_name}</h3>
                    <ul>
                    <!-- {foreach from=$help_cat.article item=item name=help_cat} -->
                    {if $smarty.foreach.help_cat.iteration < 4}
                    <li><a href="{$item.url}"  title="{$item.title|escape:html}" target="_blank" rel="nofollow">{$item.short_title}</a></li>
                    {/if}
                    <!-- {/foreach} -->
                    </ul>
                </dl>
                </div>
                {/if}
                <!-- {/foreach} -->  
            </div>
            <div class="qr-code">
                <div class="qr-item qr-item-first">
                    <div class="code_img"><img src="{$site_domain}{$ecjia_qrcode}"></div>
                    <div class="code_txt">ECJia</div>
                </div>
                <div class="qr-item">
                    <div class="code_img"><img src="{$site_domain}{$ectouch_qrcode}"></div>
                    <div class="code_txt">ECTouch</div>
                </div>
            </div>
    	</div>
    </div>
    <div class="footer-new-bot">
    	<div class="w w1200">
            <!-- {if $navigator_list.bottom} --> 
            <p class="copyright_links">
                <!-- {foreach name=nav_bottom_list from=$navigator_list.bottom item=nav} -->
                <a href="{$nav.url}"<!-- {if $nav.opennew eq 1} --> target="_blank" <!-- {/if} --> rel="nofollow">{$nav.name}</a>
                <!-- {if !$smarty.foreach.nav_bottom_list.last} --> 
                <span class="spacer"></span>
                <!-- {/if} --> 
                <!-- {/foreach} --> 
            </p>
            <!-- {/if} -->
            
            <!--{if $img_links  or $txt_links }-->
            <p class="copyright_links">
                <!--开始图片类型的友情链接{foreach from=$img_links item=link}-->
                    <a href="{$link.url}" target="_blank" title="{$link.name}" rel="nofollow"><img src="{$link.logo}" alt="{$link.name}" border="0" /></a>
                <!--结束图片类型的友情链接{/foreach}-->
                    
                <!-- {if $txt_links} -->
                <!-- {foreach from=$txt_links item=link name=nolink} 开始文字类型的友情链接-->
                    <a href="{$link.url}" target="_blank" title="{$link.name}" rel="nofollow">{$link.name}</a>
                    <!-- {if !$smarty.foreach.nolink.last} --> 
                    <span class="spacer"></span>
                    <!-- {/if} -->
                <!-- {/foreach} 结束文字类型的友情链接-->
                <!-- {/if} -->
            </p>
            <!--{/if}-->
            
            <!-- {if $icp_number} ICP 证书-->
            <p><span>(c)&nbsp;2015-2018&nbsp;dscmall.com&nbsp;版权所有&nbsp;&nbsp;</span><span>{$lang.icp_number}:</span><a href="http://www.miibeian.gov.cn/" target="_blank" rel="nofollow">{$icp_number}</a>&nbsp;</p>
            <!--{/if}-->
            
            <!--{if $partner_img_links  or $partner_txt_links }-->
            <p class="copyright_auth">
                <!--开始图片类型的合作伙伴链接{foreach from=$partner_img_links item=link}-->
                <a href="{$link.url}" target="_blank" title="{$link.name}" rel="nofollow"><img src="{$link.logo}" alt="{$link.name}" border="0" /></a>
                <!--结束图片类型的友情链接{/foreach}-->
                <!-- {if $txt_links} -->
                <!--开始文字类型的合作伙伴链接{foreach from=$partner_txt_links item=link name=nolink}-->
                <a href="{$link.url}" target="_blank" title="{$link.name}" class="mr0" rel="nofollow">{$link.name}</a>
                <!-- {if !$smarty.foreach.nolink.last} --> 
                | 
                <!-- {/if} --> 
                <!--结束文字类型的合作伙伴链接{/foreach}-->
                <!-- {/if} -->
            </p>    
            <!--{else}-->
            <p class="copyright_auth">&nbsp;</p>
            <!--{/if}-->
        </div>
    </div>
    
    <!--优惠券领取弹窗-->
    <div class="hide" id="pd_coupons">
        <span class="success-icon m-icon"></span>
        <div class="item-fore">
            <h3>{$lang.Coupon_redemption_succeed}</h3>
            <div class="txt ftx-03">{$lang.coupons_prompt}</div>
        </div>
    </div>
    
    <!--隐藏域-->
    <div class="hidden">
        <input type="hidden" name="seller_kf_IM" value="{$shop_information.is_IM}" rev="{$site_domain}" ru_id="{$smarty.get.merchant_id}" />
        <input type="hidden" name="seller_kf_qq" value="{$basic_info.kf_qq}" />
        <input type="hidden" name="seller_kf_tel" value="{$basic_info.kf_tel}" />
        <input type="hidden" name="user_id" value="{$smarty.session.user_id|default:0}" />
    </div>
</div>

<!-- {if $site_domain} -->
<script type="text/jscript" src="{$site_domain}js/suggest.js"></script>
<script type="text/jscript" src="{$site_domain}js/scroll_city.js"></script>
<script type="text/jscript" src="{$site_domain}js/utils.js"></script>
<!-- {else} -->
{insert_scripts files='suggest.js,scroll_city.js,utils.js'}
<!-- {/if} -->

<!-- {if $site_domain} -->
{if $area_htmlType neq 'goods' && $area_htmlType neq 'exchange'}
	<script type="text/javascript" src="{$site_domain}js/warehouse_area.js"></script>
{else}
	<script type="text/javascript" src="{$site_domain}js/warehouse.js"></script>
{/if}
<!-- {else} -->
{insert_scripts files='warehouse.js,warehouse_area.js'}
<!-- {/if} -->

{if $suspension_two}
<script>var seller_qrcode='<img src="{$site_domain}{$seller_qrcode_img}" alt="{$seller_qrcode_text}" width="164" height="164">'; //by wu</script>
{$suspension_two}
{/if}