<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactMessageController extends Controller
{
    /**
     * Public: Save contact message from website form.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'full_name' => ['required', 'string', 'max:150'],
            'hotel_name' => ['nullable', 'string', 'max:150'],
            'work_email' => ['required', 'email', 'max:150'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please check the form and try again.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $contactMessage = ContactMessage::create([
            'full_name' => $request->full_name,
            'hotel_name' => $request->hotel_name,
            'work_email' => $request->work_email,
            'message' => $request->message,
            'status' => ContactMessage::STATUS_NEW,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your message has been sent successfully.',
            'data' => $contactMessage,
        ], 201);
    }

    /**
     * Admin: Get all contact messages.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ContactMessage::query()->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('hotel_name', 'like', "%{$search}%")
                    ->orWhere('work_email', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $messages = $query->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Contact messages retrieved successfully.',
            'data' => $messages,
        ]);
    }

    /**
     * Admin: View one message.
     */
    public function show(ContactMessage $contactMessage): JsonResponse
    {
        if ($contactMessage->status === ContactMessage::STATUS_NEW) {
            $contactMessage->update([
                'status' => ContactMessage::STATUS_READ,
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact message retrieved successfully.',
            'data' => $contactMessage,
        ]);
    }

    /**
     * Admin: Mark message as read.
     */
    public function markAsRead(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->update([
            'status' => ContactMessage::STATUS_READ,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Message marked as read.',
            'data' => $contactMessage,
        ]);
    }

    /**
     * Admin: Mark message as replied.
     */
    public function markAsReplied(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->update([
            'status' => ContactMessage::STATUS_REPLIED,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Message marked as replied.',
            'data' => $contactMessage,
        ]);
    }

    /**
     * Admin: Delete message.
     */
    public function destroy(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact message deleted successfully.',
        ]);
    }
}