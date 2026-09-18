/**
 * Drop-in replacement for '@playwright/test'.
 *
 * Every spec imports { test, expect } from here instead of the package, so
 * cross-cutting fixtures (auto-recovery, logging, coverage) can be added in
 * one place later without touching individual specs.
 */
export { test, expect } from '@playwright/test';
