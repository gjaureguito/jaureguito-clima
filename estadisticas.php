<?php
/**
 * Estadísticas climáticas de San Juan (estación SMN San Juan Aero, OMM 87311).
 *
 * Fuente: Servicio Meteorológico Nacional, "Estadísticas de largo plazo" (smn.gob.ar/estadisticas),
 * licencia CC BY 2.5 AR. Gráficos de origen: CRC-SAS para el SMN.
 *  - Normales: valores climatológicos medios 1991-2020.
 *  - Récords: temperaturas extremas diarias 1967-2025.
 * Son valores fijos (cambian cada 10 años con la nueva normal), por eso van escritos acá.
 */
declare(strict_types=1);

const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
const MESES_CORTOS = ['E', 'F', 'M', 'A', 'M', 'J', 'J', 'A', 'S', 'O', 'N', 'D'];

const NORMAL_MAX    = [35.1, 33.3, 30.5, 25.3, 20.5, 17.3, 16.7, 20.3, 23.8, 27.9, 31.6, 34.4];
const NORMAL_MIN    = [19.8, 18.4, 16.1, 10.9, 6.0, 1.5, 0.3, 2.7, 6.6, 11.3, 15.0, 18.0];
const NORMAL_LLUVIA = [17.4, 22.5, 10.8, 4.6, 3.4, 1.8, 1.3, 2.6, 3.7, 2.9, 7.2, 12.4];
const RECORD_MAX    = [45.4, 45.1, 42.1, 39.3, 38.1, 37.6, 36.6, 38.5, 41.8, 42.5, 44.5, 46.7];
const RECORD_MIN    = [7.8, 5.5, 2.5, -1.6, -4.1, -9.2, -9.0, -7.2, -5.1, -0.5, 2.1, 6.6];

function dec(float $v, int $d = 1): string
{
    return number_format($v, $d, ',', '.');
}

function diferencia(float $v): string
{
    $r = round($v);
    return ($r > 0 ? '+' : ($r < 0 ? '−' : '±')) . abs($r) . '°';
}

