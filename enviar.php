<?php
// Árvore do Bem · recebe o formulário, guarda na planilha do servidor e avisa por e-mail.
// A planilha é a garantia: o cadastro fica salvo mesmo que o e-mail falhe.

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

const PASTA = __DIR__ . '/privado';
const PLANILHA = PASTA . '/cadastros.csv';
const LOG_ERROS = PASTA . '/erros.log';
const TEMPO_MINIMO_MS = 4000;      // ninguém preenche os 3 passos em menos de 4 s
const ENVIOS_POR_HORA = 8;         // por IP
const MAX_CRIANCAS = 10;
const SUSPEITO = '~(https?:|www\.|\.(com|net|org|ru|cn|xyz|info|biz|top|shop|online|site)\b|[<>{}\[\]]|@)~i';
const PROIBIDAS = ['viagra', 'casino', 'cassino', 'bitcoin', 'crypto', 'cripto', 'forex', 'backlink', 'porn'];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

function responder(int $status, array $corpo): void
{
    http_response_code($status);
    echo json_encode($corpo, JSON_UNESCAPED_UNICODE);
    exit;
}

function registrarErro(string $msg): void
{
    @file_put_contents(LOG_ERROS, date('c') . ' ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

// Robôs recebem "ok" para não insistirem, mas nada é salvo nem enviado
function fingirSucesso(): void
{
    responder(200, ['ok' => true, 'email' => true]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responder(405, ['ok' => false, 'erro' => 'metodo']);
}

$config = is_file(PASTA . '/config.php') ? require PASTA . '/config.php' : [];
$destino = $config['destino'] ?? 'contato@fundacaoedmilson.org.br';
$host = $config['dominio'] ?? 'arvoredobem.fundacaoedmilson.org.br';

// Só aceita envios vindos da própria página
$origem = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origem !== '' && parse_url($origem, PHP_URL_HOST) !== $host && empty($config['aceitar_qualquer_origem'])) {
    responder(403, ['ok' => false, 'erro' => 'origem']);
}

$bruto = file_get_contents('php://input', false, null, 0, 20000);
$d = json_decode((string) $bruto, true);
if (!is_array($d)) {
    responder(400, ['ok' => false, 'erro' => 'formato']);
}

// Antispam: campo-isca e tempo de preenchimento
if (trim((string) ($d['site'] ?? '')) !== '' || (int) ($d['t'] ?? 0) < TEMPO_MINIMO_MS) {
    fingirSucesso();
}

function texto($v, int $max): string
{
    $v = trim(preg_replace('/\s+/u', ' ', (string) $v) ?? '');
    return mb_substr($v, 0, $max);
}
function temProibida(string $v): bool
{
    $v = mb_strtolower($v);
    foreach (PROIBIDAS as $p) {
        if (str_contains($v, $p)) return true;
    }
    return false;
}
function nomeValido(string $v): bool
{
    return mb_strlen($v) >= 2 && mb_strlen($v) <= 80 && preg_match('/\p{L}/u', $v) && !preg_match('/\d/', $v)
        && !preg_match(SUSPEITO, $v) && !temProibida($v);
}

$nome = texto($d['nome'] ?? '', 80);
$empresa = texto($d['empresa'] ?? '', 80);
$telefone = texto($d['telefone'] ?? '', 20);
$email = texto($d['email'] ?? '', 120);
$quantidade = filter_var($d['quantidade'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 300]]);
$digitos = strlen(preg_replace('/\D/', '', $telefone));

$erros = [];
if (!nomeValido($nome)) $erros[] = 'nome';
if ($empresa !== '' && (preg_match(SUSPEITO, $empresa) || temProibida($empresa))) $erros[] = 'empresa';
if ($digitos !== 10 && $digitos !== 11) $erros[] = 'telefone';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'email';
if ($quantidade === false) $erros[] = 'quantidade';

$criancas = [];
foreach (array_slice(is_array($d['criancas'] ?? null) ? $d['criancas'] : [], 0, MAX_CRIANCAS) as $c) {
    $codigo = texto($c['codigo'] ?? '', 20);
    $nomeCrianca = texto($c['nome'] ?? '', 60);
    if ($codigo === '' && $nomeCrianca === '') continue;
    if ($codigo !== '' && !preg_match('~^[\p{L}\d][\p{L}\d \-/]{0,19}$~u', $codigo)) $erros[] = 'codigo';
    if ($nomeCrianca !== '' && !nomeValido($nomeCrianca)) $erros[] = 'crianca';
    $criancas[] = ['codigo' => $codigo, 'nome' => $nomeCrianca];
}

// Telefone sempre no formato (11) 91234-5678
if (!in_array('telefone', $erros, true)) {
    $n = preg_replace('/\D/', '', $telefone);
    $telefone = '(' . substr($n, 0, 2) . ') ' . substr($n, 2, -4) . '-' . substr($n, -4);
}

if ($erros) {
    responder(422, ['ok' => false, 'erro' => 'campos', 'campos' => array_values(array_unique($erros))]);
}

if (!is_dir(PASTA) && !@mkdir(PASTA, 0750, true)) {
    registrarErro('não foi possível criar a pasta privado');
    responder(500, ['ok' => false, 'erro' => 'servidor']);
}

// Limite por IP (o IP é guardado só como hash)
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$arqLimite = PASTA . '/limites.json';
$fl = fopen($arqLimite, 'c+');
if ($fl && flock($fl, LOCK_EX)) {
    $limites = json_decode(stream_get_contents($fl) ?: '{}', true) ?: [];
    $agora = time();
    foreach ($limites as $k => $lista) {
        $limites[$k] = array_values(array_filter($lista, fn ($t) => $t > $agora - 3600));
        if (!$limites[$k]) unset($limites[$k]);
    }
    $chave = hash('sha256', $ip . ($config['sal'] ?? 'arvore'));
    if (count($limites[$chave] ?? []) >= ENVIOS_POR_HORA) {
        flock($fl, LOCK_UN);
        fclose($fl);
        responder(429, ['ok' => false, 'erro' => 'limite']);
    }
    $limites[$chave][] = $agora;
    ftruncate($fl, 0);
    rewind($fl);
    fwrite($fl, json_encode($limites));
    flock($fl, LOCK_UN);
    fclose($fl);
}

// Planilha: separador ";" e BOM para abrir certo no Excel em português
function celula(string $v): string
{
    // Evita que o Excel interprete o conteúdo como fórmula
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}
$descricaoCriancas = implode(' | ', array_map(
    fn ($c) => ($c['nome'] ?: 'Nome não informado') . ' · código ' . ($c['codigo'] ?: 'não informado'),
    $criancas
));
$linha = [
    date('d/m/Y H:i:s'),
    $nome,
    $empresa,
    $telefone,
    $email,
    (string) $quantidade,
    $descricaoCriancas,
];
$novo = !is_file(PLANILHA);
$fp = fopen(PLANILHA, 'a');
if (!$fp || !flock($fp, LOCK_EX)) {
    registrarErro('não foi possível abrir a planilha');
    responder(500, ['ok' => false, 'erro' => 'servidor']);
}
if ($novo) {
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, ['Data', 'Nome', 'Empresa', 'Telefone', 'E-mail', 'Quantidade de crianças', 'Crianças informadas'], ';', '"', '');
}
fputcsv($fp, array_map('celula', $linha), ';', '"', '');
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

