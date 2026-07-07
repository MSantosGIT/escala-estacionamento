<?php
// ============================================================
//  Funções auxiliares, sessão e autenticação
// ============================================================
require_once __DIR__ . '/../config/db.php';

// ---- Headers de segurança HTTP (aplicados a todas as telas) ----
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');        // bloqueia clickjacking
    header('X-Content-Type-Options: nosniff');    // impede MIME sniffing
    header('Referrer-Policy: same-origin');       // não vaza URLs a sites externos
}

// Tempo máximo de inatividade antes de exigir novo login (segundos)
define('SESSAO_TIMEOUT', 30 * 60); // 30 minutos

if (session_status() === PHP_SESSION_NONE) {
    // Garante que o PHP não mantenha a sessão viva além do nosso controle.
    // (gc_maxlifetime alinhado ao nosso timeout evita sessão "ressuscitada")
    ini_set('session.gc_maxlifetime', SESSAO_TIMEOUT);
    ini_set('session.use_strict_mode', '1');

    // Cookie de sessão NÃO persistente: expira ao fechar o navegador
    // (lifetime = 0 significa "cookie de sessão", apagado ao fechar o browser)
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,    // só envia por HTTPS quando disponível
        'httponly' => true,       // bloqueia acesso via JavaScript (anti-XSS)
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---- Controle de inatividade ----
// Se o usuário está logado e ficou mais de SESSAO_TIMEOUT sem atividade,
// destrói a sessão e manda para o login com aviso.
if (!empty($_SESSION['usuario'])) {
    $agora = time();
    $inativo = isset($_SESSION['ultima_atividade'])
        && ($agora - $_SESSION['ultima_atividade']) > SESSAO_TIMEOUT;
    // segurança extra: se por algum motivo não houver marcador de atividade,
    // trata como expirada (evita sessão "órfã" sem timestamp)
    $semMarcador = !isset($_SESSION['ultima_atividade']);

    if ($inativo || $semMarcador) {
        // sessão expirada por inatividade
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        // reinicia uma sessão limpa só para carregar o flash
        session_start();
        $_SESSION['flash'] = ['msg' => 'Sua sessão expirou. Faça login novamente.', 'tipo' => 'erro'];
        // só redireciona se não estiver já na tela de login
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if ($script !== 'login.php') {
            header('Location: login.php');
            exit;
        }
    } else {
        // atividade recente: renova o marcador
        $_SESSION['ultima_atividade'] = $agora;
    }
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
    return ['lider' => 'A1', 'pleno' => 'A2', 'junior' => 'A3'][$n] ?? $n;
}

function tokenCSRF(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function validarCSRF(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        die('Falha na validação CSRF.');
    }
}
