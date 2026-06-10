import { useCallback, useEffect, useMemo, useState } from 'react'
import leaveService from '../../services/leaveService'

const initialForm = {
  leave_type: 'annual',
  start_date: '',
  end_date: '',
  reason: '',
}

const STATUS_BADGE = {
  pending: 'bg-yellow-100 text-yellow-800',
  approved: 'bg-green-100 text-green-800',
  rejected: 'bg-red-100 text-red-800',
  cancelled: 'bg-gray-100 text-gray-700',
}

const LEAVE_TYPES = [
  ['annual', 'Cuti Tahunan'],
  ['sick', 'Cuti Sakit'],
  ['emergency', 'Cuti Darurat'],
  ['maternity', 'Cuti Melahirkan'],
  ['paternity', 'Cuti Ayah'],
  ['unpaid', 'Cuti Tidak Dibayar'],
  ['other', 'Lainnya'],
]

const LeavePage = () => {
  const [activeTab, setActiveTab] = useState('request')
  const [leaveHistory, setLeaveHistory] = useState([])
  const [leaveBalance, setLeaveBalance] = useState(null)
  const [isLoading, setIsLoading] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [showRequestModal, setShowRequestModal] = useState(false)
  const [leaveForm, setLeaveForm] = useState(initialForm)
  const [toast, setToast] = useState(null)

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

  const fetchLeaveHistory = useCallback(async () => {
    setIsLoading(true)
    try {
      const res = await leaveService.getMyLeaves({ per_page: 50 })
      setLeaveHistory(normalizeRows(res.data))
    } catch (err) {
      console.error(err)
      setLeaveHistory([])
      showToast(err.response?.data?.message || 'Gagal memuat riwayat cuti.', 'error')
    } finally {
      setIsLoading(false)
    }
  }, [])

  const fetchLeaveBalance = useCallback(async () => {
    try {
      const res = await leaveService.getBalance()
      setLeaveBalance(res.data?.data || { total: 12, used: 0, remaining: 12 })
    } catch (err) {
      console.error(err)
      setLeaveBalance({ total: 12, used: 0, remaining: 12 })
    }
  }, [])

  useEffect(() => {
    fetchLeaveHistory()
    fetchLeaveBalance()
  }, [fetchLeaveHistory, fetchLeaveBalance])

  const durationPreview = useMemo(
    () => getCalendarDuration(leaveForm.start_date, leaveForm.end_date),
    [leaveForm.start_date, leaveForm.end_date]
  )

  const handleSubmitRequest = async () => {
    if (!leaveForm.leave_type || !leaveForm.start_date || !leaveForm.end_date || !leaveForm.reason.trim()) {
      showToast('Harap lengkapi semua field.', 'error')
      return
    }

    try {
      setIsSubmitting(true)
      await leaveService.create({
        leave_type: leaveForm.leave_type,
        start_date: leaveForm.start_date,
        end_date: leaveForm.end_date,
        reason: leaveForm.reason.trim(),
      })
      showToast('Pengajuan cuti berhasil dikirim.')
      setShowRequestModal(false)
      setLeaveForm(initialForm)
      setActiveTab('history')
      await Promise.all([fetchLeaveHistory(), fetchLeaveBalance()])
    } catch (err) {
      console.error(err)
      showToast(getErrorMessage(err, 'Gagal mengajukan cuti.'), 'error')
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleCancel = async (leave) => {
    if (!window.confirm('Batalkan pengajuan cuti ini?')) return

    try {
      await leaveService.cancel(leave.id)
      showToast('Pengajuan cuti berhasil dibatalkan.')
      await Promise.all([fetchLeaveHistory(), fetchLeaveBalance()])
    } catch (err) {
      console.error(err)
      showToast(getErrorMessage(err, 'Gagal membatalkan pengajuan cuti.'), 'error')
    }
  }

  return (
    <div className="space-y-6">
      {toast && <Toast toast={toast} />}

      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Permohonan Cuti</h1>
          <p className="text-gray-500 text-sm mt-1">Ajukan dan pantau status cuti Anda.</p>
        </div>
        <button
          onClick={() => setShowRequestModal(true)}
          className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
        >
          + Ajukan Cuti
        </button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <BalanceCard label="Sisa Cuti" value={leaveBalance?.remaining ?? 12} subValue={`dari ${leaveBalance?.total ?? 12} hari`} />
        <BalanceCard label="Terpakai" value={leaveBalance?.used ?? 0} subValue={`tahun ${leaveBalance?.year ?? new Date().getFullYear()}`} />
        <BalanceCard label="Pending" value={leaveHistory.filter((leave) => leave.status === 'pending').length} subValue="menunggu approval" />
      </div>

      <div className="flex border-b border-gray-200">
        {[
          ['request', 'Ajukan Cuti'],
          ['history', 'Riwayat Cuti'],
        ].map(([tab, label]) => (
          <button
            key={tab}
            onClick={() => setActiveTab(tab)}
            className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
              activeTab === tab
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      {isLoading ? (
        <div className="flex justify-center py-12">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
        </div>
      ) : activeTab === 'request' ? (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center text-gray-500">
          <p className="text-4xl mb-3">🏝️</p>
          <p className="font-medium mb-2">Ajukan Cuti Baru</p>
          <p className="text-sm mb-4">Klik tombol "Ajukan Cuti" untuk membuat permohonan baru.</p>
          <button
            onClick={() => setShowRequestModal(true)}
            className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
          >
            Ajukan Sekarang
          </button>
        </div>
      ) : leaveHistory.length === 0 ? (
        <div className="text-center py-12 text-gray-400">
          <p className="text-4xl mb-2">📝</p>
          <p className="font-medium">Belum ada riwayat cuti.</p>
        </div>
      ) : (
        <div className="space-y-3">
          {leaveHistory.map((leave) => (
            <LeaveCard key={leave.id} leave={leave} onCancel={handleCancel} />
          ))}
        </div>
      )}

      {showRequestModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="text-lg font-semibold text-gray-900">Ajukan Permohonan Cuti</h3>
                <p className="text-sm text-gray-500 mt-1">Data akan masuk ke approval Admin/HR/Manager.</p>
              </div>
              <button onClick={() => setShowRequestModal(false)} className="text-gray-400 hover:text-gray-600">✕</button>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Jenis Cuti</label>
              <select
                value={leaveForm.leave_type}
                onChange={(e) => setLeaveForm({ ...leaveForm, leave_type: e.target.value })}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                {LEAVE_TYPES.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
              </select>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <DateInput label="Tanggal Mulai" value={leaveForm.start_date} onChange={(value) => setLeaveForm({ ...leaveForm, start_date: value })} />
              <DateInput label="Tanggal Selesai" value={leaveForm.end_date} onChange={(value) => setLeaveForm({ ...leaveForm, end_date: value })} />
            </div>

            {leaveForm.start_date && leaveForm.end_date && (
              <p className="text-sm text-indigo-600 font-medium">Durasi kalender: {durationPreview} hari</p>
            )}

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Alasan</label>
              <textarea
                value={leaveForm.reason}
                onChange={(e) => setLeaveForm({ ...leaveForm, reason: e.target.value })}
                rows={3}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                placeholder="Tuliskan alasan cuti..."
              />
            </div>

            <div className="flex gap-3">
              <button
                onClick={() => setShowRequestModal(false)}
                className="flex-1 border border-gray-300 text-gray-700 py-2 rounded-lg text-sm font-medium hover:bg-gray-50"
              >
                Batal
              </button>
              <button
                onClick={handleSubmitRequest}
                disabled={isSubmitting}
                className="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-lg text-sm font-medium disabled:opacity-50"
              >
                {isSubmitting ? 'Mengirim...' : 'Kirim'}
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

const BalanceCard = ({ label, value, subValue }) => (
  <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
    <p className="text-sm text-gray-500">{label}</p>
    <p className="text-3xl font-bold text-gray-900 mt-2">{value}</p>
    <p className="text-xs text-gray-400 mt-1">{subValue}</p>
  </div>
)

const DateInput = ({ label, value, onChange }) => (
  <div>
    <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
    <input
      type="date"
      value={value}
      onChange={(e) => onChange(e.target.value)}
      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
    />
  </div>
)

const LeaveCard = ({ leave, onCancel }) => (
  <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4 space-y-3">
    <div className="flex items-start justify-between gap-4">
      <div>
        <p className="font-semibold text-gray-900">{leave.leave_type_label || getLeaveTypeLabel(leave.leave_type)}</p>
        <p className="text-sm text-gray-500 mt-0.5">{formatDate(leave.start_date)} – {formatDate(leave.end_date)}</p>
      </div>
      <span className={`text-xs px-2 py-1 rounded-full font-medium ${STATUS_BADGE[leave.status] || 'bg-gray-100 text-gray-600'}`}>
        {leave.status_label || getStatusLabel(leave.status)}
      </span>
    </div>

    <div className="flex items-center gap-3 text-sm text-gray-600">
      <span>📅 {leave.total_days || getCalendarDuration(leave.start_date, leave.end_date)} hari kerja</span>
      {leave.approver?.name && <span>👤 {leave.approver.name}</span>}
    </div>

    {leave.reason && (
      <div className="pt-2 border-t border-gray-50">
        <p className="text-xs text-gray-400">Alasan:</p>
        <p className="text-sm text-gray-700 mt-0.5">{leave.reason}</p>
      </div>
    )}

    {leave.rejection_reason && (
      <div>
        <p className="text-xs text-gray-400">Alasan penolakan:</p>
        <p className="text-sm text-red-600 italic mt-0.5">{leave.rejection_reason}</p>
      </div>
    )}

    {leave.status === 'pending' && (
      <button
        onClick={() => onCancel(leave)}
        className="text-sm text-red-600 hover:text-red-700 font-medium"
      >
        Batalkan Pengajuan
      </button>
    )}
  </div>
)

const getErrorMessage = (err, fallback) => {
  const errors = err.response?.data?.errors
  if (errors) {
    const firstKey = Object.keys(errors)[0]
    const firstMessage = errors[firstKey]?.[0]
    if (firstMessage) return firstMessage
  }

  return err.response?.data?.message || fallback
}

const getCalendarDuration = (start, end) => {
  if (!start || !end) return 0
  const s = new Date(start)
  const e = new Date(end)
  const diff = Math.ceil((e - s) / (1000 * 60 * 60 * 24)) + 1
  return diff > 0 ? diff : 0
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

const getLeaveTypeLabel = (type) => {
  const found = LEAVE_TYPES.find(([value]) => value === type)
  return found?.[1] || 'Cuti'
}

const getStatusLabel = (status) => ({
  pending: 'Menunggu',
  approved: 'Disetujui',
  rejected: 'Ditolak',
  cancelled: 'Dibatalkan',
}[status] || '-')

export default LeavePage
