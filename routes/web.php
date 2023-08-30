<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->group(['prefix' => 'api' , 'middleware' => 'api-secure'], function() use ($router){
    //param
    $router->post('param/brand/search', ['uses' => 'API\ParamController@searchBrand']);
    $router->get('param/brand', ['uses' => 'API\ParamController@getBrandList']);
    $router->get('param/category', ['uses' => 'API\ParamController@getCategoryList']);
    $router->get('param/subcategory/{category_id}', ['uses' => 'API\ParamController@getSubcategoryList']);
    $router->get('param/rating', ['uses' => 'API\ParamController@getRating']);
    $router->get('param/trivia', ['uses' => 'API\ParamController@getTrivia']);

    //auth
    $router->post('register', ['uses' => 'API\AuthController@userRegister']);
    $router->post('login', ['uses' => 'API\AuthController@userLogin']);
    $router->get('check/token', ['uses' => 'API\AuthController@checkLoginToken']);

    //search product
    $router->post('product/search', ['uses' => 'API\ProductController@searchProduct']);
    $router->get('product/list/physical', ['uses' => 'API\ProductController@listProductPhysical']);
    $router->get('product/category/{category_id}', ['uses' => 'API\ProductController@getProductCategoryList']);
    $router->get('product/subcategory/{subcat_id}', ['uses' => 'API\ProductController@getProductSubcategoryList']);
    $router->get('product/brand/{brand_id}', ['uses' => 'API\ProductController@getProductBrandList']);

    //load review
    $router->get('review/list/category/{category_id}', ['uses' => 'API\PostController@getReviewCategoryList']);

    //product detail
    $router->get('product/detail/{product_uri}', ['uses' => 'API\ProductController@detailProduct']);
    $router->get('product/variant/{product_uri}', ['uses' => 'API\ProductController@getProductVariant']);
    $router->get('product/detail/form/{product_uri}', ['uses' => 'API\ProductController@detailProductform']);
    
    //product digital
    $router->get('product/list/digital', ['uses' => 'API\ProductController@listDigitalProduct']);
    $router->get('product/detail/digital/{product_uri}', ['uses' => 'API\ProductController@detailDigitalProduct']);

    //post written review
    $router->post('review/product/post', ['uses' => 'API\PostController@postReview']);
    $router->post('review/product/post/propose', ['uses' => 'API\PostController@postReviewPropose']);

    //post video review
    $router->post('review/video/post', ['uses' => 'API\PostController@postVideo']);

    //main page
    $router->get('main', ['uses' => 'API\HomeController@mainPage']);
    $router->get('main/commerce', ['uses' => 'API\HomeController@mainCommercePage']);
    $router->get('content/detail/{content_id}', ['uses' => 'API\HomeController@detailContent']);
    $router->get('content/detail/uri/{content_uri}', ['uses' => 'API\HomeController@detailContentUri']);
    
    //list product -group buy
    $router->get('product/group_buy', ['uses' => 'API\GroupBuyController@groupBuyProducts']);

    //collection
    $router->get('collection/detail/{collection_id}', ['uses' => 'API\HomeController@detailCollection']);

    //user page
    $router->post('user/edit', ['uses' => 'API\UserController@editProfile']);
    $router->post('user/edit/password', ['uses' => 'API\UserController@editPassword']);
    $router->get('user/private', ['uses' => 'API\UserController@privateProfile']);
    $router->get('user/public/{user_uri}', ['uses' => 'API\UserController@publicProfile']);

    //list voucher - user
    $router->get('user/voucher', ['uses' => 'API\UserController@listVoucher']);
    $router->get('user/voucher/regular', ['uses' => 'API\UserController@listRegularVoucher']);
    $router->get('user/voucher/detail/{voucher_code}', ['uses' => 'API\UserController@detailVoucher']);
    $router->post('user/voucher/search', ['uses' => 'API\UserController@searchVoucher']);

    //sicepat delivery
    $router->post('delivery/sicepat', ['uses' => 'API\CommerceController@deliverySicepat']);
    $router->post('delivery/sicepat/discount', ['uses' => 'API\CommerceController@deliverySicepat2']);

    //user address
    $router->get('user/address', ['uses' => 'API\UserController@listAddress']);
    $router->post('user/address/add', ['uses' => 'API\UserController@addAddress']);
    $router->post('user/address/edit', ['uses' => 'API\UserController@editAddress']);
    $router->get('user/address/detail/{address_id}', ['uses' => 'API\UserController@detailAddress']);
    $router->get('user/address/default/{address_id}', ['uses' => 'API\UserController@detailAddress']);
    
    //invitation
    $router->get('user/invite/link', ['uses' => 'API\UserController@getInviteLink']);
    $router->get('user/invite/detail/{code}', ['uses' => 'API\UserController@getInviteDetail']);
    $router->post('register/referral', ['uses' => 'API\AuthController@userRegisterReferral']);

    //list content
    $router->get('load/content/user/private', ['uses' => 'API\UserController@loadContentPerUser']);
    $router->get('load/content/user/public/{user_uri}', ['uses' => 'API\UserController@loadContentPerUserPublic']);
    $router->get('load/content/product/{product_uri}', ['uses' => 'API\ProductController@loadContentPerProduct']);
    $router->get('load/content/main/{content_type}', ['uses' => 'API\HomeController@loadContentMain']);

    //gamification
    $router->get('gamification/main', ['uses' => 'API\UserController@loadGamificationMain']);
    $router->get('gamification/task', ['uses' => 'API\UserController@loadGamificationTask']);

    //utility
    $router->post('utility/city/search', ['uses' => 'Utility\UtilityController@searchCity']);
    $router->get('utility/sitemap', ['uses' => 'Utility\SitemapController@getSitemapUrl']);
    $router->get('utility/url', ['uses' => 'Utility\SitemapController@getURL']);

    //payment
    $router->get('payment/channel/list', ['uses' => 'Utility\PaymentController@listPaymentChannel']);

    $router->post('payment/midtrans/token', ['uses' => 'Utility\PaymentController@midtransToken']);
    $router->post('payment/midtrans/callback', ['uses' => 'Utility\PaymentController@midtransCallback']);
    $router->get('payment/complete/{order_id}', ['uses' => 'Utility\PaymentController@payment_complete']);

    $router->post('payment/faspay/token', ['uses' => 'Utility\FaspayController@faspayToken']);
    // $router->get('payment/faspay/signature/{order_id}', ['uses' => 'Utility\FaspayController@createSignature']);

    //sicepat destination
    $router->get('utility/address/province', ['uses' => 'Utility\SicepatController@getProvince']);
    $router->get('utility/address/city', ['uses' => 'Utility\SicepatController@getCity']);
    $router->get('utility/address/subdistrict', ['uses' => 'Utility\SicepatController@getSubdistrict']);
    $router->get('utility/sicepat/destination_code', ['uses' => 'Utility\SicepatController@getDestinationCode']);

    //notification
    $router->get('notification/check', ['uses' => 'Utility\NotificationController@checkNotification']);
    $router->get('notification/reminder/check/{mode}', ['uses' => 'Utility\NotificationController@checkReminderNotification']);
    $router->get('notification/reminder/{mode}', ['uses' => 'Utility\NotificationController@postReminder']);

    //forgot password
    $router->post('forgot/password', ['uses' => 'API\AuthController@forgotPassword']);
    $router->post('reset/password', ['uses' => 'API\AuthController@resetPassword']);

    //wishlist
    $router->get('wishlist', ['uses' => 'API\CommerceController@getWishlist']);
    $router->post('wishlist/add', ['uses' => 'API\CommerceController@addWishlist']);
    $router->post('wishlist/remove', ['uses' => 'API\CommerceController@removeWishlist']);

    //Commerce Basket
    $router->get('basket', ['uses' => 'API\CommerceController@getBasket']);
    $router->get('basket/count', ['uses' => 'API\CommerceController@getBasketCount']);
    $router->post('basket/add', ['uses' => 'API\CommerceController@addBasket']);
    $router->post('basket/edit', ['uses' => 'API\CommerceController@editBasket']);
    $router->post('basket/remove', ['uses' => 'API\CommerceController@removeBasket']);

    //Checkout - Physical Item
    $router->post('checkout/basket/cart/add', ['uses' => 'API\CommerceController@addToCartBasket']);
    $router->post('checkout/basket/preview', ['uses' => 'API\CommerceController@previewBasketCheckout']);
    $router->post('checkout/basket/booking', ['uses' => 'API\CommerceController@bookBasketCheckout']);

    //Checkout - Digital Voucher 
    $router->post('checkout/digital/cart/add', ['uses' => 'API\CommerceController@addToCartDigital']);
    $router->post('checkout/digital/preview', ['uses' => 'API\CommerceController@previewDigitalCheckout']);
    $router->post('checkout/digital/booking', ['uses' => 'API\CommerceController@bookDigitalCheckout']);

    //Initiate Group Buy
    $router->get('group_buy/show/{cg_id}', ['uses' => 'API\GroupBuyController@showGroupBuy']);
    $router->get('group_buy/check/{cg_id}', ['uses' => 'API\GroupBuyController@checkGroupBuy']);
    $router->get('group_buy/share/{order_id}', ['uses' => 'API\GroupBuyController@shareGroupBuy']);
    $router->get('group_buy/recommendation', ['uses' => 'API\GroupBuyController@recommendGroupBuy']);
    $router->post('group_buy/expired', ['uses' => 'API\GroupBuyController@expiredGroupBuy']);
    $router->post('group_buy/initiate', ['uses' => 'API\GroupBuyController@createGroupBuy']);
    $router->post('group_buy/join', ['uses' => 'API\GroupBuyController@joinGroupBuy']);

    //Commerce Basket - Group Buy
    $router->post('basket/group_buy', ['uses' => 'API\CommerceController@getBasketGB']);
    $router->post('basket/group_buy/add', ['uses' => 'API\CommerceController@addBasketGB']);
    $router->post('basket/group_buy/edit', ['uses' => 'API\CommerceController@editBasketGB']);
    $router->post('basket/group_buy/remove', ['uses' => 'API\CommerceController@removeBasketGB']);

    //Checkout - Group Buy
    $router->post('checkout/group_buy/cart/add', ['uses' => 'API\CommerceController@addToCartGroupBuy']);
    $router->post('checkout/group_buy/preview', ['uses' => 'API\CommerceController@previewGroupBuyCheckout']);
    $router->post('checkout/group_buy/booking', ['uses' => 'API\CommerceController@bookGroupBuyCheckout']);

    //Commerce Order Operation
    $router->post('order/cancel', ['uses' => 'API\OrderController@cancelOrder']);

    //Commerce Order Info
    $router->get('order/review', ['uses' => 'API\OrderController@reviewOrderItem']);
    $router->get('order/history', ['uses' => 'API\OrderController@historyOrder']);
    $router->get('order/history/{mode}', ['uses' => 'API\OrderController@historyOrder']);
    $router->get('order/history/{mode}/latest', ['uses' => 'API\OrderController@historyOrderLatest']);
    $router->get('order/detail/{order_id}', ['uses' => 'API\OrderController@detailOrder']);
    $router->get('order/track/{order_id}', ['uses' => 'API\OrderController@trackOrder']);
    $router->get('order/received/{order_id}', ['uses' => 'API\OrderController@packageReceived']);
    
    //affiliate
    $router->post('affiliate/generate', ['uses' => 'API\AffiliateController@generateAffiliate']);
    $router->get('affiliate/validate/{code}', ['uses' => 'API\AffiliateController@validateAffiliateCode']);

    //follow
    $router->get('follow/{user_uri}', ['uses' => 'API\FollowController@setUserFollow']);
    $router->get('unfollow/{user_uri}', ['uses' => 'API\FollowController@setUserUnfollow']);

    $router->get('follower/list/{user_id}', ['uses' => 'API\FollowController@listFollower']);
    $router->get('follower/list/{user_id}/{offset}', ['uses' => 'API\FollowController@listFollower']);
    $router->get('follower/check', ['uses' => 'API\FollowController@checkFollowerSelf']);
    $router->get('follower/check/{offset}', ['uses' => 'API\FollowController@checkFollowerSelf']);
    
    $router->get('following/list/{user_id}', ['uses' => 'API\FollowController@listFollowing']);
    $router->get('following/list/{user_id}/{offset}', ['uses' => 'API\FollowController@listFollowing']);
    $router->get('following/check', ['uses' => 'API\FollowController@checkFollowingSelf']);
    $router->get('following/check/{offset}', ['uses' => 'API\FollowController@checkFollowingSelf']);

    //newsfeed
    $router->get('home/feed', ['uses' => 'API\FeedController@mainFeed']);
    $router->post('load/feed', ['uses' => 'API\FeedController@loadMoreFeed']);
    $router->get('home/feed/date', ['uses' => 'API\FeedController@mainFeedDate']);
    $router->post('load/feed/date', ['uses' => 'API\FeedController@loadMoreFeedDate']);

    //redeem point
    $router->get('gamification/reward', ['uses' => 'API\GamificationController@getReward']);

    //menu stats
    $router->post('menu/stats/response', ['uses' => 'API\MenuController@postResponse']);
    $router->get('menu/stats/{menu_tag}/response', ['uses' => 'API\MenuController@getResponse']);

    //app version
    $router->get('app/version/{mode}', ['uses' => 'API\AuthController@checkVersion']);
});

