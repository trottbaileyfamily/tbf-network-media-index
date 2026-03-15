<?php
/**
 * File: includes/class-tbfbkm-featured-media.php
 * Version: 7.0.4.2 (Fully Expanded - Aggressive Gutenberg DOM Observer)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TBFBKM_Featured_Media {

    /**
     * Bootstraps all hooks and filters.
     */
    public static function register() {
        // Register post meta for REST API and Gutenberg
        add_action( 'init', [__CLASS__, 'register_meta'], 99 );
        
        // Intercept the Featured Image ID retrieval globally
        add_filter( 'get_post_metadata', [__CLASS__, 'intercept_thumbnail_id'], 10, 4 );
        
        // Fix the Classic Editor Dashboard Meta Box Preview
        add_filter( 'admin_post_thumbnail_html', [__CLASS__, 'fix_admin_thumbnail_preview'], 99, 3 );
        
        // Fix the Gutenberg Block Editor Visual Preview
        add_action( 'enqueue_block_editor_assets', [__CLASS__, 'enqueue_gutenberg_preview_fix'] );

        // THE ULTIMATE FRONTEND FIX: Completely replaces the HTML output on the frontend
        add_filter( 'post_thumbnail_html', [__CLASS__, 'override_frontend_thumbnail_html'], 99, 5 );
        
        // Secondary Frontend Filters for specific theme functions
        add_filter( 'image_downsize', [__CLASS__, 'filter_image_downsize'], 99, 3 );
        add_filter( 'wp_get_attachment_url', [__CLASS__, 'filter_attachment_url'], 99, 2 );
        add_filter( 'wp_get_attachment_image_attributes', [__CLASS__, 'filter_attr'], 99, 3 );
        
        // REST API Injection for Headless Setups
        add_action( 'rest_api_init', [__CLASS__, 'register_rest_fields'] );

        // Bulletproof Save Lifecycle: Protect remote URLs during Gutenberg saves
        add_action( 'deleted_post_meta', [__CLASS__, 'on_deleted_post_meta'], 10, 4 );
        add_action( 'updated_post_meta', [__CLASS__, 'on_updated_post_meta'], 10, 4 );
        add_action( 'added_post_meta',   [__CLASS__, 'on_updated_post_meta'], 10, 4 );
    }

    /**
     * Injects an aggressive MutationObserver into Gutenberg to force the sidebar 
     * to show the remote image, defeating React's constant re-renders.
     */
    public static function enqueue_gutenberg_preview_fix() {
        $script = "
        document.addEventListener('DOMContentLoaded', function() {
            if ( ! window.wp || ! window.wp.data ) return;
            
            var tbfSwapFeaturedImage = function() {
                var select = wp.data.select('core/editor');
                if (!select) return;
                
                var meta = select.getEditedPostAttribute('meta');
                if ( meta && meta._tbfbkm_featured_url ) {
                    // Target the Gutenberg Featured Image container
                    var containers = document.querySelectorAll('.editor-post-featured-image, .components-panel__body');
                    
                    containers.forEach(function(container) {
                        var img = container.querySelector('img');
                        if ( img ) {
                            // If the image is the placeholder OR not our remote URL, hijack it
                            if ( img.src.indexOf('tbf-placeholder') !== -1 || img.src !== meta._tbfbkm_featured_url ) {
                                // Only target the actual featured image preview, not sidebar icons
                                if ( img.classList.contains('components-responsive-wrapper__content') || container.classList.contains('editor-post-featured-image') ) {
                                    img.src = meta._tbfbkm_featured_url;
                                    img.srcset = ''; // Destroy responsive scaling for placeholder
                                    img.sizes = '';
                                    img.style.objectFit = 'cover';
                                    img.style.width = '100%';
                                }
                            }
                        }
                    });
                }
            };

            // Run on state changes
            wp.data.subscribe(tbfSwapFeaturedImage);

            // Run on DOM mutations to aggressively override React renders
            var editorWrap = document.querySelector('#editor') || document.body;
            if ( editorWrap ) {
                var observer = new MutationObserver(tbfSwapFeaturedImage);
                observer.observe(editorWrap, { childList: true, subtree: true });
            }
        });
        ";
        wp_add_inline_script( 'wp-edit-post', $script );
    }

    /**
     * Directly intercepts the generated HTML and injects the remote URL on the frontend.
     */
    public static function override_frontend_thumbnail_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
        $pid = (int) TBFBKM_Placeholder::get_id();
        
        if ( (int) $post_thumbnail_id === $pid && $pid > 0 ) {
            $remote_url = get_post_meta( $post_id, '_tbfbkm_featured_url', true );
            
            if ( ! empty( $remote_url ) ) {
                $class = isset( $attr['class'] ) ? esc_attr( $attr['class'] ) : 'attachment-post-thumbnail size-post-thumbnail wp-post-image';
                $alt = get_post_meta( $post_id, '_wp_attachment_image_alt', true );
                if ( ! $alt ) {
                    $alt = get_the_title( $post_id );
                }

                return sprintf(
                    '<img src="%s" class="%s tbfbkm-remote-img" alt="%s" decoding="async" fetchpriority="high">',
                    esc_url( $remote_url ),
                    $class,
                    esc_attr( $alt )
                );
            }
        }
        return $html;
    }

    /**
     * Sync logic: We do not blindly delete remote data just because _thumbnail_id was deleted.
     */
    public static function on_deleted_post_meta( $meta_ids, $object_id, $meta_key, $meta_value ) {
        if ( '_thumbnail_id' !== $meta_key ) return;

        $remote_url = get_post_meta( $object_id, '_tbfbkm_featured_url', true );
        if ( empty( $remote_url ) ) return;
    }

    /**
     * Sync logic: Only destroy the remote Big King Media URL if the user explicitly 
     * uploads a *new, native* image to replace it. Ignore empty saves from Gutenberg.
     */
    public static function on_updated_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
        if ( '_thumbnail_id' !== $meta_key ) return;

        $pid         = (int) TBFBKM_Placeholder::get_id();
        $new_thumb   = (int) $meta_value;
        $remote_url  = get_post_meta( $object_id, '_tbfbkm_featured_url', true );

        if ( empty( $remote_url ) || $pid <= 0 ) return;
        if ( $new_thumb === $pid ) return;
        if ( 0 === $new_thumb ) return;

        delete_post_meta( $object_id, '_tbfbkm_featured_url' );
        delete_post_meta( $object_id, '_tbfbkm_featured_mime' );
        delete_post_meta( $object_id, '_tbfbkm_featured_type' );
    }

    /**
     * Registers the custom metadata with Auth Callbacks to prevent REST API blockades.
     */
    public static function register_meta() {
        $post_types = get_post_types( ['public' => true], 'names' );
        
        $auth_callback = function() {
            return current_user_can( 'edit_posts' );
        };

        foreach ( $post_types as $pt ) {
            register_post_meta( $pt, '_tbfbkm_featured_url', [
                'show_in_rest'      => true,
                'single'            => true,
                'type'              => 'string',
                'sanitize_callback' => [__CLASS__, 'sanitize_raw_url'],
                'auth_callback'     => $auth_callback
            ]);
            register_post_meta( $pt, '_tbfbkm_featured_mime', [
                'show_in_rest'      => true,
                'single'            => true,
                'type'              => 'string',
                'auth_callback'     => $auth_callback
            ]);
            register_post_meta( $pt, '_tbfbkm_featured_type', [
                'show_in_rest'      => true,
                'single'            => true,
                'type'              => 'string',
                'auth_callback'     => $auth_callback
            ]);
        }
    }

    /**
     * Prevents WordPress from stripping special characters from URLs.
     */
    public static function sanitize_raw_url( $url ) {
        return esc_url_raw( $url );
    }

    /**
     * Global interceptor. Forces WP to use the Placeholder ID for remote images.
     */
    public static function intercept_thumbnail_id( $value, $post_id, $meta_key, $single ) {
        if ( '_thumbnail_id' !== $meta_key ) return $value;

        $remote_url = get_post_meta( $post_id, '_tbfbkm_featured_url', true );
        if ( ! empty( $remote_url ) ) {
            $pid = TBFBKM_Placeholder::get_id();
            if ( $pid > 0 ) {
                return $single ? $pid : [$pid];
            }
        }

        return $value;
    }

    /**
     * Modifies the Classic Editor sidebar to show the remote image preview.
     */
    public static function fix_admin_thumbnail_preview( $content, $post_id, $thumbnail_id ) {
        $remote_url = get_post_meta( $post_id, '_tbfbkm_featured_url', true );
        
        if ( ! empty( $remote_url ) ) {
            $placeholder_id = TBFBKM_Placeholder::get_id();
            
            if ( (int) $thumbnail_id === (int) $placeholder_id ) {
                $content = sprintf(
                    '<p class="hide-if-no-js"><a href="#" id="set-post-thumbnail" class="thickbox"><img src="%s" style="max-width:100%%; height:auto;" /></a></p>',
                    esc_url( $remote_url )
                );
                $content .= '<p class="hide-if-no-js"><a href="#" id="remove-post-thumbnail" onclick="return false;">Remove featured image</a></p>';
            }
        }
        
        return $content;
    }

    /**
     * Secondary fallback for specific theme loops.
     */
    public static function filter_image_downsize( $out, $id, $size ) {
        $placeholder_id = TBFBKM_Placeholder::get_id();
        
        if ( (int) $id === (int) $placeholder_id ) {
            $post_id = get_the_ID();
            
            if ( ! $post_id && isset( $GLOBALS['post']->ID ) ) {
                $post_id = $GLOBALS['post']->ID;
            }
            
            if ( ! $post_id ) {
                $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
                foreach ( $backtrace as $trace ) {
                    if ( isset( $trace['function'] ) && $trace['function'] === 'get_the_post_thumbnail' && isset( $trace['args'][0] ) ) {
                        $post_id = is_object( $trace['args'][0] ) ? $trace['args'][0]->ID : $trace['args'][0];
                        break;
                    }
                }
            }

            if ( $post_id ) {
                $remote_url = get_post_meta( $post_id, '_tbfbkm_featured_url', true );
                if ( ! empty( $remote_url ) ) {
                    return [ $remote_url, 1200, 800, false ];
                }
            }
        }

        return $out;
    }

    /**
     * Fallback to intercept raw URL requests.
     */
    public static function filter_attachment_url( $url, $post_id ) {
        $placeholder_id = TBFBKM_Placeholder::get_id();
        
        if ( (int) $post_id === (int) $placeholder_id ) {
            $parent_id = get_the_ID();
            if ( ! $parent_id && isset( $GLOBALS['post']->ID ) ) {
                $parent_id = $GLOBALS['post']->ID;
            }

            if ( $parent_id ) {
                $remote_url = get_post_meta( $parent_id, '_tbfbkm_featured_url', true );
                if ( ! empty( $remote_url ) ) {
                    return $remote_url;
                }
            }
        }
        
        return $url;
    }

    /**
     * Destroys the native 'srcset' and 'sizes' attributes to prevent physical placeholder loading.
     */
    public static function filter_attr( $attr, $attachment, $size ) {
        $placeholder_id = TBFBKM_Placeholder::get_id();
        
        if ( isset( $attachment->ID ) && (int) $attachment->ID === (int) $placeholder_id ) {
            $post_id = get_the_ID();
            if ( ! $post_id && isset( $GLOBALS['post']->ID ) ) {
                $post_id = $GLOBALS['post']->ID;
            }

            if ( $post_id ) {
                $remote_url = get_post_meta( $post_id, '_tbfbkm_featured_url', true );
                if ( ! empty( $remote_url ) ) {
                    $attr['src'] = $remote_url;
                    
                    if ( isset( $attr['srcset'] ) ) unset( $attr['srcset'] );
                    if ( isset( $attr['sizes'] ) ) unset( $attr['sizes'] );
                    
                    if ( empty( $attr['class'] ) ) {
                        $attr['class'] = 'tbfbkm-featured-image wp-post-image';
                    } else {
                        $attr['class'] .= ' tbfbkm-featured-image';
                    }
                }
            }
        }
        
        return $attr;
    }

    /**
     * Injects the Big King Media URL into the REST API.
     */
    public static function register_rest_fields() {
        $post_types = get_post_types( ['public' => true], 'names' );
        
        foreach ( $post_types as $pt ) {
            register_rest_field( $pt, 'tbfbkm_featured_media', [
                'get_callback' => function( $post_arr ) {
                    $post_id = $post_arr['id'];
                    $url = get_post_meta( $post_id, '_tbfbkm_featured_url', true );
                    
                    if ( empty( $url ) ) return null;
                    
                    return [
                        'url'  => $url,
                        'mime' => get_post_meta( $post_id, '_tbfbkm_featured_mime', true ),
                        'type' => get_post_meta( $post_id, '_tbfbkm_featured_type', true ),
                    ];
                },
                'schema' => [
                    'description' => 'TBF Big King Media remote featured object',
                    'type'        => 'object',
                ]
            ]);
        }
    }
}