/** Gráfico anual en SVG: rango normal (mín–máx) por mes, récords y el pronóstico de hoy. */
function grafico_anual(int $mes, ?float $hoyMax, ?float $hoyMin): string
{
    $W = 720; $H = 300; $izq = 40; $der = 14; $arr = 16; $abj = 34;
    $tMin = -10; $tMax = 50;
    $ancho = ($W - $izq - $der) / 12;
    $y = fn(float $t) => $arr + ($tMax - $t) / ($tMax - $tMin) * ($H - $arr - $abj);

    $s = '<svg class="graf" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Temperaturas normales y récords de San Juan por mes">';
    $s .= '<defs><linearGradient id="gNormal" x1="0" y1="1" x2="0" y2="0"><stop offset="0" stop-color="#4fc3f7"/><stop offset=".55" stop-color="#ffd54f"/><stop offset="1" stop-color="#ff8a65"/></linearGradient></defs>';
    for ($t = -10; $t <= 50; $t += 10) {
        $yy = round($y($t), 1);
        $s .= '<line x1="' . $izq . '" x2="' . ($W - $der) . '" y1="' . $yy . '" y2="' . $yy . '" class="graf-grid"/>';
        $s .= '<text x="' . ($izq - 8) . '" y="' . ($yy + 4) . '" class="graf-eje" text-anchor="end">' . $t . '°</text>';
    }
    for ($m = 0; $m < 12; $m++) {
        $cx = $izq + $ancho * $m + $ancho / 2;
        $actual = $m === $mes;
        if ($actual) {
            $s .= '<rect x="' . round($cx - $ancho / 2 + 3, 1) . '" y="' . $arr . '" width="' . round($ancho - 6, 1) . '" height="' . ($H - $arr - $abj) . '" rx="10" class="graf-actual" data-actual="1"/>';
        }
        // récords: línea fina de la mínima a la máxima absoluta
        $s .= '<line x1="' . $cx . '" x2="' . $cx . '" y1="' . round($y(RECORD_MAX[$m]), 1) . '" y2="' . round($y(RECORD_MIN[$m]), 1) . '" class="graf-record"/>';
        $s .= '<circle cx="' . $cx . '" cy="' . round($y(RECORD_MAX[$m]), 1) . '" r="2.5" class="graf-record-max"><title>Récord máximo de ' . MESES[$m] . ': ' . dec(RECORD_MAX[$m]) . '°</title></circle>';
        $s .= '<circle cx="' . $cx . '" cy="' . round($y(RECORD_MIN[$m]), 1) . '" r="2.5" class="graf-record-min"><title>Récord mínimo de ' . MESES[$m] . ': ' . dec(RECORD_MIN[$m]) . '°</title></circle>';
        // rango normal
        $y1 = $y(NORMAL_MAX[$m]); $y2 = $y(NORMAL_MIN[$m]);
        $s .= '<rect x="' . round($cx - 9, 1) . '" y="' . round($y1, 1) . '" width="18" height="' . round($y2 - $y1, 1) . '" rx="9" fill="url(#gNormal)" opacity="' . ($actual ? '1' : '.8') . '"><title>' . ucfirst(MESES[$m]) . ': máxima normal ' . dec(NORMAL_MAX[$m]) . '°, mínima normal ' . dec(NORMAL_MIN[$m]) . '°</title></rect>';
        $s .= '<text x="' . $cx . '" y="' . ($H - 12) . '" class="graf-mes' . ($actual ? ' es-actual' : '') . '" text-anchor="middle">' . MESES_CORTOS[$m] . '</text>';
        $s .= '<rect x="' . round($cx - $ancho / 2 + 2, 1) . '" y="' . ($arr - 6) . '" width="' . round($ancho - 4, 1) . '" height="' . ($H - $arr + 4) . '" rx="10" class="graf-col" data-mes-col="' . $m . '"><title>Ver ' . MESES[$m] . '</title></rect>';
        if ($actual) {
            foreach ([[$hoyMax, 'Máxima'], [$hoyMin, 'Mínima de esta noche']] as [$v, $nom]) {
                if ($v !== null) {
                    $s .= '<circle cx="' . $cx . '" cy="' . round($y($v), 1) . '" r="6" class="graf-hoy"><title>' . $nom . ' pronosticada hoy: ' . round($v) . '°</title></circle>';
                }
            }
        }
    }
    return $s . '</svg>';
}

/** Datos que usan los gráficos interactivos (estadisticas.js). */
function datos_graficos(?array $met, int $mes, ?float $hoyMax): string
{
    $tz = new DateTimeZone(CLIMA_TZ);
    $nombres = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $dias = [];
    foreach ($met['dias'] ?? [] as $i => $d) {
        if ($d['max'] === null || $d['min'] === null) {
            continue;
        }
        $f = new DateTimeImmutable($d['fecha'], $tz);
        $nom = $i === 0 ? 'Hoy' : ucfirst($nombres[(int) $f->format('w')]);
        $dias[] = [
            'nombre' => $nom,
            'corto'  => $i === 0 ? 'Hoy' : mb_substr($nom, 0, 3),
            'mes'    => (int) $f->format('n') - 1,
            'max'    => round((float) $d['max'], 1),
            'min'    => round((float) ($i === 0 && isset($met['actual']['min_noche']) ? $met['actual']['min_noche'] : $d['min']), 1),
            'lluvia' => (float) $d['lluvia'],
        ];
    }
    $datos = [
        'mes' => $mes,
        'hoy' => ['max' => $hoyMax],
        'dias' => $dias,
        'normalMax' => NORMAL_MAX, 'normalMin' => NORMAL_MIN, 'normalLluvia' => NORMAL_LLUVIA,
        'recordMax' => RECORD_MAX, 'recordMin' => RECORD_MIN,
    ];
    return '<script id="clima-datos" type="application/json">'
        . json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
}

