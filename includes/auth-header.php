<?php
/**
 * ============================================================
 *  Multimedia Class — Shared Auth Header Template
 * ============================================================
 *  Di-include oleh semua halaman auth (login, register, dll.)
 *  untuk konsistensi tampilan.
 *
 *  Variabel yang wajib di-set SEBELUM include file ini:
 *    $pageTitle   (string) — judul halaman
 *    $pageDesc    (string, optional) — meta description
 * ============================================================
 */

// Proteksi: jangan akses langsung
if (!defined('APP_NAME')) {
    header('Location: ../index.php');
    exit;
}

$pageDesc = $pageDesc ?? 'Sistem pembelajaran ekskul Multimedia — MTs & MA YABIKA';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url"    content="<?= e(BASE_URL) ?>">
    <meta name="description" content="<?= e($pageDesc) ?>">

    <title><?= e($pageTitle) ?></title>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- App CSS -->
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/main.css">
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/auth.css">
    <link rel="icon" type="image/png" href="<?= e(BASE_URL . LOGO_PATH) ?>">
</head>
<body class="auth-body">

<!-- Dekorasi background -->
<div class="auth-deco auth-deco-1" aria-hidden="true"></div>
<div class="auth-deco auth-deco-2" aria-hidden="true"></div>
<div class="auth-deco auth-deco-3" aria-hidden="true"></div>
