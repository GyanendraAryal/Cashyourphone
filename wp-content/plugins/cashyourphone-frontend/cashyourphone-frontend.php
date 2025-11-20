<?php


/**
 * Added helper: look for Vite manifest in multiple candidate locations,
 * and provide server-side upload validation helpers for public endpoints.
 */

if ( ! function_exists( 'cyf_find_manifest' ) ) {
    function cyf_find_manifest() {
        $plugin_dir = plugin_dir_path( __FILE__ );
        $candidates = array(
            $plugin_dir . 'build/.vite/manifest.json',
            $plugin_dir . 'build/manifest.json',
            $plugin_dir . 'build/manifest.webpack.json',
        );
        foreach ( $candidates as $candidate ) {
            if ( file_exists( $candidate ) ) {
                return $candidate;
            }
        }
        return false;
    }
}

if ( ! function_exists( 'cyf_validate_uploaded_file' ) ) {
    function cyf_validate_uploaded_file( $file_array ) {
        // Basic checks: size & mime type & extension
        $max_bytes = 5 * 1024 * 1024; // 5 MB
        if ( empty( $file_array ) || empty( $file_array['tmp_name'] ) ) {
            return new WP_Error( 'no_file', 'No file uploaded.' );
        }
        if ( $file_array['size'] > $max_bytes ) {
            return new WP_Error( 'file_too_large', 'Uploaded file exceeds the 5MB limit.' );
        }
        $check = wp_check_filetype_and_ext( $file_array['tmp_name'], $file_array['name'] );
        $allowed_mimes = array( 'image/jpeg', 'image/png', 'image/webp' );
        if ( empty( $check['type'] ) || ! in_array( $check['type'], $allowed_mimes, true ) ) {
            return new WP_Error( 'bad_file_type', 'Only JPG, PNG or WEBP images are allowed.' );
        }
        return true;
    }
}
/**
 * Plugin Name: CashYourPhone Frontend Bridge
 * Description: Adds CPTs and REST endpoints for CashYourPhone React app. Supports devices, reviews (with uploads), brands, hero slides, features, and sell requests.
 * Version: 1.4.3
 * Author: Nobody
 * Text Domain: cashyourphone-frontend
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* -------------------------
 * Constants
 * ------------------------- */
