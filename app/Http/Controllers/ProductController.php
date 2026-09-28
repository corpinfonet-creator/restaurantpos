<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\InventoryLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $categoryId = $request->input('category_id');
        $status = $request->input('status');

        $baseQuery = Product::query();

        $products = (clone $baseQuery)
            ->with('category')
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($categoryId, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($status === 'low_stock', fn ($query) => $query->whereNotNull('stock')->where('stock', '<=', 15))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        // KPIs generales (sobre todo el catálogo, sin filtros)
        $totalProducts = Product::count();
        $activeProducts = Product::where('is_active', true)->count();
        $lowStockCount = Product::whereNotNull('stock')->where('stock', '<=', 15)->count();
        $inventoryValue = Product::whereNotNull('stock')->selectRaw('SUM(price * stock) as total')->value('total') ?? 0;

        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $currency = \App\Models\Setting::get('currency_symbol', 'S/');

        return view('products.index', compact(
            'products', 'categories', 'search', 'categoryId', 'status', 'currency',
            'totalProducts', 'activeProducts', 'lowStockCount', 'inventoryValue'
        ));
    }

    public function create(Request $request)
    {
        $categories = Category::where('is_active', true)->get();
        // Pasamos todos los productos activos para que puedan elegirse como insumos
        $allProducts = Product::where('is_active', true)->orderBy('name')->get();
        $currency = \App\Models\Setting::get('currency_symbol', 'S/');

        // Petición AJAX (panel lateral): devolvemos solo el fragmento del formulario
        if ($request->ajax() || $request->wantsJson()) {
            return view('products._create_form', compact('categories', 'allProducts', 'currency'));
        }

        return view('products.create', compact('categories', 'allProducts', 'currency'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'price' => 'required|numeric',
            'category_id' => 'required',
            'image' => 'nullable|image|max:2048'
        ]);

        DB::transaction(function() use ($request) {
            $data = $request->except(['ingredients']);
            
            // Lógica Checkbox: Si viene '1' es true, si no, false.
            $data['is_saleable'] = $request->has('is_saleable') && $request->is_saleable == '1';
            $data['is_active'] = true;

            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('products', 'public');
            }

            $product = Product::create($data);

            // Guardar Stock Inicial si aplica
            if($request->stock > 0) {
                InventoryLog::create([
                    'product_id' => $product->id,
                    'user_id' => Auth::id(),
                    'type' => 'entry',
                    'quantity' => $request->stock,
                    'old_stock' => 0,
                    'new_stock' => $request->stock,
                    'note' => 'Stock Inicial'
                ]);
            }

            // Guardar Receta
            if ($request->has('ingredients')) {
                $ingredientsData = [];
                foreach ($request->ingredients as $ing) {
                    if(isset($ing['id']) && isset($ing['quantity'])) {
                        $ingredientsData[$ing['id']] = ['quantity' => $ing['quantity']];
                    }
                }
                $product->ingredients()->sync($ingredientsData);
            }
        });

        return redirect()->route('products.index')->with('success', 'Producto registrado correctamente.');
    }

    public function edit(Request $request, Product $product)
    {
        $categories = Category::where('is_active', true)->get();
        // Evitamos que un producto se seleccione a sí mismo como ingrediente
        $allProducts = Product::where('id', '!=', $product->id)->where('is_active', true)->orderBy('name')->get();
        $currency = \App\Models\Setting::get('currency_symbol', 'S/');

        // Petición AJAX (panel lateral): devolvemos solo el fragmento del formulario
        if ($request->ajax() || $request->wantsJson()) {
            return view('products._edit_form', compact('product', 'categories', 'allProducts', 'currency'));
        }

        return view('products.edit', compact('product', 'categories', 'allProducts', 'currency'));
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'required',
            'price' => 'required|numeric',
            'category_id' => 'required'
        ]);

        DB::transaction(function() use ($request, $product) {
            $data = $request->except(['image', 'ingredients']);

            // Actualizar estado de venta
            $data['is_saleable'] = $request->has('is_saleable') && $request->is_saleable == '1';

            if ($request->hasFile('image')) {
                if ($product->image) Storage::disk('public')->delete($product->image);
                $data['image'] = $request->file('image')->store('products', 'public');
            }

            $product->update($data);

            // Actualizar Receta
            if ($request->has('ingredients')) {
                $ingredientsData = [];
                foreach ($request->ingredients as $ing) {
                    if(isset($ing['id']) && isset($ing['quantity'])) {
                        $ingredientsData[$ing['id']] = ['quantity' => $ing['quantity']];
                    }
                }
                $product->ingredients()->sync($ingredientsData);
            } else {
                $product->ingredients()->detach();
            }
        });

        return redirect()->route('products.index')->with('success', 'Producto actualizado.');
    }

    // Ajuste de Inventario (Kardex)
    public function adjustStock(Request $request, Product $product)
    {
        $request->validate([
            'quantity' => 'required|numeric|min:0.01',
            'type' => 'required|in:add,subtract',
            'note' => 'nullable|string'
        ]);

        DB::transaction(function() use ($request, $product) {
            $oldStock = $product->stock ?? 0;
            $qty = $request->quantity;
            
            if ($request->type == 'add') {
                $newStock = $oldStock + $qty;
                $logType = 'entry';
                $note = $request->note ?? 'Reposición manual';
                $logQty = $qty;
            } else {
                $newStock = max(0, $oldStock - $qty);
                $logType = 'loss';
                $note = $request->note ?? 'Ajuste / Merma';
                $logQty = -$qty;
            }

            $product->update(['stock' => $newStock]);

            InventoryLog::create([
                'product_id' => $product->id,
                'user_id' => Auth::id(),
                'type' => $logType,
                'quantity' => $logQty,
                'old_stock' => $oldStock,
                'new_stock' => $newStock,
                'note' => $note
            ]);
        });

        return redirect()->back()->with('success', 'Stock ajustado.');
    }

    public function toggleStatus(Product $product)
    {
        $product->is_active = !$product->is_active;
        $product->save();
        return redirect()->back()->with('success', 'Estado actualizado.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->back()->with('success', 'Producto eliminado.');
    }
}