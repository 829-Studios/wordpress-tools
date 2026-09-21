import wordpress from '@wordpress/eslint-plugin';
import globals from 'globals';

export default [
	{
		ignores: [
			'**/node_modules/**/*.js',
			'**/vendor/**/*.js',
			'**/*.min.js',
		],
	},
	...wordpress.configs.recommended,
	{
		languageOptions: {
			parserOptions: {
				ecmaVersion: 'latest',
				requireConfigFile: false,
			},
		},
		rules: {
			'import/first': 'error',
			'import/exports-last': 'error',
			'import/no-unresolved': [ 'error', { ignore: [ '^@wordpress/' ] } ],
			curly: [ 'error', 'all' ],
			'padding-line-between-statements': [
				'error',
				{
					blankLine: 'always',
					prev: '*',
					next: [
						'if',
						'for',
						'while',
						'do',
						'switch',
						'try',
						'return',
						'function',
						'multiline-expression',
					],
				},
				{
					blankLine: 'always',
					prev: [
						'if',
						'for',
						'while',
						'do',
						'switch',
						'try',
						'return',
						'function',
						'multiline-expression',
					],
					next: [ 'const', 'let', 'var', 'expression' ],
				},
				{
					blankLine: 'never',
					prev: [
						'singleline-const',
						'singleline-let',
						'singleline-var',
					],
					next: [
						'singleline-const',
						'singleline-let',
						'singleline-var',
					],
				},
				{
					blankLine: 'never',
					prev: [ 'expression' ],
					next: [ 'expression' ],
				},
				{
					blankLine: 'always',
					prev: [ 'expression' ],
					next: [ 'multiline-expression' ],
				},
				{
					blankLine: 'always',
					prev: [
						'multiline-expression',
						'multiline-const',
						'multiline-let',
						'multiline-var',
					],
					next: [ 'expression' ],
				},
				{
					blankLine: 'always',
					prev: [
						'multiline-const',
						'multiline-let',
						'multiline-var',
					],
					next: [
						'singleline-const',
						'singleline-let',
						'singleline-var',
					],
				},
				{
					blankLine: 'always',
					prev: [
						'singleline-const',
						'singleline-let',
						'singleline-var',
					],
					next: [
						'multiline-const',
						'multiline-let',
						'multiline-var',
					],
				},
				{
					blankLine: 'always',
					prev: [ 'import' ],
					next: [
						'if',
						'for',
						'while',
						'do',
						'switch',
						'try',
						'return',
						'function',
						'multiline-expression',
						'const',
						'let',
						'var',
					],
				},
			],
		},
	},
	{
		files: [ 'assets/js/**/*.js' ],
		languageOptions: {
			sourceType: 'script',
			globals: {
				...globals.browser,
				...globals.es2021,
			},
		},
	},
	{
		files: [ 'npm-scripts/**/*.js' ],
		languageOptions: {
			sourceType: 'commonjs',
			globals: {
				...globals.node,
				...globals.es2021,
			},
		},
		rules: {
			'no-console': 'off',
		},
	},
	{
		files: [ 'eslint.config.mjs' ],
		languageOptions: {
			sourceType: 'module',
			globals: {
				...globals.node,
			},
		},
	},
];
