<?php

namespace App\Http\Controllers\Api\home_pages;

use App\Http\Controllers\Controller;
use App\Models\home_pages\HeroSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HeroSectionController extends Controller
{
    public function index()
    {
        $hero = HeroSection::first();
        
        return response()->json([
            'success' => true,
            'data' => $hero
        ]);
    }

    public function store(Request $request)
    {
        try {
            $data = [
                'title' => $request->title,
                'subtitle' => $request->subtitle,
                'description' => $request->description,
                'button_text' => $request->button_text,
            ];

            if ($request->hasFile('media')) {
                $file = $request->file('media');
                $isVideo = str_contains($file->getMimeType(), 'video/');
                $folder = 'home_pages/hero/' . ($isVideo ? 'videos' : 'images');
                
                $filename = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) 
                            . '.' . $file->getClientOriginalExtension();
                
                $path = $file->storeAs($folder, $filename, 'public');
                $data['media_url'] = '/storage/' . $path;
            }

            $hero = HeroSection::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Hero section created',
                'data' => $hero
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $hero = HeroSection::findOrFail($id);

            $data = [
                'title' => $request->title,
                'subtitle' => $request->subtitle,
                'description' => $request->description,
                'button_text' => $request->button_text,
            ];

            if ($request->hasFile('media')) {
                // Delete old file
                if ($hero->media_url) {
                    $oldPath = str_replace('/storage/', '', $hero->media_url);
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }
                
                $file = $request->file('media');
                $isVideo = str_contains($file->getMimeType(), 'video/');
                $folder = 'home_pages/hero/' . ($isVideo ? 'videos' : 'images');
                
                $filename = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) 
                            . '.' . $file->getClientOriginalExtension();
                
                $path = $file->storeAs($folder, $filename, 'public');
                $data['media_url'] = '/storage/' . $path;
            }

            $hero->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Hero section updated',
                'data' => $hero
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}