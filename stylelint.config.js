/** @type {import('stylelint').Config} */
module.exports = {
  extends: ['stylelint-config-standard-scss'],
  rules: {
    'rule-empty-line-before': ['never', { severity: 'warning' }],
    'at-rule-empty-line-before': ['never', { severity: 'warning' }],
    'declaration-empty-line-before': 'never',
    'function-name-case': null,
    'custom-property-empty-line-before': 'never',
    'selector-class-pattern': null,

    // :global 疑似クラスを許可（Next.jsのCSS Modules対応）
    'selector-pseudo-class-no-unknown': [
      true,
      {
        ignorePseudoClasses: ['global'],
      },
    ],

    // コメントにスペースを要求
    'scss/double-slash-comment-whitespace-inside': 'always',

    // 命名規則チェック（必要であれば有効化）
    'scss/at-mixin-pattern': null,
    'scss/at-function-pattern': null,
    'scss/dollar-variable-pattern': null,
  },
  ignoreFiles: ['**/*.js', '**/*.ts', '**/*.tsx'],
};
