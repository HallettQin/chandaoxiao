<?php

/**
 * ECSHOP 权限对照表
 * ============================================================================
 * 版权所有 2016-2018 产供销网络科技(广州)有限公司，并保留所有权利。
 * 网站地址: http://www.chandaoxiao.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: Hallett
*/

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

$purview['set_gcolor']           = 'set_gcolor'; // 设置商品颜色 bu zhang

//by guan start
$purview['user_keywords']              = 'user_keywords';
//by guan end

//商品管理权限
    $purview['01_goods_list']        = array('goods_manage', 'remove_back');
    $purview['02_goods_add']         = 'goods_manage';
    //$purview['03_category_manage']     = $purview['user_keywords_list']     = array('cat_manage', 'cat_drop');   //分类添加、分类转移和删除
    $purview['03_category_manage']     = $purview['user_keywords_list']     = array( 'cat_drop');   //分类添加、分类转移和删除 by kong 改
    $purview['03_store_category_list']    = 'cat_manage';//by kong
    $purview['05_comment_manage']    = 'comment_priv';
    $purview['06_goods_brand']  = 'brand_manage';
    $purview['08_goods_type']        = 'attr_manage';   //商品属性
    $purview['11_goods_trash']       = array('goods_manage', 'remove_back');
    $purview['12_batch_pic']         = 'picture_batch';
    $purview['13_batch_add']         = 'goods_batch';
    $purview['14_goods_export']      = 'goods_export';
    $purview['15_batch_edit']        = 'goods_batch';
    $purview['16_goods_script']      = 'gen_goods_script';
    $purview['17_tag_manage']        = 'tag_manage';
    $purview['50_virtual_card_list'] = 'virualcard';
    $purview['51_virtual_card_add']  = 'virualcard';
    $purview['52_virtual_card_change'] = 'merch_virualcard';//更改加密串
    $purview['goods_auto']           = 'goods_auto';
    $purview['seller_service_rank']  = 'seller_service';
    $purview['discuss_circle']       = 'discuss_circle'; //ecmoban模板堂 --zhuo
    $purview['website']              = 'website'; //ecmoban模板堂 --zhuo
    $purview['18_comment_edit_delete']  = 'comment_edit_delete'; //ecmoban模板堂 --zhuo
    $purview['05_comment_seller_rank'] = 'comment_seller';//by kong
    $purview['area_attr_batch']           = 'goods_manage';//by kong
    $purview['area_batch']           = 'goods_manage';//by kong
    $purview['warehouse_batch']           = 'goods_manage';//by kong
    $purview['sale_notice']           = 'sale_notice';
    $purview['notice_logs']           = 'notice_logs';
    $purview['gallery_album']           = 'gallery_album';
    $purview['01_review_status']           = 'review_status';
    $purview['18_value_card']           = 'value_card';
    $purview['goods_report']           = 'goods_report';
    $purview['13_comment_seller_rank'] = 'comment_seller';//by kong
	$purview['001_goods_setting']      = 'goods_manage'; //商品设置
    
//商品库存管理权限
    $purview['01_goods_storage_put']        = 'storage_put';
    $purview['02_goods_storage_out']        = 'storage_out';
    
//促销管理权限
    $purview['02_snatch_list']       = 'snatch_manage';
    $purview['03_seckill_list']       = 'seckill_manage';
    $purview['04_bonustype_list']    = 'bonus_manage';
    $purview['06_pack_list']         = 'goods_pack';
    $purview['07_card_list']         = 'card_manage';
    $purview['08_group_buy']         = 'group_by';
    $purview['09_topic']             = 'topic_manage';
    $purview['10_auction']           = 'auction';
    $purview['12_favourable']        = 'favourable';
    $purview['13_wholesale']         = 'whole_sale';
    $purview['14_package_list']      = 'package_manage';
    //ecmoban模板堂 --zhuo start
    $purview['gift_gard_list']      = 'gift_gard_manage';
    $purview['take_list'] = 'take_manage';
    //ecmoban模板堂 --zhuo start
//  $purview['02_snatch_list']       = 'gift_manage';  //赠品管理
    $purview['15_exchange_goods']    = 'exchange_goods';  //赠品管理
    $purview['16_presale']           = 'presale';
    $purview['17_coupons']           = 'coupons_manage'; //优惠券权限bylu;
    $purview['20_sample']         = 'sample';
if (file_exists(MOBILE_TEAM)) {
    $purview['18_team']              = 'team_manage';
}

//文章管理权限
    $purview['02_articlecat_list']   = 'article_cat';
    $purview['03_article_list']      = 'article_manage';
    $purview['article_auto']         = 'article_auto';
    $purview['vote_list']            = 'vote_priv';

