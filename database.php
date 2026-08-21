<?php
/**
 * DATABASE.PHP
 * 
 * Responsabilidade: criar a conexão com o SQLite e garantir
 * que a tabela de notificações exista.
 * 
 * PDO vs SQLite3: estou usando PDO porque é o padrão moderno
 * do PHP, mais seguro contra SQL Injection e com melhor
 * tratamento de erros.
 */

// Caminho do arquivo do banco. Ele será criado automaticamente
// na primeira vez que o script rodar.
$dbPath = __DIR__ . '/faltas.db';

try {
    // Cria (ou abre) o banco SQLite
    // SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE = cria se não existir
    $db = new PDO('sqlite:' . $dbPath);

    // Configura o PDO para lançar exceções em caso de erro
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Configura para strings virem como UTF-8
    $db->exec("PRAGMA encoding = 'UTF-8'");

} catch (PDOException $e) {
    die("Erro ao conectar no banco de dados: " . $e->getMessage());
}

// --------------------------------------------------
// CRIA A TABELA SE ELA AINDA NÃO EXISTIR
// --------------------------------------------------
$sql = "CREATE TABLE IF NOT EXISTS notificacoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome_aluno TEXT NOT NULL,
    total_faltas INTEGER NOT NULL,
    bimestre INTEGER NOT NULL,
    ano INTEGER NOT NULL,
    data_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
    destinatarios TEXT NOT NULL
)";

$db->exec($sql);

// Retorna a conexão para quem incluir este arquivo
return $db;