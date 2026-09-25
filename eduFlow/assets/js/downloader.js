/**
 * LMS Downloader — pure fetch+blob, never redirects the page.
 * Usage:
 *   LMSDownload.single('topic', fileId)
 *   LMSDownload.single('assignment', fileId)
 *   LMSDownload.single('submission', subId)
 *   LMSDownload.zip('topic_files', [1,2,3])
 *   LMSDownload.zip('assignment_files', [4,5])
 *   LMSDownload.zip('submissions', [7,8])
 */
const LMSDownload = (() => {

  async function single(type, id) {
    const btn = event?.currentTarget;
    if (btn) { btn.disabled = true; }
    try {
      const res = await fetch(`${window.LMS_BASE}/download.php?type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`, {
        credentials: 'same-origin'
      });
      if (!res.ok) {
        const txt = await res.text();
        Toast.error('Download failed: ' + txt.replace(/<[^>]+>/g,'').trim().slice(0,120));
        return;
      }
      // Extract filename from Content-Disposition
      const disp = res.headers.get('Content-Disposition') || '';
      let filename = 'download';
      const match = disp.match(/filename\*?=(?:UTF-8'')?["']?([^"';\n]+)/i);
      if (match) filename = decodeURIComponent(match[1].replace(/['"]/g,'').trim());

      const blob = await res.blob();
      _triggerBlob(blob, filename);
    } catch(e) {
      Toast.error('Download error: ' + e.message);
    } finally {
      if (btn) { btn.disabled = false; }
    }
  }

  async function zip(type, ids) {
    if (!ids || !ids.length) { Toast.warning('No files selected'); return; }
    const toastId = Toast.info('Preparing ZIP download…', 0); // persistent
    try {
      const fd = new FormData();
      fd.append('type', type);
      fd.append('ids', ids.join(','));

      const res = await fetch(window.LMS_BASE+'/download-zip.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      });

      if (!res.ok) {
        const txt = await res.text();
        Toast.error('ZIP failed: ' + txt.replace(/<[^>]+>/g,'').trim().slice(0,120));
        return;
      }

      const disp = res.headers.get('Content-Disposition') || '';
      let filename = 'files.zip';
      const match = disp.match(/filename\*?=(?:UTF-8'')?["']?([^"';\n]+)/i);
      if (match) filename = decodeURIComponent(match[1].replace(/['"]/g,'').trim());

      const blob = await res.blob();
      _triggerBlob(blob, filename);
      Toast.success(`Downloaded ${ids.length} file(s) as ZIP`);
    } catch(e) {
      Toast.error('ZIP error: ' + e.message);
    } finally {
      // dismiss persistent toast if Toast supports it
      if (typeof Toast.dismiss === 'function') Toast.dismiss(toastId);
    }
  }

  function _triggerBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(url); document.body.removeChild(a); }, 2000);
  }

  return { single, zip };
})();