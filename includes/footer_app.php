  </main><!-- end .page-content -->
</div><!-- end .main-content -->
</div><!-- end .app-wrapper -->

<?= isset($extraScripts) ? $extraScripts : '' ?>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="/reussiteplus/assets/js/ia-pdf.js?v=<?= filemtime(__DIR__ . '/../assets/js/ia-pdf.js') ?>"></script>
<script src="/reussiteplus/assets/js/exam-pdf.js?v=<?= filemtime(__DIR__ . '/../assets/js/exam-pdf.js') ?>"></script>
<script src="/reussiteplus/assets/js/receipt-pdf.js?v=<?= filemtime(__DIR__ . '/../assets/js/receipt-pdf.js') ?>"></script>
<script src="/reussiteplus/assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body>
</html>
