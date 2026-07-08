// Экспорт в Excel требует авторизации через Bearer-токен, поэтому нельзя просто
// открыть ссылку в новой вкладке — нужно скачать как blob через axios и отдать
// браузеру принудительное сохранение файла.
export async function downloadFile(apiClient, url, params, filename) {
  const response = await apiClient.get(url, { params, responseType: 'blob' });
  const blobUrl = URL.createObjectURL(response.data);
  const link = document.createElement('a');
  link.href = blobUrl;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(blobUrl);
}
