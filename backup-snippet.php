<?php
// UpdraftPlus via ManageWP Code Snippets — força disparo imediato com múltiplos caminhos + diagnóstico

ignore_user_abort(true);
if (function_exists('set_time_limit')) @set_time_limit(0);

if (!defined('DOING_CRON')) define('DOING_CRON', true);
if (!defined('UPDRAFTPLUS_CONSOLELOG')) define('UPDRAFTPLUS_CONSOLELOG', true);

require_once ABSPATH . 'wp-load.php';
require_once ABSPATH . 'wp-includes/pluggable.php';
require_once ABSPATH . 'wp-includes/option.php';
require_once ABSPATH . 'wp-includes/cron.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Garante contexto admin (ajuste o ID se necessário)
if (!function_exists('wp_get_current_user') || !current_user_can('manage_options')) {
  if (function_exists('wp_set_current_user')) {
    @wp_set_current_user(1);
  }
}

$label = 'Backup via ManageWP - ' . gmdate('Y-m-d\TH:i:s\Z');
$args  = array(
  'nocloud'     => 0,        // 0 = envia para o storage configurado
  'incremental' => 0,        // 0 = completo
  'label'       => $label,
);

// Diagnóstico básico
$diag = array(
  'time'            => gmdate('c'),
  'updraft_active'  => is_plugin_active('updraftplus/updraftplus.php'),
  'class_commands'  => class_exists('UpdraftPlus_Commands'),
  'wp_cron_disabled'=> defined('DISABLE_WP_CRON') ? (bool)DISABLE_WP_CRON : false,
  'doing_cron'      => (bool) (defined('DOING_CRON') && DOING_CRON),
);

// 1) Hook oficial
do_action('updraft_backupnow_backup_all', $args);

// 1.1) Agendamento de segurança + execução imediata do cron
if (function_exists('wp_schedule_single_event')) {
  @wp_clear_scheduled_hook('updraft_backupnow_backup_all', array($args));
  @wp_schedule_single_event(time() + 1, 'updraft_backupnow_backup_all', array($args));
}

// 2) Força o cron processar a fila agora
if (function_exists('wp_cron')) @wp_cron();
if (function_exists('_get_cron_array')) {
  $diag['cron_count'] = count((array)_get_cron_array());
}

// 3) Fallback: API interna (se disponível)
$ran_internal = false;
if (class_exists('UpdraftPlus_Commands')) {
  try {
    $cmd = new UpdraftPlus_Commands();
    $cmd->backupnow($args);
    $ran_internal = true;
  } catch (Throwable $e) {
    error_log('UpdraftPlus backup fallback error: ' . $e->getMessage());
  }
}

// 4) Segunda chamada ao cron após fallback
if (function_exists('wp_cron')) @wp_cron();

// 5) Ecoa status rápido no output do snippet
header('Content-Type: text/plain; charset=utf-8');
echo "Solicitado backup do UpdraftPlus: {$label}\n";
echo "Diagnóstico:\n";
foreach ($diag as $k => $v) {
  echo "- {$k}: " . (is_bool($v) ? ($v ? 'true' : 'false') : $v) . "\n";
}
echo "- ran_internal_api: " . ($ran_internal ? 'true' : 'false') . "\n";
