/* global jQuery, wp, tbfnmi_gutenberg */
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
            if (meta && typeof meta._tbfnmi_featured_url !== 'undefined' && meta._tbfnmi_featured_url !== '') {
                setPreviewUrl(decodeUrl(meta._tbfnmi_featured_url));
            } else {
                setPreviewUrl('');
            }
        }, [meta._tbfnmi_featured_url]);

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
                        _tbfnmi_featured_url: finalUrl,
                        _tbfnmi_featured_mime: finalMime,
                        _tbfnmi_featured_type: finalType
                    } 
                });

                if (postId) {
                    $.ajax({
                        url: tbfnmi_gutenberg.ajaxurl,
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'tbfnmi_set_featured_remote',
                            nonce: tbfnmi_gutenberg.nonce,
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
                    _tbfnmi_featured_url: '', 
                    _tbfnmi_featured_mime: '', 
                    _tbfnmi_featured_type: '' 
                }, 
                featured_media: 0 
            });
            setPreviewUrl('');
            setLoading(false);
        };

        return createElement(
            PluginDocumentSettingPanel,
            { name: 'tbfnmi-featured-media-panel', title: 'TBF Network Featured Image', icon: 'images-alt2' },
            createElement('div', { className: 'tbfnmi-featured-image-wrapper' },
                loading ? createElement(Spinner, null) : null,
                previewUrl && !loading ? createElement('img', { 
                    src: previewUrl, 
                    className: 'tbfnmi-sidebar-preview',
                    referrerPolicy: 'no-referrer' 
                }) : null,
                createElement(Button, { isPrimary: !previewUrl, isSecondary: !!previewUrl, className: 'tbfnmi-sidebar-btn', onClick: openMediaModal }, previewUrl ? 'Replace Image' : 'Set Network Image'),
                previewUrl ? createElement(Button, { isDestructive: true, isLink: true, style: { display: 'block', textAlign: 'center', marginTop: '10px', textDecoration: 'none' }, onClick: removeMedia }, 'Remove Image') : null
            )
        );
    };

    wp.domReady(() => {
        registerPlugin('tbfnmi-gutenberg-sidebar', { render: TBFNetworkFeaturedImage, icon: 'images-alt2' });
    });

})(window.wp, window.jQuery);