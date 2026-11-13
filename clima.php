<?php
/**
 * clima.jaureguitosistemas.com — capa de datos
 *
 * Fuentes (ambas permiten uso comercial con atribución):
 *  - SMN Argentina: estado del tiempo presente (CC BY 2.5 AR)
 *  - MET Norway (api.met.no): pronóstico (CC BY 4.0)
 *
 * Las respuestas se guardan en /cache para no consultar en cada visita
 * (MET Norway lo exige en sus condiciones de uso).
 */
declare(strict_types=1);

const CLIMA_LAT   = -31.537;   // San Juan capital
const CLIMA_LON   = -68.536;
const CLIMA_UA    = 'clima.jaureguitosistemas.com contacto@jaureguitosistemas.com';
const CLIMA_TZ    = 'America/Argentina/San_Juan';
const CLIMA_CACHE = __DIR__ . '/cache';
const CLIMA_TTL   = 1800;      // 30 minutos

const SMN_URL = 'https://ssl.smn.gob.ar/dpd/zipopendata.php?dato=tiepre';
const SMN_ESTACION = 'San Juan';

/* ---------- utilidades ---------- */

function http_get(string $url, int $timeout = 12): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => CLIMA_UA,
        CURLOPT_ENCODING       => '',
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($body !== false && $code === 200) ? $body : null;
}

/** Devuelve datos de caché si están frescos; si no, los busca. Si falla, usa la caché vieja. */
function cached(string $key, callable $fetch): ?array
{
    $file = CLIMA_CACHE . "/$key.json";
    if (is_file($file) && time() - filemtime($file) < CLIMA_TTL) {
        $data = json_decode((string) file_get_contents($file), true);
        if (is_array($data)) {
            return $data;
        }
    }
    try {
        $data = $fetch();
    } catch (Throwable $e) {
        $data = null;
    }
    if (is_array($data)) {
        if (!is_dir(CLIMA_CACHE)) {
            @mkdir(CLIMA_CACHE, 0755, true);
        }
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $data;
    }
    if (is_file($file)) {
        @touch($file); // evita reintentar en cada visita si la fuente está caída
        $old = json_decode((string) file_get_contents($file), true);
        return is_array($old) ? $old : null;
    }
    return null;
}

function num(string $s): ?float
{
    $s = str_replace(',', '.', trim($s));
    return is_numeric($s) ? (float) $s : null;
}

function local_dt(string $iso): DateTimeImmutable
{
    return (new DateTimeImmutable($iso))->setTimezone(new DateTimeZone(CLIMA_TZ));
}

function rumbo(?float $grados): string
{
    if ($grados === null) {
        return '';
    }
    $r = ['N', 'NE', 'E', 'SE', 'S', 'SO', 'O', 'NO'];
    return $r[(int) round($grados / 45) % 8];
}

/** Traduce el symbol_code de MET Norway a [emoji, texto]. */
function simbolo(?string $code): array
{
    $c = (string) $code;
    $noche = str_ends_with($c, '_night');
    $base = preg_replace('/_(day|night|polartwilight)$/', '', $c);

    if (str_contains($base, 'thunder'))            return ['⛈️', 'Tormenta'];
    if (str_contains($base, 'snow'))               return ['🌨️', 'Nieve'];
    if (str_contains($base, 'sleet'))              return ['🌨️', 'Aguanieve'];
    if (str_contains($base, 'rainshowers'))        return ['🌦️', 'Chaparrones'];
    if ($base === 'lightrain')                     return ['🌧️', 'Lluvia débil'];
    if ($base === 'heavyrain')                     return ['🌧️', 'Lluvia fuerte'];
    if (str_contains($base, 'rain'))               return ['🌧️', 'Lluvia'];
    if ($base === 'fog')                           return ['🌫️', 'Niebla'];
    if ($base === 'cloudy')                        return ['☁️', 'Nublado'];
    if ($base === 'partlycloudy')                  return [$noche ? '☁️' : '⛅', 'Parcialmente nublado'];
    if ($base === 'fair')                          return [$noche ? '🌙' : '🌤️', 'Algo nublado'];
    if ($base === 'clearsky')                      return [$noche ? '🌙' : '☀️', 'Despejado'];
    return ['🌡️', ''];
}

function dia_semana(DateTimeImmutable $d): string
{
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    return $dias[(int) $d->format('w')];
}

/* ---------- MET Norway: pronóstico ---------- */

function pronostico_met(): ?array
{
    return cached('met', function () {
        $url = sprintf(
            'https://api.met.no/weatherapi/locationforecast/2.0/compact?lat=%.3f&lon=%.3f',
            CLIMA_LAT,
            CLIMA_LON
        );
        $raw = http_get($url);
        if ($raw === null) {
            return null;
        }
        $json = json_decode($raw, true);
        $series = $json['properties']['timeseries'] ?? null;
        return is_array($series) && $series ? procesar_met($series) : null;
    });
}

