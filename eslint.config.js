import { defineConfig } from "eslint/config";
import js from "@eslint/js";
import pluginVue from 'eslint-plugin-vue'
import globals from "globals";
import vitest from 'eslint-plugin-vitest'

export default defineConfig([
    ...pluginVue.configs['flat/recommended'],
    {
        ...js.configs.recommended,
        files: ["assets/js/**/*.{js,vue}"],
        plugins: {
            js,vitest
        },
        extends: ["js/recommended"],
        languageOptions: {
            sourceType: "module",
            globals: {
                ...globals.browser,
                "global": true,
                "Buffer": true
            }
        },
        rules: {
            ...vitest.configs.recommended.rules, // you can also use vitest.configs.all.rules to enable all rules
            'vitest/max-nested-describe': ['error', { max: 3 }], // you can also modify rules' behavior using option like this
        },
    },
]);