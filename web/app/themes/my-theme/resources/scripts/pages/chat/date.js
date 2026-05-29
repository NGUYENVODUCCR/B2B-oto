export function todayDateValue() {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
}

export function formatDateValue(value) {
  if (!value) return '';

  const raw = String(value).trim();


  const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);

  if (!match) return raw;

  const [, year, month, day, hour, minute] = match;

  if (hour && minute) {
    return `${hour}:${minute} ${day}/${month}/${year}`;
  }

  return `${day}/${month}/${year}`;
}

export function formatDate(value) {
  return formatDateValue(value);
}
