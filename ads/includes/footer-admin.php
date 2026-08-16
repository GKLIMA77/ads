<?php
// footer-admin.php — mesma ideia do footer.php do site público:
// fecha o <body>/<html> e carrega o Bootstrap JS. O admin.js só
// entra quando $incluirAdminJs = true (o painel logado precisa
// dele; a tela de login não).
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($incluirAdminJs)): ?>
<script src="assets/js/admin.js" type="module"></script>
<?php endif; ?>
</body>
</html>
