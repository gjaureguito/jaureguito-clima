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
        . semana_tarjetas($met)
        // 3. Explorador de meses
        . '<div class="widget" id="w-mes"><h2 class="sub">¿Cómo es cada mes en San Juan?</h2>'
        . '<p class="ayuda">Elegí un mes para ver cómo es.</p>'
        . '<div class="mes-chips" role="group" aria-label="Elegir mes"></div>'
        . '<div class="mes-ficha" aria-live="polite"><h3 class="f-titulo"></h3>'
        . '<div class="f-grid"><div><small>☀️ De día</small><b class="f-dia"></b></div><div><small>🌙 De noche</small><b class="f-noche"></b></div>'
        . '<div><small>🌧️ Lluvia en el mes</small><b class="f-lluvia"></b><span class="f-gotas"></span></div>'
        . '<div><small>🔥 Récord de calor</small><b class="f-rec-max"></b></div><div><small>❄️ Récord de frío</small><b class="f-rec-min"></b></div></div>'
        . '<p class="f-texto"></p><p class="f-ropa-wrap">👕 <span class="f-ropa"></span></p></div></div>'
        . '<h2 class="sub">¿Cuánto llueve cada mes? (milímetros)</h2>'
        . '<div class="lluvia">' . $lluvia . '</div>'
        . datos_graficos($met, $mes, $hoyMax)
        . '<script src="' . version_archivo('estadisticas.js') . '" defer></script>'
        . '<p class="fuente">Estación San Juan Aero. Normales 1991-2020 y récords 1967-2025: Servicio Meteorológico Nacional.</p>'
        . '</section>';
}

/* ---------- La semana en tarjetas (sin ejes ni líneas) ---------- */

function etiqueta_clima(float $dif): array
{
    if ($dif >= 3) return ['caluroso', '🔥 Más caluroso que lo normal'];
    if ($dif <= -3) return ['fresco', '🧊 Más fresco que lo normal'];
    return ['normal', '🙂 Normal para la época'];
}

function semana_tarjetas(?array $met): string
{
    $dias = $met['dias'] ?? [];
    if (!$dias) {
        return '';
    }
    $tz = new DateTimeZone(CLIMA_TZ);
    $nombres = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    $html = '';
    $cuenta = ['caluroso' => 0, 'normal' => 0, 'fresco' => 0];
    foreach ($dias as $i => $d) {
        if ($d['max'] === null) continue;
        $f = new DateTimeImmutable($d['fecha'], $tz);
        $m = (int) $f->format('n') - 1;
        [$cl, $txt] = etiqueta_clima((float) $d['max'] - NORMAL_MAX[$m]);
        $cuenta[$cl]++;
        [$ico] = simbolo($d['simbolo']);
        $html .= '<div class="dt ' . $cl . '"><span class="dt-dia">' . ($i === 0 ? 'Hoy' : $nombres[(int) $f->format('w')]) . '</span>'
            . '<span class="dt-ico">' . $ico . '</span><b>' . round((float) $d['max']) . '°</b>'
            . '<span class="dt-et">' . explode(' ', $txt, 2)[0] . '</span></div>';
    }
    $res = $cuenta['caluroso'] > $cuenta['fresco'] && $cuenta['caluroso'] >= 2
        ? 'Se vienen ' . $cuenta['caluroso'] . ' días más calurosos de lo normal.'
        : ($cuenta['fresco'] > $cuenta['caluroso'] && $cuenta['fresco'] >= 2 ? 'Se vienen ' . $cuenta['fresco'] . ' días más frescos de lo normal.'
        : 'Se viene una semana con temperaturas normales para la época.');
    return '<div class="widget"><h2 class="sub">La semana que viene: ¿calor, normal o fresco?</h2><p class="frase">' . $res . '</p>'
        . '<div class="dts">' . $html . '</div>'
        . '<div class="claves"><span>🔥 Más caluroso que lo normal</span><span>🙂 Normal</span><span>🧊 Más fresco que lo normal</span></div></div>';
}

/* ---------- El último año en San Juan (análisis diario en Python, datos/estadisticas.json) ---------- */

