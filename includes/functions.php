<?php
// ============================================================
//  Funções auxiliares, sessão e autenticação
// ============================================================
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(?string $v): string {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function flash(string $msg = null, string $tipo = 'sucesso') {
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'tipo' => $tipo];
        return;
    }
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function logado(): bool {
    return !empty($_SESSION['usuario']);
}

function usuario(): ?array {
    return $_SESSION['usuario'] ?? null;
}

function ehAdmin(): bool {
    return logado() && $_SESSION['usuario']['tipo'] === 'administrador';
}

function exigirLogin(): void {
    if (!logado()) redirect('login.php');
}

function exigirAdmin(): void {
    exigirLogin();
    if (!ehAdmin()) {
        flash('Acesso restrito ao administrador.', 'erro');
        redirect('dashboard.php');
    }
}

function nivelLabel(string $n): string {
    return ['junior' => 'Júnior', 'pleno' => 'Pleno', 'lider' => 'Líder'][$n] ?? $n;
}

function tokenCSRF(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function validarCSRF(): void {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        die('Falha na validação CSRF.');
    }
}
