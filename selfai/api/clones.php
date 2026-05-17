<?php
/** SelfAI — Public catalogue of all 11 clones (safe subset, no system prompts leak) */
require __DIR__ . '/../includes/db.php';
$cfg = selfai_config();
$out = [];
foreach ($cfg['clones'] as $c) {
    $out[] = [
        'id'           => $c['id'],
        'code'         => $c['code'],
        'name'         => $c['name'],
        'english_name' => $c['english_name'],
        'project'      => $c['project'],
        'dimension'    => $c['dimension'],
        'currency'     => $c['currency'],
        'currency_long'=> $c['currency_long'],
        'ayah'         => $c['ayah'],
        'color'        => $c['color'],
        'icon'         => $c['icon'],
        'description'  => $c['description'],
    ];
}
selfai_json([
    'ok' => true,
    'brand' => $cfg['brand'],
    'clones' => $out,
    'default_model' => $cfg['default_model'],
]);
