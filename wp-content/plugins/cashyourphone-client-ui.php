<?php
/*
Plugin Name: CashYourPhone Client UI
Description: Beautiful, simplified and safe admin panel for the CashYourPhone client user.
Author: You
Version: 2.1
*/

if (!defined('ABSPATH')) exit;

/**
 * Identify the client user by username
 */
function cyf_is_client_user() {
    $user = wp_get_current_user();
    return ($user && $user->user_login === 'CashYourPhone');
}

/* =======================================================
 * 1. CLEAN ADMIN MENU (Left Sidebar)
 * ======================================================= */
function cyf_client_customize_admin_menu() {
    if (!cyf_is_client_user()) return;

    $remove = [
        'edit.php',                      // Posts
        'edit.php?post_type=page',       // Pages
        'upload.php',                    // Media
        'themes.php',                    // Appearance
        'plugins.php',                   // Plugins
        'tools.php',                     // Tools
        'options-general.php',           // Settings
        'edit-comments.php',             // Comments
        'acf',                           // Advanced Custom Fields
        'cptui_main_menu',               // CPT UI
    ];

    foreach ($remove as $menu) {
        remove_menu_page($menu);
    }
}
add_action('admin_menu', 'cyf_client_customize_admin_menu', 999);

/* =======================================================
 * 2. CLEAN TOP ADMIN BAR
 * ======================================================= */
function cyf_client_admin_bar($wp_admin_bar) {
    if (!cyf_is_client_user()) return;

    $nodes = [
        'wp-logo', 'about', 'wporg', 'documentation', 'support-forums',
        'feedback', 'updates', 'customize', 'themes', 'comments', 'new-content'
    ];

    foreach ($nodes as $node) {
        $wp_admin_bar->remove_node($node);
    }
}
add_action('admin_bar_menu', 'cyf_client_admin_bar', 999);

/* =======================================================
 * 3. AESTHETIC CUSTOM DASHBOARD WIDGET
 * ======================================================= */
function cyf_client_dashboard_widget() {
    if (!cyf_is_client_user()) return;

    remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
    remove_meta_box('dashboard_primary', 'dashboard', 'side');
    remove_meta_box('dashboard_activity', 'dashboard', 'normal');
    remove_meta_box('dashboard_right_now', 'dashboard', 'normal');

    wp_add_dashboard_widget(
        'cyf_client_welcome',
        '',
        'cyf_client_dashboard_widget_render'
    );
}
add_action('wp_dashboard_setup', 'cyf_client_dashboard_widget');

function cyf_client_dashboard_widget_render() {
    ?>
    <style>
        .cyf-panel {
            background: #ffffff;
            padding: 35px 45px;
            border-radius: 14px;
            box-shadow: 0 4px 28px rgba(0,0,0,0.08);
            max-width: 850px;
            margin: 25px auto;
            font-size: 17px;
            line-height: 1.8;
        }
        .cyf-panel h2 {
            font-size: 30px;
            margin-bottom: 12px;
            font-weight: 700;
        }
        .cyf-panel ul li {
            margin-bottom: 10px;
            font-size: 18px;
        }
        #wpfooter { display:none !important; }
    </style>

    <div class="cyf-panel">
        <h2>Welcome to <strong>CashYourPhone Store Panel</strong> 👋</h2>
        <p>This is your simplified and secure control panel.</p>

        <ul>
            <li>📱 <strong>Devices</strong> – Add / edit phones, prices, conditions, images.</li>
            <li>🛒 <strong>Sell Requests</strong> – View customer requests to sell phones.</li>
            <li>⭐ <strong>Reviews</strong> – Approve or edit customer reviews.</li>
            <li>🖼 <strong>Hero Slides</strong> – Edit homepage slider images & text.</li>
            <li>🏷 <strong>Brands</strong> – Manage phone brands displayed on the site.</li>
            <li>✨ <strong>Features</strong> – Edit “Why CashYourPhone” homepage features.</li>
        </ul>

        <p style="margin-top:15px;">If you are unsure about anything, please contact your developer.</p>
    </div>
    <?php
}

/* =======================================================
 * 4. HIDE WORDPRESS NOTICES FOR CLIENT
 * ======================================================= */
function cyf_client_hide_admin_notices() {
    if (!cyf_is_client_user()) return;

    echo '<style>
        .update-nag, .notice.notice-warning, .notice.notice-error,
        .notice.is-dismissible, .notice-success {
            display:none !important;
        }
    </style>';
}
add_action('admin_head', 'cyf_client_hide_admin_notices');

/* =======================================================
 * 5. RESTRICT DIRECT ACCESS TO FORBIDDEN SCREENS
 * ======================================================= */
function cyf_client_force_safe_pages() {
    if (!cyf_is_client_user()) return;

    $screen = get_current_screen();
    if (!$screen) return;

    $blocked = [
        'plugins', 'themes', 'options-general', 'tools',
        'edit-post', 'edit-page'
    ];

    if (in_array($screen->id, $blocked, true)) {
        wp_safe_redirect(admin_url('edit.php?post_type=cyf_device'));
        exit;
    }
}
add_action('current_screen', 'cyf_client_force_safe_pages');

/* =======================================================
 * 6. GLOBAL UI STYLING + BUTTONS (MERGED + CLEANED)
 * ======================================================= */
add_action('admin_head', function () {
    if (!cyf_is_client_user()) return;

    ?>
    <style>
        /* Modern clean background */
        body.wp-admin {
            background: #f5f7fa !important;
            font-size: 15px !important;
        }

        /* Sidebar menu spacing */
        #adminmenu .wp-menu-name {
            font-size: 15px !important;
            padding: 10px 0 !important;
        }

        #adminmenu li a {
            padding-top: 10px !important;
            padding-bottom: 10px !important;
        }

        /* Table styling */
        .wp-list-table th, 
        .wp-list-table td {
            padding: 14px 12px !important;
            font-size: 15px !important;
        }

        /* Hover highlight */
        .wp-list-table tbody tr:hover td {
            background: #eef2ff !important;
        }

        /* Rounded card list tables */
        .wrap .wp-list-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #ddd;
        }

        /* Top admin bar */
        #wpadminbar {
            background: #111827 !important;
        }

        /* ================================
           ADD BUTTON / PRIMARY BUTTON
           CLEAN MERGED VERSION
        =================================*/
        .page-title-action,
        .button-primary {
            background-color: #000 !important;
            color: #fff !important;
            border: 1px solid #000 !important;
            border-radius: 6px !important;
            padding: 6px 14px !important;
            font-weight: 600 !important;
            transition: 0.2s ease-in-out;
        }

        .page-title-action:hover,
        .button-primary:hover {
            background-color: #fff !important;
            color: #000 !important;
            border-color: #000 !important;
        }
    </style>
    <?php
});
