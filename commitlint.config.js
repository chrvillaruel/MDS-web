/**
 * Commit message convention: Conventional Commits (https://www.conventionalcommits.org).
 * Allowed types: feat, fix, refactor, test, docs, chore, ci, perf, build, style, revert.
 * Scope is optional; use module names where it adds clarity (identity, invoicing,
 * bulk-import, buyer-link, compliance, billing, infra).
 */
export default {
    extends: ['@commitlint/config-conventional'],
    rules: {
        'subject-case': [2, 'never', ['upper-case', 'pascal-case']],
        'body-max-line-length': [1, 'always', 100],
        'footer-max-line-length': [1, 'always', 100],
    },
};
