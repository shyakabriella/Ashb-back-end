<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MonthlyPlanPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MonthlyPlanPageController extends Controller
{
    /**
     * Get monthly plans page data.
     */
    public function show()
    {
        $page = MonthlyPlanPage::where('page_key', 'monthly-plans')->first();

        if (!$page) {
            $page = MonthlyPlanPage::create($this->defaultPageData());
        }

        return response()->json([
            'success' => true,
            'message' => 'Monthly plans page retrieved successfully.',
            'data' => $page,
        ]);
    }

    /**
     * Create or update monthly plans page data.
     */
    public function storeOrUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'hero_kicker' => 'nullable|string|max:255',
            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string',

            'tiers' => 'nullable|array',
            'tiers.*.title' => 'required_with:tiers|string|max:255',
            'tiers.*.description' => 'nullable|string',
            'tiers.*.price' => 'nullable|string|max:100',
            'tiers.*.button_text' => 'nullable|string|max:100',
            'tiers.*.is_recommended' => 'nullable|boolean',
            'tiers.*.features' => 'nullable|array',
            'tiers.*.features.*' => 'nullable|string|max:255',

            'banner_image' => 'nullable|string',
            'banner_title' => 'nullable|string|max:255',
            'banner_subtitle' => 'nullable|string',

            'compare_title' => 'nullable|string|max:255',
            'comparison_rows' => 'nullable|array',
            'comparison_rows.*.feature' => 'required_with:comparison_rows|string|max:255',
            'comparison_rows.*.foundation' => 'nullable|string|max:255',
            'comparison_rows.*.horizon' => 'nullable|string|max:255',
            'comparison_rows.*.elite' => 'nullable|string|max:255',

            'faq_title' => 'nullable|string|max:255',
            'faqs' => 'nullable|array',
            'faqs.*.question' => 'required_with:faqs|string|max:255',
            'faqs.*.answer' => 'nullable|string',

            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $page = MonthlyPlanPage::updateOrCreate(
            [
                'page_key' => 'monthly-plans',
            ],
            [
                'hero_kicker' => $request->hero_kicker,
                'hero_title' => $request->hero_title,
                'hero_subtitle' => $request->hero_subtitle,

                'tiers' => $request->tiers,

                'banner_image' => $request->banner_image,
                'banner_title' => $request->banner_title,
                'banner_subtitle' => $request->banner_subtitle,

                'compare_title' => $request->compare_title,
                'comparison_rows' => $request->comparison_rows,

                'faq_title' => $request->faq_title,
                'faqs' => $request->faqs,

                'is_active' => $request->is_active ?? true,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Monthly plans page saved successfully.',
            'data' => $page,
        ]);
    }

    /**
     * Default data from the current static page.
     */
    private function defaultPageData(): array
    {
        return [
            'page_key' => 'monthly-plans',

            'hero_kicker' => 'Investment Tiers',
            'hero_title' => 'Investment in Excellence',
            'hero_subtitle' => 'Scalable growth solutions for hospitality brands.',

            'tiers' => [
                [
                    'title' => 'Heritage Foundation',
                    'description' => 'AI guest support + booking engine for independent properties.',
                    'price' => '2,450',
                    'button_text' => 'Begin',
                    'is_recommended' => false,
                    'features' => [
                        'AI Guest Support',
                        'Booking Engine',
                        'Marketing Automations',
                        'Reports',
                    ],
                ],
                [
                    'title' => 'Horizon Suite',
                    'description' => 'Full AI concierge + analytics suite.',
                    'price' => '5,900',
                    'button_text' => 'Upgrade',
                    'is_recommended' => true,
                    'features' => [
                        'Multilingual AI',
                        'CRM Integration',
                        'Revenue Analytics',
                        'Automation',
                        'Priority Support',
                    ],
                ],
                [
                    'title' => 'Elite',
                    'description' => 'Bespoke AI + global management.',
                    'price' => '12,500',
                    'button_text' => 'Contact',
                    'is_recommended' => false,
                    'features' => [
                        'Custom AI Models',
                        'Global Panel',
                        '24/7 Concierge',
                        'Brand AI',
                        'Strategy',
                    ],
                ],
            ],

            'banner_image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&q=80&w=1600',
            'banner_title' => 'Standards',
            'banner_subtitle' => 'Engineering hospitality systems that scale.',

            'compare_title' => 'Compare',

            'comparison_rows' => [
                [
                    'feature' => 'AI Support',
                    'foundation' => 'Standard',
                    'horizon' => 'Multi',
                    'elite' => 'Bespoke',
                ],
                [
                    'feature' => 'Marketing',
                    'foundation' => 'Email',
                    'horizon' => 'CRM',
                    'elite' => 'Omnichannel',
                ],
                [
                    'feature' => 'Integration',
                    'foundation' => 'Self',
                    'horizon' => 'Guided',
                    'elite' => 'White-glove',
                ],
                [
                    'feature' => 'Analytics',
                    'foundation' => 'Monthly',
                    'horizon' => 'Real-time',
                    'elite' => 'Predictive',
                ],
                [
                    'feature' => 'Brand',
                    'foundation' => '—',
                    'horizon' => 'Basic',
                    'elite' => 'Global',
                ],
            ],

            'faq_title' => 'FAQ',

            'faqs' => [
                [
                    'question' => 'Upgrade?',
                    'answer' => 'Yes, anytime.',
                ],
                [
                    'question' => 'Setup time?',
                    'answer' => '72h–10 days.',
                ],
                [
                    'question' => 'AI custom?',
                    'answer' => 'Yes, trained per brand.',
                ],
            ],

            'is_active' => true,
        ];
    }
}