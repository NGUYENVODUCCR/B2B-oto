import { getApiBase } from './http.js';

const base = getApiBase();

export const API_ENDPOINTS = {
  USER: {
    PROFILE: `${base}/user/profile`,
    CAPABILITIES: `${base}/user/capabilities`,
    UPDATE: `${base}/user/update`,
  },
};
