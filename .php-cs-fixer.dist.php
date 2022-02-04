<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude([
        'bin',
        'bootstrap',
        'node_modules',
        'storage',
        'vendor',
    ])
    ->notPath([
        '_ide_helper_models.php',
        '_ide_helper.php',
        '.phpstorm.meta.php',
        'server.php',
    ])
    ->notPath('public/index.php')
    ->in(__DIR__);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'align_multiline_comment' => true,
        'array_indentation' => true,
        'class_definition' => [
            'space_before_parenthesis' => true,
        ],
        'comment_to_phpdoc' => false,
        'compact_nullable_typehint' => true,
        'concat_space' => [
            'spacing' => 'one',
        ],
        'declare_equal_normalize' => [
            'space' => 'single',
        ],
        'dir_constant' => true,
        'ereg_to_preg' => true,
        'function_typehint_space' => true,
        'include' => true,
        'increment_style' => [
            'style' => 'post',
        ],
        'is_null' => true,
        'linebreak_after_opening_tag' => true,
        'lowercase_cast' => true,
        'mb_str_functions' => true,
        'multiline_comment_opening_closing' => true,
        'native_function_casing' => true,
        'no_blank_lines_after_class_opening' => true,
        'no_empty_comment' => true,
        'no_empty_phpdoc' => true,
        'no_empty_statement' => true,
        'no_extra_blank_lines' => true,
        'no_leading_import_slash' => true,
        'no_leading_namespace_whitespace' => true,
        'no_mixed_echo_print' => [
            'use' => 'echo',
        ],
        'no_null_property_initialization' => true,
        'no_superfluous_elseif' => true,
        'no_trailing_comma_in_singleline_array' => true,
        'no_unused_imports' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'no_whitespace_in_blank_line' => true,
        'not_operator_with_successor_space' => true,
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
        ],
        'psr_autoloading' => true,
        'single_quote' => [
            'strings_containing_single_quote_chars' => false,
        ],
        'standardize_not_equals' => true,
        'strict_comparison' => true,
        'ternary_operator_spaces' => true,
        'whitespace_after_comma_in_array' => true,
        'yoda_style' => true,
    ])
    ->setRiskyAllowed(true)
    ->setHideProgress(false)
    ->setFinder($finder);

