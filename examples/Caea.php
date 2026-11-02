<?php
/**
 * Flujo CAEA (contingencia) en homologación.
 * Operaciones de consulta son seguras; solicitud e informe modifican estado en ARCA.
 */
include '../src/Afip.php';

$afip = new Afip(array('CUIT' => 20111111112));
$ws   = $afip->ElectronicBilling;

echo "=== FEDummy ===\n";
print_r($ws->GetServerStatus());

echo "\n=== Máximo comprobantes por request ===\n";
echo $ws->GetMaxRegistrationsPerRequest() . "\n";

$periodo = (int) date('Ym');
$orden   = ((int) date('j') <= 15) ? 1 : 2;

echo "\n=== Consulta CAEA (periodo {$periodo}, orden {$orden}) ===\n";
try {
	print_r($ws->QueryCaea($periodo, $orden));
} catch (Exception $e) {
	echo $e->getMessage() . "\n";
}

/*
 * Solicitar CAEA para la quincena (solo si el CUIT tiene CAEA habilitado en homologación).
 *
 * $caea = $ws->RequestCaea($periodo, $orden);
 * print_r($caea);
 */

/*
 * Informar CAEA sin movimiento en un punto de venta.
 *
 * $ws->InformCaeaNoMovement(1, 'CAEA_OBTENIDO_DE_REQUEST_O_QUERY');
 */

/*
 * Registro informativo de comprobantes emitidos con CAEA (misma forma que CreateVoucherBatch).
 * Cada detalle debe incluir 'CAEA' => '...' y, cuando ARCA lo exija, CbteFchHsGen.
 *
 * $ultimo = $ws->GetLastVoucher(1, 6);
 * $data = array(
 *     'PtoVta' => 1,
 *     'CbteTipo' => 6,
 *     'comprobantes' => array(
 *         array(
 *             'CAEA' => 'CAEA_OBTENIDO_DE_REQUEST_O_QUERY',
 *             'Concepto' => 1,
 *             'DocTipo' => 80,
 *             'DocNro' => 20111111112,
 *             'CondicionIVAReceptorId' => 1,
 *             'CbteDesde' => $ultimo + 1,
 *             'CbteHasta' => $ultimo + 1,
 *             'CbteFch' => (int) date('Ymd'),
 *             'ImpTotal' => 121,
 *             'ImpTotConc' => 0,
 *             'ImpNeto' => 100,
 *             'ImpOpEx' => 0,
 *             'ImpIVA' => 21,
 *             'ImpTrib' => 0,
 *             'MonId' => 'PES',
 *             'MonCotiz' => 1,
 *             'Iva' => array(
 *                 array('Id' => 5, 'BaseImp' => 100, 'Importe' => 21),
 *             ),
 *         ),
 *     ),
 * );
 * print_r($ws->RegisterCaeaInformativo($data));
 */
