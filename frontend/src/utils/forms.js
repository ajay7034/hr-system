export function buildFormData(values) {
  const formData = new FormData();

  Object.entries(values).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') {
      return;
    }

    if (typeof File !== 'undefined' && value instanceof File) {
      formData.append(key, value);
      return;
    }

    if (typeof value === 'boolean') {
      formData.append(key, value ? '1' : '0');
      return;
    }

    formData.append(key, value);
  });

  return formData;
}