//会员管理权限
    $purview['03_users_list']        = 'users_manage';
    $purview['04_users_add']         = 'users_manage';
    $purview['11_users_add']         = 'users_manage';
    $purview['05_user_rank_list']    = 'user_rank';
    $purview['09_user_account']      = 'surplus_manage';
    $purview['06_list_integrate']    = 'integrate_users';
    $purview['08_unreply_msg']       = 'feedback_priv';
    $purview['10_user_account_manage'] = 'account_manage';
    $purview['12_user_address_list']         = 'users_manage';
    $purview['13_user_baitiao_info'] = 'baitiao_manage';//@author bylu 白条管理
    $purview['15_user_vat_info'] = 'user_vat_manage';// liu
    $purview['16_users_real'] = 'users_real_manage';// 实名认证

//权限管理
    $purview['admin_logs']           = array('logs_manage', 'logs_drop');
    $purview['01_admin_list']           = array('admin_manage', 'admin_drop', 'allot_priv');
    $purview['02_admin_seller']           = array('seller_manage','seller_drop','seller_allot'); //admin_manage_shop
    $purview['agency_list']          = 'agency_manage';
    $purview['suppliers_list']          = 'suppliers_manage'; // 供货商
    $purview['admin_role']             = 'role_manage';
    $purview['admin_message']             = 'admin_message';

//商店设置权限
    $purview['01_shop_config']       = $purview['user_keywords_list']       = 'shop_config';
    $purview['shop_authorized']       = 'shop_authorized';
    $purview['shp_webcollect']            = 'webcollect_manage';
    $purview['02_payment_list']      = 'payment';
    $purview['03_shipping_list']     = array('ship_manage','shiparea_manage');
    $purview['05_area_list']         = 'area_list';
    $purview['07_cron_schcron']      = 'cron';
    $purview['08_friendlink_list']   = 'friendlink';
    $purview['09_partnerlink_list']   = 'partnerlink';
    $purview['sitemap']              = 'sitemap';
    $purview['check_file_priv']      = 'file_priv';
    $purview['captcha_manage']       = 'shop_config';
    $purview['file_check']           = 'file_check';
    $purview['navigator']            = 'navigator';
    $purview['flashplay']            = 'flash_manage';
    $purview['ucenter_setup']        = 'integrate_users';
    $purview['16_reg_fields']       = 'reg_fields';
    $purview['oss_configure']       = 'oss_configure';
    $purview['open_api']            = 'open_api';
    $purview['09_warehouse_management'] = 'warehouse_manage'; //仓库权限 ecmoban模板堂 --zhuo
    $purview['09_region_area_management'] = 'region_area'; //地区所属区域 ecmoban模板堂 --zhuo
    $purview['shipping_date_list']     = 'shipping_date_list';
    $purview['03_area_shipping']     = array('ship_manage','shiparea_manage');
    $purview['api']       = 'api';
//广告管理
    $purview['z_clicks_stats']       = 'ad_manage';
    $purview['ad_position']          = 'ad_manage';
    $purview['ad_list']              = 'ad_manage';
    
//订单管理权限
    $purview['13_goods_inventory_logs']        = 'order_view';//by kong
    $purview['02_order_list']        = 'order_view';
    $purview['03_order_query']       = 'order_view';
    $purview['04_merge_order']       = 'order_os_edit';
    $purview['05_merge_order_remove']   = 'order_os_remove';
    $purview['06_undispose_booking'] = 'booking';
    $purview['08_add_order']         = 'order_edit';
    $purview['09_delivery_order']    = 'delivery_view';
    $purview['10_back_order']        = 'back_view';
    $purview['13_complaint']        = 'complaint';
    
    //ecmoban模板堂 --zhuo start
    $purview['11_add_order']        = 'batch_add_order';
    $purview['11_back_cause']       = 'order_back_cause';
    $purview['12_back_apply']       = 'order_back_apply';
    $purview['05_edit_order_print']  = 'order_print';
    $purview['11_order_detection']   = 'order_detection';
    //ecmoban模板堂 --zhuo end

//报表统计权限
    $purview['flow_stats']           = 'users_flow_stats'; //流量分析
    $purview['report_guest']         = 'client_report_guest'; //客户统计
    $purview['report_users']         = 'client_flow_stats'; //客户流量统计
    $purview['visit_buy_per']        = 'client_flow_stats';
    $purview['searchengine_stats']   = 'client_searchengine'; //搜索引擎
    $purview['report_order']         = 'sale_order_stats'; //订单销售统计
    $purview['report_sell']          = 'sale_order_stats';
    $purview['sale_list']            = 'sale_order_stats';
    $purview['sell_stats']           = 'sale_order_stats'; 
    $purview['exchange_count']         = 'exchange'; //积分明细
    


