#!/bin/php
<?php

declare(strict_types=1);

/**
 * Composer Local Merge Handler
 * 
 * Replaces Wikimedia Composer Merge plugin with a deterministic, script-based workflow.
 * This script temporarily injects local composer requirements only when needed.
 * 
 * Workflow:
 * 1. pre-install: Detect local overrides, backup, merge, and prepare for install
 * 2. post-install: Restore original files and clean up backups
 */

class ComposerLocalMerge
{
    private const COMPOSER_JSON = 'composer.json';
    private const COMPOSER_LOCAL_JSON = 'composer.local.json';
    private const COMPOSER_LOCK = 'composer.lock';
    private const BACKUP_SUFFIX = '.bkup';

    private string $rootPath;
    private bool $hasMerged = false;
    private string $flagFile;

    public function __construct(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, '/');
        $this->flagFile = $this->rootPath . '/.composer-merge-active';
    }

    /**
     * Pre-install hook: Backup and merge local requirements if present
     */
    public function preInstall(): void
    {
        echo "=== Composer Local Merge: Pre-Install ===\n";

        // If a merge is already active, skip to avoid backing up/overwriting backups
        if (file_exists($this->flagFile)) {
            echo "Merge already active - skipping pre-install\n";
            return;
        }

        $localPath = $this->getPath(self::COMPOSER_LOCAL_JSON);
        
        if (!file_exists($localPath)) {
            echo "No composer.local.json found - skipping merge\n";
            return;
        }

        try {
            $localRequirements = $this->getLocalRequirements($localPath);

            if (
                empty($localRequirements['require'])
                && empty($localRequirements['require-dev'])
                && empty($localRequirements['exclude'])
            ) {
                echo "composer.local.json has no requirements - skipping merge\n";
                return;
            }

            $this->performMerge($localRequirements);
            echo "Successfully merged local requirements\n";
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            $this->rollback();
            exit(1);
        }
    }

    /**
     * Post-install hook: Restore original files and clean up
     */
    public function postInstall(): void
    {
        echo "=== Composer Local Merge: Post-Install ===\n";

        if (!file_exists($this->flagFile)) {
            echo "No merge was performed - skipping restore\n";
            return;
        }

        try {
            $this->restore();
            echo "Successfully restored original files\n";
        } catch (Exception $e) {
            echo "ERROR during restore: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    /**
     * Perform the merge operation
     */
    private function performMerge(array $localRequirements): void
    {
        $composerPath = $this->getPath(self::COMPOSER_JSON);
        $lockPath = $this->getPath(self::COMPOSER_LOCK);

        // Step 1: Backup composer.json
        $this->backup($composerPath);
        echo "✓ Backed up composer.json\n";

        // Step 2: Backup composer.lock if it exists
        if (file_exists($lockPath)) {
            $this->backup($lockPath);
            echo "✓ Backed up composer.lock\n";
            
            // Step 3: Remove composer.lock to force fresh resolution
            if (!unlink($lockPath)) {
                throw new Exception("Failed to remove composer.lock");
            }
            echo "✓ Removed composer.lock\n";
        }

        // Step 4: Merge requirements into composer.json
        // Note: Local requirements override existing ones - this allows developers
        // to test specific versions or patches during development
        $composer = $this->readJson($composerPath);
        
        if (!empty($localRequirements['require'])) {
            $composer['require'] = array_merge(
                $composer['require'] ?? [],
                $localRequirements['require']
            );
            echo "✓ Merged " . count($localRequirements['require']) . " require dependencies\n";
        }
        
        if (!empty($localRequirements['require-dev'])) {
            $composer['require-dev'] = array_merge(
                $composer['require-dev'] ?? [],
                $localRequirements['require-dev']
            );
            echo "✓ Merged " . count($localRequirements['require-dev']) . " require-dev dependencies\n";
        }

        if (!empty($localRequirements['repositories'])) {
            $composerRepos = $composer['repositories'] ?? [];
            $originalCount = count($composerRepos);
            $composer['repositories'] = $this->mergeRepositories($composerRepos, $localRequirements['repositories']);
            $mergedCount = count($composer['repositories']) - $originalCount;
            echo "✓ Merged " . max(0, $mergedCount) . " repositories\n";
        }

        $this->excludePackages($composer, $localRequirements['exclude']);

        $this->writeJson($composerPath, $composer);
        
        // Create flag file to indicate merge was performed
        if (file_put_contents($this->flagFile, time()) === false) {
            throw new Exception("Failed to create merge flag file");
        }
        
        $this->hasMerged = true;
    }

    /**
     * Restore original files from backups
     */
    private function restore(): void
    {
        $composerPath = $this->getPath(self::COMPOSER_JSON);
        $lockPath = $this->getPath(self::COMPOSER_LOCK);
        
        // Restore composer.json
        $this->restoreFromBackup($composerPath);
        echo "✓ Restored composer.json\n";

        // Restore composer.lock if backup exists
        $lockBackup = $lockPath . self::BACKUP_SUFFIX;
        if (file_exists($lockBackup)) {
            $this->restoreFromBackup($lockPath);
            echo "✓ Restored composer.lock\n";
        }

        // Clean up flag file
        if (file_exists($this->flagFile)) {
            unlink($this->flagFile);
        }
    }

    /**
     * Rollback changes in case of error
     */
    private function rollback(): void
    {
        echo "Rolling back changes...\n";
        
        try {
            $composerPath = $this->getPath(self::COMPOSER_JSON);
            $composerBackup = $composerPath . self::BACKUP_SUFFIX;
            
            if (file_exists($composerBackup)) {
                $this->restoreFromBackup($composerPath);
                echo "✓ Rolled back composer.json\n";
            }

            $lockPath = $this->getPath(self::COMPOSER_LOCK);
            $lockBackup = $lockPath . self::BACKUP_SUFFIX;
            
            if (file_exists($lockBackup)) {
                $this->restoreFromBackup($lockPath);
                echo "✓ Rolled back composer.lock\n";
            }

            if (file_exists($this->flagFile)) {
                unlink($this->flagFile);
            }
        } catch (Exception $e) {
            echo "ERROR during rollback: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Get local requirements from composer.local.json
     */
    private function getLocalRequirements(string $path): array
    {
        $data = $this->readJson($path);
        
        return [
            'require' => $data['require'] ?? [],
            'require-dev' => $data['require-dev'] ?? [],
            'repositories' => $data['repositories'] ?? [],
            'exclude' => $this->normalizeExclude($data['exclude'] ?? []),
        ];
    }

    /**
     * Read the exclude list from composer.local.json.
     *
     * @param mixed $exclude Raw JSON value.
     * @return list<string> Package names to drop from the merged manifest.
     * @throws Exception If exclude is not a list of package names.
     */
    private function normalizeExclude(mixed $exclude): array
    {
        if ($exclude === []) {
            return [];
        }

        if (!is_array($exclude) || array_keys($exclude) !== range(0, count($exclude) - 1)) {
            throw new Exception('composer.local.json exclude must be a list of package names');
        }

        $packages = [];
        foreach ($exclude as $package) {
            if (!is_string($package) || !str_contains($package, '/')) {
                throw new Exception('composer.local.json exclude entries must be package names');
            }
            $packages[] = $package;
        }

        return $packages;
    }

    /**
     * Drop excluded packages after local requirements are merged, so exclude wins.
     *
     * @param array<string, mixed> $composer Merged manifest, modified in place.
     * @param list<string> $packages Package names from composer.local.json.
     */
    private function excludePackages(array &$composer, array $packages): void
    {
        foreach ($packages as $package) {
            $removed = false;
            foreach (['require', 'require-dev'] as $section) {
                if (isset($composer[$section][$package])) {
                    unset($composer[$section][$package]);
                    $removed = true;
                }
            }

            if ($removed) {
                echo "✓ Excluded {$package}\n";
                continue;
            }

            echo "✓ Exclude {$package} (not in require or require-dev)\n";
        }
    }

    /**
     * Merge incoming repositories into existing ones with deduplication
     */
    private function mergeRepositories(array $existing, array $incoming): array
    {
        $seen = [];
        $result = [];

        foreach ($existing as $repo) {
            $key = json_encode($this->sortKeysRecursive($repo), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $seen[$key] = true;
            $result[] = $repo;
        }

        foreach ($incoming as $repo) {
            $key = json_encode($this->sortKeysRecursive($repo), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $repo;
            }
        }

        return $result;
    }

    /**
     * Recursively sort array keys for stable encoding
     */
    private function sortKeysRecursive(array $arr): array
    {
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $arr[$k] = $this->sortKeysRecursive($v);
            }
        }

        ksort($arr);

        return $arr;
    }

    /**
     * Create backup of a file
     */
    private function backup(string $path): void
    {
        $backup = $path . self::BACKUP_SUFFIX;
        
        if (!copy($path, $backup)) {
            throw new Exception("Failed to backup: {$path}");
        }
    }

    /**
     * Restore file from backup
     */
    private function restoreFromBackup(string $path): void
    {
        $backup = $path . self::BACKUP_SUFFIX;
        
        if (!file_exists($backup)) {
            throw new Exception("Backup not found: {$backup}");
        }
        
        if (!copy($backup, $path)) {
            throw new Exception("Failed to restore: {$path}");
        }
        
        unlink($backup);
    }

    /**
     * Read and decode JSON file
     */
    private function readJson(string $path): array
    {
        if (!file_exists($path)) {
            throw new Exception("File not found: {$path}");
        }
        
        $content = file_get_contents($path);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in {$path}: " . json_last_error_msg());
        }
        
        return $data;
    }

    /**
     * Encode and write JSON file
     */
    private function writeJson(string $path, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        if ($json === false) {
            throw new Exception("Failed to encode JSON: " . json_last_error_msg());
        }
        
        // Add newline at end of file to match standard formatting
        $json .= "\n";
        
        if (file_put_contents($path, $json) === false) {
            throw new Exception("Failed to write file: {$path}");
        }
    }

    /**
     * Get full path for a file
     */
    private function getPath(string $filename): string
    {
        return $this->rootPath . '/' . $filename;
    }
}

// Main execution
if (PHP_SAPI !== 'cli') {
    echo "This script must be run from the command line\n";
    exit(1);
}

$action = $argv[1] ?? '';
$rootPath = getcwd();

$merger = new ComposerLocalMerge($rootPath);

switch ($action) {
    case 'pre-install':
        $merger->preInstall();
        break;
    case 'post-install':
        $merger->postInstall();
        break;
    default:
        echo "Usage: php composer-local-merge.php [pre-install|post-install]\n";
        exit(1);
}
