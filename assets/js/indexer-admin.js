/*!
 * File: assets/js/indexer-admin.js
 * Version: 4.0.0
 */
(function ($) {
  if (!window.TBF_NMI_INDEXER) return;

  const $log = () => $('.tbf-nmi-indexer-log');
  const state = { running: false, timer: null, backoffMs: 500 };

  function ajax(action, data) {
    return $.ajax({
      url: TBF_NMI_INDEXER.ajax,
      method: 'POST',
      dataType: 'json',
      data: Object.assign({ action, nonce: TBF_NMI_INDEXER.nonce }, data || {})
    });
  }

  function writeLog(line) {
    const el = $log();
    if (!el.length) return;
    const now = new Date();
    const ts = now.toISOString().replace('T', ' ').replace('Z', ' UTC');
    el.append(`[${ts}] ${line}\n`);
    el.scrollTop(el[0].scrollHeight);
  }

  function setStatusUI(st) {
    $('.tbf-running').text(st.running ? 'Yes' : 'No');
    $('.tbf-current-site').text(st.current_blog_id || '');
    $('.tbf-cursor').text(st.cursor || 0);
    $('.tbf-total-indexed').text(st.total_indexed || 0);
    $('.tbf-updated').text(st.updated_at || '');
  }

  async function refreshStatus() {
    try {
      const res = await ajax('tbf_nmi_indexer_status', {});
      if (res && res.success) setStatusUI(res.data);
    } catch (e) {}
  }

  function getRunParams() {
    const limit = parseInt($('#tbf-limit').val() || '500', 10);
    const images = $('#tbf-images').is(':checked');
    const videos = $('#tbf-videos').is(':checked');
    const siteOnly = $('#tbf-site').length ? parseInt($('#tbf-site').val() || '0', 10) : 0;
    return { limit: isNaN(limit) ? 500 : limit, images: images ? 1 : 0, videos: videos ? 1 : 0, site_only: siteOnly || 0 };
  }

  async function tick() {
    if (!state.running) return;
    const params = getRunParams();

    try {
      const res = await ajax('tbf_nmi_indexer_run', params);
      if (!res || !res.success) { writeLog('Batch error (no response). Stopping.'); state.running = false; await refreshStatus(); return; }
      const data = res.data || {};
      if (data.log) {
        const l = data.log;
        writeLog(`Site ${l.blog_id}: scanned ${l.scanned}, indexed ${l.indexed}, cursor ${l.cursor}` + (l.done_site ? ' (site done)' : ''));
      } else {
        writeLog('Batch completed.');
      }
      if (data.state) setStatusUI(data.state);
      if (data.done) { writeLog('All done. Indexer stopped.'); state.running = false; await refreshStatus(); return; }
      const scanned = data.log ? (data.log.scanned || 0) : 0;
      state.backoffMs = scanned === 0 ? 1200 : 500;
    } catch (e) {
      writeLog('Batch error: request failed');
      state.backoffMs = 1500;
    }
    scheduleNext();
  }

  function scheduleNext() {
    if (!state.running) return;
    clearTimeout(state.timer);
    state.timer = setTimeout(tick, state.backoffMs);
  }

  async function start() {
    if (state.running) return;
    state.running = true;
    writeLog('Starting/resuming indexer...');
    scheduleNext();
  }

  async function stop() {
    state.running = false;
    clearTimeout(state.timer);
    try { const res = await ajax('tbf_nmi_indexer_stop', {}); if (res && res.success) writeLog('Stopped.'); } catch (e) { writeLog('Stop request failed.'); }
    await refreshStatus();
  }

  async function reset() {
    state.running = false;
    clearTimeout(state.timer);
    if (!confirm('Reset progress? This will NOT delete the DB table; it only resets the cursor/progress state.')) return;
    try { const res = await ajax('tbf_nmi_indexer_reset', {}); if (res && res.success) { writeLog('Progress reset.'); if (res.data && res.data.state) setStatusUI(res.data.state); } } catch (e) { writeLog('Reset failed.'); }
    await refreshStatus();
  }

  $(function () {
    refreshStatus();
    $('#tbf-start-index').on('click', start);
    $('#tbf-stop-index').on('click', stop);
    $('#tbf-reset-index').on('click', reset);
    writeLog('Ready.');
  });

})(jQuery);
