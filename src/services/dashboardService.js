import api from '../lib/axios'

const dashboardService = {
  getSummary: () => api.get('/dashboard/summary'),
}

export default dashboardService
