import { useEffect, useMemo, useRef, useState } from 'react'
import { Camera, MapPin, RefreshCw } from 'lucide-react'
import { useAttendanceStore } from '../../store/attendanceStore'
import { attendanceService } from '../../services/attendanceService'
import { useAuthStore } from '../../store/authStore'

const initialFilters = {
  employee_id: '',
  date_from: '',
  date_to: '',
  status: '',
  search: '',
}

const AttendancePage = () => {
  const { user } = useAuthStore()
  const isAdminLike = ['admin', 'hr', 'manager'].includes(String(user?.role || '').toLowerCase())
  const checkInPhotoRef = useRef(null)
  const checkOutPhotoRef = useRef(null)

  const {
    todayAttendance,
    attendanceList = [],
    setAttendanceList,
    setTodayAttendance,
  } = useAttendanceStore()

  const [todayShift, setTodayShift] = useState(null)
  const [isLoading, setIsLoading] = useState(false)
  const [actionLoading, setActionLoading] = useState(false)
  const [location, setLocation] = useState(null)
  const [message, setMessage] = useState(null)
  const [filters, setFilters] = useState(initialFilters)
  const [note, setNote] = useState('')

  useEffect(() => {
    if (isAdminLike) {
      fetchAdminAttendances()
      return
    }

    fetchInitialData()
    getCurrentLocation()
  }, [])

  const normalizeAttendanceList = (payload) => {
    if (payload?.data?.data) return payload.data.data
    if (Array.isArray(payload?.data)) return payload.data
    if (Array.isArray(payload)) return payload
    return []
  }

  const normalizeTodayPayload = (payload) => {
    if (payload && Object.prototype.hasOwnProperty.call(payload, 'attendance')) {
      return {
        attendance: payload.attendance || null,
        shift: payload.shift || null,
      }
    }

    return {
      attendance: payload || null,
      shift: payload?.shift || null,
    }
  }

  const fetchInitialData = async () => {
    setIsLoading(true)
    await Promise.all([fetchAttendances(), fetchTodayAttendance()])
    setIsLoading(false)
  }

  const shouldSuppressAttendanceError = (err) => {
    const status = err?.response?.status
    return isAdminLike && [404, 403, 422].includes(status)
  }

  const fetchAttendances = async () => {
    try {
      const res = await attendanceService.getMyAttendances()
      const list = normalizeAttendanceList(res.data)
      setAttendanceList(list)
    } catch (err) {
      console.error(err)
      setAttendanceList([])

      if (!shouldSuppressAttendanceError(err)) {
        setMessage({ type: 'error', text: err.response?.data?.message || 'Gagal memuat riwayat absensi.' })
      }
    }
  }

  const fetchTodayAttendance = async () => {
    try {
      const res = await attendanceService.getToday()
      const normalized = normalizeTodayPayload(res.data?.data || null)
      setTodayAttendance(normalized.attendance)
      setTodayShift(normalized.shift)
    } catch (err) {
      console.error(err)
      setTodayAttendance(null)
      setTodayShift(null)

      if (!shouldSuppressAttendanceError(err)) {
        setMessage({ type: 'error', text: err.response?.data?.message || 'Gagal memuat absensi hari ini.' })
      }
    }
  }

  const fetchAdminAttendances = async () => {
    try {
      setIsLoading(true)
      setMessage(null)

      const params = {}
      if (filters.employee_id) params.employee_id = filters.employee_id
      if (filters.date_from) params.date_from = filters.date_from
      if (filters.date_to) params.date_to = filters.date_to
      if (filters.status) params.status = filters.status

      const res = await attendanceService.getAll(params)
      const list = normalizeAttendanceList(res.data)
      setAttendanceList(list)
    } catch (err) {
      console.error(err)
      setAttendanceList([])
      setMessage({ type: 'error', text: err.response?.data?.message || 'Gagal memuat daftar absensi karyawan.' })
    } finally {
      setIsLoading(false)
    }
  }

  const getCurrentLocation = () => {
    if (!navigator.geolocation) {
      setMessage({ type: 'error', text: 'Browser tidak mendukung geolocation.' })
      return
    }

    navigator.geolocation.getCurrentPosition(
      (pos) => setLocation({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
      (err) => {
        console.error('Location error:', err)
        setMessage({ type: 'error', text: 'Gagal mengambil lokasi. Izinkan akses lokasi untuk absensi.' })
      },
      { enableHighAccuracy: true, timeout: 15000 }
    )
  }

  const buildAttendancePayload = (file) => ({
    latitude: location?.lat,
    longitude: location?.lng,
    note,
    photo: file || null,
  })

  const handleCheckIn = async (file = null) => {
    try {
      setActionLoading(true)
      setMessage(null)
      const res = await attendanceService.checkIn(buildAttendancePayload(file))
      setTodayAttendance(res.data?.data || null)
      setMessage({ type: 'success', text: res.data?.message || 'Check-in berhasil.' })
      setNote('')
      await fetchAttendances()
    } catch (err) {
      console.error(err)
      if (!shouldSuppressAttendanceError(err)) {
        setMessage({ type: 'error', text: err.response?.data?.message || 'Check-in gagal.' })
      }
    } finally {
      setActionLoading(false)
    }
  }

  const handleCheckOut = async (file = null) => {
    try {
      setActionLoading(true)
      setMessage(null)
      const res = await attendanceService.checkOut(buildAttendancePayload(file))
      setTodayAttendance(res.data?.data || null)
      setMessage({ type: 'success', text: res.data?.message || 'Check-out berhasil.' })
      setNote('')
      await fetchAttendances()
    } catch (err) {
      console.error(err)
      if (!shouldSuppressAttendanceError(err)) {
        setMessage({ type: 'error', text: err.response?.data?.message || 'Check-out gagal.' })
      }
    } finally {
      setActionLoading(false)
    }
  }

  const handlePhotoAction = (event, type) => {
    const file = event.target.files?.[0] || null
    event.target.value = ''

    if (type === 'check_in') {
      handleCheckIn(file)
      return
    }

    handleCheckOut(file)
  }

  const handleFilterChange = (e) => {
    const { name, value } = e.target
    setFilters((prev) => ({ ...prev, [name]: value }))
  }

  const handleApplyFilters = () => {
    fetchAdminAttendances()
  }

  const handleResetFilters = () => {
    setFilters(initialFilters)
    setTimeout(() => fetchAdminAttendances(), 0)
  }

  const canCheckIn = useMemo(
    () => !todayAttendance?.check_in_time,
    [todayAttendance]
  )

  const canCheckOut = useMemo(
    () => !!todayAttendance?.check_in_time && !todayAttendance?.check_out_time,
    [todayAttendance]
  )

  const filteredAdminAttendanceList = useMemo(() => {
    if (!isAdminLike) return attendanceList
    if (!filters.search) return attendanceList

    const keyword = filters.search.toLowerCase()

    return attendanceList.filter((attendance) => {
      const employeeName = attendance.employee?.name || ''
      const department = attendance.employee?.department || ''
      const position = attendance.employee?.position || ''
      const status = attendance.status || ''
      const date = attendance.attendance_date || ''

      return [employeeName, department, position, status, date]
        .some((value) => String(value).toLowerCase().includes(keyword))
    })
  }, [attendanceList, filters.search, isAdminLike])

  if (isAdminLike) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Data Absensi Karyawan</h1>
          <p className="text-gray-600 mt-1">Monitor absensi, lokasi, status telat, dan evidence foto.</p>
        </div>

        {message && <Alert message={message} />}

        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
          <div>
            <h2 className="text-lg font-semibold text-gray-900">Filter Absensi</h2>
            <p className="text-sm text-gray-500 mt-1">Filter berdasarkan employee ID, rentang tanggal, status, dan pencarian cepat.</p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
            <Input label="Employee ID" name="employee_id" value={filters.employee_id} onChange={handleFilterChange} placeholder="Contoh: 1" />
            <Input label="Tanggal Mulai" type="date" name="date_from" value={filters.date_from} onChange={handleFilterChange} />
            <Input label="Tanggal Akhir" type="date" name="date_to" value={filters.date_to} onChange={handleFilterChange} />
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Status</label>
              <select name="status" value={filters.status} onChange={handleFilterChange} className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                <option value="">Semua status</option>
                <option value="present">Present</option>
                <option value="late">Late</option>
                <option value="absent">Absent</option>
                <option value="leave">Leave</option>
              </select>
            </div>
            <Input label="Pencarian Cepat" name="search" value={filters.search} onChange={handleFilterChange} placeholder="Nama, departemen, jabatan" />
          </div>

          <div className="flex items-center gap-3">
            <button onClick={handleApplyFilters} className="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Terapkan Filter</button>
            <button onClick={handleResetFilters} className="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Reset</button>
          </div>
        </div>

        <AttendanceTable rows={filteredAdminAttendanceList} isLoading={isLoading} showEmployee />
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Absensi</h1>
        <p className="text-gray-600 mt-1">Check-in dan check-out harian dengan lokasi dan evidence foto.</p>
      </div>

      {message && <Alert message={message} />}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 lg:col-span-2">
          <div className="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div>
              <h2 className="text-lg font-semibold text-gray-900">Absensi Hari Ini</h2>
              <p className="text-sm text-gray-500 mt-1">{new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
            </div>
            <button onClick={getCurrentLocation} className="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
              <RefreshCw className="w-4 h-4 mr-2" /> Refresh Lokasi
            </button>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
            <InfoCard label="Shift Hari Ini" value={todayShift?.name || todayAttendance?.shift?.name || '-'} subValue={formatShiftTime(todayShift || todayAttendance?.shift)} />
            <InfoCard label="Check In" value={formatTime(todayAttendance?.check_in_time)} subValue={formatStatus(todayAttendance?.status)} />
            <InfoCard label="Check Out" value={formatTime(todayAttendance?.check_out_time)} subValue={todayAttendance?.overtime_minutes ? `Overtime ${todayAttendance.overtime_minutes} menit` : '-'} />
          </div>

          <div className="mt-6">
            <label className="block text-sm font-medium text-gray-700 mb-2">Catatan opsional</label>
            <textarea value={note} onChange={(e) => setNote(e.target.value)} rows={3} className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none" placeholder="Contoh: kerja dari kantor / izin terlambat karena..." />
          </div>

          <div className="flex flex-col sm:flex-row gap-3 mt-6">
            <input ref={checkInPhotoRef} type="file" accept="image/*" capture="user" className="hidden" onChange={(e) => handlePhotoAction(e, 'check_in')} />
            <input ref={checkOutPhotoRef} type="file" accept="image/*" capture="user" className="hidden" onChange={(e) => handlePhotoAction(e, 'check_out')} />

            <button disabled={!canCheckIn || actionLoading} onClick={() => handleCheckIn()} className="px-5 py-3 rounded-lg bg-green-600 text-white font-medium hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
              {actionLoading && canCheckIn ? 'Memproses...' : 'Check In'}
            </button>
            <button disabled={!canCheckIn || actionLoading} onClick={() => checkInPhotoRef.current?.click()} className="inline-flex items-center justify-center px-5 py-3 rounded-lg border border-green-200 text-green-700 font-medium hover:bg-green-50 disabled:opacity-50 disabled:cursor-not-allowed">
              <Camera className="w-4 h-4 mr-2" /> Check In + Foto
            </button>
            <button disabled={!canCheckOut || actionLoading} onClick={() => handleCheckOut()} className="px-5 py-3 rounded-lg bg-red-600 text-white font-medium hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
              {actionLoading && canCheckOut ? 'Memproses...' : 'Check Out'}
            </button>
            <button disabled={!canCheckOut || actionLoading} onClick={() => checkOutPhotoRef.current?.click()} className="inline-flex items-center justify-center px-5 py-3 rounded-lg border border-red-200 text-red-700 font-medium hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed">
              <Camera className="w-4 h-4 mr-2" /> Check Out + Foto
            </button>
          </div>
        </div>

        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
          <h2 className="text-lg font-semibold text-gray-900">Lokasi</h2>
          <p className="text-sm text-gray-500 mt-1">Koordinat dikirim saat check-in/out.</p>
          <div className="mt-4 rounded-xl bg-gray-50 p-4 text-sm text-gray-700 space-y-2">
            <div className="flex items-center gap-2"><MapPin className="w-4 h-4" /> Latitude: {location?.lat ?? '-'}</div>
            <div className="flex items-center gap-2"><MapPin className="w-4 h-4" /> Longitude: {location?.lng ?? '-'}</div>
          </div>
        </div>
      </div>

      <AttendanceTable rows={attendanceList} isLoading={isLoading} />
    </div>
  )
}

const Alert = ({ message }) => (
  <div className={`rounded-xl px-4 py-3 text-sm font-medium ${message.type === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200'}`}>
    {message.text}
  </div>
)

const Input = ({ label, ...props }) => (
  <div>
    <label className="block text-sm font-medium text-gray-700 mb-2">{label}</label>
    <input {...props} className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
  </div>
)

const InfoCard = ({ label, value, subValue }) => (
  <div className="rounded-xl border border-gray-100 bg-gray-50 p-4">
    <p className="text-xs text-gray-500">{label}</p>
    <p className="text-xl font-bold text-gray-900 mt-1">{value || '-'}</p>
    <p className="text-xs text-gray-500 mt-1">{subValue || '-'}</p>
  </div>
)

const AttendanceTable = ({ rows, isLoading, showEmployee = false }) => (
  <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div className="px-6 py-4 border-b border-gray-100">
      <h2 className="text-lg font-semibold text-gray-900">Riwayat Absensi</h2>
    </div>
    <div className="overflow-x-auto">
      <table className="min-w-full divide-y divide-gray-200">
        <thead className="bg-gray-50">
          <tr>
            {showEmployee && <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Karyawan</th>}
            <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tanggal</th>
            <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Shift</th>
            <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Check In</th>
            <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Check Out</th>
            <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Late</th>
            <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Evidence</th>
            <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100">
          {isLoading ? (
            <tr><td colSpan={showEmployee ? 8 : 7} className="px-6 py-8 text-center text-gray-500">Memuat data absensi...</td></tr>
          ) : rows.length === 0 ? (
            <tr><td colSpan={showEmployee ? 8 : 7} className="px-6 py-8 text-center text-gray-500">Belum ada data absensi.</td></tr>
          ) : rows.map((attendance) => (
            <tr key={attendance.id} className="hover:bg-gray-50">
              {showEmployee && <td className="px-6 py-4 text-sm text-gray-700">{attendance.employee?.name || '-'}</td>}
              <td className="px-6 py-4 text-sm text-gray-700">{attendance.attendance_date || '-'}</td>
              <td className="px-6 py-4 text-sm text-gray-700">{attendance.shift?.name || '-'}</td>
              <td className="px-6 py-4 text-sm text-gray-700">{formatTime(attendance.check_in_time)}</td>
              <td className="px-6 py-4 text-sm text-gray-700">{formatTime(attendance.check_out_time)}</td>
              <td className="px-6 py-4 text-sm text-gray-700">{attendance.late_minutes ?? 0} menit</td>
              <td className="px-6 py-4 text-sm text-gray-700">
                <div className="flex gap-2">
                  {attendance.check_in_photo_url && <a className="text-indigo-600 hover:underline" href={attendance.check_in_photo_url} target="_blank" rel="noreferrer">IN</a>}
                  {attendance.check_out_photo_url && <a className="text-indigo-600 hover:underline" href={attendance.check_out_photo_url} target="_blank" rel="noreferrer">OUT</a>}
                  {!attendance.check_in_photo_url && !attendance.check_out_photo_url && '-'}
                </div>
              </td>
              <td className="px-6 py-4"><StatusBadge status={attendance.status} /></td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  </div>
)

const StatusBadge = ({ status }) => {
  const map = {
    present: 'bg-green-100 text-green-700',
    late: 'bg-yellow-100 text-yellow-700',
    absent: 'bg-red-100 text-red-700',
    leave: 'bg-blue-100 text-blue-700',
  }

  return <span className={`px-2 py-1 rounded-full text-xs font-medium ${map[status] || 'bg-gray-100 text-gray-700'}`}>{status || '-'}</span>
}

const formatTime = (value) => {
  if (!value) return '-'
  return String(value).slice(0, 5)
}

const formatShiftTime = (shift) => {
  if (!shift?.start_time || !shift?.end_time) return '-'
  return `${formatTime(shift.start_time)} - ${formatTime(shift.end_time)}${shift.is_overnight ? ' (overnight)' : ''}`
}

const formatStatus = (status) => {
  const map = {
    present: 'Hadir',
    late: 'Terlambat',
    absent: 'Tidak hadir',
    leave: 'Cuti',
  }

  return map[status] || 'Belum check-in'
}

export default AttendancePage
