<?php

namespace App\Http\Controllers\Api\home_pages;

use App\Http\Controllers\Controller;
use App\Models\home_pages\Section3;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class Section3Controller extends Controller
{
    // GET section data (PUBLIC)
    public function index()
    {
        try {
            $section = Section3::first();
            
            if (!$section) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'left_title' => 'Everything hotels need to sell more rooms',
                        'left_description' => 'Website + booking engine + payments + SEO + OTA distribution + channel manager + PMS + marketing + local support — one connected system. Built for bookings',
                        'left_image_url' => null,
                        'right_medium_image_url' => null,
                        'right_items' => [
                            ['id' => 1, 'text' => 'Turn Your Website Into a 24/7 Booking Machine (Payments + Confirmations).', 'image_url' => null],
                            ['id' => 2, 'text' => 'PMS Made Simple: Check-in/out, Calendar, Invoicing & Reports.', 'image_url' => null],
                            ['id' => 3, 'text' => 'Digital Marketing + Reviews: Grow Trust & Increase Bookings.', 'image_url' => null],
                        ],
                    ]
                ]);
            }

            // Decode right_items if it's a string
            $rightItems = $section->right_items;
            if (is_string($rightItems)) {
                $rightItems = json_decode($rightItems, true);
            }
            
            // Ensure each item has an id
            if ($rightItems && is_array($rightItems)) {
                foreach ($rightItems as $index => &$item) {
                    if (!isset($item['id'])) {
                        $item['id'] = $index + 1;
                    }
                    // Make sure image_url is properly formatted
                    if (isset($item['image_url']) && $item['image_url'] && !str_starts_with($item['image_url'], 'http')) {
                        $item['image_url'] = asset($item['image_url']);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $section->id,
                    'left_title' => $section->left_title,
                    'left_description' => $section->left_description,
                    'left_image_url' => $section->left_image_url ? asset($section->left_image_url) : null,
                    'right_medium_image_url' => $section->right_medium_image_url ? asset($section->right_medium_image_url) : null,
                    'right_items' => $rightItems ?: [],
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Section3 index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // UPDATE section data (PROTECTED)
    public function update(Request $request)
    {
        try {
            $section = Section3::first();
            if (!$section) {
                $section = new Section3();
            }

            // Update text content
            if ($request->has('left_title')) {
                $section->left_title = $request->left_title;
            }
            if ($request->has('left_description')) {
                $section->left_description = $request->left_description;
            }

            // Handle Left Big Image Upload
            if ($request->hasFile('left_image')) {
                $file = $request->file('left_image');
                $folder = 'home_pages/section3';
                $filename = 'left_image_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs($folder, $filename, 'public');
                
                if ($section->left_image_url) {
                    $oldPath = str_replace('/storage/', '', $section->left_image_url);
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }
                $section->left_image_url = '/storage/' . $path;
            }

            // Handle Right Medium Image Upload
            if ($request->hasFile('right_medium_image')) {
                $file = $request->file('right_medium_image');
                $folder = 'home_pages/section3';
                $filename = 'right_medium_image_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs($folder, $filename, 'public');
                
                if ($section->right_medium_image_url) {
                    $oldPath = str_replace('/storage/', '', $section->right_medium_image_url);
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }
                $section->right_medium_image_url = '/storage/' . $path;
            }

            // Handle Right Items
            if ($request->has('right_items')) {
                $items = json_decode($request->right_items, true);
                $existingItems = [];
                
                // Get existing items from database
                $existingItemsData = $section->right_items;
                if (is_string($existingItemsData)) {
                    $existingItemsData = json_decode($existingItemsData, true);
                }
                
                // Create a map of existing items by id
                if ($existingItemsData && is_array($existingItemsData)) {
                    foreach ($existingItemsData as $existingItem) {
                        if (isset($existingItem['id'])) {
                            $existingItems[$existingItem['id']] = $existingItem;
                        }
                    }
                }
                
                // Process each incoming item
                foreach ($items as $index => $item) {
                    $itemId = $item['id'];
                    
                    // Check for item image upload
                    $imageKey = "item_image_" . ($index + 1);
                    if ($request->hasFile($imageKey)) {
                        $file = $request->file($imageKey);
                        $folder = 'home_pages/section3/items';
                        $filename = 'item_' . $itemId . '_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
                        $path = $file->storeAs($folder, $filename, 'public');
                        $item['image_url'] = '/storage/' . $path;
                        
                        // Delete old image if exists
                        if (isset($existingItems[$itemId]['image_url']) && $existingItems[$itemId]['image_url']) {
                            $oldPath = str_replace('/storage/', '', $existingItems[$itemId]['image_url']);
                            if (Storage::disk('public')->exists($oldPath)) {
                                Storage::disk('public')->delete($oldPath);
                            }
                        }
                    } elseif (isset($existingItems[$itemId]['image_url'])) {
                        // Keep existing image
                        $item['image_url'] = $existingItems[$itemId]['image_url'];
                    } else {
                        $item['image_url'] = null;
                    }
                    
                    $items[$index] = $item;
                }
                
                $section->right_items = json_encode($items);
            }

            $section->save();

            // Prepare response with full URLs
            $responseData = [
                'id' => $section->id,
                'left_title' => $section->left_title,
                'left_description' => $section->left_description,
                'left_image_url' => $section->left_image_url ? asset($section->left_image_url) : null,
                'right_medium_image_url' => $section->right_medium_image_url ? asset($section->right_medium_image_url) : null,
                'right_items' => [],
            ];
            
            $rightItems = $section->right_items;
            if (is_string($rightItems)) {
                $rightItems = json_decode($rightItems, true);
            }
            
            if ($rightItems && is_array($rightItems)) {
                foreach ($rightItems as $item) {
                    $responseData['right_items'][] = [
                        'id' => $item['id'],
                        'text' => $item['text'],
                        'image_url' => isset($item['image_url']) && $item['image_url'] ? asset($item['image_url']) : null,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Section 3 updated successfully',
                'data' => $responseData
            ]);
        } catch (\Exception $e) {
            Log::error('Section3 update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy()
    {
        try {
            $section = Section3::first();
            if ($section) {
                if ($section->left_image_url) {
                    $path = str_replace('/storage/', '', $section->left_image_url);
                    if (Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }
                if ($section->right_medium_image_url) {
                    $path = str_replace('/storage/', '', $section->right_medium_image_url);
                    if (Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }
                
                $items = $section->right_items ? json_decode($section->right_items, true) : [];
                foreach ($items as $item) {
                    if (isset($item['image_url']) && $item['image_url']) {
                        $path = str_replace('/storage/', '', $item['image_url']);
                        if (Storage::disk('public')->exists($path)) {
                            Storage::disk('public')->delete($path);
                        }
                    }
                }
                
                $section->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Section 3 reset successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}