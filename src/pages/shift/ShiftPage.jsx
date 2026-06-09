import { useEffect, useMemo, useState } from 'react'
import { Clock, Plus, Search } from 'lucide-react'
import shiftService from '../../services/shiftService'
import ShiftList from '../../components/shift/ShiftList'
import ShiftFormModal from '../../components/shift/ShiftFormModal'

const normalizeRows = (payload) => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.data?.data)) return payload.data.data
  return []
}

const ShiftPage = () => {
  const [shifts, setShifts] = useState([])
  const [isLoading, setIsLoading] = useState(false)
  const [showModal, setShowModal] = useState(false)
  const [editingShift, setEditingShift] = useState(null)
  const [searchQuery, setSearchQuery] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [toast, setToast] = useState(null)

  const showToast = (message, type = 'success') => {
    setToast({ message, type })
    setTimeout(() => setToast(null), 3000)
  }

  const fetchShifts = async () => {
    setIsLoading(true)
    try {
      const params = {}
      if (searchQuery.trim()) params.search = searchQuery.trim()
      if (statusFilter) params.status = statusFilter

      const res = await shiftService.getAll(params)
      setShifts(normalizeRows(res.data))
    } catch (err) {
      console.error('Error fetching shifts:', err)
      setShifts([])
      showToast(err.response?.data?.message || 'Gagal memuat data shift', 'error')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    const timer = setTimeout(fetchShifts, 300)
    return () => clearTimeout(timer)
  }, [searchQuery, statusFilter])

  const handleAdd = () => {
    setEditingShift(null)
    setShowModal(true)
  }

  const handleEdit = (shift) => {
    setEditingShift(shift)
    setShowModal(true)
  }

  const handleDelete = async (shift) => {
    const label = shift?.name || 'shift ini'
    if (!window.confirm(`Hapus/nonaktifkan ${label}?`)) return

    try {
      const res = await shiftService.remove(shift.id)
      showToast(res.data?.message || 'Shift berhasil diproses')
      fetchShifts()
    } catch (err) {
      console.error('Error deleting shift:', err)
      showToast(err.response?.data?.message || 'Gagal menghapus shift', 'error')
    }
  }

  const handleCloseModal = () => {
    setShowModal(false)
    setEditingShift(null)
  }

  const handleSaveSuccess = (message = 'Shift berhasil disimpan') => {
    showToast(message)
    fetchShifts()
    handleCloseModal()
  }

  const stats = useMemo(() => ({
    total: shifts.length,
    active: shifts.filter((shift) => shift.is_active).length,
    overnight: shifts.filter((shift) => shift.is_overnight).length,
  }), [shifts])

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Manajemen Shift</h1>
          <p className="text-gray-600 mt-1">Kelola shift kerja untuk absensi dan jadwal karyawan</p>
        </div>
        <button
          type="button"
          onClick={handleAdd}
          className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
        >
          <Plus className="w-4 h-4 mr-2" />
          Tambah Shift
        </button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <StatCard label="Total Shift" value={stats.total} />
        <StatCard label="Shift Aktif" value={stats.active} />
        <StatCard label="Overnight" value={stats.overnight} />
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div className="relative md:col-span-2">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5" />
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Cari nama shift, kode, atau keterangan..."
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
        <ShiftList
          shifts={shifts}
          isLoading={isLoading}
          onEdit={handleEdit}
          onDelete={handleDelete}
        />
      </div>

      {showModal && (
        <ShiftFormModal
          shift={editingShift}
          onClose={handleCloseModal}
          onSaveSuccess={handleSaveSuccess}
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

const StatCard = ({ label, value }) => (
  <div className="bg-white rounded-xl border border-gray-200 p-4 shadow-sm flex items-center gap-3">
    <div className="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
      <Clock className="w-5 h-5" />
    </div>
    <div>
      <p className="text-xs text-gray-500">{label}</p>
      <p className="text-xl font-bold text-gray-900">{value}</p>
    </div>
  </div>
)

export default ShiftPage
