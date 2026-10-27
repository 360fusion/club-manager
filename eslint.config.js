import js from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';
import globals from 'globals';

export default [
    { ignores: ['public/**', 'vendor/**', 'node_modules/**', 'storage/**', 'bootstrap/cache/**'] },
    js.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    {
        files: ['resources/js/**/*.{js,vue}'],
        languageOptions: {
            globals: { ...globals.browser, route: 'readonly' }, // `route` is Ziggy's global
        },
        rules: {
            // Real mistakes stay errors; the existing code's layout and style choices are not policed.
            'no-unused-vars': ['warn', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
            'vue/multi-word-component-names': 'off',
            'vue/no-v-html': 'warn', // every use must be of sanitised content; see RichTextSanitizer
            'vue/max-attributes-per-line': 'off',
            'vue/singleline-html-element-content-newline': 'off',
            'vue/multiline-html-element-content-newline': 'off',
            'vue/html-self-closing': 'off',
            'vue/html-indent': 'off',
            'vue/html-closing-bracket-newline': 'off',
            'vue/first-attribute-linebreak': 'off',
            'vue/attributes-order': 'off',
            'vue/require-default-prop': 'off',
            'no-useless-assignment': 'warn',
            'vue/html-closing-bracket-spacing': 'off',
            'vue/html-quotes': 'off',
            'vue/attribute-hyphenation': 'off',
            // The block editors and the sections split out of the big pages edit the reactive form or block object they
            // are given, on purpose; the page owns saving it. Replacing a whole prop is still flagged.
            'vue/no-mutating-props': ['warn', { shallowOnly: true }],
            // `v-for="{ block, theme } in [item]"` is used to name things once; it is valid.
            'vue/valid-v-for': 'warn',
            'vue/no-template-shadow': 'warn',
            'vue/no-v-text-v-html-on-component': 'warn',
        },
    },
    { files: ['vite.config.js', 'eslint.config.js'], languageOptions: { globals: globals.node } },
];
