import api from '../lib/axios'

const toFormData = (data = {}) => {
  const formData = new FormData()

  Object.entries(data).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      formData.append(key, value)
    }
  })

  return formData
}

const postAttendance = (url, data = {}) => {
  if (data.photo) {
    return api.post(url, toFormData(data), {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
  }

  return api.post(url, data)
}

export const attendanceService = {
  /** Riwayat absensi milik user yang sedang login */
  getMyAttendances: (params) => api.get('/attendance/my', { params }),

  /** Absensi hari ini milik user yang sedang login */
  getToday: () => api.get('/attendance/today'),

  /** Check-in dengan lokasi dan optional photo evidence */
  checkIn: (data) => postAttendance('/attendance/check-in', data),

  /** Check-out dengan lokasi dan optional photo evidence */
  checkOut: (data) => postAttendance('/attendance/check-out', data),

  /** Check-in via QR code */
  checkInQr: (data) => postAttendance('/attendance/check-in/qr', data),

  /** Check-out via QR code */
  checkOutQr: (data) => postAttendance('/attendance/check-out/qr', data),

  /** Data absensi satu karyawan tertentu (admin/hr) */
  getByEmployee: (employeeId, params) =>
    api.get(`/attendance/employee/${employeeId}`, { params }),

  /** Semua absensi - monitoring admin/hr */
  getAll: (params) => api.get('/attendance', { params }),

  /** Export absensi ke Excel */
  exportExcel: (params) =>
    api.get('/attendance/export', {
      params,
      responseType: 'blob',
    }),
}
