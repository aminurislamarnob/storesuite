/**
 * Mock for the @woocommerce/navigation webpack external.
 *
 * Tests can inspect navigation calls via `getMockHistory()` and reset them
 * between tests with `resetMockHistory()`.
 */
const history = {
	push: jest.fn(),
	replace: jest.fn(),
};

export const getHistory = jest.fn( () => history );

export const getNewPath = jest.fn(
	( query = {}, path = '/', currentQuery = {} ) => {
		const params = new URLSearchParams( { ...currentQuery, ...query } );
		return `${ path }?${ params.toString() }`;
	}
);

export const getQuery = jest.fn( () => ( {} ) );

export function getMockHistory() {
	return history;
}

export function resetMockHistory() {
	history.push.mockReset();
	history.replace.mockReset();
	getHistory.mockClear();
}
