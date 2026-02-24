/* =========================================================
   File: assets/js/gutenberg-sidebar.js
   Version: 6.4.9.3 (Bulletproof React Execution)
   Description: Independent TBF Featured Media Panel
   ========================================================= */
( function( wp ) {
    // 1. Strict Dependency Check: Abort cleanly if Gutenberg is not fully booted
    if ( ! wp || ! wp.plugins || ! wp.editPost || ! wp.element || ! wp.data || ! wp.components ) {
        console.error( 'TBFNMI Error: Missing core Gutenberg dependencies.' );
        return;
    }

    const { registerPlugin } = wp.plugins;
    const { PluginDocumentSettingPanel } = wp.editPost;
    const { createElement: el, Fragment } = wp.element;
    const { useSelect, useDispatch } = wp.data;
    const { Button } = wp.components;

    const TBFFeaturedMediaPanel = () => {
        // 2. Bulletproof Meta Extraction: Prevents crashes if post type lacks custom fields
        const meta = useSelect( ( select ) => {
            const editor = select( 'core/editor' );
            return editor ? ( editor.getEditedPostAttribute( 'meta' ) || {} ) : {};
        } );

        const { editPost } = useDispatch( 'core/editor' );

        const featuredUrl = meta['_tbfnmi_featured_url'] || '';
        const featuredMime = meta['_tbfnmi_featured_mime'] || '';

        const openMediaModal = () => {
            if ( ! wp.media ) {
                alert('WordPress Media Library is not initialized.');
                return;
            }

            const frame = wp.media({
                title: 'Select TBF Network Media',
                button: { text: 'Set as TBF Featured Media' },
                multiple: false
            });

            frame.on('select', () => {
                const attachment = frame.state().get('selection').first().toJSON();
                let type = 'image';
                if (attachment.mime && attachment.mime.indexOf('video') !== -1) type = 'video';
                if (attachment.mime && attachment.mime.indexOf('audio') !== -1) type = 'audio';

                // Instantly dispatch to the database
                editPost({
                    meta: {
                        '_tbfnmi_featured_url': attachment.url,
                        '_tbfnmi_featured_mime': attachment.mime,
                        '_tbfnmi_featured_type': type
                    }
                });
            });

            frame.open();
        };

        const removeMedia = () => {
            editPost({
                meta: {
                    '_tbfnmi_featured_url': '',
                    '_tbfnmi_featured_mime': '',
                    '_tbfnmi_featured_type': ''
                }
            });
        };

        // 3. Render Preview
        let mediaPreview = null;
        if ( featuredUrl ) {
            if ( featuredMime && featuredMime.includes('audio') ) {
                mediaPreview = el('audio', { controls: true, src: featuredUrl, style: { width: '100%', marginBottom: '15px', outline: 'none' } });
            } else if ( featuredMime && featuredMime.includes('video') ) {
                mediaPreview = el('video', { controls: true, src: featuredUrl, style: { width: '100%', background: '#000', marginBottom: '15px', outline: 'none' } });
            } else {
                mediaPreview = el('img', { src: featuredUrl, style: { width: '100%', height: 'auto', borderRadius: '4px', marginBottom: '15px' } });
            }
        }

        // 4. Safe Fragment Rendering: Prevents React from crashing when evaluating null elements
        return el( PluginDocumentSettingPanel, {
            name: 'tbfnmi-featured-media',
            title: 'TBF Network Media (Global)',
            icon: 'admin-network'
        },
            el( Fragment, null, 
                mediaPreview,
                el( Button, {
                    isPrimary: !featuredUrl,
                    isSecondary: !!featuredUrl,
                    onClick: openMediaModal,
                    style: { width: '100%', justifyContent: 'center', marginBottom: featuredUrl ? '10px' : '0' }
                }, featuredUrl ? 'Replace Media' : 'Set Network Media' ),
                
                featuredUrl ? el( Button, {
                    isLink: true,
                    isDestructive: true,
                    onClick: removeMedia,
                    style: { width: '100%', justifyContent: 'center' }
                }, 'Remove Media' ) : null
            )
        );
    };

    // Initialize the plugin safely
    try {
        registerPlugin( 'tbfnmi-featured-media-panel', {
            render: TBFFeaturedMediaPanel,
            icon: 'admin-network'
        } );
    } catch ( err ) {
        console.error( "TBFNMI Sidebar Registration Failed:", err );
    }

} )( window.wp );