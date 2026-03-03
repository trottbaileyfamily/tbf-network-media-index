/**
 * File: assets/js/photofall-public.js
 * Version: 6.6.21 (Scroll & Delete Logic)
 */
(function() {
    'use strict';
    
    const dataObj = typeof tbfnmi_data !== 'undefined' ? tbfnmi_data : null;
    const ajaxUrl = dataObj ? dataObj.ajax_url : '/wp-admin/admin-ajax.php';

    // Auto-Submit Logic
    const filterForm = document.getElementById('tbf-filter-form');
    if (filterForm) {
        document.querySelectorAll('.tbf-auto-submit').forEach(el => {
            el.addEventListener('change', () => { filterForm.submit(); });
        });
    }

    // AJAX Load More Engine
    const loadMoreBtn = document.getElementById('tbf-load-more');
    const loader = document.getElementById('tbf-loader');
    const gridContainer = document.getElementById('tbf-grid-container'); 
    
    if (loadMoreBtn && dataObj && gridContainer) {
        loadMoreBtn.addEventListener('click', function() {
            if (parseInt(dataObj.current_page) >= parseInt(dataObj.max_pages)) return;

            loadMoreBtn.disabled = true;
            loadMoreBtn.innerText = 'Loading...';
            if(loader) loader.style.display = 'inline-block';

            const urlParams = new URLSearchParams(window.location.search);
            const currentTab = urlParams.get('tbf_tab') || 'active';

            const params = new URLSearchParams();
            params.append('action', 'tbfnmi_load_more');
            params.append('nonce', dataObj.nonce);
            params.append('page', parseInt(dataObj.current_page) + 1);
            params.append('filter', dataObj.filter || 'all'); 
            params.append('source', dataObj.source || 'all'); 
            params.append('sort', dataObj.sort || '');
            params.append('search', dataObj.search || '');
            params.append('year', dataObj.year || '');
            params.append('site_filter', dataObj.site_filter || '');
            params.append('tbf_tab', currentTab);

            fetch(ajaxUrl, { method: 'POST', body: params })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = res.data.html;
                    Array.from(tempDiv.children).forEach(node => gridContainer.appendChild(node));
                    
                    dataObj.current_page++;
                    if (dataObj.current_page >= res.data.max_pages) {
                        loadMoreBtn.style.display = 'none';
                    }
                }
            })
            .catch(err => console.error('TBF Photofall Error:', err))
            .finally(() => {
                loadMoreBtn.disabled = false;
                loadMoreBtn.innerText = 'Load More';
                if(loader) loader.style.display = 'none';
            });
        });
    }

    // CAPTION TOGGLE LISTENER
    const captionBtn = document.getElementById('tbf-toggle-captions');
    if (captionBtn && gridContainer) {
        captionBtn.addEventListener('click', function() {
            gridContainer.classList.toggle('captions-hidden');
            this.classList.toggle('active');
        });
    }

    // Lightbox & SEO Engine
    window.tbfnmi_photofall = {
        currentTrigger: null,
        open: function(triggerImg) {
            this.currentTrigger = triggerImg;
            this.render(
                triggerImg.getAttribute('data-full'),
                triggerImg.getAttribute('data-type'),
                triggerImg.getAttribute('data-caption'),
                triggerImg.getAttribute('data-source-title'),
                triggerImg.getAttribute('data-source-url'),
                triggerImg.getAttribute('data-permalink')
            );
        },
        openRaw: function(src, type, caption) { this.render(src, type, caption, '', '', ''); },
        render: function(src, type, caption, sourceTitle, sourceUrl, permalink) {
            const lb = document.getElementById('tbf-lightbox');
            if (!lb) return;
            
            const lbImg = document.getElementById('tbf-lb-img');
            const lbVid = document.getElementById('tbf-lb-video');
            const lbAud = document.getElementById('tbf-lb-audio');
            const lbCap = document.getElementById('tbf-lb-caption');
            const lbViewBtn = document.getElementById('tbf-lb-view-page');
            const lbSourceBtn = document.getElementById('tbf-lb-source-link');

            if(lbVid) { lbVid.pause(); lbVid.style.display = 'none'; }
            if(lbAud) { lbAud.pause(); lbAud.style.display = 'none'; }
            if(lbImg) { lbImg.style.display = 'none'; lbImg.style.width = ''; lbImg.style.marginBottom = ''; }

            if (type === 'video' && lbVid) {
                lbVid.style.display = 'block'; lbVid.src = src; 
                try { lbVid.play(); } catch(e){}
            } else if (type === 'audio' && lbAud && lbImg && dataObj) {
                lbImg.style.display = 'block'; 
                lbImg.src = dataObj.includes_url + 'images/media/audio.png';
                lbImg.style.width = '150px'; lbImg.style.marginBottom = '20px';
                lbAud.style.display = 'block'; lbAud.src = src; 
                try { lbAud.play(); } catch(e){}
            } else if (lbImg) {
                lbImg.style.display = 'block'; lbImg.src = src;
            }

            if(lbCap) lbCap.innerText = caption || '';
            
            if (lbViewBtn) {
                if (permalink) {
                    lbViewBtn.style.display = 'inline-block';
                    lbViewBtn.href = permalink;
                    lbViewBtn.removeAttribute('target');
                } else {
                    lbViewBtn.style.display = 'none';
                }
            }

            if (lbSourceBtn) {
                if (sourceUrl) {
                    lbSourceBtn.style.display = 'inline-block';
                    lbSourceBtn.href = sourceUrl;
                    lbSourceBtn.innerText = 'Featured in: ' + (sourceTitle || 'View Source Post');
                    lbSourceBtn.removeAttribute('target'); 
                } else {
                    lbSourceBtn.style.display = 'none';
                }
            }

            lb.style.display = 'flex'; 
            document.body.style.overflow = 'hidden';
        },
        close: function() {
            const lb = document.getElementById('tbf-lightbox'); if(lb) lb.style.display = 'none';
            const vid = document.getElementById('tbf-lb-video'); if(vid) vid.pause();
            const aud = document.getElementById('tbf-lb-audio'); if(aud) aud.pause();
            document.body.style.overflow = '';
        },
        nav: function(direction) {
            if (!this.currentTrigger) return;
            const allVisible = Array.from(document.querySelectorAll('.tbf-photofall-img'));
            let idx = allVisible.indexOf(this.currentTrigger);
            if (idx === -1) return;
            let nextIdx = idx + direction;
            if (nextIdx >= allVisible.length) nextIdx = 0;
            if (nextIdx < 0) nextIdx = allVisible.length - 1;
            this.open(allVisible[nextIdx]);
        }
    };

    const closeBtn = document.querySelector('.tbf-close'); 
    if (closeBtn) closeBtn.addEventListener('click', () => tbfnmi_photofall.close());
    const nextBtn = document.querySelector('.tbf-next'); 
    if (nextBtn) nextBtn.addEventListener('click', (e) => { e.stopPropagation(); tbfnmi_photofall.nav(1); });
    const prevBtn = document.querySelector('.tbf-prev'); 
    if (prevBtn) prevBtn.addEventListener('click', (e) => { e.stopPropagation(); tbfnmi_photofall.nav(-1); });
    
    const lb = document.getElementById('tbf-lightbox');
    if (lb) lb.addEventListener('click', (e) => { 
        if (e.target.id === 'tbf-lightbox' || e.target.classList.contains('tbf-lightbox-content')) tbfnmi_photofall.close(); 
    });
    
    document.addEventListener('keydown', (e) => {
        if (lb && lb.style.display === 'flex') {
            if (e.key === 'Escape') tbfnmi_photofall.close();
            if (e.key === 'ArrowRight') tbfnmi_photofall.nav(1);
            if (e.key === 'ArrowLeft') tbfnmi_photofall.nav(-1);
        }
    });

    // --------------------------------------------------------------------
    // STAGING QUEUE ENGINE (ADD & REMOVE)
    // --------------------------------------------------------------------
    const uploadBtn = document.getElementById('tbfnmi-trigger-upload');
    const modal = document.getElementById('tbfnmi-upload-modal');
    const fileInput = document.getElementById('tbfnmi-file-input');
    const queueList = document.getElementById('tbfnmi-queue-list');
    const startUploadBtn = document.getElementById('tbfnmi-start-upload');
    
    let uploadQueue = []; 

    if (uploadBtn && modal) {
        uploadBtn.addEventListener('click', () => { 
            modal.style.display = 'flex';
            uploadQueue = [];
            queueList.innerHTML = '';
            startUploadBtn.disabled = true;
            fileInput.value = '';
        });
        
        const modalCloseBtn = modal.querySelector('.tbfnmi-modal-close');
        if (modalCloseBtn) {
            modalCloseBtn.addEventListener('click', () => modal.style.display = 'none');
        }
        
        window.addEventListener('click', (e) => {
            if (e.target === modal) modal.style.display = 'none';
        });

        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                const files = Array.from(e.target.files);
                if (!files.length) return;

                files.forEach(file => {
                    const id = 'q-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
                    
                    const itemDiv = document.createElement('div');
                    itemDiv.className = 'tbfnmi-queue-item';
                    itemDiv.id = id;
                    
                    let thumbHtml = '';
                    if (file.type.startsWith('image/')) {
                        const src = URL.createObjectURL(file);
                        thumbHtml = `<img src="${src}" class="tbfnmi-queue-thumb">`;
                    } else if (file.type.startsWith('video/')) {
                        thumbHtml = `<div class="tbfnmi-queue-thumb" style="display:flex;align-items:center;justify-content:center;font-size:30px;">▶</div>`;
                    } else {
                        thumbHtml = `<div class="tbfnmi-queue-thumb" style="display:flex;align-items:center;justify-content:center;font-size:30px;">📄</div>`;
                    }

                    itemDiv.innerHTML = `
                        ${thumbHtml}
                        <div class="tbfnmi-queue-fields">
                            <input type="text" class="tbfnmi-q-title" placeholder="Title" value="${file.name.replace(/\.[^/.]+$/, "")}">
                            <textarea class="tbfnmi-q-desc" placeholder="Description (e.g., A white sand pool...)"></textarea>
                            <div class="tbfnmi-progress-wrap">
                                <div class="tbfnmi-progress-bar"></div>
                            </div>
                            <div class="tbfnmi-status-text">Ready</div>
                        </div>
                        <span class="tbfnmi-remove-item" title="Remove">&times;</span>
                    `;
                    
                    queueList.appendChild(itemDiv);
                    
                    uploadQueue.push({ id: id, file: file, dom: itemDiv });
                });

                startUploadBtn.disabled = false;
                startUploadBtn.innerText = 'Upload ' + uploadQueue.length + ' Files';
            });
        }

        // DELETION HANDLER
        if (queueList) {
            queueList.addEventListener('click', function(e) {
                if (e.target.classList.contains('tbfnmi-remove-item')) {
                    const row = e.target.closest('.tbfnmi-queue-item');
                    if (row) {
                        const id = row.id;
                        uploadQueue = uploadQueue.filter(item => item.id !== id);
                        row.remove();
                        
                        if (uploadQueue.length > 0) {
                            startUploadBtn.innerText = 'Upload ' + uploadQueue.length + ' Files';
                        } else {
                            startUploadBtn.disabled = true;
                            startUploadBtn.innerText = 'Start Upload';
                        }
                    }
                }
            });
        }

        // Process Upload Queue
        if (startUploadBtn) {
            startUploadBtn.addEventListener('click', async function() {
                if (uploadQueue.length === 0) return;
                
                startUploadBtn.disabled = true;
                startUploadBtn.innerText = 'Uploading...';

                // We iterate over a copy so deletions don't break index if logic changes later
                const currentQueue = [...uploadQueue];

                for (let i = 0; i < currentQueue.length; i++) {
                    const item = currentQueue[i];
                    const itemDom = document.getElementById(item.id);
                    if (!itemDom) continue;

                    const title = itemDom.querySelector('.tbfnmi-q-title').value;
                    const desc = itemDom.querySelector('.tbfnmi-q-desc').value;
                    const progressBar = itemDom.querySelector('.tbfnmi-progress-bar');
                    const progressWrap = itemDom.querySelector('.tbfnmi-progress-wrap');
                    const statusText = itemDom.querySelector('.tbfnmi-status-text');
                    const removeBtn = itemDom.querySelector('.tbfnmi-remove-item');

                    if(removeBtn) removeBtn.style.display = 'none'; // Lock removal during upload
                    progressWrap.style.display = 'block';
                    statusText.innerText = 'Uploading...';

                    try {
                        await uploadSingleFile(item.file, title, desc, (percent) => {
                            progressBar.style.width = percent + '%';
                        });
                        
                        statusText.innerText = 'Complete';
                        statusText.className = 'tbfnmi-status-text success';
                        progressBar.style.background = 'green';
                        
                    } catch (err) {
                        console.error(err);
                        statusText.innerText = 'Error: ' + (err.message || 'Failed');
                        statusText.className = 'tbfnmi-status-text error';
                        progressBar.style.background = 'red';
                    }
                }

                startUploadBtn.innerText = 'All Done';
                setTimeout(() => window.location.reload(), 1500);
            });
        }
    }

    function uploadSingleFile(file, title, desc, onProgress) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('action', 'tbfnmi_frontend_upload');
            formData.append('security', dataObj.nonce);
            formData.append('tbfnmi_title', title);
            formData.append('tbfnmi_description', desc);
            formData.append('tbfnmi_media[]', file);

            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener("progress", function(evt) {
                if (evt.lengthComputable) {
                    const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                    onProgress(percentComplete);
                }
            }, false);

            xhr.open("POST", ajaxUrl);
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const resp = JSON.parse(xhr.responseText);
                        if (resp.success) resolve(resp);
                        else reject(new Error(resp.data.message));
                    } catch (e) {
                        reject(new Error('Invalid server response'));
                    }
                } else {
                    reject(new Error('Server error ' + xhr.status));
                }
            };

            xhr.onerror = function() {
                reject(new Error('Network error'));
            };

            xhr.send(formData);
        });
    }

    // Admin Inline Exposes
    window.tbfnmiToggleHide = function(id, nonce) {
        const formData = new FormData();
        formData.append('action', 'tbfnmi_hide_media');
        formData.append('attachment_id', id);
        formData.append('nonce', nonce);

        fetch(ajaxUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => { if(data.success) location.reload(); });
    };

    window.tbfnmiDeleteMedia = function(id, nonce) {
        if (!confirm('WARNING: This permanently wipes this media file from the local library and the network index. Proceed?')) return;
        const formData = new FormData();
        formData.append('action', 'tbfnmi_delete_media');
        formData.append('attachment_id', id);
        formData.append('nonce', nonce);

        fetch(ajaxUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => { if(data.success) location.reload(); });
    };

})();