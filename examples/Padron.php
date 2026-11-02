<?php
/**
 * Consulta padrón (ws_sr_padron_a13) en homologación.
 * Requiere certificado con servicio ws_sr_padron_a13 autorizado en AFIP.
 */
include '../src/Afip.php';

$afip = new Afip(array('CUIT' => 20111111112));

$padron = $afip->RegisterScopeThirteen;

echo "=== dummy ===\n";
print_r($padron->GetServerStatus());

$cuit_consulta = 20111111112;

echo "\n=== getPersona (CUIT {$cuit_consulta}) ===\n";
$persona = $padron->GetTaxpayerDetails($cuit_consulta);
if ($persona === null) {
	echo "No existe en padrón.\n";
} else {
	print_r($persona);
}

/*
echo "\n=== getIdPersonaListByDocumento ===\n";
print_r($padron->GetTaxIDByDocument('12345678'));
*/
