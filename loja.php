<?php
/**
 * ╔══════════════════════════════════════════╗
 * LOJA DO AVENTUREIRO — Servidor PHP
 * Arquivo: loja.php
 * ╚══════════════════════════════════════════╝
 *
 * ENDPOINTS:
 * GET loja.php?action=listar
 * → retorna todos os itens da loja
 *
 * GET loja.php?item_id=N&moedas=M
 * → tenta comprar item N com M moedas
 */

// ── Blindagem contra Erros ───────────────────────────────────────────────────
// Impede que o PHP injete avisos de texto (Warnings/Notices) no meio do JSON
error_reporting(0);
ini_set('display_errors', 0);

// ── Cabeçalhos ───────────────────────────────────────────────────────────────
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// ── Helpers ──────────────────────────────────────────────────────────────────
function responder(array $payload, int $status = 200): void {
    http_response_code($status);
    
    // Tenta codificar o array para JSON
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
    // Se o json_encode falhar (geralmente por caracteres corrompidos no itens.json)
    if ($json === false) {
        echo json_encode([
            "sucesso" => false,
            "erro"    => "Erro interno: O PHP nao conseguiu gerar o JSON. Verifique se o arquivo data/itens.json esta salvo em UTF-8 e sem erros de sintaxe.",
            "total"   => 0,
            "itens"   => []
        ]);
    } else {
        echo $json;
    }
    exit;
}

function carregarItens(): array {
    $path = __DIR__ . "/data/itens.json";
    if (!file_exists($path)) {
        responder(["sucesso" => false, "erro" => "Arquivo de itens nao encontrado.", "total" => 0, "itens" => []], 500);
    }
    
    $conteudo = file_get_contents($path);
    $data = json_decode($conteudo, true);
    
    if ($data === null) {
        responder(["sucesso" => false, "erro" => "Erro de sintaxe ao ler data/itens.json. Verifique virgulas ou aspas extras.", "total" => 0, "itens" => []], 500);
    }
    return $data;
}

function buscarItem(array $itens, int $id): ?array {
    foreach ($itens as $item) {
        if ((int)$item["id"] === $id) return $item;
    }
    return null;
}

// ── Roteamento ───────────────────────────────────────────────────────────────
$action  = $_GET["action"]  ?? null;
$itemId  = $_GET["item_id"] ?? null;
$moedas  = $_GET["moedas"]  ?? null;

$itens = carregarItens();

// ── Listar itens ─────────────────────────────────────────────────────────────
if ($action === "listar") {
    responder([
        "sucesso" => true,
        "erro"    => "",
        "total"   => count($itens),
        "itens"   => $itens
    ]);
}

// ── Comprar item ─────────────────────────────────────────────────────────────
if ($itemId !== null || $moedas !== null) {

    // Validações de entrada
    if ($itemId === null || !is_numeric($itemId) || (int)$itemId <= 0) {
        responder([
            "sucesso" => false,
            "erro"    => "Parametro 'item_id' ausente ou invalido."
        ], 400);
    }
    if ($moedas === null || !is_numeric($moedas) || (int)$moedas < 0) {
        responder([
            "sucesso" => false,
            "erro"    => "Parametro 'moedas' ausente ou invalido."
        ], 400);
    }

    $itemId = (int)$itemId;
    $moedas = (int)$moedas;

    // Busca o item
    $item = buscarItem($itens, $itemId);
    if ($item === null) {
        responder([
            "sucesso" => false,
            "erro"    => "Item #$itemId nao encontrado na loja."
        ], 404);
    }

    // Verifica saldo
    if ($moedas < $item["preco"]) {
        responder([
            "sucesso"         => false,
            "erro"            => "Moedas insuficientes para comprar \"{$item['nome']}\".",
            "moedas_jogador"  => $moedas,
            "preco_item"      => $item["preco"],
            "moedas_faltando" => $item["preco"] - $moedas
        ]);
    }

    // Compra aprovada
    responder([
        "sucesso"          => true,
        "mensagem"         => "Compra realizada com sucesso!",
        "erro"             => "",
        "item"             => $item,
        "moedas_gastas"    => $item["preco"],
        "moedas_restantes" => $moedas - $item["preco"]
    ]);
}

// ── Sem parâmetros reconhecidos ───────────────────────────────────────────────
responder([
    "sucesso" => false,
    "erro"    => "Requisicao invalida.",
    "exemplos" => [
        "listar"  => "GET loja.php?action=listar",
        "comprar" => "GET loja.php?item_id=1&moedas=100"
    ]
], 400);
