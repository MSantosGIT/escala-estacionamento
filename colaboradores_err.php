<?php
error_reporting(E_ALL); ini_set("display_errors",1);
require_once __DIR__ . "/includes/functions.php";
exigirLogin();
if ($_SESSION["usuario"]["tipo"] !== "admin") { header("Location: dashboard.php"); exit; }
$pdo = db();
$acao = $_GET["acao"] ?? "";
$editar = null;
if ($acao === "editar") {
    $id = (int)($_GET["id"] ?? 0);
    $st = $pdo->prepare("SELECT * FROM colaboradores WHERE id=?");
    $st->execute([$id]);
    $editar = $st->fetch();
}
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acaoP = $_POST["acao"] ?? "";
    if ($acaoP === "salvar") {
        $id = (int)($_POST["id"] ?? 0);
        $nome = trim($_POST["nome"] ?? "");
        $cel  = trim($_POST["celular"] ?? "");
        $nivel= $_POST["nivel"] ?? "junior";
        $semana = isset($_POST["trabalha_semana"]) ? 1:0;
        $sabado = isset($_POST["trabalha_sabado"]) ? 1:0;
        $domMan  = isset($_POST["trabalha_domingo_manha"]) ? 1:0;
        $domNoite= isset($_POST["trabalha_domingo_noite"]) ? 1:0;
        if ($id>0) {
            $pdo->prepare("UPDATE colaboradores SET nome=?,celular=?,nivel=?,trabalha_semana=?,trabalha_sabado=?,trabalha_domingo_manha=?,trabalha_domingo_noite=? WHERE id=?")
                ->execute([$nome,$cel,$nivel,$semana,$sabado,$domMan,$domNoite,$id]);
            flash("Colaborador atualizado.");
        } else {
            $pdo->prepare("INSERT INTO colaboradores (nome,celular,nivel,trabalha_semana,trabalha_sabado,trabalha_domingo_manha,trabalha_domingo_noite) VALUES (?,?,?,?,?,?,?)")
                ->execute([$nome,$cel,$nivel,$semana,$sabado,$domMan,$domNoite]);
            $cid = $pdo->lastInsertId();
            $crLogin = trim($_POST["login"] ?? "");
            $crSenha = $_POST["senha"] ?? "";
            if ($crLogin !== "" && $crSenha !== "") {
                $hash = password_hash($crSenha, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO usuarios (nome,login,senha,tipo,colaborador_id) VALUES (?,?,?, " . "
colaborador" . ", ?)")->execute([$nome,$crLogin,$hash,$cid]);
            }
            flash("Colaborador cadastrado.");
        }
        header("Location: colaboradores.php");
        exit;
    }
    if ($acaoP === "excluir") {
        $id = (int)($_POST["id"] ?? 0);
        if ($id>0) { $pdo->prepare("UPDATE colaboradores SET ativo=0 WHERE id=?")->execute([$id]); flash("Colaborador inativado."); }
        header("Location: colaboradores.php");
        exit;
    }
}
$lista = $pdo->query("SELECT * FROM colaboradores WHERE ativo=1 ORDER BY FIELD(nivel," . "lider" . "," . "pleno" . "," . "junior" . "), nome")->fetchAll();
$titulo = "Colaboradores"; require __DIR__ . "/includes/header.php";

