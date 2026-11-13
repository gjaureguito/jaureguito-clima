<?php
declare(strict_types=1);
require __DIR__ . '/clima.php';
require __DIR__ . '/estadisticas.php';

header('Cache-Control: public, max-age=600');

$met = pronostico_met();
$smn = actual_smn();

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
function t($v): string
{
    return $v === null ? '–' : (string) round((float) $v);
}

$a = $met['actual'] ?? null;
[$iconoActual, $textoActual] = simbolo($a['simbolo'] ?? null);
$hoy = $met['dias'][0] ?? null;
$wa  = 'https://wa.me/5492644583811?text=' . rawurlencode('Hola, vi el clima de San Juan y quiero una página web para mi negocio');
$web = 'https://www.jaureguitosistemas.com/?utm_source=clima&utm_medium=banner';

// Foto: Precordillera de San Juan (Sierra del Tontal), WeHaKa, CC BY-SA 4.0, Wikimedia Commons.
// Si existe img/san-juan.jpg se usa la copia local; si no, la de Wikimedia.
$fotoArchivo = 'https://commons.wikimedia.org/wiki/File:Precordillera_de_San_Juan_Sierra_del_Tontal.jpg';
$foto = is_file(__DIR__ . '/img/san-juan.jpg')
    ? 'img/san-juan.jpg'
    : 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b4/Precordillera_de_San_Juan_Sierra_del_Tontal.jpg/1920px-Precordillera_de_San_Juan_Sierra_del_Tontal.jpg';

