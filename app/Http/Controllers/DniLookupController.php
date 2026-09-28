<?php

namespace App\Http\Controllers;

use App\Services\DniLookupService;

class DniLookupController extends Controller
{
    /**
     * Consulta un documento en api.json.pe y devuelve los datos del cliente.
     *
     * Acepta DNI (8 dígitos) y RUC (11), decidiendo por la longitud: el cajero
     * escribe el número en un solo campo y no tiene que indicar de qué tipo es.
     *
     * La forma de la respuesta ({found, name, message}) se mantiene igual que
     * antes para no romper la pantalla de Clientes, que ya la consumía.
     */
    public function lookup(string $dni, DniLookupService $service)
    {
        $document = trim($dni);

        if (!preg_match('/^\d{8}$|^\d{11}$/', $document)) {
            return response()->json([
                'found' => false,
                'message' => 'Ingresa un DNI de 8 dígitos o un RUC de 11.',
            ], 422);
        }

        $result = $service->lookupDocument($document);

        if (!$result) {
            $tipo = strlen($document) === 11 ? 'ese RUC' : 'ese DNI';

            return response()->json([
                'found' => false,
                'message' => "No se encontró información para {$tipo}.",
            ], 404);
        }

        return response()->json([
            'found' => true,
            'type' => $result['type'],
            'document' => $result['document'],
            // Se mantiene "dni" por compatibilidad con la vista de Clientes.
            'dni' => $result['document'],
            'name' => $result['name'],
            'address' => $result['address'] ?? null,
            'status' => $result['status'] ?? null,
            'condition' => $result['condition'] ?? null,
        ]);
    }
}
