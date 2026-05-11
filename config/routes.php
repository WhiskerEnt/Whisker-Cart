<?php
/**
 * WHISKER — Route Definitions
 */

use App\Controllers\Store\{HomeController, ProductController, CartController, CheckoutController, AccountController};
use App\Controllers\Admin\{
    AuthController, DashboardController,
    ProductController as AdminProductController,
    OrderController, CustomerController, CouponController,
    GatewayController, SettingsController, CategoryController,
    SeoController
};

// ── Storefront ───────────────────────────────────
$router->get('/',                    [HomeController::class, 'index']);
$router->get('/shop',               [HomeController::class, 'shop']);
$router->get('/product/{slug}',      [ProductController::class, 'show']);
$router->get('/category/{slug}',     [ProductController::class, 'category']);
$router->get('/search',              [ProductController::class, 'search']);

// Cart (AJAX)
$router->get('/cart',                [CartController::class, 'show']);
$router->post('/cart/add',           [CartController::class, 'add']);
$router->post('/cart/update',        [CartController::class, 'update']);
$router->post('/cart/remove',        [CartController::class, 'remove']);
$router->post('/cart/coupon',        [CartController::class, 'applyCoupon']);
$router->post('/cart/clear',         [CartController::class, 'clear']);

// Checkout
$router->get('/checkout',            [CheckoutController::class, 'index']);
$router->post('/checkout/process',   [CheckoutController::class, 'process'], ['csrf']);
$router->get('/order-success',       [CheckoutController::class, 'success']);

// Pages, Contact, Chatbot
$router->get('/page/{slug}',        [\App\Controllers\Store\PageController::class, 'show']);
$router->get('/contact',            [\App\Controllers\Store\PageController::class, 'contact']);
$router->post('/contact/submit',    [\App\Controllers\Store\PageController::class, 'submitContact'], ['csrf']);
$router->post('/chatbot/message',   [\App\Controllers\Store\ChatbotController::class, 'handle']);

// Customer Tickets
$router->get('/account/tickets',              [\App\Controllers\Store\TicketController::class, 'index']);
$router->get('/account/tickets/create',       [\App\Controllers\Store\TicketController::class, 'create']);
$router->post('/account/tickets/store',       [\App\Controllers\Store\TicketController::class, 'store'], ['csrf']);
$router->get('/account/tickets/{id}',         [\App\Controllers\Store\TicketController::class, 'show']);
$router->post('/account/tickets/reply/{id}',  [\App\Controllers\Store\TicketController::class, 'reply'], ['csrf']);

// Customer Account
$router->get('/account/register',          [AccountController::class, 'showRegister']);
$router->post('/account/register',         [AccountController::class, 'register'], ['csrf']);
$router->get('/account/login',             [AccountController::class, 'showLogin']);
$router->post('/account/login',            [AccountController::class, 'login'], ['csrf']);
$router->get('/account/logout',            [AccountController::class, 'logout']);
$router->get('/account',                   [AccountController::class, 'dashboard']);
$router->get('/account/profile',           [AccountController::class, 'profile']);
$router->post('/account/profile',          [AccountController::class, 'updateProfile'], ['csrf']);
$router->post('/account/set-password',     [AccountController::class, 'setPassword'], ['csrf']);
$router->get('/account/addresses',         [AccountController::class, 'addresses']);
$router->post('/account/addresses/store',  [AccountController::class, 'storeAddress'], ['csrf']);
$router->post('/account/addresses/delete/{id}', [AccountController::class, 'deleteAddress'], ['csrf']);
$router->get('/account/orders',            [AccountController::class, 'orders']);
$router->get('/account/order/{id}',        [AccountController::class, 'orderDetail']);
$router->post('/account/order/cancel/{id}',[AccountController::class, 'cancelOrder'], ['csrf']);
$router->get('/account/forgot-password',   [AccountController::class, 'showForgotPassword']);
$router->post('/account/forgot-password',  [AccountController::class, 'forgotPassword'], ['csrf']);
$router->get('/account/reset-password',    [AccountController::class, 'showResetPassword']);
$router->post('/account/reset-password',   [AccountController::class, 'resetPassword'], ['csrf']);

