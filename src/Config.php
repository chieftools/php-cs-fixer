<?php

namespace ChiefTools\PhpCsFixer;

use PhpCsFixer\Finder;
use PhpCsFixer\Config as PhpCsFixerConfig;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;
use ChiefTools\PhpCsFixer\Fixer\BinaryOperatorAlignmentFixer;
use ChiefTools\PhpCsFixer\Fixer\PhpdocFullyQualifiedClassNamesFixer;
use ChiefTools\PhpCsFixer\Fixer\NestedMethodChainingIndentationFixer;

class Config
{
    /**
     * @param array<string, mixed> $rules
     */
    public static function make(Finder $finder, array $rules = []): PhpCsFixerConfig
    {
        return (new PhpCsFixerConfig)
            ->setParallelConfig(ParallelConfigFactory::detect())
            ->setRuleCustomisationPolicy(new PackageCacheInvalidationPolicy)
            ->setUnsupportedPhpVersionAllowed(true)
            ->registerCustomFixers([
                new BinaryOperatorAlignmentFixer,
                new NestedMethodChainingIndentationFixer,
                new PhpdocFullyQualifiedClassNamesFixer,
            ])
            ->setRules(array_replace_recursive(self::rules(), $rules))
            ->setFinder($finder);
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            '@PER-CS3x0' => true,

            'ChiefTools/phpdoc_fqcn'                        => true,
            'ChiefTools/binary_operator_alignment'          => true,
            'ChiefTools/nested_method_chaining_indentation' => true,

            'single_trait_insert_per_statement' => false,

            'backtick_to_shell_exec'                           => true,
            'class_reference_name_casing'                      => true,
            'clean_namespace'                                  => true,
            'control_structure_braces'                         => true,
            'declare_parentheses'                              => true,
            'echo_tag_syntax'                                  => true,
            'empty_loop_condition'                             => true,
            'include'                                          => true,
            'integer_literal_case'                             => true,
            'lambda_not_used_import'                           => true,
            'linebreak_after_opening_tag'                      => true,
            'magic_constant_casing'                            => true,
            'magic_method_casing'                              => true,
            'method_chaining_indentation'                      => true,
            'native_function_casing'                           => true,
            'native_type_declaration_casing'                   => true,
            'no_alias_language_construct_call'                 => true,
            'no_binary_string'                                 => true,
            'no_blank_lines_after_phpdoc'                      => true,
            'no_empty_comment'                                 => true,
            'no_empty_phpdoc'                                  => true,
            'no_empty_statement'                               => true,
            'no_leading_namespace_whitespace'                  => true,
            'no_mixed_echo_print'                              => true,
            'no_multiline_whitespace_around_double_arrow'      => true,
            'no_null_property_initialization'                  => true,
            'no_short_bool_cast'                               => true,
            'no_singleline_whitespace_before_semicolons'       => true,
            'no_spaces_around_offset'                          => true,
            'no_trailing_comma_in_singleline'                  => true,
            'no_unneeded_import_alias'                         => true,
            'no_unset_cast'                                    => true,
            'no_unused_imports'                                => true,
            'no_useless_concat_operator'                       => true,
            'no_useless_else'                                  => true,
            'no_useless_nullsafe_operator'                     => true,
            'no_useless_return'                                => true,
            'normalize_index_brace'                            => true,
            'nullable_type_declaration_for_default_null_value' => true,
            'object_operator_without_whitespace'               => true,
            'php_unit_fqcn_annotation'                         => true,
            'php_unit_method_casing'                           => true,
            'phpdoc_annotation_without_dot'                    => true,
            'phpdoc_indent'                                    => true,
            'phpdoc_inline_tag_normalizer'                     => true,
            'phpdoc_no_access'                                 => true,
            'phpdoc_no_package'                                => true,
            'phpdoc_no_useless_inheritdoc'                     => true,
            'phpdoc_order'                                     => true,
            'phpdoc_return_self_reference'                     => true,
            'phpdoc_single_line_var_spacing'                   => true,
            'phpdoc_summary'                                   => true,
            'phpdoc_trim'                                      => true,
            'phpdoc_trim_consecutive_blank_line_separation'    => true,
            'phpdoc_types'                                     => true,
            'phpdoc_var_annotation_correct_order'              => true,
            'phpdoc_var_without_name'                          => true,
            'protected_to_private'                             => true,
            'return_type_declaration'                          => true,
            'semicolon_after_instruction'                      => true,
            'simple_to_complex_string_variable'                => true,
            'single_import_per_statement'                      => true,
            'single_line_comment_spacing'                      => true,
            'single_quote'                                     => true,
            'single_space_around_construct'                    => true,
            'standardize_increment'                            => true,
            'standardize_not_equals'                           => true,
            'switch_continue_to_break'                         => true,
            'trim_array_spaces'                                => true,
            'unary_operator_spaces'                            => true,
            'whitespace_after_comma_in_array'                  => true,

            'align_multiline_comment'             => [
                'comment_type' => 'phpdocs_like',
            ],
            'array_syntax'                        => [
                'syntax' => 'short',
            ],
            'attribute_empty_parentheses'         => [
                'use_parentheses' => false,
            ],
            'binary_operator_spaces'              => [
                'operators' => [
                    '|'  => null,
                    '='  => 'align_single_space',
                    '=>' => 'align_single_space',
                ],
            ],
            'blank_line_before_statement'         => [
                'statements' => ['return'],
            ],
            'braces_position'                     => [
                'allow_single_line_anonymous_functions'     => true,
                'allow_single_line_empty_anonymous_classes' => true,
            ],
            'cast_spaces'                         => [
                'space' => 'none',
            ],
            'class_definition'                    => [
                'single_line' => true,
            ],
            'curly_braces_position'               => [
                'anonymous_classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
            ],
            'empty_loop_body'                     => [
                'style' => 'braces',
            ],
            'fully_qualified_strict_types'        => [
                'phpdoc_tags' => [],
            ],
            'function_declaration'                => [
                'closure_function_spacing' => 'one',
            ],
            'general_phpdoc_tag_rename'           => [
                'replacements' => [
                    'inheritDocs' => 'inheritDoc',
                ],
            ],
            'global_namespace_import'             => [
                'import_classes'   => null,
                'import_constants' => null,
                'import_functions' => true,
            ],
            'increment_style'                     => [
                'style' => 'post',
            ],
            'method_argument_space'               => [
                'after_heredoc' => true,
                'on_multiline'  => 'ignore',
            ],
            'new_with_parentheses'                => [
                'named_class'     => false,
                'anonymous_class' => false,
            ],
            'no_alternative_syntax'               => [
                'fix_non_monolithic_code' => false,
            ],
            'no_extra_blank_lines'                => [
                'tokens' => [],
            ],
            'no_unneeded_braces'                  => [
                'namespaces' => true,
            ],
            'no_unneeded_control_parentheses'     => [
                'statements' => ['break', 'clone', 'continue', 'echo_print', 'negative_instanceof', 'others', 'return', 'switch_case', 'yield', 'yield_from'],
            ],
            'no_whitespace_before_comma_in_array' => [
                'after_heredoc' => true,
            ],
            'nullable_type_declaration'           => [
                'syntax' => 'question_mark',
            ],
            'operator_linebreak'                  => [
                'only_booleans' => true,
            ],
            'ordered_imports'                     => [
                'imports_order'  => ['class', 'const', 'function'],
                'sort_algorithm' => 'length',
            ],
            'ordered_types'                       => [
                'sort_algorithm'  => 'none',
                'null_adjustment' => 'always_last',
            ],
            'phpdoc_align'                        => [
                'align' => 'vertical',
                'tags'  => ['param', 'property', 'property-read', 'property-write', 'return', 'throws', 'type', 'var', 'method'],
            ],
            'phpdoc_no_alias_tag'                 => [
                'replacements' => [
                    'type' => 'var',
                    'link' => 'see',
                ],
            ],
            'phpdoc_scalar'                       => [
                'types' => ['boolean', 'callback', 'double', 'integer', 'never-return', 'never-returns', 'no-return', 'real', 'str'],
            ],
            'phpdoc_separation'                   => [
                'groups'                    => [
                    ['Annotation', 'NamedArgumentConstructor', 'Target'],
                    ['author', 'copyright', 'license'],
                    ['category', 'package', 'subpackage'],
                    ['property', 'property-read', 'property-write'],
                    ['deprecated', 'link', 'see', 'since'],
                ],
                'skip_unlisted_annotations' => false,
            ],
            'phpdoc_tag_type'                     => [
                'tags' => [
                    'inheritDoc' => 'inline',
                ],
            ],
            'phpdoc_types_order'                  => [
                'null_adjustment' => 'always_last',
                'sort_algorithm'  => 'none',
            ],
            'single_line_comment_style'           => [
                'comment_types' => ['hash'],
            ],
            'space_after_semicolon'               => [
                'remove_in_empty_for_expressions' => true,
            ],
            'statement_indentation'               => [
                'stick_comment_to_next_continuous_control_statement' => true,
            ],
            'trailing_comma_in_multiline'         => [
                'elements' => ['arrays', 'arguments', 'parameters', 'match'],
            ],
            'type_declaration_spaces'             => [
                'elements' => [],
            ],
        ];
    }
}