//模板管理
    $purview['02_template_select']   = 'template_select';
    $purview['03_template_setup']    = 'template_setup';
    $purview['04_template_library']  = 'library_manage';
    $purview['05_edit_languages']    = 'lang_edit';
    $purview['06_template_backup']   = 'backup_setting';
    $purview['01_visualhome']        = 'visualhome';

//数据库管理
    $purview['02_db_manage']         = array('db_backup', 'db_renew');
    $purview['03_db_optimize']       = 'db_optimize';
    $purview['04_sql_query']         = 'sql_query';
    $purview['convert']              = 'convert';
    $purview['05_table_prefix']      = 'table_prefix';
    $purview['06_transfer_config']   = 'transfer';
    $purview['07_transfer_choose']   = 'transfer';
    $purview['08_db_fields']   = 'db_fields';

//短信管理
    $purview['02_sms_my_info']       = 'my_info';
    $purview['03_sms_send']          = 'sms_send';
    $purview['04_sms_charge']        = 'sms_charge';
    $purview['05_sms_send_history']  = 'send_history';
    $purview['06_sms_charge_history']= 'charge_history';

//推荐管理
    $purview['affiliate']            = 'affiliate';
    $purview['affiliate_ck']         = 'affiliate_ck';

//邮件群发管理
    $purview['01_mail_settings']        = 'mail_settings';
    $purview['02_attention_list']       = 'attention_list';
    $purview['03_email_list']           = 'email_list';
    $purview['04_magazine_list']        = 'magazine_list';
    $purview['05_view_sendlist']        = 'view_sendlist';
    $purview['06_mail_template_manage'] = 'mail_template';
    
//商家入驻管理部分的权限 ecmoban模板堂 --zhuo start
    $purview['07_merchants_brand']   = 'merchants_brand'; 
    $purview['01_merchants_steps_list']       = 'merchants_setps';
    $purview['01_seller_stepup']       = 'merchants_setps';
    $purview['02_merchants_users_list']       = 'users_merchants';
    $purview['03_merchants_commission']       = 'merchants_commission';
    $purview['03_merchants_percent']          = 'merchants_percent';
    $purview['03_users_merchants_priv']       = 'users_merchants_priv';
    $purview['09_seller_domain'] = 'seller_dimain'; //by kong 二级域名管理
    $purview['10_seller_grade'] = 'seller_grade'; 
    $purview['11_seller_apply'] = 'seller_apply';
    $purview['12_seller_account'] = 'seller_account';// 商家账户管理
    $purview['16_seller_users_real'] = 'seller_users_real';// 商家实名认证 
    
    $purview['04_create_seller_grade']        = 'create_seller_grade';
    $purview['01_cloud_services']             = 'cloud_services';
    $purview['02_platform_recommend']         = 'cloud_services';
    $purview['03_best_recommend']             = 'cloud_services';
//ecmoban模板堂 --zhuo end	

    /*店铺设置管理 by kong*/
    $purview['01_merchants_basic_info']   = 'seller_store_informa';
    $purview['08_merchants_template']   = 'seller_store_other';
    $purview['07_merchants_window']   = 'seller_store_other';
    $purview['06_merchants_custom']   = 'seller_store_other';
    $purview['05_merchants_shop_bg']   = 'seller_store_other';
    $purview['04_merchants_basic_nav']   = 'seller_store_other';
    $purview['03_merchants_shop_top']   = 'seller_store_other';
    $purview['02_merchants_ad']         = 'seller_store_other';
    $purview['09_merchants_upgrade']    = 'seller_store_other';
	
//众筹权限 by wu
    $purview['01_crowdfunding_list']       = 'zc_project_manage';
    $purview['02_crowdfunding_cat']        = 'zc_category_manage';
    $purview['03_project_initiator']       = 'zc_initiator_manage';
    $purview['04_topic_list']              = 'zc_topic_manage';

// 门店权限
    $purview['12_offline_store'] = 'offline_store';//门店  by kong 
    $purview['2_order_stats'] = 'offline_store';//门店  by kong	
    $purview['01_self_offline_store'] = 'offline_store';
    $purview['02_self_order_stats'] = 'offline_store';
    $purview['03_self_support_info'] = 'offline_store';
    $purview['12_seller_store'] = 'offline_store';
    
    
