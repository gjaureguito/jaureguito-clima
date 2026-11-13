<?php
/**
 * Panel privado de visitas: https://clima.jaureguitosistemas.com/panel.php
 * La primera vez pide crear una clave (se guarda cifrada con password_hash, fuera de public_html).
 */
declare(strict_types=1);
require __DIR__ . '/visitas.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']), 'samesite' => 'Strict']);
session_start();

$cfgArchivo = carpeta_visitas() . '/panel.json';
$cfg = is_file($cfgArchivo) ? (json_decode((string) file_get_contents($cfgArchivo), true) ?: []) : [];
$error = '';

if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(16));
}
$postValido = $_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($_SESSION['token'], (string) ($_POST['token'] ?? ''));

if ($postValido && isset($_POST['salir'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: /panel.php');
    exit;
}
if ($postValido && empty($cfg['hash']) && isset($_POST['nueva'])) {
    $n = (string) $_POST['nueva'];
    if (strlen($n) < 10) {
        $error = 'La clave tiene que tener al menos 10 caracteres.';
    } elseif ($n !== (string) ($_POST['repetir'] ?? '')) {
        $error = 'Las dos claves no coinciden.';
    } else {
        file_put_contents($cfgArchivo, json_encode(['hash' => password_hash($n, PASSWORD_DEFAULT)]), LOCK_EX);
        @chmod($cfgArchivo, 0600);
        session_regenerate_id(true);
        $_SESSION['ok'] = true;
        header('Location: /panel.php');
        exit;
    }
}
if ($postValido && !empty($cfg['hash']) && isset($_POST['clave'])) {
    if (password_verify((string) $_POST['clave'], $cfg['hash'])) {
        session_regenerate_id(true);
        $_SESSION['ok'] = true;
        header('Location: /panel.php');
        exit;
    }
    sleep(2); // frena intentos repetidos
    $error = 'Clave incorrecta.';
}

function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

$logueado = !empty($_SESSION['ok']);
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Visitas · Clima en San Juan</title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<style>
  :root{--bg:#07142b;--card:#0e2142;--c2:#13294f;--line:#1f3a66;--txt:#eaf2ff;--mut:#93a8c9;--acc:#2ee6d6}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;background:var(--bg);color:var(--txt);line-height:1.45}
  .wrap{max-width:980px;margin:0 auto;padding:24px 18px 40px}
  header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:10px;flex-wrap:wrap}
  h1{font-size:1.2rem}h1 span{color:var(--acc)}
  .card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:20px;margin-bottom:16px}
  .lbl{font-size:.75rem;text-transform:uppercase;letter-spacing:1px;color:var(--mut);margin-bottom:12px}
  .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
  .kpi{background:var(--c2);border-radius:14px;padding:14px}
  .kpi small{color:var(--mut);font-size:.8rem;display:block}.kpi b{font-size:1.9rem}
  .kpi span{display:block;color:var(--mut);font-size:.78rem}
  .barras{display:flex;align-items:flex-end;gap:4px;height:170px;padding-top:10px}
  .barra{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;font-size:.65rem;color:var(--mut)}
  .barra i{width:100%;max-width:26px;background:linear-gradient(180deg,var(--acc),#13897f);border-radius:5px 5px 2px 2px;min-height:2px}
  .barra b{color:var(--txt);font-size:.7rem;margin-bottom:3px}
  .tabla div{display:grid;grid-template-columns:1fr 60px;gap:10px;padding:7px 0;border-bottom:1px solid var(--line);align-items:center}
  .tabla div:last-child{border-bottom:0}
  .tabla .bar{height:8px;border-radius:4px;background:var(--acc);margin-top:4px}
  .tabla b{text-align:right}
  .dos{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  form.login{max-width:380px;margin:60px auto}
  input{width:100%;padding:12px 14px;border-radius:10px;border:1px solid var(--line);background:var(--c2);color:var(--txt);font:inherit;margin:6px 0 12px}
  button{background:var(--acc);color:#04202a;border:0;border-radius:999px;padding:11px 20px;font-weight:700;font:inherit;font-weight:700;cursor:pointer}
  button.sec{background:transparent;color:var(--mut);border:1px solid var(--line)}
  .error{color:#ff8a80;margin-bottom:10px}.ayuda{color:var(--mut);font-size:.85rem}
  @media(max-width:700px){.dos{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
<?php if (!$logueado): ?>
  <form class="login card" method="post" autocomplete="off">
    <h1>Visitas · <span>Clima en San Juan</span></h1>
    <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
    <?php if ($error): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
    <?php if (empty($cfg['hash'])): ?>
      <p class="ayuda" style="margin:12px 0">Primera vez: creá la clave para entrar al panel. Guardala bien; no se puede recuperar.</p>
      <label>Nueva clave (mínimo 10 caracteres)<input type="password" name="nueva" required minlength="10" autocomplete="new-password"></label>
      <label>Repetir clave<input type="password" name="repetir" required minlength="10" autocomplete="new-password"></label>
      <button type="submit">Crear clave y entrar</button>
    <?php else: ?>
      <label style="display:block;margin-top:14px">Clave<input type="password" name="clave" required autocomplete="current-password" autofocus></label>
      <button type="submit">Entrar</button>
    <?php endif; ?>
  </form>
<?php else:
    $dias = leer_dias(30);
    $suma = function (array $ds, string $g, string $k): int { $t = 0; foreach ($ds as $d) { $t += (int) ($d[$g][$k] ?? 0); } return $t; };
    $agrupar = function (array $ds, string $g): array { $r = []; foreach ($ds as $d) { foreach ($d[$g] ?? [] as $k => $v) { $r[$k] = ($r[$k] ?? 0) + $v; } } arsort($r); return $r; };
    $hoyD = array_slice($dias, -1, 1, true);
    $sem = array_slice($dias, -7, 7, true);
    $tarjeta = function (string $tit, array $ds) use ($suma) {
        $n = fn($v, $uno, $varios) => $v . ' ' . ($v === 1 ? $uno : $varios);
        return '<div class="kpi"><small>' . $tit . '</small><b>' . $suma($ds, 'total', 'entradas') . '</b><span>'
            . ($suma($ds, 'total', 'entradas') === 1 ? 'visita' : 'visitas') . ' · ' . $n($suma($ds, 'total', 'vistas'), 'página vista', 'páginas vistas') . '</span><span>'
            . $n($suma($ds, 'clics', 'whatsapp'), 'clic', 'clics') . ' en WhatsApp · ' . $n($suma($ds, 'clics', 'sitio'), 'clic', 'clics') . ' al sitio</span></div>';
    };
    $maxDia = max(1, ...array_values(array_map(fn($d) => (int) ($d["total"]["entradas"] ?? 0), $dias)));
    $tabla = function (array $items) {
        if (!$items) return '<p class="ayuda">Todavía no hay datos.</p>';
        $max = max($items); $s = '';
        foreach ($items as $k => $v) {
            $s .= '<div><span>' . h($k) . '<span class="bar" style="display:block;width:' . round($v / $max * 100) . '%"></span></span><b>' . (int) $v . '</b></div>';
        }
        return '<div class="tabla">' . $s . '</div>';
    };
?>
  <header>
    <h1>Visitas · <span>Clima en San Juan</span></h1>
    <form method="post"><input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>"><button class="sec" name="salir" value="1">Salir</button></form>
  </header>

  <section class="card">
    <div class="lbl">Resumen</div>
    <div class="kpis"><?= $tarjeta('Hoy', $hoyD) ?><?= $tarjeta('Últimos 7 días', $sem) ?><?= $tarjeta('Últimos 30 días', $dias) ?></div>
    <p class="ayuda" style="margin-top:12px">"Visitas" son las personas que entraron desde afuera (Google, WhatsApp, un link, etc.). "Páginas vistas" incluye cuando navegan entre meses. No se cuentan robots.</p>
  </section>

  <section class="card">
    <div class="lbl">Visitas por día · últimos 30 días</div>
    <div class="barras">
      <?php foreach ($dias as $f => $d): $v = (int) ($d['total']['entradas'] ?? 0); ?>
        <div class="barra" title="<?= h($f) ?>: <?= $v ?> visitas"><b><?= $v ?: '' ?></b><i style="height:<?= round($v / $maxDia * 100) ?>%"></i><?= h(substr($f, 8, 2)) ?></div>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="dos">
    <section class="card"><div class="lbl">¿De dónde vienen? · 30 días</div><?= $tabla($agrupar($dias, 'origenes')) ?></section>
    <section class="card"><div class="lbl">Páginas más vistas · 30 días</div><?= $tabla($agrupar($dias, 'paginas')) ?></section>
  </div>
  <div class="dos">
    <section class="card"><div class="lbl">Celular o computadora · 30 días</div><?= $tabla($agrupar($dias, 'dispositivo')) ?></section>
    <section class="card"><div class="lbl">Clics en la publicidad · 30 días</div><?= $tabla(array_filter(['WhatsApp' => $suma($dias, 'clics', 'whatsapp'), 'Ir a jaureguitosistemas.com' => $suma($dias, 'clics', 'sitio')])) ?></section>
  </div>
  <p class="ayuda">Para ver con qué búsquedas te encuentra Google, usá Search Console (Rendimiento). Este panel no usa cookies con los visitantes ni guarda sus IP.</p>
<?php endif; ?>
</div>
</body>
</html>
