<?php
// ============================================================
// FOOTER — Global JS + close tags
// ============================================================
?>
  <!-- Scripts -->
  <script src="<?= BASE_PATH ?>/assets/js/toast.js"></script>
  <script src="<?= BASE_PATH ?>/assets/js/modal.js"></script>
  <script src="<?= BASE_PATH ?>/assets/js/app.js"></script>
  <script src="<?= BASE_PATH ?>/assets/js/downloader.js"></script>

  <?php if (!empty($extraScripts)) echo $extraScripts; ?>

  <!-- Chat Widget (all authenticated pages) -->
  <?php
  if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/pusher.php';
    include __DIR__ . '/chat-widget.php';
  }
  ?>

  <!-- File Viewer -->
  <script src="<?= BASE_PATH ?>/assets/js/file-viewer.js"></script>

  <!-- Init Lucide Icons -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      if (window.lucide) lucide.createIcons();
    });
  </script>
</body>
</html>