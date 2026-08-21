<?php
/**
 * CLASSE: AlunoParser
 * 
 * Extrai dados de frequência de PDFs e Excels.
 * 
 * FORMATO DO PDF (texto bruto do getText()):
 * 
 *   1AMANDA VITORINO SOUZA	• • • • • • • • • • • • • • • • • • • • • • • • 0 0
 *   4ANNA CAROLINY BERNABE SALES	• • • • • • • • • • • • F F • • • • • • • • • • 2 0
 *   27PHILIPE MERLIM BRISSON DA COSTA	FJ FJ FJ FJ FJ FJ FJ FJ FJ FJ FJ FJ FJ FJ • • • • • • • • • • 0 14
 * 
 * ESTRUTURA DE CADA LINHA:
 *   [numero][nome]  [marcacoes]  [total F] [total FJ]
 * 
 * Onde:
 *   - numero: digitos no inicio (1, 4, 27...)
 *   - nome: letras, espacos, pontos, hifens, parenteses
 *   - marcacoes: sequencia de • (presente), F (falta), FJ (falta justificada)
 *   - total F: numero de faltas nao justificadas
 *   - total FJ: numero de faltas justificadas
 */

class AlunoParser
{
    /**
     * Método principal.
     */
    public function extrair($conteudo, string $extensao): array
    {
        if ($extensao === 'pdf') {
            return $this->extrairDoPdf($conteudo);
        }

        if (in_array($extensao, ['xlsx', 'xls'])) {
            return $this->extrairDoExcel($conteudo);
        }

        return [];
    }

    /**
     * Extrai alunos do texto de PDF.
     * 
     * COMO FUNCIONA PASSO A PASSO:
     * 
     * 1. Divide o texto em linhas com explode("\n")
     * 2. Para cada linha, tenta aplicar um REGEX
     * 3. O regex captura 5 grupos da linha:
     *    Grupo 1: numero do aluno  (ex: "1", "27")
     *    Grupo 2: nome do aluno     (ex: "AMANDA VITORINO SOUZA")
     *    Grupo 3: marcacoes         (ex: "• • • • F F • •")
     *    Grupo 4: total F           (ex: "2")
     *    Grupo 5: total FJ          (ex: "0")
     * 4. Processa as marcacoes contando F e FJ
     * 5. Monta o array final
     */
    private function extrairDoPdf(string $texto): array
    {
        $linhas = explode("\n", $texto);
        $alunos = [];

        foreach ($linhas as $linha) {
            $linha = trim($linha);

            // Pula linhas muito curtas (menos de 20 chars nao pode ser aluno)
            if (strlen($linha) < 20) {
                continue;
            }

            // --------------------------------------------------
            // O REGEX EXPLICADO:
            // 
            // ^(\d+)                           -> Grupo 1: um ou mais digitos no INICIO
            // ([A-Za-z\x{00C0}-\x{00FF}\s\.\-\(\)]+?) -> Grupo 2: nome (letras, acentos, espacos, pontos, hifens, parenteses). O +? = "preguiçoso" (para no primeiro espaco/tab que vem antes das marcacoes)
            // \s+                              -> um ou mais espacos/tabs (separa nome das marcacoes)
            // ([\x{2022}\x{25CF}\*\sFJ]+)       -> Grupo 3: marcacoes. Aceita: • (bullet), ● (circulo preto), *, espacos, F, FJ
            // \s+                              -> espacos/tabs
            // (\d+)                            -> Grupo 4: total F (digitos)
            // \s+                              -> espaco
            // (\d+)                            -> Grupo 5: total FJ (digitos)
            // $                                -> fim da linha
            // 
            // A flag /u no final diz que é Unicode (para os acentos e o bullet funcionarem)
            // --------------------------------------------------
            $padrao = '/^(\d+)([A-Za-z\x{00C0}-\x{00FF}\s\.\-\(\)]+?)\s+([\x{2022}\x{25CF}\*\sFJ]+)\s+(\d+)\s+(\d+)$/u';

            if (preg_match($padrao, $linha, $m)) {

                $numero = (int) $m[1];
                $nome   = trim($m[2]);
                $marcacoesBruto = trim($m[3]);
                $totalF  = (int) $m[4];
                $totalFJ = (int) $m[5];

                // --------------------------------------------------
                // LIMPA O NOME
                // Remove sufixos como "(Vice Lider)", "(Lider)" do final
                // --------------------------------------------------
                $nome = preg_replace('/\s*\(.*?\)\s*$/', '', $nome);

                // --------------------------------------------------
                // PROCESSA AS MARCACOES
                // 
                // Divide a string de marcacoes por espacos
                // "• • • • F F • •" vira: ['•', '•', '•', '•', 'F', 'F', '•', '•']
                // Depois converte para padrao interno:
                //   • -> 'P' (Presente)
                //   F -> 'F' (Falta)
                //   FJ -> 'FJ' (Falta Justificada)
                // --------------------------------------------------
                $presencas = [];
                $itens = preg_split('/\s+/', $marcacoesBruto);

                foreach ($itens as $item) {
                    $item = trim($item);
                    if ($item === '') continue;

                    if ($item === 'FJ') {
                        $presencas[] = 'FJ';
                    } elseif ($item === 'F') {
                        $presencas[] = 'F';
                    } else {
                        // Qualquer outra coisa (•, ●, *, etc.) = Presente
                        $presencas[] = 'P';
                    }
                }

                // Conta manualmente para validar com os totais do PDF
                $contagemF  = 0;
                $contagemFJ = 0;
                foreach ($presencas as $p) {
                    if ($p === 'F')  $contagemF++;
                    if ($p === 'FJ') $contagemFJ++;
                }

                // Só adiciona se o nome for valido (pelo menos 3 letras)
                if (strlen($nome) >= 3) {
                    $alunos[] = [
                        'numero'            => $numero,
                        'nome'              => $nome,
                        'presencas'         => $presencas,
                        'total_faltas'      => $totalF,
                        'total_faltas_just' => $totalFJ,
                        'total_geral'       => $totalF,
                        'aulas_dadas'       => count($presencas),
                        // Campos de debug (pode remover depois):
                        'contagem_manual_f'  => $contagemF,
                        'contagem_manual_fj' => $contagemFJ,
                        'match_f'           => ($totalF === $contagemF),
                        'match_fj'          => ($totalFJ === $contagemFJ)
                    ];
                }
            }
        }

        return $alunos;
    }

