// API хранит и принимает суммы в копейках (целые числа).
// В интерфейсе работаем в рублях — конвертируем на границе.

export function kopecksToRub(kopecks) {
  if (kopecks === null || kopecks === undefined || kopecks === '') return '';
  return (Number(kopecks) / 100).toFixed(2);
}

export function rubToKopecks(rub) {
  if (rub === null || rub === undefined || rub === '') return null;
  return Math.round(Number(rub) * 100);
}

export function formatMoney(kopecks) {
  if (kopecks === null || kopecks === undefined) return '—';
  return (Number(kopecks) / 100).toLocaleString('ru-RU', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }) + ' ₽';
}
