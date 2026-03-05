/* global jQuery, wp, tbfbkm_gutenberg */
/* =========================================================
   File: assets/js/gutenberg-sidebar.js
   Version: 6.5.15 (DOM Sync & Native Parsing Fix)
   ========================================================= */
(function(wp, $) {
    if (!wp || !wp.plugins || !wp.editPost || !wp.data) return;

    const { registerPlugin } = wp.plugins;
    const { PluginDocumentSettingPanel } = wp.editPost;
    const { Button, Spinner } = wp.components;
    const { useState, useEffect, createElement } = wp.element;
    const { useSelect, useDispatch } = wp.data;

    const decodeUrl = (url) => {
        if (!url) return '';
        const txt = document.createElement("textarea");
        txt.innerHTML = url;
        return txt.value;
    };

    const TBFNetworkFeaturedImage = () => {
        const postId = useSelect(select => select('core/editor').getCurrentPostId());
        const meta = useSelect(select => select('core/editor').getEditedPostAttribute('meta') || {});
        const { editPost } = useDispatch('core/editor');

        const [previewUrl, setPreviewUrl] = useState('');
        const [loading, setLoading] = useState(false);

        useEffect(() => {
            if (meta && typeof meta._tbfbkm_featured_url !== 'undefined' && meta._tbfbkm_featured_url !== '') {
                setPreviewUrl(decodeUrl(meta._tbfbkm_featured_url));
            } else {
                setPreviewUrl('');
            }
        }, [meta._tbfbkm_featured_url]);

        const openMediaModal = () => {
            const frame = wp.media({
                title: 'Select Network Featured Media',
                button: { text: 'Set as Network Featured Image' },
                multiple: false
            });

            frame.on('select', () => {
                const attachment = frame.state().get('selection').first().toJSON();
                setLoading(true);

                const rawUrl = attachment.url || attachment.sizes?.full?.url || '';
                const finalUrl = decodeUrl(rawUrl);
                const finalMime = attachment.mime || attachment.subtype || 'image/jpeg';
                const finalType = attachment.type || 'image';

                if (!finalUrl) { 
                    setLoading(false); 
                    return; 
                }

                editPost({ 
                    meta: { 
                        ...meta, 
                        _tbfbkm_featured_url: finalUrl,
                        _tbfbkm_featured_mime: finalMime,
                        _tbfbkm_featured_type: finalType
                    } 
                });

                if (postId) {
                    $.ajax({
                        url: tbfbkm_gutenberg.ajaxurl,
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'tbfbkm_set_featured_remote',
                            nonce: tbfbkm_gutenberg.nonce,
                            post_id: postId,
                            url: finalUrl,
                            mime: finalMime,
                            type: finalType
                        }
                    }).done((res) => {
                        if (res.success && res.data.placeholder_id) {
                            editPost({ featured_media: res.data.placeholder_id });
                        }
                    }).always(() => { 
                        setLoading(false); 
                    });
                } else {
                    setLoading(false);
                }
                
                setPreviewUrl(finalUrl);
            });

            frame.open();
        };

        const removeMedia = () => {
            setLoading(true);
            editPost({ 
                meta: { 
                    ...meta, 
                    _tbfbkm_featured_url: '', 
                    _tbfbkm_featured_mime: '', 
                    _tbfbkm_featured_type: '' 
                }, 
                featured_media: 0 
            });
            setPreviewUrl('');
            setLoading(false);
        };

        return createElement(
            PluginDocumentSettingPanel,
            { name: 'tbfbkm-featured-media-panel', title: 'TBF Network Featured Image', icon: 'images-alt2' },
            createElement('div', { className: 'tbfbkm-featured-image-wrapper' },
                loading ? createElement(Spinner, null) : null,
                previewUrl && !loading ? createElement('img', { 
                    src: previewUrl, 
                    className: 'tbfbkm-sidebar-preview',
                    referrerPolicy: 'no-referrer' 
                }) : null,
                createElement(Button, { isPrimary: !previewUrl, isSecondary: !!previewUrl, className: 'tbfbkm-sidebar-btn', onClick: openMediaModal }, previewUrl ? 'Replace Image' : 'Set Network Image'),
                previewUrl ? createElement(Button, { isDestructive: true, isLink: true, style: { display: 'block', textAlign: 'center', marginTop: '10px', textDecoration: 'none' }, onClick: removeMedia }, 'Remove Image') : null
            )
        );
    };

    wp.domReady(() => {
        registerPlugin('tbfbkm-gutenberg-sidebar', { render: TBFNetworkFeaturedImage, icon: 'images-alt2' });
    });

})(window.wp, window.jQuery);