/** Máxima y mínima medidas en una fecha (Y-m-d) según el histórico propio. */
function historico_dia(string $fecha): ?array
{
    $archivo = __DIR__ . '/datos/historico_san_juan.csv';
    if (!is_file($archivo) || !($fh = fopen($archivo, 'r'))) {
        return null;
    }
    $res = null;
    while (($c = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        if ($c[0] === $fecha && $c[1] !== '') {
            $res = ['tmax' => (float) $c[1], 'tmin' => $c[2] === '' ? null : (float) $c[2]];
            break;
        }
    }
    fclose($fh);
    return $res;
}

function plural(int $n, string $uno, string $varios): string
{
    return $n . ' ' . ($n === 1 ? $uno : $varios);
}

function barra_comparada(string $etiqueta, float $valor, float $tope, string $clase): string
{
    $ancho = max(6, min(100, round($valor / $tope * 100)));
    return '<div class="bc"><span class="bc-et">' . $etiqueta . '</span><span class="bc-pista"><i class="' . $clase . '" style="--w:' . $ancho . '%"></i></span><b>' . dec($valor) . '°</b></div>';
}

function seccion_ultimo_anio(?array $met): string
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
    $tz = new DateTimeZone(CLIMA_TZ);
    $hoy = new DateTimeImmutable('now', $tz);
    $bloques = '';

    // 1. Hoy contra el mismo día del año pasado
    $haceUnAnio = $hoy->modify('-1 year');
    $pasado = historico_dia($haceUnAnio->format('Y-m-d'));
    $hoyMax = $met['dias'][0]['max'] ?? null;
    if ($pasado && $hoyMax !== null) {
        $dif = (float) $hoyMax - $pasado['tmax'];
        $fechaPasada = (int) $haceUnAnio->format('j') . ' de ' . MESES[(int) $haceUnAnio->format('n') - 1] . ' de ' . $haceUnAnio->format('Y');
        $frase = abs($dif) < 1 ? 'Hoy se espera prácticamente la misma temperatura que hace un año.'
            : 'Hoy se esperan <b class="' . ($dif > 0 ? 't-calor' : 't-frio') . '">' . abs((int) round($dif)) . '° ' . ($dif > 0 ? 'más' : 'menos') . '</b> que el mismo día del año pasado.';
        $tope = max((float) $hoyMax, $pasado['tmax']) + 5;
        $bloques .= '<div class="widget"><h2 class="sub">Hoy contra el mismo día del año pasado</h2><p class="frase">' . $frase . '</p>'
            . barra_comparada('Hace un año<small>' . $h($fechaPasada) . ' · medido</small>', $pasado['tmax'], $tope, 'gris')
            . barra_comparada('Hoy<small>pronóstico</small>', (float) $hoyMax, $tope, $dif >= 0 ? 'calor' : 'frio')
            . '</div>';
    }

    // 2. Este mes contra el mismo mes del año pasado
    $ia = $A['interanual'] ?? null;
    if ($ia) {
        $titulo = ucfirst($ia['mes']) . ' ' . (int) $ia['anio'] . ' contra ' . $ia['mes'] . ' ' . (int) $ia['anio_pasado'];
        if (isset($ia['este'])) {
            $e = $ia['este']; $p = $ia['pasado'];
            $dif = (float) $e['prom_max'] - (float) $p['prom_max'];
            $frase = 'Comparando los mismos días (del ' . (int) $ia['desde_dia'] . ' al ' . (int) $ia['hasta_dia'] . '), este ' . $h($ia['mes']) . ' viene '
                . (abs($dif) < 1 ? '<b>igual</b> que el del año pasado.' : '<b class="' . ($dif > 0 ? 't-calor' : 't-frio') . '">' . dec(abs($dif)) . '° ' . ($dif > 0 ? 'más caluroso' : 'más fresco') . '</b> que el del año pasado.');
            $tope = max((float) $e['prom_max'], (float) $p['prom_max']) + 5;
            $bloques .= '<div class="widget"><h2 class="sub">' . $h($titulo) . '</h2><p class="frase">' . $frase . '</p>'
                . '<p class="ayuda">Temperatura máxima promedio</p>'
                . barra_comparada(ucfirst($h($ia['mes'])) . ' ' . (int) $ia['anio_pasado'], (float) $p['prom_max'], $tope, 'gris')
                . barra_comparada(ucfirst($h($ia['mes'])) . ' ' . (int) $ia['anio'], (float) $e['prom_max'], $tope, $dif >= 0 ? 'calor' : 'frio')
                . '<div class="vs"><div><small>Días con 35° o más</small><b>' . (int) $p['calor35'] . ' → ' . (int) $e['calor35'] . '</b></div>'
                . '<div><small>Día más caluroso</small><b>' . dec((float) $p['max']) . '° → ' . dec((float) $e['max']) . '°</b></div></div></div>';
        } elseif (!empty($ia['listo_el'])) {
            $bloques .= '<div class="widget"><h2 class="sub">' . $h($titulo) . '</h2><p class="frase">Esta comparación se arma sola con los datos que se van juntando. '
                . 'A partir del <b>' . $h($ia['listo_el']) . '</b> vas a ver si este ' . $h($ia['mes']) . ' viene más caluroso o más fresco que el del año pasado.</p></div>';
        }
    }

    // 3. Los últimos 30 días en un calendario de colores
    $u30 = $A['ultimos30'];
    $k30 = $u30['cuenta'];
    $celdas = '';
    foreach ($u30['dias'] as $d) {
        $celdas .= '<div class="cal ' . $h($d['clase']) . '" title="' . $h($d['f']) . ': máxima ' . ($d['max'] === null ? 'sin dato' : dec((float) $d['max']) . '°') . '">'
            . '<small>' . (int) $d['dia'] . '</small><b>' . ($d['max'] === null ? '–' : round((float) $d['max']) . '°') . '</b></div>';
    }
    $bloques .= '<div class="widget"><h2 class="sub">El último mes, día por día</h2>'
        . '<p class="frase">De los últimos 30 días, <b class="t-calor">' . plural((int) $k30['caluroso'], 'fue más caluroso', 'fueron más calurosos') . '</b> de lo normal, '
        . '<b class="t-normal">' . plural((int) $k30['normal'], 'normal', 'normales') . '</b> y <b class="t-frio">' . plural((int) $k30['fresco'], 'más fresco', 'más frescos') . '</b>.</p>'
        . '<div class="cals">' . $celdas . '</div>'
        . '<div class="claves"><span class="k-calor">Más caluroso</span><span class="k-normal">Normal</span><span class="k-frio">Más fresco</span></div>'
        . '<p class="ayuda">Cada cuadrado es un día con su temperatura máxima.</p></div>';

    // 4. El año mes por mes
    $meses = $A['meses'] ?? [];
    if ($meses) {
        $tiles = '';
        $n = ['caluroso' => 0, 'normal' => 0, 'fresco' => 0];
        foreach ($meses as $m) {
            $n[$m['clase']]++;
            $ico = ['caluroso' => '🔥', 'normal' => '🙂', 'fresco' => '🧊'][$m['clase']];
            $txt = ['caluroso' => 'Más caluroso', 'normal' => 'Normal', 'fresco' => 'Más fresco'][$m['clase']];
            $tiles .= '<div class="mt ' . $h($m['clase']) . '"><span class="mt-mes">' . $h(ucfirst($m['mes'])) . ' ' . substr((string) $m['anio'], 2) . '</span>'
                . '<span class="mt-ico">' . $ico . '</span><b>' . $txt . '</b>'
                . '<small>' . ((int) $m['calor35'] > 0 ? plural((int) $m['calor35'], 'día de 35°+', 'días de 35°+') : ((int) $m['heladas'] > 0 ? plural((int) $m['heladas'], 'helada', 'heladas') : '&nbsp;')) . '</small></div>';
        }
        $bloques .= '<div class="widget"><h2 class="sub">El año, mes por mes</h2>'
            . '<p class="frase">' . plural($n['caluroso'], 'mes fue más caluroso', 'meses fueron más calurosos') . ' de lo normal, '
            . plural($n['normal'], 'normal', 'normales') . ' y ' . plural($n['fresco'], 'más fresco', 'más frescos') . '.</p>'
            . '<div class="mts">' . $tiles . '</div></div>';
    }

    // 5. Números del año y datos curiosos
    $k = $A['conteos'];
    $e = $A['extremos'];
    $tarj = fn($ico, $num, $txt) => '<div class="cuenta"><span class="cuenta-ico">' . $ico . '</span><b>' . $num . '</b><span>' . $txt . '</span></div>';
    $bloques .= '<div class="widget"><h2 class="sub">En los últimos 12 meses hubo…</h2><div class="cuentas">'
        . $tarj('🔥', (int) $k['calor35'], 'días con 35° o más')
        . $tarj('🥵', (int) $k['calor40'], 'días con 40° o más')
        . $tarj('🌙', (int) $k['noches20'], 'noches que no bajaron de 20°')
        . $tarj('❄️', (int) $k['heladas'], 'días con helada')
        . '</div><p class="frase">El día más caluroso fue el ' . $h($e['max_fecha']) . ' con <b>' . dec((float) $e['max']) . '°</b> y la mañana más fría, el '
        . $h($e['min_fecha']) . ' con <b>' . dec((float) $e['min']) . '°</b>.</p></div>';
    if (!empty($A['curiosos'])) {
        $bloques .= '<div class="widget"><h2 class="sub">💡 ¿Sabías que…?</h2><ul class="curiosos">'
            . implode('', array_map(fn($x) => '<li>' . $h($x) . '</li>', $A['curiosos'])) . '</ul></div>';
    }

    return '<section class="card" id="ultimo-anio">'
        . '<div class="lbl">El último año en San Juan · datos medidos</div>'
        . $bloques
        . '<p class="fuente">Del ' . $h($A['desde']) . ' al ' . $h($A['hasta']) . '. "Normal" es el promedio 1991-2020 para esa época del año (±3° en un día, ±1° en un mes). '
        . 'Datos medidos en la estación San Juan Aero: SMN, Registro de temperatura (365 días), CC BY 2.5 AR. Se actualiza todos los días.'
        . ((int) $k['dias_con_dato'] < 360 ? ' El SMN tiene dato de ' . (int) $k['dias_con_dato'] . ' de esos 365 días; los conteos usan solo los días con dato.' : '')
        . '</p></section>';
}
