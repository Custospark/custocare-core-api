<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Services\Message\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;


/**
 * MessageController
 * ─────────────────
 * REST API for the messaging module.
 *
 * Suggested route registration (routes/api.php):
 * ─────────────────────────────────────────────
 *   Route::prefix('messages')->middleware('auth:sanctum')->group(function () {
 *       Route::get('/',                  [MessageController::class, 'index']);
 *       Route::post('/',                 [MessageController::class, 'store']);
 *       Route::get('/stats',             [MessageController::class, 'stats']);
 *       Route::delete('/trash/empty',    [MessageController::class, 'emptyTrash']);
 *       Route::post('/bulk',             [MessageController::class, 'bulk']);
 *       Route::get('/{id}',              [MessageController::class, 'show']);
 *       Route::put('/{id}',              [MessageController::class, 'update']);
 *       Route::delete('/{id}',           [MessageController::class, 'destroy']);
 *       Route::post('/{id}/send',        [MessageController::class, 'send']);
 *       Route::post('/{id}/restore',     [MessageController::class, 'restore']);
 *       Route::delete('/{id}/permanent', [MessageController::class, 'permanentDelete']);
 *       Route::patch('/{id}/read',       [MessageController::class, 'markRead']);
 *       Route::patch('/{id}/unread',     [MessageController::class, 'markUnread']);
 *       Route::patch('/{id}/star',       [MessageController::class, 'star']);
 *       Route::patch('/{id}/archive',    [MessageController::class, 'archive']);
 *       Route::patch('/{id}/unarchive',  [MessageController::class, 'unarchive']);
 *       Route::post('/{id}/labels',      [MessageController::class, 'addLabel']);
 *       Route::delete('/{id}/labels/{label}', [MessageController::class, 'removeLabel']);
 *       Route::post('/{id}/attachments', [MessageController::class, 'uploadAttachment']);
 *       Route::delete('/attachments/{attachmentId}', [MessageController::class, 'removeAttachment']);
 *   });
 */
class MessageController extends Controller
{
    public function __construct(private readonly MessageService $service) {}

    // ── GET /messages ──────────────────────────────────────────────────────

