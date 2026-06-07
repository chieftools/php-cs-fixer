<?php

namespace ChiefTools\PhpCsFixer;

use RecursiveIteratorIterator;
use Composer\InstalledVersions;
use RecursiveDirectoryIterator;
use PhpCsFixer\Config\RuleCustomisationPolicyInterface;

class PackageCacheInvalidationPolicy implements RuleCustomisationPolicyInterface
{
    private ?string $policyVersion = null;

    public function getPolicyVersionForCache(): string
    {
        return $this->policyVersion ??= implode(':', [
            'chieftools/php-cs-fixer',
            $this->installedVersion(),
            $this->sourceHash(),
        ]);
    }

    public function getRuleCustomisers(): array
    {
        return [];
    }

    private function installedVersion(): string
    {
        if (!class_exists(InstalledVersions::class) || !InstalledVersions::isInstalled('chieftools/php-cs-fixer')) {
            return 'unknown';
        }

        $version   = InstalledVersions::getPrettyVersion('chieftools/php-cs-fixer') ?? 'unknown';
        $reference = InstalledVersions::getReference('chieftools/php-cs-fixer') ?? 'unknown';

        return $version . '@' . $reference;
    }

    private function sourceHash(): string
    {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        $context = hash_init('sha256');

        foreach ($files as $file) {
            hash_update($context, str_replace('\\', '/', substr($file, strlen(__DIR__) + 1)));
            hash_update($context, "\0");
            hash_update_file($context, $file);
            hash_update($context, "\0");
        }

        return hash_final($context);
    }
}
