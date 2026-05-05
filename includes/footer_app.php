  </main><!-- end .page-content -->
</div><!-- end .main-content -->
</div><!-- end .app-wrapper -->

<?= isset($extraScripts) ? $extraScripts : '' ?>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="/reussiteplus/assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body>
</html>