// E-mail para a equipe
$h = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$linhasTabela = [
    'Nome' => $nome,
    'Empresa' => $empresa !== '' ? $empresa : 'Não veio por empresa',
    'Telefone' => $telefone,
    'E-mail' => $email,
    'Quantidade de crianças' => (string) $quantidade,
];
if (!$criancas) $linhasTabela['Crianças'] = 'Ainda não escolheu o cartão';
foreach ($criancas as $i => $c) {
    $linhasTabela['Criança ' . ($i + 1)] = ($c['nome'] ?: 'Nome não informado') . ' · código ' . ($c['codigo'] ?: 'não informado');
}
$whats = 'https://wa.me/55' . preg_replace('/\D/', '', $telefone);
$html = '<div style="font-family:Arial,sans-serif;color:#1F4C38;max-width:560px">'
    . '<h2 style="margin:0 0 4px">Novo padrinho na Árvore do Bem</h2>'
    . '<p style="margin:0 0 16px;color:#4A5A52">Cadastro feito em ' . $h(date('d/m/Y \à\s H:i')) . '</p>'
    . '<table cellpadding="10" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:15px">';
foreach ($linhasTabela as $k => $v) {
    $html .= '<tr><th align="left" style="background:#F2EADC;border:1px solid #EAE1CF;width:40%">' . $h($k) . '</th>'
        . '<td style="border:1px solid #EAE1CF">' . $h($v) . '</td></tr>';
}
$html .= '</table><p style="margin:20px 0 0"><a href="' . $h($whats) . '" style="background:#1F4C38;color:#FAF6EE;padding:12px 20px;border-radius:999px;text-decoration:none;font-weight:bold">Falar com o padrinho no WhatsApp</a></p>'
    . '<p style="margin:20px 0 0;font-size:13px;color:#5B6B62">Todos os cadastros também ficam salvos na planilha do servidor (privado/cadastros.csv).</p></div>';
