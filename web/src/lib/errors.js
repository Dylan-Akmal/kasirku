export function errorMessage(e, fallback = 'Terjadi kesalahan. Coba lagi.') {
  const errors = e.response?.data?.errors

  if (errors) {
    return Object.values(errors).flat()[0]
  }

  return e.response?.data?.message || fallback
}