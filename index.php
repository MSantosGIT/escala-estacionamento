<?php
// Roteador inicial — usado como start_url do PWA.
// Manda o usuário para o dashboard se já estiver logado, ou para o login.
require_once __DIR__ . '/includes/functions.php';
if (logado()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
