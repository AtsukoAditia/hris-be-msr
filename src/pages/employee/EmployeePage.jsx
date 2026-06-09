import { useState, useEffect, useCallback } from 'react'
import { Plus, Search, Users, UserCheck, UserX } from 'lucide-react'
import employeeService from '../../services/employeeService'
import { useAuthStore } from '../../store/authStore'
import EmployeeTable from './components/EmployeeTable'
import EmployeeFormModal from './components/EmployeeFormModal'
import EmployeeDetailModal from './components/EmployeeDetailModal'
import { initialFormData, mapFormDataToPayload, normalizeEmployee, normalizePagination, normalizeRows } from './employee.helpers'

const EmployeePage = () => {
  const { user } = useAuthStore()
  const canManageEmployee = ['admin', 'hr'].includes(user?.role)
  const canDeleteEmployee = user?.role === 'admin'

  const [employees, setEmployees] = useState([])
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, per_page: 10, total: 0 })
  const [isLoading, setIsLoading] = useState(false)
  const [selectedEmployee, setSelectedEmployee] = useState(null)
  const [showDetailModal, setShowDetailModal] = useState(false)
  const [showFormModal, setShowFormModal] = useState(false)
  const [isEditing, setIsEditing] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [toast, setToast] = useState(null)
  const [formData, setFormData] = useState(initialFormData)

  const showToast = (message, type = 'success') => {
    setToast({ message, type })
    setTimeout(() => setToast(null), 3000)
  }

  const fetchEmployees = useCallback(async (page = 1) => {
    setIsLoading(true)
    try {
      const params = {
        page,
        per_page: pagination.per_page,
      }

      if (searchQuery.trim()) params.search = searchQuery.trim()
      if (statusFilter) params.status = statusFilter

      const res = await employeeService.getAll(params)
      const paginatedEmployees = res.data?.data
      const rows = normalizeRows(paginatedEmployees)

      setEmployees(rows.map(normalizeEmployee).filter(Boolean))
      setPagination(normalizePagination(paginatedEmployees))
    } catch (err) {
      console.error(err)
      setEmployees([])
      showToast(err.response?.data?.message || 'Gagal memuat data karyawan', 'error')
    } finally {
      setIsLoading(false)
    }
  }, [pagination.per_page, searchQuery, statusFilter])

  useEffect(() => {
    const timer = setTimeout(() => {
      fetchEmployees(1)
    }, 350)

    return () => clearTimeout(timer)
  }, [fetchEmployees])

  const handleInputChange = (e) => {
    const { name, value } = e.target
    setFormData((prev) => ({ ...prev, [name]: value }))
  }

  const handleAddClick = () => {
    if (!canManageEmployee) return

    setIsEditing(false)
    setSelectedEmployee(null)
    setFormData({
      ...initialFormData,
      join_date: new Date().toISOString().split('T')[0],
    })
    setShowFormModal(true)
  }

  const handleEditClick = (employee) => {
    if (!canManageEmployee) return

    const normalizedEmployee = normalizeEmployee(employee)
    setIsEditing(true)
    setSelectedEmployee(normalizedEmployee)
    setFormData({
      name: normalizedEmployee?.name || '',
      email: normalizedEmployee?.email || '',
      nik: normalizedEmployee?.nik || '',
      phone: normalizedEmployee?.phone || '',
      address: normalizedEmployee?.address || '',
      birth_date: normalizedEmployee?.birth_date || '',
      gender: normalizedEmployee?.gender || '',
      position: normalizedEmployee?.position || '',
      department: normalizedEmployee?.department || '',
      join_date: normalizedEmployee?.join_date || '',
      employment_type: normalizedEmployee?.employment_type || 'permanent',
      status: normalizedEmployee?.status || 'active',
      role: normalizedEmployee?.role || 'employee',
    })
    setShowFormModal(true)
  }

  const handleDetailClick = (employee) => {
    setSelectedEmployee(normalizeEmployee(employee))
    setShowDetailModal(true)
  }

  const handleFaceUpdated = (employee) => {
    const normalizedEmployee = normalizeEmployee(employee)
    if (!normalizedEmployee) return

    setSelectedEmployee(normalizedEmployee)
    setEmployees((prev) => prev.map((item) => (item.id === normalizedEmployee.id ? normalizedEmployee : item)))
    showToast('Foto wajah absensi berhasil disimpan')
  }

  const handleDeleteClick = async (employee) => {
    if (!canDeleteEmployee) return

    const normalizedEmployee = normalizeEmployee(employee)
    const confirmDelete = window.confirm(`Hapus data karyawan ${normalizedEmployee?.name || 'ini'}? Data akan masuk ke soft delete.`)
    if (!confirmDelete) return

    try {
      await employeeService.delete(normalizedEmployee.id)
      showToast('Data karyawan berhasil dihapus')
      fetchEmployees(pagination.current_page)
    } catch (err) {
      console.error(err)
      showToast(err.response?.data?.message || 'Gagal menghapus data karyawan', 'error')
    }
  }

  const handleSubmit = async (e) => {
    e.preventDefault()

    if (!canManageEmployee) {
      showToast('Anda tidak memiliki akses untuk mengelola data karyawan.', 'error')
      return
    }

    try {
      const payload = mapFormDataToPayload(formData)

      if (isEditing && selectedEmployee) {
        await employeeService.update(selectedEmployee.id, payload)
        showToast('Data karyawan berhasil diperbarui')
      } else {
        await employeeService.create(payload)
        showToast('Data karyawan berhasil ditambahkan. Password default: password123')
      }

      setShowFormModal(false)
      setFormData(initialFormData)
      setSelectedEmployee(null)
      setIsEditing(false)
      fetchEmployees(isEditing ? pagination.current_page : 1)
    } catch (err) {
      console.error(err)
      showToast(err.response?.data?.message || 'Gagal menyimpan data karyawan', 'error')
    }
  }

  const stats = {
    total: pagination.total,
    active: employees.filter((employee) => employee.status === 'active').length,
    inactive: employees.filter((employee) => employee.status === 'inactive').length,
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Management Pegawai</h1>
          <p className="text-gray-600 mt-1">Kelola data pegawai dan informasi SDM</p>
        </div>
        {canManageEmployee && (
          <button
            onClick={handleAddClick}
            className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            <Plus className="w-4 h-4 mr-2" />
            Tambah Pegawai
          </button>
        )}
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <StatCard icon={<Users className="w-5 h-5" />} label="Total Data" value={stats.total} />
        <StatCard icon={<UserCheck className="w-5 h-5" />} label="Aktif di Halaman Ini" value={stats.active} />
        <StatCard icon={<UserX className="w-5 h-5" />} label="Nonaktif di Halaman Ini" value={stats.inactive} />
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div className="relative md:col-span-2">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Cari nama, email, NIK, departemen, jabatan..."
              className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">Semua Status</option>
            <option value="active">Aktif</option>
            <option value="inactive">Nonaktif</option>
          </select>
        </div>
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <EmployeeTable
          employees={employees}
          isLoading={isLoading}
          canManageEmployee={canManageEmployee}
          canDeleteEmployee={canDeleteEmployee}
          onDetail={handleDetailClick}
          onEdit={handleEditClick}
          onDelete={handleDeleteClick}
        />
      </div>

      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-sm text-gray-600">
        <p>
          Menampilkan halaman {pagination.current_page} dari {pagination.last_page} • Total {pagination.total} data
        </p>
        <div className="flex gap-2">
          <button
            type="button"
            disabled={pagination.current_page <= 1 || isLoading}
            onClick={() => fetchEmployees(pagination.current_page - 1)}
            className="px-3 py-2 border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
          >
            Sebelumnya
          </button>
          <button
            type="button"
            disabled={pagination.current_page >= pagination.last_page || isLoading}
            onClick={() => fetchEmployees(pagination.current_page + 1)}
            className="px-3 py-2 border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
          >
            Berikutnya
          </button>
        </div>
      </div>

      {showFormModal && canManageEmployee && (
        <EmployeeFormModal
          isEditing={isEditing}
          formData={formData}
          onChange={handleInputChange}
          onClose={() => setShowFormModal(false)}
          onSubmit={handleSubmit}
        />
      )}

      {showDetailModal && (
        <EmployeeDetailModal
          employee={selectedEmployee}
          onClose={() => setShowDetailModal(false)}
          onFaceUpdated={handleFaceUpdated}
        />
      )}

      {toast && (
        <div className={`fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white ${toast.type === 'error' ? 'bg-red-500' : 'bg-green-500'}`}>
          {toast.message}
        </div>
      )}
    </div>
  )
}

const StatCard = ({ icon, label, value }) => (
  <div className="bg-white rounded-xl border border-gray-200 p-4 shadow-sm flex items-center gap-3">
    <div className="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
      {icon}
    </div>
    <div>
      <p className="text-xs text-gray-500">{label}</p>
      <p className="text-xl font-bold text-gray-900">{value}</p>
    </div>
  </div>
)

export default EmployeePage
