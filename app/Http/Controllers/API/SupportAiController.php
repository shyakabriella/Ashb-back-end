<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SupportAiChatMessage;
use App\Models\SupportAiChatSession;
use App\Models\SupportAiKnowledge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
                'suggestions' => $this->getSuggestions(),
            ],
        ]);
    }

    /**
     * Admin: list AI training data.
     */
    public function indexKnowledge(Request $request): JsonResponse
    {
        $query = SupportAiKnowledge::query()
            ->latest();

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
        $knowledgeResult = $this->findBestKnowledgeAnswer($question);

        $hasGoodKnowledgeMatch =
            $knowledgeResult['matched_knowledge_id'] !== null &&
            $knowledgeResult['score'] >= 8;

        /*
         | If the knowledge base has a strong answer, return it directly.
         | This saves Gemini cost and keeps official company answers stable.
         */
        if ($hasGoodKnowledgeMatch) {
            return [
                'answer' => $knowledgeResult['answer'],
                'matched_knowledge_id' => $knowledgeResult['matched_knowledge_id'],
                'matched_title' => $knowledgeResult['matched_title'],
                'score' => $knowledgeResult['score'],
                'source' => 'knowledge_base',
                'requires_human' => false,
            ];
        }

        /*
         | If Gemini is disabled or API key is missing, use fallback.
         */
        if (!config('services.gemini.use_ai') || !config('services.gemini.api_key')) {
            return [
                'answer' => $knowledgeResult['answer'] ?: $this->fallbackAnswer(),
                'matched_knowledge_id' => $knowledgeResult['matched_knowledge_id'],
                'matched_title' => $knowledgeResult['matched_title'],
                'score' => $knowledgeResult['score'],
                'source' => 'fallback',
                'requires_human' => true,
            ];
        }

        $geminiAnswer = $this->askGemini($question, $session, $knowledgeResult);

        if (!$geminiAnswer) {
            return [
                'answer' => $knowledgeResult['answer'] ?: $this->fallbackAnswer(),
                'matched_knowledge_id' => $knowledgeResult['matched_knowledge_id'],
                'matched_title' => $knowledgeResult['matched_title'],
                'score' => $knowledgeResult['score'],
                'source' => 'fallback',
                'requires_human' => true,
            ];
        }

        return [
            'answer' => $geminiAnswer,
            'matched_knowledge_id' => $knowledgeResult['matched_knowledge_id'],
            'matched_title' => $knowledgeResult['matched_title'],
            'score' => max($knowledgeResult['score'], 5),
            'source' => 'gemini',
            'requires_human' => $knowledgeResult['score'] <= 0,
        ];
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

    private function askGemini(
        string $question,
        SupportAiChatSession $session,
        array $knowledgeResult
    ): ?string {
        try {
            $apiKey = config('services.gemini.api_key');
            $model = config('services.gemini.model', 'gemini-2.5-flash');
            $temperature = (float) config('services.gemini.temperature', 0.3);
            $maxOutputTokens = (int) config('services.gemini.max_output_tokens', 800);

            $recentMessages = SupportAiChatMessage::query()
                ->where('support_ai_chat_session_id', $session->id)
                ->latest()
                ->limit(8)
                ->get()
                ->reverse()
                ->map(function ($message) {
                    return strtoupper($message->sender) . ': ' . $message->message;
                })
                ->implode("\n");

            $activeKnowledge = SupportAiKnowledge::query()
                ->where('is_active', true)
                ->orderByDesc('priority')
                ->limit(15)
                ->get()
                ->map(function ($item) {
                    return "- Question: {$item->question}\n  Answer: {$item->answer}";
                })
                ->implode("\n\n");

            $matchedKnowledge = $knowledgeResult['answer']
                ? "Closest matched company answer:\n{$knowledgeResult['answer']}"
                : "No strong company answer was matched.";

            $prompt = <<<PROMPT
You are AshBHub customer support AI.

Company context:
AshBHub supports hotels, safaris, travel businesses, hotel websites, booking engines, OTA/channel management guidance, PMS support, direct booking support, digital marketing, SEO, and hospitality technology.

Rules:
1. Answer in simple, clear English.
2. Be professional, warm, and helpful.
3. Do not invent prices, contracts, guarantees, or technical promises.
4. If the question needs human support, say that the AshBHub team can follow up.
5. If the user asks about something unrelated to AshBHub services, briefly guide them back to AshBHub support.
6. Keep the answer short, around 2 to 5 sentences.

Visitor details:
Name: {$session->visitor_name}
Email: {$session->visitor_email}
Hotel/Company: {$session->visitor_hotel}

Recent conversation:
{$recentMessages}

Company knowledge base:
{$activeKnowledge}

{$matchedKnowledge}

Customer question:
{$question}

Give the best support answer now.
PROMPT;

            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

            $response = Http::timeout(30)
                ->retry(2, 500)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $apiKey,
                ])
                ->post($url, [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                [
                                    'text' => $prompt,
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => $temperature,
                        'maxOutputTokens' => $maxOutputTokens,
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('Gemini support AI request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            $text = data_get($data, 'candidates.0.content.parts.0.text');

            if (!$text || !is_string($text)) {
                Log::warning('Gemini support AI returned empty text', [
                    'response' => $data,
                ]);

                return null;
            }

            return trim($text);
        } catch (\Throwable $e) {
            Log::error('Gemini support AI exception', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
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
        return 'Thank you for your question. AshBHub supports hotels, safaris, travel businesses, websites, booking tools, and digital marketing. Please ask about hotel websites, booking engine, pricing, digital marketing, or request human support.';
    }

    private function getSuggestions(): array
    {
        return SupportAiKnowledge::query()
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->limit(5)
            ->pluck('question')
            ->values()
            ->toArray();
    }
}