    /**
     * Extrai alunos do array que veio do Excel.
     */
    private function extrairDoExcel(array $linhas): array
    {
        $alunos = [];

        if (empty($linhas)) return $alunos;

        $primeira = $linhas[0];
        $colNome   = 0;
        $colFaltas = 1;
        $temCabecalho = false;

        foreach ($primeira as $indice => $valor) {
            $valorLimpo = strtolower(trim((string)$valor));

            if (in_array($valorLimpo, ['aluno', 'nome', 'nome do aluno', 'estudante'])) {
                $colNome = $indice;
                $temCabecalho = true;
            }
            if (in_array($valorLimpo, ['faltas', 'total de faltas', 'falta', 'frequencia', 'freq'])) {
                $colFaltas = $indice;
                $temCabecalho = true;
            }
        }

        $inicio = $temCabecalho ? 1 : 0;

        for ($i = $inicio; $i < count($linhas); $i++) {
            $linha = $linhas[$i];

            if (!isset($linha[$colNome]) || !isset($linha[$colFaltas])) {
                continue;
            }

            $nome   = trim((string) $linha[$colNome]);
            $faltas = $linha[$colFaltas];

            if (is_string($faltas)) {
                $faltas = str_replace(',', '.', $faltas);
                $faltas = (int) $faltas;
            } else {
                $faltas = (int) $faltas;
            }

            if (!empty($nome) && $faltas >= 0 && $faltas <= 200) {
                $alunos[] = [
                    'numero'            => $i,
                    'nome'              => $nome,
                    'presencas'         => [],
                    'total_faltas'      => $faltas,
                    'total_faltas_just' => 0,
                    'total_geral'       => $faltas,
                    'aulas_dadas'       => 0
                ];
            }
        }

        return $alunos;
    }
}