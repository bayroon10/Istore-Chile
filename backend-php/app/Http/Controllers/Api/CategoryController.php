<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Category::query();

        if ($request->has('with_count')) {
            $query->withCount('products');
        }

        $categories = $query->latest()->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:categories,slug',
            'icon' => 'nullable|string',
        ]);

        $data = $request->all();
        if (!$request->has('slug')) {
            $data['slug'] = \Illuminate\Support\Str::slug($request->name);
        }

        $category = Category::create($data);

        return new CategoryResource($category);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|unique:categories,slug,' . $category->id,
        ]);

        $category->update($request->all());

        return new CategoryResource($category);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        // Deberíamos validar si tiene productos asociados antes de borrar
        if ($category->products()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with associated products'
            ], 400);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }
}