    /**
     * List messages for the authenticated user in a given folder.
     *
     * Query params:
     *   folder   string  inbox|sent|drafts|archive|trash  (default: inbox)
     *   filter   string  all|unread|starred|archived|incomplete|failed
     *   sort     string  newest|oldest|alphabetical|recentlyDeleted|originalDate
     *   search   string  Full-text substring search
     *   per_page int     Items per page (default 20, max 100)
     */
    public function index(Request $request): JsonResponse
    {
        $params = $request->validate([
            'folder'   => ['sometimes', Rule::in(array_merge(
                ['inbox', 'sent', 'drafts', 'archive', 'trash']
            ))],
            'filter'   => ['sometimes', Rule::in([
                'all', 'unread', 'starred', 'archived', 'incomplete', 'failed',
            ])],
            'sort'     => ['sometimes', Rule::in([
                'newest', 'oldest', 'alphabetical',
                'recentlyDeleted', 'oldestDeleted', 'originalDate',
            ])],
            'search'   => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->service->getFolder(Auth::user(), $params);

        return response()->json($paginator);
    }

    // ── POST /messages ─────────────────────────────────────────────────────

    /**
     * Create a new message — either save it as a draft or send it immediately.
     *
     * Pass `save_draft: true` in the body to save without sending.
     * Pass `scheduled_send_at` to queue a future send.
     */
    public function store(Request $request): JsonResponse
    {  

        $data = $request->validate([
            'save_draft'                => ['sometimes', 'boolean'],
            'message_id'                => ['sometimes', 'integer', 'exists:messages,id'],
            'subject'                   => ['nullable', 'string', 'max:998'],
            'body'                      => ['nullable', 'string'],
            'body_type'                 => ['sometimes', Rule::in(Message::BODY_TYPES)],
            'priority'                  => ['sometimes', Rule::in(Message::PRIORITIES)],
            'scheduled_send_at'         => ['nullable', 'date', 'after:now'],
            'read_receipt'              => ['sometimes', 'boolean'],
            'delivery_confirmation'     => ['sometimes', 'boolean'],
            'parent_id'                 => ['nullable', 'integer', 'exists:messages,id'],
            'labels'                    => ['sometimes', 'array'],
            'labels.*'                  => ['string', 'max:100'],
            // Recipients: flat arrays keyed by type
            'to'                        => ['sometimes', 'array'],
            'to.*.name'                 => ['nullable', 'string', 'max:255'],
            'to.*.email'                => ['required_with:to', 'email', 'max:255'],
            'cc'                        => ['sometimes', 'array'],
            'cc.*.name'                 => ['nullable', 'string', 'max:255'],
            'cc.*.email'                => ['required_with:cc', 'email', 'max:255'],
            'bcc'                       => ['sometimes', 'array'],
            'bcc.*.name'                => ['nullable', 'string', 'max:255'],
            'bcc.*.email'               => ['required_with:bcc', 'email', 'max:255'],
        ]);

        $user = Auth::user();

        // Route to draft or send based on the `save_draft` flag
        if ($request->boolean('save_draft', false)) {
            $message = $this->service->saveDraft($user, $data);

            return response()->json([
                'message' => $message,
                'status'  => 'draft_saved',
            ], Response::HTTP_CREATED);
        }

        // Validate minimum requirements for sending
        $request->validate([
            'subject' => ['required', 'string', 'max:998'],
            'body'    => ['required', 'string'],
            'to'      => ['required_without_all:cc,bcc', 'array'],
        ]);

        $message = $this->service->sendMessage($user, $data);

        $status = $message->scheduled_send_at ? 'scheduled' : 'sent';

        return response()->json([
            'message' => $message,
            'status'  => $status,
        ], Response::HTTP_CREATED);
    }

    // ── GET /messages/{id} ────────────────────────────────────────────────

    /**
     * Retrieve a single message detail.
     * Auto-marks inbox messages as read on first open.
     */
    public function show(int $id): JsonResponse
    {
        $result = $this->service->getMessage(Auth::user(), $id);

        return response()->json($result);
    }

    // ── PUT /messages/{id} ────────────────────────────────────────────────

    /**
     * Update (patch) an existing draft.
     * Only the sender may update their own drafts.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'subject'               => ['nullable', 'string', 'max:998'],
            'body'                  => ['nullable', 'string'],
            'body_type'             => ['sometimes', Rule::in(Message::BODY_TYPES)],
            'priority'              => ['sometimes', Rule::in(Message::PRIORITIES)],
            'scheduled_send_at'     => ['nullable', 'date', 'after:now'],
            'read_receipt'          => ['sometimes', 'boolean'],
            'delivery_confirmation' => ['sometimes', 'boolean'],
            'labels'                => ['sometimes', 'array'],
            'labels.*'              => ['string', 'max:100'],
            'to'                    => ['sometimes', 'array'],
            'to.*.name'             => ['nullable', 'string', 'max:255'],
            'to.*.email'            => ['required_with:to', 'email'],
            'cc'                    => ['sometimes', 'array'],
            'cc.*.name'             => ['nullable', 'string', 'max:255'],
            'cc.*.email'            => ['required_with:cc', 'email'],
            'bcc'                   => ['sometimes', 'array'],
            'bcc.*.name'            => ['nullable', 'string', 'max:255'],
            'bcc.*.email'           => ['required_with:bcc', 'email'],
        ]);

        $message = $this->service->updateDraft(Auth::user(), $id, $data);

        return response()->json(['message' => $message]);
    }

    // ── DELETE /messages/{id} (move to trash) ─────────────────────────────

    /**
     * Move a message to the authenticated user's trash.
     * Does NOT permanently delete — use /messages/{id}/permanent for that.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->trashMessage(Auth::user(), $id);

        return response()->json(['status' => 'trashed']);
    }

    // ── POST /messages/{id}/send ───────────────────────────────────────────

    /**
     * Dispatch a previously saved draft immediately.
     */
    public function send(int $id): JsonResponse
    {
        $message = $this->service->sendDraft(Auth::user(), $id);

        return response()->json(['message' => $message, 'status' => 'sent']);
    }

    // ── POST /messages/{id}/restore ────────────────────────────────────────

    /**
     * Restore a trashed message to its original folder.
     */
    public function restore(int $id): JsonResponse
    {
        $this->service->restoreFromTrash(Auth::user(), $id);

        return response()->json(['status' => 'restored']);
    }

    // ── DELETE /messages/{id}/permanent ────────────────────────────────────

    /**
     * Permanently delete a message for the authenticated user.
     * Drafts owned by the user are hard-deleted from the database.
     */
    public function permanentDelete(int $id): JsonResponse
    {
        $this->service->permanentDelete(Auth::user(), $id);

        return response()->json(['status' => 'permanently_deleted']);
    }

    // ── PATCH /messages/{id}/read ─────────────────────────────────────────

    public function markRead(int $id): JsonResponse
    {
        $this->service->markRead(Auth::user(), $id);

        return response()->json(['status' => 'read']);
    }

    // ── PATCH /messages/{id}/unread ───────────────────────────────────────

    public function markUnread(int $id): JsonResponse
    {
        $this->service->markUnread(Auth::user(), $id);

        return response()->json(['status' => 'unread']);
    }

    // ── PATCH /messages/{id}/star ─────────────────────────────────────────

    /**
     * Toggle the star on a message and return the new starred state.
     */
    public function star(int $id): JsonResponse
    {
        $starred = $this->service->toggleStar(Auth::user(), $id);

        return response()->json(['starred' => $starred]);
    }

    // ── PATCH /messages/{id}/archive ──────────────────────────────────────

    public function archive(int $id): JsonResponse
    {
        $this->service->archiveMessage(Auth::user(), $id);

        return response()->json(['status' => 'archived']);
    }

    // ── PATCH /messages/{id}/unarchive ────────────────────────────────────

    public function unarchive(int $id): JsonResponse
    {
        $this->service->unarchiveMessage(Auth::user(), $id);

        return response()->json(['status' => 'unarchived']);
    }

    // ── DELETE /messages/trash/empty ──────────────────────────────────────

    /**
     * Permanently delete every message in the user's trash.
     */
    public function emptyTrash(): JsonResponse
    {
        $count = $this->service->emptyTrash(Auth::user());

        return response()->json([
            'status'  => 'trash_emptied',
            'deleted' => $count,
        ]);
    }

    // ── POST /messages/bulk ────────────────────────────────────────────────

    /**
     * Bulk-apply an action to a list of message IDs.
     *
     * Body:
     *   action      string   trash|restore|star|archive|unarchive|markRead|markUnread|permanentDelete
     *   message_ids int[]
     */
    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action'       => ['required', 'string', Rule::in([
                'trash', 'restore', 'star', 'archive', 'unarchive',
                'markRead', 'markUnread', 'permanentDelete',
            ])],
            'message_ids'  => ['required', 'array', 'min:1'],
            'message_ids.*'=> ['integer'],
        ]);

        $count = $this->service->bulkAction(
            Auth::user(),
            $data['action'],
            $data['message_ids']
        );

        return response()->json([
            'status'   => 'bulk_action_complete',
            'action'   => $data['action'],
            'affected' => $count,
        ]);
    }

    // ── POST /messages/{id}/labels ────────────────────────────────────────

    /**
     * Add a label tag to a message for the current user.
     */
    public function addLabel(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
        ]);

        $this->service->addLabel(Auth::user(), $id, $data['label']);

        return response()->json(['status' => 'label_added']);
    }

    // ── DELETE /messages/{id}/labels/{label} ──────────────────────────────

    /**
     * Remove a label tag from a message for the current user.
     */
    public function removeLabel(int $id, string $label): JsonResponse
    {
        $this->service->removeLabel(Auth::user(), $id, $label);

        return response()->json(['status' => 'label_removed']);
    }

    // ── POST /messages/{id}/attachments ───────────────────────────────────

    /**
     * Upload a file and attach it to a draft message.
     */
    public function uploadAttachment(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'file'  => ['required', 'file', 'max:20480'],   // 20 MB hard limit
            'disk'  => ['sometimes', 'string', Rule::in(['local', 'public', 's3'])],
        ]);

        $attachment = $this->service->uploadAttachment(
            Auth::user(),
            $id,
            $request->file('file'),
            $request->input('disk', 'local')
        );

        return response()->json(['attachment' => $attachment], Response::HTTP_CREATED);
    }

    // ── DELETE /messages/attachments/{attachmentId} ───────────────────────

    /**
     * Remove an attachment from a draft and delete the physical file.
     */
    public function removeAttachment(int $attachmentId): JsonResponse
    {
        $this->service->removeAttachment(Auth::user(), $attachmentId);

        return response()->json(['status' => 'attachment_removed']);
    }


