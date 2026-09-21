<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;

class CategoryController
{
    use ApiResponse, AuthorizesRequests;

    public function index()
    {
        $categories = Category::orderBy('name')->get();

        return $this->success(CategoryResource::collection($categories));
    }

    public function store(StoreCategoryRequest $request)
    {
        if (Category::where('name', $request->validated()['name'])->exists()) {
            throw new ApiException('DUPLICATE_CATEGORY', 'يوجد تصنيف بنفس الاسم مسبقاً', 409);
        }

        $category = Category::create([
            'id' => (string) Str::uuid(),
            ...$request->validated(),
        ]);

        return $this->success(new CategoryResource($category), 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $this->authorize('update', $category);

        $data = $request->validated();

        if (array_key_exists('name', $data)) {
            $duplicate = Category::where('name', $data['name'])
                ->whereKeyNot($category->id)
                ->exists();

            if ($duplicate) {
                throw new ApiException('DUPLICATE_CATEGORY', 'يوجد تصنيف بنفس الاسم مسبقاً', 409);
            }
        }

        $category->update($data);

        return $this->success(new CategoryResource($category));
    }

    public function destroy(Category $category)
    {
        $this->authorize('delete', $category);
        // فصل المهام المرتبطة بدل رفض الحذف
        $category->tasks()->update(['category_id' => null]);
        $category->delete();

        return response()->json(null, 204);
    }
}
