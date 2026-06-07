<?php

namespace ChiefTools\PhpCsFixer\Tests\Unit;

use PhpCsFixer\Finder;
use PHPUnit\Framework\TestCase;
use ChiefTools\PhpCsFixer\Config;
use PhpCsFixer\Config as PhpCsFixerConfig;

class ConfigTest extends TestCase
{
    public function testItBuildsAPhpCsFixerConfigWithChiefToolsRules(): void
    {
        $finder = Finder::create()->in(__DIR__);
        $config = Config::make($finder);

        $this->assertInstanceOf(PhpCsFixerConfig::class, $config);
        $this->assertSame($finder, $config->getFinder());
        $this->assertTrue($config->getRules()['ChiefTools/phpdoc_fqcn']);
        $this->assertContains('ChiefTools/phpdoc_fqcn', array_map(
            static fn ($fixer): string => $fixer->getName(),
            $config->getCustomFixers(),
        ));
    }

    public function testProjectRulesAreMergedOnTopOfDefaults(): void
    {
        $config = Config::make(Finder::create()->in(__DIR__), [
            'yoda_style'      => true,
            'ordered_imports' => [
                'sort_algorithm' => 'alpha',
            ],
        ]);

        $rules = $config->getRules();

        $this->assertTrue($rules['yoda_style']);
        $this->assertSame('alpha', $rules['ordered_imports']['sort_algorithm']);
        $this->assertSame(['class', 'const', 'function'], $rules['ordered_imports']['imports_order']);
    }
}
