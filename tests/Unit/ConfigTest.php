<?php

namespace ChiefTools\PhpCsFixer\Tests\Unit;

use SplFileInfo;
use PhpCsFixer\Finder;
use PhpCsFixer\RuleSet\RuleSet;
use PHPUnit\Framework\TestCase;
use PhpCsFixer\Tokenizer\Tokens;
use ChiefTools\PhpCsFixer\Config;
use PhpCsFixer\Config as PhpCsFixerConfig;
use PhpCsFixer\Fixer\Phpdoc\PhpdocLineSpanFixer;
use PhpCsFixer\Fixer\Operator\BinaryOperatorSpacesFixer;
use ChiefTools\PhpCsFixer\Fixer\BinaryOperatorAlignmentFixer;

class ConfigTest extends TestCase
{
    public function testItBuildsAPhpCsFixerConfigWithChiefToolsRules(): void
    {
        $finder = Finder::create()->in(__DIR__);
        $config = Config::make($finder);

        $this->assertInstanceOf(PhpCsFixerConfig::class, $config);
        $this->assertSame($finder, $config->getFinder());
        $this->assertTrue($config->getRules()['ChiefTools/binary_operator_alignment']);
        $this->assertTrue($config->getRules()['ChiefTools/phpdoc_fqcn']);
        $this->assertTrue($config->getRules()['ChiefTools/nested_method_chaining_indentation']);
        $this->assertContains('ChiefTools/binary_operator_alignment', array_map(
            static fn ($fixer): string => $fixer->getName(),
            $config->getCustomFixers(),
        ));
        $this->assertContains('ChiefTools/phpdoc_fqcn', array_map(
            static fn ($fixer): string => $fixer->getName(),
            $config->getCustomFixers(),
        ));
        $this->assertContains('ChiefTools/nested_method_chaining_indentation', array_map(
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

    public function testItAddsPackageVersionToTheCacheSignature(): void
    {
        $policy = Config::make(Finder::create()->in(__DIR__))->getRuleCustomisationPolicy();

        $this->assertNotNull($policy);
        $this->assertSame([], $policy->getRuleCustomisers());
        $this->assertStringStartsWith('chieftools/php-cs-fixer:', $policy->getPolicyVersionForCache());
    }

    public function testRulesUsePerBaseWithoutSymfonyRuleSet(): void
    {
        $rules        = Config::rules();
        $metaRuleSets = array_values(array_filter(
            array_keys($rules),
            static fn (string $rule): bool => str_starts_with($rule, '@'),
        ));

        $this->assertSame(['@PER-CS3x0'], $metaRuleSets);

        foreach (array_keys((new RuleSet($rules))->getRules()) as $rule) {
            $this->assertStringStartsNotWith('@', $rule);
        }
    }

    public function testAssignmentsUseMinimalVerticalAlignment(): void
    {
        $source = <<<'PHP'
<?php

function values(): void
{
    $shortName      = 1;
    $longerName     = 2;
}

PHP;

        $expected = <<<'PHP'
<?php

function values(): void
{
    $shortName  = 1;
    $longerName = 2;
}

PHP;

        $this->assertSame($expected, $this->fixBinaryOperators($source));
    }

    public function testArrayPairsAreVerticallyAligned(): void
    {
        $source = <<<'PHP'
<?php

function values(): array
{
    return [
        'short' => 1,
        'longer_key' => 2,
    ];
}

PHP;

        $expected = <<<'PHP'
<?php

function values(): array
{
    return [
        'short'      => 1,
        'longer_key' => 2,
    ];
}

PHP;

        $this->assertSame($expected, $this->fixBinaryOperators($source));
    }

    public function testNestedArrayPairsKeepExistingVerticalAlignment(): void
    {
        $source = <<<'PHP'
<?php

function values(): array
{
    return [
        'short'    => [
            'value' => 1,
        ],
        'long_key' => [
            'value' => 2,
        ],
    ];
}

PHP;

        $this->assertSame($source, $this->fixBinaryOperators($source));
    }

    public function testPhpdocsWithOneContentLineAreCollapsed(): void
    {
        $source = <<<'PHP'
<?php

class Fixture
{
    /**
     * @return string
     */
    public function value(): string
    {
        return 'value';
    }
}

PHP;

        $expected = <<<'PHP'
<?php

class Fixture
{
    /** @return string */
    public function value(): string
    {
        return 'value';
    }
}

PHP;

        $this->assertSame($expected, $this->fixPhpdocLineSpan($source));
    }

    public function testMultiLinePhpdocsStayMultiLine(): void
    {
        $source = <<<'PHP'
<?php

class Fixture
{
    /**
     * @param string $value
     * @return string
     */
    public function value(string $value): string
    {
        return $value;
    }
}

PHP;

        $this->assertSame($source, $this->fixPhpdocLineSpan($source));
    }

    private function fixBinaryOperators(string $source): string
    {
        $config = Config::rules()['binary_operator_spaces'];
        $tokens = Tokens::fromCode($source);
        $fixer  = new BinaryOperatorSpacesFixer;

        $this->assertIsArray($config);
        $fixer->configure($config);
        $fixer->fix(new SplFileInfo(__FILE__), $tokens);
        (new BinaryOperatorAlignmentFixer)->fix(new SplFileInfo(__FILE__), $tokens);

        return $tokens->generateCode();
    }

    private function fixPhpdocLineSpan(string $source): string
    {
        $config = Config::rules()['phpdoc_line_span'];
        $tokens = Tokens::fromCode($source);
        $fixer  = new PhpdocLineSpanFixer;

        $this->assertIsArray($config);
        $fixer->configure($config);
        $fixer->fix(new SplFileInfo(__FILE__), $tokens);

        return $tokens->generateCode();
    }
}
