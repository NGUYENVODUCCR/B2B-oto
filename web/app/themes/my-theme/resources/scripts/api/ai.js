import { apiFetch } from './http.js';

async function parseResponse(response) {
  const result = await response.json().catch(() => ({}));

  if (!response.ok || result?.success === false) {
    throw new Error(result?.message || `API error ${response.status}`);
  }

  return result?.data ?? result;
}

export const AiAPI = {
  async ask(message) {
    const response = await apiFetch('/ai/ask', {
      method: 'POST',
      body: {
        message,
      },
    });

    return parseResponse(response);
  },
};

