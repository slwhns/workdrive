<?php

namespace App\Http\Controllers\Preview;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Services\Preview\FilePreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Gate;

class FilePreviewController extends Controller
{
    public function __construct(private FilePreviewService $previewService)
    {
    }

    /**
     * Show the preview modal/view for a file
     */
    public function show(Request $request, string $file): View|JsonResponse
    {
        // Find the file by ID
        $fileModel = File::findOrFail($file);
        
        // Check authorization
        if (!$this->canViewFile($fileModel)) {
            abort(403, 'Unauthorized to view this file');
        }

        $previewData = $this->previewService->getPreviewData($fileModel);
        [$prevFileId, $nextFileId] = $this->getSiblingFileIds($fileModel);

        if ($request->wantsJson() || $request->query('json')) {
            return response()->json([
                'status' => 'success',
                'preview' => $previewData,
                'file' => [
                    'id' => $fileModel->id,
                    'name' => $fileModel->name,
                    'size' => $fileModel->size,
                    'mime_type' => $fileModel->mime_type,
                    'created_at' => $fileModel->created_at,
                    'updated_at' => $fileModel->updated_at,
                ],
                'prev_id' => $prevFileId,
                'next_id' => $nextFileId
            ]);
        }

        return view('preview.show', [
            'file' => $fileModel, 
            'previewData' => $previewData,
            'prevFileId' => $prevFileId,
            'nextFileId' => $nextFileId
        ]);
    }

    /**
     * Get preview data as JSON
     */
    public function getPreviewData(Request $request, string $file): JsonResponse
    {
        try {
            // Find the file by ID
            $fileModel = File::findOrFail($file);
            
            // Check authorization
            if (!$this->canViewFile($fileModel)) {
                abort(403, 'Unauthorized to view this file');
            }

            $previewData = $this->previewService->getPreviewData($fileModel);
            [$prevFileId, $nextFileId] = $this->getSiblingFileIds($fileModel);

            return response()->json([
                'status' => 'success',
                'preview' => $previewData,
                'file' => [
                    'id' => $fileModel->id,
                    'name' => $fileModel->name,
                    'size' => $fileModel->size,
                    'mime_type' => $fileModel->mime_type,
                    'created_at' => $fileModel->created_at,
                    'updated_at' => $fileModel->updated_at,
                    'created_by' => $fileModel->creator->name ?? 'Unknown',
                ],
                'prev_id' => $prevFileId,
                'next_id' => $nextFileId
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get prev and next file IDs
     */
    private function getSiblingFileIds(File $file): array
    {
        $siblings = File::where('parent_id', $file->parent_id)
            ->where('is_folder', false)
            ->where('created_by', $file->created_by)
            ->orderBy('name')
            ->pluck('id')
            ->toArray();

        $currentIndex = array_search($file->id, $siblings);
        $prevId = null;
        $nextId = null;

        if ($currentIndex !== false) {
            if ($currentIndex > 0) {
                $prevId = $siblings[$currentIndex - 1];
            }
            if ($currentIndex < count($siblings) - 1) {
                $nextId = $siblings[$currentIndex + 1];
            }
        }

        return [$prevId, $nextId];
    }

    /**
     * Check if user can view the file
     */
    private function canViewFile(File $file): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }

        // Owner can always view
        if ($file->created_by === $user->id) {
            return true;
        }

        // Check if file is shared with user
        return $file->shares()
            ->where('shared_with', $user->id)
            ->exists();
    }
}
