<?php

namespace App\Services\Import;

use App\Models\Review;
use App\Models\ReviewImport;
use App\Services\Nlp\TextNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class CsvImportService
{
    public const FIELDS = ['body', 'title', 'rating', 'date', 'verified', 'source', 'external_id'];

    /** Accepted header aliases (compared lowercase, non-alphanumerics → underscore). */
    public const ALIASES = [
        'body' => ['review', 'review_text', 'review_body', 'text', 'body', 'content', 'comment', 'comments', 'review_content'],
        'title' => ['title', 'review_title', 'headline', 'summary', 'subject'],
        'rating' => ['rating', 'stars', 'star_rating', 'score', 'review_rating', 'star'],
        'date' => ['date', 'review_date', 'reviewed_at', 'created_at', 'timestamp', 'posted', 'posted_at', 'time'],
        'verified' => ['verified', 'verified_purchase', 'is_verified', 'verified_buyer'],
        'source' => ['source', 'retailer', 'site', 'platform', 'origin', 'channel'],
        'external_id' => ['id', 'review_id', 'external_id', 'source_id', 'reviewid'],
    ];

    public function __construct(private readonly TextNormalizer $normalizer) {}

    public function disk()
    {
        return Storage::disk('local');
    }

    /** Suggest a field mapping from CSV headers. @return array<string,?string> field => header name */
    public function suggestMapping(array $headers): array
    {
        $norm = [];
        foreach ($headers as $h) {
            $norm[$this->slug($h)] = $h;
        }
        $map = [];
        foreach (self::FIELDS as $field) {
            $map[$field] = null;
            foreach (self::ALIASES[$field] as $alias) {
                if (isset($norm[$alias])) {
                    $map[$field] = $norm[$alias];
                    break;
                }
            }
        }

        return $map;
    }

    /** Read and validate header row. @return array{0:string[],1:string} headers, delimiter */
    public function readHeaders(string $path): array
    {
        $fh = $this->open($path);
        try {
            $first = fgets($fh);
            if ($first === false || trim($first) === '') {
                throw new ImportException('The file is empty.');
            }
            $first = $this->stripBom($first);
            if (str_contains($first, "\0")) {
                throw new ImportException('The file looks binary, not a text CSV.');
            }
            $delimiter = $this->detectDelimiter($first);
            $headers = array_map(fn ($h) => trim((string) $h), str_getcsv($first, $delimiter, '"', ''));
            $headers = array_values(array_filter($headers, fn ($h) => $h !== ''));
            if (count($headers) < 1) {
                throw new ImportException('No header row was found.');
            }
            if (count($headers) !== count(array_unique(array_map(fn ($h) => mb_strtolower($h), $headers)))) {
                throw new ImportException('Header names must be unique.');
            }

            return [$headers, $delimiter];
        } finally {
            fclose($fh);
        }
    }

    /**
     * Stream the file, validating each row. Calls $onRow(array $row, ?string $error) for
     * every data row. Memory use is bounded to a single row regardless of file size.
     *
     * @param  callable(int,array,?string):void  $onRow  (line number, normalized row, error)
     */
    public function scan(string $path, array $mapping, callable $onRow): int
    {
        [$headers, $delimiter] = $this->readHeaders($path);
        $this->assertMapping($mapping, $headers);
        $index = array_flip($headers);
        $max = (int) config('reviewlens.import.max_rows');

        $fh = $this->open($path);
        try {
            fgets($fh); // header
            $line = 1;
            $count = 0;
            while (($raw = fgetcsv($fh, 65536, $delimiter, '"', '')) !== false) {
                $line++;
                if ($raw === [null] || $raw === []) {
                    continue; // blank line
                }
                $count++;
                if ($count > $max) {
                    throw new ImportException("The file has more than {$max} data rows. Split it and import in parts.");
                }
                if (count($raw) > count($headers)) {
                    $onRow($line, [], 'Row has more columns than the header.');

                    continue;
                }
                $raw = array_pad($raw, count($headers), '');
                $raw = array_map(fn ($v) => $this->toUtf8((string) $v), $raw);
                $onRow($line, $this->mapRow($raw, $index, $mapping), null);
            }

            return $count;
        } finally {
            fclose($fh);
        }
    }

    /** Validate one mapped row. @return array{0:?array,1:?string} cleaned row, error */
    public function validateRow(array $row): array
    {
        $body = $this->normalizer->clean($row['body'] ?? '');
        if ($reason = $this->normalizer->unusableReason($body)) {
            return [null, $reason];
        }
        $title = $this->normalizer->clean($row['title'] ?? '');
        $title = $title === '' ? null : mb_substr($title, 0, 255);

        $rating = null;
        $rawRating = trim((string) ($row['rating'] ?? ''));
        if ($rawRating !== '') {
            $rating = $this->normalizer->normalizeRating($rawRating);
            if ($rating === null) {
                return [null, "Rating \"".mb_substr($rawRating, 0, 20)."\" is not a valid 0-5 value."];
            }
        }

        $date = null;
        $rawDate = trim((string) ($row['date'] ?? ''));
        if ($rawDate !== '') {
            try {
                $date = Carbon::parse($rawDate);
            } catch (\Throwable) {
                return [null, 'Date "'.mb_substr($rawDate, 0, 30).'" could not be parsed.'];
            }
            if ($date->isFuture() || $date->year < 1990) {
                return [null, 'Date is outside the accepted range.'];
            }
        }

        $verified = match (mb_strtolower(trim((string) ($row['verified'] ?? '')))) {
            '1', 'true', 'yes', 'y', 'verified' => true,
            '0', 'false', 'no', 'n' => false,
            default => null,
        };

        $externalId = trim((string) ($row['external_id'] ?? ''));
        $source = trim($this->normalizer->clean($row['source'] ?? ''));

        return [[
            'body' => $body,
            'title' => $title,
            'rating' => $rating,
            'reviewed_at' => $date?->format('Y-m-d'),
            'verified_purchase' => $verified,
            'source' => $source === '' ? null : mb_substr($source, 0, 100),
            'external_id' => $externalId === '' ? null : mb_substr($externalId, 0, 191),
            'fingerprint' => $this->normalizer->fingerprint($title, $body),
        ], null];
    }

    /**
     * Dry run: counts plus the first rows for the preview screen. Writes nothing.
     *
     * @return array{total:int,accepted:int,rejected:int,duplicates:int,errors:array,sample:array}
     */
    public function preview(ReviewImport $import): array
    {
        $result = ['total' => 0, 'accepted' => 0, 'rejected' => 0, 'duplicates' => 0, 'errors' => [], 'sample' => []];
        $seenFp = [];
        $seenIds = [];
        $limit = (int) config('reviewlens.import.preview_rows');
        $maxErrors = (int) config('reviewlens.import.max_row_errors_stored');

        $result['total'] = $this->scan($this->path($import), $import->mapping ?? [], function ($line, $row, $error) use (&$result, &$seenFp, &$seenIds, $import, $limit, $maxErrors) {
            [$clean, $err] = $error ? [null, $error] : $this->validateRow($row);
            $status = 'accepted';
            if ($err) {
                $status = 'rejected';
                $result['rejected']++;
                if (count($result['errors']) < $maxErrors) {
                    $result['errors'][] = ['row' => $line, 'message' => $err];
                }
            } elseif ($this->isDuplicate($import->product_id, $clean, $seenFp, $seenIds)) {
                $status = 'duplicate';
                $result['duplicates']++;
            } else {
                $result['accepted']++;
            }
            if (count($result['sample']) < $limit) {
                $result['sample'][] = [
                    'row' => $line, 'status' => $status, 'message' => $err,
                    'title' => $clean['title'] ?? ($row['title'] ?? null),
                    'body' => mb_substr($clean['body'] ?? (string) ($row['body'] ?? ''), 0, 200),
                    'rating' => $clean['rating'] ?? null, 'date' => $clean['reviewed_at'] ?? null,
                ];
            }
        });

        return $result;
    }

    /** Persist accepted rows in bounded batches. Throws ImportException on file-level problems. */
    public function import(ReviewImport $import): ReviewImport
    {
        $batch = [];
        $seenFp = [];
        $seenIds = [];
        $counts = ['total' => 0, 'accepted' => 0, 'rejected' => 0, 'duplicates' => 0];
        $errors = [];
        $maxErrors = (int) config('reviewlens.import.max_row_errors_stored');
        $now = now();

        $flush = function () use (&$batch, &$counts, $import, $now) {
            if (! $batch) {
                return;
            }
            $rows = array_map(fn ($r) => array_merge($r, ['source' => $r['source'] ?? $import->source_name]) + [
                'user_id' => $import->user_id, 'product_id' => $import->product_id,
                'review_import_id' => $import->id, 'is_demo' => $import->is_demo,
                'created_at' => $now, 'updated_at' => $now,
            ], $batch);
            $inserted = Review::query()->insertOrIgnore($rows);
            $counts['duplicates'] += count($batch) - $inserted; // lost a race with a unique key
            $counts['accepted'] += $inserted;
            $batch = [];
        };

        $counts['total'] = $this->scan($this->path($import), $import->mapping ?? [], function ($line, $row, $error) use (&$batch, &$counts, &$errors, &$seenFp, &$seenIds, $import, $maxErrors, $flush) {
            [$clean, $err] = $error ? [null, $error] : $this->validateRow($row);
            if ($err) {
                $counts['rejected']++;
                if (count($errors) < $maxErrors) {
                    $errors[] = ['row' => $line, 'message' => $err];
                }

                return;
            }
            if ($this->isDuplicate($import->product_id, $clean, $seenFp, $seenIds)) {
                $counts['duplicates']++;

                return;
            }
            $batch[] = $clean;
            if (count($batch) >= 200) {
                $flush();
            }
        });
        $flush();

        $import->forceFill([
            'status' => 'completed', 'total_rows' => $counts['total'], 'accepted_rows' => $counts['accepted'],
            'rejected_rows' => $counts['rejected'], 'duplicate_rows' => $counts['duplicates'],
            'row_errors' => $errors, 'error' => null, 'completed_at' => now(),
        ])->save();
        $this->discardFile($import);

        return $import;
    }

    public function discardFile(ReviewImport $import): void
    {
        if ($import->stored_path) {
            $this->disk()->delete($import->stored_path);
            $import->forceFill(['stored_path' => null])->save();
        }
    }

    public function path(ReviewImport $import): string
    {
        if (! $import->stored_path || ! $this->disk()->exists($import->stored_path)) {
            throw new ImportException('The uploaded file is no longer available. Please upload it again.');
        }

        return $this->disk()->path($import->stored_path);
    }

    public function assertMapping(array $mapping, array $headers): void
    {
        if (empty($mapping['body'])) {
            throw new ImportException('Map a column to review text. Accepted headers: '.implode(', ', self::ALIASES['body']).'.');
        }
        foreach ($mapping as $field => $header) {
            if ($header !== null && $header !== '' && ! in_array($header, $headers, true)) {
                throw new ImportException("Mapped column \"{$header}\" does not exist in the file.");
            }
            if ($header !== null && $header !== '' && ! in_array($field, self::FIELDS, true)) {
                throw new ImportException('Unknown field mapping.');
            }
        }
    }

    private function isDuplicate(int $productId, array $clean, array &$seenFp, array &$seenIds): bool
    {
        $fp = $clean['fingerprint'];
        $ext = $clean['external_id'];
        if (isset($seenFp[$fp]) || ($ext !== null && isset($seenIds[$ext]))) {
            return true;
        }
        $seenFp[$fp] = true;
        if ($ext !== null) {
            $seenIds[$ext] = true;
        }

        return Review::query()->where('product_id', $productId)
            ->where(fn ($q) => $q->where('fingerprint', $fp)->when($ext !== null, fn ($q) => $q->orWhere('external_id', $ext)))
            ->exists();
    }

    private function mapRow(array $raw, array $index, array $mapping): array
    {
        $row = [];
        foreach (self::FIELDS as $field) {
            $header = $mapping[$field] ?? null;
            $row[$field] = ($header !== null && $header !== '' && isset($index[$header])) ? $raw[$index[$header]] : null;
        }

        return $row;
    }

    private function open(string $path)
    {
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            throw new ImportException('The file could not be read.');
        }

        return $fh;
    }

    private function stripBom(string $s): string
    {
        return str_starts_with($s, "\xEF\xBB\xBF") ? substr($s, 3) : $s;
    }

    private function detectDelimiter(string $line): string
    {
        $best = ',';
        $max = 0;
        foreach ([',', ';', "\t", '|'] as $d) {
            $c = substr_count($line, $d);
            if ($c > $max) {
                $max = $c;
                $best = $d;
            }
        }

        return $best;
    }

    private function toUtf8(string $v): string
    {
        if ($v === '' || mb_check_encoding($v, 'UTF-8')) {
            return $v;
        }

        return mb_convert_encoding($v, 'UTF-8', 'Windows-1252');
    }

    private function slug(string $h): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($h)) ?? '', '_');
    }
}
