<?php
/**
 * Piezas compartidas por la home (index.php) y las páginas de cada mes (mes.php):
 * <head> con SEO, publicidad, enlaces a los meses y pie con créditos.
 */
declare(strict_types=1);

const SITIO = 'https://clima.jaureguitosistemas.com';
const WA_URL = 'https://wa.me/5492644583811?text=Hola%2C%20vi%20el%20clima%20de%20San%20Juan%20y%20quiero%20una%20p%C3%A1gina%20web%20para%20mi%20negocio';
const WEB_URL = 'https://www.jaureguitosistemas.com/?utm_source=clima&utm_medium=banner';
const FOTO_ARCHIVO = 'https://commons.wikimedia.org/wiki/File:Precordillera_de_San_Juan_Sierra_del_Tontal.jpg';
const SLUGS_MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function t($v): string
{
    return $v === null ? '–' : (string) round((float) $v);
}

function version_archivo(string $f): string
{
    return '/' . $f . '?v=' . (is_file(__DIR__ . '/' . $f) ? filemtime(__DIR__ . '/' . $f) : '1');
}

/** <head> completo. $jsonld: lista de objetos schema.org. */
function cabeza(string $titulo, string $descripcion, string $ruta, array $jsonld = []): string
{
    $url = SITIO . $ruta;
    $ld = '';
    foreach ($jsonld as $obj) {
        $ld .= '<script type="application/ld+json">' . json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>' . "\n";
    }
    return '<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . e($titulo) . '</title>
<meta name="description" content="' . e($descripcion) . '">
<link rel="canonical" href="' . e($url) . '">
<meta property="og:type" content="website">
<meta property="og:locale" content="es_AR">
<meta property="og:site_name" content="Clima en San Juan">
<meta property="og:title" content="' . e($titulo) . '">
<meta property="og:description" content="' . e($descripcion) . '">
<meta property="og:url" content="' . e($url) . '">
<meta property="og:image" content="' . SITIO . '/img/san-juan-og.jpg">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#07142b">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="preload" as="image" href="/img/san-juan-960.webp" media="(max-width:700px)">
<link rel="preload" as="image" href="/img/san-juan.webp" media="(min-width:701px)">
<link rel="stylesheet" href="' . version_archivo('estilos.css') . '">
' . $ld . '</head>';
}

function publicidad(): string
{
    return '<aside class="ad">
  <span class="tag">Publicidad</span>
  <h2>¿Tu negocio todavía no tiene página web?</h2>
  <p>En Jaureguito Sistemas diseñamos sitios profesionales para negocios de San Juan: rápidos, adaptados al celular y listos para aparecer en Google.</p>
  <a class="btn wa" href="' . e(WA_URL) . '" rel="noopener">Escribinos por WhatsApp</a>
  <a class="btn ol" href="' . e(WEB_URL) . '">Ver jaureguitosistemas.com</a>
</aside>';
}

/** Enlaces internos a las 12 páginas de meses. */
function enlaces_meses(?int $actual = null): string
{
    $s = '';
    foreach (SLUGS_MESES as $i => $slug) {
        $s .= '<a href="/' . $slug . '"' . ($i === $actual ? ' class="activo" aria-current="page"' : '') . '>' . ucfirst($slug) . '</a>';
    }
    return '<nav class="meses-nav" aria-label="El clima de San Juan mes por mes">' . $s . '</nav>';
}

function pie(): string
{
    return '<footer>
  <p>Medición actual: <a href="https://www.smn.gob.ar/" rel="noopener">Servicio Meteorológico Nacional</a> (<a href="https://creativecommons.org/licenses/by/2.5/ar/" rel="noopener">CC BY 2.5 AR</a>).
  Pronóstico: datos de <a href="https://www.met.no/en" rel="noopener">MET Norway</a> (<a href="https://creativecommons.org/licenses/by/4.0/" rel="noopener">CC BY 4.0</a>), adaptados a la hora de San Juan.</p>
  <p>Estadísticas: normales 1991-2020 y récords 1967-2025 de la estación San Juan Aero, <a href="https://www.smn.gob.ar/estadisticas" rel="noopener">SMN</a> (<a href="https://creativecommons.org/licenses/by/2.5/ar/" rel="noopener">CC BY 2.5 AR</a>).</p>
  <p>Foto: Precordillera de San Juan, <a href="' . e(FOTO_ARCHIVO) . '" rel="noopener">WeHaKa / Wikimedia Commons</a> (<a href="https://creativecommons.org/licenses/by-sa/4.0/" rel="noopener">CC BY-SA 4.0</a>), con filtro de color.</p>
  <p class="aviso">La información de esta página es orientativa y puede no coincidir con las condiciones reales. Para decisiones importantes y ante alertas meteorológicas, consultá siempre el <a href="https://www.smn.gob.ar/" rel="noopener">sitio oficial del SMN</a>.</p>
  <p class="aviso">Sitio independiente de Jaureguito Sistemas, sin vinculación con el Servicio Meteorológico Nacional ni con MET Norway. Esta página no usa cookies ni recopila datos personales.</p>
  <p>© ' . date('Y') . ' <a href="' . e(WEB_URL) . '">Jaureguito Sistemas</a> · Tulum 50, Rivadavia, San Juan</p>
</footer>';
}

/** Texto en lenguaje simple sobre un mes, a partir de las normales del SMN (sirve para la página y para el JSON-LD). */
function resumen_mes(int $m): array
{
    $dia = NORMAL_MAX[$m];
    $noche = NORMAL_MIN[$m];
    $mm = NORMAL_LLUVIA[$m];
    $sensDia = $dia >= 32 ? 'mucho calor' : ($dia >= 27 ? 'calor' : ($dia >= 21 ? 'una temperatura agradable' : ($dia >= 15 ? 'fresco' : 'frío')));
    $sensNoche = $noche >= 18 ? 'noches cálidas' : ($noche >= 10 ? 'noches templadas' : ($noche >= 3 ? 'noches frías' : 'noches muy frías, con heladas frecuentes'));
    $lluvia = $mm < 3 ? 'Casi no llueve en todo el mes' : ($mm < 8 ? 'Llueve muy poco, algún día aislado' : ($mm < 15 ? 'Llueve poco, en general con tormentas de verano' : 'Es de los meses más lluviosos del año, aunque igual llueve poco'));
    if ($dia >= 30) {
        $ropa = 'Ropa liviana, gorra, protector solar y mucha agua.' . ($dia - $noche >= 15 ? ' A la noche refresca: un buzo liviano.' : '');
    } elseif ($dia >= 22) {
        $ropa = 'Remera durante el día y un abrigo liviano para la noche.';
    } elseif ($dia >= 15) {
        $ropa = 'Abrigo liviano de día y campera para la noche.';
    } else {
        $ropa = 'Campera abrigada, sobre todo a la mañana temprano y a la noche.';
    }
    $texto = 'En ' . SLUGS_MESES[$m] . ', en San Juan, de día hace ' . $sensDia . ' (la máxima promedio es de ' . dec($dia) . '°) y hay '
        . $sensNoche . ' (la mínima promedio es de ' . dec($noche) . '°). ' . $lluvia . ': en promedio caen ' . dec($mm) . ' mm en el mes.';
    return ['texto' => $texto, 'ropa' => $ropa];
}
