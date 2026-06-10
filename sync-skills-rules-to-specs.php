#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = __DIR__;
$skillsDir = $root . '/skills';
$specsRulesDir = $root . '/specs/rules';
$specsIndexPath = $root . '/specs/index.md';

$rulesDictionaryStart = '<!-- rules-dictionary:start -->';
$rulesDictionaryEnd = '<!-- rules-dictionary:end -->';
$syncDictionaryStart = '<!-- sync-rules-dictionary:start -->';
$syncDictionaryEnd = '<!-- sync-rules-dictionary:end -->';

if (!is_dir($skillsDir)) {
    fwrite(STDERR, "Skills directory not found: {$skillsDir}\n");
    exit(1);
}

if (!is_file($specsIndexPath)) {
    fwrite(STDERR, "Specs index not found: {$specsIndexPath}\n");
    exit(1);
}

$hasErrors = false;

// --- Copy rule files into specs/rules/ (flat) ---

if (!is_dir($specsRulesDir) && !mkdir($specsRulesDir, 0755, true) && !is_dir($specsRulesDir)) {
    fwrite(STDERR, "Failed to create directory: {$specsRulesDir}\n");
    exit(1);
}

/** @var array<string, list<array{skill: string, path: string}>> $sourcesByBasename */
$sourcesByBasename = [];

foreach (glob($skillsDir . '/*/rules/*.md') ?: [] as $sourcePath) {
    $basename = basename($sourcePath);
    $skillName = basename(dirname(dirname($sourcePath)));
    $sourcesByBasename[$basename][] = [
        'skill' => $skillName,
        'path' => $sourcePath,
    ];
}

ksort($sourcesByBasename);

foreach ($sourcesByBasename as $basename => $sources) {
    if (count($sources) > 1) {
        fwrite(STDERR, "Duplicate rule file name: {$basename}\n");
        foreach ($sources as $source) {
            fwrite(STDERR, "  - skills/{$source['skill']}/rules/{$basename}\n");
        }
    }
}

$copiedCount = 0;
$skippedCount = 0;

foreach ($sourcesByBasename as $basename => $sources) {
    $targetPath = $specsRulesDir . '/' . $basename;
    $sourcePath = $sources[0]['path'];

    if (count($sources) > 1) {
        $contents = [];
        foreach ($sources as $source) {
            $content = file_get_contents($source['path']);
            if ($content === false) {
                fwrite(STDERR, "Failed to read: {$source['path']}\n");
                $hasErrors = true;
                continue 2;
            }
            $contents[] = $content;
        }

        if (count(array_unique($contents)) > 1) {
            fwrite(STDERR, "Skipped copy of {$basename}: conflicting content in duplicate sources\n");
            $skippedCount++;
            continue;
        }
    }

    if (!copy($sourcePath, $targetPath)) {
        fwrite(STDERR, "Failed to copy {$sourcePath} -> {$targetPath}\n");
        $hasErrors = true;
        continue;
    }

    echo "Copied rules/{$basename} <- skills/{$sources[0]['skill']}/rules/{$basename}\n";
    $copiedCount++;
}

echo "Rules copied: {$copiedCount}";
if ($skippedCount > 0) {
    echo ", skipped: {$skippedCount}";
}
echo "\n";

// --- Sync rules dictionary into specs/index.md ---

$dictionaries = [];

foreach (glob($skillsDir . '/*/SKILL.md') ?: [] as $skillPath) {
    $skillName = basename(dirname($skillPath));
    $content = file_get_contents($skillPath);

    if ($content === false) {
        fwrite(STDERR, "Failed to read: {$skillPath}\n");
        $hasErrors = true;
        continue;
    }

    $dictionary = extractBetweenMarkers($content, $rulesDictionaryStart, $rulesDictionaryEnd);
    if ($dictionary === null) {
        fwrite(STDERR, "Warning: {$rulesDictionaryStart} block not found in skills/{$skillName}/SKILL.md\n");
        continue;
    }

    $dictionary = trim($dictionary);
    if ($dictionary === '') {
        fwrite(STDERR, "Warning: empty rules dictionary in skills/{$skillName}/SKILL.md\n");
        continue;
    }

    $dictionaries[$skillName] = $dictionary;
}

if ($dictionaries === []) {
    fwrite(STDERR, "No rules dictionaries found to sync into specs/index.md\n");
    $hasErrors = true;
} else {
    ksort($dictionaries);
    $combinedDictionary = implode("\n\n", $dictionaries);

    $indexContent = file_get_contents($specsIndexPath);
    if ($indexContent === false) {
        fwrite(STDERR, "Failed to read: {$specsIndexPath}\n");
        exit(1);
    }

    $updatedIndex = replaceBetweenMarkers(
        $indexContent,
        $syncDictionaryStart,
        $syncDictionaryEnd,
        "\n\n" . $combinedDictionary . "\n\n"
    );

    if ($updatedIndex === null) {
        fwrite(STDERR, "Markers {$syncDictionaryStart} / {$syncDictionaryEnd} not found in specs/index.md\n");
        $hasErrors = true;
    } elseif ($updatedIndex !== $indexContent) {
        if (file_put_contents($specsIndexPath, $updatedIndex) === false) {
            fwrite(STDERR, "Failed to write: {$specsIndexPath}\n");
            exit(1);
        }
        echo "Updated specs/index.md (" . count($dictionaries) . " skill dictionaries)\n";
    } else {
        echo "specs/index.md is already up to date\n";
    }
}

exit($hasErrors ? 1 : 0);

/**
 * @return string|null Inner content between markers, without markers; null if markers missing.
 */
function extractBetweenMarkers(string $content, string $startMarker, string $endMarker): ?string
{
    $startPos = strpos($content, $startMarker);
    if ($startPos === false) {
        return null;
    }

    $innerStart = $startPos + strlen($startMarker);
    $endPos = strpos($content, $endMarker, $innerStart);
    if ($endPos === false) {
        return null;
    }

    return substr($content, $innerStart, $endPos - $innerStart);
}

/**
 * @return string|null Updated content, or null if markers are missing.
 */
function replaceBetweenMarkers(
    string $content,
    string $startMarker,
    string $endMarker,
    string $replacement
): ?string {
    $startPos = strpos($content, $startMarker);
    if ($startPos === false) {
        return null;
    }

    $innerStart = $startPos + strlen($startMarker);
    $endPos = strpos($content, $endMarker, $innerStart);
    if ($endPos === false) {
        return null;
    }

    return substr($content, 0, $innerStart)
        . $replacement
        . substr($content, $endPos);
}
