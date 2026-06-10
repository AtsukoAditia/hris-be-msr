import api from '../lib/axios'

const approvalService = {
  getPending: (params = {}) => api.get('/leaves', { params: { ...params, status: 'pending' } }),
  getAll: (params = {}) => api.get('/leaves', { params }),
  getById: (id) => api.get(`/leaves/${id}`),
  approve: (id, data = {}) => api.post(`/leaves/${id}/approve`, data),
  reject: (id, data = {}) => api.post(`/leaves/${id}/reject`, data),
  getHistory: (params = {}) => api.get('/leaves', { params: { ...params, status: 'approved,rejected,cancelled' } }),
}

export default approvalService