function procesar_met(array $series): array
{
    $ahora = $series[0];
    $d = $ahora['data']['instant']['details'];
    $actual = [
        'hora'      => local_dt($ahora['time'])->format('H:i'),
        'temp'      => $d['air_temperature'] ?? null,
        'humedad'   => $d['relative_humidity'] ?? null,
        'viento'    => isset($d['wind_speed']) ? round($d['wind_speed'] * 3.6) : null,
        'rumbo'     => rumbo($d['wind_from_direction'] ?? null),
        'simbolo'   => $ahora['data']['next_1_hours']['summary']['symbol_code']
                    ?? $ahora['data']['next_6_hours']['summary']['symbol_code'] ?? null,
        'lluvia_1h' => $ahora['data']['next_1_hours']['details']['precipitation_amount'] ?? 0,
    ];

    $horas = [];
    foreach ($series as $p) {
        if (count($horas) >= 24 || !isset($p['data']['next_1_hours'])) {
            break;
        }
        $horas[] = [
            'hora'    => local_dt($p['time'])->format('H'),
            'temp'    => $p['data']['instant']['details']['air_temperature'] ?? null,
            'simbolo' => $p['data']['next_1_hours']['summary']['symbol_code'] ?? null,
            'lluvia'  => $p['data']['next_1_hours']['details']['precipitation_amount'] ?? 0,
        ];
    }

    $dias = [];
    foreach ($series as $p) {
        $t = local_dt($p['time']);
        $k = $t->format('Y-m-d');
        $temp = $p['data']['instant']['details']['air_temperature'] ?? null;
        if (!isset($dias[$k])) {
            $dias[$k] = ['fecha' => $k, 'min' => $temp, 'max' => $temp, 'lluvia' => 0.0, 'simbolo' => null, 'dist' => 99];
        }
        if ($temp !== null) {
            $dias[$k]['min'] = min($dias[$k]['min'] ?? $temp, $temp);
            $dias[$k]['max'] = max($dias[$k]['max'] ?? $temp, $temp);
        }
        if (isset($p['data']['next_1_hours'])) {
            $dias[$k]['lluvia'] += $p['data']['next_1_hours']['details']['precipitation_amount'] ?? 0;
        } elseif (isset($p['data']['next_6_hours'])) {
            $dias[$k]['lluvia'] += $p['data']['next_6_hours']['details']['precipitation_amount'] ?? 0;
        }
        // el ícono del día es el más cercano al mediodía
        $sym = $p['data']['next_6_hours']['summary']['symbol_code']
            ?? $p['data']['next_1_hours']['summary']['symbol_code'] ?? null;
        $dist = abs((int) $t->format('G') - 12);
        if ($sym && $dist < $dias[$k]['dist']) {
            $dias[$k]['simbolo'] = $sym;
            $dias[$k]['dist'] = $dist;
        }
    }
    $dias = array_slice(array_values($dias), 0, 7);
    foreach ($dias as &$dia) {
        $dia['lluvia'] = round($dia['lluvia'], 1);
        unset($dia['dist']);
    }

    return ['actual' => $actual, 'horas' => $horas, 'dias' => $dias, 'obtenido' => time()];
}

/* ---------- SMN: estado del tiempo presente ---------- */

function actual_smn(): ?array
{
    return cached('smn', function () {
        $zip = http_get(SMN_URL, 15);
        if ($zip === null || !class_exists('ZipArchive')) {
            return null;
        }
        $txt = leer_zip($zip);
        return $txt === null ? null : parsear_smn($txt);
    });
}

function leer_zip(string $bytes): ?string
{
    $tmp = tempnam(sys_get_temp_dir(), 'smn');
    file_put_contents($tmp, $bytes);
    $za = new ZipArchive();
    $txt = null;
    if ($za->open($tmp) === true) {
        $r = $za->getFromIndex(0);
        $txt = $r === false ? null : $r;
        $za->close();
    }
    @unlink($tmp);
    return $txt;
}

/**
 * Formato del SMN (una estación por línea, separado por ";"):
 * Estación;Fecha;Hora;Estado;Visibilidad;Temperatura;Sensación térmica;Humedad;Viento;Presión
 */
function parsear_smn(string $txt): ?array
{
    if (function_exists('mb_check_encoding') && !mb_check_encoding($txt, 'UTF-8')) {
        $txt = mb_convert_encoding($txt, 'UTF-8', 'ISO-8859-1');
    }
    foreach (preg_split('/\r?\n/', $txt) as $linea) {
        $c = array_map('trim', explode(';', $linea));
        if (count($c) < 9 || stripos($c[0], SMN_ESTACION) !== 0) {
            continue;
        }
        return [
            'estacion' => $c[0],
            'fecha'    => $c[1],
            'hora'     => $c[2],
            'estado'   => $c[3],
            'temp'     => num($c[5]),
            'termica'  => num($c[6]),
            'humedad'  => num($c[7]),
            'viento'   => preg_replace('/(\d)$/', '$1 km/h', preg_replace('/\s+/', ' ', $c[8])),
            'presion'  => trim(rtrim($c[9] ?? '', '/ ')),
        ];
    }
    return null;
}