/**
 * Download an attachment file.
 * 
 * @param Request $request
 * @param int $attachmentId
 * @return BinaryFileResponse|StreamedResponse|\Illuminate\Http\JsonResponse
 */
public function downloadAttachment(Request $request, int $attachmentId)
{
    // Validate signed URL
    if (!$request->hasValidSignature()) {
        abort(403, 'Invalid or expired download link.');
    }

    try {
        $attachment = $this->service->downloadAttachment(Auth::user(), $attachmentId);
        
        $disk = Storage::disk($attachment->disk);
        $path = $attachment->path;

        if (!$disk->exists($path)) {
            return response()->json([
                'message' => 'File not found on disk.',
            ], Response::HTTP_NOT_FOUND);
        }

        $filename = $attachment->original_name;
        $mimeType = $attachment->mime_type ?: 'application/octet-stream';
        
        // Clear any existing output buffers
        if (ob_get_level()) {
            ob_end_clean();
        }

        // For local disks - use BinaryFileResponse
        if ($attachment->disk === 'local') {
            $fullPath = $disk->path($path);
            return new BinaryFileResponse($fullPath, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => $attachment->size_bytes,
                'Cache-Control' => 'no-cache, private',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }
        
        // For cloud disks - use StreamedResponse
        $stream = $disk->readStream($path);
        
        if ($stream === false) {
            return response()->json([
                'message' => 'Unable to read file stream.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        
        return new StreamedResponse(function() use ($stream) {
            // Set binary mode
            if (function_exists('set_time_limit')) {
                set_time_limit(0);
            }
            
            // Output the file in chunks
            while (!feof($stream)) {
                echo fread($stream, 8192);
                flush();
            }
            fclose($stream);
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => $attachment->size_bytes,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Transfer-Encoding' => 'binary',
            'Cache-Control' => 'no-cache, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        
    } catch (\Throwable $e) {
        Log::error('Failed to download attachment', [
            'user_id' => Auth::id(),
            'attachment_id' => $attachmentId,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'message' => 'Failed to download attachment: ' . $e->getMessage(),
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
    // ── GET /messages/stats ───────────────────────────────────────────────

    /**
     * Return unread counts and totals per folder for the sidebar badges.
     *
     * Response shape:
     * {
     *   "inbox":   { "total": 10, "unread": 3 },
     *   "sent":    { "total": 12, "unread": 0 },
     *   "drafts":  { "total": 2,  "unread": 0 },
     *   "archive": { "total": 5,  "unread": 0 },
     *   "trash":   { "total": 4,  "unread": 0 }
     * }
     */
    public function stats(): JsonResponse
    {
        return response()->json($this->service->getStats(Auth::user()));
    }
}