// 手机端管理权限
    $purview['01_oauth_admin']                = 'oauth_admin';
    $purview['02_touch_nav_admin']            = 'touch_nav_admin';
    $purview['03_touch_ads']                  = 'touch_ad';
    $purview['04_touch_ad_position']          = 'touch_ad_position';
    $purview['05_touch_dashboard']            = 'touch_dashboard';

// ecjia APP权限管理 qin
$purview['20_ecjia_app']                = 'ecjia_app';
$purview['22_ecjia_shipping']                = 'ecjia_shipping';
$purview['24_ecjia_sms']                = 'ecjia_sms';
$purview['26_ecjia_feedback']                = 'ecjia_feedback';
$purview['28_ecjia_marketing']                = 'ecjia_marketing';

// APP二级菜单 移动应用
$purview['02_ecjia_app_shortcut'] = 'ecjia_app';
$purview['03_ecjia_app_shortcut_ipad'] = 'ecjia_app';
$purview['04_ecjia_app_cycleimage'] = 'ecjia_app';
$purview['05_ecjia_app_cycleimage_ipad'] = 'ecjia_app';
$purview['06_ecjia_app_discover'] = 'ecjia_app';
$purview['08_ecjia_app_device'] = 'ecjia_app';
$purview['10_ecjia_app_news'] = 'ecjia_app';
$purview['12_ecjia_app_config'] = 'ecjia_app';
$purview['14_ecjia_app_manage'] = 'ecjia_app';
$purview['16_ecjia_app_toutiao'] = 'ecjia_app';
$purview['18_ecjia_app_activity'] = 'ecjia_app';

// APP二级菜单 配送方式
$purview['02_ecjia_shipping_ship'] = 'ecjia_shipping';

// APP二级菜单 短信管理
$purview['02_ecjia_sms_record'] = 'ecjia_sms';
$purview['04_ecjia_sms_template'] = 'ecjia_sms';
$purview['06_ecjia_sms_config'] = 'ecjia_sms';

// APP二级菜单 留言反馈
$purview['02_ecjia_feedback_order'] = 'ecjia_feedback';
$purview['04_ecjia_feedback_user'] = 'ecjia_feedback';
$purview['06_ecjia_feedback_public'] = 'ecjia_feedback';
$purview['08_ecjia_feedback_mobile'] = 'ecjia_feedback';

// APP二级菜单 留言反馈
$purview['02_ecjia_marketing_adviser'] = 'ecjia_marketing';

// 微信通权限
if (file_exists(MOBILE_WECHAT)) {
    $purview['01_wechat_admin'] = 'wechat_admin';
    $purview['02_mass_message'] = 'mass_message';
    $purview['03_auto_reply'] = 'auto_reply';
    $purview['04_menu'] = 'menu';
    $purview['05_fans'] = 'fans';
    $purview['06_media'] = 'media';
    $purview['07_qrcode'] = 'qrcode';
    $purview['08_share'] = 'share';
    $purview['09_extend'] = 'extend';
    $purview['10_market'] = 'market';
    $purview['11_template'] = 'template';
    $purview['30_wxapp_config'] = 'wxapp_config';
}

//微分销管理权限
if (file_exists(MOBILE_DRP)) {
    $purview['01_drp_config'] = 'drp_config';
    $purview['02_drp_shop'] = 'drp_shop';
    $purview['03_drp_list'] = 'drp_list';
    $purview['04_drp_order_list'] = 'drp_order_list';
    $purview['05_drp_set_config'] = 'drp_set_config';
}

//b2b
$purview['supply_and_demand'] = 'supply_and_demand';
$purview['01_wholesale'] = 'whole_sale';
$purview['03_wholesale_purchase'] = 'wholesale_purchase';
$purview['02_wholesale_order'] = 'wholesale_order';

//短信配置
$purview['01_sms_setting'] = 'sms_setting';
$purview['alidayu_configure'] = 'sms_setting';
$purview['alitongxin_configure'] = 'sms_setting';
$purview['huyi_configure'] = 'sms_setting';

//第三方服务
//purview['01_sms_setting'] = 'sms_setting';

//商品库权限
$purview['20_goods_lib'] = 'goods_lib_list';
$purview['21_goods_lib_cat'] = 'goods_lib_cat';

//短信群发
$purview['17_mass_sms'] = 'mass_sms';

//进销存
$purview['01_psi_purchase'] 		= 'goods_psi';
$purview['03_psi_inventory'] 		= 'goods_psi';

//快递鸟、电子面单
$purview['order_print_setting'] = 'order_print_setting';
$purview['kdniao'] = 'shop_config';
?>