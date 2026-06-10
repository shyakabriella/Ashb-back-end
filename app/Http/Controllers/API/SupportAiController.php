<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SupportAiChatMessage;
use App\Models\SupportAiChatSession;
use App\Models\SupportAiKnowledge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SupportAiController extends Controller
{
    /**
     * Public: Customer asks chatbot a question.
     */
    public function chat(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => ['required', 'string', 'min:1', 'max:2000'],
            'session_id' => ['nullable', 'string', 'max:100'],

            'visitor_name' => ['nullable', 'string', 'max:150'],
            'visitor_email' => ['nullable', 'email', 'max:150'],
            'visitor_hotel' => ['nullable', 'string', 'max:150'],
            'source' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please check your chatbot request.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $session = $this->findOrCreateSession($request);

        $userMessage = SupportAiChatMessage::create([
            'support_ai_chat_session_id' => $session->id,
            'sender' => 'user',
            'message' => $request->message,
            'metadata' => [
                'ip_address' => $request->ip(),
                'source' => $request->source ?? 'support_badge',
            ],
        ]);

        $aiResult = $this->resolveAnswer($request->message, $session);

        $botMessage = SupportAiChatMessage::create([
            'support_ai_chat_session_id' => $session->id,
            'sender' => 'bot',
            'message' => $aiResult['answer'],
            'matched_knowledge_id' => $aiResult['matched_knowledge_id'],
            'metadata' => [
                'score' => $aiResult['score'],
                'matched_title' => $aiResult['matched_title'],
                'source' => $aiResult['source'],
                'requires_human' => $aiResult['requires_human'],
                'is_in_scope' => $aiResult['is_in_scope'],
            ],
        ]);

        $session->update([
            'last_message_at' => now(),
            'status' => $aiResult['requires_human']
                ? 'transferred_to_human'
                : $session->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'AI response generated successfully.',
            'data' => [
                'session_id' => $session->uuid,

                'user_message' => $userMessage,
                'bot_message' => $botMessage,

                'answer' => $aiResult['answer'],
                'matched_knowledge_id' => $aiResult['matched_knowledge_id'],
                'matched_title' => $aiResult['matched_title'],
                'score' => $aiResult['score'],
                'source' => $aiResult['source'],
                'requires_human' => $aiResult['requires_human'],
                'is_in_scope' => $aiResult['is_in_scope'],

                'suggestions' => $this->getSuggestions(),
            ],
        ]);
    }

    /**
     * Admin: list AI training data.
     */
    public function indexKnowledge(Request $request): JsonResponse
    {
        $query = SupportAiKnowledge::query()->latest();

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('question', 'like', "%{$search}%")
                    ->orWhere('answer', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'success' => true,
            'message' => 'AI knowledge retrieved successfully.',
            'data' => $query->paginate($request->get('per_page', 20)),
        ]);
    }

    /**
     * Admin: create AI training data.
     */
    public function storeKnowledge(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['nullable', 'string', 'max:150'],
            'question' => ['required', 'string', 'max:2000'],
            'answer' => ['required', 'string', 'max:10000'],
            'keywords' => ['nullable'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please check AI training data.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $knowledge = SupportAiKnowledge::create([
            'title' => $request->title,
            'question' => $request->question,
            'answer' => $request->answer,
            'keywords' => $this->normalizeKeywords($request->keywords),
            'category' => $request->category,
            'is_active' => $request->boolean('is_active', true),
            'priority' => $request->priority ?? 0,
            'created_by' => optional($request->user())->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'AI knowledge created successfully.',
            'data' => $knowledge,
        ], 201);
    }

    /**
     * Admin: show one AI training record.
     */
    public function showKnowledge(SupportAiKnowledge $knowledge): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'AI knowledge retrieved successfully.',
            'data' => $knowledge,
        ]);
    }

    /**
     * Admin: update AI training data.
     */
    public function updateKnowledge(Request $request, SupportAiKnowledge $knowledge): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['nullable', 'string', 'max:150'],
            'question' => ['sometimes', 'required', 'string', 'max:2000'],
            'answer' => ['sometimes', 'required', 'string', 'max:10000'],
            'keywords' => ['nullable'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please check AI training data.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only([
            'title',
            'question',
            'answer',
            'category',
            'is_active',
            'priority',
        ]);

        if ($request->has('keywords')) {
            $data['keywords'] = $this->normalizeKeywords($request->keywords);
        }

        $knowledge->update($data);

        return response()->json([
            'success' => true,
            'message' => 'AI knowledge updated successfully.',
            'data' => $knowledge->fresh(),
        ]);
    }

    /**
     * Admin: delete AI training data.
     */
    public function destroyKnowledge(SupportAiKnowledge $knowledge): JsonResponse
    {
        $knowledge->delete();

        return response()->json([
            'success' => true,
            'message' => 'AI knowledge deleted successfully.',
        ]);
    }

    /**
     * Admin: list chat sessions.
     */
    public function sessions(Request $request): JsonResponse
    {
        $query = SupportAiChatSession::query()
            ->withCount('messages')
            ->latest('last_message_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('visitor_name', 'like', "%{$search}%")
                    ->orWhere('visitor_email', 'like', "%{$search}%")
                    ->orWhere('visitor_hotel', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'message' => 'AI chat sessions retrieved successfully.',
            'data' => $query->paginate($request->get('per_page', 20)),
        ]);
    }

    /**
     * Admin: view session messages.
     */
    public function sessionMessages(SupportAiChatSession $session): JsonResponse
    {
        $session->load([
            'messages' => function ($query) {
                $query->oldest();
            },
            'messages.matchedKnowledge',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'AI chat session messages retrieved successfully.',
            'data' => $session,
        ]);
    }

    /**
     * Admin: close chat session.
     */
    public function closeSession(SupportAiChatSession $session): JsonResponse
    {
        $session->update([
            'status' => 'closed',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'AI chat session closed successfully.',
            'data' => $session,
        ]);
    }

    private function findOrCreateSession(Request $request): SupportAiChatSession
    {
        if ($request->filled('session_id')) {
            $existing = SupportAiChatSession::where('uuid', $request->session_id)->first();

            if ($existing) {
                if (
                    $request->filled('visitor_name') ||
                    $request->filled('visitor_email') ||
                    $request->filled('visitor_hotel')
                ) {
                    $existing->update([
                        'visitor_name' => $request->visitor_name ?? $existing->visitor_name,
                        'visitor_email' => $request->visitor_email ?? $existing->visitor_email,
                        'visitor_hotel' => $request->visitor_hotel ?? $existing->visitor_hotel,
                    ]);
                }

                return $existing;
            }
        }

        return SupportAiChatSession::create([
            'uuid' => (string) Str::uuid(),
            'visitor_name' => $request->visitor_name,
            'visitor_email' => $request->visitor_email,
            'visitor_hotel' => $request->visitor_hotel,
            'source' => $request->source ?? 'support_badge',
            'status' => 'open',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'last_message_at' => now(),
        ]);
    }

    private function resolveAnswer(string $question, SupportAiChatSession $session): array
    {
        /*
         |--------------------------------------------------------------------------
         | 1. Greeting detection
         |--------------------------------------------------------------------------
         | If user only says hello, reply like a real assistant.
         |--------------------------------------------------------------------------
         */
        if ($this->isGreetingMessage($question)) {
            return [
                'answer' => $this->greetingAnswer(),
                'matched_knowledge_id' => null,
                'matched_title' => null,
                'score' => 10,
                'source' => 'greeting',
                'requires_human' => false,
                'is_in_scope' => true,
            ];
        }

        /*
         |--------------------------------------------------------------------------
         | 2. Search database knowledge and local scope
         |--------------------------------------------------------------------------
         */
        $knowledgeResult = $this->findBestKnowledgeAnswer($question);
        $isLocalScope = $this->isProbablyAshbhubQuestion($question) || !empty($knowledgeResult['answer']);

        /*
         |--------------------------------------------------------------------------
         | 3. Outside/general questions
         |--------------------------------------------------------------------------
         | Only unrelated questions should go to human support.
         |--------------------------------------------------------------------------
         */
        if (!$isLocalScope) {
            return [
                'answer' => $this->outOfScopeAnswer(),
                'matched_knowledge_id' => null,
                'matched_title' => null,
                'score' => 0,
                'source' => 'out_of_scope',
                'requires_human' => true,
                'is_in_scope' => false,
            ];
        }

        /*
         |--------------------------------------------------------------------------
         | 4. Ask Gemini for ASHBHUB-related question
         |--------------------------------------------------------------------------
         */
        $geminiAnswer = null;

        if ($this->geminiIsEnabled()) {
            $geminiAnswer = $this->askGemini($question, $session, $knowledgeResult);
        }

        /*
         |--------------------------------------------------------------------------
         | 5. Use Gemini answer if helpful
         |--------------------------------------------------------------------------
         */
        if ($geminiAnswer && !$this->isHumanSupportOnlyAnswer($geminiAnswer)) {
            return [
                'answer' => $geminiAnswer,
                'matched_knowledge_id' => $knowledgeResult['matched_knowledge_id'],
                'matched_title' => $knowledgeResult['matched_title'],
                'score' => max((int) $knowledgeResult['score'], 10),
                'source' => 'gemini',
                'requires_human' => false,
                'is_in_scope' => true,
            ];
        }

        /*
         |--------------------------------------------------------------------------
         | 6. Use database answer if available
         |--------------------------------------------------------------------------
         */
        if ($knowledgeResult['answer']) {
            return [
                'answer' => $knowledgeResult['answer'],
                'matched_knowledge_id' => $knowledgeResult['matched_knowledge_id'],
                'matched_title' => $knowledgeResult['matched_title'],
                'score' => max((int) $knowledgeResult['score'], 9),
                'source' => 'knowledge_base',
                'requires_human' => false,
                'is_in_scope' => true,
            ];
        }

        /*
         |--------------------------------------------------------------------------
         | 7. Final ASHBHUB local answer
         |--------------------------------------------------------------------------
         */
        return [
            'answer' => $this->localAshbhubAnswer($question),
            'matched_knowledge_id' => null,
            'matched_title' => null,
            'score' => 8,
            'source' => 'local_business_fallback',
            'requires_human' => false,
            'is_in_scope' => true,
        ];
    }

    private function geminiIsEnabled(): bool
    {
        return (bool) config('services.gemini.use_ai')
            && filled(config('services.gemini.api_key'))
            && filled(config('services.gemini.model'));
    }

    private function askGemini(
        string $question,
        SupportAiChatSession $session,
        array $knowledgeResult
    ): ?string {
        try {
            $apiKey = config('services.gemini.api_key');
            $model = config('services.gemini.model', 'gemini-2.5-flash');
            $temperature = (float) config('services.gemini.temperature', 0.4);
            $maxOutputTokens = (int) config('services.gemini.max_output_tokens', 800);

            $recentMessages = $this->getRecentConversationText($session);
            $businessKnowledge = $this->getBusinessKnowledgeText();
            $knowledgeText = $this->getKnowledgeBaseText();

            $matchedText = $knowledgeResult['answer']
                ? "Closest matched database FAQ answer:\n{$knowledgeResult['answer']}"
                : "No close database FAQ answer was matched.";

            $prompt = <<<PROMPT
You are ASHBHUB customer support AI.

Your job:
Understand the customer's intent first, then answer naturally and helpfully using ASHBHUB business knowledge.

Main instruction:
Use only the ASHBHUB business knowledge below to answer ASHBHUB-related questions.
Answer normally when the question is about ASHBHUB, hotels, lodges, apartments, resorts, Airbnbs, safaris, travel businesses, hotel websites, OTA visibility, channel management, PMS, booking engine, digital marketing, packages, pricing, setup fees, commission model, contact, requirements, onboarding, or support.

Conversation behavior:
- If the user only greets you, reply warmly and ask how you can help. Do not give the full company description.
- If the user asks about requirements, onboarding, getting started, or working with ASHBHUB, answer with the customer/property information needed.
- If the user asks "what do you do" or "what services do you offer", summarize ASHBHUB services.
- If the user asks pricing, answer with official USD pricing from the knowledge file.
- If the user asks for custom quotation, ask for property name, location, number of rooms/units, phone/email, and service needed.
- If the question is truly unrelated to ASHBHUB business support, answer exactly: Human support recommended. Please click “Talk to human support” below.

Style rules:
1. Use simple and clear English.
2. Be warm, professional, and helpful.
3. Keep the answer short: 2 to 5 sentences.
4. Do not say you are Google Gemini.
5. Do not invent prices, services, guarantees, phone numbers, or emails.
6. Do not over-answer a simple greeting.

Useful onboarding answer:
To work with ASHBHUB, a customer should provide property name, property type, location, contact person, phone number, email address, number of rooms or units, current website if available, current OTA links if available, services needed, and preferred package.

Visitor details:
Name: {$session->visitor_name}
Email: {$session->visitor_email}
Hotel/Company: {$session->visitor_hotel}

Recent conversation:
{$recentMessages}

ASHBHUB full business knowledge markdown:
{$businessKnowledge}

Extra database FAQ knowledge:
{$knowledgeText}

{$matchedText}

Customer question:
{$question}
PROMPT;

            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            $payload = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => $temperature,
                    'maxOutputTokens' => $maxOutputTokens,
                ],
            ];

            $response = $this->curlJsonPost($url, $payload);

            if (!$response['ok']) {
                Log::warning('Gemini support AI request failed', [
                    'status' => $response['status'],
                    'body' => $response['body'],
                ]);

                return null;
            }

            $data = json_decode($response['body'], true);

            if (!is_array($data)) {
                Log::warning('Gemini support AI returned invalid JSON body', [
                    'body' => $response['body'],
                ]);

                return null;
            }

            $text = data_get($data, 'candidates.0.content.parts.0.text');

            if (!$text || !is_string($text)) {
                Log::warning('Gemini support AI returned empty text', [
                    'response' => $data,
                ]);

                return null;
            }

            return $this->cleanGeminiAnswer($text);
        } catch (\Throwable $e) {
            Log::error('Gemini support AI exception', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function cleanGeminiAnswer(string $text): string
    {
        $text = trim($text);

        $text = preg_replace('/^```json\s*/i', '', $text);
        $text = preg_replace('/^```\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
        $text = trim($text);

        $decoded = json_decode($text, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            if (!empty($decoded['answer']) && is_string($decoded['answer'])) {
                return trim($decoded['answer']);
            }
        }

        return $text;
    }

    private function curlJsonPost(string $url, array $payload): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 35,
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($body === false) {
            return [
                'ok' => false,
                'status' => $status,
                'body' => $error ?: 'cURL request failed.',
            ];
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => $body,
        ];
    }

    private function getRecentConversationText(SupportAiChatSession $session): string
    {
        $messages = SupportAiChatMessage::query()
            ->where('support_ai_chat_session_id', $session->id)
            ->latest()
            ->limit(8)
            ->get()
            ->reverse();

        if ($messages->isEmpty()) {
            return 'No previous conversation.';
        }

        return $messages
            ->map(function ($message) {
                return strtoupper($message->sender) . ': ' . $message->message;
            })
            ->implode("\n");
    }

    private function getBusinessKnowledgeText(): string
    {
        $businessKnowledgePath = resource_path('ai/ashbhub-business-knowledge.md');

        if (!file_exists($businessKnowledgePath)) {
            Log::warning('ASHBHUB business knowledge markdown file not found', [
                'path' => $businessKnowledgePath,
            ]);

            return 'ASHBHUB business knowledge file not found.';
        }

        $content = file_get_contents($businessKnowledgePath);

        if (!$content || trim($content) === '') {
            Log::warning('ASHBHUB business knowledge markdown file is empty', [
                'path' => $businessKnowledgePath,
            ]);

            return 'ASHBHUB business knowledge file is empty.';
        }

        return trim($content);
    }

    private function getKnowledgeBaseText(): string
    {
        $items = SupportAiKnowledge::query()
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->limit(20)
            ->get();

        if ($items->isEmpty()) {
            return 'No database FAQ knowledge records found.';
        }

        return $items
            ->map(function ($item) {
                $keywords = $this->normalizeKeywords($item->keywords);
                $keywordsText = count($keywords) ? implode(', ', $keywords) : 'none';

                return "- Title: {$item->title}\n  Question: {$item->question}\n  Answer: {$item->answer}\n  Keywords: {$keywordsText}\n  Category: {$item->category}";
            })
            ->implode("\n\n");
    }

    private function findBestKnowledgeAnswer(string $question): array
    {
        $knowledgeItems = SupportAiKnowledge::query()
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->get();

        if ($knowledgeItems->isEmpty()) {
            return [
                'answer' => null,
                'matched_knowledge_id' => null,
                'matched_title' => null,
                'score' => 0,
            ];
        }

        $normalizedQuestion = $this->normalizeText($question);
        $questionWords = $this->importantWords($normalizedQuestion);

        $bestItem = null;
        $bestScore = 0;

        foreach ($knowledgeItems as $item) {
            $score = 0;

            $title = $this->normalizeText($item->title ?? '');
            $storedQuestion = $this->normalizeText($item->question ?? '');
            $answer = $this->normalizeText($item->answer ?? '');
            $keywords = $this->normalizeKeywords($item->keywords);

            if ($storedQuestion && str_contains($storedQuestion, $normalizedQuestion)) {
                $score += 10;
            }

            if ($storedQuestion && str_contains($normalizedQuestion, $storedQuestion)) {
                $score += 10;
            }

            foreach ($keywords as $keyword) {
                $keyword = $this->normalizeText($keyword);

                if (!$keyword) {
                    continue;
                }

                if (str_contains($normalizedQuestion, $keyword)) {
                    $score += 8;
                }

                if (str_contains($keyword, $normalizedQuestion)) {
                    $score += 4;
                }
            }

            foreach ($questionWords as $word) {
                if (str_contains($title, $word)) {
                    $score += 3;
                }

                if (str_contains($storedQuestion, $word)) {
                    $score += 3;
                }

                if (str_contains($answer, $word)) {
                    $score += 1;
                }
            }

            $score += (int) $item->priority;

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestItem = $item;
            }
        }

        if (!$bestItem || $bestScore <= 0) {
            return [
                'answer' => null,
                'matched_knowledge_id' => null,
                'matched_title' => null,
                'score' => 0,
            ];
        }

        return [
            'answer' => $bestItem->answer,
            'matched_knowledge_id' => $bestItem->id,
            'matched_title' => $bestItem->title,
            'score' => $bestScore,
        ];
    }

    private function isGreetingMessage(string $question): bool
    {
        $text = $this->normalizeText($question);

        $greetings = [
            'hi',
            'hello',
            'hey',
            'good morning',
            'good afternoon',
            'good evening',
            'muraho',
        ];

        return in_array($text, $greetings, true);
    }

    private function greetingAnswer(): string
    {
        return 'Hello 👋 Welcome to ASHBHUB support. How can I help you today? You can ask about hotel websites, OTA listing, PMS setup, booking engine, pricing, digital marketing, or how to work with ASHBHUB.';
    }

    private function isProbablyAshbhubQuestion(string $question): bool
    {
        $text = $this->normalizeText($question);

        $keywords = [
            'ashbhub',
            'ashb',
            'african safari',
            'hotel booking hub',

            'requirement',
            'requirements',
            'required',
            'what is required',
            'what are required',
            'what do i need',
            'what we need',
            'need to work',
            'needed to work',
            'work with you',
            'work with them',
            'working with you',
            'start with you',
            'get started',
            'getting started',
            'how to start',
            'start working',
            'onboard',
            'onboarding',
            'register',
            'registration',
            'sign up',
            'join',
            'become client',
            'partner',
            'partnership',

            'service',
            'services',
            'offer',
            'offers',
            'offering',
            'what do you do',
            'what you do',
            'about you',
            'about ashbhub',
            'business',
            'company',
            'help',
            'help me',
            'manage',
            'management',
            'grow',
            'growth',
            'online',
            'visibility',

            'hotel',
            'hotels',
            'apartment',
            'apartments',
            'lodge',
            'lodges',
            'resort',
            'resorts',
            'bnb',
            'airbnb',
            'guesthouse',
            'guesthouses',
            'safari',
            'travel',
            'tour',

            'booking',
            'bookings',
            'reservation',
            'direct booking',
            'booking engine',

            'website',
            'websites',
            'web design',
            'online presence',

            'ota',
            'otas',
            'booking com',
            'booking.com',
            'expedia',
            'agoda',
            'trip',
            'tripadvisor',
            'trivago',
            'hotels com',
            'hotels.com',

            'channel',
            'channel management',
            'channel manager',

            'pms',
            'property management',
            'front desk',
            'check in',
            'check out',
            'room',
            'rooms',
            'availability',

            'digital marketing',
            'marketing',
            'social media',
            'facebook',
            'instagram',
            'tiktok',
            'seo',
            'google visibility',
            'google ads',
            'meta ads',

            'graphic design',
            'branding',
            'logo',
            'review',
            'reviews',
            'reputation',

            'price',
            'pricing',
            'cost',
            'package',
            'packages',
            'plan',
            'plans',
            'basic',
            'standard',
            'premium',
            'commission',
            'setup',
            'fee',
            'fees',

            'support',
            'contact',
            'phone',
            'email',
            'whatsapp',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($text, $this->normalizeText($keyword))) {
                return true;
            }
        }

        return false;
    }

    private function isHumanSupportOnlyAnswer(string $answer): bool
    {
        $text = $this->normalizeText($answer);

        return str_contains($text, 'human support recommended')
            || str_contains($text, 'talk to human support below')
            || str_contains($text, 'click talk to human support');
    }

    private function localAshbhubAnswer(string $question): string
    {
        $text = $this->normalizeText($question);

        if (
            str_contains($text, 'requirement') ||
            str_contains($text, 'required') ||
            str_contains($text, 'what do i need') ||
            str_contains($text, 'work with you') ||
            str_contains($text, 'work with them') ||
            str_contains($text, 'get started') ||
            str_contains($text, 'start') ||
            str_contains($text, 'onboard') ||
            str_contains($text, 'register') ||
            str_contains($text, 'join')
        ) {
            return 'To work with ASHBHUB, a customer should provide the property name, property type, location, contact person, phone number, email address, number of rooms or units, current website if available, current OTA links if available, services needed, and preferred package. This helps ASHBHUB prepare the right setup for website, OTA visibility, PMS, booking engine, digital marketing, or full hotel digital management.';
        }

        if (
            str_contains($text, 'service') ||
            str_contains($text, 'offer') ||
            str_contains($text, 'what do you do') ||
            str_contains($text, 'what you do') ||
            str_contains($text, 'help')
        ) {
            return 'ASHBHUB helps hotels, apartments, lodges, resorts, Airbnbs, and safari/travel businesses grow online. We provide hotel websites, booking engine support, OTA visibility, channel management, PMS setup, social media management, digital marketing, branding, review management, reporting, and local support.';
        }

        if (
            str_contains($text, 'price') ||
            str_contains($text, 'pricing') ||
            str_contains($text, 'cost') ||
            str_contains($text, 'package') ||
            str_contains($text, 'plan')
        ) {
            return 'ASHBHUB has monthly plans in USD: Basic at $800/month, Standard at $1,200/month, and Premium at $2,000/month. One-time options include website development from $350–$800, OTA setup at $300, PMS integration at $450, and branding at $350.';
        }

        if (str_contains($text, 'basic')) {
            return 'The ASHBHUB Basic Plan is $800/month. It is best for small apartments, Airbnbs, and small properties starting online, and includes a website, setup on major OTAs, social media setup, basic channel management, monthly reporting, and basic support.';
        }

        if (str_contains($text, 'standard')) {
            return 'The ASHBHUB Standard Plan is $1,200/month. It is best for guesthouses, lodges, and mid-size hotels, and includes everything in Basic plus listing on 150+ OTAs, social media management, 8 posts per month, brand design templates, SEO, and Google visibility optimization.';
        }

        if (str_contains($text, 'premium')) {
            return 'The ASHBHUB Premium Plan is $2,000/month. It is recommended for hotels, resorts, and high-demand properties, and includes a professional website, listing on 450+ OTAs, channel management setup, PMS setup, social media management, review management, digital marketing consultation, priority support, and 24/7 assistance.';
        }

        if (
            str_contains($text, 'website') ||
            str_contains($text, 'web design') ||
            str_contains($text, 'online presence')
        ) {
            return 'Yes. ASHBHUB builds professional hotel websites with room pages, gallery, contact forms, booking engine support, SEO structure, online payment support, and mobile-friendly design.';
        }

        if (
            str_contains($text, 'ota') ||
            str_contains($text, 'booking com') ||
            str_contains($text, 'booking.com') ||
            str_contains($text, 'expedia') ||
            str_contains($text, 'agoda')
        ) {
            return 'Yes. ASHBHUB helps properties get listed and optimized on major OTAs such as Booking.com, Airbnb, Expedia, Agoda, Trip.com, Hotels.com, Tripadvisor, Trivago, and up to 450+ platforms depending on the package.';
        }

        if (
            str_contains($text, 'channel') ||
            str_contains($text, 'channel management') ||
            str_contains($text, 'channel manager')
        ) {
            return 'Yes. ASHBHUB supports channel management to sync prices, availability, and bookings across OTA platforms. This helps reduce manual work, avoid overbookings, and improve occupancy.';
        }

        if (
            str_contains($text, 'pms') ||
            str_contains($text, 'property management') ||
            str_contains($text, 'front desk')
        ) {
            return 'Yes. ASHBHUB supports PMS setup for front desk operations, reservations, check-in/check-out, billing, guest messages, room availability, direct booking, and performance reports.';
        }

        if (
            str_contains($text, 'marketing') ||
            str_contains($text, 'social media') ||
            str_contains($text, 'facebook') ||
            str_contains($text, 'instagram') ||
            str_contains($text, 'tiktok')
        ) {
            return 'Yes. ASHBHUB helps with digital marketing, social media setup and management, monthly content, graphic design, Google/Meta campaign support, review management, branding, and guest engagement.';
        }

        if (
            str_contains($text, 'commission') ||
            str_contains($text, 'no fixed monthly')
        ) {
            return 'ASHBHUB can also offer a commission-based model for some properties. In this option, the hotel pays 30% commission on online bookings generated, while ASHBHUB manages OTAs, social media, and digital marketing with no fixed monthly fee.';
        }

        if (
            str_contains($text, 'contact') ||
            str_contains($text, 'phone') ||
            str_contains($text, 'email') ||
            str_contains($text, 'whatsapp')
        ) {
            return 'You can contact ASHBHUB by phone or WhatsApp at +250 788 471 880, or by email at Hotelandsafari@gmail.com. ASHBHUB is based in Kigali, Rwanda.';
        }

        return 'ASHBHUB is a hospitality digital growth company based in Kigali, Rwanda. We help hotels, lodges, apartments, resorts, Airbnbs, and safari/travel businesses grow online through websites, OTA visibility, channel management, PMS setup, digital marketing, branding, reporting, and support.';
    }

    private function normalizeKeywords($keywords): array
    {
        if (!$keywords) {
            return [];
        }

        if (is_string($keywords)) {
            $decoded = json_decode($keywords, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter(array_map('trim', $decoded)));
            }

            return array_values(array_filter(array_map('trim', explode(',', $keywords))));
        }

        if (is_array($keywords)) {
            return array_values(array_filter(array_map(function ($keyword) {
                return trim((string) $keyword);
            }, $keywords)));
        }

        return [];
    }

    private function normalizeText(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }

    private function importantWords(string $text): array
    {
        $stopWords = [
            'the',
            'and',
            'or',
            'is',
            'are',
            'am',
            'i',
            'you',
            'we',
            'to',
            'of',
            'in',
            'on',
            'for',
            'with',
            'about',
            'what',
            'how',
            'can',
            'do',
            'does',
            'a',
            'an',
            'it',
            'this',
            'that',
            'please',
            'hello',
            'hi',
            'hey',
        ];

        $words = explode(' ', $text);

        return array_values(array_filter($words, function ($word) use ($stopWords) {
            return strlen($word) >= 3 && !in_array($word, $stopWords, true);
        }));
    }

    private function fallbackAnswer(): string
    {
        return 'Human support recommended. Please click “Talk to human support” below.';
    }

    private function outOfScopeAnswer(): string
    {
        return 'Human support recommended. Please click “Talk to human support” below.';
    }

    private function getSuggestions(): array
    {
        $suggestions = SupportAiKnowledge::query()
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->limit(5)
            ->pluck('question')
            ->values()
            ->toArray();

        if (count($suggestions) > 0) {
            return $suggestions;
        }

        return [
            'What is ASHBHUB?',
            'Do you build hotel websites?',
            'Do you support booking engine?',
            'Do you help with digital marketing?',
            'How can I list my hotel?',
        ];
    }
}