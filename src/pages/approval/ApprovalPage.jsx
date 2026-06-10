import { useCallback, useEffect, useMemo, useState } from 'react'
import approvalService from '../../services/approvalService'

const STATUS_BADGE = {
  pending: 'bg-yellow-100 text-yellow-800',
  approved: 'bg-green-100 text-green-800',
  rejected: 'bg-red-100 text-red-800',
  cancelled: 'bg-gray-100 text-gray-700',
}

const ApprovalPage = () => {
  const [activeTab, setActiveTab] = useState('pending')
  const [pendingList, setPendingList] = useState([])
  const [historyList, setHistoryList] = useState([])
  const [isLoading, setIsLoading] = useState(false)
  const [actionLoading, setActionLoading] = useState(null)
  const [selectedItem, setSelectedItem] = useState(null)
  const [note, setNote] = useState('')
  const [showModal, setShowModal] = useState(false)
  const [modalAction, setModalAction] = useState(null)
  const [toast, setToast] = useState(null)
  const [filters, setFilters] = useState({ status: '', leave_type: '', search: '' })

  const showToast = (message, type = 'success') => {
    setToast({ message, type })
    setTimeout(() => setToast(null), 3000)
  }

  const normalizeRows = (payload) => {
    if (Array.isArray(payload)) return payload
    if (Array.isArray(payload?.data?.data)) return payload.data.data
    if (Array.isArray(payload?.data)) return payload.data
    return []
  }

  const fetchPending = useCallback(async () => {
    setIsLoading(true)
    try {
      const res = await approvalService.getPending({ per_page: 50 })
      setPendingList(normalizeRows(res.data))
    } catch (err) {
      console.error(err)
      setPendingList([])
      showToast(getErrorMessage(err, 'Gagal memuat daftar persetujuan.'), 'error')
    } finally {
      setIsLoading(false)
    }
  }, [])

  const fetchHistory = useCallback(async () => {
    setIsLoading(true)
    try {
      const params = { per_page: 50 }
      if (filters.status) params.status = filters.status
      if (filters.leave_type) params.leave_type = filters.leave_type

      const res = await approvalService.getHistory(params)
      setHistoryList(normalizeRows(res.data).filter((item) => item.status !== 'pending'))
    } catch (err) {
      console.error(err)
      setHistoryList([])
      showToast(getErrorMessage(err, 'Gagal memuat riwayat persetujuan.'), 'error')
    } finally {
      setIsLoading(false)
    }
  }, [filters.status, filters.leave_type])

  useEffect(() => {
    if (activeTab === 'pending') fetchPending()
    else fetchHistory()
  }, [activeTab, fetchPending, fetchHistory])

  const openModal = (item, action) => {
    setSelectedItem(item)
    setModalAction(action)
    setNote('')
    setShowModal(true)
  }

  const handleConfirmAction = async () => {
    if (!selectedItem || !modalAction) return

    if (modalAction === 'reject' && !note.trim()) {
      showToast('Alasan penolakan wajib diisi.', 'error')
      return
    }

    setActionLoading(selectedItem.id)
    try {
      if (modalAction === 'approve') {
        await approvalService.approve(selectedItem.id, { note: note.trim() })
        showToast('Pengajuan cuti berhasil disetujui.')
      } else {
        await approvalService.reject(selectedItem.id, { rejection_reason: note.trim() })
        showToast('Pengajuan cuti berhasil ditolak.', 'error')
      }

      setShowModal(false)
      setSelectedItem(null)
      setNote('')
      await Promise.all([fetchPending(), fetchHistory()])
    } catch (err) {
      console.error(err)
      showToast(getErrorMessage(err, 'Terjadi kesalahan saat memproses approval.'), 'error')
    } finally {
      setActionLoading(null)
    }
  }

  const filteredPendingList = useMemo(() => filterRows(pendingList, filters.search), [pendingList, filters.search])
  const filteredHistoryList = useMemo(() => filterRows(historyList, filters.search), [historyList, filters.search])

  return (
    <div className="space-y-6">
      {toast && <Toast toast={toast} />}

      <div>
        <h1 className="text-2xl font-bold text-gray-900">Persetujuan Cuti</h1>
        <p className="text-gray-500 text-sm mt-1">Kelola pengajuan cuti dan izin karyawan.</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <SummaryCard label="Pending" value={pendingList.length} subValue="menunggu persetujuan" />
        <SummaryCard label="Approved" value={historyList.filter((item) => item.status === 'approved').length} subValue="disetujui" />
        <SummaryCard label="Rejected" value={historyList.filter((item) => item.status === 'rejected').length} subValue="ditolak" />
      </div>

      <div className="flex border-b border-gray-200">
        <TabButton active={activeTab === 'pending'} onClick={() => setActiveTab('pending')}>
          Menunggu Persetujuan
          {pendingList.length > 0 && <span className="ml-2 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full">{pendingList.length}</span>}
        </TabButton>
        <TabButton active={activeTab === 'history'} onClick={() => setActiveTab('history')}>Riwayat</TabButton>
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4 grid grid-cols-1 md:grid-cols-3 gap-3">
        <input
          value={filters.search}
          onChange={(e) => setFilters((prev) => ({ ...prev, search: e.target.value }))}
          className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          placeholder="Cari nama, departemen, alasan..."
        />
        <select
          value={filters.leave_type}
          onChange={(e) => setFilters((prev) => ({ ...prev, leave_type: e.target.value }))}
          className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option value="">Semua jenis cuti</option>
          <option value="annual">Cuti Tahunan</option>
          <option value="sick">Cuti Sakit</option>
          <option value="emergency">Cuti Darurat</option>
          <option value="maternity">Cuti Melahirkan</option>
          <option value="paternity">Cuti Ayah</option>
          <option value="unpaid">Cuti Tidak Dibayar</option>
          <option value="other">Lainnya</option>
        </select>
        <select
          value={filters.status}
          onChange={(e) => setFilters((prev) => ({ ...prev, status: e.target.value }))}
          className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          disabled={activeTab === 'pending'}
        >
          <option value="">Semua status history</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-12">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
        </div>
      ) : (
        <div className="space-y-3">
          {activeTab === 'pending' && (
            filteredPendingList.length === 0 ? <EmptyState icon="✅" text="Tidak ada pengajuan yang menunggu." /> : filteredPendingList.map((item) => (
              <ApprovalCard key={item.id} item={item} showActions onOpenModal={openModal} actionLoading={actionLoading} />
            ))
          )}

          {activeTab === 'history' && (
            filteredHistoryList.length === 0 ? <EmptyState icon="📋" text="Belum ada riwayat persetujuan." /> : filteredHistoryList.map((item) => (
              <ApprovalCard key={item.id} item={item} actionLoading={actionLoading} />
            ))
          )}
        </div>
      )}

      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="text-lg font-semibold text-gray-900">
                  {modalAction === 'approve' ? 'Setujui Pengajuan' : 'Tolak Pengajuan'}
                </h3>
                <p className="text-sm text-gray-500 mt-1">
                  {getEmployeeName(selectedItem || {})} — {getLeaveType(selectedItem || {})}
                </p>
              </div>
              <button onClick={() => setShowModal(false)} className="text-gray-400 hover:text-gray-600">✕</button>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                {modalAction === 'reject' ? 'Alasan penolakan' : 'Catatan approval (opsional)'}
              </label>
              <textarea
                value={note}
                onChange={(e) => setNote(e.target.value)}
                rows={3}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                placeholder={modalAction === 'reject' ? 'Masukkan alasan penolakan...' : 'Tambahkan catatan jika perlu...'}
              />
            </div>

            <div className="flex gap-3">
              <button
                onClick={() => setShowModal(false)}
                className="flex-1 border border-gray-300 text-gray-700 py-2 rounded-lg text-sm font-medium hover:bg-gray-50"
              >
                Batal
              </button>
              <button
                onClick={handleConfirmAction}
                disabled={!!actionLoading}
                className={`flex-1 py-2 rounded-lg text-sm font-medium text-white transition-colors disabled:opacity-50 ${modalAction === 'approve' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-500 hover:bg-red-600'}`}
              >
                {actionLoading ? 'Memproses...' : modalAction === 'approve' ? 'Setujui' : 'Tolak'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

const Toast = ({ toast }) => (
  <div className={`fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg text-white text-sm font-medium ${toast.type === 'error' ? 'bg-red-500' : 'bg-green-500'}`}>
    {toast.message}
  </div>
)

const SummaryCard = ({ label, value, subValue }) => (
  <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
    <p className="text-sm text-gray-500">{label}</p>
    <p className="text-3xl font-bold text-gray-900 mt-2">{value}</p>
    <p className="text-xs text-gray-400 mt-1">{subValue}</p>
  </div>
)

const TabButton = ({ active, onClick, children }) => (
  <button
    onClick={onClick}
    className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${active ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
  >
    {children}
  </button>
)

const EmptyState = ({ icon, text }) => (
  <div className="text-center py-12 text-gray-400">
    <p className="text-4xl mb-2">{icon}</p>
    <p className="font-medium">{text}</p>
  </div>
)

const ApprovalCard = ({ item, showActions = false, onOpenModal = () => {}, actionLoading = null }) => (
  <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4 space-y-3">
    <div className="flex items-start justify-between gap-4">
      <div>
        <p className="font-semibold text-gray-900">{getEmployeeName(item)}</p>
        <p className="text-sm text-gray-500">{getLeaveType(item)}</p>
        <p className="text-xs text-gray-400 mt-0.5">{item.employee?.department || '-'} • {item.employee?.position || '-'}</p>
      </div>
      <span className={`text-xs px-2 py-1 rounded-full font-medium ${STATUS_BADGE[item.status] || 'bg-gray-100 text-gray-600'}`}>
        {item.status_label || getStatusLabel(item.status)}
      </span>
    </div>

    <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
      <Info label="Mulai" value={formatDate(item.start_date)} />
      <Info label="Selesai" value={formatDate(item.end_date)} />
      <Info label="Durasi" value={`${item.total_days || getCalendarDuration(item.start_date, item.end_date)} hari kerja`} />
      <Info label="Diajukan" value={formatDate(item.created_at)} />
    </div>

    {item.reason && <TextBlock label="Alasan" value={item.reason} />}
    {item.rejection_reason && <TextBlock label="Alasan Penolakan" value={item.rejection_reason} danger />}
    {item.approver?.name && <TextBlock label="Diproses oleh" value={`${item.approver.name}${item.approved_at ? ` pada ${formatDateTime(item.approved_at)}` : ''}`} />}

    {showActions && (
      <div className="flex gap-2 pt-1">
        <button
          onClick={() => onOpenModal(item, 'approve')}
          disabled={actionLoading === item.id}
          className="flex-1 bg-green-600 hover:bg-green-700 text-white text-sm font-medium py-2 rounded-lg transition-colors disabled:opacity-50"
        >
          Setujui
        </button>
        <button
          onClick={() => onOpenModal(item, 'reject')}
          disabled={actionLoading === item.id}
          className="flex-1 bg-red-500 hover:bg-red-600 text-white text-sm font-medium py-2 rounded-lg transition-colors disabled:opacity-50"
        >
          Tolak
        </button>
      </div>
    )}
  </div>
)

const Info = ({ label, value }) => (
  <div>
    <p className="text-gray-400 text-xs">{label}</p>
    <p className="text-gray-700">{value || '-'}</p>
  </div>
)

const TextBlock = ({ label, value, danger = false }) => (
  <div>
    <p className="text-gray-400 text-xs">{label}</p>
    <p className={`text-sm ${danger ? 'text-red-600 italic' : 'text-gray-700'}`}>{value}</p>
  </div>
)

const filterRows = (rows, keyword) => {
  if (!keyword) return rows
  const q = keyword.toLowerCase()
  return rows.filter((item) => [
    getEmployeeName(item),
    item.employee?.department,
    item.employee?.position,
    item.reason,
    item.rejection_reason,
    item.leave_type_label,
    item.status_label,
  ].some((value) => String(value || '').toLowerCase().includes(q)))
}

const getErrorMessage = (err, fallback) => {
  const errors = err.response?.data?.errors
  if (errors) {
    const firstKey = Object.keys(errors)[0]
    const firstMessage = errors[firstKey]?.[0]
    if (firstMessage) return firstMessage
  }

  return err.response?.data?.message || fallback
}

const getEmployeeName = (item) => item.employee?.name || item.employee?.user?.name || item.employee_name || item.user?.name || 'Karyawan'
const getLeaveType = (item) => item.leave_type_label || item.leave_type || item.type || item.request_type || 'Leave Request'
const getStatusLabel = (status) => ({ pending: 'Menunggu', approved: 'Disetujui', rejected: 'Ditolak', cancelled: 'Dibatalkan' }[status] || '-')

const getCalendarDuration = (start, end) => {
  if (!start || !end) return 0
  const s = new Date(start)
  const e = new Date(end)
  const diff = Math.ceil((e - s) / (1000 * 60 * 60 * 24)) + 1
  return diff > 0 ? diff : 0
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const formatDateTime = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

export default ApprovalPage
