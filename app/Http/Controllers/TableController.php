<?php

namespace App\Http\Controllers;

use App\Models\Table;
use App\Models\Area;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function index()
    {
        $areas = Area::where('active', true)
            ->with(['tables' => function ($query) {
                $query->where('active', true);
            }])
            ->get();
        return view('tables.index', compact('areas'));
    }

    public function storeArea(Request $request)
    {
        $request->validate(['name' => 'required']);
        Area::create($request->all());
        return redirect()->back()->with('success', 'Zona creada.');
    }

    public function destroyArea(Area $area)
    {
        // Las mesas sin pedidos se borran de verdad (limpieza normal).
        // Las mesas con pedidos nunca se borran (perderían el historial de
        // ventas): se desactivan. Por eso la zona en sí nunca se borra
        // físicamente si tuvo alguna vez una mesa con pedidos: se oculta
        // (active = false) para que desaparezca del salón/POS sin arriesgar
        // el historial de ventas de sus mesas.
        $hasOrders = false;

        foreach ($area->tables as $table) {
            if ($table->orders()->exists()) {
                $hasOrders = true;
                $table->update(['active' => false]);
                continue;
            }

            if ($table->image) {
                \Storage::disk('public')->delete($table->image);
            }
            $table->delete();
        }

        if ($hasOrders) {
            $area->update(['active' => false]);
            return redirect()->back()->with('success', 'Zona eliminada. Algunas mesas tenían pedidos registrados, así que se conservaron ocultas junto con la zona para no perder el historial de ventas.');
        }

        $area->delete();
        return redirect()->back()->with('success', 'Zona eliminada.');
    }

    public function storeTable(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'area_id' => 'required|exists:areas,id',
            'image' => 'nullable|image|max:5120',
        ]);

        Table::create([
            'name' => $request->name,
            'area_id' => $request->area_id,
            'status' => 'available',
            'x_pos' => 50, // Posición por defecto visible
            'y_pos' => 50,
            'image' => $request->hasFile('image') ? $this->storeImageAsWebp($request->file('image')) : null,
        ]);

        return redirect()->back()->with('success', 'Mesa creada.');
    }

    public function updateTable(Request $request, Table $table)
    {
        $request->validate([
            'name' => 'required',
            'area_id' => 'required|exists:areas,id',
            'image' => 'nullable|image|max:5120',
        ]);

        $data = [
            'name' => $request->name,
            'area_id' => $request->area_id,
        ];

        if ($request->hasFile('image')) {
            if ($table->image) {
                \Storage::disk('public')->delete($table->image);
            }
            $data['image'] = $this->storeImageAsWebp($request->file('image'));
        }

        $table->update($data);

        return redirect()->back()->with('success', 'Mesa actualizada.');
    }

    public function destroyTable(Table $table)
    {
        // Si la mesa tiene pedidos asociados, no se puede borrar sin perder ese
        // historial de ventas. En ese caso se desactiva: desaparece del salón
        // pero se conserva intacta para reportes y contabilidad.
        if ($table->orders()->exists()) {
            $table->update(['active' => false]);
            return redirect()->back()->with('success', 'La mesa tiene pedidos registrados, así que se ocultó del salón en lugar de eliminarse (se conserva el historial de ventas).');
        }

        if ($table->image) {
            \Storage::disk('public')->delete($table->image);
        }
        $table->delete();
        return redirect()->back()->with('success', 'Mesa eliminada.');
    }

    /**
     * Convierte la imagen subida a WebP (calidad 85) y la guarda en storage/app/public/tables.
     * Se normaliza a un lienzo cuadrado fijo (misma resolución que la imagen por defecto)
     * para que todas las mesas se vean del mismo tamaño en el canvas del salón,
     * sin importar la proporción o resolución original de la foto subida.
     * Devuelve la ruta relativa guardada (para el campo `image`).
     */
    private function storeImageAsWebp($file): string
    {
        $source = match ($file->getMimeType()) {
            'image/png' => imagecreatefrompng($file->getRealPath()),
            'image/gif' => imagecreatefromgif($file->getRealPath()),
            'image/webp' => imagecreatefromwebp($file->getRealPath()),
            default => imagecreatefromjpeg($file->getRealPath()),
        };

        // Preserva transparencia para PNG/GIF
        imagepalettetotruecolor($source);
        imagealphablending($source, true);
        imagesavealpha($source, true);

        $canvasSize = 900;
        $canvas = imagecreatetruecolor($canvasSize, $canvasSize);
        imagepalettetotruecolor($canvas);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        $srcWidth = imagesx($source);
        $srcHeight = imagesy($source);
        $scale = min($canvasSize / $srcWidth, $canvasSize / $srcHeight);
        $destWidth = (int) round($srcWidth * $scale);
        $destHeight = (int) round($srcHeight * $scale);
        $destX = (int) round(($canvasSize - $destWidth) / 2);
        $destY = (int) round(($canvasSize - $destHeight) / 2);

        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $source, $destX, $destY, 0, 0, $destWidth, $destHeight, $srcWidth, $srcHeight);
        imagedestroy($source);

        $filename = 'tables/' . uniqid('table_', true) . '.webp';
        $fullPath = \Storage::disk('public')->path($filename);
        \Storage::disk('public')->makeDirectory('tables');

        imagewebp($canvas, $fullPath, 85);
        imagedestroy($canvas);

        return $filename;
    }

    // --- FUNCIÓN DE GUARDADO DE MAPA ---
    public function updatePositions(Request $request)
    {
        // Validamos que llegue un array
        $positions = $request->input('positions');

        if (!is_array($positions)) {
            return response()->json(['status' => 'error', 'message' => 'Datos inválidos'], 400);
        }

        foreach($positions as $pos) {
            // Buscamos la mesa y actualizamos
            $table = Table::find($pos['id']);
            if($table) {
                $table->x_pos = (int) $pos['x'];
                $table->y_pos = (int) $pos['y'];
                $table->save();
            }
        }

        return response()->json(['status' => 'success', 'message' => 'Mapa guardado correctamente']);
    }
}