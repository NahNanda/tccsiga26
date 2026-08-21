<?php
/**
 * EmailService - Envia emails de alerta
 *
 * IMPORTANTE: Esta versão usa a API HTTP do Brevo (antigo Sendinblue)
 * em vez de SMTP. Motivo: a rede da escola bloqueia as portas de SMTP
 * (25/465/587), mas libera HTTPS normal (porta 443), que é o que
 * qualquer navegador usa. A API do Brevo funciona via HTTPS, então
 * contorna esse bloqueio.
 *
 * Para usar, você precisa de uma conta grátis no Brevo e de uma
 * "API Key" (chave de API). Veja o passo a passo no final deste arquivo.
 */

class EmailService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function enviarAlerta(array $alunos, string $destinatarios, int $bimestre, int $ano): bool
    {
        $templatePath = __DIR__ . '/../templates/email_alerta.html';

        if (file_exists($templatePath)) {
            $html = file_get_contents($templatePath);
            $html = $this->preencherTemplate($html, $alunos, $bimestre, $ano);
        } else {
            $html = $this->montarTextoSimples($alunos, $bimestre, $ano);
        }

        return $this->enviarComBrevo($html, $destinatarios, $bimestre, $ano);
    }

    /**
     * Envia o email usando a API HTTP do Brevo (https://api.brevo.com).
     *
     * Isso substitui o envio por SMTP. Não precisa de PHPMailer,
     * não precisa da extensão openssl, e usa a porta 443 (HTTPS),
     * que normalmente não é bloqueada em redes de escola/empresa.
     */
    private function enviarComBrevo(string $html, string $destinatarios, int $bimestre, int $ano): bool
    {
        $apiKey = $this->config['brevo']['api_key'] ?? '';

        if (empty($apiKey)) {
            error_log("Erro Brevo: API key não configurada em config.php ['brevo']['api_key']");
            return false;
        }

        $emails = array_map('trim', explode(',', $destinatarios));
        $destinatariosValidos = [];
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $destinatariosValidos[] = ['email' => $email];
            }
        }

        if (empty($destinatariosValidos)) {
            error_log("Erro Brevo: nenhum destinatário válido encontrado em '$destinatarios'");
            return false;
        }

        $payload = [
            'sender' => [
                'name'  => $this->config['brevo']['from_name']  ?? 'Sistema de Faltas Escolar',
                'email' => $this->config['brevo']['from_email'] ?? '',
            ],
            'to'      => $destinatariosValidos,
            'subject' => "Alerta de Faltas - {$bimestre}º Bimestre / {$ano}",
            'htmlContent' => $html,
        ];

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'api-key: ' . $apiKey,
                'content-type: application/json',
            ],
            CURLOPT_TIMEOUT        => 20,
        ]);

        $resposta   = curl_exec($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErro   = curl_error($ch);
        // curl_close($ch) foi removido: desde o PHP 8.0 essa função não faz
        // mais nada (o PHP libera a conexão sozinho), e a partir do PHP 8.5
        // ela virou "deprecated", gerando esse aviso no log.

        // A API do Brevo retorna 201 quando o email foi aceito para envio
        if ($httpCode === 201) {
            return true;
        }

        error_log("Erro Brevo (HTTP {$httpCode}): " . ($curlErro ?: $resposta));
        return false;
    }

    private function preencherTemplate(string $html, array $alunos, int $bimestre, int $ano): string
    {
        $linhas = '';
        foreach ($alunos as $a) {
            $linhas .= "<tr>";
            $linhas .= "<td>" . htmlspecialchars($a['nome']) . "</td>";
            $linhas .= "<td>" . $a['total_geral'] . "</td>";
            $linhas .= "<td>" . $a['total_faltas'] . "</td>";
            $linhas .= "<td>" . $a['total_faltas_just'] . "</td>";
            $linhas .= "</tr>";
        }

        $html = str_replace('{{BIMESTRE}}', $bimestre, $html);
        $html = str_replace('{{ANO}}', $ano, $html);
        $html = str_replace('{{LINHAS_ALUNOS}}', $linhas, $html);
        $html = str_replace('{{TOTAL_ALUNOS}}', count($alunos), $html);

        return $html;
    }

    private function montarTextoSimples(array $alunos, int $bimestre, int $ano): string
    {
        $html = "<h2>Alerta de Faltas - {$bimestre}º Bimestre / {$ano}</h2>";
        $html .= "<table border='1' cellpadding='6'>";
        $html .= "<tr><th>Aluno</th><th>Total</th><th>Faltas</th><th>FJ</th></tr>";

        foreach ($alunos as $a) {
            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($a['nome']) . "</td>";
            $html .= "<td>" . $a['total_geral'] . "</td>";
            $html .= "<td>" . $a['total_faltas'] . "</td>";
            $html .= "<td>" . $a['total_faltas_just'] . "</td>";
            $html .= "</tr>";
        }

        $html .= "</table>";
        return $html;
    }
}

/**
 * ============================================================
 * COMO CONFIGURAR O BREVO (passo a passo)
 * ============================================================
 *
 * 1. Crie uma conta grátis em https://www.brevo.com (300 emails/dia grátis).
 *
 * 2. Confirme o email de verificação da conta.
 *
 * 3. No painel do Brevo, vá em:
 *    Menu (canto superior direito, ícone de engrenagem) > SMTP & API > API Keys
 *    Clique em "Generate a new API key", dê um nome (ex: "sistema-faltas")
 *    e copie a chave gerada (começa com "xkeysib-...").
 *
 * 4. IMPORTANTE: no Brevo, você só pode enviar emails a partir de um
 *    endereço de remetente verificado. Vá em:
 *    Menu > Senders, Domains & Dedicated IPs > Senders
 *    Adicione o email que você quer usar como remetente (ex: seu email
 *    da escola) e confirme a verificação (o Brevo manda um link de
 *    confirmação para esse email).
 *
 * 5. No config.php do projeto, adicione (ou troque o bloco 'smtp' por):
 *
 *    'brevo' => [
 *        'api_key'    => 'xkeysib-SUACHAVEAQUI',
 *        'from_email' => 'seuemail@escola.com', // precisa estar verificado no passo 4
 *        'from_name'  => 'Sistema de Faltas Escolar',
 *    ],
 *
 * 6. Pronto. Não precisa mais preencher host/porta/senha de SMTP.
 * ============================================================
 */