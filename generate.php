<?php
require_once "productkeyencoder.php";

$pkeyFile = __DIR__ . "/pkconfig.xrm-ms";
$xmlContent = file_get_contents($pkeyFile);
$xml = simplexml_load_string($xmlContent);

// Buscar el nodo infoBin con los datos de configuración
$pkeyNode = $xml->xpath('//*[local-name()="infoBin"][@name="pkeyConfigData"]');
if (!$pkeyNode) {
    die("No se encontró infoBin pkeyConfigData");
}

$pkeyDataXml = base64_decode((string)$pkeyNode[0]);
$pkeyData = simplexml_load_string($pkeyDataXml);

$sku   = $_POST['sku'] ?? null;
$count = (int)($_POST['count'] ?? 0);

if (!$sku || $count < 1) {
    die("Parámetros inválidos.");
}

$ns = "http://www.microsoft.com/DRM/PKEY/Configuration/2.0";

// Extraer nodos con children() y namespace
$configs = $pkeyData->children($ns)->Configurations->children($ns)->Configuration;
$ranges  = $pkeyData->children($ns)->KeyRanges->children($ns)->KeyRange;

$selectedConfig = null;
foreach ($configs as $c) {
    if ((string)$c->children($ns)->RefGroupId === $sku) {
        $selectedConfig = $c;
        break;
    }
}
if (!$selectedConfig) {
    die("SKU no encontrado.");
}

// Buscar rangos válidos
$validRanges = [];
foreach ($ranges as $r) {
    if ((string)$r->children($ns)->RefActConfigId === (string)$selectedConfig->children($ns)->ActConfigId &&
        strtolower((string)$r->children($ns)->IsValid) === "true") {
        $validRanges[] = $r;
    }
}
if (empty($validRanges)) {
    die("No hay rangos válidos para este SKU.");
}

// Generar claves
$results = [];
for ($i = 0; $i < $count; $i++) {
    $range = $validRanges[array_rand($validRanges)];
    $serial = rand((int)$range->children($ns)->Start, (int)$range->children($ns)->End);
    $security = rand(0, 0x1FFFFFFFFFFFFF);
    $group = (int)$selectedConfig->children($ns)->RefGroupId;

    $key = new ProductKeyEncoder($group, $serial, $security, 0, '0x400', 0);
    $results[] = (string)$key;
}

echo implode("\n", $results);
?>

