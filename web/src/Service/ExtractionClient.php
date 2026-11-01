<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Http\Client;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;
use function Cake\Core\env;

/**
 * Turns an uploaded file into structured data.
 * CSV/text statements are parsed in-process; PDFs are sent to the extraction API.
 * Uploaded content only lives in memory / a temp file that is always deleted.
 */
class ExtractionClient
{
    public function __construct(private ?string $baseUrl = null, private ?Client $http = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? (string)(env('API_URL') ?: 'http://api:5000'), '/');
    }

    /**
     * @return array{lines: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    public function statement(UploadedFileInterface $file): array
    {
        if (!self::isPdf($file)) {
            return (new StatementParser())->parse((string)$file->getStream());
        }
        $data = $this->callApi('statement', $file);
        if ($data === null) {
            return ['lines' => [], 'errors' => ["Extraction PDF indisponible : importez un CSV ou collez le texte du relevé."]];
        }
        $lines = [];
        foreach ($data['lines'] ?? [] as $l) {
            $date = StatementParser::parseDate((string)($l['booked_on'] ?? ''));
            if ($date !== null) {
                $lines[] = [
                    'booked_on' => $date,
                    'label' => (string)($l['label'] ?? ''),
                    'debit_cents' => abs((int)($l['debit_cents'] ?? 0)),
                    'credit_cents' => abs((int)($l['credit_cents'] ?? 0)),
                ];
            }
        }

        return ['lines' => $lines, 'errors' => array_map('strval', $data['warnings'] ?? [])];
    }

    /**
     * @return array{fields: array<string, mixed>, errors: array<int, string>}
     */
    public function payslip(UploadedFileInterface $file): array
    {
        if (!self::isPdf($file)) {
            return ['fields' => [], 'errors' => ['Le bulletin doit être un PDF : complétez les champs manuellement.']];
        }
        $data = $this->callApi('payslip', $file);
        if ($data === null) {
            return ['fields' => [], 'errors' => ['Extraction PDF indisponible : saisissez le bulletin manuellement.']];
        }

        return ['fields' => (array)($data['fields'] ?? []), 'errors' => array_map('strval', $data['warnings'] ?? [])];
    }

    public static function isPdf(UploadedFileInterface $file): bool
    {
        $name = strtolower((string)$file->getClientFilename());

        return str_ends_with($name, '.pdf') || str_starts_with((string)$file->getStream()->read(5), '%PDF');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function callApi(string $kind, UploadedFileInterface $file): ?array
    {
        $file->getStream()->rewind();
        $tmp = tempnam(sys_get_temp_dir(), 'bm');
        if ($tmp === false) {
            return null;
        }
        try {
            file_put_contents($tmp, (string)$file->getStream());
            $handle = fopen($tmp, 'rb');
            $headers = [];
            if ($token = env('API_TOKEN')) {
                $headers['Authorization'] = 'Bearer ' . $token;
            }
            $response = ($this->http ?? new Client(['timeout' => 60]))->post(
                $this->baseUrl . '/extract/' . $kind,
                ['file' => $handle],
                ['headers' => $headers],
            );
            if (is_resource($handle)) {
                fclose($handle);
            }

            return $response->isOk() ? (array)$response->getJson() : null;
        } catch (Throwable) {
            return null;
        } finally {
            @unlink($tmp);
        }
    }
}
