<?php

namespace App\Http\Controllers\Api\home_pages;

use App\Http\Controllers\Controller;
use App\Models\home_pages\ChannelManagerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChannelManagerPageController extends Controller
{
    private function getOrCreatePage()
    {
        $page = ChannelManagerPage::first();
        if (!$page) {
            $page = ChannelManagerPage::create([
                // Hero Section
                'hero_title' => 'Real-time Channel Manager',
                'hero_subtitle' => 'Sync Rates & Availability to Stop Overbookings',
                'hero_description' => 'Professional-grade synchronization across 450+ booking channels including Booking.com, Expedia, Agoda, and TripAdvisor. Eliminate manual entry errors and protect your hotels reputation instantly.',
                'hero_button_text' => 'Reach Out To Us',
                
                // Dashboard Stats
                'total_bookings' => '12,847',
                'total_bookings_percentage' => '+23%',
                'revenue' => '$2.4M',
                'revenue_percentage' => '+18%',
                'ota_status' => [
                    ['name' => 'Booking.com', 'status' => 'Synced', 'time' => '2s ago'],
                    ['name' => 'Expedia', 'status' => 'Synced', 'time' => '5s ago'],
                    ['name' => 'Agoda', 'status' => 'Synced', 'time' => '3s ago'],
                    ['name' => 'Direct Website', 'status' => 'Synced', 'time' => '1s ago'],
                ],
                'trust_count' => '15,000+',
                'trust_text' => 'hotels trust our sync engine',
                
                // Section 1: Sync Cards
                'sync_cards' => [
                    ['id' => 1, 'value' => '< 2 Seconds', 'title' => 'Average Sync Speed', 'description' => 'Real-time availability updates across all connected OTAs'],
                    ['id' => 2, 'value' => '100%', 'title' => 'Error Reduction', 'description' => 'Guaranteed accuracy with zero double bookings'],
                    ['id' => 3, 'value' => '450+', 'title' => 'Global Channels', 'description' => 'Integrated OTAs including Booking.com, Expedia, Agoda'],
                ],
                
                // Section 2: Zero Errors
                'zero_errors_title' => 'ZERO ERRORS GUARANTEED',
                'zero_errors_subtitle' => 'Eliminate Overbookings Forever',
                'zero_errors_description' => 'Join 15,000+ hoteliers who trust our Sync engine to manage their properties with 100% accuracy',
                'zero_errors_cards' => [
                    ['id' => 1, 'title' => 'Zero Double Bookings', 'description' => 'Our intelligent sync engine prevents overlapping reservations. When a room sells, availability is locked across all channels instantly.', 'icon' => '🔒'],
                    ['id' => 2, 'title' => 'Real-time Sync', 'description' => 'Changes reflect across all connected OTAs in under 2 seconds. No delays, no manual updates, no errors.', 'icon' => '⚡'],
                    ['id' => 3, 'title' => '450+ Global Channels', 'description' => 'Connect to Booking.com, Expedia, Agoda, TripAdvisor, and hundreds more with a single click.', 'icon' => '🌐'],
                ],
                
                // Section 3: Stats
                'stats_items' => [
                    ['id' => 1, 'value' => '0', 'label' => 'Overbookings reported', 'suffix' => ''],
                    ['id' => 2, 'value' => '99.99', 'label' => 'Uptime guaranteed', 'suffix' => '%'],
                    ['id' => 3, 'value' => '24/7', 'label' => 'Monitoring & Support', 'suffix' => ''],
                    ['id' => 4, 'value' => '15,000+', 'label' => 'Happy hoteliers', 'suffix' => ''],
                ],
                
                // Section 4: Sync Engine Works
                'sync_engine_title' => 'The Sync Engine',
                'sync_engine_subtitle' => 'Real-time data flow between your PMS and 450+ OTAs',
                'sync_engine_description' => 'Live connection active',
                'sync_engine_steps' => [
                    ['id' => 1, 'title' => 'Centralize Your Data', 'description' => 'Upload your rooms, descriptions, base rates, and availability into the ChannelSync dashboard. One source of truth for all your inventory across all platforms.'],
                    ['id' => 2, 'title' => 'Connect Your OTAs', 'description' => 'Map your rooms to Expedia, Booking.com, Agoda, and 450+ more channels with a single click. No technical skills needed. Our team helps with setup.'],
                    ['id' => 3, 'title' => 'Automate the Flow', 'description' => 'The Sync engine monitors bookings 24/7. When a room sells, availability is instantly updated across ALL connected channels. No manual work. No double bookings. Ever.'],
                ],
                
                // Footer CTA
                'footer_title' => 'Ready to say goodbye to overbookings forever?',
                'footer_description' => 'Join 15,000+ hoteliers who trust our Sync engine to manage their properties. Start your 14-day free trial today.',
                'footer_button_text' => 'Start Free Trial',
                'footer_icon' => '🔄',
            ]);
        }
        return $page;
    }

    // ========== GET ALL DATA (PUBLIC) ==========
    public function index()
    {
        $page = $this->getOrCreatePage();
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $page->id,
                'hero_title' => $page->hero_title,
                'hero_subtitle' => $page->hero_subtitle,
                'hero_description' => $page->hero_description,
                'hero_button_text' => $page->hero_button_text,
                
                'total_bookings' => $page->total_bookings,
                'total_bookings_percentage' => $page->total_bookings_percentage,
                'revenue' => $page->revenue,
                'revenue_percentage' => $page->revenue_percentage,
                'ota_status' => $page->ota_status,
                'trust_count' => $page->trust_count,
                'trust_text' => $page->trust_text,
                
                'sync_cards' => $page->sync_cards,
                
                'zero_errors_title' => $page->zero_errors_title,
                'zero_errors_subtitle' => $page->zero_errors_subtitle,
                'zero_errors_description' => $page->zero_errors_description,
                'zero_errors_cards' => $page->zero_errors_cards,
                
                'stats_items' => $page->stats_items,
                
                'sync_engine_title' => $page->sync_engine_title,
                'sync_engine_subtitle' => $page->sync_engine_subtitle,
                'sync_engine_description' => $page->sync_engine_description,
                'sync_engine_steps' => $page->sync_engine_steps,
                
                'footer_title' => $page->footer_title,
                'footer_description' => $page->footer_description,
                'footer_button_text' => $page->footer_button_text,
                'footer_icon' => $page->footer_icon ?? '🔄',
                
                'is_active' => $page->is_active,
                'created_at' => $page->created_at,
                'updated_at' => $page->updated_at,
            ]
        ]);
    }

    // ========== HERO SECTION - FULL CRUD ==========
    
    public function getHero()
    {
        $page = $this->getOrCreatePage();
        
        return response()->json([
            'success' => true,
            'data' => [
                'title' => $page->hero_title,
                'subtitle' => $page->hero_subtitle,
                'description' => $page->hero_description,
                'button_text' => $page->hero_button_text,
            ]
        ]);
    }

    public function createHero(Request $request)
    {
        try {
            $page = $this->getOrCreatePage();
            
            $page->hero_title = $request->title;
            $page->hero_subtitle = $request->subtitle;
            $page->hero_description = $request->description;
            $page->hero_button_text = $request->button_text;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Hero section created', 'data' => $page], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateHero(Request $request)
    {
        try {
            $page = $this->getOrCreatePage();
            
            if ($request->has('title')) $page->hero_title = $request->title;
            if ($request->has('subtitle')) $page->hero_subtitle = $request->subtitle;
            if ($request->has('description')) $page->hero_description = $request->description;
            if ($request->has('button_text')) $page->hero_button_text = $request->button_text;
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
            
            $page->hero_title = null;
            $page->hero_subtitle = null;
            $page->hero_description = null;
            $page->hero_button_text = null;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Hero section deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ========== DASHBOARD STATS - FULL CRUD ==========
    
    public function getDashboardStats()
    {
        $page = $this->getOrCreatePage();
        
        return response()->json([
            'success' => true,
            'data' => [
                'total_bookings' => $page->total_bookings,
                'total_bookings_percentage' => $page->total_bookings_percentage,
                'revenue' => $page->revenue,
                'revenue_percentage' => $page->revenue_percentage,
                'ota_status' => $page->ota_status,
                'trust_count' => $page->trust_count,
                'trust_text' => $page->trust_text,
            ]
        ]);
    }

    public function createDashboardStats(Request $request)
    {
        try {
            $page = $this->getOrCreatePage();
            
            $page->total_bookings = $request->total_bookings;
            $page->total_bookings_percentage = $request->total_bookings_percentage;
            $page->revenue = $request->revenue;
            $page->revenue_percentage = $request->revenue_percentage;
            $page->ota_status = $request->ota_status;
            $page->trust_count = $request->trust_count;
            $page->trust_text = $request->trust_text;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Dashboard stats created'], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateDashboardStats(Request $request)
    {
        try {
            $page = $this->getOrCreatePage();
            
            if ($request->has('total_bookings')) $page->total_bookings = $request->total_bookings;
            if ($request->has('total_bookings_percentage')) $page->total_bookings_percentage = $request->total_bookings_percentage;
            if ($request->has('revenue')) $page->revenue = $request->revenue;
            if ($request->has('revenue_percentage')) $page->revenue_percentage = $request->revenue_percentage;
            if ($request->has('ota_status')) $page->ota_status = $request->ota_status;
            if ($request->has('trust_count')) $page->trust_count = $request->trust_count;
            if ($request->has('trust_text')) $page->trust_text = $request->trust_text;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Dashboard stats updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteDashboardStats()
    {
        try {
            $page = $this->getOrCreatePage();
            
            $page->total_bookings = null;
            $page->total_bookings_percentage = null;
            $page->revenue = null;
            $page->revenue_percentage = null;
            $page->ota_status = null;
            $page->trust_count = null;
            $page->trust_text = null;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Dashboard stats deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ========== SYNC CARDS - FULL CRUD ==========
    
    public function getSyncCards()
    {
        $page = $this->getOrCreatePage();
        
        return response()->json([
            'success' => true,
            'data' => $page->sync_cards
        ]);
    }

    public function createSyncCard(Request $request)
    {
        try {
            $page = $this->getOrCreatePage();
            $cards = $page->sync_cards ?? [];
            
            $newId = count($cards) > 0 ? max(array_column($cards, 'id')) + 1 : 1;
            
            $newCard = [
                'id' => $newId,
                'value' => $request->value,
                'title' => $request->title,
                'description' => $request->description,
            ];
            
            $cards[] = $newCard;
            $page->sync_cards = $cards;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Sync card created', 'data' => $newCard], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateSyncCard(Request $request, $id)
    {
        try {
            $page = $this->getOrCreatePage();
            $cards = $page->sync_cards ?? [];
            
            foreach ($cards as &$card) {
                if ($card['id'] == $id) {
                    if ($request->has('value')) $card['value'] = $request->value;
                    if ($request->has('title')) $card['title'] = $request->title;
                    if ($request->has('description')) $card['description'] = $request->description;
                    break;
                }
            }
            
            $page->sync_cards = $cards;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Sync card updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteSyncCard($id)
    {
        try {
            $page = $this->getOrCreatePage();
            $cards = $page->sync_cards ?? [];
            
            foreach ($cards as $key => $card) {
                if ($card['id'] == $id) {
                    unset($cards[$key]);
                    break;
                }
            }
            
            $page->sync_cards = array_values($cards);
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Sync card deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateSyncCards(Request $request)
    {
        try {
            $page = $this->getOrCreatePage();
            
            if ($request->has('sync_cards')) {
                $page->sync_cards = $request->sync_cards;
                $page->save();
            }
            
            return response()->json(['success' => true, 'message' => 'Sync cards updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ========== ZERO ERRORS SECTION - FULL CRUD ==========
    
    public function getZeroErrors()
    {
        $page = $this->getOrCreatePage();
        
        return response()->json([
            'success' => true,
            'data' => [
                'title' => $page->zero_errors_title,
                'subtitle' => $page->zero_errors_subtitle,
                'description' => $page->zero_errors_description,
                'cards' => $page->zero_errors_cards,
            ]
        ]);
    }

    public function createZeroErrorsCard(Request $request)
    {
        try {
            $page = $this->getOrCreatePage();
            $cards = $page->zero_errors_cards ?? [];
            
            $newId = count($cards) > 0 ? max(array_column($cards, 'id')) + 1 : 1;
            
            $newCard = [
                'id' => $newId,
                'title' => $request->title,
                'description' => $request->description,
                'icon' => $request->icon ?? '🔒',
            ];
            
            $cards[] = $newCard;
            $page->zero_errors_cards = $cards;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Zero errors card created', 'data' => $newCard], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateZeroErrorsCard(Request $request, $id)
    {
        try {
            $page = $this->getOrCreatePage();
            $cards = $page->zero_errors_cards ?? [];
            
            foreach ($cards as &$card) {
                if ($card['id'] == $id) {
                    if ($request->has('title')) $card['title'] = $request->title;
                    if ($request->has('description')) $card['description'] = $request->description;
                    if ($request->has('icon')) $card['icon'] = $request->icon;
                    break;
                }
            }
            
            $page->zero_errors_cards = $cards;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Zero errors card updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteZeroErrorsCard($id)
    {
        try {
            $page = $this->getOrCreatePage();
            $cards = $page->zero_errors_cards ?? [];
            
            foreach ($cards as $key => $card) {
                if ($card['id'] == $id) {
                    unset($cards[$key]);
                    break;
                }
            }
            
            $page->zero_errors_cards = array_values($cards);
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Zero errors card deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateZeroErrors(Request $request)
    {
        try {
            $page = $this->getOrCreatePage();
            
            if ($request->has('title')) $page->zero_errors_title = $request->title;
            if ($request->has('subtitle')) $page->zero_errors_subtitle = $request->subtitle;
            if ($request->has('description')) $page->zero_errors_description = $request->description;
            if ($request->has('cards')) $page->zero_errors_cards = $request->cards;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Zero errors section updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteZeroErrors()
    {
        try {
            $page = $this->getOrCreatePage();
            
            $page->zero_errors_title = null;
            $page->zero_errors_subtitle = null;
            $page->zero_errors_description = null;
            $page->zero_errors_cards = null;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Zero errors section deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ========== STATS ITEMS - FULL CRUD ==========
    
    public function getStatsItems()
    {
        $page = $this->getOrCreatePage();
        
        return response()->json([
            'success' => true,
            'data' => $page->stats_items
        ]);
    }

    public function createStatsItem(Request $request)
    {
        try {
            $page = $this->getOrCreatePage();
            $items = $page->stats_items ?? [];
            
            $newId = count($items) > 0 ? max(array_column($items, 'id')) + 1 : 1;
            
            $newItem = [
                'id' => $newId,
                'value' => $request->value,
                'label' => $request->label,
                'suffix' => $request->suffix ?? '',
            ];
            
            $items[] = $newItem;
            $page->stats_items = $items;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Stats item created', 'data' => $newItem], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateStatsItem(Request $request, $id)
    {
        try {
            $page = $this->getOrCreatePage();
            $items = $page->stats_items ?? [];
            
            foreach ($items as &$item) {
                if ($item['id'] == $id) {
                    if ($request->has('value')) $item['value'] = $request->value;
                    if ($request->has('label')) $item['label'] = $request->label;
                    if ($request->has('suffix')) $item['suffix'] = $request->suffix;
                    break;
                }
            }
            
            $page->stats_items = $items;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Stats item updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteStatsItem($id)
    {
        try {
            $page = $this->getOrCreatePage();
            $items = $page->stats_items ?? [];
            
            foreach ($items as $key => $item) {
                if ($item['id'] == $id) {
                    unset($items[$key]);
                    break;
                }
            }
            
            $page->stats_items = array_values($items);
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Stats item deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateStatsItems(Request $request)
    {
        try {
            $page = $this->getOrCreatePage();
            
            if ($request->has('stats_items')) {
                $page->stats_items = $request->stats_items;
                $page->save();
            }
            
            return response()->json(['success' => true, 'message' => 'Stats items updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ========== SYNC ENGINE SECTION - FULL CRUD ==========
    
    public function getSyncEngine()
    {
        $page = $this->getOrCreatePage();
        
        return response()->json([
            'success' => true,
            'data' => [
                'title' => $page->sync_engine_title,
                'subtitle' => $page->sync_engine_subtitle,
                'description' => $page->sync_engine_description,
                'steps' => $page->sync_engine_steps,
            ]
        ]);
    }

    public function createSyncEngineStep(Request $request)
    {
        try {
            $page = $this->getOrCreatePage();
            $steps = $page->sync_engine_steps ?? [];
            
            $newId = count($steps) > 0 ? max(array_column($steps, 'id')) + 1 : 1;
            
            $newStep = [
                'id' => $newId,
                'title' => $request->title,
                'description' => $request->description,
            ];
            
            $steps[] = $newStep;
            $page->sync_engine_steps = $steps;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Sync engine step created', 'data' => $newStep], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateSyncEngineStep(Request $request, $id)
    {
        try {
            $page = $this->getOrCreatePage();
            $steps = $page->sync_engine_steps ?? [];
            
            foreach ($steps as &$step) {
                if ($step['id'] == $id) {
                    if ($request->has('title')) $step['title'] = $request->title;
                    if ($request->has('description')) $step['description'] = $request->description;
                    break;
                }
            }
            
            $page->sync_engine_steps = $steps;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Sync engine step updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteSyncEngineStep($id)
    {
        try {
            $page = $this->getOrCreatePage();
            $steps = $page->sync_engine_steps ?? [];
            
            foreach ($steps as $key => $step) {
                if ($step['id'] == $id) {
                    unset($steps[$key]);
                    break;
                }
            }
            
            $page->sync_engine_steps = array_values($steps);
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Sync engine step deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateSyncEngine(Request $request)
    {
        try {
            $page = $this->getOrCreatePage();
            
            if ($request->has('title')) $page->sync_engine_title = $request->title;
            if ($request->has('subtitle')) $page->sync_engine_subtitle = $request->subtitle;
            if ($request->has('description')) $page->sync_engine_description = $request->description;
            if ($request->has('steps')) $page->sync_engine_steps = $request->steps;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Sync engine section updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteSyncEngine()
    {
        try {
            $page = $this->getOrCreatePage();
            
            $page->sync_engine_title = null;
            $page->sync_engine_subtitle = null;
            $page->sync_engine_description = null;
            $page->sync_engine_steps = null;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Sync engine section deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ========== FOOTER CTA - FULL CRUD ==========
    
    public function getFooterCta()
    {
        $page = $this->getOrCreatePage();
        
        return response()->json([
            'success' => true,
            'data' => [
                'title' => $page->footer_title,
                'description' => $page->footer_description,
                'button_text' => $page->footer_button_text,
                'icon' => $page->footer_icon ?? '🔄',
            ]
        ]);
    }

    public function createFooterCta(Request $request)
    {
        try {
            $page = $this->getOrCreatePage();
            
            $page->footer_title = $request->title;
            $page->footer_description = $request->description;
            $page->footer_button_text = $request->button_text;
            $page->footer_icon = $request->icon ?? '🔄';
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Footer CTA created'], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateFooterCta(Request $request)
    {
        try {
            $page = $this->getOrCreatePage();
            
            if ($request->has('title')) $page->footer_title = $request->title;
            if ($request->has('description')) $page->footer_description = $request->description;
            if ($request->has('button_text')) $page->footer_button_text = $request->button_text;
            if ($request->has('icon')) $page->footer_icon = $request->icon;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Footer CTA updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteFooterCta()
    {
        try {
            $page = $this->getOrCreatePage();
            
            $page->footer_title = null;
            $page->footer_description = null;
            $page->footer_button_text = null;
            $page->footer_icon = null;
            $page->save();
            
            return response()->json(['success' => true, 'message' => 'Footer CTA deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}