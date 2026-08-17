  <!-- Bootstrap 5 JS (modais, dropdowns etc.) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <?php if (!empty($incluirAdminJs)): ?>
    <!-- JS do painel, compilado a partir de src/admin.ts (npm run build) -->
    <script type="module" src="../assets/js/admin.js"></script>
  <?php endif; ?>

</body>
</html>
