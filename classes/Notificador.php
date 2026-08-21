<?php
/**
 * CLASSE: Notificador
 * 
 * Orquestra o fluxo de alerta:
 * 1. Recebe lista de alunos do AlunoParser
 * 2. Filtra quem ultrapassou o limite (usando 'total_geral')
 * 3. Verifica no banco se ja foi notificado neste bimestre/ano
 * 4. Envia email para novos casos
 * 5. Registra no banco
 * 6. Devolve resumo
 */

class Notificador
{
    private array $config;
    private PDO $db;
    private EmailService $email;

    public function __construct(array $config, PDO $db, EmailService $email)
    {
        $this->config = $config;
        $this->db     = $db;
        $this->email  = $email;
    }

    /**
     * Processa a lista de alunos e retorna resumo.
     */
    public function processar(array $alunos): array
    {
        $limite    = $this->config['limite_faltas'];
        $bimestre  = $this->config['bimestre'];
        $ano       = $this->config['ano'];

        $destinatarios = implode(',', [
            $this->config['email_pedagogos'],
            $this->config['email_direcao']
        ]);

        $emRisco       = [];
        $novosAlertas  = [];
        $jaNotificados = [];

        // --------------------------------------------------
        // PASSO 1: Filtrar alunos em risco (total_geral > limite)
        // --------------------------------------------------
        foreach ($alunos as $aluno) {
            if ($aluno['total_geral'] > $limite) {
                $emRisco[] = $aluno;
            }
        }

        // --------------------------------------------------
        // PASSO 2: Verificar duplicidade no banco
        // --------------------------------------------------
        foreach ($emRisco as $aluno) {
            if ($this->jaFoiNotificado($aluno['nome'], $bimestre, $ano)) {
                $jaNotificados[] = $aluno;
            } else {
                $novosAlertas[] = $aluno;
            }
        }

        // --------------------------------------------------
        // PASSO 3: Enviar email (se houver novos casos)
        // --------------------------------------------------
        $emailEnviado = false;
        if (!empty($novosAlertas)) {
            $emailEnviado = $this->email->enviarAlerta(
                $novosAlertas,
                $destinatarios,
                $bimestre,
                $ano
            );

            if ($emailEnviado) {
                foreach ($novosAlertas as $aluno) {
                    $this->registrarNotificacao(
                        $aluno['nome'],
                        $aluno['total_geral'],
                        $bimestre,
                        $ano,
                        $destinatarios
                    );
                }
            }
        }

        // --------------------------------------------------
        // PASSO 4: Montar resumo
        // --------------------------------------------------
        return [
            'total_processado'  => count($alunos),
            'total_em_risco'    => count($emRisco),
            'novos_alertas'     => count($novosAlertas),
            'ja_notificados'    => count($jaNotificados),
            'email_enviado'     => $emailEnviado,
            'lista_novos'       => $novosAlertas,
            'lista_ja_notif'    => $jaNotificados
        ];
    }

    private function jaFoiNotificado(string $nome, int $bimestre, int $ano): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM notificacoes
            WHERE nome_aluno = :nome
              AND bimestre   = :bimestre
              AND ano        = :ano
        ");
        $stmt->execute([':nome' => $nome, ':bimestre' => $bimestre, ':ano' => $ano]);
        return $stmt->fetchColumn() > 0;
    }

    private function registrarNotificacao(string $nome, int $faltas, int $bimestre, int $ano, string $destinatarios): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO notificacoes (nome_aluno, total_faltas, bimestre, ano, destinatarios)
            VALUES (:nome, :faltas, :bimestre, :ano, :destinatarios)
        ");
        $stmt->execute([
            ':nome' => $nome, ':faltas' => $faltas,
            ':bimestre' => $bimestre, ':ano' => $ano,
            ':destinatarios' => $destinatarios
        ]);
    }
}