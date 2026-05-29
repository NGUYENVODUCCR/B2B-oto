import { getHomeUrl } from '../api/http.js';

export function redirectTo(path) {
  const target = getHomeUrl(path);

  if (window.location.href === target) {
    return;
  }

  window.location.replace(target);
}
