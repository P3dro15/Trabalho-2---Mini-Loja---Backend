<?php
/**
 * ╔══════════════════════════════════════════╗
 *   LOJA DO AVENTUREIRO — Servidor PHP
 *   Arquivo: loja.php
 * ╚══════════════════════════════════════════╝
 *
 * ENDPOINTS:
 *   GET loja.php?action=listar
 *       → retorna todos os itens da loja
 *
 *   GET loja.php?item_id=N&moedas=M
 *       → tenta comprar item N com M moedas
 */

// ── Cabeçalhos ───────────────────────────────────────────────────────────────
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// ── Helpers ──────────────────────────────────────────────────────────────────
function responder(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function carregarItens(): array {
    $path = __DIR__ . "/data/itens.json";
    if (!file_exists($path)) {
        responder(["sucesso" => false, "erro" => "Arquivo de itens não encontrado."], 500);
    }
    $data = json_decode(file_get_contents($path), true);
    if ($data === null) {
        responder(["sucesso" => false, "erro" => "Erro ao ler itens.json."], 500);
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
            "erro"    => "Parâmetro 'item_id' ausente ou inválido."
        ], 400);
    }
    if ($moedas === null || !is_numeric($moedas) || (int)$moedas < 0) {
        responder([
            "sucesso" => false,
            "erro"    => "Parâmetro 'moedas' ausente ou inválido."
        ], 400);
    }

    $itemId = (int)$itemId;
    $moedas = (int)$moedas;

    // Busca o item
    $item = buscarItem($itens, $itemId);
    if ($item === null) {
        responder([
            "sucesso" => false,
            "erro"    => "Item #$itemId não encontrado na loja."
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
        "item"             => $item,
        "moedas_gastas"    => $item["preco"],
        "moedas_restantes" => $moedas - $item["preco"]
    ]);
}

// ── Sem parâmetros reconhecidos ───────────────────────────────────────────────
responder([
    "sucesso" => false,
    "erro"    => "Requisição inválida.",
    "exemplos" => [
        "listar"  => "GET loja.php?action=listar",
        "comprar" => "GET loja.php?item_id=1&moedas=100"
    ]
], 400);
