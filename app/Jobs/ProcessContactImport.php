<?php

namespace App\Jobs;

use App\Enums\ContactConsentStatus;
use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Services\PhoneNumberNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ProcessContactImport implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $importId)
    {
        $this->onQueue('contact-imports');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("contact-import:{$this->importId}"))->expireAfter(600)];
    }

    public function handle(PhoneNumberNormalizer $phones): void
    {
        $batch = ContactImport::findOrFail($this->importId);
        if (! in_array($batch->status, ['ready', 'queued'])) {
            return;
        }$batch->update(['status' => 'processing']);
        $path = Storage::disk($batch->disk)->path($batch->storage_key);
        $rows = $this->rows($path, $batch->mime_type);
        $map = $batch->mapping ?? [];
        $count = 0;
        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }if (++$count > config('contacts.import_max_rows')) {
                break;
            }try {
                $phone = $phones->normalize((string) ($row[$map['phone'] ?? 0] ?? ''));
                $existing = Contact::withTrashed()->where('tenant_id', $batch->tenant_id)->where('phone_normalized', $phone->e164)->first();
                if ($existing) {
                    if ($batch->duplicate_policy === 'skip_existing' || $batch->duplicate_policy === 'create_only') {
                        $batch->increment('skipped_rows');

                        continue;
                    }$existing->update(array_filter(['first_name' => $row[$map['first_name'] ?? -1] ?? null, 'last_name' => $row[$map['last_name'] ?? -1] ?? null, 'display_name' => $row[$map['display_name'] ?? -1] ?? null, 'email' => $row[$map['email'] ?? -1] ?? null]));
                    $batch->increment('updated_rows');
                } else {
                    Contact::create(['tenant_id' => $batch->tenant_id, 'created_by' => $batch->created_by, 'phone_input' => $phone->input, 'phone_normalized' => $phone->e164, 'whatsapp_address' => $phone->whatsappAddress, 'first_name' => $row[$map['first_name'] ?? -1] ?? null, 'last_name' => $row[$map['last_name'] ?? -1] ?? null, 'display_name' => $row[$map['display_name'] ?? -1] ?? null, 'email' => $row[$map['email'] ?? -1] ?? null, 'status' => ContactStatus::Active, 'consent_status' => ContactConsentStatus::Unknown, 'source' => ContactSource::Import, 'source_reference' => $batch->uuid]);
                    $batch->increment('created_rows');
                }$batch->increment('processed_rows');
            } catch (Throwable) {
                $batch->increment('failed_rows');
            }
        }$batch->update(['status' => 'completed', 'total_rows' => $count, 'completed_at' => now()]);
    }

    private function rows(string $path, string $mime): array
    {
        if (str_contains($mime, 'csv') || str_ends_with(strtolower($path), '.csv')) {
            $h = fopen($path, 'rb');
            $rows = [];
            while (($r = fgetcsv($h)) !== false) {
                $rows[] = $r;
            }fclose($h);

            return $rows;
        }if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('XLSX support requires ZipArchive.');
        }$zip = new \ZipArchive;
        $zip->open($path);
        $shared = [];
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml) {
            foreach (simplexml_load_string($xml)->si as $s) {
                $shared[] = (string) $s->t;
            }
        }$sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $v = (string) $cell->v;
                $values[] = (string) $cell['t'] === 's' ? ($shared[(int) $v] ?? '') : $v;
            }$rows[] = $values;
        }$zip->close();

        return $rows;
    }
}
