<?php
/**
 * Estadísticas de visitas propias, sin cookies para los visitantes.
 *
 * No se guarda la IP ni ningún identificador: solo contadores por día
 * (vistas, entradas, página, origen, celular/PC y clics en la publicidad).
 * Los datos van FUERA de public_html (no se ven desde la web y no se borran
 * cuando Hostinger vuelve a desplegar el repo). Si esa carpeta no se puede
 * crear, se usa cache/visitas (bloqueada por cache/.htaccess).
 */
declare(strict_types=1);

const PAGINAS_VALIDAS = ['/', '/enero', '/febrero', '/marzo', '/abril', '/mayo', '/junio', '/julio', '/agosto',
    '/septiembre', '/octubre', '/noviembre', '/diciembre'];
const CLICS_VALIDOS = ['whatsapp', 'sitio'];

function carpeta_visitas(): string
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }
    // public_html/clima -> domains/<dominio>/clima-privado
    $fuera = dirname(__DIR__, 2) . '/clima-privado';
    if ((is_dir($fuera) || @mkdir($fuera, 0750, true)) && is_writable($fuera)) {
        return $dir = $fuera;
    }
    $dentro = __DIR__ . '/cache/visitas';
    if (!is_dir($dentro)) {
        @mkdir($dentro, 0750, true);
    }
    return $dir = $dentro;
}

function es_bot(string $ua): bool
{
    return $ua === '' || (bool) preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|headless|lighthouse|curl|wget|python|monitor|uptime/i', $ua);
}

/** Origen legible a partir del referrer, sin guardar la URL completa. */
function origen(string $ref): string
{
    $host = strtolower((string) parse_url($ref, PHP_URL_HOST));
    if ($host === '' ) return 'Directo';
    if (str_ends_with($host, 'jaureguitosistemas.com')) return 'Jaureguito Sistemas';
    foreach (['google' => 'Google', 'bing' => 'Bing', 'duckduckgo' => 'DuckDuckGo', 'facebook' => 'Facebook', 'fb.' => 'Facebook',
              'instagram' => 'Instagram', 'whatsapp' => 'WhatsApp', 'wa.me' => 'WhatsApp', 't.co' => 'X (Twitter)', 'twitter' => 'X (Twitter)',
              'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'chatgpt' => 'ChatGPT', 'openai' => 'ChatGPT', 'perplexity' => 'Perplexity'] as $k => $v) {
        if (str_contains($host, $k)) return $v;
    }
    return 'Otro sitio';
}

/** Suma 1 a cada contador del día, con bloqueo de archivo para que no se pisen visitas simultáneas. */
function registrar(array $claves): void
{
    $archivo = carpeta_visitas() . '/' . (new DateTimeImmutable('now', new DateTimeZone('America/Argentina/San_Juan')))->format('Y-m-d') . '.json';
    $fh = @fopen($archivo, 'c+');
    if (!$fh) {
        return;
    }
    flock($fh, LOCK_EX);
    $datos = json_decode((string) stream_get_contents($fh), true) ?: [];
    foreach ($claves as $grupo => $clave) {
        $datos[$grupo][$clave] = ($datos[$grupo][$clave] ?? 0) + 1;
    }
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($datos, JSON_UNESCAPED_UNICODE));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
}

/** Lee los últimos $n días (incluido hoy). Devuelve [fecha => datos]. */
function leer_dias(int $n): array
{
    $tz = new DateTimeZone('America/Argentina/San_Juan');
    $hoy = new DateTimeImmutable('today', $tz);
    $res = [];
    for ($i = $n - 1; $i >= 0; $i--) {
        $f = $hoy->modify("-$i day")->format('Y-m-d');
        $archivo = carpeta_visitas() . "/$f.json";
        $res[$f] = is_file($archivo) ? (json_decode((string) file_get_contents($archivo), true) ?: []) : [];
    }
    return $res;
}