$fechaHoy = new DateTimeImmutable('now', new DateTimeZone(CLIMA_TZ));
$meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$fechaTexto = ucfirst(dia_semana($fechaHoy)) . ' ' . $fechaHoy->format('j') . ' de ' . $meses[(int) $fechaHoy->format('n') - 1];
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Clima en San Juan hoy y pronóstico extendido | Jaureguito Sistemas</title>
<meta name="description" content="El clima en San Juan, Argentina: temperatura actual, pronóstico por hora y extendido a 7 días, con datos oficiales del SMN. Actualizado cada 30 minutos.">
<link rel="canonical" href="https://clima.jaureguitosistemas.com/">
<meta property="og:title" content="Clima en San Juan hoy">
<meta property="og:description" content="Temperatura actual y pronóstico extendido para San Juan, Argentina.">
<meta property="og:image" content="<?= e(str_starts_with($foto, 'http') ? $foto : 'https://clima.jaureguitosistemas.com/' . $foto) ?>">
<meta name="theme-color" content="#07142b">
<meta http-equiv="refresh" content="1800">
<link rel="preload" as="image" href="<?= e($foto) ?>">
<style>
  :root{--bg:#07142b;--glass:rgba(255,255,255,.08);--glass2:rgba(255,255,255,.12);--line:rgba(255,255,255,.14);--txt:#f3f7ff;--mut:#b4c3dc;--acc:#2ee6d6}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:var(--bg);color:var(--txt);line-height:1.45;-webkit-font-smoothing:antialiased}

  /* Portada con la foto */
  .hero{position:relative;min-height:min(88vh,780px);display:flex;flex-direction:column;background:#0b1d3a url('<?= e($foto) ?>') center 40%/cover no-repeat}
  .hero::before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(7,20,43,.55) 0%,rgba(7,20,43,.15) 35%,rgba(7,20,43,.35) 65%,var(--bg) 100%)}
  .hero>*{position:relative}
  .top{display:flex;justify-content:space-between;align-items:center;max-width:960px;width:100%;margin:0 auto;padding:22px 20px}
  .brand{font-weight:600;letter-spacing:.3px;font-size:1rem}
  .brand span{color:var(--acc)}
  .top a{color:var(--txt);text-decoration:none;font-size:.85rem;padding:8px 14px;border:1px solid var(--line);border-radius:999px;background:rgba(7,20,43,.35);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)}
  .now{margin:auto;text-align:center;padding:20px 20px 60px;text-shadow:0 2px 24px rgba(0,0,0,.45)}
  .now .lugar{font-size:1.05rem;letter-spacing:.4px;color:#e2ebfa}
  .now .fecha{font-size:.88rem;color:var(--mut);margin-top:2px}
  .now .temp{font-size:clamp(5.5rem,18vw,9.5rem);font-weight:200;line-height:1;letter-spacing:-4px;margin:14px 0 4px}
  .now .desc{font-size:1.35rem;font-weight:500}
  .now .mm{margin-top:6px;color:#e2ebfa}
  .chips{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:22px}
  .chip{background:var(--glass);border:1px solid var(--line);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-radius:999px;padding:8px 16px;font-size:.9rem;text-shadow:none}
  .chip b{font-weight:600}

  /* Contenido */
  .wrap{max-width:960px;margin:-40px auto 0;padding:0 20px 40px;position:relative}
  .card{background:var(--glass);border:1px solid var(--line);border-radius:22px;padding:22px;margin-bottom:16px;backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px)}
  .lbl{font-size:.74rem;text-transform:uppercase;letter-spacing:1.2px;color:var(--mut);margin-bottom:14px}
  .smn{display:grid;grid-template-columns:repeat(auto-fit,minmax(135px,1fr));gap:12px}
  .smn div{background:var(--glass2);border-radius:14px;padding:12px 14px}
  .smn small{display:block;color:var(--mut);font-size:.75rem}
  .smn span{font-size:1.1rem;font-weight:600}
  .horas{display:flex;gap:10px;overflow-x:auto;padding-bottom:8px;scrollbar-color:var(--line) transparent}
  .horas::-webkit-scrollbar{height:6px}.horas::-webkit-scrollbar-thumb{background:var(--line);border-radius:3px}
  .hora{min-width:62px;text-align:center;background:var(--glass2);border-radius:16px;padding:12px 4px;font-size:.85rem}
  .hora.ya{background:rgba(46,230,214,.18);border:1px solid rgba(46,230,214,.4)}
  .hora .i{font-size:1.45rem;margin:5px 0}
  .hora .r{color:var(--acc);font-size:.72rem;min-height:1em}
  .dia{display:grid;grid-template-columns:110px 36px 1fr 150px;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--line)}
  .dia:last-child{border-bottom:0}
  .dia .n{text-transform:capitalize;font-weight:500}
  .dia .i{font-size:1.45rem}
  .dia .tx{color:var(--mut);font-size:.9rem}
  .dia .r{color:var(--acc);font-size:.8rem}
  .rango{display:flex;align-items:center;gap:8px;justify-content:flex-end;font-size:.95rem}
  .rango small{color:var(--mut);width:28px;text-align:right}
  .barra{flex:1;height:5px;border-radius:3px;background:rgba(255,255,255,.12);position:relative;min-width:50px}
  .barra i{position:absolute;top:0;bottom:0;border-radius:3px;background:linear-gradient(90deg,#4fc3f7,#ffd54f,#ff8a65)}

  .ad{position:relative;overflow:hidden;border-radius:22px;padding:26px;margin-bottom:16px;background:linear-gradient(135deg,rgba(18,58,110,.95),rgba(10,72,88,.95));border:1px solid rgba(46,230,214,.35)}
  .ad::after{content:"";position:absolute;right:-60px;top:-60px;width:220px;height:220px;border-radius:50%;background:radial-gradient(circle,rgba(46,230,214,.35),transparent 70%)}
  .ad .tag{position:absolute;top:12px;right:16px;font-size:.66rem;color:var(--mut);text-transform:uppercase;letter-spacing:1px;z-index:1}
  .ad h2{font-size:1.35rem;margin-bottom:6px;position:relative;z-index:1}
  .ad p{color:#d3e1f5;margin-bottom:16px;max-width:600px;position:relative;z-index:1}
  .btn{display:inline-block;padding:11px 20px;border-radius:999px;text-decoration:none;font-weight:600;font-size:.92rem;margin:4px 8px 4px 0;position:relative;z-index:1}
  .btn.wa{background:var(--acc);color:#04202a}
  .btn.ol{border:1px solid var(--acc);color:var(--acc)}

  /* Estadísticas */
  .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:22px}
  .stat{background:var(--glass2);border-radius:16px;padding:14px 16px;border:1px solid transparent}
  .stat small{display:block;color:var(--mut);font-size:.78rem}
  .stat b{display:block;font-size:1.9rem;font-weight:600;letter-spacing:-.5px;margin:2px 0}
  .stat span{color:var(--mut);font-size:.82rem}
  .stat.calido{border-color:rgba(255,138,101,.45)}.stat.calido b{color:#ffab91}
  .stat.frio{border-color:rgba(79,195,247,.45)}.stat.frio b{color:#81d4fa}
  .sub{font-size:.95rem;font-weight:600;margin:6px 0 10px}
  .graf-wrap{overflow-x:auto;scrollbar-color:var(--line) transparent}
  .graf{width:100%;min-width:560px;height:auto;display:block}
  .graf-grid{stroke:rgba(255,255,255,.08)}
  .graf-eje{fill:var(--mut);font-size:11px}
  .graf-mes{fill:var(--mut);font-size:12px}.graf-mes.es-actual{fill:var(--acc);font-weight:700}
  .graf-actual{fill:rgba(46,230,214,.08);stroke:rgba(46,230,214,.35)}
  .graf-record{stroke:rgba(255,255,255,.25);stroke-width:1.5;stroke-dasharray:3 3}
  .graf-record-max{fill:#ff8a65}.graf-record-min{fill:#4fc3f7}
  .graf-hoy{fill:#fff;stroke:var(--acc);stroke-width:3}
  .leyenda{display:flex;flex-wrap:wrap;gap:16px;color:var(--mut);font-size:.8rem;margin:8px 0 22px}
  .leyenda i{display:inline-block;width:12px;height:12px;border-radius:6px;margin-right:6px;vertical-align:-1px}
  .lg-normal{background:linear-gradient(0deg,#4fc3f7,#ffd54f,#ff8a65)}
  .lg-record{border:1.5px dashed rgba(255,255,255,.4)}
  .lg-hoy{background:#fff;border:3px solid var(--acc)}
  .lluvia{display:grid;grid-template-columns:repeat(12,1fr);gap:6px;align-items:end;height:130px}
  .ll{display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;font-size:.72rem;color:var(--mut)}
  .ll i{width:70%;max-width:26px;border-radius:6px 6px 2px 2px;background:linear-gradient(180deg,#4fc3f7,#1e6fa8);display:block}
  .ll small{margin-top:4px;color:var(--txt)}
  .ll.es-actual i{background:linear-gradient(180deg,var(--acc),#13897f)}.ll.es-actual span{color:var(--acc);font-weight:700}
  .fuente{color:var(--mut);font-size:.75rem;margin-top:14px}

  /* Gráficos interactivos */
  .widget{background:rgba(255,255,255,.04);border:1px solid var(--line);border-radius:18px;padding:18px;margin-bottom:18px}
  .widget .sub{margin-top:0}
  .frase{color:var(--txt);font-size:1rem;line-height:1.5;margin-top:10px}
  .frase b{color:var(--acc)}
  .ayuda{color:var(--mut);font-size:.85rem;margin:-4px 0 12px}
  .termo{padding:46px 0 26px}
  .termo-barra{position:relative;height:18px;border-radius:9px;background:rgba(255,255,255,.08)}
  .termo-zona{position:absolute;top:0;bottom:0}
  .termo-zona:first-of-type{border-radius:9px 0 0 9px}.termo-zona:last-of-type{border-radius:0 9px 9px 0}
  .termo-zona:last-of-type em{left:auto;right:4px}
  .termo-zona em{position:absolute;top:24px;left:4px;font-style:normal;font-size:.72rem;color:var(--mut);white-space:nowrap}
  .z-fresco{background:#3b82c4}.z-normal{background:#2fbf8f}.z-calor{background:#f59e4b}.z-record{background:#e5484d}
  .termo-aguja{position:absolute;top:-10px;width:4px;height:38px;margin-left:-2px;background:#fff;border-radius:2px;box-shadow:0 0 0 3px rgba(7,20,43,.6);transition:left 1.4s cubic-bezier(.2,.8,.2,1);z-index:2}
  .termo-valor{position:absolute;bottom:44px;left:50%;transform:translateX(-50%);background:#fff;color:#07142b;font-weight:700;padding:3px 10px;border-radius:999px;font-size:1rem;white-space:nowrap}
  .termo-valor::after{content:"";position:absolute;left:50%;bottom:-5px;margin-left:-5px;border:5px solid transparent;border-top-color:#fff;border-bottom:0}
  .termo-marcas{position:relative;height:0}
  .termo-marcas span{position:absolute;top:-40px;transform:translateX(-50%);font-size:.7rem;color:var(--mut);white-space:nowrap}
  .termo-marcas span.a-der{transform:translateX(-100%)}
  .termo-marcas span.a-der::before{left:auto;right:0}
  .termo-marcas span::before{content:"";position:absolute;left:50%;top:14px;width:1px;height:12px;background:rgba(255,255,255,.5)}
  .graf-wrap-sem{overflow-x:auto}
  .semana{min-width:520px}
  .sem-banda{fill:rgba(47,191,143,.16);stroke:rgba(47,191,143,.5);stroke-dasharray:4 4}
  .sem-max{fill:none;stroke:#ff8a65;stroke-width:3.5;stroke-linecap:round;stroke-linejoin:round}
  .sem-min{fill:none;stroke:#4fc3f7;stroke-width:3.5;stroke-linecap:round;stroke-linejoin:round}
  .sem-p-max{fill:#ff8a65;stroke:#07142b;stroke-width:2}.sem-p-min{fill:#4fc3f7;stroke:#07142b;stroke-width:2}
  .sem-val{fill:var(--txt);font-size:13px;font-weight:600;pointer-events:none}
  .sem-val-min{fill:#b3e5fc}
  .sem-col{fill:transparent;cursor:pointer;outline:none}
  .sem-col:hover,.sem-col:focus-visible,.sem-col.activa{fill:rgba(255,255,255,.07)}
  .lg-max{background:#ff8a65}.lg-min{background:#4fc3f7}.lg-banda{background:rgba(47,191,143,.35);border:1px dashed rgba(47,191,143,.8)}
  .mes-chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
  .chip-mes{font:inherit;font-size:.85rem;text-transform:capitalize;color:var(--txt);background:var(--glass2);border:1px solid var(--line);border-radius:999px;padding:7px 13px;cursor:pointer;transition:background .2s,border-color .2s}
  .chip-mes:hover{border-color:var(--acc)}
  .chip-mes.activo{background:var(--acc);color:#04202a;border-color:var(--acc);font-weight:700}
  .mes-ficha{background:var(--glass2);border-radius:16px;padding:18px}
  .mes-ficha.entra{animation:entra .45s ease-out}
  @keyframes entra{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
  .f-titulo{font-size:1.1rem;margin-bottom:12px}
  .f-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:10px}
  .f-grid div{background:rgba(7,20,43,.35);border-radius:12px;padding:10px 12px}
  .f-grid small{display:block;color:var(--mut);font-size:.78rem}
  .f-grid b{display:block;font-size:1.5rem;font-weight:600;margin-top:2px}
  .f-gotas{display:flex;gap:3px;margin-top:4px}
  .f-gotas i{width:9px;height:12px;border-radius:50% 50% 50% 50%/60% 60% 40% 40%;background:rgba(255,255,255,.12)}
  .f-gotas i.on{background:#4fc3f7}
  .f-texto{margin-top:14px;line-height:1.55}
  .f-ropa-wrap{margin-top:8px;color:var(--mut)}
  .graf-col{fill:transparent;cursor:pointer}
  .graf-col:hover{fill:rgba(255,255,255,.05)}
  .graf-col.elegido{fill:rgba(255,255,255,.07);stroke:rgba(255,255,255,.35)}
  .ll{cursor:pointer;border-radius:8px}
  .ll.elegido{background:rgba(255,255,255,.07)}
  @media (prefers-reduced-motion:reduce){.termo-aguja{transition:none}.mes-ficha.entra{animation:none}}

  footer{color:var(--mut);font-size:.78rem;text-align:center;margin-top:26px;line-height:1.7}
  footer a{color:var(--mut)}
  footer .aviso{max-width:640px;margin:8px auto 0}
  .err{color:var(--mut)}
  @media(max-width:560px){.dia{grid-template-columns:82px 30px 1fr}.dia .tx{display:none}.rango{grid-column:3}.hero{min-height:min(80vh,680px)}}
</style>
</head>
<body>

<section class="hero">
  <div class="top">
    <div class="brand">Clima <span>San Juan</span></div>
    <a href="<?= e($web) ?>">Jaureguito Sistemas</a>
  </div>

  <div class="now">
    <div class="lugar">San Juan, Argentina</div>
    <div class="fecha"><?= e($fechaTexto) ?></div>
    <?php if ($a): ?>
      <h1 class="temp"><?= t($a['temp']) ?>°</h1>
      <div class="desc"><?= $iconoActual ?> <?= e($textoActual) ?></div>
      <div class="mm">Máx <?= t($hoy['max'] ?? null) ?>° · Mín <?= t($a['min_noche'] ?? $hoy['min'] ?? null) ?>°</div>
      <div class="chips">
        <span class="chip">💧 Humedad <b><?= t($a['humedad']) ?>%</b></span>
        <span class="chip">🌬️ Viento <b><?= t($a['viento']) ?> km/h <?= e($a['rumbo']) ?></b></span>
        <span class="chip">☔ Próxima hora <b><?= e($a['lluvia_1h']) ?> mm</b></span>
      </div>
    <?php else: ?>
      <h1 class="temp">–°</h1>
      <div class="desc">No pudimos obtener el pronóstico. Probá en unos minutos.</div>
    <?php endif; ?>
  </div>
</section>

<main class="wrap">

<?php if (!empty($met['horas'])): ?>
<section class="card">
  <div class="lbl">Próximas 24 horas</div>
  <div class="horas">
    <?php foreach ($met['horas'] as $i => $h): [$ic] = simbolo($h['simbolo']); ?>
      <div class="hora<?= $i === 0 ? ' ya' : '' ?>">
        <div><?= $i === 0 ? 'Ahora' : e($h['hora']) . ' h' ?></div>
        <div class="i"><?= $ic ?></div>
        <div><b><?= t($h['temp']) ?>°</b></div>
        <div class="r"><?= $h['lluvia'] > 0 ? e($h['lluvia']) . ' mm' : '' ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($smn): ?>
<section class="card">
  <div class="lbl">Medición oficial SMN · estación <?= e($smn['estacion']) ?> · <?= e($smn['hora']) ?> h</div>
  <div class="smn">
    <div><small>Temperatura</small><span><?= t($smn['temp']) ?>°</span></div>
    <?php if ($smn['termica'] !== null): ?><div><small>Sensación térmica</small><span><?= t($smn['termica']) ?>°</span></div><?php endif; ?>
    <div><small>Humedad</small><span><?= t($smn['humedad']) ?>%</span></div>
    <div><small>Viento</small><span><?= e($smn['viento']) ?></span></div>
    <div><small>Estado</small><span><?= e($smn['estado']) ?></span></div>
    <?php if ($smn['presion'] !== ''): ?><div><small>Presión</small><span><?= e($smn['presion']) ?> hPa</span></div><?php endif; ?>
  </div>
</section>
<?php endif; ?>

<aside class="ad">
  <span class="tag">Publicidad</span>
  <h2>¿Tu negocio todavía no tiene página web?</h2>
  <p>En Jaureguito Sistemas diseñamos sitios profesionales para negocios de San Juan: rápidos, adaptados al celular y listos para aparecer en Google.</p>
  <a class="btn wa" href="<?= e($wa) ?>" rel="noopener">Escribinos por WhatsApp</a>
  <a class="btn ol" href="<?= e($web) ?>">Ver jaureguitosistemas.com</a>
</aside>

<?php if (!empty($met['dias'])):
    $tmin = min(array_column($met['dias'], 'min'));
    $tmax = max(array_column($met['dias'], 'max'));
    $span = max(1, $tmax - $tmin); ?>
<section class="card">
  <div class="lbl">Pronóstico 7 días</div>
  <?php foreach ($met['dias'] as $i => $d):
      $f = new DateTimeImmutable($d['fecha'], new DateTimeZone(CLIMA_TZ));
      [$ic, $tx] = simbolo($d['simbolo']);
      $izq = ($d['min'] - $tmin) / $span * 100;
      $ancho = max(4, ($d['max'] - $d['min']) / $span * 100); ?>
    <div class="dia">
      <div class="n"><?= $i === 0 ? 'Hoy' : e(dia_semana($f)) ?></div>
      <div class="i"><?= $ic ?></div>
      <div class="tx"><?= e($tx) ?><?php if ($d['lluvia'] > 0): ?> · <span class="r"><?= e($d['lluvia']) ?> mm</span><?php endif; ?></div>
      <div class="rango">
        <small><?= t($d['min']) ?>°</small>
        <span class="barra"><i style="left:<?= round($izq) ?>%;width:<?= round($ancho) ?>%"></i></span>
        <b><?= t($d['max']) ?>°</b>
      </div>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?= seccion_estadisticas($met) ?>

<footer>
  <p>Medición actual: <a href="https://www.smn.gob.ar/" rel="noopener">Servicio Meteorológico Nacional</a> (<a href="https://creativecommons.org/licenses/by/2.5/ar/" rel="noopener">CC BY 2.5 AR</a>).
  Pronóstico: datos de <a href="https://www.met.no/en" rel="noopener">MET Norway</a> (<a href="https://creativecommons.org/licenses/by/4.0/" rel="noopener">CC BY 4.0</a>), adaptados a la hora de San Juan.</p>
  <p>Estadísticas: normales 1991-2020 y récords 1967-2025 de la estación San Juan Aero, <a href="https://www.smn.gob.ar/estadisticas" rel="noopener">SMN</a> (<a href="https://creativecommons.org/licenses/by/2.5/ar/" rel="noopener">CC BY 2.5 AR</a>).</p>
  <p>Foto: Precordillera de San Juan, <a href="<?= e($fotoArchivo) ?>" rel="noopener">WeHaKa / Wikimedia Commons</a> (<a href="https://creativecommons.org/licenses/by-sa/4.0/" rel="noopener">CC BY-SA 4.0</a>), con filtro de color.</p>
  <p class="aviso">La información de esta página es orientativa y puede no coincidir con las condiciones reales. Para decisiones importantes y ante alertas meteorológicas, consultá siempre el <a href="https://www.smn.gob.ar/" rel="noopener">sitio oficial del SMN</a>.</p>
  <p class="aviso">Sitio independiente de Jaureguito Sistemas, sin vinculación con el Servicio Meteorológico Nacional ni con MET Norway. Esta página no usa cookies ni recopila datos personales.</p>
  <p>© <?= date('Y') ?> <a href="<?= e($web) ?>">Jaureguito Sistemas</a> · Tulum 50, Rivadavia, San Juan</p>
</footer>

</main>
</body>
</html>
