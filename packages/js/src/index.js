// ES module entry: the implementation is index.cjs, so require() and import share one copy.
import cjs from './index.cjs';

export const { A2zClient, A2zApiError, ENDPOINTS, VERSION, DEFAULT_BASE_URL } = cjs;
export default A2zClient;