function seccion_estadisticas(?array $met): string
{
    $tz = new DateTimeZone(CLIMA_TZ);
    $mes = (int) (new DateTimeImmutable('now', $tz))->format('n') - 1;
    $hoy = $met['dias'][0] ?? null;
    $hoyMax = isset($hoy['max']) ? (float) $hoy['max'] : null;
    $hoyMin = isset($met['actual']['min_noche']) ? (float) $met['actual']['min_noche']
        : (isset($hoy['min']) ? (float) $hoy['min'] : null);
    $nombreMes = MESES[$mes];
    $lluviaAnual = array_sum(NORMAL_LLUVIA);

    $tarjetas = '';
    if ($hoyMax !== null) {
        $d = $hoyMax - NORMAL_MAX[$mes];
        $clase = $d >= 1 ? 'calido' : ($d <= -1 ? 'frio' : '');
        $tarjetas .= '<div class="stat ' . $clase . '"><small>Máxima de hoy</small><b>' . diferencia($d) . '</b>'
            . '<span>' . round($hoyMax) . '° contra ' . dec(NORMAL_MAX[$mes]) . '° normal en ' . $nombreMes . '</span></div>';
    }
    if ($hoyMin !== null) {
        $d = $hoyMin - NORMAL_MIN[$mes];
        $clase = $d >= 1 ? 'calido' : ($d <= -1 ? 'frio' : '');
        $tarjetas .= '<div class="stat ' . $clase . '"><small>Mínima de esta noche</small><b>' . diferencia($d) . '</b>'
            . '<span>' . round($hoyMin) . '° contra ' . dec(NORMAL_MIN[$mes]) . '° normal en ' . $nombreMes . '</span></div>';
    }
    $falta = $hoyMax !== null ? RECORD_MAX[$mes] - $hoyMax : null;
    $tarjetas .= '<div class="stat"><small>Récord de ' . $nombreMes . '</small><b>' . dec(RECORD_MAX[$mes]) . '°</b>'
        . '<span>' . ($falta !== null && $falta <= 8
            ? ($falta <= 0 ? '¡Hoy se podría superar el récord!' : 'Hoy estamos a ' . dec($falta) . '° del récord')
            : 'Mínima récord: ' . dec(RECORD_MIN[$mes]) . '°') . '</span></div>';
    $tarjetas .= '<div class="stat"><small>Lluvia normal en ' . $nombreMes . '</small><b>' . dec(NORMAL_LLUVIA[$mes]) . ' mm</b>'
        . '<span>En todo el año llueven ' . dec($lluviaAnual, 0) . ' mm en promedio</span></div>';

    // Lluvia: barras por mes
    $maxLl = max(NORMAL_LLUVIA);
    $lluvia = '';
    foreach (NORMAL_LLUVIA as $m => $mm) {
        $h = max(4, round($mm / $maxLl * 100));
        $lluvia .= '<div data-mes-col="' . $m . '" class="ll' . ($m === $mes ? ' es-actual' : '') . '" title="' . ucfirst(MESES[$m]) . ': ' . dec($mm) . ' mm">'
            . '<i style="height:' . $h . '%"></i><small>' . dec($mm, 0) . '</small><span>' . MESES_CORTOS[$m] . '</span></div>';
    }

    return '<section class="card">'
        . '<div class="lbl">El clima de San Juan en números</div>'
        . '<div class="stats-grid">' . $tarjetas . '</div>'
        // 1. Termómetro de hoy
        . ($hoyMax !== null ? '<div class="widget" id="w-hoy"><h2 class="sub">¿Qué tan caluroso está hoy?</h2>'
            . '<div class="termo"><div class="termo-barra"><span class="termo-aguja"><b class="termo-valor">' . round($hoyMax) . '°</b></span></div><div class="termo-marcas"></div></div>'
            . '<p class="frase termo-frase"></p></div>' : '')
        // 2. La semana
        . '<div class="widget" id="w-semana"><h2 class="sub">Los próximos días, comparados con lo normal</h2>'
        . '<p class="frase sem-resumen"></p><div class="sem-graf graf-wrap-sem"></div>'
        . '<div class="leyenda"><span><i class="lg-max"></i>Máxima (de día)</span><span><i class="lg-min"></i>Mínima (de noche)</span><span><i class="lg-banda"></i>Lo normal para la época</span></div>'
        . '<p class="frase sem-frase">Tocá un día para ver el detalle.</p></div>'
        // 3. Explorador de meses
        . '<div class="widget" id="w-mes"><h2 class="sub">¿Cómo es cada mes en San Juan?</h2>'
        . '<p class="ayuda">Elegí un mes, o tocá una columna del gráfico.</p>'
        . '<div class="mes-chips" role="group" aria-label="Elegir mes"></div>'
        . '<div class="mes-ficha" aria-live="polite"><h3 class="f-titulo"></h3>'
        . '<div class="f-grid"><div><small>☀️ De día</small><b class="f-dia"></b></div><div><small>🌙 De noche</small><b class="f-noche"></b></div>'
        . '<div><small>🌧️ Lluvia en el mes</small><b class="f-lluvia"></b><span class="f-gotas"></span></div>'
        . '<div><small>🔥 Récord de calor</small><b class="f-rec-max"></b></div><div><small>❄️ Récord de frío</small><b class="f-rec-min"></b></div></div>'
        . '<p class="f-texto"></p><p class="f-ropa-wrap">👕 <span class="f-ropa"></span></p></div></div>'
        . '<h2 class="sub">Temperaturas de cada mes</h2>'
        . '<div class="graf-wrap" data-mes="' . $mes . '">' . grafico_anual($mes, $hoyMax, $hoyMin) . '</div>'
        // En celular el gráfico se desplaza: centrarlo en el mes actual
        . '<script>document.querySelectorAll(".graf-wrap").forEach(function(w){var m=+w.dataset.mes;var r=w.querySelector("[data-actual]");if(r){w.scrollLeft=r.getBoundingClientRect().left-w.getBoundingClientRect().left+w.scrollLeft-w.clientWidth/2+r.getBoundingClientRect().width/2;}});</script>'
        . '<div class="leyenda"><span><i class="lg-normal"></i>Rango normal (mín.–máx.)</span><span><i class="lg-record"></i>Récords desde 1967</span><span><i class="lg-hoy"></i>Pronóstico de hoy</span></div>'
        . '<h2 class="sub">Lluvia promedio por mes (mm)</h2>'
        . '<div class="lluvia">' . $lluvia . '</div>'
        . datos_graficos($met, $mes, $hoyMax)
        . '<script src="estadisticas.js?v=' . filemtime(__DIR__ . '/estadisticas.js') . '" defer></script>'
        . '<p class="fuente">Estación San Juan Aero. Normales 1991-2020 y récords 1967-2025: Servicio Meteorológico Nacional.</p>'
        . '</section>';
}