define( 'CYF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CYF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/* -------------------------
 * Utilities
 * ------------------------- */

/**
 * Resize uploaded image (best-effort)
 */
function cyf_resize_uploaded_image( $file_path, $max_width = 1200, $max_height = 800 ) {
    if ( ! function_exists( 'wp_get_image_editor' ) ) {
        return $file_path;
    }
    $editor = wp_get_image_editor( $file_path );
    if ( is_wp_error( $editor ) ) {
        return $file_path;
    }
    $editor->resize( $max_width, $max_height, false );
    $saved = $editor->save( $file_path );
    if ( ! is_wp_error( $saved ) && isset( $saved['path'] ) ) {
        return $saved['path'];
    }
    return $file_path;
}

/* ======================================================
   CPT: Devices
====================================================== */
function cyf_register_cpt_device() {
    register_post_type( 'cyf_device', [
        'label'         => 'cyf_device',
        'labels'        => [ 'name' => 'Devices', 'singular_name' => 'Device' ],
        'public'        => true,
        'has_archive'   => true,
        'show_in_rest'  => true,
        'rest_base'     => 'cyf_device',
        'supports'      => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
        'menu_position' => 20,
        'menu_icon'     => 'dashicons-smartphone',
    ] );
}
add_action( 'init', 'cyf_register_cpt_device' );

/* ======================================================
   Device REST Fields (Improved)
====================================================== */
function cyf_register_device_rest_fields() {
    $fields = [ 'brand', 'price', 'condition', 'availability', 'feature', 'sku', 'is_featured' ];

    foreach ( $fields as $field ) {
        register_rest_field( 'cyf_device', $field, [
            'get_callback' => function( $obj ) use ( $field ) {
                $val = get_post_meta( $obj['id'], $field, true );
                return $val !== '' ? $val : null;
            },
            'schema' => [ 'type' => 'string' ],
        ] );
    }

    register_rest_field( 'cyf_device', 'image', [
        'get_callback' => function( $obj ) {
            $id = get_post_thumbnail_id( $obj['id'] );
            if ( $id ) {
                return wp_get_attachment_url( $id );
            }
            // plugin asset fallback
            return CYF_PLUGIN_URL . 'assets/placeholder.png';
        },
        'schema' => [ 'type' => 'string' ],
    ] );
}
add_action( 'rest_api_init', 'cyf_register_device_rest_fields' );

/* ======================================================
   Device Meta: is_featured
====================================================== */
function cyf_register_device_meta() {
    register_post_meta( 'cyf_device', 'is_featured', [
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'string',
    ] );
}
add_action( 'init', 'cyf_register_device_meta' );

/* ======================================================
   CPT: Reviews
====================================================== */
function cyf_register_cpt_review() {
    register_post_type( 'cyf_review', [
        'label'        => 'cyf_review',
        'labels'       => [ 'name' => 'Reviews', 'singular_name' => 'Review' ],
        'public'       => true,
        'has_archive'  => false,
        'show_in_rest' => true,
        'rest_base'    => 'cyf_review',
        'supports'     => [ 'title', 'editor', 'thumbnail' ],
        'menu_icon'    => 'dashicons-star-filled',
    ] );
}
add_action( 'init', 'cyf_register_cpt_review' );

/* ======================================================
   Review REST fields
====================================================== */
function cyf_register_review_rest_fields() {
    register_rest_field( 'cyf_review', 'rating', [
        'get_callback' => function( $o ) { return intval( get_post_meta( $o['id'], 'rating', true ) ); },
        'schema'       => [ 'type' => 'integer' ],
    ] );

    register_rest_field( 'cyf_review', 'email', [
        'get_callback' => function( $o ) { return get_post_meta( $o['id'], 'email', true ); },
        'schema'       => [ 'type' => 'string' ],
    ] );

    register_rest_field( 'cyf_review', 'avatar', [
        'get_callback' => function( $o ) {
            $val = get_post_meta( $o['id'], 'avatar', true );
            return is_numeric( $val ) ? wp_get_attachment_url( $val ) : esc_url_raw( $val );
        },
        'schema' => [ 'type' => 'string' ],
    ] );
}
add_action( 'rest_api_init', 'cyf_register_review_rest_fields' );

/* ======================================================
   POST Review API (public)
====================================================== */
function cyf_handle_public_review_post( WP_REST_Request $request ) {
    if ( ! function_exists( 'wp_handle_upload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }
    if ( ! function_exists( 'media_handle_sideload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
    }

    $params = $request->get_body_params();
    $files  = $request->get_file_params();

    $name    = sanitize_text_field( $params['name'] ?? '' );
    $email   = sanitize_email( $params['email'] ?? '' );
    $rating  = intval( $params['rating'] ?? 0 );
    $content = wp_kses_post( $params['content'] ?? '' );
    $avatar  = '';

    $errors = [];
    if ( ! $name ) {
        $errors[] = 'Name is required.';
    }
    if ( ! $email || ! is_email( $email ) ) {
        $errors[] = 'Valid email is required.';
    }
    if ( $rating < 1 || $rating > 5 ) {
        $errors[] = 'Rating must be 1–5.';
    }
    if ( ! $content ) {
        $errors[] = 'Review content required.';
    }

    // Handle avatar upload (optional)
    if ( ! empty( $files['avatar'] ) && is_array( $files['avatar'] ) ) {
        $uploaded = wp_handle_upload( $files['avatar'], [ 'test_form' => false ] );

        if ( isset( $uploaded['error'] ) ) {
            $errors[] = 'Avatar upload failed: ' . $uploaded['error'];
        } else {
            $file_array = [
                'name'     => basename( $uploaded['file'] ),
                'tmp_name' => $uploaded['file'],
            ];

            $attach_id = /* validate uploaded file */
$validation_result = cyf_validate_uploaded_file( $file_array ?? $file );
if ( is_wp_error( $validation_result ) ) { return $validation_result; }
media_handle_sideload( $file_array, 0 );
            if ( is_wp_error( $attach_id ) ) {
                $errors[] = 'Could not attach avatar: ' . $attach_id->get_error_message();
                $avatar = isset( $uploaded['url'] ) ? esc_url_raw( $uploaded['url'] ) : '';
            } else {
                $avatar = wp_get_attachment_url( $attach_id );
            }
        }
    }

    if ( $errors ) {
        return new WP_REST_Response( [ 'errors' => $errors ], 422 );
    }

    $post_id = wp_insert_post( [
        'post_type'    => 'cyf_review',
        'post_title'   => wp_trim_words( $name, 10, '' ),
        'post_content' => $content,
        'post_status' => 'pending',
    ] );

    if ( is_wp_error( $post_id ) || $post_id == 0 ) {
        return new WP_REST_Response( [ 'error' => 'Could not create review.' ], 500 );
    }

    update_post_meta( $post_id, 'rating', $rating );
    update_post_meta( $post_id, 'email', $email );
    if ( $avatar ) {
        update_post_meta( $post_id, 'avatar', esc_url_raw( $avatar ) );
    }

    return new WP_REST_Response( [ 'success' => true, 'id' => $post_id, 'avatar' => $avatar ], 201 );
}
add_action( 'rest_api_init', function() {
    register_rest_route( 'wp/v2', '/cyf_review', [
        'methods'             => 'POST',
        'callback'            => 'cyf_handle_public_review_post',
        'permission_callback' => '__return_true',
    ] );
} );

/* ======================================================
   CORS (Dev + Production)
====================================================== */
add_action( 'rest_api_init', function() {
    $origin = get_http_origin();
    $allowed_origins = [
        'http://localhost:5173',
        site_url(),
    ];

    if ( $origin && in_array( $origin, $allowed_origins, true ) ) {
        add_filter( 'rest_pre_serve_request', function( $value ) use ( $origin ) {
            header( "Access-Control-Allow-Origin: $origin" );
            header( "Access-Control-Allow-Methods: GET, POST, OPTIONS" );
            header( "Access-Control-Allow-Credentials: true" );
            header( "Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce" );
            return $value;
        } );
    }
} );

/* ======================================================
   Frontend Build Loader — FINAL CLEAN VERSION
====================================================== */
function cyf_enqueue_build_assets() {
    $plugin_dir = CYF_PLUGIN_DIR;
    $plugin_url = CYF_PLUGIN_URL;

    // Detect Vite dev server
    $vite_dev = 'http://localhost:5173';
    $dev_running = false;

    if (function_exists('wp_remote_get')) {
        $res = wp_remote_get($vite_dev, ['timeout' => 1]);
        if (!is_wp_error($res)) {
            $dev_running = true;
        }
    }

    // DEV MODE
    if ($dev_running) {
        wp_enqueue_script(
            'vite-client',
            $vite_dev . '/@vite/client',
            [],
            null,
            true
        );

        wp_enqueue_script(
            'cyf-dev',
            $vite_dev . '/src/main.jsx',
            ['vite-client'],
            null,
            true
        );

        return;
    }

    // PROD MODE – load from /build
    $manifest_path = cyf_find_manifest();
    if (!file_exists($manifest_path)) return;

    $manifest = json_decode(file_get_contents($manifest_path), true);
    $entry = $manifest['src/main.jsx'];

    // CSS
    if (!empty($entry['css'])) {
        foreach ($entry['css'] as $css) {
            wp_enqueue_style(
                'cyf-style-' . md5($css),
                $plugin_url . 'build/' . $css,
                [],
                null
            );
        }
    }

    // JS (type=module)
    wp_enqueue_script(
        'cyf-app',
        $plugin_url . 'build/' . $entry['file'],
        [],
        null,
        true
    );
    wp_script_add_data('cyf-app', 'type', 'module');
}
add_action('wp_enqueue_scripts', 'cyf_enqueue_build_assets');


/* ======================================================
   Activation / Deactivation
====================================================== */
register_activation_hook( __FILE__, function() {
    // Ensure CPTs are registered on activation
    cyf_register_cpt_device();
    cyf_register_cpt_review();
    cyf_register_cpt_feature();
    cyf_register_brand_cpt();
    cyf_register_hero_slides_cpt();
    cyf_register_cpt_sell_request();
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/* ======================================================
   Contact options REST (single registration)
====================================================== */
function cyf_get_contact_options() {
    return [
        'contact_phone'     => get_option( 'cyf_contact_phone', '+977-9812345678' ),
        'contact_whatsapp'  => get_option( 'cyf_contact_whatsapp', '+977-9812345678' ),
        'contact_address'   => get_option( 'cyf_contact_address', 'Sonauli Border' ),
        'contact_maps_link' => get_option( 'cyf_contact_maps_link', 'https://google.com' ),
    ];
}

add_action( 'rest_api_init', function() {
    register_rest_route( 'cyf/v1', '/contact', [
        'methods'             => 'GET',
        'callback'            => 'cyf_get_contact_options',
        'permission_callback' => '__return_true',
    ] );
} );

/* ======================================================
   Sell Requests CPT + Endpoint
====================================================== */
function cyf_register_cpt_sell_request() {
    register_post_type( 'cyf_sell_request', [
        'label'        => 'cyf_sell_request',
        'public'       => false,
        'show_ui'      => true,
        'show_in_rest' => true,
        'supports'     => [ 'title', 'editor', 'custom-fields' ],
        'menu_icon'    => 'dashicons-cart',
    ] );
}
add_action( 'init', 'cyf_register_cpt_sell_request' );

add_action( 'rest_api_init', function() {
    register_rest_route( 'wp/v2', '/cyf_sell_request', [
        'methods'             => 'POST',
        'callback'            => 'cyf_handle_sell_post',
        'permission_callback' => '__return_true',
    ] );
} );

function cyf_handle_sell_post( WP_REST_Request $req ) {
    $p = $req->get_body_params();
    $f = $req->get_file_params();

    $errors = [];

    $brand = sanitize_text_field( $p['brand'] ?? '' );
    $model = sanitize_text_field( $p['model'] ?? '' );
    $name  = sanitize_text_field( $p['name'] ?? '' );
    $phone = sanitize_text_field( $p['phone'] ?? '' );

    if ( ! $brand ) {
        $errors[] = 'Brand is required.';
    }
    if ( ! $model ) {
        $errors[] = 'Model is required.';
    }
    if ( ! $name ) {
        $errors[] = 'Name is required.';
    }
    if ( ! $phone ) {
        $errors[] = 'Phone is required.';
    }

    if ( ! empty( $errors ) ) {
        return new WP_REST_Response( [ 'errors' => $errors ], 422 );
    }

    $id = wp_insert_post( [
        'post_type'    => 'cyf_sell_request',
        'post_title'   => $brand . ' ' . $model,
        'post_content' => "Name: $name\nPhone: $phone",
        'post_status' => 'pending',
    ] );

    if ( is_wp_error( $id ) ) {
        return new WP_REST_Response( [ 'error' => 'Could not save request' ], 500 );
    }

    return new WP_REST_Response( [ 'success' => true, 'id' => $id ], 201 );
}

/* ======================================================
   Features + Brands + Hero Slides CPTs
====================================================== */
function cyf_register_cpt_feature(){
    register_post_type('cyf_feature',[
        'label' => 'Features',
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'supports' => ['title', 'editor', 'custom-fields'],
        'menu_icon' => 'dashicons-star-filled'
    ]);
}
add_action('init', 'cyf_register_cpt_feature', 5);


register_post_meta( 'cyf_feature', 'icon', [
    'show_in_rest' => true,
    'single'       => true,
    'type'         => 'string',
] );

function cyf_register_hero_slides_cpt() {
    register_post_type( 'hero_slides', [
        'label'        => 'Hero Slides',
        'public'       => true,
        'show_in_rest' => true,
        'supports'     => [ 'title', 'editor', 'thumbnail' ],
        'menu_icon'    => 'dashicons-images-alt2',
    ] );
}
add_action( 'init', 'cyf_register_hero_slides_cpt' );

function cyf_register_brand_cpt() {
    register_post_type( 'cyf_brand', [
        'label'        => 'Brands',
        'public'       => true,
        'show_in_rest' => true,
        'supports'     => [ 'title', 'thumbnail' ],
        'menu_icon'    => 'dashicons-tag',
    ] );
}
add_action( 'init', 'cyf_register_brand_cpt' );

/* ===========================================
   SHORTCODE: [cashyourphone_app]
   Outputs <div id="cyf-root"></div>
=========================================== */

add_shortcode('cashyourphone_app', function() {
    return '<div id="cyf-root"></div>';
});

/**
 * Inject React root into the Front Page
 */
add_filter('the_content', function ($content) {

    // Only replace the homepage
    if (is_front_page()) {

        // React root for WordPress mounting
        return '<div id="cyf-root"></div>';
    }

    return $content;
});

