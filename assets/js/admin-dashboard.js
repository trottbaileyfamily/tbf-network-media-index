/**
 * File: assets/js/admin-dashboard.js
 * Version: 6.2.6 (Enqueued Script Extraction)
 */
(function($) {
    'use strict';

    // 1. Photofall Tab: "Check All" checkbox logic
    const selectAllCb = document.getElementById('cb-select-all-1');
    if (selectAllCb) {
        selectAllCb.addEventListener('change', function(e) {
            const checkboxes = document.querySelectorAll('input[name="enabled_sites[]"]');
            for (let i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = e.target.checked;
            }
        });
    }

    // 2. Indexer Tab Logic (Only runs if the data object exists)
    if (typeof tbfnmi_dashboard_data !== 'undefined' && $('#tbf-indexer-ui').length) {
        var sites = tbfnmi_dashboard_data.sites;
        var totalSites = sites.length;
        var currentSiteIdx = 0;
        var currentLastId = 0;
        var totalIndexed = 0;
        var nonce = tbfnmi_dashboard_data.nonce;
        var ajaxurl = tbfnmi_dashboard_data.ajaxurl;
        var $log = $('#log-list');

        function logMsg(msg, color) {
            color = color || '#00ff00';
            $log.append('<li style="color:' + color + ';">> ' + msg + '</li>');
            $log.scrollTop($log[0].scrollHeight);
        }

        // --- VIKINGER SYNC LOGIC ---
        $('#sync-vikinger').on('click', function() {
            $(this).prop('disabled', true).text('Syncing...');
            $('#start-indexing').prop('disabled', true);
            $('#index-progress').slideDown();
            
            logMsg('=================================', '#e0a800');
            logMsg('INITIATING VIKINGER BRIDGE SYNC...', '#e0a800');
            
            var syncIdx = 0;
            
            function syncNextSite() {
                if (syncIdx >= totalSites) {
                    logMsg('Vikinger Sync Complete! You can now run the Full Network Index.', '#e0a800');
                    $('#sync-vikinger').prop('disabled', false).text('Sync Vikinger Frontend Uploads');
                    $('#start-indexing').prop('disabled', false);
                    return;
                }
                
                var site = sites[syncIdx];
                logMsg('Checking Site ' + site.id + ' for Vikinger uploads...', '#aaa');
                
                function syncChunk(offset) {
                    $.post(ajaxurl, { 
                        action: 'tbfnmi_sync_vikinger', 
                        nonce: nonce, 
                        blog_id: site.id,
                        offset: offset
                    }).done(function(res) {
                        if(res.success) {
                            if(res.data.synced > 0) {
                                logMsg('SUCCESS: Bridged ' + res.data.synced + ' files (Processed ' + Math.min(res.data.next_offset, res.data.total) + '/' + res.data.total + ') on Site ' + site.id);
                            }
                            
                            if (res.data.done) {
                                syncIdx++;
                                setTimeout(syncNextSite, 200);
                            } else {
                                setTimeout(function() { syncChunk(res.data.next_offset); }, 200);
                            }
                        } else {
                            logMsg('Server rejected the request on Site ' + site.id, 'red');
                            syncIdx++;
                            setTimeout(syncNextSite, 200);
                        }
                    }).fail(function() {
                        logMsg('Timeout on Site ' + site.id + '. Trying to resume...', '#e0a800');
                        setTimeout(function() { syncChunk(offset); }, 2000); 
                    });
                }
                
                syncChunk(0); 
            }
            syncNextSite();
        });

        // --- NORMAL INDEXER LOGIC ---
        $('#start-indexing').on('click', function() {
            $(this).prop('disabled', true).text('Indexing in Progress...');
            $('#sync-vikinger').prop('disabled', true);
            $('#index-progress').slideDown();
            logMsg('Initiating Network Scan...');
            
            currentSiteIdx = 0;
            currentLastId = 0;
            totalIndexed = 0;
            $('#stat-total-indexed').text('0');
            
            processBatch();
        });

        function processBatch() {
            if (currentSiteIdx >= totalSites) {
                logMsg('=================================', '#fff');
                logMsg('INDEXING COMPLETE!', '#fff');
                logMsg('Total Media Files Indexed: ' + totalIndexed, '#fff');
                $('#progress-bar').css('width', '100%');
                $('#stat-progress').text('100%');
                $('#stat-site').text('Complete');
                $('#start-indexing').prop('disabled', false).text('Re-Run Indexer');
                $('#sync-vikinger').prop('disabled', false);
                return;
            }

            var site = sites[currentSiteIdx];
            var pct = Math.round((currentSiteIdx / totalSites) * 100);
            $('#progress-bar').css('width', pct + '%');
            $('#stat-progress').text(pct + '%');
            $('#stat-site').text(site.name);

            if (currentLastId === 0) logMsg('Connecting to Site ID ' + site.id + ' (' + site.name + ')...', '#aaa');

            $.post(ajaxurl, { 
                action: 'tbfnmi_index_batch', 
                nonce: nonce, 
                blog_id: site.id,
                start_after: currentLastId
            }).done(function(res) {
                if(res.success) {
                    var data = res.data;
                    if (data.scanned > 0) {
                        totalIndexed += data.indexed;
                        $('#stat-total-indexed').text(totalIndexed);
                        logMsg('Scanned ' + data.scanned + ' items. Found & Indexed ' + data.indexed + ' valid media files.');
                    }

                    if (data.done) {
                        logMsg('Finished Site ID ' + site.id + '.', '#aaa');
                        currentSiteIdx++;
                        currentLastId = 0; 
                    } else {
                        currentLastId = data.last_id; 
                    }
                } else {
                    logMsg('Error on Site ' + site.id + ': ' + (res.data ? res.data.error : 'Unknown'), 'red');
                    currentSiteIdx++;
                    currentLastId = 0;
                }
                setTimeout(processBatch, 100);
            }).fail(function() {
                logMsg('Server connection lost on Site ' + site.id + '. Skipping to next.', 'red');
                currentSiteIdx++;
                currentLastId = 0;
                setTimeout(processBatch, 500);
            });
        }
    }
})(jQuery);