/* ---------- El último año en San Juan (análisis diario en Python, datos/estadisticas.json) ---------- */

function seccion_ultimo_anio(): string
{
    $archivo = __DIR__ . '/datos/estadisticas.json';
    if (!is_file($archivo)) {
        return '';
    }
    $A = json_decode((string) file_get_contents($archivo), true);
    if (!is_array($A) || empty($A['ultimo_dia'])) {
        return '';
    }
    $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $u = $A['ultimo_dia'];
    $c = $u['comparacion'];

    // Frase principal sobre el último día medido
    $frase = 'El ' . $h($u['fecha']) . ' la máxima fue de <b>' . dec((float) $u['tmax']) . '°</b>';
    $an = (float) $u['anom_max'];
    if (abs($an) >= 1) {
        $frase .= ', ' . abs((int) round($an)) . '° ' . ($an > 0 ? 'más' : 'menos') . ' que lo normal';
    }
    if ($c['tipo'] === 'maximo') {
        $frase .= '. Fue <b>el día más caluroso</b> de los ' . (int) $c['dias'] . ' días anteriores registrados.';
    } elseif ((int) $c['dias'] >= 14) {
        $frase .= '. Fue <b>el día más caluroso en ' . (int) $c['dias'] . ' días</b>: no hacía tanto calor desde el '
            . $h($c['fecha']) . ' (' . dec((float) $c['valor']) . '°).';
    } else {
        $frase .= '.';
    }

    // Mes por mes: barras hacia la derecha (más cálido) o la izquierda (más fresco)
    $meses = $A['meses'] ?? [];
    $tope = max(2.0, ...array_map(fn($m) => abs((float) $m['anom']), $meses ?: [['anom' => 2]]));
    $barras = '';
    $calidos = 0;
    foreach ($meses as $m) {
        $an = (float) $m['anom'];
        $calidos += $an > 0 ? 1 : 0;
        $ancho = round(abs($an) / $tope * 50, 1);
        $barras .= '<div class="anom-fila"><span class="anom-mes"><span class="mes-largo">' . $h(ucfirst($m['mes'])) . '</span><span class="mes-corto">' . $h(ucfirst(mb_substr($m['mes'], 0, 3))) . '</span> <small>' . substr((string) $m['anio'], 2) . '</small></span>'
            . '<span class="anom-pista"><i class="' . ($an >= 0 ? 'mas' : 'menos') . '" style="--w:' . $ancho . '%"></i></span>'
            . '<span class="anom-val ' . ($an >= 0 ? 'mas' : 'menos') . '">' . ($an > 0 ? '+' : ($an < 0 ? '−' : '')) . dec(abs($an)) . '°</span></div>';
    }
    $resMeses = $meses ? ($calidos . ' de los últimos ' . count($meses) . ' meses fueron más calurosos de lo normal.') : '';

    $k = $A['conteos'];
    $e = $A['extremos'];
    $tarj = fn($ico, $num, $txt) => '<div class="cuenta"><span class="cuenta-ico">' . $ico . '</span><b>' . $num . '</b><span>' . $txt . '</span></div>';
    $cuentas = $tarj('🔥', (int) $k['calor35'], 'días con 35° o más')
        . $tarj('🥵', (int) $k['calor40'], 'días con 40° o más')
        . $tarj('🌙', (int) $k['noches20'], 'noches que no bajaron de 20°')
        . $tarj('❄️', (int) $k['heladas'], 'días con helada (0° o menos)')
        . $tarj('📆', (int) $k['racha35'], 'días seguidos de 35° o más, la racha más larga');

    $u30 = $A['ultimos30'];
    $a30 = (float) $u30['anom_max'];
    $res30 = abs($a30) < 1 ? 'En los últimos 30 días las máximas fueron normales para la época.'
        : 'En los últimos 30 días las máximas estuvieron, en promedio, <b>' . dec(abs($a30)) . '° ' . ($a30 > 0 ? 'por encima' : 'por debajo') . '</b> de lo normal.';

    $datos30 = json_encode($u30['dias'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

    return '<section class="card" id="ultimo-anio">'
        . '<div class="lbl">El último año en San Juan · datos medidos</div>'
        . '<p class="destacado">' . $frase . '</p>'
        . '<div class="widget" id="w-30"><h2 class="sub">Los últimos 30 días, día por día</h2><p class="frase">' . $res30 . '</p>'
        . '<div class="graf-wrap-sem w30-graf"></div>'
        . '<div class="leyenda"><span><i class="lg-dia"></i>Mínima a máxima de cada día</span><span><i class="lg-banda"></i>Lo normal para la época</span></div>'
        . '<p class="frase w30-frase">Tocá un día para ver el detalle.</p></div>'
        . '<script id="clima-30" type="application/json">' . $datos30 . '</script>'
        . ($meses ? '<div class="widget"><h2 class="sub">¿Cada mes fue más caluroso o más fresco de lo normal?</h2><p class="frase">' . $resMeses . '</p>'
            . '<div class="anom-ejes"><span>← Más fresco</span><span>Más caluroso →</span></div><div class="anom">' . $barras . '</div>'
            . '<p class="ayuda">Promedio de las máximas de cada mes comparado con el normal 1991-2020.</p></div>' : '')
        . '<div class="widget"><h2 class="sub">En los últimos 12 meses hubo…</h2><div class="cuentas">' . $cuentas . '</div>'
        . '<p class="frase">El día más caluroso fue el ' . $h($e['max_fecha']) . ' con <b>' . dec((float) $e['max']) . '°</b> y el más frío, el '
        . $h($e['min_fecha']) . ' con <b>' . dec((float) $e['min']) . '°</b>.</p></div>'
        . '<p class="fuente">Del ' . $h($A['desde']) . ' al ' . $h($A['hasta']) . '. Datos medidos en la estación San Juan Aero: SMN, Registro de temperatura (365 días), CC BY 2.5 AR. Se actualiza todos los días.'
        . ((int) $k['dias_con_dato'] < 360 ? ' El SMN tiene dato de ' . (int) $k['dias_con_dato'] . ' de esos 365 días; los conteos usan solo los días con dato.' : '')
        . '</p>'
        . '</section>';
}