$textoPuro = "Novo padrinho na Árvore do Bem\n\n";
foreach ($linhasTabela as $k => $v) $textoPuro .= "$k: $v\n";
$textoPuro .= "\nWhatsApp: $whats\n";

$assunto = 'Novo padrinho · Árvore do Bem · ' . $nome;
$emailOk = enviarEmail($config, $destino, $assunto, $html, $textoPuro, $email, $nome);

responder(200, ['ok' => true, 'email' => $emailOk]);

// ---------------------------------------------------------------

function cabecalho(string $v): string
{
    return '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], '', $v)) . '?=';
}

function montarMensagem(string $de, string $nomeDe, string $para, string $assunto, string $html, string $texto, string $responderPara, string $nomeResponder, string $dominio): string
{
    $limite = 'arvore-' . bin2hex(random_bytes(8));
    $cab = [
        'Date: ' . date('r'),
        'From: ' . cabecalho($nomeDe) . " <$de>",
        "To: <$para>",
        'Reply-To: ' . cabecalho($nomeResponder) . " <$responderPara>",
        'Subject: ' . cabecalho($assunto),
        'Message-ID: <' . bin2hex(random_bytes(12)) . "@$dominio>",
        'MIME-Version: 1.0',
        "Content-Type: multipart/alternative; boundary=\"$limite\"",
    ];
    $corpo = "--$limite\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($texto))
        . "--$limite\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html))
        . "--$limite--\r\n";
    return implode("\r\n", $cab) . "\r\n\r\n" . $corpo;
}

// Envio pelo e-mail interno da hospedagem (função mail() do PHP), sem senha
function enviarEmail(array $config, string $para, string $assunto, string $html, string $texto, string $responderPara, string $nomeResponder): bool
{
    $dominio = $config['dominio'] ?? 'arvoredobem.fundacaoedmilson.org.br';
    $de = $config['remetente'] ?? "no-reply@$dominio";
    $nomeDe = $config['remetente_nome'] ?? 'Árvore do Bem';

    $msg = montarMensagem($de, $nomeDe, $para, $assunto, $html, $texto, $responderPara, $nomeResponder, $dominio);
    [$cab, $corpo] = explode("\r\n\r\n", $msg, 2);
    // mail() acrescenta o destinatário e o assunto por conta própria
    $cab = preg_replace('/^(To|Subject): .*\r\n/m', '', $cab);
    if (@mail($para, cabecalho($assunto), $corpo, $cab, "-f$de")) return true;
    registrarErro('mail() recusou o envio');
    return false;
}
