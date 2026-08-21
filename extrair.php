<?php
/**
 * extrair.php - Sistema completo de alerta de faltas
 */

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use Smalot\PdfParser\Parser;

require_once __DIR__ . '/classes/AlunoParser.php';
require_once __DIR__ . '/classes/EmailService.php';
require_once __DIR__ . '/classes/Notificador.php';

$config = require __DIR__ . '/config.php';
$db     = require __DIR__ . '/database.php';

// --------------------------------------------------
// PEGA O LIMITE DE FALTAS DO FORMULARIO
// Se o professor digitou um valor, usa ele.
// Se nao veio nada, usa o valor padrao do config.php
// --------------------------------------------------
if (isset($_POST['limite_faltas']) && is_numeric($_POST['limite_faltas'])) {
    $limiteDigitado = (int) $_POST['limite_faltas'];
    if ($limiteDigitado >= 1 && $limiteDigitado <= 200) {
        $config['limite_faltas'] = $limiteDigitado;
    }
}

// --------------------------------------------------
// PEGA O(S) EMAIL(S) DE DESTINO DO FORMULARIO
// Se o usuario digitou algo, valida e usa esses emails.
// Se o campo ficou em branco, mantem os emails padrao
// definidos em config.php (email_pedagogos/email_direcao).
// --------------------------------------------------
if (!empty($_POST['destinatarios'])) {
    $emailsDigitados = array_map('trim', explode(',', $_POST['destinatarios']));
    $emailsValidos = array_filter($emailsDigitados, function ($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    });

    if (!empty($emailsValidos)) {
        // Substitui os destinatarios padrao pelos digitados no formulario
        $config['email_pedagogos'] = implode(',', $emailsValidos);
        $config['email_direcao']   = '';
    }
}

// Validacao do upload
if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    die("Erro: nenhum arquivo enviado.<br><a href='index.html'>Voltar</a>");
}

$arquivo   = $_FILES['arquivo'];
$extensao  = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
$caminhoTemp = $arquivo['tmp_name'];

$conteudoBruto = null;

try {
    switch ($extensao) {
        case 'pdf':
            $parser = new Parser();
            $pdf = $parser->parseFile($caminhoTemp);
            $conteudoBruto = $pdf->getText();
            break;

        case 'xlsx':
        case 'xls':
            $spreadsheet = IOFactory::load($caminhoTemp);
            $sheet = $spreadsheet->getActiveSheet();
            $conteudoBruto = $sheet->toArray();
            break;

        default:
            die("Erro: formato nao suportado. Use PDF, XLSX ou XLS.<br><a href='index.html'>Voltar</a>");
    }

    // Parsing
    $parserAlunos = new AlunoParser();
    $alunos = $parserAlunos->extrair($conteudoBruto, $extensao);

    if (empty($alunos)) {
        echo "<h2>Nenhum aluno reconhecido.</h2>";
        echo "<p>Verifique se o arquivo esta no formato esperado.</p>";
        echo "<a href='index.html'>Voltar</a>";
        exit;
    }

    // Notificacao
    $emailService = new EmailService($config);
    $notificador  = new Notificador($config, $db, $emailService);
    $resumo       = $notificador->processar($alunos);

    // Resultado na tela
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Resultado - Alerta de Faltas</title>
    </head>
    <body>

    <h1>Resumo do Processamento</h1>
    <p><strong>Arquivo:</strong> <?= htmlspecialchars($arquivo['name']) ?></p>
    <p><strong>Limite configurado:</strong> <?= $config['limite_faltas'] ?> faltas</p>
    <p><strong>Enviado para:</strong> <?= htmlspecialchars(trim(implode(', ', array_filter([$config['email_pedagogos'], $config['email_direcao']])), ', ')) ?></p>
    <hr>

    <p><strong>Alunos lidos:</strong> <?= $resumo['total_processado'] ?></p>
    <p><strong>Acima do limite:</strong> <?= $resumo['total_em_risco'] ?></p>
    <p><strong>Alertas enviados:</strong> <?= $resumo['novos_alertas'] ?></p>
    <p><strong>Ja notificados:</strong> <?= $resumo['ja_notificados'] ?></p>
    <hr>

    <?php if (!empty($resumo['lista_novos'])): ?>
    <h3>Novos Alertas Enviados</h3>
    <table border="1" cellpadding="8">
        <tr>
            <th>Nº</th>
            <th>Aluno</th>
            <th>Aulas</th>
            <th>Faltas</th>
            <th>FJ</th>
            <th>Total</th>
        </tr>
        <?php foreach ($resumo['lista_novos'] as $a): ?>
        <tr>
            <td><?= $a['numero'] ?></td>
            <td><?= htmlspecialchars($a['nome']) ?></td>
            <td><?= $a['aulas_dadas'] ?></td>
            <td><?= $a['total_faltas'] ?></td>
            <td><?= $a['total_faltas_just'] ?></td>
            <td><strong><?= $a['total_geral'] ?></strong></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <?php if (!empty($resumo['lista_ja_notif'])): ?>
    <h3>Ja Notificados Anteriormente</h3>
    <table border="1" cellpadding="8">
        <tr>
            <th>Nº</th>
            <th>Aluno</th>
            <th>Aulas</th>
            <th>Faltas</th>
            <th>FJ</th>
            <th>Total</th>
        </tr>
        <?php foreach ($resumo['lista_ja_notif'] as $a): ?>
        <tr>
            <td><?= $a['numero'] ?></td>
            <td><?= htmlspecialchars($a['nome']) ?></td>
            <td><?= $a['aulas_dadas'] ?></td>
            <td><?= $a['total_faltas'] ?></td>
            <td><?= $a['total_faltas_just'] ?></td>
            <td><strong><?= $a['total_geral'] ?></strong></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <?php if ($resumo['novos_alertas'] > 0): ?>
        <?php if ($resumo['email_enviado']): ?>
            <p><strong>✅ Email enviado com sucesso!</strong></p>
        <?php else: ?>
            <p><strong>❌ Falha ao enviar email.</strong></p>
        <?php endif; ?>
    <?php elseif ($resumo['total_em_risco'] === 0): ?>
        <p><strong>✅ Nenhum aluno ultrapassou o limite de <?= $config['limite_faltas'] ?> faltas.</strong></p>
    <?php endif; ?>

    <hr>
    <a href="index.html">Extrair outro arquivo</a>

    </body>
    </html>
    <?php

} catch (Exception $e) {
    echo "Erro ao processar: " . htmlspecialchars($e->getMessage());
    echo "<br><a href='index.html'>Voltar</a>";
}