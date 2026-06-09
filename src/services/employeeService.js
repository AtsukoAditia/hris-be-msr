import api from '../lib/axios'

const employeeService = {
  getAll: (params = {}) => api.get('/employees', { params }),
  getById: (id) => api.get(`/employees/${id}`),
  create: (data) => api.post('/employees', data),
  update: (id, data) => api.put(`/employees/${id}`, data),
  delete: (id) => api.delete(`/employees/${id}`),
  enrollFace: (id, file) => {
    const formData = new FormData()
    formData.append('face_image', file)

    return api.post(`/employees/${id}/face-enrollment`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
  },
}

export default employeeService
