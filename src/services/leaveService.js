import api from '../lib/axios'

const leaveService = {
  getMyLeaves: (params = {}) => api.get('/leaves/my', { params }),
  getAll: (params = {}) => api.get('/leaves', { params }),
  getById: (id) => api.get(`/leaves/${id}`),
  create: (data) => api.post('/leaves', data),
  cancel: (id) => api.delete(`/leaves/${id}`),
  approve: (id, data = {}) => api.post(`/leaves/${id}/approve`, data),
  reject: (id, data = {}) => api.post(`/leaves/${id}/reject`, data),
  getBalance: (params = {}) => api.get('/leaves/balance', { params }),
}

export default leaveService
