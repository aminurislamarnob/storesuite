/**
 * Jest setup for the StoreSuite JS unit suite.
 */
import '@testing-library/jest-dom';

// Let React know act() is supported, so async state updates inside Testing
// Library helpers don't warn (and fail via @wordpress/jest-console).
globalThis.IS_REACT_ACT_ENVIRONMENT = true;
