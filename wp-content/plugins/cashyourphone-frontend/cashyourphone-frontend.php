<?php
/**
 * Plugin Name: CashYourPhone Frontend Bridge
 * Description: Adds CPTs and REST endpoints for the CashYourPhone React app. Supports devices, reviews (with uploads), brands, hero slides, features, and sell requests.
 * Version: 1.5.0
 * Author: Nobody
 * Text Domain: cashyourphone-frontend
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* --------------------------------------------------
 * Constants
 * -------------------------------------------------- */
define( 'CYF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CYF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/* --------------------------------------------------
 * Helpers
 * -------------------------------------------------- */

/**
 * Find Vite / build manifest in common locations.
 */
if ( ! function_exists( 'cyf_find_manifest' ) ) {
    function cyf_find_manifest() {
        $plugin_dir = CYF_PLUGIN_DIR;
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

/**
 * Basic file validation for public uploads.
 */
if ( ! function_exists( 'cyf_validate_uploaded_file' ) ) {
    function cyf_validate_uploaded_file( $file_array ) {
        $max_bytes = 5 * 1024 * 1024; // 5 MB

        if ( empty( $file_array ) || empty( $file_array['tmp_name'] ) ) {
            return new WP_Error( 'no_file', 'No file uploaded.' );
        }
        if ( ! empty( $file_array['size'] ) && $file_array['size'] > $max_bytes ) {
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
 * Best-effort resize for uploaded images.
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
 * CPT: Devices (cyf_device)
 * ==================================================== */

function cyf_register_cpt_device() {
    register_post_type(
        'cyf_device',
        array(
            'label'         => 'Devices',
            'labels'        => array(
                'name'          => 'Devices',
                'singular_name' => 'Device',
            ),
            'public'        => true,
            'has_archive'   => true,
            'show_in_rest'  => true,
            'rest_base'     => 'cyf_device',
            'supports'      => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
            'menu_position' => 20,
            'menu_icon'     => 'dashicons-smartphone',
        )
    );
}
add_action( 'init', 'cyf_register_cpt_device', 0 );

/**
 * Extra REST fields for devices.
 */
function cyf_register_device_rest_fields() {
    // Simple meta fields (ACF + manual)
    $fields = array(
        'brand',
        'price',
        'condition',
        'availability',
        'feature',
        'sku',
        'is_featured',
        'gallery_images',
        'storage_options',
        'ram_options',
        'specifications',
        'description',
    );

    foreach ( $fields as $field ) {
        register_rest_field(
            'cyf_device',
            $field,
            array(
                'get_callback' => function ( $obj ) use ( $field ) {
                    $val = get_post_meta( $obj['id'], $field, true );
                    return ( '' !== $val ) ? $val : null;
                },
                'schema'       => array(
                    'type' => 'string',
                ),
            )
        );
    }

    // Main image URL
    register_rest_field(
        'cyf_device',
        'image',
        array(
            'get_callback' => function ( $obj ) {
                $id = get_post_thumbnail_id( $obj['id'] );
                if ( $id ) {
                    return wp_get_attachment_url( $id );
                }
                return CYF_PLUGIN_URL . 'assets/placeholder.png';
            },
            'schema'       => array(
                'type' => 'string',
            ),
        )
    );
}
add_action( 'rest_api_init', 'cyf_register_device_rest_fields' );

/**
 * Device meta registered for REST (for is_featured).
 */
function cyf_register_device_meta() {
    register_post_meta(
        'cyf_device',
        'is_featured',
        array(
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
        )
    );
}
add_action( 'init', 'cyf_register_device_meta' );

/* ======================================================
 * CPT: Reviews (cyf_review)
 * ==================================================== */

function cyf_register_cpt_review() {
    register_post_type(
        'cyf_review',
        array(
            'label'        => 'Reviews',
            'labels'       => array(
                'name'          => 'Reviews',
                'singular_name' => 'Review',
            ),
            'public'       => true,
            'has_archive'  => false,
            'show_in_rest' => true,
            'rest_base'    => 'cyf_review',
            'supports'     => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
            'menu_icon'    => 'dashicons-star-filled',
        )
    );
}
add_action( 'init', 'cyf_register_cpt_review', 0 );

/**
 * Review REST fields.
 */
function cyf_register_review_rest_fields() {
    register_rest_field(
        'cyf_review',
        'rating',
        array(
            'get_callback' => function ( $o ) {
                return intval( get_post_meta( $o['id'], 'rating', true ) );
            },
            'schema'       => array(
                'type' => 'integer',
            ),
        )
    );

    register_rest_field(
        'cyf_review',
        'email',
        array(
            'get_callback' => function ( $o ) {
                return get_post_meta( $o['id'], 'email', true );
            },
            'schema'       => array(
                'type' => 'string',
            ),
        )
    );

    register_rest_field(
        'cyf_review',
        'avatar',
        array(
            'get_callback' => function ( $o ) {
                $val = get_post_meta( $o['id'], 'avatar', true );
                return is_numeric( $val ) ? wp_get_attachment_url( $val ) : esc_url_raw( $val );
            },
            'schema'       => array(
                'type' => 'string',
            ),
        )
    );
}
add_action( 'rest_api_init', 'cyf_register_review_rest_fields' );

/**
 * Public POST /wp-json/wp/v2/cyf_review
 */
function cyf_handle_public_review_post( WP_REST_Request $request ) {
    if ( ! function_exists( 'wp_handle_upload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if ( ! function_exists( 'media_handle_sideload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
    }
    if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $params = $request->get_body_params();
    $files  = $request->get_file_params();

    $name    = sanitize_text_field( $params['name'] ?? '' );
    $email   = sanitize_email( $params['email'] ?? '' );
    $rating  = intval( $params['rating'] ?? 0 );
    $content = wp_kses_post( $params['content'] ?? '' );
    $avatar  = '';

    $errors = array();

    if ( ! $name ) {
        $errors[] = 'Name is required.';
    }
    if ( ! $email || ! is_email( $email ) ) {
        $errors[] = 'Valid email is required.';
    }
    if ( $rating < 1 || $rating > 5 ) {
        $errors[] = 'Rating must be between 1 and 5.';
    }
    if ( ! $content ) {
        $errors[] = 'Review content is required.';
    }

    // Avatar upload (optional)
    if ( ! empty( $files['avatar'] ) && is_array( $files['avatar'] ) && empty( $errors ) ) {

        // Validate raw upload.
        $validation = cyf_validate_uploaded_file( $files['avatar'] );
        if ( is_wp_error( $validation ) ) {
            $errors[] = $validation->get_error_message();
        } else {
            $uploaded = wp_handle_upload(
                $files['avatar'],
                array(
                    'test_form' => false,
                )
            );

            if ( isset( $uploaded['error'] ) ) {
                $errors[] = 'Avatar upload failed: ' . $uploaded['error'];
            } else {
                // Optionally resize.
                $resized_path = cyf_resize_uploaded_image( $uploaded['file'] );

                $file_array = array(
                    'name'     => basename( $resized_path ),
                    'tmp_name' => $resized_path,
                );

                $attach_id = media_handle_sideload( $file_array, 0 );
                if ( is_wp_error( $attach_id ) ) {
                    $errors[] = 'Could not attach avatar: ' . $attach_id->get_error_message();
                } else {
                    $avatar = wp_get_attachment_url( $attach_id );
                }
            }
        }
    }

    if ( ! empty( $errors ) ) {
        return new WP_REST_Response(
            array(
                'errors' => $errors,
            ),
            422
        );
    }

    $post_id = wp_insert_post(
        array(
            'post_type'    => 'cyf_review',
            'post_title'   => wp_trim_words( $name, 10, '' ),
            'post_content' => $content,
            'post_status'  => 'pending',
        )
    );

    if ( is_wp_error( $post_id ) || 0 === $post_id ) {
        return new WP_REST_Response(
            array(
                'error' => 'Could not create review.',
            ),
            500
        );
    }

    update_post_meta( $post_id, 'rating', $rating );
    update_post_meta( $post_id, 'email', $email );
    if ( $avatar ) {
        update_post_meta( $post_id, 'avatar', esc_url_raw( $avatar ) );
    }

    return new WP_REST_Response(
        array(
            'success' => true,
            'id'      => $post_id,
            'avatar'  => $avatar,
        ),
        201
    );
}

add_action(
    'rest_api_init',
    function () {
        register_rest_route(
            'wp/v2',
            '/cyf_review',
            array(
                'methods'             => 'POST',
                'callback'            => 'cyf_handle_public_review_post',
                'permission_callback' => '__return_true',
            )
        );
    }
);

/* ======================================================
 * CORS for dev + prod
 * ==================================================== */

add_action(
    'rest_api_init',
    function () {
        $origin          = get_http_origin();
        $allowed_origins = array(
            'http://localhost:5173',
            site_url(),
        );

        if ( $origin && in_array( $origin, $allowed_origins, true ) ) {
            add_filter(
                'rest_pre_serve_request',
                function ( $value ) use ( $origin ) {
                    header( "Access-Control-Allow-Origin: $origin" );
                    header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
                    header( 'Access-Control-Allow-Credentials: true' );
                    header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce' );
                    return $value;
                }
            );
        }
    }
);

/* ======================================================
 * Frontend build loader (Vite / build)
 * ==================================================== */

function cyf_enqueue_build_assets() {
    $plugin_dir = CYF_PLUGIN_DIR;
    $plugin_url = CYF_PLUGIN_URL;

    // Detect Vite dev server.
    $vite_dev    = 'http://localhost:5173';
    $dev_running = false;

    if ( function_exists( 'wp_remote_get' ) ) {
        $res = wp_remote_get(
            $vite_dev,
            array(
                'timeout' => 1,
            )
        );
        if ( ! is_wp_error( $res ) ) {
            $dev_running = true;
        }
    }

    // Dev mode → load from Vite directly.
    if ( $dev_running ) {
        wp_enqueue_script(
            'vite-client',
            $vite_dev . '/@vite/client',
            array(),
            null,
            true
        );

        wp_enqueue_script(
            'cyf-dev',
            $vite_dev . '/src/main.jsx',
            array( 'vite-client' ),
            null,
            true
        );

        return;
    }

    // Production mode → load compiled build.
    $manifest_path = cyf_find_manifest();
    if ( ! $manifest_path || ! file_exists( $manifest_path ) ) {
        return;
    }

    $manifest = json_decode( file_get_contents( $manifest_path ), true );
    if ( empty( $manifest['src/main.jsx'] ) ) {
        return;
    }

    $entry = $manifest['src/main.jsx'];

    // CSS files.
    if ( ! empty( $entry['css'] ) && is_array( $entry['css'] ) ) {
        foreach ( $entry['css'] as $css ) {
            wp_enqueue_style(
                'cyf-style-' . md5( $css ),
                $plugin_url . 'build/' . $css,
                array(),
                null
            );
        }
    }

    // Main JS (module).
    wp_enqueue_script(
        'cyf-app',
        $plugin_url . 'build/' . $entry['file'],
        array(),
        null,
        true
    );
    wp_script_add_data( 'cyf-app', 'type', 'module' );
}
add_action( 'wp_enqueue_scripts', 'cyf_enqueue_build_assets' );

/* ======================================================
 * Contact options REST
 * ==================================================== */

function cyf_get_contact_options() {
    return array(
        'contact_phone'     => get_option( 'cyf_contact_phone', '+977-9812345678' ),
        'contact_whatsapp'  => get_option( 'cyf_contact_whatsapp', '+977-9812345678' ),
        'contact_address'   => get_option( 'cyf_contact_address', 'Sonauli Border' ),
        'contact_maps_link' => get_option( 'cyf_contact_maps_link', 'https://google.com' ),
    );
}

add_action(
    'rest_api_init',
    function () {
        register_rest_route(
            'cyf/v1',
            '/contact',
            array(
                'methods'             => 'GET',
                'callback'            => 'cyf_get_contact_options',
                'permission_callback' => '__return_true',
            )
        );
    }
);

/* ======================================================
 * Sell Requests CPT + Endpoint
 * ==================================================== */

function cyf_register_cpt_sell_request() {
    register_post_type(
        'cyf_sell_request',
        array(
            'label'        => 'Sell Requests',
            'public'       => false,
            'show_ui'      => true,
            'show_in_rest' => true,
            'supports'     => array( 'title', 'editor', 'custom-fields' ),
            'menu_icon'    => 'dashicons-cart',
        )
    );
}
add_action( 'init', 'cyf_register_cpt_sell_request', 0 );

function cyf_handle_sell_post( WP_REST_Request $req ) {
    $p = $req->get_body_params();

    $errors = array();

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
        return new WP_REST_Response(
            array(
                'errors' => $errors,
            ),
            422
        );
    }

    $id = wp_insert_post(
        array(
            'post_type'    => 'cyf_sell_request',
            'post_title'   => $brand . ' ' . $model,
            'post_content' => "Name: $name\nPhone: $phone",
            'post_status'  => 'pending',
        )
    );

    if ( is_wp_error( $id ) ) {
        return new WP_REST_Response(
            array(
                'error' => 'Could not save request.',
            ),
            500
        );
    }

    return new WP_REST_Response(
        array(
            'success' => true,
            'id'      => $id,
        ),
        201
    );
}

add_action(
    'rest_api_init',
    function () {
        register_rest_route(
            'wp/v2',
            '/cyf_sell_request',
            array(
                'methods'             => 'POST',
                'callback'            => 'cyf_handle_sell_post',
                'permission_callback' => '__return_true',
            )
        );
    }
);

/* ======================================================
 * Features, Brands, Hero Slides CPTs
 * ==================================================== */

function cyf_register_cpt_feature() {
    register_post_type(
        'cyf_feature',
        array(
            'label'        => 'Features',
            'public'       => true,
            'show_ui'      => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'supports'     => array( 'title', 'editor', 'custom-fields' ),
            'menu_icon'    => 'dashicons-star-filled',
        )
    );
}
add_action( 'init', 'cyf_register_cpt_feature', 0 );

// simple icon meta for features
register_post_meta(
    'cyf_feature',
    'icon',
    array(
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'string',
    )
);

function cyf_register_hero_slides_cpt() {
    register_post_type(
        'hero_slides',
        array(
            'label'        => 'Hero Slides',
            'public'       => true,
            'show_in_rest' => true,
            'supports'     => array( 'title', 'editor', 'thumbnail' ),
            'menu_icon'    => 'dashicons-images-alt2',
        )
    );
}
add_action( 'init', 'cyf_register_hero_slides_cpt', 0 );

function cyf_register_brand_cpt() {
    register_post_type(
        'cyf_brand',
        array(
            'label'        => 'Brands',
            'public'       => true,
            'show_in_rest' => true,
            'supports'     => array( 'title', 'thumbnail' ),
            'menu_icon'    => 'dashicons-tag',
        )
    );
}
add_action( 'init', 'cyf_register_brand_cpt', 0 );

/* ======================================================
 * Disable Gutenberg for CPTs → use classic editor UI
 * ==================================================== */

add_filter(
    'use_block_editor_for_post_type',
    function ( $use_block_editor, $post_type ) {
        if ( in_array( $post_type, array( 'cyf_device', 'cyf_review', 'cyf_sell_request' ), true ) ) {
            return false;
        }
        return $use_block_editor;
    },
    10,
    2
);

/* ======================================================
 * Shortcode: [cashyourphone_app]
 * ==================================================== */

add_shortcode(
    'cashyourphone_app',
    function () {
        return '<div id="cyf-root"></div>';
    }
);

/* ======================================================
 * Activation / Deactivation
 * ==================================================== */

register_activation_hook(
    __FILE__,
    function () {
        // Ensure CPTs exist on activation.
        cyf_register_cpt_device();
        cyf_register_cpt_review();
        cyf_register_cpt_feature();
        cyf_register_brand_cpt();
        cyf_register_hero_slides_cpt();
        cyf_register_cpt_sell_request();
        flush_rewrite_rules();
    }
);

register_deactivation_hook(
    __FILE__,
    function () {
        flush_rewrite_rules();
    }
);