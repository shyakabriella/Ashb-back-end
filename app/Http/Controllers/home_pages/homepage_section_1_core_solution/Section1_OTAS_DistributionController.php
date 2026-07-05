<?php

namespace App\Http\Controllers\home_pages\homepage_section_1_core_solution;

use App\Http\Controllers\Controller;
use App\Models\home_pages\homepage_section_1_core_solution\Section1_OTAS_Distribution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class Section1_OTAS_DistributionController extends Controller
{
    public function index()
    {
        $data = Section1_OTAS_Distribution::all();
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function show($id)
    {
        $data = Section1_OTAS_Distribution::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'title' => 'required|string',
            'subtitle' => 'required|string',
            'description' => 'required|string',
        ];

        // Check if image is provided as file OR URL
        if ($request->hasFile('icon_image')) {
            $rules['icon_image'] = 'required|image|mimes:png,jpg,jpeg,svg|max:2048';
        } elseif ($request->has('icon_image_url')) {
            $rules['icon_image_url'] = 'required|url';
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either icon_image (file) or icon_image_url is required'
            ], 422);
        }

        $request->validate($rules);

        $iconImageUrl = null;
        
        if ($request->hasFile('icon_image')) {
            $path = $request->file('icon_image')->store('core-solutions', 'public');
            $iconImageUrl = Storage::url($path);
        } elseif ($request->has('icon_image_url')) {
            $iconImageUrl = $request->icon_image_url;
        }

        $data = Section1_OTAS_Distribution::create([
            'icon_image' => $iconImageUrl,
            'title' => $request->title,
            'subtitle' => $request->subtitle,
            'description' => $request->description
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Created successfully',
            'data' => $data
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $data = Section1_OTAS_Distribution::findOrFail($id);

        $rules = [
            'title' => 'sometimes|string',
            'subtitle' => 'sometimes|string',
            'description' => 'sometimes|string',
        ];

        if ($request->hasFile('icon_image')) {
            $rules['icon_image'] = 'sometimes|image|mimes:png,jpg,jpeg,svg|max:2048';
        } elseif ($request->has('icon_image_url')) {
            $rules['icon_image_url'] = 'sometimes|url';
        }

        $request->validate($rules);

        if ($request->hasFile('icon_image')) {
            if ($data->icon_image && !filter_var($data->icon_image, FILTER_VALIDATE_URL)) {
                $oldPath = str_replace('/storage/', '', $data->icon_image);
                Storage::disk('public')->delete($oldPath);
            }
            
            $path = $request->file('icon_image')->store('core-solutions', 'public');
            $iconImageUrl = Storage::url($path);
            $data->icon_image = $iconImageUrl;
        } elseif ($request->has('icon_image_url')) {
            if ($data->icon_image && !filter_var($data->icon_image, FILTER_VALIDATE_URL)) {
                $oldPath = str_replace('/storage/', '', $data->icon_image);
                Storage::disk('public')->delete($oldPath);
            }
            $data->icon_image = $request->icon_image_url;
        }

        $data->fill($request->only(['title', 'subtitle', 'description']));
        $data->save();

        return response()->json([
            'success' => true,
            'message' => 'Updated successfully',
            'data' => $data
        ]);
    }

    public function destroy($id)
    {
        $data = Section1_OTAS_Distribution::findOrFail($id);
        
        if ($data->icon_image && !filter_var($data->icon_image, FILTER_VALIDATE_URL)) {
            $oldPath = str_replace('/storage/', '', $data->icon_image);
            Storage::disk('public')->delete($oldPath);
        }
        
        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted successfully'
        ]);
    }
}