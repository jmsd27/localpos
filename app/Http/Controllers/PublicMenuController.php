<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PublicMenuController extends Controller
{
    public function __invoke(Request $request): View
    {
        $business = Business::query()->firstOrFail();

        $categoryIds = array_filter(array_map(
            'intval',
            explode(',', (string) $request->query('c', ''))
        ));

        $categories = ProductCategory::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->when($categoryIds, fn ($query) => $query->whereIn('id', $categoryIds))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->with(['products' => function ($query) {
                $query->where('is_active', true)
                    ->where('is_sellable', true)
                    ->orderBy('name');
            }])
            ->get()
            ->filter(fn (ProductCategory $category) => $category->products->isNotEmpty());

        $uncategorized = Product::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->where('is_sellable', true)
            ->whereNull('product_category_id')
            ->when($categoryIds, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get();

        return view('menu.show', [
            'business' => $business,
            'categories' => $categories,
            'uncategorized' => $uncategorized,
        ]);
    }
}