$router->group(['prefix' => 'dash' , 'middleware' => 'dash-secure'], function()  use ($router){
    //Product
    $router->get('product/detail/{product_id}', ['uses' => 'Dash\ProductController@detailProduct']);
    $router->post('product/save', ['uses' => 'Dash\ProductController@saveProduct']);
    $router->put('product/edit', ['uses' => 'Dash\ProductController@editProduct']);
    $router->post('product/edit', ['uses' => 'Dash\ProductController@editProduct']);
    $router->get('product/delete/{product_id}', ['uses' => 'Dash\ProductController@deleteProduct']);

    //Product Variant
    $router->get('product/variant/list/{product_id}', ['uses' => 'Dash\ProductController@listProductVariant']);
    $router->get('product/variant/detail/{variant_id}', ['uses' => 'Dash\ProductController@detailProductVariant']);
    $router->post('product/variant/save', ['uses' => 'Dash\ProductController@saveProductVariant']);
    $router->put('product/variant/edit', ['uses' => 'Dash\ProductController@editProductVariant']);
    $router->post('product/variant/edit', ['uses' => 'Dash\ProductController@editProductVariant']);
    $router->get('product/variant/delete/{variant_id}', ['uses' => 'Dash\ProductController@deleteProductVariant']);
    $router->get('product/variant/publish/{variant_id}/{status}', ['uses' => 'Dash\ProductController@publishProductVariant']);
    $router->post('product/variant/bulk/upload', ['uses' => 'Dash\ProductController@uploadProductVariant']);

    //Product Gallery
    $router->post('product/image/save', ['uses' => 'Dash\ProductController@saveProductGallery']);
    $router->post('product/image/save/single', ['uses' => 'Dash\ProductController@saveProductGallerySingle']);
    $router->post('product/image/edit', ['uses' => 'Dash\ProductController@editProductGallery']);
    $router->get('product/image/delete/{prod_gallery_id}', ['uses' => 'Dash\ProductController@deleteProductGallery']);
    $router->get('product/image/main/{prod_gallery_id}', ['uses' => 'Dash\ProductController@setMainProductGallery']);
    $router->get('product/image/list/{product_id}', ['uses' => 'Dash\ProductController@listProductGallery']);

    //Digital Product
    $router->get('product/digital/detail/{product_id}', ['uses' => 'Dash\ProductController@detailProductDigital']);

    //Digital Product Bundle
    $router->post('product/voucher/save', ['uses' => 'Dash\ProductController@saveProductVoucher']);
    $router->post('product/voucher/edit', ['uses' => 'Dash\ProductController@editProductVoucher']);

    //Banner
    $router->post('banner/save', ['uses' => 'Dash\BannerController@saveBanner']);
    $router->post('banner/edit', ['uses' => 'Dash\BannerController@editBanner']);
    $router->get('banner/delete/{banner_id}', ['uses' => 'Dash\BannerController@deleteBanner']);

    //Curate Product Review
    $router->get('curate/post/detail/{content_id}', ['uses' => 'Dash\PostController@detailContent']);
    $router->post('curate/post/change/status', ['uses' => 'Dash\PostController@changeStatusContent']);

    //Article
    $router->post('article/save', ['uses' => 'Dash\PostController@saveArticle']);
    $router->post('article/edit', ['uses' => 'Dash\PostController@editArticle']);
    $router->get('article/detail/{content_id}', ['uses' => 'Dash\PostController@detailArticle']);

    //User
    $router->get('user/load/{user_id}', ['uses' => 'Dash\UserController@detailUser']);
    $router->get('user/content/load/{user_id}', ['uses' => 'Dash\UserController@loadContentPerUser']);

    //Product Review Assign
    $router->post('search/product', ['uses' => 'Dash\ProductController@searchProduct']);
    $router->post('assign/product/review', ['uses' => 'Dash\PostController@assignProduct']);

    //Collection
    $router->post('collection/save', ['uses' => 'Dash\CollectionController@saveCollection']);
    $router->post('collection/edit', ['uses' => 'Dash\CollectionController@editCollection']);
    $router->get('collection/publish/{collection_id}', ['uses' => 'Dash\CollectionController@showStatusCollection']);
    $router->get('collection/delete/{collection_id}', ['uses' => 'Dash\CollectionController@deleteCollection']);
    $router->get('collection/detail/{collection_id}', ['uses' => 'Dash\CollectionController@detailCollection']);
    $router->post('collection/item/assign', ['uses' => 'Dash\CollectionController@assignProductCollection']);
    $router->post('collection/item/remove', ['uses' => 'Dash\CollectionController@removeProductCollection']);

    //Point
    $router->post('point/add', ['uses' => 'Dash\PointController@addPointToUser']);
    $router->post('point/remove', ['uses' => 'Dash\PointController@removePointFromUser']);
    $router->get('point/multiplier/{mode}', ['uses' => 'Dash\PointController@multiplierTrigger']);
    $router->get('point/status/multiplier', ['uses' => 'Dash\PointController@multiplierStatus']);

    //Warehouse Agent
    $router->get('agent/detail/{agent_id}', ['uses' => 'Dash\AgentController@detailAgent']);
    $router->post('agent/save', ['uses' => 'Dash\AgentController@saveAgent']);
    $router->post('agent/edit', ['uses' => 'Dash\AgentController@editAgent']);
    $router->get('agent/delete/{agent_id}', ['uses' => 'Dash\AgentController@deleteAgent']);
    
    //Admin Notification
    $router->get('notification/show', ['uses' => 'Dash\PostController@showNotification']);
    $router->get('notification/read/all', ['uses' => 'Dash\PostController@readNotification']);
    $router->get('notification/read/{mode}/{id}', ['uses' => 'Dash\PostController@readNotificationContent']);

    //delivery
    $router->post('sicepat/pickup/request', ['uses' => 'Dash\DeliveryController@sicepatPickupRequest']);
    $router->post('sicepat/pickup/request/bulk', ['uses' => 'Dash\DeliveryController@sicepatPickupRequestBulk']);

    //order
    $router->get('order/detail/{booking_id}', ['uses' => 'Dash\BookingController@detailOrder']);
    $router->post('order/bulk/detail', ['uses' => 'Dash\BookingController@detailOrderBulk']);
    $router->post('order/search', ['uses' => 'Dash\BookingController@searchOrder']);
    $router->post('order/verified', ['uses' => 'Dash\BookingController@verifyOrder']);
    $router->post('order/pickup/verified', ['uses' => 'Dash\BookingController@verifyPickupOrder']);
    $router->post('order/pickup/verified/bulk', ['uses' => 'Dash\BookingController@verifyPickupOrderBulk']);
    
    //Affiliate-Reward
    $router->get('reward/detail/{reward_id}', ['uses' => 'Dash\RewardController@detailReward']);
    $router->post('reward/save', ['uses' => 'Dash\RewardController@saveReward']);
    $router->put('reward/edit', ['uses' => 'Dash\RewardController@editReward']);
    $router->post('reward/edit', ['uses' => 'Dash\RewardController@editReward']);
    $router->get('reward/delete/{reward_id}', ['uses' => 'Dash\RewardController@deleteReward']);

    //voucher management
    $router->get('voucher/detail/{voucher_id}', ['uses' => 'Dash\VoucherController@detailVoucher']);
    $router->post('voucher/save', ['uses' => 'Dash\VoucherController@saveVoucher']);
    $router->put('voucher/edit', ['uses' => 'Dash\VoucherController@editVoucher']);
    $router->post('voucher/edit', ['uses' => 'Dash\VoucherController@editVoucher']);
    $router->get('voucher/publish/{voucher_id}/{mode}', ['uses' => 'Dash\VoucherController@publishVoucher']);
    $router->get('voucher/delete/{voucher_id}', ['uses' => 'Dash\VoucherController@deleteVoucher']);

    //groupbuy
    $router->get('groupbuy/status/checker', ['uses' => 'Dash\GroupBuyController@checkStatusGroupBuy']);
    $router->get('groupbuy/detail/{cg_id}', ['uses' => 'Dash\GroupBuyController@detailGroupBuy']);
    $router->post('groupbuy/verify', ['uses' => 'Dash\GroupBuyController@verifyGroupBuy']);
    $router->post('groupbuy/merge', ['uses' => 'Dash\GroupBuyController@mergeGroupBuy']);
    $router->post('groupbuy/search', ['uses' => 'Dash\GroupBuyController@searchGroupBuy']);

    //app version
    $router->post('app/version/add', ['uses' => 'Dash\AppController@addVersion']);
    $router->post('app/version/edit', ['uses' => 'Dash\AppController@editVersion']);

    //test
    //$router->post('point', ['uses' => 'Dash\PostController@givePoint']);
    //$router->get('voucher', ['uses' => 'Dash\BookingController@giveVoucher']);
});

