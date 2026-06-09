import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuthStore } from '../../store/authStore'
import dashboardService from '../../services/dashboardService'

const DashboardPage = () => {
  const { user } = useAuthStore()
  const [summary, setSummary] = useState(null)
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState(null)

  const now = new Date()
  const greeting =
    now.getHours() < 11 ? 'Selamat Pagi' :
    now.getHours() < 15 ? 'Selamat Siang' :
    now.getHours() < 18 ? 'Selamat Sore' : 'Selamat Malam'

  const role = String(user?.role || 'employee').toLowerCase()
  const cards = summary?.cards || {}
  const recentAttendances = Array.isArray(summary?.recent_attendances) ? summary.recent_attendances : []
  const recentLeaves = Array.isArray(summary?.recent_leaves) ? summary.recent_leaves : []

  useEffect(() => {
    const fetchSummary = async () => {
      setIsLoading(true)
      setError(null)
      try {
        const res = await dashboardService.getSummary()
        setSummary(res.data?.data || null)
      } catch (err) {
        console.error(err)
        setError(err.response?.data?.message || 'Gagal memuat dashboard.')
      } finally {
        setIsLoading(false)
      }
    }

    fetchSummary()
  }, [])

  const statCards = useMemo(() => {
    if (role === 'employee') {
      return [
        { label: 'Status Hari Ini', value: formatStatus(cards.attendance_status), icon: '📋', color: 'bg-blue-50 text-blue-600', border: 'border-blue-100' },
        { label: 'Check In', value: formatTime(cards.check_in_time), icon: '✅', color: 'bg-green-50 text-green-600', border: 'border-green-100' },
        { label: 'Check Out', value: formatTime(cards.check_out_time), icon: '🏁', color: 'bg-purple-50 text-purple-600', border: 'border-purple-100' },
        { label: 'Sisa Cuti', value: cards.remaining_leave ?? 0, icon: '🌴', color: 'bg-yellow-50 text-yellow-600', border: 'border-yellow-100' },
      ]
    }

    return [
      { label: 'Total Karyawan', value: cards.total_employees ?? 0, icon: '👥', color: 'bg-blue-50 text-blue-600', border: 'border-blue-100' },
      { label: 'Hadir Hari Ini', value: cards.present_today ?? 0, icon: '✅', color: 'bg-green-50 text-green-600', border: 'border-green-100' },
      { label: 'Terlambat', value: cards.late_today ?? 0, icon: '⏰', color: 'bg-orange-50 text-orange-600', border: 'border-orange-100' },
      { label: 'Pending Approval', value: cards.pending_leave_requests ?? 0, icon: '⏳', color: 'bg-red-50 text-red-600', border: 'border-red-100' },
    ]
  }, [role, cards])

  const quickLinks = useMemo(() => {
    const links = [
      { label: 'Absensi', desc: 'Catat kehadiran hari ini', icon: '📋', href: '/attendance' },
      { label: 'Pengajuan Cuti', desc: 'Ajukan cuti atau izin', icon: '🗂', href: '/leave' },
    ]

    if (['admin', 'hr', 'manager'].includes(role)) {
      links.push({ label: 'Persetujuan', desc: 'Kelola approval cuti', icon: '✅', href: '/approval' })
      links.push({ label: 'Laporan', desc: 'Laporan kehadiran', icon: '📈', href: '/report' })
    }

    if (['admin', 'hr'].includes(role)) {
      links.push({ label: 'Karyawan', desc: 'Data pegawai', icon: '👥', href: '/employee' })
      links.push({ label: 'Jadwal Shift', desc: 'Atur shift kerja', icon: '🗓', href: '/shift-schedule' })
    }

    return links
  }, [role])

  return (
    <div className="space-y-6">
      <div className="bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-2xl p-6 text-white">
        <p className="text-indigo-200 text-sm">{greeting},</p>
        <h1 className="text-2xl font-bold mt-1">{user?.name || 'Karyawan'} 👋</h1>
        <p className="text-indigo-200 text-sm mt-1">
          {now.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
        </p>
        <div className="mt-4 inline-flex items-center gap-2 bg-white/20 rounded-lg px-3 py-1.5">
          <span className="text-xs">🏢 {user?.department || 'HRIS MSR'}</span>
          <span className="text-indigo-300">•</span>
          <span className="text-xs capitalize">{user?.role || 'employee'}</span>
        </div>
      </div>

      {error && (
        <div className="bg-red-50 border border-red-100 text-red-700 rounded-xl p-4 text-sm">
          {error}
        </div>
      )}

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {statCards.map((card) => (
          <div key={card.label} className={`bg-white rounded-xl p-5 border ${card.border} shadow-sm`}>
            <div className={`w-10 h-10 rounded-lg ${card.color} flex items-center justify-center text-xl mb-3`}>
              {card.icon}
            </div>
            <p className="text-xs text-gray-500">{card.label}</p>
            <p className="text-2xl font-bold text-gray-900 mt-1">{isLoading ? '-' : card.value}</p>
          </div>
        ))}
      </div>

      <div>
        <h2 className="text-base font-semibold text-gray-800 mb-3">Akses Cepat</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          {quickLinks.map((link) => (
            <Link
              key={link.label}
              to={link.href}
              className="bg-white rounded-xl p-4 border border-gray-100 shadow-sm hover:shadow-md hover:border-indigo-200 transition-all group"
            >
              <span className="text-2xl block mb-2">{link.icon}</span>
              <p className="text-sm font-semibold text-gray-800 group-hover:text-indigo-600">{link.label}</p>
              <p className="text-xs text-gray-400 mt-0.5">{link.desc}</p>
            </Link>
          ))}
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <section className="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
          <h2 className="text-base font-semibold text-gray-800 mb-4">Absensi Terbaru</h2>
          {recentAttendances.length === 0 ? (
            <p className="text-sm text-gray-400">Belum ada data absensi.</p>
          ) : (
            <div className="space-y-3">
              {recentAttendances.map((item) => (
                <div key={item.id} className="flex items-center justify-between border-b border-gray-50 pb-2 last:border-0">
                  <div>
                    <p className="text-sm font-medium text-gray-800">{item.employee?.user?.name || item.employee_name || 'Karyawan'}</p>
                    <p className="text-xs text-gray-400">{formatDate(item.attendance_date)} • {formatStatus(item.status)}</p>
                  </div>
                  <span className="text-xs text-gray-500">{formatTime(item.check_in_time)}</span>
                </div>
              ))}
            </div>
          )}
        </section>

        <section className="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
          <h2 className="text-base font-semibold text-gray-800 mb-4">Cuti Terbaru</h2>
          {recentLeaves.length === 0 ? (
            <p className="text-sm text-gray-400">Belum ada data cuti.</p>
          ) : (
            <div className="space-y-3">
              {recentLeaves.map((item) => (
                <div key={item.id} className="flex items-center justify-between border-b border-gray-50 pb-2 last:border-0">
                  <div>
                    <p className="text-sm font-medium text-gray-800">{item.employee?.user?.name || item.employee_name || 'Karyawan'}</p>
                    <p className="text-xs text-gray-400">{item.leave_type || '-'} • {formatDate(item.start_date)}</p>
                  </div>
                  <span className="text-xs text-gray-500 capitalize">{item.status || '-'}</span>
                </div>
              ))}
            </div>
          )}
        </section>
      </div>
    </div>
  )
}

const formatTime = (value) => {
  if (!value) return '-'
  return String(value).slice(0, 5)
}

const formatDate = (value) => {
  if (!value) return '-'
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const formatStatus = (value) => {
  const map = {
    present: 'Hadir',
    late: 'Terlambat',
    absent: 'Tidak Hadir',
    leave: 'Cuti',
    not_checked_in: 'Belum Check-in',
    not_found: 'Profil tidak ditemukan',
  }

  return map[value] || value || '-'
}

export default DashboardPage
