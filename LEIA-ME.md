# Como rodar o Extrator de PDF/Excel em qualquer computador (Windows)

Este projeto é feito em **PHP**. PHP não é um programa que se "abre" clicando
duas vezes — ele precisa de um **servidor** para funcionar. No computador do
professor isso já está resolvido (provavelmente por um XAMPP/Laragon já
instalado e configurado). Em outro computador "do zero", nada disso existe
ainda — é por isso que funciona só numa máquina.

A solução abaixo cria uma versão **portátil**: uma pasta com um PHP
"de bolso" dentro do próprio projeto, sem precisar instalar nada no
Windows nem mexer no PATH do sistema. O aluno só baixa uma pasta, extrai
um zip dentro dela e clica duas vezes em um `.bat`.

## Passo 1 — Baixar o PHP portátil (uma vez só, por computador)

1. Acesse **https://windows.php.net/download/**
2. Baixe a versão **PHP 8.3.x** (ou 8.4.x), opção **x64 Non Thread Safe (NTS) Zip**.
   - Não precisa da versão "Thread Safe" nem de instalador (.msi/.exe).
   - Tem que ser 8.3 ou 8.4 — este projeto usa uma biblioteca que exige
     PHP 64 bits versão 8.3 ou superior, e outra que exige versão anterior à 8.6.
3. Extraia o conteúdo do zip baixado dentro da pasta do projeto, criando uma
   pasta chamada exatamente `php`. A estrutura final deve ficar assim:

   ```
   texte2026/
   ├── php/              <- PHP portátil extraído aqui
   │   ├── php.exe
   │   ├── php.ini-development
   │   └── ...
   ├── vendor/
   ├── classes/
   ├── index.html
   ├── extrair.php
   └── iniciar_servidor.bat
   ```

## Passo 2 — Configurar o `php.ini`

1. Dentro da pasta `php`, copie o arquivo `php.ini-development` e renomeie a
   cópia para `php.ini`.
2. Abra `php.ini` com o Bloco de Notas e localize (Ctrl+F) cada linha abaixo.
   Remova o `;` do começo de cada uma (isso "ativa" a extensão):

   ```
   extension=fileinfo
   extension=gd
   extension=mbstring
   extension=zip
   ```

3. Ainda no `php.ini`, localize a linha `; extension_dir = "ext"` e deixe assim
   (sem o `;` na frente):

   ```
   extension_dir = "ext"
   ```

4. Salve e feche o arquivo.

## Passo 3 — Rodar o sistema

1. Dê duplo clique em **`iniciar_servidor.bat`** (fica na pasta raiz do projeto).
2. Uma janela preta vai abrir e o navegador vai abrir sozinho em
   `http://localhost:8000`.
3. Use o sistema normalmente. **Não feche a janela preta** enquanto estiver
   usando — é o "motor" rodando por trás.
4. Para encerrar, feche a janela preta (ou pressione `Ctrl+C` dentro dela).

## Observações importantes

- A pasta `vendor/` (com `phpoffice/phpspreadsheet` e `smalot/pdfparser`) **já
  vem pronta dentro do projeto** — não é necessário instalar o Composer nem
  rodar `composer install` em outro computador. Isso já resolve boa parte do
  problema de portabilidade.
- Repita **apenas o Passo 1 e 2** uma vez por computador novo. Depois disso,
  é só duplo clique no `.bat` sempre que for usar.
- Se aparecer erro de extensão faltando (ex: "Call to undefined function
  gd_info()" ou similar ao processar Excel/PDF), volte ao Passo 2 e confira
  se a linha da extensão citada no erro está mesmo sem `;` na frente.
- Isso roda 100% localmente (`localhost`) — nenhum arquivo enviado sai do
  computador do aluno.
