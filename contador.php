<?php
/**
 * Recibe los avisos de visita y de clic que manda cada página (ver comun.php).
 * Responde 204 sin contenido. No usa cookies ni guarda la IP.
 */
declare(strict_types=1);
require __DIR__ . '/visitas.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
$ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
// Solo pedidos desde el propio sitio y de navegadores reales
$origenPedido = (string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
if (es_bot($ua) || !str_contains($origenPedido, 'clima.jaureguitosistemas.com') && !str_contains($origenPedido, '127.0.0.1')) {
    http_response_code(204);
    exit;
}

$d = json_decode((string) file_get_contents('php://input', false, null, 0, 2048), true);
if (!is_array($d)) {
    http_response_code(400);
    exit;
}
$pagina = (string) ($d['p'] ?? '');
if (!in_array($pagina, PAGINAS_VALIDAS, true)) {
    http_response_code(204);
    exit;
}

if (($d['t'] ?? '') === 'vista') {
    $ref = (string) ($d['r'] ?? '');
    registrar([
        'total' => 'vistas',
        'paginas' => $pagina,
        'dispositivo' => preg_match('/Mobi|Android|iPhone|iPad/i', $ua) ? 'Celular' : 'Computadora',
    ]);
    // "Entrada": la persona llegó desde afuera (no navegando entre páginas del clima)
    if (!str_contains($ref, 'clima.jaureguitosistemas.com')) {
        registrar(['total' => 'entradas', 'origenes' => origen($ref)]);
    }
} elseif (($d['t'] ?? '') === 'clic' && in_array($d['c'] ?? '', CLICS_VALIDOS, true)) {
    registrar(['clics' => (string) $d['c']]);
}
http_response_code(204);