// ── Admin Auth ───────────────────────────────────
$router->get('/admin/login',         [AuthController::class, 'showLogin']);
$router->post('/admin/login',        [AuthController::class, 'login'], ['csrf']);
$router->get('/admin/logout',        [AuthController::class, 'logout']);

// ── Admin Panel (auth + csrf protected) ──────────
$router->group(['prefix' => '/admin', 'middleware' => ['auth', 'csrf']], function ($r) {

    $r->get('',                          [DashboardController::class, 'index']);
    $r->get('/dashboard',                [DashboardController::class, 'index']);

    // System Updates
    $r->post('/update/check',            [DashboardController::class, 'checkUpdate']);
    $r->post('/update/apply',            [DashboardController::class, 'applyUpdate']);
    $r->post('/update/dismiss',          [DashboardController::class, 'dismissUpdate']);
    $r->post('/update/rollback',         [DashboardController::class, 'rollback']);

    // Products
    $r->get('/products',                 [AdminProductController::class, 'index']);
    $r->get('/products/create',          [AdminProductController::class, 'create']);
    $r->post('/products/store',          [AdminProductController::class, 'store']);
    $r->get('/products/edit/{id}',       [AdminProductController::class, 'edit']);
    $r->post('/products/update/{id}',    [AdminProductController::class, 'update']);
    $r->post('/products/delete/{id}',    [AdminProductController::class, 'delete']);

    // Product Images (AJAX)
    $r->post('/products/upload-image',           [AdminProductController::class, 'uploadImage']);
    $r->post('/products/delete-image/{id}',      [AdminProductController::class, 'deleteImage']);
    $r->post('/products/set-primary-image/{id}', [AdminProductController::class, 'setPrimaryImage']);
    $r->post('/products/quick-category',         [AdminProductController::class, 'quickCategory']);

    // Orders
    $r->get('/orders',                   [OrderController::class, 'index']);
    $r->get('/orders/{id}',              [OrderController::class, 'show']);
    $r->post('/orders/status/{id}',      [OrderController::class, 'updateStatus']);
    $r->post('/orders/shipping/{id}',    [OrderController::class, 'updateShipping']);
    $r->get('/orders/invoice/{id}',      [OrderController::class, 'invoice']);

    // Product Variants (AJAX)
    $r->post('/products/variants/save/{id}',        [AdminProductController::class, 'saveVariants']);
    $r->post('/products/variants/update-combo/{id}', [AdminProductController::class, 'updateCombo']);
    $r->post('/products/variants/upload-option-image', [AdminProductController::class, 'uploadVariantOptionImage']);
    $r->post('/products/variants/delete-option-image/{id}', [AdminProductController::class, 'deleteVariantOptionImage']);

    // Customers
    $r->get('/customers',                [CustomerController::class, 'index']);
    $r->get('/customers/{id}',          [CustomerController::class, 'show']);

    // Categories
    $r->get('/categories',               [CategoryController::class, 'index']);
    $r->get('/categories/create',        [CategoryController::class, 'create']);
    $r->post('/categories/store',        [CategoryController::class, 'store']);
    $r->get('/categories/edit/{id}',     [CategoryController::class, 'edit']);
    $r->post('/categories/update/{id}',  [CategoryController::class, 'update']);
    $r->post('/categories/delete/{id}',  [CategoryController::class, 'delete']);

    // Coupons
    $r->get('/coupons',                  [CouponController::class, 'index']);
    $r->get('/coupons/create',           [CouponController::class, 'create']);
    $r->post('/coupons/store',           [CouponController::class, 'store']);
    $r->post('/coupons/delete/{id}',     [CouponController::class, 'delete']);

    // Payment Gateways
    $r->get('/gateways',                 [GatewayController::class, 'index']);
    $r->post('/gateways/toggle',         [GatewayController::class, 'toggle']);
    $r->post('/gateways/configure',      [GatewayController::class, 'configure']);

    // Settings
    $r->get('/settings',                 [SettingsController::class, 'index']);
    $r->post('/settings/update',         [SettingsController::class, 'update']);
    $r->post('/settings/change-password',[SettingsController::class, 'changePassword']);
    $r->post('/settings/test-smtp',      [SettingsController::class, 'testSmtp']);

    // Support Tickets
    $r->get('/tickets',               [\App\Controllers\Admin\TicketController::class, 'index']);
    $r->get('/tickets/{id}',          [\App\Controllers\Admin\TicketController::class, 'show']);
    $r->post('/tickets/reply/{id}',   [\App\Controllers\Admin\TicketController::class, 'reply']);
    $r->post('/tickets/status/{id}',  [\App\Controllers\Admin\TicketController::class, 'updateStatus']);

    // Pages (Policies)
    $r->get('/pages',             [\App\Controllers\Admin\PageController::class, 'index']);
    $r->get('/pages/create',      [\App\Controllers\Admin\PageController::class, 'create']);
    $r->post('/pages/store',      [\App\Controllers\Admin\PageController::class, 'store']);
    $r->get('/pages/edit/{id}',   [\App\Controllers\Admin\PageController::class, 'edit']);
    $r->post('/pages/update/{id}',[\App\Controllers\Admin\PageController::class, 'update']);
    $r->post('/pages/delete/{id}',[\App\Controllers\Admin\PageController::class, 'delete']);

    // Email Templates
    $r->get('/email-templates',                [\App\Controllers\Admin\EmailTemplateController::class, 'index']);
    $r->get('/email-templates/create',         [\App\Controllers\Admin\EmailTemplateController::class, 'create']);
    $r->post('/email-templates/store',         [\App\Controllers\Admin\EmailTemplateController::class, 'store']);
    $r->get('/email-templates/edit/{id}',      [\App\Controllers\Admin\EmailTemplateController::class, 'edit']);
    $r->post('/email-templates/update/{id}',   [\App\Controllers\Admin\EmailTemplateController::class, 'update']);
    $r->post('/email-templates/delete/{id}',   [\App\Controllers\Admin\EmailTemplateController::class, 'delete']);
    $r->get('/email-templates/preview/{id}',   [\App\Controllers\Admin\EmailTemplateController::class, 'preview']);
    $r->post('/email-templates/test-send/{id}', [\App\Controllers\Admin\EmailTemplateController::class, 'testSend']);

    // Abandoned Carts
    $r->get('/abandoned-carts',                    [\App\Controllers\Admin\AbandonedCartController::class, 'index']);
    $r->get('/abandoned-carts/{id}',               [\App\Controllers\Admin\AbandonedCartController::class, 'show']);
    $r->post('/abandoned-carts/send-reminder/{id}', [\App\Controllers\Admin\AbandonedCartController::class, 'sendReminder']);
    $r->post('/abandoned-carts/mark-abandoned/{id}',[\App\Controllers\Admin\AbandonedCartController::class, 'markAbandoned']);

    // Shipping Carriers
    $r->get('/shipping',                 [\App\Controllers\Admin\ShippingController::class, 'index']);
    $r->post('/shipping/store',          [\App\Controllers\Admin\ShippingController::class, 'store']);
    $r->post('/shipping/update/{id}',    [\App\Controllers\Admin\ShippingController::class, 'update']);
    $r->post('/shipping/delete/{id}',    [\App\Controllers\Admin\ShippingController::class, 'delete']);
    $r->get('/shipping/settings',        [\App\Controllers\Admin\ShippingController::class, 'settings']);
    $r->post('/shipping/settings/update',[\App\Controllers\Admin\ShippingController::class, 'updateSettings']);

    // SEO
    $r->get('/seo',                      [SeoController::class, 'index']);
    $r->post('/seo/update',              [SeoController::class, 'update']);
    $r->post('/seo/generate-sitemap',    [SeoController::class, 'generateSitemap']);
    $r->post('/seo/generate-robots',     [SeoController::class, 'generateRobots']);

    // CSV Import
    $r->get('/import',                   [\App\Controllers\Admin\ImportController::class, 'index']);
    $r->post('/import/process',          [\App\Controllers\Admin\ImportController::class, 'process']);
    $r->get('/import/sample/{type}',     [\App\Controllers\Admin\ImportController::class, 'sample']);
});

// ── Plugin Routes (webhooks) ─────────────────────
\Core\PluginManager::registerRoutes($router);