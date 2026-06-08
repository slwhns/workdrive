<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class OnlyOfficeController extends Controller
{
    /**
     * Get OnlyOffice configuration for the given file.
     */
    public function getConfig(File $file)
    {
        $file->update(['accessed_at' => now()]);

        $jwtSecret = config('onlyoffice.jwt_secret');

        // Generate a secure signed URL for OnlyOffice to download the file.
        // It does not require login but signature is validated.
        $downloadUrl = URL::temporarySignedRoute(
            'onlyoffice.download',
            now()->addHours(12),
            ['file' => $file->id]
        );

        $callbackUrl = route('onlyoffice.callback');
        $ext = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
        $docType = $this->getDocType($ext);

        $mode = request()->query('mode', 'edit');
        if (!in_array($mode, ['edit', 'view'])) {
            $mode = 'edit';
        }
        $canEdit = ($mode === 'edit');

        $config = [
            'document' => [
                'fileType' => $ext,
                'key' => (string) $file->id . '_' . $file->updated_at->timestamp,
                'title' => $file->name,
                'url' => $downloadUrl,
                'permissions' => [
                    'comment' => true,
                    'copy' => true,
                    'download' => true,
                    'edit' => $canEdit,
                    'print' => true,
                    'fillForms' => $canEdit,
                    'modifyFilter' => $canEdit,
                    'modifyContentControl' => $canEdit,
                    'review' => $canEdit,
                ],
            ],
            'documentType' => $docType,
            'editorConfig' => [
                'callbackUrl' => $callbackUrl,
                'user' => [
                    'id' => (string) auth()->id(),
                    'name' => auth()->user()->name,
                ],
                'mode' => $mode,
                'lang' => 'en',
                'customization' => [
                    'autosave' => true,
                    'chat' => true,
                    'comments' => true,
                    'compactHeader' => false,
                    'compactToolbar' => false,
                    'feedback' => false,
                    'forcesave' => true,
                    'help' => false,
                ],
            ],
        ];

        // Ensure parentOrigin is set and forced to HTTPS so ONLYOFFICE's reverse proxy accepts the request.
        $origin = request()->getSchemeAndHttpHost();
        $parentOrigin = str_starts_with($origin, 'http://')
            ? str_replace('http://', 'https://', $origin)
            : $origin;
        $config['parentOrigin'] = $parentOrigin;

        if ($jwtSecret) {
            $config['token'] = $this->encodeJwt($config, $jwtSecret);
        }

        return response()->json($config);
    }

    /**
     * Download the file for OnlyOffice. Securely validates signature.
     */
    public function download(Request $request, File $file)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired signature.');
        }

        if ($file->is_folder) {
            abort(400, 'Cannot download a folder.');
        }

        if (! Storage::disk('public')->exists($file->storage_path)) {
            abort(404, 'File not found in storage.');
        }

        return Storage::disk('public')->download($file->storage_path, $file->name);
    }

    /**
     * Handle ONLYOFFICE callback events (saving, status updates).
     */
    public function callback(Request $request)
    {
        $jwtSecret = config('onlyoffice.jwt_secret');
        $token = null;

        if ($request->has('token')) {
            $token = $request->input('token');
        } elseif ($request->hasHeader('Authorization')) {
            $authHeader = $request->header('Authorization');
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }

        $data = $request->all();

        // If JWT token is present, we decode and verify it
        if ($jwtSecret && $token) {
            try {
                $payload = $this->decodeJwt($token, $jwtSecret);
                if (isset($payload['payload'])) {
                    $data = $payload['payload'];
                } elseif (isset($payload['status'])) {
                    $data = $payload;
                }
            } catch (\Exception $e) {
                return response()->json(['error' => 1, 'message' => 'Invalid JWT signature: ' . $e->getMessage()]);
            }
        }

        $status = $data['status'] ?? null;
        $key = $data['key'] ?? '';
        $downloadUrl = $data['url'] ?? null;

        // Parse file ID from key: "<file_id>_<timestamp>"
        $fileId = head(explode('_', $key));

        // Status 2: Document is ready for saving (save button clicked or document closed)
        // Status 6: Document is ready for saving (forcesave triggered)
        if (in_array($status, [2, 6]) && $downloadUrl) {
            $file = File::find($fileId);
            if (!$file) {
                return response()->json(['error' => 1, 'message' => 'File not found']);
            }

            // Fetch the updated document content from OnlyOffice Document Server
            $contextOptions = [
                "ssl" => [
                    "verify_peer" => false,
                    "verify_peer_name" => false,
                ]
            ];
            $contents = @file_get_contents($downloadUrl, false, stream_context_create($contextOptions));
            if ($contents === false) {
                return response()->json(['error' => 1, 'message' => 'Failed to download edited file from DocServer']);
            }

            // Save back to public disk
            Storage::disk('public')->put($file->path, $contents);
            
            // Update database attributes
            $file->size = strlen($contents);
            $file->touch(); // updates updated_at timestamp
            $file->save();
        }

        return response()->json(['error' => 0]);
    }

    /**
     * Helpers for local JWT implementation
     */
    private function encodeJwt(array $payload, string $secret): string
    {
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $payloadJson = json_encode($payload);

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payloadJson);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    private function decodeJwt(string $token, string $secret): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \Exception('Invalid token structure');
        }

        list($headerB64, $payloadB64, $signatureB64) = $parts;

        $signature = self::base64UrlDecode($signatureB64);
        $expectedSignature = hash_hmac('sha256', $headerB64 . '.' . $payloadB64, $secret, true);

        if (!hash_equals($signature, $expectedSignature)) {
            throw new \Exception('Signature verification failed');
        }

        $payloadJson = self::base64UrlDecode($payloadB64);
        return json_decode($payloadJson, true) ?: [];
    }

    private static function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }

    private function getDocType(string $ext): string
    {
        $wordExtensions = ['docx', 'doc', 'docm', 'dot', 'dotx', 'epub', 'fodt', 'htm', 'html', 'mht', 'mhtml', 'odt', 'ott', 'pdf', 'rtf', 'txt', 'djvu', 'xps', 'oxps'];
        $cellExtensions = ['xlsx', 'xls', 'xlsm', 'xlt', 'xltx', 'csv', 'fods', 'ods', 'ots'];
        $slideExtensions = ['pptx', 'ppt', 'pptm', 'pot', 'potx', 'pps', 'ppsx', 'fodp', 'odp', 'otp'];

        if (in_array($ext, $wordExtensions)) {
            return 'word';
        }
        if (in_array($ext, $cellExtensions)) {
            return 'cell';
        }
        if (in_array($ext, $slideExtensions)) {
            return 'slide';
        }

        return 'word';
    }

    /**
     * Generate document thumbnail from OnlyOffice conversion service.
     */
    public function thumbnail(\Illuminate\Http\Request $request, File $file)
    {
        if ($file->is_folder) {
            abort(400, 'Cannot generate thumbnail for a folder.');
        }

        $ext = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
        $officeExtensions = [
            'docx', 'doc', 'docm', 'dot', 'dotx', 'epub', 'fodt', 'htm', 'html', 'mht', 'mhtml', 'odt', 'ott', 'rtf', 'txt', 'djvu', 'xps', 'oxps',
            'xlsx', 'xls', 'xlsm', 'xlt', 'xltx', 'csv', 'fods', 'ods', 'ots',
            'pptx', 'ppt', 'pptm', 'pot', 'potx', 'pps', 'ppsx', 'fodp', 'odp', 'otp', 'pdf'
        ];

        if (!in_array($ext, $officeExtensions)) {
            abort(400, 'Unsupported file type for thumbnail generation.');
        }

        // Cache file name in public/thumbnails directory
        $thumbnailDir = 'thumbnails';
        $thumbnailPath = $thumbnailDir . '/' . $file->id . '_' . $file->updated_at->timestamp . '.png';

        // Check if thumbnail already exists in public disk
        if (Storage::disk('public')->exists($thumbnailPath)) {
            return response()->file(Storage::disk('public')->path($thumbnailPath));
        }

        // Expiration: Clean up older thumbnails for this file
        try {
            $oldFiles = Storage::disk('public')->files($thumbnailDir);
            foreach ($oldFiles as $oldFile) {
                if (str_starts_with(basename($oldFile), $file->id . '_')) {
                    Storage::disk('public')->delete($oldFile);
                }
            }
        } catch (\Exception $e) {
            // Ignore directory missing errors
        }

        // Generate temporary signed URL for OnlyOffice to download the file
        $downloadUrl = URL::temporarySignedRoute(
            'onlyoffice.download',
            now()->addHours(1),
            ['file' => $file->id]
        );

        $onlyOfficeUrl = config('onlyoffice.url', 'https://onlyoffice.khaleefapps.com');
        $converterUrl = rtrim($onlyOfficeUrl, '/') . '/converter';
        $jwtSecret = config('onlyoffice.jwt_secret');

        $payload = [
            'async' => false,
            'filetype' => $ext,
            'key' => (string) $file->id . '_' . $file->updated_at->timestamp,
            'outputtype' => 'png',
            'thumbnail' => [
                'first' => true,
                'width' => 400,
                'height' => 500
            ],
            'title' => $file->name,
            'url' => $downloadUrl,
        ];

        if ($jwtSecret) {
            $payload['token'] = $this->encodeJwt($payload, $jwtSecret);
        }

        // Send POST request to ONLYOFFICE conversion service
        try {
            $headers = [
                'Accept: application/json',
                'Content-Type: application/json',
            ];
            if ($jwtSecret) {
                // Some ONLYOFFICE setups require the token in the header
                $headers[] = 'Authorization: Bearer ' . $this->encodeJwt($payload, $jwtSecret);
            }

            $contextOptions = [
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $headers),
                    'content' => json_encode($payload),
                    'ignore_errors' => true,
                    'timeout' => 15,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ];

            $response = file_get_contents($converterUrl, false, stream_context_create($contextOptions));
            if ($response === false) {
                throw new \Exception('Failed to connect to OnlyOffice Converter.');
            }

            $responseData = json_decode($response, true);
            if (!isset($responseData['fileUrl'])) {
                throw new \Exception('OnlyOffice Converter response error: ' . ($responseData['error'] ?? $response));
            }

            // Fetch the generated PNG thumbnail
            $imgContext = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);
            $imageContents = file_get_contents($responseData['fileUrl'], false, $imgContext);
            if ($imageContents === false) {
                throw new \Exception('Failed to download thumbnail image.');
            }

            // Save to public storage
            Storage::disk('public')->put($thumbnailPath, $imageContents);

            return response()->file(Storage::disk('public')->path($thumbnailPath));

        } catch (\Exception $e) {
            // Log error and return 404
            logger()->error('Thumbnail generation failed: ' . $e->getMessage());
            abort(404, 'Thumbnail not available.');
        }
    }
}
