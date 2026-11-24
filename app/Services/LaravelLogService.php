<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class LaravelLogService
{
    /**
     * Maximum bytes read from the tail of a log file so large production logs stay fast and memory-safe.
     */
    public const DEFAULT_MAX_READ_BYTES = 2_097_152; // 2 MB

    /**
     * Regex matching a standard Laravel / Monolog entry header line.
     */
    private const ENTRY_HEADER_PATTERN = '/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:?\d{2}|Z)?)\]\s+([A-Za-z0-9_.-]+)\.([A-Z]+):\s*(.*)$/';

    /**
     * Log levels grouped under the "error" filter.
     *
     * @var list<string>
     */
    private const ERROR_LEVELS = ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR'];

    /**
     * Log levels grouped under the "info" filter.
     *
     * @var list<string>
     */
    private const INFO_LEVELS = ['NOTICE', 'INFO'];

    private string $logDirectory;

    public function __construct(?string $logDirectory = null)
    {
        $this->logDirectory = $logDirectory ?? storage_path('logs');
    }

    /**
     * List all readable .log files in storage/logs sorted by latest modification time first.
     *
     * @return list<array{name: string, size: int, modified_at: string}>
     */
    public function listFiles(): array
    {
        if (! is_dir($this->logDirectory)) {
            return [];
        }

        $items = scandir($this->logDirectory);
        if ($items === false) {
            return [];
        }

        $files = [];
        foreach ($items as $item) {
            if (! $this->isValidFilename($item)) {
                continue;
            }

            $path = $this->resolvePath($item);
            if ($path === null) {
                continue;
            }

            $mtime = filemtime($path) ?: time();
            $files[] = [
                'name' => $item,
                'size' => (int) (filesize($path) ?: 0),
                'modified_at' => Carbon::createFromTimestamp($mtime)->toIso8601String(),
                '_mtime' => $mtime,
            ];
        }

        usort($files, static fn (array $a, array $b): int => $b['_mtime'] <=> $a['_mtime'] ?: strcmp($a['name'], $b['name']));

        return array_map(static fn (array $file): array => [
            'name' => $file['name'],
            'size' => $file['size'],
            'modified_at' => $file['modified_at'],
        ], $files);
    }

    /**
     * Resolve and validate a log filename strictly within the configured logs directory.
     */
    public function resolvePath(string $filename): ?string
    {
        if (! $this->isValidFilename($filename)) {
            return null;
        }

        $baseReal = realpath($this->logDirectory);
        if ($baseReal === false) {
            return null;
        }

        $candidate = realpath($baseReal.DIRECTORY_SEPARATOR.$filename);
        if ($candidate === false || ! is_file($candidate)) {
            return null;
        }

        $prefix = rtrim($baseReal, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (! str_starts_with($candidate, $prefix)) {
            return null;
        }

        return $candidate;
    }

    /**
     * Read, parse, filter, and paginate entries from the given log file (or the newest available log file).
     *
     * @return array{
     *     files: list<array{name: string, size: int, modified_at: string}>,
     *     current_file: ?string,
     *     file_size: int,
     *     read_bytes: int,
     *     truncated: bool,
     *     summary: array{total: int, error: int, warning: int, info: int, debug: int},
     *     entries: list<array{id: string, timestamp: string, env: string, level: string, category: string, message: string, context: string, has_details: bool}>,
     *     meta: array{page: int, per_page: int, total: int, last_page: int}
     * }
     */
    public function read(
        ?string $requestedFile = null,
        string $level = 'all',
        string $search = '',
        int $page = 1,
        int $perPage = 50,
        int $maxReadBytes = self::DEFAULT_MAX_READ_BYTES,
    ): array {
        $files = $this->listFiles();
        $perPage = max(10, min(200, $perPage));
        $page = max(1, $page);

        $emptySummary = ['total' => 0, 'error' => 0, 'warning' => 0, 'info' => 0, 'debug' => 0];

        if ($files === []) {
            return [
                'files' => [],
                'current_file' => null,
                'file_size' => 0,
                'read_bytes' => 0,
                'truncated' => false,
                'summary' => $emptySummary,
                'entries' => [],
                'meta' => ['page' => 1, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1],
            ];
        }

        $currentFile = $requestedFile !== null && $requestedFile !== ''
            ? $requestedFile
            : $files[0]['name'];

        $path = $this->resolvePath($currentFile);
        if ($path === null) {
            return [
                'files' => $files,
                'current_file' => null,
                'file_size' => 0,
                'read_bytes' => 0,
                'truncated' => false,
                'summary' => $emptySummary,
                'entries' => [],
                'meta' => ['page' => 1, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1],
            ];
        }

        [$content, $fileSize, $readBytes, $truncated] = $this->readTail($path, $maxReadBytes);
        $parsedEntries = $this->parseEntries($content, $truncated);

        $summary = $emptySummary;
        $summary['total'] = count($parsedEntries);
        foreach ($parsedEntries as $entry) {
            $category = $entry['category'];
            if (isset($summary[$category])) {
                $summary[$category]++;
            }
        }

        $normalizedLevel = strtolower(trim($level));
        $normalizedSearch = mb_strtolower(trim($search));

        $filtered = array_values(array_filter(
            $parsedEntries,
            static function (array $entry) use ($normalizedLevel, $normalizedSearch): bool {
                if ($normalizedLevel !== '' && $normalizedLevel !== 'all') {
                    if ($entry['category'] !== $normalizedLevel && strtolower($entry['level']) !== $normalizedLevel) {
                        return false;
                    }
                }

                if ($normalizedSearch !== '') {
                    $haystack = mb_strtolower(
                        $entry['timestamp'].' '.$entry['level'].' '.$entry['env'].' '.$entry['message'].' '.$entry['context']
                    );

                    return str_contains($haystack, $normalizedSearch);
                }

                return true;
            }
        ));

        $totalFiltered = count($filtered);
        $lastPage = max(1, (int) ceil($totalFiltered / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        return [
            'files' => $files,
            'current_file' => $currentFile,
            'file_size' => $fileSize,
            'read_bytes' => $readBytes,
            'truncated' => $truncated,
            'summary' => $summary,
            'entries' => array_slice($filtered, $offset, $perPage),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalFiltered,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * Truncate the selected log file to 0 bytes without deleting the file itself.
     */
    public function clear(string $filename): bool
    {
        $path = $this->resolvePath($filename);
        if ($path === null) {
            return false;
        }

        return file_put_contents($path, '', LOCK_EX) !== false;
    }

    private function isValidFilename(string $filename): bool
    {
        if ($filename === '' || str_contains($filename, '..')) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9._-]+\.log$/', $filename) === 1;
    }

    /**
     * Read up to $maxReadBytes from the end of a file.
     *
     * @return array{0: string, 1: int, 2: int, 3: bool}
     */
    private function readTail(string $path, int $maxReadBytes): array
    {
        $fileSize = (int) (filesize($path) ?: 0);
        if ($fileSize === 0) {
            return ['', 0, 0, false];
        }

        $truncated = $fileSize > $maxReadBytes;
        $bytesToRead = $truncated ? $maxReadBytes : $fileSize;

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return ['', $fileSize, 0, $truncated];
        }

        try {
            if ($truncated) {
                fseek($handle, -$bytesToRead, SEEK_END);
            }

            $content = (string) fread($handle, $bytesToRead);
        } finally {
            fclose($handle);
        }

        return [$content, $fileSize, strlen($content), $truncated];
    }

    /**
     * Parse raw log text into structured newest-first entries.
     *
     * @return list<array{id: string, timestamp: string, env: string, level: string, category: string, message: string, context: string, has_details: bool}>
     */
    private function parseEntries(string $content, bool $truncated): array
    {
        if (trim($content) === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

        // Saat memotong dari akhir file besar, buang potongan baris pertama yang mungkin terpotong di tengah.
        if ($truncated && count($lines) > 1) {
            array_shift($lines);
        }

        $entries = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match(self::ENTRY_HEADER_PATTERN, $line, $matches) === 1) {
                if ($current !== null) {
                    $entries[] = $this->finalizeEntry($current, count($entries));
                }

                $level = strtoupper($matches[3]);
                $current = [
                    'timestamp' => $matches[1],
                    'env' => $matches[2],
                    'level' => $level,
                    'category' => $this->categorizeLevel($level),
                    'message' => trim($matches[4]),
                    'extra_lines' => [],
                ];

                continue;
            }

            if ($current !== null) {
                $current['extra_lines'][] = $line;
            }
        }

        if ($current !== null) {
            $entries[] = $this->finalizeEntry($current, count($entries));
        }

        // Fallback untuk file log non-standar Monolog (mis. browser.log / raw output)
        if ($entries === []) {
            $nonEmptyLines = array_values(array_filter(
                array_map('trim', $lines),
                static fn (string $line): bool => $line !== ''
            ));
            $tailLines = array_slice($nonEmptyLines, -500);

            foreach (array_reverse($tailLines) as $index => $line) {
                $entries[] = [
                    'id' => 'raw-'.$index,
                    'timestamp' => '',
                    'env' => '',
                    'level' => 'INFO',
                    'category' => 'info',
                    'message' => $line,
                    'context' => '',
                    'has_details' => mb_strlen($line) > 220,
                ];
            }

            return $entries;
        }

        return array_reverse($entries);
    }

    /**
     * @param  array{timestamp: string, env: string, level: string, category: string, message: string, extra_lines: list<string>}  $raw
     * @return array{id: string, timestamp: string, env: string, level: string, category: string, message: string, context: string, has_details: bool}
     */
    private function finalizeEntry(array $raw, int $index): array
    {
        $context = trim(implode("\n", $raw['extra_lines']));

        return [
            'id' => $raw['timestamp'].'-'.$index,
            'timestamp' => $raw['timestamp'],
            'env' => $raw['env'],
            'level' => $raw['level'],
            'category' => $raw['category'],
            'message' => $raw['message'],
            'context' => $context,
            'has_details' => $context !== '' || mb_strlen($raw['message']) > 220,
        ];
    }

    private function categorizeLevel(string $level): string
    {
        if (in_array($level, self::ERROR_LEVELS, true)) {
            return 'error';
        }

        if ($level === 'WARNING') {
            return 'warning';
        }

        if (in_array($level, self::INFO_LEVELS, true)) {
            return 'info';
        }

        return 'debug';
    }
}
