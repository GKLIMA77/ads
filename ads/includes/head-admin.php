<?php
// head-admin.php — mesma ideia do header.php do site público:
// um só lugar com o <head>, reaproveitado pela tela de login
// e pelo painel, em vez de repetir esse bloco duas vezes.
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($tituloPagina ?? 'Admin — Barbearia Adrian Souza'); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;700&family=Lora:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
