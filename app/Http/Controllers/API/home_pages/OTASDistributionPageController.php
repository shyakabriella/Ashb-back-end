<?php

namespace App\Http\Controllers\Api\home_pages;

use App\Http\Controllers\Controller;
use App\Models\home_pages\OTASDistributionPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class OTASDistributionPageController extends Controller
{
    private function getOrCreatePage()
    {
        $page = OTASDistributionPage::first();
        if (!$page) {
            $page = OTASDistributionPage::create([
                'hero_title' => 'GLOBAL REACH PLATFORM',
                'hero_subtitle' => 'Online visibility on 450+ OTAs',
                'hero_description' => 'Distribute your inventory globally on Booking.com, Expedia, Airbnb, and more. Reach more guests with zero effort through our seamless channel management.',
                'hero_button1_text' => 'Start Free Trial',
                'hero_button2_text' => 'View Channels',
                'platforms_section_title' => 'Reach More Guests Globally',
                'platforms' => [
                    ['id' => 1, 'name' => 'Booking.com', 'description' => 'Global Reach', 'image' => null],
                    ['id' => 2, 'name' => 'Expedia', 'description' => 'Massive Traffic', 'image' => null],
                    ['id' => 3, 'name' => 'Airbnb', 'description' => 'Vacation Rentals', 'image' => null],
                    ['id' => 4, 'name' => 'Agoda', 'description' => 'Asia Specialist', 'image' => null],
                ],
                'why_choose_section_title' => 'Why Choose Our Channel Manager?',
                'why_choose_section_description' => 'Simplify your operations and maximize your revenue with automated sync across the world\'s leading platforms.',
                'why_choose_items' => [
                    ['id' => 1, 'title' => 'Increased Occupancy', 'description' => 'List on 450+ channels to ensure your rooms are always visible to potential guests, filling vacancies faster than ever.', 'icon' => 'Globe'],
                    ['id' => 2, 'title' => 'Instant Sync', 'description' => 'Updates prices and availability across all platforms in real-time. When a booking is made, all other sites update instantly.', 'icon' => 'Zap'],
                    ['id' => 3, 'title' => 'No Overbookings', 'description' => 'Our smart sync engine prevents double bookings automatically, giving you peace of mind and protecting your reputation.', 'icon' => 'Shield'],
                ],
                'cta_title' => 'Ready to boost your hotel\'s visibility?',
                'cta_description' => 'Join 5,000+ properties managing their distribution more efficiently today.',
                'cta_button_text' => 'Get Started Now',
            ]);
        }
        return $page;
    }

    // ========== HERO SECTION CRUD ==========
    
    public function getHero()
    {
        $page = $this->getOrCreatePage();
        
        return response()->json([
            'success' => true,
            'data' => [
                'title' => $page->hero_title,
                'subtitle' => $page->hero_subtitle,
                'description' => $page->hero_description,
                'button1_text' => $page->hero_button1_text,
                'button2_text' => $page->hero_button2_text,
                'image_url' => $page->hero_image ? asset($page->hero_image) : null,
            ]
        ]);
    }

    public function updateHero(Request $request)
    {
        try {
            $page = $this->getOrCreatePage();
            
            if ($request->has('title')) $page->hero_title = $request->title;
            if ($request->has('subtitle')) $page->hero_subtitle = $request->subtitle;
            if ($request->has('description')) $page->hero_description = $request->description;
            if ($request->has('button1_text')) $page->hero_button1_text = $request->button1_text;
            if ($request->has('button2_text')) $page->hero_button2_text = $request->button2_text;
            
            if ($request->hasFile('image')) {
                if ($page->hero_image) {
                    $oldPath = str_replace('/storage/', '', $page->hero_image);
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }
                $file = $request->file('image');
                $filename = 'hero_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('otas/hero', $filename, 'public');
                $page->hero_image = '/storage/' . $path;
            }
            
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Hero section updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteHero()
    {
        try {
            $page = $this->getOrCreatePage();
            
            if ($page->hero_image) {
                $oldPath = str_replace('/storage/', '', $page->hero_image);
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
            
            $page->hero_title = null;
            $page->hero_subtitle = null;
            $page->hero_description = null;
            $page->hero_button1_text = null;
            $page->hero_button2_text = null;
            $page->hero_image = null;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Hero section deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ========== PLATFORMS SECTION CRUD ==========
    
    public function getPlatforms()
    {
        $page = $this->getOrCreatePage();
        $platforms = $page->platforms ?? [];
        
        foreach ($platforms as &$platform) {
            if (isset($platform['image']) && $platform['image']) {
                $platform['image'] = asset($platform['image']);
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'section_title' => $page->platforms_section_title,
                'platforms' => $platforms,
            ]
        ]);
    }

    public function updatePlatform(Request $request, $id)
    {
        try {
            $page = $this->getOrCreatePage();
            $platforms = $page->platforms ?? [];
            
            foreach ($platforms as &$platform) {
                if ($platform['id'] == $id) {
                    if ($request->has('name')) $platform['name'] = $request->name;
                    if ($request->has('description')) $platform['description'] = $request->description;
                    
                    if ($request->hasFile('image')) {
                        if (isset($platform['image']) && $platform['image']) {
                            $oldPath = str_replace('/storage/', '', $platform['image']);
                            if (Storage::disk('public')->exists($oldPath)) {
                                Storage::disk('public')->delete($oldPath);
                            }
                        }
                        $file = $request->file('image');
                        $filename = 'platform_' . $id . '_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
                        $path = $file->storeAs('otas/platforms', $filename, 'public');
                        $platform['image'] = '/storage/' . $path;
                    }
                    break;
                }
            }
            
            $page->platforms = $platforms;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Platform updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deletePlatform($id)
    {
        try {
            $page = $this->getOrCreatePage();
            $platforms = $page->platforms ?? [];
            
            foreach ($platforms as $key => $platform) {
                if ($platform['id'] == $id) {
                    if (isset($platform['image']) && $platform['image']) {
                        $oldPath = str_replace('/storage/', '', $platform['image']);
                        if (Storage::disk('public')->exists($oldPath)) {
                            Storage::disk('public')->delete($oldPath);
                        }
                    }
                    unset($platforms[$key]);
                    break;
                }
            }
            
            $page->platforms = array_values($platforms);
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Platform deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updatePlatformsSectionTitle(Request $request)
    {
        $page = $this->getOrCreatePage();
        $page->platforms_section_title = $request->section_title;
        $page->save();
        
        return response()->json(['success' => true, 'message' => 'Section title updated']);
    }

    // ========== WHY CHOOSE US SECTION CRUD ==========
    
    public function getWhyChoose()
    {
        $page = $this->getOrCreatePage();
        $items = $page->why_choose_items ?? [];
        
        return response()->json([
            'success' => true,
            'data' => [
                'section_title' => $page->why_choose_section_title,
                'section_description' => $page->why_choose_section_description,
                'items' => $items,
            ]
        ]);
    }

    public function updateWhyChooseItem(Request $request, $id)
    {
        try {
            $page = $this->getOrCreatePage();
            $items = $page->why_choose_items ?? [];
            
            foreach ($items as &$item) {
                if ($item['id'] == $id) {
                    if ($request->has('title')) $item['title'] = $request->title;
                    if ($request->has('description')) $item['description'] = $request->description;
                    if ($request->has('icon')) $item['icon'] = $request->icon;
                    break;
                }
            }
            
            $page->why_choose_items = $items;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Item updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteWhyChooseItem($id)
    {
        try {
            $page = $this->getOrCreatePage();
            $items = $page->why_choose_items ?? [];
            
            foreach ($items as $key => $item) {
                if ($item['id'] == $id) {
                    unset($items[$key]);
                    break;
                }
            }
            
            $page->why_choose_items = array_values($items);
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Item deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateWhyChooseSection(Request $request)
    {
        $page = $this->getOrCreatePage();
        if ($request->has('section_title')) $page->why_choose_section_title = $request->section_title;
        if ($request->has('section_description')) $page->why_choose_section_description = $request->section_description;
        $page->save();
        
        return response()->json(['success' => true, 'message' => 'Section updated']);
    }

    // ========== CTA BANNER CRUD ==========
    
    public function getCta()
    {
        $page = $this->getOrCreatePage();
        
        return response()->json([
            'success' => true,
            'data' => [
                'title' => $page->cta_title,
                'description' => $page->cta_description,
                'button_text' => $page->cta_button_text,
            ]
        ]);
    }

    public function updateCta(Request $request)
    {
        try {
            $page = $this->getOrCreatePage();
            
            if ($request->has('title')) $page->cta_title = $request->title;
            if ($request->has('description')) $page->cta_description = $request->description;
            if ($request->has('button_text')) $page->cta_button_text = $request->button_text;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'CTA banner updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteCta()
    {
        try {
            $page = $this->getOrCreatePage();
            
            $page->cta_title = null;
            $page->cta_description = null;
            $page->cta_button_text = null;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'CTA banner deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}