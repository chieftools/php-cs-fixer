<?php

namespace ChiefTools\PhpCsFixer\Tests\Unit;

use SplFileInfo;
use PHPUnit\Framework\TestCase;
use PhpCsFixer\Tokenizer\Tokens;
use ChiefTools\PhpCsFixer\Fixer\PhpdocFullyQualifiedClassNamesFixer;

class PhpdocFullyQualifiedClassNamesFixerTest extends TestCase
{
    public function testItExpandsImportedNamesInPhpdocGenerics(): void
    {
        $source = <<<'PHP'
<?php

use App\Models\Domain;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @return HasMany<Domain> */
function domains(): HasMany
{
}

PHP;

        $expected = <<<'PHP'
<?php

use App\Models\Domain;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Domain> */
function domains(): HasMany
{
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItExpandsAliasedAndGroupedImports(): void
    {
        $source = <<<'PHP'
<?php

use App\Models\Domain as DomainModel;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * @param BelongsTo<DomainModel> $relation
 * @return HasMany<DomainModel>
 */
function domains($relation): HasMany
{
}

PHP;

        $expected = <<<'PHP'
<?php

use App\Models\Domain as DomainModel;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * @param \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Domain> $relation
 * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Domain>
 */
function domains($relation): HasMany
{
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItDoesNotRewriteExecutableTypeHintsOrExistingFullyQualifiedNames(): void
    {
        $source = <<<'PHP'
<?php

use App\Models\Domain;

/** @return \App\Models\Domain */
function domain(Domain $domain): Domain
{
    return $domain;
}

PHP;

        $this->assertSame($source, $this->fix($source));
    }

    private function fix(string $source): string
    {
        $tokens = Tokens::fromCode($source);

        (new PhpdocFullyQualifiedClassNamesFixer)->fix(new SplFileInfo(__FILE__), $tokens);

        return $tokens->generateCode();
    }
}
