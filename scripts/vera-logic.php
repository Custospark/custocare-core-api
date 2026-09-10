<?php

/**
 * Vera Logic - Backend repo rules & contracts (not php -l).
 * Usage: php scripts/vera-logic.php
 * Also runs from vera-fast.php after syntax checks.
 * Ported from Custosell; rules below are the generic subset plus
 * Custocare payment atomicity (gateway-atomic-approval).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

const VERA_MAX_LINES = 500;

/**
 * @return list<string>
 */
function veraLogicChangedPhpFiles(string $root): array
{
    $commands = [
        'git diff --name-only --diff-filter=ACMRTUXB HEAD',
        'git diff --cached --name-only --diff-filter=ACMRTUXB',
        'git ls-files --others --exclude-standard',
    ];

    $files = [];

    foreach ($commands as $command) {
        $output = shell_exec($command . ' 2>nul') ?? shell_exec($command . ' 2>/dev/null') ?? '';
        foreach (preg_split('/\R/', trim($output)) as $path) {
            if ($path === '' || !str_ends_with($path, '.php')) {
                continue;
            }
            $normalized = str_replace('\\', '/', $path);
            if (!str_starts_with($normalized, 'app/') && !str_starts_with($normalized, 'tests/')) {
                continue;
            }
            $full = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            if (is_file($full)) {
                $files[$normalized] = $normalized;
            }
        }
    }

    return array_values($files);
}

function veraLogicRead(string $root, string $rel): ?string
{
    $full = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
    if (!is_file($full)) {
        return null;
    }

    return file_get_contents($full) ?: '';
}

function veraLogicHeadLineCount(string $root, string $rel): ?int
{
    $output = shell_exec('git show HEAD:' . escapeshellarg($rel) . ' 2>nul') ?? '';
    if ($output === '') {
        $output = shell_exec('git show HEAD:' . escapeshellarg($rel) . ' 2>/dev/null') ?? '';
    }
    if ($output === '' || $output === null) {
        return null; // new file - enforce strictly
    }

    return substr_count($output, "\n");
}

function veraLogicLineCount(string $root, string $rel): int
{
    $text = veraLogicRead($root, $rel);
    if ($text === null) {
        return 0;
    }

    return substr_count($text, "\n") + (str_ends_with($text, "\n") ? 0 : 1);
}

/**
 * File size: new/previously-compliant files must stay within the limit.
 * Files already over the limit at HEAD are grandfathered (splitting them
 * is separate tech-debt work, not a gate on every change).
 *
 * @return list<array{id: string, ok: bool, detail: string}>
 */
function veraLogicCheckFileSize(string $root, array $changed): array
{
    $results = [];
    foreach ($changed as $file) {
        $lines = veraLogicLineCount($root, $file);
        if ($lines <= VERA_MAX_LINES) {
            continue;
        }
        $headLines = veraLogicHeadLineCount($root, $file);
        if ($headLines !== null && $headLines > VERA_MAX_LINES) {
            continue; // grandfathered - was already over at HEAD
        }
        $results[] = [
            'id' => 'file-size-500',
            'ok' => false,
            'detail' => "{$file} has {$lines} lines (max " . VERA_MAX_LINES . ') - split into smaller units',
        ];
    }

    if ($results === []) {
        $results[] = [
            'id' => 'file-size-500',
            'ok' => true,
            'detail' => $changed === []
                ? 'No changed app/tests PHP - size check skipped'
                : 'Changed app/tests PHP within ' . VERA_MAX_LINES . ' lines (' . count($changed) . ' checked)',
        ];
    }

    return $results;
}

/**
 * @return array{id: string, ok: bool, detail: string}
 */
function veraLogicNoLongDashes(string $root): array
{
    $offenders = [];
    foreach (veraLogicChangedPhpFiles($root) as $file) {
        // Config + scripts carry their own checks; scope here mirrors vera-fast (app/tests).
        $text = veraLogicRead($root, $file);
        if ($text === null) {
            continue;
        }
        if (preg_match('/[\x{2014}\x{2013}]/u', $text)) {
            $offenders[] = $file;
        }
    }

    if ($offenders !== []) {
        $shown = array_slice($offenders, 0, 8);
        $extra = count($offenders) > 8 ? ' (+' . (count($offenders) - 8) . ' more)' : '';

        return [
            'id' => 'no-long-dashes',
            'ok' => false,
            'detail' => 'Long dash (em/en) found in changed file(s): ' . implode(', ', $shown) . $extra . ' - run the dash normalizer, use a plain hyphen instead',
        ];
    }

    return [
        'id' => 'no-long-dashes',
        'ok' => true,
        'detail' => 'No em/en dashes in changed files',
    ];
}

/**
 * Payment atomicity: subscription and status changes must commit atomically
 * with the payment approval - never independently.
 *
 * Guards every approval path: gateway auto-approve (webhook/callback/poll),
 * the local bypass, and manual admin approval.
 *
 * @return array{id: string, ok: bool, detail: string}
 */
function veraLogicGatewayAtomicApproval(string $root): array
{
    $failures = [];

    $service = veraLogicRead($root, 'app/Services/Billing/Gateways/GatewayService.php') ?? '';
    $trait = veraLogicRead($root, 'app/Services/Billing/Gateways/Concerns/FinalizesGatewayApprovals.php') ?? '';

    if ($service === '' || str_contains($service, 'use FinalizesGatewayApprovals;') === false) {
        $failures[] = 'GatewayService must use FinalizesGatewayApprovals (single approval path)';
    }
    if (substr_count($service, 'DB::transaction') < 2) {
        $failures[] = 'GatewayService must wrap auto-approve AND bypass approval in DB::transaction';
    }
    if ($trait === '' || str_contains($trait, 'function finalizeApprovedPayment') === false) {
        $failures[] = 'FinalizesGatewayApprovals trait with finalizeApprovedPayment() is required';
    }

    $manual = veraLogicRead($root, 'app/Services/Billing/PaymentService.php') ?? '';
    if ($manual === '' || str_contains($manual, 'function approvePayment') === false) {
        $failures[] = 'PaymentService::approvePayment() is required';
    } else {
        $approvePos = strpos($manual, 'function approvePayment');
        $approveBody = substr($manual, $approvePos, 3000);
        if (str_contains($approveBody, 'DB::transaction') === false) {
            $failures[] = 'PaymentService::approvePayment() must run inside DB::transaction';
        }
    }

    if ($failures !== []) {
        return [
            'id' => 'gateway-atomic-approval',
            'ok' => false,
            'detail' => implode('; ', $failures),
        ];
    }

    return [
        'id' => 'gateway-atomic-approval',
        'ok' => true,
        'detail' => 'All approval paths transactional (gateway auto-approve, bypass, manual approve)',
    ];
}

$changed = veraLogicChangedPhpFiles($root);
$results = array_merge(
    veraLogicCheckFileSize($root, $changed),
    [
        veraLogicNoLongDashes($root),
        veraLogicGatewayAtomicApproval($root),
    ],
);

$failed = array_values(array_filter($results, static fn (array $r): bool => !$r['ok']));

echo '🧪 Vera logic: ' . count($results) . " rule(s)\n";
foreach ($results as $r) {
    echo '  ' . ($r['ok'] ? '✅' : '❌') . " [{$r['id']}] {$r['detail']}\n";
}

if ($failed !== []) {
    echo '❌ Vera logic: failed (' . count($failed) . ")\n";
    exit(1);
}

echo "✅ Vera logic: passed\n";
exit(0);
