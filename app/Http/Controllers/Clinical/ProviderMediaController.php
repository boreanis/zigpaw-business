<?php

namespace App\Http\Controllers\Clinical;

use App\Exceptions\PlatformApiException;
use App\Http\Controllers\Controller;
use App\Services\PlatformApiClient;
use App\Support\ClinicalPortalAccessTokenStore;
use Illuminate\Http\Client\Response;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProviderMediaController extends Controller
{
    public function __invoke(
        string $grantId,
        string $mediaId,
        PlatformApiClient $api,
        ClinicalPortalAccessTokenStore $tokens,
    ): StreamedResponse|RedirectResponse {
        $accessToken = $tokens->accessToken();
        $organizationId = session('portal.organization_id');

        if (! is_string($accessToken) || $accessToken === '' || ! is_string($organizationId) || $organizationId === '') {
            return redirect()->route('clinical.dashboard')->with('error', 'Your secure clinical session has ended. Please sign in again.');
        }

        try {
            $upstream = $api->clinicalProviderMediaDownload($tokens, $organizationId, $grantId, $mediaId);
        } catch (PlatformApiException $exception) {
            if ($exception->status === 401) {
                $tokens->forget();
                session()->forget(['portal.organization_id', 'portal.organization_name']);
            }

            return redirect()->route('clinical.patients.show', ['grantId' => $grantId])->with(
                'error',
                match ($exception->status) {
                    401 => 'Your secure clinical session has ended. Please sign in again.',
                    403 => 'This family has not shared that file with this clinical team.',
                    404 => 'That shared file is no longer available.',
                    422 => 'The shared file request was not accepted.',
                    default => 'The shared file is temporarily unavailable.',
                },
            );
        }

        $contentType = strtolower(trim((string) strtok($upstream->header('Content-Type'), ';')));
        $headers = [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="clinical-media-'.$mediaId.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        $contentLength = $upstream->header('Content-Length');
        if (preg_match('/^[0-9]+$/D', $contentLength) === 1) {
            $headers['Content-Length'] = $contentLength;
        }

        try {
            /** @var resource $stream */
            $stream = $this->prepareMediaStream($upstream, $contentLength);
        } catch (\Throwable) {
            return redirect()->route('clinical.patients.show', ['grantId' => $grantId])->with(
                'error',
                'The shared file is temporarily unavailable.',
            );
        }

        return response()->stream(function () use ($stream): void {
            try {
                while (! feof($stream)) {
                    if (connection_aborted()) {
                        break;
                    }

                    $chunk = fread($stream, 8192);
                    if ($chunk === false) {
                        break;
                    }

                    if ($chunk !== '') {
                        echo $chunk;
                    }
                }
            } finally {
                fclose($stream);
            }
        }, 200, $headers);
    }

    /** @return resource */
    private function prepareMediaStream(Response $upstream, string $contentLength): mixed
    {
        $source = $upstream->resource();
        if (! is_resource($source)) {
            throw new \RuntimeException('The clinical media stream was unavailable.');
        }

        $buffer = fopen('php://temp/maxmemory:1048576', 'w+b');
        if (! is_resource($buffer)) {
            fclose($source);
            throw new \RuntimeException('The clinical media buffer was unavailable.');
        }

        $bytes = 0;
        $keepBuffer = false;

        try {
            while (! feof($source)) {
                if (connection_aborted()) {
                    throw new \RuntimeException('The clinical media stream was interrupted.');
                }

                $chunk = fread($source, 8192);
                if ($chunk === false) {
                    throw new \RuntimeException('The clinical media stream could not be read.');
                }

                if ($chunk === '') {
                    if (feof($source)) {
                        break;
                    }

                    throw new \RuntimeException('The clinical media stream ended unexpectedly.');
                }

                $bytes += strlen($chunk);
                if ($bytes > PlatformApiClient::MAX_CLINICAL_MEDIA_BYTES) {
                    throw new \RuntimeException('The clinical media stream exceeded its size limit.');
                }

                $offset = 0;
                $length = strlen($chunk);
                while ($offset < $length) {
                    $written = fwrite($buffer, substr($chunk, $offset));
                    if ($written === false || $written === 0) {
                        throw new \RuntimeException('The clinical media buffer could not be written.');
                    }

                    $offset += $written;
                }
            }

            if ($bytes === 0) {
                throw new \RuntimeException('The clinical media stream was empty.');
            }

            if (preg_match('/^[0-9]+$/D', $contentLength) === 1 && $bytes !== (int) $contentLength) {
                throw new \RuntimeException('The clinical media stream was truncated.');
            }

            rewind($buffer);
            $keepBuffer = true;

            return $buffer;
        } finally {
            fclose($source);
            if (! $keepBuffer) {
                fclose($buffer);
            }
        }
    }
}