$router->group(['prefix' => 'scrapper'], function()  use ($router){
    //sicepat
    $router->get('sicepat/origin', ['uses' => 'Utility\SicepatController@scrapperOrigin']);
    $router->get('sicepat/destination', ['uses' => 'Utility\SicepatController@scrapperDestination']);
});

$router->group(['prefix' => 'payment'], function()  use ($router){
    //payment
    $router->post('midtrans/notify', ['uses' => 'Utility\PaymentController@midtransNotify']);
    $router->post('faspay/notify', ['uses' => 'Utility\FaspayController@paymentNotify']);
});

$router->group(['prefix' => 'cron', 'middleware' => 'cron-secure'], function()  use ($router){
    //cron
    $router->get('add_bogus', ['uses' => 'Utility\GroupBuyController@addBogusUser']);
    $router->get('cancel_payment', ['uses' => 'Utility\PaymentController@cronPaymentCancellation']);
    $router->get('product_scan', ['uses' => 'Utility\UtilityController@cronProductScan']);
    $router->get('order_scan', ['uses' => 'Utility\UtilityController@cronOrderScan']);
});

$router->get('payment_complete/{order_id}', ['uses' => 'Utility\PaymentController@payment_complete_test']);
$router->get('tracking/{receipt_number}', ['uses' => 'Utility\SicepatController@trackingSicepat_exe']);
$router->get('error/{mode}', ['uses' => 'Utility\UtilityController@replicateError']);
//$router->get('transaction_point/{price_amount}', ['uses' => 'Utility\UtilityController@countTransactionPoint']);
