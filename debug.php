<?php
/**
 * DEBUG.PHP
 * 
 * Use este arquivo para testar a extração SEM enviar email,
 * sem salvar no banco, sem nada. Só para ver se o parser
 * está pegando os alunos certos.
 * 
 * Como usar:
 * 1. Coloque este arquivo na pasta do projeto
 * 2. Acesse no navegador: http://localhost/texte2026/debug.php
 * 3. Faça upload do PDF
 * 4. Veja o resultado na tela
 */

require 'vendor/autoload.php';
use Smalot\PdfParser\Parser;

// Se nenhum arquivo foi enviado, mostra o form
if (!isset($_FILES['arquivo'])) {
    echo '<form action="debug.php" method="POST" enctype="multipart/form-data">';
    echo '<input type="file" name="arquivo" accept=".pdf" required>';
    echo '<button type="submit">Testar Extração</button>';
    echo '</form>';
    exit;
}

// Pega o PDF
$arquivo = $_FILES['arquivo'];
$parser = new Parser();
$pdf = $parser->parseFile($arquivo['tmp_name']);
$texto = $pdf->getText();

// Mostra o TEXTO BRUTO (primeiros 3000 caracteres)
echo '<h2>Texto bruto (primeiros 3000 chars):</h2>';
echo '<pre style="background:#1e293b;color:#e2e8f0;padding:16px;overflow:auto;max-height:300px;font-size:11px;">';
echo htmlspecialchars(substr($texto, 0, 3000));
echo '</pre>';

// ============================================================
// AQUI COMEÇA A MÁGICA DO PARSER
// ============================================================

$linhas = explode("\n", $texto);
$alunos = [];

foreach ($linhas as $numLinha => $linha) {
    $linha = trim($linha);

    // Pula linhas muito curtas
    if (strlen($linha) < 20) continue;

    // --------------------------------------------------
    // REGEX PARA O FORMATO:
    // 1AMANDA VITORINO SOUZA  • • • • • • ... 0 0
    // 
    // Grupo 1: numero do aluno
    // Grupo 2: nome do aluno  
    // Grupo 3: marcacoes (•, F, FJ separados por espacos/tabs)
    // Grupo 4: total F
    // Grupo 5: total FJ
    // --------------------------------------------------
    $padrao = '/^(\d+)([A-Za-z\x{00C0}-\x{00FF}\s\.\-\(\)]+?)\s+([\x{2022}\x{25CF}\*\sFJ]+)\s+(\d+)\s+(\d+)$/u';

    if (preg_match($padrao, $linha, $m)) {

        $numero = (int) $m[1];
        $nome   = trim($m[2]);
        $marcacoesBruto = trim($m[3]);
        $totalF  = (int) $m[4];
        $totalFJ = (int) $m[5];

        // Limpa o nome: remove (Vice Lider), (Lider), etc.
        $nome = preg_replace('/\s*\(.*?\)\s*$/', '', $nome);

        // Processa as marcacoes
        $presencas = [];
        $itens = preg_split('/\s+/', $marcacoesBruto);
        foreach ($itens as $item) {
            $item = trim($item);
            if ($item === '' || $item === ' ' || $item === "\t") continue;

            // Detecta o tipo de marcacao
            if ($item === 'FJ') {
                $presencas[] = 'FJ';
            } elseif ($item === 'F') {
                $presencas[] = 'F';
            } else {
                // Qualquer outra coisa (•, ●, *, etc.) = Presente
                $presencas[] = 'P';
            }
        }

        // Conta manualmente para validar
        $contagemF = 0;
        $contagemFJ = 0;
        foreach ($presencas as $p) {
            if ($p === 'F') $contagemF++;
            if ($p === 'FJ') $contagemFJ++;
        }

        $alunos[] = [
            'numero'            => $numero,
            'nome'              => $nome,
            'presencas'         => $presencas,
            'total_faltas_pdf'  => $totalF,
            'total_fj_pdf'      => $totalFJ,
            'total_geral'       => $totalF + $totalFJ,
            'aulas_dadas'       => count($presencas),
            'contagem_manual_f' => $contagemF,
            'contagem_manual_fj'=> $contagemFJ,
            'linha_debug'       => $linha  // guarda a linha original para debug
        ];
    }
}

// ============================================================
// MOSTRA O RESULTADO
// ============================================================

echo '<h2>Alunos encontrados: ' . count($alunos) . '</h2>';

if (empty($alunos)) {
    echo '<p style="color:red;"><strong>Nenhum aluno foi reconhecido!</strong></p>';
    echo '<p>O regex não bateu com nenhuma linha. Veja o texto bruto acima e me envie.</p>';
} else {
    echo '<table border="1" cellpadding="8" style="border-collapse:collapse;font-family:monospace;font-size:12px;">';
    echo '<tr style="background:#333;color:#fff;">';
    echo '<th>Nº</th><th>Nome</th><th>Aulas</th><th>F (PDF)</th><th>FJ (PDF)</th><th>Total</th><th>F (Manual)</th><th>FJ (Manual)</th><th>Match?</th>';
    echo '</tr>';

    foreach ($alunos as $a) {
        $matchF  = ($a['total_faltas_pdf'] === $a['contagem_manual_f']) ? '✅' : '❌';
        $matchFJ = ($a['total_fj_pdf'] === $a['contagem_manual_fj']) ? '✅' : '❌';

        echo '<tr>';
        echo '<td>' . $a['numero'] . '</td>';
        echo '<td>' . htmlspecialchars($a['nome']) . '</td>';
        echo '<td>' . $a['aulas_dadas'] . '</td>';
        echo '<td>' . $a['total_faltas_pdf'] . '</td>';
        echo '<td>' . $a['total_fj_pdf'] . '</td>';
        echo '<td><strong>' . $a['total_geral'] . '</strong></td>';
        echo '<td>' . $a['contagem_manual_f'] . ' ' . $matchF . '</td>';
        echo '<td>' . $a['contagem_manual_fj'] . ' ' . $matchFJ . '</td>';
        echo '<td>' . (($matchF === '✅' && $matchFJ === '✅') ? '✅ OK' : '⚠️ Diferença') . '</td>';
        echo '</tr>';
    }

    echo '</table>';

    // Mostra linhas que NÃO deram match (para debug)
    echo '<h3>Linhas que NÃO deram match (primeiras 20):</h3>';
    echo '<pre style="background:#fef3c7;padding:12px;font-size:11px;">';
    $naoMatch = 0;
    foreach ($linhas as $linha) {
        $linha = trim($linha);
        if (strlen($linha) > 20 && !preg_match($padrao, $linha)) {
            echo htmlspecialchars(substr($linha, 0, 200)) . "\n";
            $naoMatch++;
            if ($naoMatch >= 20) break;
        }
    }
    if ($naoMatch === 0) echo 'Todas as linhas longas deram match!';
    echo '</pre>';
}